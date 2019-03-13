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

use n2n\util\type\ArgUtils;
use n2n\persistence\meta\structure\IndexType;
use n2n\persistence\meta\structure\Table;
use n2n\persistence\meta\structure\Index;
use n2n\persistence\meta\structure\UnknownColumnException;
use n2n\persistence\meta\structure\Column;
use n2n\persistence\meta\structure\DuplicateMetaElementException;

class CommonIndex implements Index {
	
	private $name;
	private $type;
	/**
	 * @var \n2n\persistence\meta\structure\Table
	 */
	private $table;
	private $columns = [];
	private $attrs = [];
	
	public function __construct(Table $table, $name, $type, array $columns) {
		ArgUtils::valEnum($type, array(IndexType::PRIMARY, IndexType::INDEX, IndexType::UNIQUE, IndexType::FOREIGN));
		ArgUtils::valArray($columns, Column::class);
		
		$this->name = $name;
		$this->table = $table;
		$this->type = $type;
		
		foreach ($columns as $column) {
			$this->addColumn($column);
		}
	}
	
	public function getType(): string {
		return $this->type;
	}
	
	public function getName(): string {
		return $this->name;
	}
	
	public function addColumn(Column $column) {
		if ($this->containsColumnName($column->getName())) {
			throw new DuplicateMetaElementException('Duplicate column with name ' . $column->getName() .' found in index ' 
					. $this->name . ' on table ' . $this->table->getName());
		}
		
		$this->columns[] = $column;
	}
	
	public function getColumns(): array {
		return $this->columns;
	}
	
	/**
	 * {@inheritDoc}
	 * @see \n2n\persistence\meta\structure\Index::getColumnByName()
	 * @return Column
	 */
	public function getColumnByName($name): Column {
		foreach ($this->columns as $column) {
			if ($column->getName() == $name) return $column;
		}
		
		throw new UnknownColumnException('Column with name "'  . $name 
				. '" does not exist in index "' . $this->name . '" for table "' . $this->table->getName() . '"');
	}
	
	public function containsColumnName($name): bool {
		try {
			$this->getColumnByName($name);
			return true;
		} catch (UnknownColumnException $e) {
			return false;
		}
	}
	
	public function setAttrs(array $attrs) {
		$this->attrs = $attrs;
	}
	
	public function getAttrs(): array {
		return $this->attrs;
	}
	
	public function getTable(): Table {
		return $this->table;
	}
	
	public function getRefColumns(): array {
		return [];
	}
	
	public function getRefTable(): ?Table {
		return null;
	}
	
	public function equals(Index $index): bool {
		//Don't compare the name if it is a Primary, some DBMS have generated Primary-Key Names
		if (($index->getType() !== $this->getType())
				|| (($this->getType() != IndexType::PRIMARY) 
						&& ($index->getName() !== $this->getName()))) return false;
		
		return true;
	}
}