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

use n2n\persistence\meta\structure\ColumnFactory;

class PgsqlColumnFactory implements ColumnFactory {
	/**
	 * @var PgsqlTable
	 */
	private $table;

	/**
	 * @param PgsqlTable $table
	 */
	public function __construct(PgsqlTable $table) {
		$this->setTable($table);
	}

	/**
	 * @param PgsqlTable $table
	 */
	public function setTable(PgsqlTable $table) {
		$this->table = $table;
	}

	/**
	 * (non-PHPdoc)
	 * @see n2n\persistence\meta.ColumnFactory::getTable()
	 */
	public function getTable() {
		return $this->table;
	}

	/**
	 * (non-PHPdoc)
	 * @see n2n\persistence\meta.ColumnFactory::createBinaryColumn()
	 */
	public function createBinaryColumn($name, $size) {
		$binaryColumn = new PgsqlBinaryColumn($name, $size);
		$this->table->addColumn($binaryColumn);
		return $binaryColumn;
	}

	/**
	 * (non-PHPdoc)
	 * @see n2n\persistence\meta.ColumnFactory::createIntegerColumn()
	 */
	public function createIntegerColumn($name, $size, $signed = true) {
		$integerColumn = new PgsqlIntegerColumn($name, $size, $signed);
		$this->table->addColumn($integerColumn);
		return $integerColumn;
	}

	/**
	 * (non-PHPdoc)
	 * @see n2n\persistence\meta.ColumnFactory::createFixedPointColumn()
	 */
	public function createFixedPointColumn($name, $numIntegerDigits, $numDecimalDigits) {
		$fixedPointColumn = new PgsqlFixedPointColumn($name, $numIntegerDigits, $numDecimalDigits);
		$this->table->addColumn($fixedPointColumn);
		return $fixedPointColumn;
	}

	/**
	 * (non-PHPdoc)
	 * @see n2n\persistence\meta.ColumnFactory::createFloatingPointColumn()
	 */
	public function createFloatingPointColumn($name, $size) {
		$floatingPointColumn = new PgsqlFloatingPointColumn($name, $size);
		$this->table->addColumn($floatingPointColumn);
		return $floatingPointColumn;
	}

	/**
	 * (non-PHPdoc)
	 * @see n2n\persistence\meta.ColumnFactory::createDateTimeColumn()
	 */
	public function createDateTimeColumn($name, $dateAvailable = true, $timeAvailable = true) {
		$dateTimeColumn = new PgsqlDateTimeColumn($name, $dateAvailable, $timeAvailable);
		$this->table->addColumn($dateTimeColumn);
		return $dateTimeColumn;
	}

	/**
	 * (non-PHPdoc)
	 * @see n2n\persistence\meta.ColumnFactory::createEnumColumn()
	 */
	public function createEnumColumn($name, array $values) {
		$enumColumn = new PgsqlEnumColumn($name, $values);
		$this->table->addColumn($enumColumn);
		return $enumColumn;
	}

	/**
	 * (non-PHPdoc)
	 * @see n2n\persistence\meta.ColumnFactory::createStringColumn()
	 */
	public function createStringColumn($name, $length, $charset = null) {
		$stringColumn = new PgsqlStringColumn($name, $length, $charset);
		$this->table->addColumn($stringColumn);
		return $stringColumn;
	}

	/**
	 * (non-PHPdoc)
	 * @see n2n\persistence\meta.ColumnFactory::createTextColumn()
	 */
	public function createTextColumn($name, $size, $charset = null) {
		$textColumn = new PgsqlTextColumn($name, $size, $charset);
		$this->table->addColumn($textColumn);
		return $textColumn;
	}
}
