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

use n2n\persistence\meta\structure\FixedPointColumn;

class PgsqlFixedPointColumn extends PgsqlColumn implements FixedPointColumn, PgsqlManagedColumn {
	private $numIntegerDigits;
	private $numDecimalDigits;

	/**
	 * @param String $name
	 * @param int $numIntegerDigits
	 * @param int $numDecimalDigits
	 */
	public function __construct($name, $numIntegerDigits, $numDecimalDigits) {
		parent::__construct($name);
		$this->setNumIntegerDigits($numIntegerDigits);
		$this->setNumDecimalDigits($numDecimalDigits);
	}

	/**
	 * (non-PHPdoc)
	 * @see n2n\persistence\meta.FixedPointColumn::getNumIntegerDigits()
	 */
	public function getNumIntegerDigits() {
		return $this->numIntegerDigits;
	}

	/**
	 * @param int $numIntegerDigits
	 */
	public function setNumIntegerDigits($numIntegerDigits) {
		$this->triggerChangeListeners();
		$this->numIntegerDigits = intval($numIntegerDigits);
	}

	/**
	 * (non-PHPdoc)
	 * @see n2n\persistence\meta.FixedPointColumn::getNumDecimalDigits()
	 */
	public function getNumDecimalDigits() {
		return $this->numDecimalDigits;
	}

	/**
	 * @param int $numDecimalDigits
	 */
	public function setNumDecimalDigits($numDecimalDigits) {
		$this->triggerChangeListeners();
		$this->numDecimalDigits = intval($numDecimalDigits);
	}

	/**
	 * (non-PHPdoc)
	 * @see n2n\persistence\meta\impl\pgsql.PgsqlManagedColumn::getTypeForCurrentState()
	 */
	public function getTypeForCurrentState() {
		$attrs = $this->getAttrs();
		if (isset($attrs['data_type']) && $attrs['data_type'] != 'numeric') {
			return 'MONEY';
		}
		return 'NUMERIC(' . ($this->getNumIntegerDigits() + $this->getNumDecimalDigits()) . ',' . $this->getNumDecimalDigits() . ')';
	}

	/**
	 * (non-PHPdoc)
	 * @see n2n\persistence\meta.Column::copy()
	 */
	public function copy($newColumnName = null) {
		if (is_null($newColumnName)) $newColumnName = $this->getName();
		$fixedPointColumn = new PgsqlFixedPointColumn($newColumnName, $this->getNumIntegerDigits(), $this->getNumDecimalDigits());
		$fixedPointColumn->setAttrs($this->getAttrs());
		$fixedPointColumn->setDefaultValue($this->getDefaultValue());
		$fixedPointColumn->setNullAllowed($this->isNullAllowed());
		$fixedPointColumn->setValueGenerated($this->isValueGenerated());

		return $fixedPointColumn;
	}
}
