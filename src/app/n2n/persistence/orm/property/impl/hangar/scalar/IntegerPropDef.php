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

use hangar\entity\model\DbInfo;
use n2n\persistence\meta\structure\IndexType;
use n2n\persistence\orm\model\EntityModelFactory;
use n2n\persistence\meta\structure\ColumnFactory;
use n2n\util\config\Attributes;
use hangar\entity\model\PropSourceDef;
use n2n\web\dispatch\mag\MagCollection;
use n2n\web\dispatch\mag\impl\model\BoolMag;
use n2n\persistence\meta\structure\Size;
use n2n\web\dispatch\mag\impl\model\EnumMag;
use n2n\persistence\orm\property\EntityProperty;
use hangar\entity\model\CompatibilityLevel;
use n2n\persistence\orm\property\impl\ScalarEntityProperty;
use n2n\reflection\ArgUtils;
use n2n\persistence\meta\structure\common\CommonIntegerColumn;

class IntegerPropDef extends ScalarPropDefAdapter {
	const PROP_NAME_SIZE = 'size';
	const PROP_NAME_SIGNED = 'signed';
			
	public function getName() {
		return 'Integer';
	}

	public function createMagCollection(PropSourceDef $propSourceDef = null) {
		$optionCollection = new MagCollection();
	
		$size = $this->columnDefaults->getDefaultIntegerSize();
		$signed = $this->columnDefaults->getDefaultInterSigned();;
		
		if (null !== $propSourceDef) {
			$size = $propSourceDef->getHangarData()->get(self::PROP_NAME_SIZE, false, $size);
			$signed = $propSourceDef->getHangarData()->get(self::PROP_NAME_SIGNED, false, $signed);
		}
	
		$optionCollection->addMag(new EnumMag(self::PROP_NAME_SIZE, 'Size', 
				$this->getSizeOptions(), $size));
		$optionCollection->addMag(new BoolMag(self::PROP_NAME_SIGNED, 'Signed', $signed));
	
		return $optionCollection;
	}
	
	public function updatePropSourceDef(Attributes $attributes, PropSourceDef $propSourceDef) {
		$propSourceDef->getHangarData()->setAll(array(
				self::PROP_NAME_SIZE => $attributes->get(self::PROP_NAME_SIZE),
				self::PROP_NAME_SIGNED => $attributes->get(self::PROP_NAME_SIGNED)));
		
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
				case 'id':
				case 'orderIndex':
				case 'lft':
				case 'rgt':
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
	
		return new CommonIntegerColumn($entityProperty->getColumnName(),
				$this->determineSize($propSourceDef->getHangarData()),
				$this->determineSigned($propSourceDef->getHangarData()));
	}
	
	protected function createColumn(EntityProperty $entityProperty, DbInfo $dbInfo, ColumnFactory $columnFactory, $columnName, Attributes $attributes) {
		$columnFactory->createIntegerColumn($columnName, $this->determineSize($attributes), 
				$this->determineSigned($attributes));
		
		if ($columnName == EntityModelFactory::DEFAULT_ID_PROPERTY_NAME) {
			$dbInfo->getTable()->createIndex(IndexType::PRIMARY, array($columnName));
		}
	}
	
	private function determineSize(Attributes $attributes) {
		return $attributes->get(self::PROP_NAME_SIZE, false, $this->columnDefaults->getDefaultIntegerSize());
	}
	
	private function determineSigned(Attributes $attributes) {
		return $attributes->get(self::PROP_NAME_SIGNED, false, $this->columnDefaults->getDefaultInterSigned());
	}
	
	private function getSizeOptions() {
		return array(Size::SHORT => 'Short', Size::MEDIUM => 'Medium', Size::INTEGER => 'Integer', 
				Size::LONG => 'Long');
	}
}
