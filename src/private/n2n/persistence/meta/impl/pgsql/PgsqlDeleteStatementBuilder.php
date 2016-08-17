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

use n2n\persistence\Pdo;
use n2n\persistence\meta\data\QueryComparator;
use n2n\persistence\meta\data\DeleteStatementBuilder;

/**
 * NotCompletedImplementedYet
 * @author Thiruban
 *
 */
class PgsqlDeleteStatementBuilder implements DeleteStatementBuilder {
	private $table;
	private $whereSelector;

	public function __construct(Pdo $dbh) {
		$this->dbh = $dbh;
		$this->whereSelector = new QueryComparator();
	}

	public function setTable($tableName, $tableAlias = null) {
		if (!is_null($tableAlias)) {
			$this->table = array('tableName' => $tableName, 'tableAlias' => $tableAlias);
		} else {
			$this->table = array('tableName' => $tableName);
		}
	}

	/**
	 * @return QueryComparator
	 */
	public function getWhereComparator() {
		return $this->whereSelector;
	}

	public function toSqlString() {
		return $this->deleteSqlBuilder() . $this->whereSqlBuilder() . ';' . "\n";
	}

	private function deleteSqlBuilder() {
		$sql = 'DELETE FROM ' . $this->dbh->quoteField($this->table['tableName']);

		if (isset($this->table['tableAlias']) && !is_null($this->table['tableAlias'])) {
			$sql .= ' AS ' . $this->dbh->quoteField($this->table['tableAlias']);
		}
		return $sql;
	}

	private function whereSqlBuilder() {
		if (is_null($this->getWhereComparator()) || $this->getWhereComparator()->isEmpty()) return;

		$builder = new PgsqlQueryFragmentBuilder($this->dbh);
		$this->getWhereComparator()->buildQueryFragment($builder);
		return ' WHERE ' . $builder->toSql();
	}
}
