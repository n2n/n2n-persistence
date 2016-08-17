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

use n2n\persistence\meta\structure\UnknownColumnException;
use n2n\persistence\meta\structure\Index;
use n2n\core\SysTextUtils;

class PgsqlIndex implements Index {
	const TYPE_CHECK = "CHECK";

	private $table;
	private $name;
	private $type;
	private $columns;
	private $attrs;

	/**
	 * @param PgsqlTable $table
	 * @param Stirng $name
	 * @param String $type
	 * @param array $columns
	 * @param array $attrs
	 */
	public function __construct(PgsqlTable $table, $name, $type, array $columns, array $attrs = null) {
		$this->setTable($table);
		$this->setName($name);
		$this->setType($type);
		$this->setColumns($columns);
		if (!is_null($attrs)) $this->setAttrs($attrs);
	}

	/**
	 * @param String $type
	 */
	private function setType($type) {
		$this->type = $type;
	}

	/**
	 * (non-PHPdoc)
	 * @see n2n\persistence\meta.Index::getType()
	 */
	public function getType() {
		return $this->type;
	}

	/**
	 * @param String $name
	 */
	private function setName($name) {
		$this->name = $name;
	}

	/**
	 * (non-PHPdoc)
	 * @see n2n\persistence\meta.Index::getName()
	 */
	public function getName() {
		return $this->name;
	}

	/**
	 * @param PgsqlTable $table
	 */
	private function setTable(PgsqlTable $table) {
		$this->table = $table;
	}

	/**
	 * (non-PHPdoc)
	 * @see n2n\persistence\meta.Index::getTable()
	 */
	public function getTable() {
		return $this->table;
	}

	/**
	 * @param array $columns
	 */
	private function setColumns(array $columns) {
		$this->columns = $columns;
	}

	/**
	 * (non-PHPdoc)
	 * @see n2n\persistence\meta.Index::getColumns()
	 */
	public function getColumns() {
		return $this->columns;
	}

	/**
	 * (non-PHPdoc)
	 * @see n2n\persistence\meta.Index::getColumnByName()
	 */
	public function getColumnByName($name) {
		$columns = $this->getColumns();
		if (in_array($name, $columns)) {
			return $columns[$name];
		}
		throw new UnknownColumnException(SysTextUtils::get('n2n_error_persistence_meta_pgsql_column_does_not_exist_in_index',
				array('databaseName' => $this->getTable()->getDatabase()->getName(), 'entityName' => $this->getTable()->getName(), 'indexName' => $this->getName(), 'columnName' => $name)));
	}

	/**
	 * (non-PHPdoc)
	 * @see n2n\persistence\meta.Index::containsColumnName()
	 */
	public function containsColumnName($name) {
		try {
			$this->getColumnByName($name);
			return true;
		} catch (UnknownColumnException $e) {
			return false;
		}
	}

	/**
	 * @param array $attrs
	 */
	public function setAttrs(array $attrs) {
		$this->attrs = $attrs;
	}

	/**
	 * (non-PHPdoc)
	 * @see n2n\persistence\meta.Index::getAttrs()
	 */
	public function getAttrs() {
		return $this->attrs;
	}
}
