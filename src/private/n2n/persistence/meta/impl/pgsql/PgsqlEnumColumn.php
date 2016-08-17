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

use n2n\persistence\meta\structure\EnumColumn;

class PgsqlEnumColumn extends PgsqlColumn implements EnumColumn, PgsqlManagedColumn {
	private $values;
	private $charset;
	private $dbh;

	/**
	 * @param String $name
	 * @param array
	 */
	public function __construct($name, array $values) {
		parent::__construct($name);
		$this->setValues($values);
	}

	/**
	 * (non-PHPdoc)
	 * @see n2n\persistence\meta.EnumColumn::getValues()
	 * @return array
	 */
	public function getValues() {
		return $this->values;
	}

	/**
	 * @param array
	 */
	public function setValues(array $values) {
		$this->triggerChangeListeners();
		$this->values = $values;
	}

	/**
	 * (non-PHPdoc)
	 * @see n2n\persistence\meta\impl\pgsql.PgsqlManagedColumn::getTypeForCurrentState()
	 * @return String
	 */
	public function getTypeForCurrentState() {
		return ' ENUM (' . implode(',', $this->getValues()) . ') ';
	}

	/**
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
	 * @see n2n\persistence\meta.Column::copy()
	 */
	public function copy($newColumnName = null) {
		if (is_null($newColumnName)) $newColumnName = $this->getName();
		$enumColumn = new PgsqlEnumColumn($newColumnName, $this->getValues());
		$enumColumn->setAttrs($this->getAttrs());
		$enumColumn->setCharset($this->getCharset());
		$enumColumn->setDefaultValue($this->getDefaultValue());
		$enumColumn->setNullAllowed($this->isNullAllowed());
		$enumColumn->setValueGenerated($this->isValueGenerated());

		return $enumColumn;
	}
}
