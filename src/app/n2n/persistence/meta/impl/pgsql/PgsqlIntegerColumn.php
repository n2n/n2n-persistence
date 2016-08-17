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

use n2n\persistence\meta\structure\IntegerColumn;

class PgsqlIntegerColumn extends PgsqlColumn implements IntegerColumn, PgsqlManagedColumn {
	const NUM_BITS_SMALLINT = 16;
	const NUM_BITS_INTEGER = 32;
	const NUM_BITS_BIGINT = 64;

	private $size;
	private $signed;
	private $minValue;
	private $maxValue;

	/**
	 * @param String $name
	 * @param int $size
	 * @param bool $signed
	 * @param bool $autoincrement
	 */
	public function __construct($name, $size, $signed, array $attrs = null) {
		parent::__construct($name);

		if (!is_null($attrs)) $this->setAttrs($attrs);
		$this->setSize($size);
		$this->setSigned($signed);
	}

	/**
	 * @see n2n\persistence\meta.IntegerColumn::getSize()
	 * @return Size of the Data Type
	 */
	public function getSize() {
		return $this->size;
	}

	/**
	 * @param int $size
	 */
	public function setSize($size) {
		$this->triggerChangeListeners();
		$this->size = intval($size);
		$this->defineMinMaxValues();
	}

	/**
	 * (non-PHPdoc)
	 * @see n2n\persistence\meta.IntegerColumn::isSigned()
	 */
	public function isSigned() {
		return $this->signed;
	}

	/**
	 * @param Boolean $signed
	 */
	public function setSigned($signed) {
		$this->signed = intval($signed);
	}

	/**
	 * (non-PHPdoc)
	 * @see n2n\persistence\meta.IntegerColumn::getMinValue()
	 */
	public function getMinValue() {
		return $this->minValue;
	}

	/**
	 * @param int $minValue
	 */
	private function setMinValue($minValue) {
		$this->minValue = intval($minValue);
	}

	/**
	 * (non-PHPdoc)
	 * @see n2n\persistence\meta.IntegerColumn::getMaxValue()
	 */
	public function getMaxValue() {
		return $this->maxValue;
	}

	/**
	 * @param int $maxValue
	 */
	private function setMaxValue($maxValue) {
		$this->maxValue = intval($maxValue);
	}

	/**
	 * Defines the mininmum and maximum Values.
	 */
	private function defineMinMaxValues() {
		if ($this->isSigned()) {
			$this->setMinValue(0);
			$this->setMaxValue(pow(2, $this->getSize()) - 1);
		} else {
			$pow = pow(2, $this->getSize() - 1);
			$this->setMinValue(-$pow);
			$this->setMaxValue($pow - 1);
		}
	}

	/**
	 * (non-PHPdoc)
	 * @see n2n\persistence\meta\impl\pgsql.PgsqlManagedColumn::getTypeForCurrentState()
	 */
	public function getTypeForCurrentState() {
		$attrs = $this->getAttrs();
		if (isset($attrs['data_type'])) {
			switch ($attrs['data_type']) {
				case 'bigint':
					return 'BIGINT';
				case 'smallint':
					return 'SMALLINT';
				case 'serial':
					return 'SERIAL';
				case 'bigserial':
					return 'BIGSERIAL';
				case 'integer':
				default:
					return 'INTEGER';
			}
		} else {
			if ($this->getSize() <= self::NUM_BITS_SMALLINT) {
				$this->setSize(self::NUM_BITS_SMALLINT);
				return 'SMALLINT';
			} elseif ($this->getSize() > self::NUM_BITS_INTEGER) {
				$this->setSize(self::NUM_BITS_BIGINT);
				if ($this->signed) return 'BIGINT';
				return 'BIGSERIAL';
			} else {
				$this->setSize(self::NUM_BITS_INTEGER);
				if ($this->signed) return 'INTEGER';
				return 'SERIAL';
			}
		}
	}

	/**
	 * (non-PHPdoc)
	 * @see n2n\persistence\meta.Column::copy()
	 */
	public function copy($newColumnName = null) {
		if (is_null($newColumnName)) $newColumnName = $this->getName();
		$integerColumn = new PgsqlIntegerColumn($newColumnName, $this->getSize(), $this->isSigned());
		$integerColumn->setAttrs($this->getAttrs());
		$integerColumn->setDefaultValue($this->getDefaultValue());
		$integerColumn->setNullAllowed($this->isNullAllowed());
		$integerColumn->setValueGenerated($this->isValueGenerated());

		return $integerColumn;
	}
}
