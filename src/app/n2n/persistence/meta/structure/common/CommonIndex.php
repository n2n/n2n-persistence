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

use n2n\reflection\ArgUtils;

use n2n\persistence\meta\structure\IndexType;

use n2n\core\SysTextUtils;

use n2n\persistence\meta\structure\Table;

use n2n\persistence\meta\structure\Index;

use n2n\persistence\meta\structure\UnknownColumnException;

class CommonIndex implements Index {
	
	private $name;
	private $type;
	/**
	 * @var \n2n\persistence\meta\structure\Table
	 */
	private $table;
	private $columns;
	private $attrs;
	
	public function __construct(Table $table, $name, $type, array $columns) {
		ArgUtils::valEnum($type, array(IndexType::PRIMARY, IndexType::INDEX, IndexType::UNIQUE));
		
		$this->name = $name;
		$this->table = $table;
		$this->type = $type;
		$this->columns = $columns;
	}
	
	public function getType() {
		return $this->type;
	}
	
	public function getName() {
		return $this->name;
	}
	
	public function getColumns() {
		return $this->columns;
	}
	
	public function getColumnByName($name) {
		$columns = $this->getColumns();
		if (isset($columns[$name])) {
			return $columns[$name];
		}
		
		throw new UnknownColumnException('Column with name "'  . $name 
				. '" does not exist in index "' . $this->name . '" for table "' . $this->table->getName() . '"');
	}
	
	public function containsColumnName($name) {
		try {
			$this->getColumnByName($name);
			return true;
		} catch (UnknownColumnException $e) {
			return false;
		}
	}
	
	public function setAttrs($attrs) {
		$this->attrs = $attrs;
	}
	
	public function getAttrs() {
		return $this->attrs;
	}
	
	public function getTable() {
		return $this->table;
	}
	
	public function equals(Index $index) {
		//Don't compare the name if it is a Primary, some DBMS have generated Primary-Key Names
		if (($index->getType() !== $this->getType())
				|| (($this->getType() != IndexType::PRIMARY ) 
						&& ($index->getName() !== $this->getName()))) return false;
		
		return true;
	}
	
}
