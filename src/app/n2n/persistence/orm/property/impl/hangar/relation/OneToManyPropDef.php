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
namespace n2n\persistence\orm\property\impl\hangar\relation;

use hangar\entity\model\HangarPropDef;
use hangar\entity\model\PropSourceDef;
use n2n\web\dispatch\mag\MagCollection;
use hangar\core\option\OrmRelationColumnOption;
use n2n\util\config\Attributes;
use hangar\entity\model\DbInfo;
use n2n\persistence\orm\annotation\AnnoOneToMany;
use n2n\persistence\meta\structure\Table;
use n2n\persistence\orm\property\impl\relation\RelationFactory;
use n2n\persistence\orm\property\EntityProperty;
use n2n\persistence\orm\property\impl\RelationEntityProperty;
use n2n\reflection\CastUtils;
use n2n\persistence\orm\property\impl\relation\JoinTableRelation;
use n2n\persistence\orm\property\impl\relation\InverseJoinColumnOneToManyRelation;
use n2n\reflection\annotation\AnnotationSet;
use n2n\reflection\ArgUtils;
use n2n\persistence\orm\annotation\AnnoJoinColumn;
use n2n\persistence\meta\structure\IndexType;
use n2n\util\ex\IllegalStateException;
use n2n\persistence\orm\annotation\AnnoJoinTable;
use hangar\core\config\ColumnDefaults;
use n2n\persistence\orm\property\impl\ToManyEntityProperty;
use hangar\entity\model\CompatibilityLevel;

class OneToManyPropDef implements HangarPropDef {
	const PROP_NAME_PROPS = 'props';
	
	private $columnDefaults;
	
	public function setup(ColumnDefaults $columnDefaults) {
		$this->columnDefaults = $columnDefaults;
	}
	
	public function getName() {
		return 'OneToMany';
	}

	public function getEntityPropertyClass() {
		return new \ReflectionClass('n2n\persistence\orm\property\impl\ToManyEntityProperty');
	}

	public function createMagCollection(PropSourceDef $propSourceDef = null) {
		$magCollection = new MagCollection();
		$mag = new OrmRelationColumnOption(self::PROP_NAME_PROPS, true, true);
		
		if (null !== $propSourceDef) {
			$phpAnnotation = $propSourceDef->getPhpPropertyAnno()->getParam('n2n\persistence\orm\annotation\AnnoOneToMany');
			if (null !== $phpAnnotation) {
				$oneToManyAnno = $phpAnnotation->getAnnotation();
				IllegalStateException::assertTrue($oneToManyAnno instanceof AnnoOneToMany);
				$mag->setValuesByAnnotation($oneToManyAnno);
			}
		}
		
		$magCollection->addMag($mag);
		return $magCollection;
	}

	public function updatePropSourceDef(Attributes $attributes, PropSourceDef $propSourceDef) {
		$propAttributes = new Attributes($attributes->get(self::PROP_NAME_PROPS, false, array()));
		
		$propSourceDef->setBoolean(false);
		$propSourceDef->getHangarData()->setAll(array(self::PROP_NAME_PROPS 
				=> $propAttributes->toArray()));
		
		$targetEntityTypeName = $propAttributes->get(OrmRelationColumnOption::PROP_NAME_TARGET_ENTITY_CLASS);
		$propSourceDef->setReturnTypeName($targetEntityTypeName . ' []');
		
		$propertyAnno = $propSourceDef->getPhpPropertyAnno();
		
		$annoParam = $propertyAnno->getOrCreateParam(AnnoOneToMany::class);
		$annoParam->setConstructorParams(array());
		$annoParam->addConstructorParam($targetEntityTypeName . '::getClass()');
		
		$cascadeTypeValue = OrmRelationColumnOption::buildCascadeTypeAnnoParam(
				$propAttributes->get(OrmRelationColumnOption::PROP_NAME_CASCADE_TYPE));
		
		$fetchType = OrmRelationColumnOption::buildFetchTypeAnnoParam(
				$propAttributes->getString(OrmRelationColumnOption::PROP_NAME_FETCH_TYPE));
		
		$orphanRemoval = ($propAttributes->get(OrmRelationColumnOption::PROP_NAME_ORPHAN_REMOVAL));
		if (!$orphanRemoval) {
			$orphanRemoval = null;
		} else {
			$orphanRemoval = 'true';
		}
		
		if (null !== ($mappedBy = $propAttributes->get(OrmRelationColumnOption::PROP_NAME_MAPPED_BY))) {
			$annoParam->addConstructorParam($mappedBy, true);
		} else {
			if (null !== $cascadeTypeValue || null !== $fetchType || null !== $orphanRemoval) {
				$annoParam->addConstructorParam('null');
			}
		}
		
		if (null !== $cascadeTypeValue) {
			$annoParam->addConstructorParam($cascadeTypeValue);
		} else if (null !== $fetchType || null !== $orphanRemoval) {
			$annoParam->addConstructorParam('null');
		}
		
		if (null !== $fetchType) {
			$annoParam->addConstructorParam($fetchType);
		} elseif (null !== $orphanRemoval) {
			$annoParam->addConstructorParam('null');
		}
	
		if (null !== $orphanRemoval) {
			$annoParam->addConstructorParam($orphanRemoval);
		}
	}

