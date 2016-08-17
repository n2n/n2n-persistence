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
namespace n2n\persistence\orm\property\impl;

use n2n\persistence\orm\property\CustomComparableEntityProperty;
use n2n\persistence\orm\model\EntityPropertyCollection;
use n2n\persistence\orm\property\EntityProperty;
use n2n\persistence\orm\model\UnknownEntityPropertyException;
use n2n\reflection\property\AccessProxy;
use n2n\persistence\orm\query\from\MetaTreePoint;
use n2n\persistence\orm\query\QueryState;
use n2n\persistence\orm\store\action\PersistAction;
use n2n\reflection\ArgUtils;
use n2n\persistence\orm\store\action\RemoveAction;
use n2n\persistence\orm\store\operation\MergeOperation;
use n2n\reflection\ReflectionUtils;
use n2n\persistence\orm\property\JoinableEntityProperty;
use n2n\persistence\orm\query\from\meta\TreePointMeta;
use n2n\persistence\orm\query\from\ExtendableTreePoint;
use n2n\persistence\orm\query\from\JoinedTreePoint;
use n2n\persistence\orm\criteria\JoinType;
use n2n\persistence\orm\query\QueryConflictException;
use n2n\persistence\orm\criteria\compare\ComparisonStrategy;
use n2n\persistence\orm\EntityManager;
use n2n\persistence\meta\data\QueryComparator;

class EmbeddedEntityProperty extends EntityPropertyAdapter implements CustomComparableEntityProperty, 
		EntityPropertyCollection, JoinableEntityProperty {
	private $targetClass;
	private $properties = array();
	
	public function __construct(AccessProxy $accessProxy, \ReflectionClass $targetClass) {
		parent::__construct($accessProxy);
		$this->targetClass = $targetClass;
	}
	
	public function getTargetClass() {
		return $this->targetClass;
	}
		
	public function addEntityProperty(EntityProperty $property) {
		$this->properties[$property->getName()] = $property;
		$property->setParent($this);
	}
	
	public function containsEntityPropertyName($name) {
		return isset($this->properties[$name]);
	}

	public function getEntityProperties() {
		return $this->properties;
	}
	
	public function getEntityPropertyByName($name) {
		if (!$this->containsEntityPropertyName($name)) {
			throw new UnknownEntityPropertyException(
					'Unkown entity property: ' . $this->targetClass->getName() . '::$' . $name);
		}
	
		return $this->properties[$name];
	}
	/* (non-PHPdoc)
	 * @see \n2n\persistence\orm\property\CustomComparableEntityProperty::createCustomComparable()
	 */
	public function createCustomComparable(MetaTreePoint $metaTreePoint, QueryState $queryState) {
		return new EmbeddedCustomComparable($metaTreePoint->requestPropertyJoinTreePoint($this->getName(), false), $this);
	}

	/* (non-PHPdoc)
	 * @see \n2n\persistence\orm\property\EntityProperty::createSelection()
	 */
	public function createSelection(MetaTreePoint $metaTreePoint, QueryState $queryState) {
		return new EmbeddedSelection($this, $metaTreePoint->requestPropertyJoinTreePoint($this->getName(), false));
	}

	/* (non-PHPdoc)
	 * @see \n2n\persistence\orm\property\EntityProperty::mergeValue()
	 */
	public function mergeValue($object, $sameEntity, MergeOperation $mergeOperation) {
		if ($object === null) return null;
		
		$mergedObject = null;
		if ($sameEntity) {
			$mergedObject = $object;
		} else {
			$mergedObject = ReflectionUtils::createObject($this->targetClass);
		}
		
		foreach ($this->properties as $property) {
			$mergedPropertyValue = $property->mergeValue($property->readValue($object), $sameEntity, $mergeOperation);
			$property->writeValue($mergedObject, $mergedPropertyValue);
		}
		
		return $mergedObject;
	}

	/* (non-PHPdoc)
	 * @see \n2n\persistence\orm\property\EntityProperty::supplyPersistAction()
	 */
	public function supplyPersistAction($object, $valueHash, PersistAction $persistingJob) {
		ArgUtils::assertTrue($valueHash === null || is_array($valueHash));
		
		foreach ($this->properties as $propertyName => $property)  {
			$propertyValue = null;
			if ($object !== null) {
				$propertyValue = $property->readValue($object);
			}
			$propertyValueHash = null;
			if ($valueHash !== null) {
				ArgUtils::assertTrue(array_key_exists($propertyName, $valueHash));
				$propertyValueHash = $valueHash[$propertyName];
			}
			
			$property->supplyPersistAction($propertyValue, $propertyValueHash, $persistingJob);
		}
	}

	/* (non-PHPdoc)
	 * @see \n2n\persistence\orm\property\EntityProperty::supplyRemoveAction()
	 */
	public function supplyRemoveAction($object, $valueHash, RemoveAction $removeAction) {
		ArgUtils::assertTrue($valueHash === null || is_array($valueHash));
		
		foreach ($this->properties as $propertyName => $property)  {
			$propertyValue = $property->readValue($object);
			$propertyValueHash = null;
			if ($valueHash !== null) {
				ArgUtils::assertTrue(array_key_exists($propertyName, $valueHash));
				$propertyValueHash = $valueHash[$propertyName];
			}
			
			$property->supplyRemoveAction($propertyValue, $propertyValueHash, $removeAction);
		}
	}

	/* (non-PHPdoc)
	 * @see \n2n\persistence\orm\property\EntityProperty::buildValueHash()
	 */
	public function buildValueHash($value, EntityManager $em) {
		if ($value === null) return null;
		
		$valueHash = array();
		foreach ($this->properties as $propertyName => $property)  {
			$valueHash[$propertyName] = $property->readValue($value);
		}
		return $valueHash;
	}
	/* (non-PHPdoc)
	 * @see \n2n\persistence\orm\property\JoinableEntityProperty::createJoinTreePoint()
	 */
	public function createJoinTreePoint(TreePointMeta $treePointMeta, QueryState $queryState) {
		return new EmbeddedTreePoint($queryState, $treePointMeta, $this);
	}
	
	public function hasEmbeddedEntityPropertyCollection(): bool {
		return true;
	}
	
	public function getEmbeddedEntityPropertyCollection(): EntityPropertyCollection {
		return $this;
	}
}

