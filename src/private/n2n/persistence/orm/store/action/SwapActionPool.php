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
// namespace n2n\persistence\orm\store;

// use n2n\core\SysTextUtils;
// use n2n\persistence\orm\store\EntityInfo;
// use n2n\persistence\orm\store\RemoveActionQueue;
// use n2n\persistence\orm\store\action\PersistActionQueue;
// use n2n\persistence\orm\LifecycleEvent;
// use n2n\persistence\orm\store\action\ActionQueueImpl;
// use n2n\persistence\orm\store\PersistenceContext;
// use n2n\persistence\orm\store\TypeChangeActionQueue;
// 
// use n2n\persistence\orm\OrmUtils;
// use n2n\persistence\orm\model\EntityModel;
// use n2n\util\StringUtils;
// /**
//  * Using this class is a really adventure! Do not use this class if you have any other choice!
//  */
// class SwapActionPool extends ActionQueueImpl implements TypeChangeActionQueue {
// 	private $entityModel;
// 	private $newEntityModel;
// 	private $lowestCommonEntityModel;
// 	private $propertiesToPersist = array();
// 	private $propertiesToDelete = array();
	
// 	private $entity;
// 	private $newEntity;
// 	private $id;
	
// 	private $persistenceActionQueue;
// 	private $removeActionQueue;
	
// 	public function __construct(PersistenceContext $persistenceContext, 
// 			PersistActionQueue $persistenceActionQueue, RemoveActionQueue $removeActionQueue) {
// 		parent::__construct($persistenceContext);
		
// 		$this->persistenceActionQueue = $persistenceActionQueue;
// 		$this->removeActionQueue = $removeActionQueue;
// 	}
	
// 	public function initializeWithNewEntityModel($entity, EntityModel $newEntityModel) {
// 		$this->newEntityModel = $newEntityModel;
		
// 		$this->initialize($entity, ReflectionUtils::createObject($newEntityModel->getClass()));

// 		$this->findLowestCommonEntityModel();

// 		foreach ($this->lowestCommonEntityModel->getProperties() as $entityProperty) {
// 			$accessProxy = $entityProperty->getAccessProxy();
				
// 			$accessProxy->setValue($this->newEntity, $accessProxy->getValue($this->entity));
// 		}
// 	}
	
// 	public function initialize($entity, Entity $newEntity) {
// 		OrmUtils::initializeProxy($entity);
// 		$this->entity = $entity;
		
// 		$entityInfo = $this->getPersistenceContext()->getEntityInfo($entity);
// 		if ($entityInfo->getState() != EntityInfo::STATE_MANAGED) {
// 			throw new \InvalidArgumentException(
// 					SysTextUtils::get('n2n_persistance_orm_type_change_original_entity_not_managed'));
// 		}
// 		$this->entityModel = $entityInfo->getEntityModel();
// 		$this->id = $entityInfo->getId();
		
// 		$this->newEntity = $newEntity;
// 		if (isset($this->newEntityModel)) {
// 			return;
// 		}
		
// 		$newEntityInfo = $this->getPersistenceContext()->getEntityInfo($newEntity);
// 		if ($newEntityInfo->getState() != EntityInfo::STATE_NEW
// 				&& !($newEntityInfo->getState() == EntityInfo::STATE_DETACHED 
// 						&& StringUtils::doEqual($this->id, $newEntityInfo->getId()))) {
// 			throw new \InvalidArgumentException(
// 					SysTextUtils::get('n2n_persistance_orm_type_change_new_entity_not_transient'));
// 		}
// 		$this->newEntityModel = $newEntityInfo->getEntityModel();

// 		$this->findLowestCommonEntityModel();
// 	}
	
// 	private function findLowestCommonEntityModel() {
// 		if ($this->entityModel->equals($this->newEntityModel)) {
// 			throw new \InvalidArgumentException(SysTextUtils::get('n2n_persistance_orm_inheritance_type_nothing_to_change'));
// 		}
		
// 		$newEntityModels = $this->newEntityModel->getAllSuperEntityModels(true);
		
