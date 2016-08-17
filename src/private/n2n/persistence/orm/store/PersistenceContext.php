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
namespace n2n\persistence\orm\store;


use n2n\persistence\orm\model\EntityModel;
use n2n\reflection\ReflectionUtils;
use n2n\util\ex\IllegalStateException;
use n2n\persistence\orm\model\EntityPropertyCollection;
use n2n\persistence\orm\proxy\EntityProxyManager;
use n2n\persistence\orm\model\EntityModelManager;
use n2n\reflection\ArgUtils;
use n2n\persistence\orm\proxy\LazyInitialisationException;
use n2n\persistence\orm\proxy\EntityProxyAccessListener;
use n2n\persistence\orm\EntityManager;
use n2n\persistence\orm\proxy\EntityProxyInitializationException;
use n2n\persistence\orm\proxy\CanNotCreateEntityProxyClassException;
use page\bo\Page;
use n2n\reflection\ObjectCreationFailedException;
use n2n\persistence\orm\EntityCreationFailedException;

class PersistenceContext {
	private $entityProxyManager;
	
	private $managedEntities = array();
	private $removedEntities = array();
	
	private $entityValueHashes = array();
	private $entityIdReps = array();
	private $entityIdentifiers = array();
	private $entityModels = array();
	
	public function __construct(EntityProxyManager $entityProxyManager) {
		$this->entityProxyManager = $entityProxyManager;
	}
	/**
	 * @return EntityProxyManager
	 */
	public function getEntityProxyManager() {
		return $this->entityProxyManager;
	}
	/**
	 * 
	 */
	public function clear() {
		$this->managedEntities = array();
		$this->entityValueHashes = array();
		
		$this->removedEntities = array();
		
		$this->entityIdReps = array();
		$this->entityIdentifiers = array();
		$this->entityModels = array();
	}
	
	public function getIdByEntity($entity) {
		ArgUtils::assertTrue(is_object($entity));
		
		$objHash = spl_object_hash($entity);
		if (isset($this->entityIdReps[$objHash])) {
			return $this->entityIdReps[$objHash];
		}
		
		return null;
	}
	
	public function getEntityById(EntityModel $entityModel, $id) {
		return $this->getEntityByIdRep($entityModel, 
				$entityModel->getIdDef()->getEntityProperty()->valueToRep($id));
	}
	
	public function getEntityByIdRep(EntityModel $entityModel, $idRep) {
		$className = $entityModel->getClass()->getName();
		
		if (isset($this->entityIdentifiers[$className][$idRep])) {
			return $this->entityIdentifiers[$className][$idRep];
		}
		
		return null;
	}
	
	public function getRemovedEntity(EntityModel $entityModel, $id) {
		if ($id === null) return null;
	
		$idRep = $entityModel->getIdDef()->getEntityProperty()->valueToRep($id);
	
		return $this->getRemovedEntityByIdRep($entityModel, $idRep);
	}
	
	public function getRemovedEntityByIdRep(EntityModel $entityModel, $idRep) {
		$className = $entityModel->getClass()->getName();
		
		if (!isset($this->entityIdentifiers[$className][$idRep])) return null;
		
		$objHash = spl_object_hash($this->entityIdentifiers[$className][$idRep]);
		if (isset($this->removedEntities[$objHash])) {
			return $this->removedEntities[$objHash];
		}
		
		return null;
	}
	
	/**
	 * @param $entity
	 * @return \n2n\persistence\orm\store\EntityInfo
	 */
	public function getEntityInfo($entity, EntityModelManager $entityModelManager) {
		ArgUtils::assertTrue(is_object($entity));
		
		$objectHash = spl_object_hash($entity);
		$entityModel = null;
		$id = null;
		if (isset($this->entityIdReps[$objectHash])) {
			$id = $this->entityModels[$objectHash]->getIdDef()->getEntityProperty()->repToValue(
					$this->entityIdReps[$objectHash]);	
		}
		
		if (isset($this->managedEntities[$objectHash])) {
			return new EntityInfo(EntityInfo::STATE_MANAGED, $this->entityModels[$objectHash], $id);
		}
			
		if (isset($this->removedEntities[$objectHash])) {
			return new EntityInfo(EntityInfo::STATE_REMOVED, $this->entityModels[$objectHash], $id);
		}
		
		$entityModel = $entityModelManager->getEntityModelByEntity($entity);
		$idDef = $entityModel->getIdDef();
		$id = $idDef->getEntityProperty()->readValue($entity);
	
		if ($idDef->isGenerated()) {
			return new EntityInfo(($id === null ? EntityInfo::STATE_NEW : EntityInfo::STATE_DETACHED), 
					$entityModel, $id);
		}
	
		return new EntityInfo(EntityInfo::STATE_NEW, $entityModel, $id);
	}
	
