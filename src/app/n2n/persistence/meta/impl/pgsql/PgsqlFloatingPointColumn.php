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

use n2n\persistence\meta\structure\FloatingPointColumn;

class PgsqlFloatingPointColumn extends PgsqlColumn implements FloatingPointColumn, PgsqlManagedColumn {
	const FLOAT_BITS_REAL = 32;
	const FLOAT_BITS_REAL_EXPONENT = 8;
	const FLOAT_BITS_REAL_MANTISSA = 23;
	const FLOAT_BITS_REAL_BIAS = 127;

	const FLOAT_BITS_DOUBLE_PRECISION = 64;
	const FLOAT_BITS_DOUBLE_PRECISION_EXPONENT = 11;
	const FLOAT_BITS_DOUBLE_PRECISION_MANTISSA = 52;
	const FLOAT_BITS_DOUBLE_PRECISION_BIAS = 1023;

	const FLOAT_PRECISION = 24;

	private $size;
	private $maxValue;
	private $minValue;
	private $maxExponent;
	private $minExponent;

	/**
	 * @param String $name
	 * @param int $size
	 */
	public function __construct($name, $size) {
		parent::__construct($name);
		$this->setSize($size);
	}

	/**
	 * (non-PHPdoc)
	 * @see n2n\persistence\meta.FloatingPointColumn::getSize()
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
	 * @param int $maxValue
	 */
	private function setMaxValue($maxValue) {
		$this->maxValue = intval($maxValue);
	}

	/**
	 * (non-PHPdoc)
	 * @see n2n\persistence\meta.FloatingPointColumn::getMaxValue()
	 */
	public function getMaxValue() {
		return $this->maxValue;
	}

	/**
	 * @param int $minValue
	 */
	private function setMinValue($minValue) {
		$this->minValue = intval($minValue);
	}

	/**
	 * (non-PHPdoc)
	 * @see n2n\persistence\meta.FloatingPointColumn::getMinValue()
	 */
	public function getMinValue() {
		return $this->minValue;
	}

	/**
	 * @param int $maxExponent
	 */
	private function setMaxExponent($maxExponent) {
		$this->maxExponent = intval($maxExponent);
	}

	/**
	 * (non-PHPdoc)
	 * @see n2n\persistence\meta.FloatingPointColumn::getMaxExponent()
	 */
	public function getMaxExponent() {
		return $this->maxExponent;
	}

	/**
	 * @param int $minExponent
	 */
	private function setMinExponent($minExponent) {
		$this->minExponent = intval($minExponent);
	}

	/**
	 * (non-PHPdoc)
	 * @see n2n\persistence\meta.FloatingPointColumn::getMinExponent()
	 */
	public function getMinExponent() {
		return $this->minExponent;
	}

	/**
	 * Defines the mininmum and maximum Values.
	 */
	private function defineMinMaxValues() {
		if($this->getSize() <= self::FLOAT_BITS_REAL) {
			$this->size = self::FLOAT_BITS_REAL;
			$this->setMinExponent(2 - self::FLOAT_BITS_REAL_BIAS);
			$this->setMaxExponent(1 + self::FLOAT_BITS_REAL_BIAS);
			$this->setMinValue(pow(2, self::FLOAT_BITS_REAL_MANTISSA * (-1)) * pow(2, $this->getMinExponent()));
			$this->setMaxValue(pow(2, self::FLOAT_BITS_REAL_MANTISSA * (-1)) * pow(2, $this->getMaxExponent()));
		} else {
			$this->size = self::FLOAT_BITS_DOUBLE_PRECISION;
			$this->setMinExponent(2 - self::FLOAT_BITS_DOUBLE_PRECISION_BIAS);
			$this->setMaxExponent(1 + self::FLOAT_BITS_DOUBLE_PRECISION_BIAS);
			$this->setMinValue(pow(2, self::FLOAT_BITS_DOUBLE_PRECISION_MANTISSA * (-1)) * pow(2, $this->getMinExponent()));
			$this->setMaxValue(pow(2, self::FLOAT_BITS_DOUBLE_PRECISION_MANTISSA * (-1)) * pow(2, $this->getMaxExponent()));
		}
	}

	/**
	 * (non-PHPdoc)
	 * @see n2n\persistence\meta\impl\pgsql.PgsqlManagedColumn::getTypeForCurrentState()
	 */
	public function getTypeForCurrentState() {
		if ($this->getSize() <= self::FLOAT_BITS_REAL) {
			return 'REAL';
		} else {
			return 'DOUBLE PRECISION';
		}
	}

	/**
	 * (non-PHPdoc)
	 * @see n2n\persistence\meta.Column::copy()
	 */
	public function copy($newColumnName = null) {
		if (is_null($newColumnName)) $newColumnName = $this->getName();
		$floatingPointColumn = new PgsqlFloatingPointColumn($newColumnName, $this->getSize());
		$floatingPointColumn->setAttrs($this->getAttrs());
		$floatingPointColumn->setDefaultValue($this->getDefaultValue());
		$floatingPointColumn->setNullAllowed($this->isNullAllowed());
		$floatingPointColumn->setValueGenerated($this->isValueGenerated());

		return $floatingPointColumn;
	}
}
