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

use n2n\persistence\meta\data\UpdateStatementBuilder;
use n2n\persistence\meta\data\QueryColumn;
use n2n\persistence\meta\data\QueryItem;
use n2n\persistence\meta\data\QueryComparator;
use n2n\persistence\Pdo;

class PgsqlUpdateStatementBuilder implements UpdateStatementBuilder {
	private $whereSelector;
	private $dbh;

	private $table;
	private $columns = array();

	public function __construct(Pdo $dbh) {
		$this->dbh = $dbh;
		$this->whereSelector = new QueryComparator();
	}

	public function getWhereComparator() {
		return $this->whereSelector;
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
		if (!isset($this->table['tableName'])) return '';

		return $this->updateTableSql() . $this->setColumnsSql() . ' ' . $this->whereSql() . ';' . "\n";
	}

	private function updateTableSql() {
		return ' UPDATE ' . $this->table['tableName'] . (isset($this->table['tableAlias']) ? ' AS ' . $this->table['tableAlias'] : '') . ' SET ';
	}

	private function setColumnsSql() {
		if (!sizeof($this->columns)) return '';

		$setArray = array();
		foreach ($this->columns as $column) {
			$fragmentBuilder = new PgsqlQueryFragmentBuilder($this->dbh);
			$column['queryColumn']->buildItem($fragmentBuilder);
			$fragmentBuilder->addOperator('=');
			$column['queryItem']->buildItem($fragmentBuilder);

			$setArray[] = $fragmentBuilder->toSql();
		}
		return implode(',', $setArray);
	}

	private function whereSql() {
		if ($this->getWhereComparator()->isEmpty() || !is_null($this->getWhereComparator())) return '';

		$fragBuilder = new PgsqlQueryFragmentBuilder($this->dbh);
		$this->whereSelector->buildQueryFragment($fragBuilder);
		return ' WHERE ' . $fragBuilder->toSql();
	}
}