	public function getManagedEntities() {
		return $this->managedEntities;
	}
	
	public function getManagedEntityByIdRep(EntityModel $entityModel, $idRep) {
		$className = $entityModel->getClass()->getName();
		
		if (isset($this->entityIdentifiers[$className][$idRep])
				&& $this->containsManagedEntity($this->entityIdentifiers[$className][$idRep])) {
			return $this->entityIdentifiers[$className][$idRep];
		}
		
		return null;
	}
	
	public function getManagedEntity(EntityModel $entityModel, $id) {
		if ($id === null) return null;
		
		$idRep = $entityModel->getIdDef()->getEntityProperty()->valueToRep($id);
		
		return $this->getManagedEntityByIdRep($entityModel, $idRep);
	}
	
	public function getOrCreateManagedEntity(EntityModel $entityModel, $id) {
		if (null !== ($entity = $this->getManagedEntity($entityModel, $id))) {
			return $entity;
		}
		
		return $this->createManagedEntity($entityModel, $id);
	}
	
	public function createManagedEntity(EntityModel $entityModel, $id) {
		$entityObj = null;
		try {
			$entityObj = ReflectionUtils::createObject($entityModel->getClass(), true);
		} catch (ObjectCreationFailedException $e) {
			throw new EntityCreationFailedException('Could not create entity object for ' 
					. EntityInfo::buildEntityString($entityModel, $id), 0, $e);
		}
		$this->manageEntity($entityObj, $entityModel);
		$this->identifyManagedEntity($entityObj, $id);
		return $entityObj;
	}

	public function getOrCreateEntityProxy(EntityModel $entityModel, $id, EntityManager $em) {
		if ($id === null) return null;
	
		if (null !== ($entity = $this->getEntityByIdRep($entityModel, $id))) {
			return $entity;	
		}
	
		if ($entityModel->hasSubEntityModels()) {
			throw new EntityProxyInitializationException(
					'Entity which gets inherited by other entities can not be lazy initialized: '
							. EntityInfo::buildEntityString($entityModel, $id));
		}
	
		try {
			$entity = $this->entityProxyManager->createProxy($entityModel->getClass(), 
					new EntityProxyAccessListener($em, $entityModel, $id));
		} catch (CanNotCreateEntityProxyClassException $e) {
			throw new LazyInitialisationException('Cannot lazy initialize class: '
					. EntityInfo::buildEntityString($entityModel, $id), 0, $e);
		}
		
		$entityModel->getIdDef()->getEntityProperty()->writeValue($entity, $id);
		
		$this->manageEntity($entity, $entityModel);
		$this->identifyManagedEntity($entity, $id);
	
		return $entity;
	}
	
	public function manageEntity($entity, EntityModel $entityModel) {
		$objHash = spl_object_hash($entity);
		unset($this->removedEntities[$objHash]);
		
		$this->managedEntities[$objHash] = $entity;
		$this->entityModels[$objHash] = $entityModel;
	}
	
	public function containsManagedEntity($entity) {
		ArgUtils::assertTrue(is_object($entity));
		return isset($this->managedEntities[spl_object_hash($entity)]);
	}
	
	public function removeEntity($entity) {
		$this->validateEntityManaged($entity);
		
		$objHash = spl_object_hash($entity);
		unset($this->managedEntities[$objHash]);
		$this->removedEntities[$objHash] = $entity;
	}
	
	private function removeEntityIdentifiction(EntityModel $entityModel, $idRep) {
		do {
			unset($this->entityIdentifiers[$entityModel->getClass()->getName()][$idRep]);
		} while (null !== ($entityModel = $entityModel->getSuperEntityModel()));
	}
	
	public function detachEntity($entity) {
		$objHash = spl_object_hash($entity);
		
		if (isset($this->entityModels[$objHash])) {
			$entityModel = $this->entityModels[$objHash];
			unset($this->entityModels[$objHash]);
			
			if (isset($this->entityIdReps[$objHash])) {
				$this->removeEntityIdentifiction($entityModel, $this->entityIdReps[$objHash]);
				unset($this->entityIdReps[$objHash]);
			}
		}
		
		unset($this->entityIdReps[$objHash]);
		unset($this->managedEntities[$objHash]);
		unset($this->entityValueHashes[$objHash]);
		unset($this->removedEntities[$objHash]);
// 		$this->detachedEntities[$objHash] = $entity;
	}
	                
	public function detachNotManagedEntities() {
		foreach ($this->removedEntities as $entity) {
			$this->detachEntity($entity);
// 			unset($this->entityIdReps[$objHash]);
// 			unset($this->managedEntities[$objHash]);
// 			unset($this->entityValueHashes[$objHash]);
// 			unset($this->removedEntities[$objHash]);
		}
	}
	
