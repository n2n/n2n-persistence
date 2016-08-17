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
namespace n2n\persistence\meta\impl\mysql;

use n2n\persistence\meta\structure\common\CommonFloatingPointColumn;

use n2n\persistence\meta\structure\common\CommonFixedPointColumn;

use n2n\persistence\meta\structure\common\CommonEnumColumn;

use n2n\persistence\meta\structure\common\CommonBinaryColumn;

use n2n\persistence\meta\structure\common\CommonTextColumn;

use n2n\persistence\meta\structure\common\CommonStringColumn;

use n2n\persistence\meta\structure\ColumnFactory;

class MysqlColumnFactory implements ColumnFactory {
	
	/**
	 * @var n2n\persistence\meta\impl\mysql\MysqlTable
	 */
	private $table;
	
	public function __construct(MysqlTable $table) {
		$this->table = $table;
	}
	
	public function getTable() {
		return $this->table;
	}
	
	public function createIntegerColumn($name, $size, $signed = true) {
		$column = new MysqlIntegerColumn($name, $size, $signed);
		$this->table->addColumn($column);
		return $column;
	}
	
	public function createStringColumn($name, $length, $charset = null) {
		$column = new CommonStringColumn($name, $length, $charset);
		$this->table->addColumn($column);
		return $column;
	}
	
	public function createTextColumn($name, $size, $charset = null) {
		$column = new CommonTextColumn($name, $size, $charset);
		$this->table->addColumn($column);
		return $column;
	}
	
	public function createBinaryColumn($name, $size) {
		$column = new CommonBinaryColumn($name, $size);
		$this->table->addColumn($column);
		return $column;
	}
	
	public function createDateTimeColumn($name, $dateAvailable = true, $timeAvailable = true) {
		$column = new MysqlDateTimeColumn($name, $dateAvailable, $timeAvailable);
		$this->table->addColumn($column);
		return $column;
	}
	
	public function createEnumColumn($name, array $values) {
		$column = new CommonEnumColumn($name, $values);
		$this->table->addColumn($column);
		return $column;
	}
	
	public function createFixedPointColumn($name, $numIntegerDigits, $numDecimalDigits) {
		$column = new CommonFixedPointColumn($name, $numIntegerDigits, $numDecimalDigits);
		$this->table->addColumn($column);
		return $column;
	}
	
	public function createFloatingPointColumn($name, $size) {
		$column = new CommonFloatingPointColumn($name, $size);
		$this->table->addColumn($column);
		return $column;
	}
	
}
