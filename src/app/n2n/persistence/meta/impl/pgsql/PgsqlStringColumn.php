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

use n2n\persistence\meta\structure\StringColumn;

class PgsqlStringColumn extends PgsqlColumn implements StringColumn, PgsqlManagedColumn {
	private $length;
	private $charset;

	/**
	 * @param String $name
	 * @param int $length
	 * @param String $charset
	 */
	public function __construct($name, $length, $charset = null) {
		parent::__construct($name);
		$this->setLength($length);
		if (!is_null($charset)) $this->setCharset($charset);
	}

	/**
	 * (non-PHPdoc)
	 * @see n2n\persistence\meta.StringColumn::getLength()
	 */
	public function getLength() {
		return $this->length;
	}

	/**
	 * @param int $length
	 */
	public function setLength($length) {
		$this->triggerChangeListeners();
		$this->length = intval($length);
	}

	/**
	 * (non-PHPdoc)
	 * @see n2n\persistence\meta.StringColumn::getCharset()
	 */
	public function getCharset() {
		return $this->charset;
	}

	/**
	 * @param String $charset
	 */
	public function setCharset($charset) {
		$this->triggerChangeListeners();
		$this->charset = $charset;
	}

	/**
	 * (non-PHPdoc)
	 * @see n2n\persistence\meta\impl\pgsql.PgsqlManagedColumn::getTypeForCurrentState()
	 */
	public function getTypeForCurrentState() {
		if ($this->getLength() <= 1) {
			return 'CHAR(1)';
		} else {
			return 'CHARACTER(' . $this->getLength() . ')';
		}
	}

	/**
	 * (non-PHPdoc)
	 * @see n2n\persistence\meta.Column::copy()
	 */
	public function copy($newColumnName = null) {
		if (is_null($newColumnName)) $newColumnName = $this->getName();
		$stringColumn = new PgsqlStringColumn($newColumnName, $this->getLength(), $this->getCharset());
		$stringColumn->setAttrs($this->getAttrs());
		$stringColumn->setDefaultValue($this->getDefaultValue());
		$stringColumn->setNullAllowed($this->isNullAllowed());
		$stringColumn->setValueGenerated($this->isValueGenerated());

		return $stringColumn;
	}
}
