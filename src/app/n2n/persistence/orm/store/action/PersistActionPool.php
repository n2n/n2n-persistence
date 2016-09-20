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
namespace n2n\persistence\orm\store\action;

use n2n\persistence\orm\store\EntityInfo;

use n2n\reflection\ArgUtils;
use n2n\persistence\orm\proxy\EntityProxy;
use n2n\persistence\orm\model\EntityModel;
use n2n\util\ex\IllegalStateException;
use n2n\persistence\orm\store\PersistenceOperationException;
use n2n\persistence\orm\LifecycleEvent;
use n2n\persistence\orm\store\ValueHashesFactory;
use n2n\persistence\orm\store\action\supply\PersistSupplyJob;
use n2n\persistence\orm\store\operation\PersistOperation;

class PersistActionPool {
	private $actionQueue;
	private $persistActions = array();
	private $unsuppliedPersistActions = array();
	private $supplyJobs = array();
	private $emptyPersistActions = array();
	private $frozen;
	
	public function __construct(ActionQueue $actionQueue) {
		$this->actionQueue = $actionQueue;
	}
	
	public function containsAction($entity) {
		ArgUtils::assertTrue(is_object($entity));
		return isset($this->persistActions[spl_object_hash($entity)]);
	}
	
	private function findAction($entity) {
		ArgUtils::assertTrue(is_object($entity));
		$objHash = spl_object_hash($entity);
		if (isset($this->persistActions[$objHash])) {
			return $this->persistActions[$objHash];
		}
		
		$em = $this->actionQueue->getEntityManager();
		$persistenceContext = $em->getPersistenceContext();
		
		if ($entity instanceof EntityProxy
				&& !$persistenceContext->getEntityProxyManager()->isProxyInitialized($entity)) {
			$entityInfo = $persistenceContext->getEntityInfo($entity, $em->getEntityModelManager());
			return new UninitializedPersistAction($entityInfo->getEntityModel(), $entityInfo->getId());
		}
		
		return null;
	}
	
	public function getAction($entity) {
		if (null !== ($persistAction = $this->findAction($entity))) {
			return $persistAction;
		}
		
		$em = $this->actionQueue->getEntityManager();
		$persistenceContext = $em->getPersistenceContext();
		$entityInfo = $persistenceContext->getEntityInfo($entity, $em->getEntityModelManager());
		
		if ($entityInfo->getState() == EntityInfo::STATE_MANAGED) {
			throw new IllegalStateException('No PersistAction available for passed entity.');
		}
		
		throw new PersistenceOperationException('No valid persist action for '
				. $entityInfo->getState() . ' entity: ' . $entityInfo->toEntityString());
	}
	
	public function removeAction($entity) {
		IllegalStateException::assertTrue(!$this->frozen);
		
		ArgUtils::assertTrue(is_object($entity));
		$objHash = spl_object_hash($entity);
		if (!isset($this->persistActions[$objHash])) return;
		
		$persistAction = $this->persistActions[$objHash];
		
		$persistAction->disable();
		$this->actionQueue->remove($persistAction);
		unset($this->persistActions[$objHash]);
		unset($this->unsuppliedPersistActions[$objHash]);
		
		$persistenceContext = $this->actionQueue->getEntityManager()->getPersistenceContext();
		if ($persistAction->isNew()) {
			$persistenceContext->detachEntityObj($entity);
		} else {
			$persistenceContext->manageEntityObj($entity, $persistAction->getEntityModel());
		}
	}
	/**
	 *
	 * @param $entity
	 * @param bool $initialize
	 * @return PersistAction
	 */
	public function getOrCreateAction($entity) {
		if (null !== ($persistAction = $this->findAction($entity))) {
			return $persistAction;
		}
		
		IllegalStateException::assertTrue(!$this->frozen);
				
		$em = $this->actionQueue->getEntityManager();
		$persistenceContext = $em->getPersistenceContext();
				
		$entityInfo = $persistenceContext->getEntityInfo($entity, 
				$em->getEntityModelManager());
		$entityModel = $entityInfo->getEntityModel();
		
		$persistAction = $this->createPersistAction($entity, $entityInfo);
		$objHash = spl_object_hash($entity);
		$this->persistActions[$objHash] = $persistAction;
		$this->unsuppliedPersistActions[$objHash] = $persistAction;
		$this->actionQueue->add($persistAction);
					
		return $persistAction;
	}
	
