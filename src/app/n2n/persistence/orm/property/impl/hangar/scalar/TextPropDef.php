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

use hangar\entity\model\PropSourceDef;
use n2n\web\dispatch\mag\MagCollection;
use n2n\util\config\Attributes;
use hangar\entity\model\DbInfo;
use n2n\web\dispatch\mag\impl\model\NumericMag;
use n2n\web\dispatch\mag\impl\model\StringMag;
use n2n\persistence\meta\structure\ColumnFactory;
use n2n\persistence\orm\property\EntityProperty;
use n2n\reflection\ArgUtils;
use n2n\persistence\meta\structure\common\CommonTextColumn;
use n2n\persistence\orm\property\impl\ScalarEntityProperty;
use hangar\entity\model\CompatibilityLevel;
use n2n\util\StringUtils;

class TextPropDef extends ScalarPropDefAdapter {
	const PROP_NAME_SIZE = 'size';
	const PROP_NAME_CHARSET = 'charset';
	
	public function getName() {
		return 'Text';
	}

	public function createMagCollection(PropSourceDef $propSourceDef = null) {
		$magCollection = new MagCollection();
		
		$size = $this->columnDefaults->getDefaultTextSize();
		$charset = $this->columnDefaults->getDefaultTextCharset();
		
		if (null !== $propSourceDef) {
			$size = $propSourceDef->getHangarData()->get(self::PROP_NAME_SIZE, false, $size);
			$charset = $propSourceDef->getHangarData()->get(self::PROP_NAME_CHARSET, false, $charset);
		}
		$magCollection->addMag(new NumericMag(self::PROP_NAME_SIZE, 'Size', $size, true));
		$magCollection->addMag(new StringMag(self::PROP_NAME_CHARSET, 'Charset', $charset));
		
		return $magCollection;
	}

	public function updatePropSourceDef(Attributes $attributes, PropSourceDef $propSourceDef) {
		$propSourceDef->setBoolean(false);
		$propSourceDef->getHangarData()->setAll(array(self::PROP_NAME_SIZE => $attributes->get(self::PROP_NAME_SIZE), 
				self::PROP_NAME_CHARSET => $attributes->get(self::PROP_NAME_CHARSET, false)));
		$propSourceDef->setReturnTypeName();
		$propSourceDef->setSetterTypeName();
	}
	
	/**
	 * @param PropSourceDef $propSourceDef
	 * @return \n2n\persistence\meta\structure\Column
	 */
	public function createMetaColumn(EntityProperty $entityProperty, PropSourceDef $propSourceDef) {
		ArgUtils::assertTrue($entityProperty instanceof ScalarEntityProperty);
	
		return new CommonTextColumn($entityProperty->getColumnName(),
				$this->determineSize($propSourceDef->getHangarData()),
				$this->determineCharset($propSourceDef->getHangarData()));
	}
	
	/**
	 * @param EntityProperty $entityProperty
	 * @return int
	 */
	public function testCompatibility(EntityProperty $entityProperty) {
		if ($entityProperty instanceof ScalarEntityProperty) {
			switch ($entityProperty->getName()) {
				case 'description':
				case 'lead':
					return CompatibilityLevel::COMMON;
			}
			
			if (StringUtils::endsWith('Html', $entityProperty->getName())) {
				return CompatibilityLevel::COMMON;
			}
		}
	
		return parent::testCompatibility($entityProperty);
	}

	protected function createColumn(EntityProperty $entityProperty, DbInfo $dbInfo, ColumnFactory $columnFactory, $columnName, Attributes $attributes) {
		$columnFactory->createTextColumn($columnName, $this->determineSize($attributes), 
				$this->determineCharset($attributes));
	}
	
	private function determineSize(Attributes $attributes) {
		return $attributes->get(self::PROP_NAME_SIZE, false, $this->columnDefaults->getDefaultTextSize());
	}
	
	private function determineCharset(Attributes $attributes) {
		return $attributes->get(self::PROP_NAME_CHARSET, 
				false, $this->columnDefaults->getDefaultTextCharset());
	}
}
