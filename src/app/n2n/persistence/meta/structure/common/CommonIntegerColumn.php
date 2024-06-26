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

use n2n\persistence\meta\structure\Size;

use n2n\spec\dbo\meta\structure\Column;

use n2n\spec\dbo\meta\structure\IntegerColumn;

class CommonIntegerColumn extends ColumnAdapter implements IntegerColumn {

	private $size;
	private $maxValue;
	private $minValue;
	private $signed;

	public function __construct($name, $size, $signed = true) {
		parent::__construct($name);
		$this->signed = $signed;
		$this->setSize($this->purifySize($size));
	}

	public function getSize() {
		return $this->size;
	}

	private function setSize($size) {
		$this->triggerChangeListeners();
		$this->size = intval($size);
		$this->applyMinAndMaxValue();
	}

	public function getMaxValue() {
		return $this->maxValue;
	}

	public function getMinValue() {
		return $this->minValue;
	}

	public function isSigned() {
		return $this->signed;
	}

	public function equalsType(Column $column, $ignoreNull = false) {
		return parent::equalsType($column, $ignoreNull)
				&& $column instanceof CommonIntegerColumn
				&& $column->getSize() === $this->getSize()	
				&& $column->isSigned() === $this->isSigned();
		}

	public function copy($newColumnName = null) {
		if (is_null($newColumnName)) {
			$newColumnName = $this->getName();
		}
		$newColumn = new self($newColumnName, $this->getSize(), $this->isSigned());
		$newColumn->applyCommonAttributes($this);
		return $newColumn;
	}
	
	protected function purifySize($size) {
		if ($size <= Size::SHORT) {
			return Size::SHORT;
		}
		if ($size <= Size::MEDIUM) {
			return Size::MEDIUM;
		}
		if ($size <= Size::INTEGER) {
			return Size::INTEGER;
		}
		return Size::LONG;
	}

	protected function applyMinAndMaxValue() {
		$pow = pow(2, $this->size - 1);
		if ($this->signed) {
			$this->minValue = -$pow;
			$this->maxValue = $pow - 1;
		} else {
			$this->minValue = 0;
			$this->maxValue = pow(2, $this->size) - 1;
		}
	}
}