	private function createPersistAction($entity, EntityInfo $entityInfo) {
		$entityModel = $entityInfo->getEntityModel();
		
		switch ($entityInfo->getState()) {
			case EntityInfo::STATE_NEW:
				$persistAction = new InsertPersistAction($this->actionQueue, $entityModel,
						$entityInfo->getId(), $entity, $this->createActionMeta($entityModel, $entityInfo));
				$this->manage($persistAction);
				return $persistAction;
		
			case EntityInfo::STATE_MANAGED:
				if (!$entityInfo->hasId()) {
					throw new IllegalStateException('Unable to update entity with unkown id: '
							. $entityInfo->toEntityString());
				}
				return new UpdatePersistAction($this->actionQueue, $entityModel, $entityInfo->getId(), 
						$entity, $this->createActionMeta($entityModel, $entityInfo));
		
			case EntityInfo::STATE_REMOVED:
				if (!$entityInfo->hasId()) {
					throw new IllegalStateException('Unable to update entity with unkown id: '
							. $entityInfo->toEntityString());
				}
				$persistAction = new UpdatePersistAction($this->actionQueue, $entityModel, $entityInfo->getId(),
						$entity, $this->createActionMeta($entityModel, $entityInfo));
				$this->manage($persistAction);
				return $persistAction;
				
// 				throw new PersistenceOperationException('Can not persist removed entity: '
// 						. $entityInfo->toEntityString());
		
			case EntityInfo::STATE_DETACHED:
				throw new PersistenceOperationException('Can not persist detached entity: '
						. $entityInfo->toEntityString());
		}
	}
	
	private function createActionMeta(EntityModel $entityModel, EntityInfo $entityInfo) {
		$actionMeta = $entityModel->createActionMeta();
		if ($entityInfo->hasId()) {
			$actionMeta->setIdRawValue($entityModel->getIdDef()->getEntityProperty()
					->buildRaw($entityInfo->getId(), $this->actionQueue->getEntityManager()->getPdo()));
		}
		return $actionMeta;
	}
	
	private function getPersistActions() {
		return $this->persistActions;
	}
	
	private function manage(PersistActionAdapter $persistAction) {
		$entityModel = $persistAction->getEntityModel();
		$entity = $persistAction->getEntityObj();
		
		$persistenceContext = $this->actionQueue->getEntityManager()->getPersistenceContext();
		$persistenceContext->manageEntityObj($entity, $entityModel);
		
		if ($persistAction->hasId()) {
			$persistenceContext->identifyManagedEntityObj($entity, $persistAction->getId());
		} else {		
			$persistAction->executeAtEnd(function () use ($entity, $persistenceContext, $persistAction) {
				$persistenceContext->identifyManagedEntityObj($persistAction->getEntityObj(), $persistAction->getId());
				$persistAction->getEntityModel()->getIdDef()->getEntityProperty()
						->writeValue($entity, $persistAction->getId());
			});
		}
		
		$this->actionQueue->announceLifecycleEvent(new LifecycleEvent(LifecycleEvent::PRE_PERSIST, $entity, 
				$entityModel, $persistAction->getId()));
		
		$that = $this;
		$persistAction->executeAtEnd(function () use ($that, $persistAction) {
			$that->actionQueue->announceLifecycleEvent(new LifecycleEvent(LifecycleEvent::POST_PERSIST, 
					$persistAction->getEntityObj(), $persistAction->getEntityModel(), $persistAction->getId()));
		});
		
		return $entity;
	}
	
	public function isFrozend() {
		return $this->frozen;
	}
	
	public function freeze() {
		foreach ($this->supplyJobs as $supplyJob) {
			$supplyJob->init();
		}
		
		$this->frozen = true;
	}
	
	public function clear() {
		$this->frozen = false;
		$this->persistActions = array();
		$this->unsuppliedPersistActions = array();
		$this->supplyJobs = array();
		$this->emptyPersistActions = array();
	}
	
	public function supply() {
		IllegalStateException::assertTrue($this->frozen);
				
		foreach ($this->supplyJobs as $supplyJob) {
			$supplyJob->execute();
		}
		
		$this->supplyJobs = array();
	}

