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

use n2n\persistence\meta\structure\TextColumn;

class PgsqlTextColumn extends PgsqlColumn implements TextColumn, PgsqlManagedColumn {
	private $size;
	private $charset;

	/**
	 * @param String $name
	 * @param int $size
	 */
	public function __construct($name, $size, $charset = null) {
		parent::__construct($name);
		$this->setSize($size);
		if (!is_null($charset)) $this->setCharset($charset);
	}

	/**
	 * (non-PHPdoc)
	 * @see n2n\persistence\meta.TextColumn::getSize()
	 * @return int
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
	}

	/**
	 * (non-PHPdoc)
	 * @see n2n\persistence\meta.TextColumn::getCharset()
	 * @return String
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
	 * @return String
	 */
	public function getTypeForCurrentState() {
		return 'TEXT';
	}

	public function copy($newColumnName = null) {
		if (is_null($newColumnName)) $newColumnName = $this->getName();
		$textColumn = new PgsqlTextColumn($newColumnName, $this->getSize(), $this->getCharset());
		$textColumn->setAttrs($this->getAttrs());
		$textColumn->setDefaultValue($this->getDefaultValue());
		$textColumn->setNullAllowed($this->isNullAllowed());
		$textColumn->setValueGenerated($this->isValueGenerated());

		return $textColumn;
	}
}
