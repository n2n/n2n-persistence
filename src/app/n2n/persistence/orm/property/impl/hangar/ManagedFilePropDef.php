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
namespace n2n\persistence\orm\property\impl\hangar;

use hangar\entity\model\HangarPropDef;
use hangar\entity\model\PropSourceDef;
use n2n\util\config\Attributes;
use n2n\web\dispatch\mag\MagCollection;
use hangar\entity\model\DbInfo;
use n2n\persistence\orm\property\EntityProperty;
use n2n\reflection\annotation\AnnotationSet;
use n2n\reflection\ArgUtils;
use n2n\io\orm\ManagedFileEntityProperty;
use hangar\core\config\ColumnDefaults;
use n2n\web\dispatch\mag\impl\model\NumericMag;
use n2n\persistence\orm\annotation\AnnoManagedFile;
use n2n\io\managed\FileManager;
use n2n\web\dispatch\mag\impl\model\ReflectionClassMag;
use n2n\persistence\meta\structure\common\CommonStringColumn;
use hangar\entity\model\CompatibilityLevel;

class ManagedFilePropDef implements HangarPropDef {
	const PROP_NAME_LENGTH = 'length';
	const PROP_NAME_FILE_MANAGER = 'fileManager';
	
	private $columnDefaults;
	
	public function setup(ColumnDefaults $columnDefaults) {
		$this->columnDefaults = $columnDefaults;
	}
	
	public function getName() {
		return 'MangedFile';
	}
	
	public function getEntityPropertyClass() {
		return new \ReflectionClass('n2n\io\orm\ManagedFileEntityProperty');
	}
	
	public function createMagCollection(PropSourceDef $propSourceDef = null) {
		$magCollection = new MagCollection();
		
		$size = $this->columnDefaults->getDefaultStringLength();
		$fileManagerLookupId = null;
		if (null !== $propSourceDef) {
			$size = $propSourceDef->getHangarData()->get(self::PROP_NAME_LENGTH, false, $size);
			$fileManagerLookupId = $propSourceDef->getHangarData()
					->get(self::PROP_NAME_FILE_MANAGER, false, $fileManagerLookupId);
		}
		
		$magCollection->addMag(new NumericMag(self::PROP_NAME_LENGTH, 'Length', $size, true));
		$magCollection->addMag(new ReflectionClassMag(self::PROP_NAME_FILE_MANAGER, 'FileManager (Lookup Id)', 
				new \ReflectionClass(FileManager::class), $fileManagerLookupId));
		
		return $magCollection;
	}
	
	public function updatePropSourceDef(Attributes $attributes, PropSourceDef $propSourceDef) {
		$propSourceDef->setBoolean(false);
		$propSourceDef->setReturnTypeName('\n2n\io\managed\File');
		$propSourceDef->setSetterTypeName('\n2n\io\managed\File');
		
		$annoManagedFile = $propSourceDef->getPhpPropertyAnno()
				->getOrCreateParam(AnnoManagedFile::class);
		
		$fileManagerLookupId = $attributes->get(self::PROP_NAME_FILE_MANAGER);

		if ($annoManagedFile->hasConstructorParam(1)) {
			if (null === $fileManagerLookupId) {
				$annoManagedFile->setConstructorParam(1, $fileManagerLookupId);
			} else {
				$annoManagedFile->setConstructorParam(1, $fileManagerLookupId, true);
			}
		} else if (null !== $fileManagerLookupId) {
			$annoManagedFile->addConstructorParam($fileManagerLookupId, true);
		}
		
		$propSourceDef->getHangarData()->setAll(array(
				self::PROP_NAME_LENGTH => $attributes->get(self::PROP_NAME_LENGTH)));
	}
	/**
	 * Apply to Database
	 *
	 * @param string $columnName
	 * @param ColumnFactory $columnFactory
	 * @param PropSourceDef $propSourceDef
	 */
	public function applyDbMeta(DbInfo $dbInfo, PropSourceDef $propSourceDef, EntityProperty $entityProperty, 
			AnnotationSet $annotationSet) {
		
		ArgUtils::assertTrue($entityProperty instanceof ManagedFileEntityProperty);
		$columnName = $entityProperty->getColumnName();
		$dbInfo->removeColumn($columnName);
		
		$dbInfo->getTable()->createColumnFactory()
				->createStringColumn($columnName, 
						$propSourceDef->getHangarData()->get(self::PROP_NAME_LENGTH, 
								false, $this->columnDefaults->getDefaultStringLength()));
	}

	/**
	 * @param PropSourceDef $propSourceDef
	 * @return \n2n\persistence\meta\structure\Column
	 */
	public function createMetaColumn(EntityProperty $entityProperty, PropSourceDef $propSourceDef) {
		ArgUtils::assertTrue($entityProperty instanceof ManagedFileEntityProperty);
		return new CommonStringColumn($entityProperty->getColumnName(), 
				$propSourceDef->getHangarData()->get(self::PROP_NAME_LENGTH, 
						false, $this->columnDefaults->getDefaultStringLength()));
	}
	
	/**
	 * @param EntityProperty $entityProperty
	 * @return int
	 */
	public function testCompatibility(EntityProperty $entityProperty) {
		if ($entityProperty instanceof ManagedFileEntityProperty) return CompatibilityLevel::COMMON;
	
		return CompatibilityLevel::NOT_COMPATIBLE;
	}
}