	public function prepareSupplyJobs() {
		IllegalStateException::assertTrue(!$this->frozen);
		
		if (empty($this->unsuppliedPersistActions)) return false;
		
		do {
			$updatePersistActions = array();
			
			while (null !== ($persistAction = array_pop($this->unsuppliedPersistActions))) {
				if ($persistAction->isDisabled()) continue;
				
				if ($persistAction->isNew()) {
					$this->refreshSupplyJob($this->supplyJobs[] = new PersistSupplyJob($persistAction));
					continue;
				}

				if (null !== ($supplyJob = $this->checkDiff($persistAction))) {
					$updatePersistActions[] = $persistAction;
					$this->supplyJobs[] = $supplyJob;
				} else {
					$this->emptyPersistActions[] = $persistAction;
				}
			}

			while ($this->announceUpdateLifecylcEvents($updatePersistActions)) {
				$persistOperation = new PersistOperation($this->actionQueue);
				foreach ($updatePersistActions as $updatePersistAction) {
					$persistOperation->cascade($updatePersistAction->getEntityObj());
				}
				
				foreach ($this->supplyJobs as $supplyJob) {
					$this->refreshSupplyJob($supplyJob);
				}
				
				$updatePersistActions = array();
				foreach ($this->emptyPersistActions as $key => $emptyPersistAction) {
					if ($emptyPersistAction->isDisabled()) continue;
		
					if (null !== ($supplyJob = $this->checkDiff($emptyPersistAction))) {
						$updatePersistActions[] = $emptyPersistAction;
						unset($this->emptyPersistActions[$key]);
						$this->supplyJobs[] = $supplyJob;
					}
				}
			}
		} while (!empty($this->unsuppliedPersistActions));
		
		return true;
	}

	private function checkDiff(PersistActionAdapter $persistAction) {
		$entityModel = $persistAction->getEntityModel();
		$entity = $persistAction->getEntityObj();
		$hasher = new ValueHashesFactory($entityModel, $persistAction->getActionQueue()->getEntityManager());
	
		$values = array();
		$valuesHash = $hasher->create($entity, $values);
		$oldValuesHash = $this->actionQueue->getEntityManager()->getPersistenceContext()
				->getValuesHashByEntityObj($entity);
		
		if ($valuesHash->matches($oldValuesHash)) {
			return null;
		}
		
		$supplyJob = new PersistSupplyJob($persistAction, $oldValuesHash);
		$supplyJob->setValues($values);
		$supplyJob->setValueHashes($valuesHash);
		
		$supplyJob->prepare();
		return $supplyJob;
	}
	
	private function refreshSupplyJob(PersistSupplyJob $supplyJob) {
		if ($supplyJob->isDisabled()) return;
		
		$persistAction = $supplyJob->getPersistAction();
		
		$entityModel = $persistAction->getEntityModel();
		$entity = $persistAction->getEntityObj();
		
		$hasher = new ValueHashesFactory($entityModel, $this->actionQueue->getEntityManager());
		$values = array();
		$valueHashes = $hasher->create($entity, $values);

// 		cascade could destroy this
// 		if ($valueHashes === $supplyJob->getValueHashes()) return;
		
		$supplyJob->setValues($values);
		$supplyJob->setValueHashes($valueHashes);
		
		$supplyJob->prepare();
	}
	
	private function announceUpdateLifecylcEvents(array $persistActions) {
		$callbacksInvoked = false;
		
		foreach ($persistActions as $persistAction) {
			if ($persistAction->isDisabled()) continue;
			
			if ($this->actionQueue->announceLifecycleEvent(new LifecycleEvent(LifecycleEvent::PRE_UPDATE,
					$persistAction->getEntityObj(), $persistAction->getEntityModel(), $persistAction->getId()))) {
				$callbacksInvoked = true;
			}

			$that = $this;
			$persistAction->executeAtEnd(function () use ($that, $persistAction) {
				$that->actionQueue->announceLifecycleEvent(new LifecycleEvent(LifecycleEvent::POST_UPDATE,
						$persistAction->getEntityObj(), $persistAction->getEntityModel(), $persistAction->getId()));
			});			
		}	
		
		return $callbacksInvoked;
	}
}
