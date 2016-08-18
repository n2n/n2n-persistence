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
namespace n2n\persistence\orm\property\impl\hangar\scalar;

use n2n\util\config\Attributes;
use hangar\entity\model\DbInfo;
use n2n\persistence\meta\structure\ColumnFactory;
use n2n\web\dispatch\mag\MagCollection;
use hangar\entity\model\PropSourceDef;
use n2n\web\dispatch\mag\impl\model\NumericMag;
use n2n\persistence\orm\property\EntityProperty;
use n2n\persistence\orm\property\impl\ScalarEntityProperty;
use hangar\entity\model\CompatibilityLevel;
use n2n\reflection\ArgUtils;
use n2n\persistence\meta\structure\common\CommonFixedPointColumn;

class FixedPointPropDef extends ScalarPropDefAdapter {
	const PROP_NAME_NUM_INTEGER_DIGITS = 'num-integer-digits';
	const PROP_NAME_NUM_DECIMAL_DIGITS = 'num-decimal-digits';
	
	public function getName() {
		return 'Fixed Point';
	}

	protected function createColumn(EntityProperty $entityProperty, DbInfo $dbInfo, ColumnFactory $columnFactory, $columnName, 
			Attributes $attributes) {
		$columnFactory->createFixedPointColumn($columnName, 
				$this->determineNumIntegerDigits($entityProperty->getName(), $attributes),
				$this->determineNumDecimalDigits($entityProperty->getName(), $attributes));
	}
	

	public function createMagCollection(PropSourceDef $propSourceDef = null) {
		$optionCollection = new MagCollection();
	
		$numIntegerDigits = $this->columnDefaults->getDefaultFixedPointNumIntegerDigits();
		$numDecimalDigits = $this->columnDefaults->getDefaultFixedPointNumDecimalDigits();
		if (null !== $propSourceDef) {
			$numIntegerDigits = $this->determineNumIntegerDigits($propSourceDef->getPropertyName(), 
					$propSourceDef->getHangarData());
			$numDecimalDigits = $this->determineNumDecimalDigits($propSourceDef->getPropertyName(), 
					$propSourceDef->getHangarData());		
		}
	
		$optionCollection->addMag(new NumericMag(self::PROP_NAME_NUM_INTEGER_DIGITS,
				'Num Integer Digits', $numIntegerDigits, true));
		
		$optionCollection->addMag(new NumericMag(self::PROP_NAME_NUM_DECIMAL_DIGITS,
				'Num Decimal Digits', $numDecimalDigits, true));
	
		return $optionCollection;
	}
	
	public function updatePropSourceDef(Attributes $attributes, PropSourceDef $propSourceDef) {
		$propSourceDef->getHangarData()->setAll(
				array(self::PROP_NAME_NUM_DECIMAL_DIGITS => $attributes->get(self::PROP_NAME_NUM_DECIMAL_DIGITS),
						self::PROP_NAME_NUM_INTEGER_DIGITS => $attributes->get(self::PROP_NAME_NUM_DECIMAL_DIGITS)));
		$propSourceDef->setReturnTypeName();
		$propSourceDef->setSetterTypeName();
		$propSourceDef->setBoolean(false);
	}
	
	/**
	 * @param EntityProperty $entityProperty
	 * @return int
	 */
	public function testCompatibility(EntityProperty $entityProperty) {
		if ($entityProperty instanceof ScalarEntityProperty) {
			switch ($entityProperty->getName()) {
				case 'lat':
				case 'lng':
				case 'currency':
					return CompatibilityLevel::COMMON;
			}
		}
	
		return parent::testCompatibility($entityProperty);
	}
	
	/**
	 * @param PropSourceDef $propSourceDef
	 * @return \n2n\persistence\meta\structure\Column
	 */
	public function createMetaColumn(EntityProperty $entityProperty, PropSourceDef $propSourceDef) {
		ArgUtils::assertTrue($entityProperty instanceof ScalarEntityProperty);
	
		return new CommonFixedPointColumn($entityProperty->getColumnName(), 
				$this->determineNumIntegerDigits($entityProperty->getName(), $propSourceDef->getHangarData()),
				$this->determineNumDecimalDigits($entityProperty->getName(), $propSourceDef->getHangarData()));
	}
	
	private function determineNumIntegerDigits($propertyName, Attributes $attributes) {
		if ($attributes->contains(self::PROP_NAME_NUM_INTEGER_DIGITS)) {
			return $attributes->get(self::PROP_NAME_NUM_INTEGER_DIGITS);
		}
		
		switch ($propertyName) {
			case 'lat':
			case 'lng':
				return 3;
			case 'currency':
				return 15;
			default:
				return $this->columnDefaults->getDefaultFixedPointNumIntegerDigits();
		}
	}
	
	private function determineNumDecimalDigits($propertyName, Attributes $attributes) {
		if ($attributes->contains(self::PROP_NAME_NUM_DECIMAL_DIGITS)) {
			return $attributes->get(self::PROP_NAME_NUM_DECIMAL_DIGITS);
		}
		
		switch ($propertyName) {
			case 'lat':
			case 'lng':
				return 12;
			case 'currency':
				return 2;
			default:
				return $this->columnDefaults->getDefaultFixedPointNumDecimalDigits();
		}
	}
}
