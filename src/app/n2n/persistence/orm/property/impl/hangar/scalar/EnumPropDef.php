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
use n2n\web\dispatch\mag\impl\model\StringArrayMag;
use n2n\web\dispatch\mag\MagCollection;
use hangar\entity\model\PropSourceDef;
use n2n\persistence\orm\property\EntityProperty;
use n2n\reflection\ArgUtils;
use n2n\persistence\orm\property\impl\ScalarEntityProperty;
use n2n\persistence\meta\structure\common\CommonEnumColumn;

class EnumPropDef extends ScalarPropDefAdapter {
	const PROP_NAME_VALUES = 'values';
	
	public function getName() {
		return 'Enum';
	}
	
	protected function createColumn(EntityProperty $entityProperty, DbInfo $dbInfo, ColumnFactory $columnFactory, 
			$columnName, Attributes $attributes) {
		$columnFactory->createEnumColumn($columnName, $this->getValues($attributes));
	}
	
	public function createMagCollection(PropSourceDef $propSourceDef = null) {
		$optionCollection = new MagCollection();
	
		$values = array();
		if (null !== $propSourceDef) {
			$values = $propSourceDef->getHangarData()->get(self::PROP_NAME_VALUES, false);
		}
		
		$optionCollection->addMag(new StringArrayMag(self::PROP_NAME_VALUES, 'Values', $values, true));
	
		return $optionCollection;
	}
	
	public function updatePropSourceDef(Attributes $attributes, PropSourceDef $propSourceDef) {
		$propSourceDef->getHangarData()->setAll(array(self::PROP_NAME_VALUES => 
				$attributes->get(self::PROP_NAME_VALUES)));
		$propSourceDef->setReturnTypeName();
		$propSourceDef->setSetterTypeName();
		$propSourceDef->setBoolean(false);
	}
	
	/**
	 * @param PropSourceDef $propSourceDef
	 * @return \n2n\persistence\meta\structure\Column
	 */
	public function createMetaColumn(EntityProperty $entityProperty, PropSourceDef $propSourceDef) {
		ArgUtils::assertTrue($entityProperty instanceof ScalarEntityProperty);
	
		return new CommonEnumColumn($entityProperty->getColumnName(), $this->getValues($propSourceDef->getHangarData()));
	}
	
	private function getValues(Attributes $attributes) {
		return $attributes->get(self::PROP_NAME_VALUES, false, array());
	}
}
