<?php
/*
 * Copyright (c) 2012-2016, Hofmänner New Media.
 * DO NOT ALTER OR REMOVE COPYRIGHT NOTICES OR THIS FILE HEADER.
 *
 * This file is part of the N2N FRAMEWORK.
 *
 * The N2N FRAMEWORK is free software: you can redistribute it and/or modify it under the terms of
 * the GNU Lesser General Public License as published by the Free Software Foundation, either
 * version 2.1 of the License, or (at your option) any later version.
 *
 * N2N is distributed in the hope that it will be useful, but WITHOUT ANY WARRANTY; without even
 * the implied warranty of MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU Lesser General Public License for more details: http://www.gnu.org/licenses/
 *
 * The following people participated in this project:
 *
 * Andreas von Burg.....: Architect, Lead Developer
 * Bert Hofmänner.......: Idea, Community Leader, Marketing
 * Thomas Günther.......: Developer, Hangar
 */
namespace n2n\persistence\meta\impl\pgsql;

use n2n\persistence\meta\data\StatementQueryResult;

use n2n\persistence\Pdo;
use n2n\persistence\meta\data\QueryResult;
use n2n\persistence\meta\data\QueryComparator;
use n2n\persistence\meta\data\QueryItem;
use n2n\persistence\meta\data\SelectStatementBuilder;
use n2n\reflection\ArgUtils;
use n2n\persistence\meta\data\JoinType;

/**
 * NotCompletedFinishYet
 *
 */
class PgsqlSelectStatementBuilder implements SelectStatementBuilder {
	const DEFAULT_LIMIT_NUM = 30;

	private $dbh;
	private $whereSelector;

	private $columns = array();
	private $froms = array();
	private $joins = array();
	private $groups = array();
	private $orders = array();
	private $limit;
	private $num;
	private $distinct;
	private $havingComparator;

	public function __construct(Pdo $dbh) {
		$this->dbh = $dbh;
		$this->whereSelector = new QueryComparator();
		$this->havingComparator = new QueryComparator();
	}

	public function addSelectColumn(QueryItem $item, $asName = null) {
		if (!is_null($asName)) {
			$this->columns[] = array('columnItem' => $item);
		} else {
			$this->columns[] = array('columnItem' => $item, 'asName' => $asName);
		}
	}

	public function addFrom(QueryResult $queryResult, $tableAlias = null) {
		$this->froms[] = array('tableName' => $queryResult->getName(), 'tableAlias' => $tableAlias);
	}

	public function addJoin(QueryResult $queryResult, $alias, $joinType, QueryComparator $onComparator = null) {
		ArgUtils::valEnum($joinType, JoinType::getValues());
		if ($onComparator === null) {
			$onComparator = new QueryComparator();
		}
		$this->joins[] = array('tableName' => $tableName, 'tableAlias' => $alias, 'joinType' => $joinType, 'onSelector' => $onComparator);
		return $onComparator;
	}

	public function getWhereComparator() {
		return $this->whereSelector;
	}

	public function addGroup(QueryItem $queryItem) {
		$this->groups[] = array('groupItem' => $queryItem);
	}

	public function addOrderBy(QueryItem $queryItem, $direction) {
		$this->orders[] = array('orderItem' => $queryItem, 'direction' => $direction);
	}

	public function setLimit($limit, $num = null) {
		$this->limit = intval($limit);
		$this->num = (!is_null($num) ? intval($num) : intval(self::DEFAULT_LIMIT_NUM));
	}

	public function toSqlString() {
		if (is_null($this->froms)) return;

		return $this->selectSqlBuilder() . ' ' . $this->fromSqlBuilder() . ' ' . $this->joinSqlBuilder() . ' ' . $this->whereSqlBuilder()
				. ' ' . $this->groupBySqlBuilder() . ' ' . $this->orderBySqlBuilder() . ' ' . $this->limitSqlBuilder() . ';' . "\n";
	}

