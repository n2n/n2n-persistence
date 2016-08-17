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

use n2n\persistence\meta\structure\DateTimeColumn;

class PgsqlDateTimeColumn extends PgsqlColumn implements DateTimeColumn, PgsqlManagedColumn {
	const FORMAT_DATE = 'Y-m-d';
	const FORMAT_DATE_TIME = 'Y-m-d H:i:s';
	const FORMAT_TIME = 'H:i:s';

	private $dateAvailable;
	private $timeAvailable;

	/**
	 * @param Strign $name
	 * @param Boolean $dateAvailable
	 * @param Boolean $timeAvailable
	 */
	public function __construct($name, $dateAvailable, $timeAvailable) {
		parent::__construct($name);
		$this->setDateAvailable($dateAvailable);
		$this->setTimeAvailable($timeAvailable);
	}

	/**
	 * (non-PHPdoc)
	 * @see n2n\persistence\meta.DateTimeColumn::parseDateTime()
	 */
	public function parseDateTime($rawValue) {
		return \DateTime::createFromFormat($this->getFormat(), $rawValue);
	}

	/**
	 * (non-PHPdoc)
	 * @see n2n\persistence\meta.DateTimeColumn::buildRawValue()
	 */
	public function buildRawValue(\DateTime $dateTime = null) {
		if (is_null($dateTime)) {
			$dateTime = new \DateTime();
		}
		return $dateTime->format($this->getFormat());
	}

	/**
	 * @param Boolean $dateAvailable
	 */
	private function setDateAvailable($dateAvailable) {
		$this->dateAvailable = (bool) $dateAvailable;
	}

	/**
	 * (non-PHPdoc)
	 * @see n2n\persistence\meta.DateTimeColumn::isDateAvailable()
	 */
	public function isDateAvailable() {
		return $this->dateAvailable;
	}

	/**
	 * @param Boolean $timeAvailable
	 */
	private function setTimeAvailable($timeAvailable) {
		$this->timeAvailable = (bool) $timeAvailable;
	}

	/**
	 * (non-PHPdoc)
	 * @see n2n\persistence\meta.DateTimeColumn::isTimeAvailable()
	 */
	public function isTimeAvailable() {
		return $this->timeAvailable;
	}

	/**
	 * (non-PHPdoc)
	 * @see n2n\persistence\meta\impl\pgsql.PgsqlManagedColumn::getTypeForCurrentState()
	 */
	public function getTypeForCurrentState() {
		switch (true) {
			case ($this->isDateAvailable() && $this->isTimeAvailable()):
			default:
				return 'TIMESTAMP WITHOUT TIME ZONE';
			case ($this->isDateAvailable()):
				return 'DATE';
			case ($this->isTimeAvailable()):
				return 'TIME WITHOUT TIME ZONE';
		}
	}

	/**
	 * @return String
	 */
	private function getFormat() {
		switch (true) {
			case ($this->isDateAvailable() && $this->isTimeAvailable()):
			default:
				return self::FORMAT_DATE_TIME;
			case ($this->isDateAvailable()):
				return self::FORMAT_DATE;
			case ($this->isTimeAvailable()):
				return self::FORMAT_TIME;
		}
	}

	/**
	 * (non-PHPdoc)
	 * @see n2n\persistence\meta.Column::copy()
	 */
	public function copy($newColumnName = null) {
		if (is_null($newColumnName)) $newColumnName = $this->getName();
		$newDateTimeColumn = new PgsqlDateTimeColumn($newColumnName, $this->isDateAvailable(), $this->isTimeAvailable());
		$newDateTimeColumn->setAttrs($this->getAttrs());
		$newDateTimeColumn->setDefaultValue($this->getDefaultValue());
		$newDateTimeColumn->setNullAllowed($this->isNullAllowed());
		$newDateTimeColumn->setValueGenerated($this->isValueGenerated());

		return $newDateTimeColumn;
	}
}