	public function applyDbMeta(DbInfo $dbInfo, PropSourceDef $propSourceDef, EntityProperty $entityProperty, 
			AnnotationSet $annotationSet) {
		ArgUtils::assertTrue($entityProperty instanceof RelationEntityProperty);
		
		$propertyName = $propSourceDef->getPropertyName();
		$annoOneToMany = $annotationSet->getPropertyAnnotation($propertyName, AnnoOneToMany::class);
		CastUtils::assertTrue($annoOneToMany instanceof AnnoOneToMany);
		
		if (null === $annoOneToMany->getMappedBy()) {
			$idColumnSize = $this->columnDefaults->getDefaultIntegerSize();
			$idColumnSigned = $this->columnDefaults->getDefaultInterSigned();

			$joinMode = RelationFactory::detectJoinMode(RelationEntityProperty::TYPE_ONE_TO_MANY, 
					null !== $annotationSet->getPropertyAnnotation($propertyName, AnnoJoinColumn::class), 
					null !== $annotationSet->getPropertyAnnotation($propertyName, AnnoJoinTable::class));
			
			$relation = $entityProperty->getRelation();
			
			if ($joinMode == RelationFactory::JOIN_MODE_TABLE) {
				CastUtils::assertTrue($relation instanceof JoinTableRelation);

				$database = $dbInfo->getDatabase();
				$joinTable = null;
				$joinTableName = $relation->getJoinTableName();
				
				if ($database->containsMetaEntityName($joinTableName)) {
					$joinTable = $database->getMetaEntityByName($joinTableName);
					CastUtils::assertTrue($joinTable instanceof Table);
				} else {
					$joinTable = $database->createMetaEntityFactory()->createTable($joinTableName);
				}
				
				$columnFactory = $joinTable->createColumnFactory();
				$joinTable->removeAllColumns();
				$joinColumnName = $relation->getJoinColumnName();
				$inverseJoinColumnName = $relation->getInverseJoinColumnName();

				$joinTable->addColumn($columnFactory->createIntegerColumn($joinColumnName, $idColumnSize, $idColumnSigned));
				$joinTable->addColumn($columnFactory->createIntegerColumn($inverseJoinColumnName, $idColumnSize, $idColumnSigned));
				
				$joinTable->removeAllIndexes();
				$joinTable->createIndex(IndexType::PRIMARY, array($joinColumnName, $inverseJoinColumnName));
			} else {
				CastUtils::assertTrue($relation instanceof InverseJoinColumnOneToManyRelation);
				
				$targetTableName = $relation->getTargetEntityModel()->getTableName();
				$targetTable = null;

				if ($database->containsMetaEntityName($targetTableName)) {
					$targetTable = $database->getMetaEntityByName($targetTableName);
					CastUtils::assertTrue($joinTable instanceof Table);
				} else {
					$targetTable = $database->createMetaEntityFactory()->createTable($targetTableName);
				}
				
				$inverseJoinColumnName = $relation->getInverseJoinColumnName();
				$inverseJoinColumn = null;
				if ($targetTable->containsColumnName($inverseJoinColumnName)) {
					$targetTable->removeColumnByName($inverseJoinColumnName);
				}
				
				$targetTable->createColumnFactory()->createIntegerColumn($inverseJoinColumnName, $idColumnSize, $idColumnSigned);
			}
		}
	}
	
	/**
	 * @param PropSourceDef $propSourceDef
	 * @return \n2n\persistence\meta\structure\Column
	 */
	public function createMetaColumn(EntityProperty $entityProperty, PropSourceDef $propSourceDef) {
		return null;
	}
	
	/**
	 * @param EntityProperty $entityProperty
	 * @return int
	 */
	public function testCompatibility(EntityProperty $entityProperty) {
		if ($entityProperty instanceof ToManyEntityProperty
				&& $entityProperty->getType() == RelationEntityProperty::TYPE_ONE_TO_MANY) {
			return CompatibilityLevel::COMMON;
		}

		return CompatibilityLevel::NOT_COMPATIBLE;
	}
}