	private function validateEntityManaged($entity) {
		if ($this->containsManagedEntity($entity)) return;
		
		throw new \InvalidArgumentException('Passed entity not managed');
	}
	
	public function identifyManagedEntity($entity, $id) {
		ArgUtils::assertTrue(is_object($entity));
		ArgUtils::assertTrue($id !== null);
		
		$objHash = spl_object_hash($entity);
		if (!isset($this->managedEntities[$objHash])) {
			throw new IllegalStateException('Unable to identify non managed entity.');
		}
		
		$entityModel = $this->entityModels[$objHash];
		$idRep = $entityModel->getIdDef()->getEntityProperty()->valueToRep($id);
		
		if (isset($this->entityIdReps[$objHash]) && $this->entityIdReps[$objHash] !== $idRep) {
			throw new IllegalStateException('Entity already identified with other id: '
					. $this->entityIdReps[$objHash]);
		}
				
		$this->entityIdReps[$objHash] = $idRep;
		
		do {		
			$className = $entityModel->getClass()->getName();
			if (!isset($this->entityIdentifiers[$className])) {
				$this->entityIdentifiers[$className] = array();
			} else if (isset($this->entityIdentifiers[$className][$idRep])) {
				if ($this->entityIdentifiers[$className][$idRep] === $entity) return;
				
				throw new IllegalStateException('Other entity instance already exists in persistence context: ' 
						. EntityInfo::buildEntityString($entityModel, $id));
			}
			
			$this->entityIdentifiers[$className][$idRep] = $entity;
		} while (null !== ($entityModel = $entityModel->getSuperEntityModel()));
	}
	
	public function getEntityModelByEntity($entity) {
		$objHash = spl_object_hash($entity);
		if (isset($this->entityModels[$objHash])) {
			return $this->entityModels[$objHash];
		}
		
		throw new \InvalidArgumentException('Entity has status new');
	}	
	
	public function mapValues($entity, array $values) {
		$this->validateEntityManaged($entity);
		
		$entityModel = $this->getEntityModelByEntity($entity);
		$this->entityProxyManager->disposeProxyAccessListenerOf($entity);
		
		foreach ($entityModel->getEntityProperties() as $propertyName => $entityProperty) {
			if (!array_key_exists($propertyName, $values)) continue;
			$entityProperty->writeValue($entity, $values[$propertyName]);
		}
	}
	
	public function containsValueHashes($entity) {
		$this->validateEntityManaged($entity);
		
		return isset($this->entityValueHashes[spl_object_hash($entity)]);
	}
	
	public function updateValueHashes($entity, array $values, array $valueHashes, EntityManager $em) {
		$this->validateEntityManaged($entity);
	
		$entityModel = $this->getEntityModelByEntity($entity);
		
		$hashFactory = new ValueHashesFactory($entityModel, $em);
		$hashFactory->setValues($values);
		$hashFactory->setValueHashes($valueHashes);
		
		$this->entityValueHashes[spl_object_hash($entity)] = $hashFactory->create($entity);
	}
	
	public function getValueHashesByEntity($entity) {
		$objectHash = spl_object_hash($entity);
		
		if (isset($this->entityValueHashes[$objectHash])) {
			return $this->entityValueHashes[$objectHash];
		}
		
		throw new IllegalStateException();
	}
}

class ValueHashesFactory {
	private $entityPropertyCollection;
	private $valueHashes = array();
	private $values = array();
	private $em;	
	
	public function __construct(EntityPropertyCollection $entityPropertyCollection, EntityManager $em) {
		$this->entityPropertyCollection = $entityPropertyCollection;
		$this->em = $em;
	}
	
	public function setValueHashes(array $valueHashes) {
		$this->valueHashes = $valueHashes;
	}
	
	public function getValueHashes() {
		return $this->valueHashes;
	}

	public function setValues(array $values) {
		$this->values = $values;
	}
	
	public function getValues() {
		return $this->values;
	}
	
	public function create($object, &$values = array()) {
		$hash = array();
		
		foreach ($this->entityPropertyCollection->getEntityProperties() as $propertyName => $entityProperty) {
			if (array_key_exists($propertyName, $this->valueHashes)) {
				$hash[$propertyName] = $this->valueHashes[$propertyName];
				continue;
			}
			
			if (array_key_exists($propertyName, $this->values)) {
				$hash[$propertyName] = $entityProperty->buildValueHash(
						$values[$propertyName] = $this->values[$propertyName], $this->em);
				continue;
			}
			
			$hash[$propertyName] = $entityProperty->buildValueHash(
					$values[$propertyName] = $entityProperty->readValue($object), $this->em);
		}
		
		return $hash;
	}
}