	private function selectSqlBuilder() {
		$sqlString = ' SELECT ';
		$sqlString .= $this->distinctSqlBuilder();

		if (sizeof($this->columns)) {
			$columnArray = array();
			foreach ($this->columns as $column) {
				$fragBuilder = $this->getItemQueryFragmentBuilder($column['columnItem']);
				if(isset($column['asName'])) $fragBuilder->addFieldAlias($column['asName']);
				$columnArray[] = $fragBuilder->toSql();
			}
			$sqlString .= implode(', ', $columnArray);
		} else {
			$sqlString .= ' * ';
		}
		return $sqlString;
	}

	private function distinctSqlBuilder() {
		if (!is_null($this->getDistinct())) {
			return ' DISTINCT ON (' . $this->dbh->quoteField($this->getDistinct()) . ') ';
		}
		return null;
	}

	private function fromSqlBuilder(){
		$sqlString = ' FROM ';
		$fromArray = array();
		foreach ($this->froms as $from) {
			$fromArray[] = $from['tableName'] . (!is_null($from['tableAlias']) ? ' AS ' . $from['tableAlias'] : '');
		}
		$sqlString .= implode(', ', $fromArray);
		return $sqlString;
	}

	private function joinSqlBuilder() {
		$joinArray = array();
		foreach ($this->joins as $join) {
			$joinString = ' ' . $join['joinType'] . ' ' . $join['tableName'] . (isset($join['tableAlias']) ? ' AS ' . $join['tableAlias'] : '');

			if (!$join['onSelector']->isEmpty()) {
				$fragmentBuilder = new PgsqlQueryFragmentBuilder($this->dbh);
				$join['onSelector']->buildQueryFragment($fragmentBuilder);
				$joinString .= ' ON ' . $fragmentBuilder->toSql();
			}

			$joinArray[] = $joinString;
		}
		return implode(', ', $joinArray);
	}

	private function whereSqlBuilder() {
		$compareSql = $this->getCompareBuilderSql($this->whereSelector);
		if (!is_null($compareSql)) {
			return ' WHERE ' . $compareSql;
		}
		return null;
	}

	private function groupBySqlBuilder() {
		if (sizeof($this->groups)) {
			$groupByArray = array();
			foreach ($this->groups as $group) {
				$groupByArray[] = $this->getItemQueryFragmentBuilder($group['groupItem'])->toSql();
			}
			return ' GROUP BY ' . implode(',', $groupByArray) . ' ' . $this->havingSqlBuilder();
		}
		return null;
	}

	private function orderBySqlBuilder() {
		if (sizeof($this->orders)) {
			$orderArray = array();
			foreach ($this->orders as $order) {
				$fragmentBuilder = $this->getItemQueryFragmentBuilder($order['orderItem']);
				$fragmentBuilder->addOperator($order['direction']);
				$orderArray[] = $fragmentBuilder->toSql();
			}
			return ' ORDER BY ' . implode(', ', $orderArray);
		}
		return null;
	}

	private function havingSqlBuilder() {
		$compareSql = $this->getCompareBuilderSql($this->havingComparator);
		if (!is_null($compareSql)) {
			return ' HAVING ' . $compareSql;
		}
		return null;
	}

	private function getCompareBuilderSql(QueryComparator $comparator) {
		if (is_null($comparator) || $comparator->isEmpty()) return;

		$fragBuilder = new PgsqlQueryFragmentBuilder($this->dbh);
		$comparator->buildQueryFragment($fragBuilder);
		return $fragBuilder->toSql();
	}

	private function limitSqlBuilder() {
		if (!is_null($this->limit)) {
			return ' LIMIT ' . $this->limit . (!is_null($this->num) ? ', ' . intval($this->num) : '');
		}
		return;
	}

	private function getItemQueryFragmentBuilder(QueryItem $item) {
		$fragmentBuilder = new PgsqlQueryFragmentBuilder($this->dbh);
		$item->buildItem($fragmentBuilder);
		return $fragmentBuilder;
	}

	public function setDistinct($distinct) {
		$this->distinct = $distinct;
	}

	public function getDistinct() {
		return $this->distinct;
	}

	public function getHavingComparator() {
		return $this->havingComparator;
	}

	public function toQueryResult() {
		return new StatementQueryResult($this->toSqlString());
	}
}
