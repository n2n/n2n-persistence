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

use n2n\persistence\meta\data\InsertValueGroup;
use n2n\persistence\meta\data\InsertStatementBuilder;
use n2n\persistence\meta\data\QueryColumn;
use n2n\persistence\meta\data\QueryItem;
use n2n\persistence\Pdo;

class PgsqlInsertStatementBuilder implements InsertStatementBuilder {
	private $dbh;
	private $table;
	private $columns = array();
	private $valueGroups = array();

	public function __construct(Pdo $dbh) {
		$this->dbh = $dbh;
	}

	public function setTable($tableName, $tableAlias = null) {
		if (is_null($tableAlias)) {
			$this->table = array('tableName' => $tableName);
		} else {
			$this->table = array('tableName' => $tableName, 'tableAlias' => $tableAlias);
		}
	}

	public function addColumn(QueryColumn $column, QueryItem $value) {
		$this->columns[] = array('queryColumn' => $column, 'queryItem' => $value);
	}

	public function toSqlString() {
		if (!isset($this->table['tableName']) || (!sizeof($this->columns) && !sizeof($this->valueGroups))) return '';

		$columnArray = array();
		$columnValueArray = array();
		if (sizeof($this->columns)) {
			foreach ($this->columns as $column) {
				$columnArray[] = $this->dbh->quoteField($column['queryColumn']->getColumnName());
				$fragBuilder = new PgsqlQueryFragmentBuilder($this->dbh);
				$column['queryItem']->buildItem($fragBuilder);
				$columnValueArray[] = $fragBuilder->toSql();
			}
			$insertColumnSql = '(' . implode(',', $columnArray) . ')';
			$insertValueSql = '(' . implode(',',$columnValueArray) . ')';
		} elseif (sizeof($this->valueGroups)) {
			$insertColumnSql = '';
			$insertValueSql = $this->buildAdditionalValueGroupSql();
		}
		return ' INSERT INTO ' . $this->dbh->quoteField($this->table['tableName']) . $insertColumnSql . ' VALUES ' . $insertValueSql . ';' . "\n";
	}

	public function createAdditionalValueGroup() {
		$valueGroup = new InsertValueGroup();
		$this->valueGroups[] = $valueGroup;
		return $valueGroup;
	}

	private function buildAdditionalValueGroupSql() {
		if (!sizeof($this->valueGroups) && is_array($this->valueGroups)) return null;

		$valueGroupArray = array();
		foreach ($this->valueGroups as $valueGroup) {
			$valueArray = array();
			foreach ($valueGroup->getValues() as $value) {
				$fragBuilder = new PgsqlQueryFragmentBuilder($this->dbh);
				$value->buildItem($fragBuilder);
				$valueArray[] = $fragBuilder->toSql();
			}
			$valueGroupArray[] = '(' . implode(',', $valueArray) . ')';
		}
		return implode(',', $valueGroupArray);
	}
}
