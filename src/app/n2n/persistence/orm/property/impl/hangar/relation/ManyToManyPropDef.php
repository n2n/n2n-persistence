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
use n2n\util\ex\IllegalStateException;
use n2n\persistence\orm\property\EntityProperty;
use n2n\reflection\annotation\AnnotationSet;
use n2n\persistence\orm\annotation\AnnoManyToMany;
use n2n\reflection\ArgUtils;
use n2n\persistence\orm\property\impl\RelationEntityProperty;
use n2n\reflection\CastUtils;
use n2n\persistence\orm\property\impl\relation\JoinTableToManyRelation;
use n2n\persistence\meta\structure\IndexType;
use hangar\core\config\ColumnDefaults;
use n2n\persistence\orm\property\impl\ToManyEntityProperty;
use hangar\entity\model\CompatibilityLevel;

class ManyToManyPropDef implements HangarPropDef {
	const PROP_NAME_PROPS = 'props';
	
	protected $columnDefaults;
	
	public function setup(ColumnDefaults $columnDefaults) {
		$this->columnDefaults = $columnDefaults;
	}
	
	public function getName() {
		return 'ManyToMany';
	}

	public function getEntityPropertyClass() {
		return new \ReflectionClass('n2n\persistence\orm\property\impl\ToManyEntityProperty');
	}

	public function createMagCollection(PropSourceDef $propSourceDef = null) {
		$magCollection = new MagCollection();
		$mag = new OrmRelationColumnOption(self::PROP_NAME_PROPS);
		
		if (null !== $propSourceDef) {
			$phpAnnotation = $propSourceDef->getPhpPropertyAnno()->getParam('n2n\persistence\orm\annotation\AnnoManyToMany');
			if (null !== $phpAnnotation) {
				$manyToManyAnno = $phpAnnotation->getAnnotation();
				IllegalStateException::assertTrue($manyToManyAnno instanceof AnnoManyToMany);
				$mag->setValuesByAnnotation($manyToManyAnno);
			}
		}
		
		$magCollection->addMag($mag);
		return $magCollection;
	}

	public function updatePropSourceDef(Attributes $attributes, PropSourceDef $propSourceDef) {
		$propAttributes = new Attributes($attributes->get('props', false, array()));
		
		$propSourceDef->setBoolean(false);
		$propSourceDef->getHangarData()->setAll(array(self::PROP_NAME_PROPS => $propAttributes->toArray()));
		
		$targetEntityTypeName = $propAttributes->get(OrmRelationColumnOption::PROP_NAME_TARGET_ENTITY_CLASS);
		$propSourceDef->setReturnTypeName($targetEntityTypeName . ' []');
		
		$propertyAnno = $propSourceDef->getPhpPropertyAnno();
		$annoParam = $propertyAnno->getOrCreateParam('n2n\persistence\orm\annotation\AnnoManyToMany');
		$annoParam->setConstructorParams(array());
		$annoParam->addConstructorParam($targetEntityTypeName . '::getClass()');
		
		$cascadeTypeValue = OrmRelationColumnOption::buildCascadeTypeAnnoParam(
				$propAttributes->get(OrmRelationColumnOption::PROP_NAME_CASCADE_TYPE));
		
		$fetchType = OrmRelationColumnOption::buildFetchTypeAnnoParam(
				$propAttributes->getString(OrmRelationColumnOption::PROP_NAME_FETCH_TYPE));
		
		if (null !== ($mappedBy = $propAttributes->get(OrmRelationColumnOption::PROP_NAME_MAPPED_BY))) {
			$annoParam->addConstructorParam($mappedBy, true);
		} else {
			if (null !== $cascadeTypeValue || null !== $fetchType) {
				$annoParam->addConstructorParam('null');
			}
		}
		
		if (null !== $cascadeTypeValue) {
			$annoParam->addConstructorParam($cascadeTypeValue);
		} else if (null !== $fetchType) {
			$annoParam->addConstructorParam('null');
		}
		
		if (null !== $fetchType) {
			$annoParam->addConstructorParam($fetchType);
		}
	}

	public function applyDbMeta(DbInfo $dbInfo, PropSourceDef $propSourceDef, 
			EntityProperty $entityProperty, AnnotationSet $as) {
		
		ArgUtils::assertTrue($entityProperty instanceof RelationEntityProperty);
		
		$propertyName = $propSourceDef->getPropertyName();
		$annoManyToMany = $as->getPropertyAnnotation($propertyName, AnnoManyToMany::class);
		CastUtils::assertTrue($annoManyToMany instanceof AnnoManyToMany);
		
		if (null === $annoManyToMany->getMappedBy()) {
			$relation = $entityProperty->getRelation();
			if ($relation instanceof JoinTableToManyRelation) {
				$joinTableName = $relation->getJoinTableName();
				$joinColumnName = $relation->getJoinColumnName();
				$inverseJoinColumnName = $relation->getInverseJoinColumnName();
				
				$database = $dbInfo->getDatabase();
				if ($database->containsMetaEntityName($joinTableName)) {
					$database->removeMetaEntityByName($joinTableName);
				}
				
				$table = $database->createMetaEntityFactory()->createTable($joinTableName);
				$columnFactory = $table->createColumnFactory();
				//@todo id column defs from hangar
				$columnFactory->createIntegerColumn($joinColumnName, 
						$this->columnDefaults->getDefaultIntegerSize(), $this->columnDefaults->getDefaultInterSigned());
				$columnFactory->createIntegerColumn($inverseJoinColumnName, $this->columnDefaults->getDefaultIntegerSize(), 
						$this->columnDefaults->getDefaultInterSigned());
				$table->createIndex(IndexType::PRIMARY, array($joinColumnName, $inverseJoinColumnName));
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
				&& $entityProperty->getType() == RelationEntityProperty::TYPE_MANY_TO_MANY) {
			return CompatibilityLevel::COMMON;
		}
	
		return CompatibilityLevel::NOT_COMPATIBLE;
	}
}
