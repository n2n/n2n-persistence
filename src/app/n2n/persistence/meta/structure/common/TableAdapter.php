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
 * Bert Hofmänner.......: Idea, Frontend UI, Community Leader, Marketing
 * Thomas Günther.......: Developer, Hangar
 */
namespace n2n\persistence\meta\structure\common;

use n2n\persistence\meta\structure\MetaEntity;

use n2n\persistence\meta\structure\Column;
use n2n\persistence\meta\structure\UnknownColumnException;
use n2n\persistence\meta\structure\IndexType;
use n2n\persistence\meta\structure\Index;
use n2n\persistence\meta\structure\Table;
use n2n\persistence\meta\MetaRuntimeException;

abstract class TableAdapter extends MetaEntityAdapter implements Table, ColumnChangeListener {

	private $indexes;
	private $primaryKey;
	private $columns;
	
	public function __construct($name) {
		parent::__construct($name);
		$this->indexes = array();
		$this->columns = array();
	}

	public function getColumns() {
		return $this->columns;
	}

	public function setColumns(array $columns) {
		foreach ($this->columns as $column) {
			$this->removeColumn($column);
		}
		foreach ($columns as $column) {
			$this->addColumn($column);
		}
	}

	public function getColumnByName($name) {
		$columns = $this->getColumns();
		
		if (isset($columns[$name])) {
			return $columns[$name];
		}

		throw new UnknownColumnException('Column "' . $name . '" does not exist in Table "' . $this->getName() . '"');
	}

	public function containsColumnName($name) {
		try {
			$this->getColumnByName($name);
			return true;
		} catch (UnknownColumnException $e) {
			return false;
		}
	}

	public function addColumn(Column $column) {
		$this->triggerChangeListeners();
		$this->columns[$column->getName()] = $column;
		$column->setTable($this);
		$column->registerChangeListener($this);
	}

	public function removeColumnByName($name) {
		$this->removeColumn($this->getColumnByName($name));	
	}

	public function getPrimaryKey() {
		if ((!($this->primaryKey))) {
			$indexes = $this->getIndexes();
			// if the table is not persistent so far, it is possible that it doesn't have a Primary Key
			foreach ($indexes as $index) {
				$index instanceof Index;
				if ($index->getType() == IndexType::PRIMARY) {
					if (!isset($this->primaryKey)) {
						$this->primaryKey = $index;
					} else {
						throw new MetaRuntimeException('Duplicate primary key in table "' . $this->getName() . '"');
					}
				}
			}
		}
		return $this->primaryKey;
	}

	public function getIndexes() {
		return $this->indexes;
	}

	public function setIndexes(array $indexes) {
		$this->triggerChangeListeners();
		$this->indexes = $indexes;
	}

	public function removeIndexByName($name) {
		if (!isset($this->indexes[$name])) return;
		$this->triggerChangeListeners();
		unset($this->indexes[$name]);
	}


	protected function applyColumnsFrom(Table $table) {
		$columns = array();
		foreach ($table->getColumns() as $column) {
			$columns[$column->getName()] = $column->copy();
		}
		$this->setColumns($columns);
	}
	
	protected function applyIndexesFrom(Table $table) {
		$indexes = array();
		
		foreach ($table->getIndexes() as $index) {
			$indexColumnsNames = array();
			foreach ($index->getColumns() as $indexColumn) {
				$indexColumnsNames[] = $indexColumn->getName();
			}
			$name = ($index->getType() == IndexType::PRIMARY) ? $this->generatePrimaryKeyName() : $index->getName();
			$indexes[$name] = $this->createIndex($index->getType(), $indexColumnsNames, $name);
		}

		$this->setIndexes($indexes);
	}
	
	public function removeAllColumns() {
		foreach ($this->columns as $column) {
			$this->removeColumn($column);
		}
	}

	public function removeAllIndexes() {
		$this->triggerChangeListeners();
		$this->indexes = array();
	}

	public function equals(MetaEntity $metaEntity) {
		if (!(parent::equals($metaEntity) 
				&& (count($this->columns) == count($metaEntity->getColumns())) 
				&& (count($this->indexes) == count($metaEntity->getIndexes())))) return false;
		//Check Columns
		foreach ($this->columns as $columnName => $column) {
			if (!($metaEntity->containsColumnName($columnName))) return false;
			if (!($column->equals($metaEntity->getColumnByName($columnName)))) return false;
		}
		//Check Indexes
		$otherIndexes = $metaEntity->getIndexes();
		foreach ($this->indexes as $indexName => $index) {
			if (!(isset($otherIndexes[$indexName]))) return false;
			if (!($index->equals($otherIndexes[$indexName]))) return false;
		}
		return true;
	}

	public function onColumnChange(Column $column) {
		$this->triggerChangeListeners();
	}
	
	protected function generateColumnsForNames(array $columnNames) {
		$columns = array();
		foreach ($columnNames as $columnName) {
			$columns[$columnName] = $this->getColumnByName($columnName);
		}
		return $columns;
	}
	
	public function createIndex($type, array $columnNames, $name = null) {
		if (!$name) {
			$name = $this->generateIndexKeyName($type);
		}
		$this->triggerChangeListeners();
	
		$newIndex = new CommonIndex($this, $name, $type, $this->generateColumnsForNames($columnNames));
			
		$this->indexes[$name] = $newIndex;
		return $newIndex;
	}

	protected function generateIndexKeyName($type) {
		$name = null;
		if ($type == IndexType::PRIMARY) {
			$name = $this->generatePrimaryKeyName();
		}
		
		if (!$name) {
			for ($i = 1; $i <= PHP_INT_MAX; $i++) {
				$name = $this->getName() . '_index_' . $i;
				if (array_key_exists($name, $this->indexes)) {
					continue;
				}
				break;
			}
			if ($i == PHP_INT_MAX) {
				$name = null;
			}
		}
		return $name;
	}
	
	private function removeColumn(Column $column) {
		$this->triggerChangeListeners();
		$column->unregisterChangeListener($this);
		unset($this->columns[$column->getName()]);
	}
	
	public abstract function generatePrimaryKeyName();
}