class EmbeddedTreePoint extends ExtendableTreePoint implements JoinedTreePoint {
	private $embeddedEntityProperty;
	
	public function __construct(QueryState $queryState, TreePointMeta $treePointMeta, 
			EmbeddedEntityProperty $embeddedEntityProperty) {
		parent::__construct($queryState, $embeddedEntityProperty, $treePointMeta);
		
		$this->embeddedEntityProperty = $embeddedEntityProperty;
	}
	
	public function setJoinType($joinType) {
		if ($joinType == JoinType::INNER/* || $joinType == JoinType::LEFT*/) return;
		
		throw new QueryConflictException('Can not perform ' . $joinType . ' JOIN on embedded property ' 
				. $this->embeddedEntityProperty->toPropertyString() . '. Use INNER JOIN.');
	}
	/* (non-PHPdoc)
	 * @see \n2n\persistence\orm\query\from\JoinedTreePoint::getJoinType()
	 */
	public function getJoinType() {
		return JoinType::INNER;
	}
	
	public function getOnQueryComparator(): QueryComparator {
		throw new QueryConflictException('no on clause available for JOIN on embedded property ' 
				. $this->embeddedEntityProperty->toPropertyString() . '.');
	}
	
	private $comparisonStrategy;
	private $selection;
	/* (non-PHPdoc)
	 * @see \n2n\persistence\orm\query\QueryPoint::requestComparisonStrategy()
	 */
	public function requestComparisonStrategy() {
		if ($this->comparisonStrategy !== null) return $this->comparisonStrategy;
		return $this->comparisonStrategy = new ComparisonStrategy(null, 
				$this->embeddedEntityProperty->createCustomComparable($this, $this->queryState));
	}
	/* (non-PHPdoc)
	 * @see \n2n\persistence\orm\query\QueryPoint::requestSelection()
	 */
	public function requestSelection() {
		if ($this->selection !== null) return $this->selection;
		return $this->selection = $this->embeddedEntityProperty->createSelection($this, $this->queryState);
	}
	/* (non-PHPdoc)
	 * @see \n2n\persistence\orm\query\QueryPoint::requestRepresentableQueryItem()
	 */
	public function requestRepresentableQueryItem() {
		throw new QueryConflictException('Embedded property is not representable by a single query item: ' 
				. $this->embeddedEntityProperty->toPropertyString());
	}

}