// 		foreach ($this->entityModel->getAllSuperEntityModels(true) as $entityModel) {
// // 			$this->propertiesToPersist = array();
			
// 			foreach ($newEntityModels as $newEntityModel) {
// 				if ($entityModel->equals($newEntityModel)) {
// 					$this->lowestCommonEntityModel = $entityModel; 
// 					return;
// 				}
				
// // 				$this->propertiesToPersist = array_merge($this->propertiesToPersist, $newEntityModel->getLevelProperties());
// 			}
			
// 			$this->propertiesToDelete = array_merge($this->propertiesToDelete, $entityModel->getLevelProperties());
// 		}
		
// 		$this->propertiesToPersist = $this->newEntityModel->getEntityProperties();
		
// 		throw new \InvalidArgumentException(SysTextUtils::get('n2n_persistance_orm_incompatible_inheritance_change_class',
// 				array('entity_class' => $entityModel->getClass()->getName(), 
// 						'new_entity_class' => $newEntityModel->getClass()->getName())));
// 	}
	
// 	public function getEntityObj() {
// 		return $this->entity;
// 	}
	
// 	public function getEntityModel() {
// 		return $this->entityModel;
// 	}
	
// 	public function getId() {
// 		return $this->id;
// 	}
	
// 	public function getNewEntity() {
// 		return $this->newEntity;
// 	}
	
// 	public function getNewEntityModel() {
// 		return $this->newEntityModel;
// 	}
	
// 	public function getLowestCommonEntityModel() {
// 		return $this->lowestCommonEntityModel;
// 	}
	
// 	public function activate() {
// 		$removeMeta = $this->entityModel->createActionMeta();
// 		$removeMeta->setId($this->id);
		
// 		$persistMeta = $this->newEntityModel->createActionMeta();
// 		$persistMeta->setId($this->id);
		
// 		$actionJob = new TypeChangingJob($this->removeActionQueue, $removeMeta, $this->persistenceActionQueue, $persistMeta);
// 		$newRawDataMap = new \ArrayObject($this->getPersistenceContext()->getRawDataMapByObject($this->entity)->getArrayCopy());
		
// 		$this->add($actionJob);
				
// 		$this->announceLifecycleEvent(new LifecycleEvent($this->getPersistenceContext(), 
// 				LifecycleEvent::TYPE_ON_TYPE_CHANGE, $this->entity, $this->entityModel, $this->id, $this->newEntity, 
// 				$this->newEntityModel, $this->lowestCommonEntityModel));
			
// 		foreach ($this->propertiesToDelete as $property) {
// 			$referenceColumnName = $property->getReferencedColumnName();
// 			$property->supplyRemoveAction($property->getAccessProxy()->getValue($this->entity), $actionJob);
// 		}
		
// 		foreach ($this->propertiesToPersist as $property) {
// 			$property->supplyPersistAction($property->getAccessProxy()->getValue($this->newEntity), $actionJob);
// 		}
		
// 		foreach ($persistMeta->getRawDataMap() as $key => $value) {
// 			$newRawDataMap[$key] = $value; 
// 		}

// 		$this->triggerObjectDetached($this->entityModel, $this->id, $this->entity);
// 		$this->triggerObjectAdded($this->newEntity, $newRawDataMap);
// 		$this->triggerObjectIdentified($this->newEntityModel, $this->id, $this->newEntity);
		
// 		$that = $this;
// 		$event = new LifecycleEvent($this->getPersistenceContext(), 
// 					LifecycleEvent::TYPE_TYPE_CHANGED, $this->entity, $this->entityModel, $this->id, 
// 					$this->newEntity, $this->newEntityModel, $this->lowestCommonEntityModel);
// 		$actionJob->executeAtEnd(function() use ($that, $event) {
// 			$that->announceLifecycleEvent($event);
// 		});
// 	}	
	
// 	public function execute() {
// 		parent::execute();
		
// 		$this->removeActionQueue->execute();
// 		$this->persistenceActionQueue->execute();
// 	}

// }
