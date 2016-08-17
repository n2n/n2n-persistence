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
namespace n2n\persistence\meta\impl\sqlite;

use n2n\persistence\meta\structure\Table;

use n2n\core\SysTextUtils;

use n2n\persistence\meta\structure\UnavailableTypeException;

use n2n\persistence\meta\structure\ColumnFactory;

class SqliteColumnFactory implements ColumnFactory {
	
	/**
	 * @var Table
	 */
	private $table;
	
	/**
	 * @var n2n\persistence\Pdo
	 */
	private $dbh;
	
	public function __construct(Table $table) {
		$this->table = $table;
	}
	
	public function getTable() {
		return $this->table;
	}
	
	public function createIntegerColumn($name, $size, $signed = true) {
		$column = new SqliteIntegerColumn($name);
		$this->table->addColumn($column);
		return $column;
	}
	
	public function createStringColumn($name, $length, $charset = null) {
		$column = new SqliteStringColumn($name);
		$this->table->addColumn($column);
		return $column;
	}
	
	public function createTextColumn($name, $size, $charset = null) {
		throw $this->createUnvailableTypeException('TextColumn');
	}
	
	public function createBinaryColumn($name, $size) {
		$column = new SqliteBinaryColumn($name);
		$this->table->addColumn($column);
		return $column;
	}
	
	public function createDateTimeColumn($name, $dateAvailable = true, $timeAvailable = true) {
		$column = new SqliteDateTimeColumn($name);
		$this->table->addColumn($column);
		return $column;
	}
	
	public function createEnumColumn($name, array $values) {
		throw $this->createUnvailableTypeException('EnumColumn');
	}
	
	public function createFixedPointColumn($name, $numIntegerDigits, $numDecimalDigits) {
		$column = new SqliteFixedPointColumn($name);
		$this->table->addColumn($column);
		return $column;
	}
	
	public function createFloatingPointColumn($name, $size) {
		$column = new SqliteFloatingPointColumn($name);
		$this->table->addColumn($column);
		return $column;
	}
	
	private function createUnvailableTypeException($type) {
		return new UnavailableTypeException(SysTextUtils::get('n2n_persistence_meta_dialect_sqlite_type_is_unavailable', array('type' => $type)));
	}
	
}
