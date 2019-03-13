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

use n2n\persistence\meta\structure\Column;
use n2n\persistence\meta\structure\UnknownColumnException;
use n2n\persistence\meta\structure\IndexType;
use n2n\persistence\meta\structure\Index;
use n2n\persistence\meta\structure\Table;
use n2n\persistence\meta\MetaRuntimeException;
use n2n\util\type\CastUtils;
use n2n\persistence\meta\structure\DuplicateMetaElementException;
use n2n\util\type\ArgUtils;

abstract class TableAdapter extends MetaEntityAdapter implements Table, ColumnChangeListener {

	private $indexes;
	private $primaryKey;
	private $columns;
	
	public function __construct(string $name) {
		parent::__construct($name);
		$this->indexes = array();
		$this->columns = array();
	}

	/**
	 * {@inheritDoc}
	 * @see \n2n\persistence\meta\structure\Table::getColumns()
	 * @return Column[]
	 */
	public function getColumns(): array {
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

	public function getColumnByName(string $name): Column {
		foreach ($this->getColumns() as $column) {
			if ($column->getName() == $name) return $column;
		}

		throw new UnknownColumnException('Column "' . $name . '" does not exist in Table "' . $this->getName() . '"');
	}

	public function containsColumnName(string $name): bool {
		try {
			$this->getColumnByName($name);
			return true;
		} catch (UnknownColumnException $e) {
			return false;
		}
	}

	public function addColumn(Column $column) {
		if ($this->containsColumnName($column->getName())) {
			throw new DuplicateMetaElementException('Duplicate column ' . $column->getName() . ' on table "' . $this->getName() .'"'); 
		}
		
		$this->triggerChangeListeners();
		$this->columns[] = $column;
		
		CastUtils::assertTrue($column instanceof CommonColumn);
		$column->setTable($this);
		$column->registerChangeListener($this);
	}

	public function removeColumnByName(string $name) {
		$this->removeColumn($this->getColumnByName($name));	
	}

	/**
	 * {@inheritDoc}
	 * @see \n2n\persistence\meta\structure\Table::getPrimaryKey()
	 * @return Index
	 */
	public function getPrimaryKey(): ?Index {
		if (null === $this->primaryKey) {
			// if the table is not persistent so far, it is possible that it doesn't have a Primary Key
			foreach ($this->getIndexes() as $index) {
				if ($index->getType() == IndexType::PRIMARY) {
					if (null === $this->primaryKey) {
						$this->primaryKey = $index;
					} else {
						throw new MetaRuntimeException('Duplicate primary key in table "' . $this->getName() . '"');
					}
				}
			}
		}
		
		return $this->primaryKey;
	}

	/**
	 * {@inheritDoc}
	 * @see \n2n\persistence\meta\structure\Table::getIndexes()
	 */
	public function getIndexes(): array {
		return $this->indexes;
	}

	public function setIndexes(array $indexes) {
		$this->triggerChangeListeners();
		$this->indexes = $indexes;
	}

	public function removeIndexByName(string $name) {
		foreach ($this->indexes as $key => $index) {
			if ($index->getName() != $name) continue;
			
			unset($this->indexes[$key]);
			$this->triggerChangeListeners();
			return;
		}
	}

	protected function applyColumnsFrom(Table $table) {
		$columns = array();
		foreach ($table->getColumns() as $column) {
			$columns[] = $column->copy();
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
			$indexes[] = $this->createIndex($index->getType(), $indexColumnsNames, $name, 
					$index->getRefTable(), $index->getRefColumns());
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

	public function equals($obj): bool {
		$check = $obj->getName() == $this->getName() && $this->getName() == 'comptusch';
		if (!($obj instanceof TableAdapter && $obj->getName() === $this->getName() 
				&& count($this->columns) == count($obj->getColumns())
				&& count($this->indexes) == count($obj->getIndexes()))) {
			return false;
		}
		
		//Check Columns
		foreach ($this->columns as $column) {
			if (!($obj->containsColumnName($column->getName()))) return false;
			if (!($column->equals($obj->getColumnByName($column->getName())))) return false;
		}
		
		//Check Indexes
		foreach ($this->indexes as $index) {
			if (!$obj->containsIndexName($index->getName())) return false;
			if (!$index->equals($obj->getIndexByName($index->getName()))) return false;
		}
		
		return true;
	}

	public function onColumnChange(Column $column) {
		$this->triggerChangeListeners();
	}
	
	public function generateColumnsForNames(array $columnNames) {
		$columns = array();
		foreach ($columnNames as $columnName) {
			$columns[$columnName] = $this->getColumnByName($columnName);
		}
		return $columns;
	}
	
	/**
	 * {@inheritDoc}
	 * @see \n2n\persistence\meta\structure\Table::createIndex()
	 */
	public function createIndex(string $type, array $columnNames, ?string $name = null,
			?Table $refTable = null, ?array $refColumnNames = null): Index {
		$name = $name ?? $this->generateIndexKeyName($type);
		$columns = $this->generateColumnsForNames($columnNames);
		$this->triggerChangeListeners();
		
		if ($type !== IndexType::FOREIGN) {
			ArgUtils::assertTrue(empty($refColumnNames) && null === $refTable);
			$newIndex = new CommonIndex($this, $name, $type, $columns);
		} else {
			$refColumns = [];
			foreach ($refColumnNames as $refColumnName) {
				$refColumns[] = $refTable->getColumnByName($refColumnName);
			}
			$newIndex = new ForeignIndex($this, $name, $columns, $refTable, $refColumns);
		}
			
		$this->indexes[] = $newIndex;
		return $newIndex;
	}
	
	public function getIndexByName(string $name): Index {
		foreach ($this->getIndexes() as $index) {
			if ($index->getName() == $name) return $index;
		}
		
		throw new UnknownColumnException('Index "' . $index->getName() 
				. '" does not exist in Table "' . $this->getName() . '"');
	}
	
	public function containsIndexName(string $name): bool {
		foreach ($this->getIndexes() as $index) {
			if ($index->getName() === $name) return true;
		}
		
		return false;
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
	
	private function removeColumn(CommonColumn $column) {
		foreach ($this->columns as $key => $aColumn) {
			if (!$column->equals($aColumn)) continue;
			
			unset($this->columns[$key]);
			$this->triggerChangeListeners();
			$column->unregisterChangeListener($this);
			return;
		}
	}
	
	public abstract function generatePrimaryKeyName();
}
