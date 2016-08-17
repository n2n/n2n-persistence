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

use n2n\persistence\meta\structure\Column;
use n2n\persistence\meta\structure\ColumnFactory;
use n2n\persistence\meta\impl\pgsql\PgsqlColumnFactory;
use n2n\persistence\meta\structure\IndexType;
use n2n\persistence\meta\impl\pgsql\PgsqlColumn;
use n2n\persistence\meta\structure\UnknownColumnException;
use n2n\persistence\meta\structure\common\ColumnChangeListener;
use n2n\persistence\meta\structure\Table;
use n2n\core\SysTextUtils;
use n2n\persistence\meta\structure\DuplicateMetaElementException;

class PgsqlTable extends PgsqlMetaEntity implements Table, ColumnChangeListener {
	private $columns;
	private $indexes;
	private $columnFactory;

	/**
	 * @param String $name
	 * @param array $columns
	 */
	public function __construct($name) {
		parent::__construct($name);
	}

	/**
	 * (non-PHPdoc)
	 * @see n2n\persistence\meta.Table::getColumns()
	 */
	public function getColumns() {
		return $this->columns;
	}

	/**
	 * (non-PHPdoc)
	 * @see n2n\persistence\meta.Table::containsColumnName()
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
	 * (non-PHPdoc)
	 * @see n2n\persistence\meta.Table::getColumnByName()
	 */
	public function getColumnByName($name) {
		foreach ($this->getColumns() as $column) {
			if ($column->getName() == $name) {
				return $column;
			}
		}
		throw new UnknownColumnException(SysTextUtils::get('n2n_error_persistence_meta_pgsql_column_does_not_exist',
			array('databaseName' => $this->getDatabase()->getName(), 'entityName' => $this->getName(), 'columnName' => $name)));
	}

	/**
	 * (non-PHPdoc)
	 * @see n2n\persistence\meta.Table::setColumns()
	 */
	public function setColumns(array $columns) {
		if (sizeof($this->getColumns())) $this->removeAllColumns();
		foreach ($columns as $column) {
			$this->addColumn($column);
		}
	}

	/**
	 * @param PgsqlColumn $column
	 */
	public function addColumn(PgsqlColumn $column) {
		$this->triggerChangeListeners();
		$this->columns[$column->getName()] = $column;
		$column->setTable($this);
		$column->registerChangeListener($this);
	}

	/**
	 * (non-PHPdoc)
	 * @see n2n\persistence\meta.Table::removeColumnByName()
	 */
	public function removeColumnByName($name) {
		$this->triggerChangeListeners();
		$column = $this->getColumnByName($name);
		$column->unregisterChangeListener($this);

		unset($this->columns[$column->getName()]);
	}

	/**
	 * (non-PHPdoc)
	 * @see n2n\persistence\meta.Table::removeAllColumns()
	 */
	public function removeAllColumns() {
		$columns = $this->getColumns();
		foreach ($columns as $column) {
			$this->removeColumnByName($column->getName());
		}
	}

	/**
	 * (non-PHPdoc)
	 * @see n2n\persistence\meta.Table::getPrimaryKey()
	 */
	public function getPrimaryKey() {
		foreach ($this->getIndexes() as $index) {
			if ($index->getType() != IndexType::PRIMARY) continue; 
			return $index;
		}
		return null;
	}

	/**
	 * @param array $indexes
	 */
	private function setIndexes(array $indexes) {
		$this->triggerChangeListeners();
		$this->indexes = $indexes;
	}

	/**
	 * (non-PHPdoc)
	 * @see n2n\persistence\meta.Table::getIndexes()
	 */
	public function getIndexes() {
		return $this->indexes;
	}

	/**
	 * (non-PHPdoc)
	 * @see n2n\persistence\meta.Table::createIndex()
	 */
	public function createIndex($type, array $columnNames, $name = null) {
		if (!is_null($name)) {
			if (sizeof($this->getIndexes())) {
				foreach ($this->getIndexes() as $index) {
					if ($index->getType() == $type && $index->getName() == $name) {
						foreach ($index->getColumns() as $column) {
							$columnNameArray[] = $column->getName();
						}
						if ($columnNameArray === $columnNames) {
							throw new DuplicateMetaElementException(SysTextUtils::get('n2n_error_persistence_meta_pgsql_index_object_already_exist',
									array('databaseName' => $this->getDatabase()->getName(), 'entityName' => $this->getName(), 'indexName' => $name)));
						}
					}
				}
			}
		}

		if (is_null($name)) {
			for ($i = 0; $i < PHP_INT_MAX; $i++) {
				$name = 'index_' . $type . '_' . $i;
				if (!sizeof($this->getIndexes())) break;
				if (in_array($name, $this->getIndexes())) continue;
				break;
			}
		}

		$columns = array();
		foreach ($columnNames as $columnName) {
			$columns[$name] = $this->getColumnByName($columnName);
		}

		$newIndex = new PgsqlIndex($this, $name, $type, $columns);
		$this->triggerChangeListeners();
		$this->indexes[$name] = $newIndex;
		return $newIndex;
	}

	/**
	 * (non-PHPdoc)
	 * @see n2n\persistence\meta.Table::removeIndexByName()
	 */
	public function removeIndexByName($name) {
		$this->triggerChangeListeners();
		unset($this->indexes[$name]);
	}

	/**
	 * (non-PHPdoc)
	 * @see n2n\persistence\meta.Table::removeAllIndexes()
	 */
	public function removeAllIndexes() {
		$this->triggerChangeListeners();
		$this->setIndexes(array());
	}

	/**
	 * (non-PHPdoc)
	 * @see n2n\persistence\meta.Table::copy()
	 */
	public function copy($newTableName = null) {
		if (is_null($newTableName)) $newTableName = $this->getName();

		$newTable = new PgsqlTable($newTableName);

		$newColumns = array();
		foreach ($this->getColumns() as $column) {
			$newColumns[$column->getName()] = $column;
		}
		$newTable->setColumns($newColumns);

		$newIndexes = array();
		foreach ($this->getIndexes() as $index) {
			$newIndexColumns = array();
			foreach ($index->getColumns() as $column) {
				$newIndexColumns[$column->getName()] = $column;
			}
			$newIndexes[$index->getName()] = new PgsqlIndex($newTable, $index->getName(), $index->getType(), $newIndexColumns);
		}
		$newTable->setIndexes($newIndexes);

		return $newTable;
	}

	/**
	 * @param ColumnFactory $columnFactory
	 */
	private function setColumnFactory(ColumnFactory $columnFactory) {
		$this->columnFactory = $columnFactory;
	}

	/**
	 * @return PgsqlColumnFactory
	 */
	private function getColumnFactory() {
		return $this->columnFactory;
	}

	/**
	 * (non-PHPdoc)
	 * @see n2n\persistence\meta.Table::createColumnFactory()
	 */
	public function createColumnFactory() {
		if (is_null($this->getColumnFactory())) {
			$this->setColumnFactory(new PgsqlColumnFactory($this));
		}
		return $this->getColumnFactory();
	}

	public function onColumnChange(Column $column) {
		$this->triggerChangeListeners();
	}
}
