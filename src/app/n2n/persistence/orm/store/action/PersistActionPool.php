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
 * Bert Hofmänner.......: Idea, Frontend UI, Community Leader, Marketing
 * Thomas Günther.......: Developer, Hangar
 */
namespace n2n\persistence\orm\store\action;

use n2n\persistence\orm\store\EntityInfo;

use n2n\util\type\ArgUtils;
use n2n\persistence\orm\proxy\EntityProxy;
use n2n\persistence\orm\model\EntityModel;
use n2n\util\ex\IllegalStateException;
use n2n\persistence\orm\store\PersistenceOperationException;
use n2n\persistence\orm\LifecycleEvent;
use n2n\persistence\orm\store\ValueHashColFactory;
use n2n\persistence\orm\store\action\supply\PersistSupplyJob;
use n2n\persistence\orm\store\operation\PersistOperation;
use n2n\persistence\orm\proxy\EntityProxyManager;

class PersistActionPool {
	private ActionQueue $actionQueue;
	private $persistActions = array();
	private $unsuppliedPersistActions = array();
	private $persistSupplyJobs = array();
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

		$persistenceContext = $this->actionQueue->getPersistenceContext();

		if ($entity instanceof EntityProxy
				&& !EntityProxyManager::getInstance()->isProxyInitialized($entity)) {
			$entityInfo = $persistenceContext->getEntityInfo($entity);
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
		ArgUtils::assertTrue(is_object($entity));
		$objHash = spl_object_hash($entity);
		if (!isset($this->persistActions[$objHash])) return;

		IllegalStateException::assertTrue(!$this->frozen);

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
	 * @param bool $ignoreRemovedState
	 * @return PersistAction
	 */
	public function getOrCreateAction($entity, bool $ignoreRemovedState): PersistAction {
		if (null !== ($persistAction = $this->findAction($entity))) {
			return $persistAction;
		}

		IllegalStateException::assertTrue(!$this->frozen);

		$persistenceContext = $this->actionQueue->getPersistenceContext();

		$entityInfo = $persistenceContext->getEntityInfo($entity);

		return $this->createPersistAction($entity, $entityInfo, $ignoreRemovedState);
	}

	private function registerPersistAction($entity, PersistAction $persistAction): void {
		$objHash = spl_object_hash($entity);
		$this->persistActions[$objHash] = $persistAction;
		$this->unsuppliedPersistActions[$objHash] = $persistAction;
		$this->actionQueue->add($persistAction);
	}

	private function createPersistAction($entity, EntityInfo $entityInfo, bool $ignoreRemovedState): PersistAction {
		$entityModel = $entityInfo->getEntityModel();

		switch ($entityInfo->getState()) {
			case EntityInfo::STATE_NEW:
				$persistAction = new InsertPersistAction($this->actionQueue, $entityModel,
						$entityInfo->getId(), $entity, $this->createActionMeta($entityModel, $entityInfo));
				$this->registerPersistAction($entity, $persistAction);
				$this->manage($persistAction);
				return $persistAction;

			case EntityInfo::STATE_MANAGED:
				if (!$entityInfo->hasId()) {
					throw new IllegalStateException('Unable to update entity with unknown id: '
							. $entityInfo->toEntityString());
				}
				$persistAction = new UpdatePersistAction($this->actionQueue, $entityModel, $entityInfo->getId(),
						$entity, $this->createActionMeta($entityModel, $entityInfo));
				$this->registerPersistAction($entity, $persistAction);
				return $persistAction;

			case EntityInfo::STATE_REMOVED:
				if (!$entityInfo->hasId()) {
					throw new IllegalStateException('Unable to remove entity with unknown id: '
							. $entityInfo->toEntityString());
				}

				if (!$ignoreRemovedState) {
					throw new PersistenceOperationException('Can not persist removed entity: '
							. $entityInfo->toEntityString());
				}

				$persistAction = new UpdatePersistAction($this->actionQueue, $entityModel, $entityInfo->getId(),
						$entity, $this->createActionMeta($entityModel, $entityInfo));
				$this->registerPersistAction($entity, $persistAction);
				$this->manage($persistAction);
				return $persistAction;

			case EntityInfo::STATE_DETACHED:
				throw new PersistenceOperationException('Can not persist detached entity: '
						. $entityInfo->toEntityString());
		}

		throw new IllegalStateException('Illegal state: ' . $entityInfo->getState());
	}

	private function createActionMeta(EntityModel $entityModel, EntityInfo $entityInfo) {
		$actionMeta = $entityModel->createActionMeta($entityInfo->getState() === EntityInfo::STATE_NEW);
		if ($entityInfo->hasId()) {
			$actionMeta->setIdRawValue($entityModel->getIdDef()->getEntityProperty()
					->buildRaw($entityInfo->getId(), $this->actionQueue->getEntityManager()->getPdo()));
		}
		return $actionMeta;
	}

	private function getPersistActions() {
		return $this->persistActions;
	}

	private function manage(PersistActionAdapter $persistAction, ?LifecycleEvent &$prePersistEvent = null): void {
		$entityModel = $persistAction->getEntityModel();
		$entity = $persistAction->getEntityObj();

		$persistenceContext = $this->actionQueue->getPersistenceContext();
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

//		$that = $this;
//		$persistAction->executeAtEnd(function () use ($that, $persistAction) {
//			$that->actionQueue->announceLifecycleEvent(new LifecycleEvent(LifecycleEvent::POST_PERSIST,
//					$persistAction->getEntityObj(), $persistAction->getEntityModel(), $persistAction->getId(),
//					$persistAction->getMeta()));
//		});
//
//		// Moved down so the entity has already the state managed when the external listener is called. This is to avoid
//		// problems if the called listener is flushing the EntityManager or persists the same entity again.
//		$this->actionQueue->announceLifecycleEvent(new LifecycleEvent(LifecycleEvent::PRE_PERSIST, $entity,
//				$entityModel, $persistAction->getId(), $persistAction->getMeta()));

	}

	public function isFrozend() {
		return $this->frozen;
	}

	public function freeze(): void {
		IllegalStateException::assertTrue(!$this->frozen && empty($this->unsuppliedPersistActions));

		$this->frozen = true;

		foreach ($this->persistSupplyJobs as $supplyJob) {
			$supplyJob->init();
		}
	}

	public function clear(): void {
		foreach ($this->persistActions as $action) {
			$this->actionQueue->remove($action);
		}

		$this->frozen = false;
		$this->persistActions = array();
		$this->unsuppliedPersistActions = array();
		$this->persistSupplyJobs = array();
		$this->emptyPersistActions = array();
	}

	public function supply() {
		IllegalStateException::assertTrue($this->frozen);

		foreach ($this->persistSupplyJobs as $supplyJob) {
			$supplyJob->execute();
		}

		$this->persistSupplyJobs = array();
	}

	public function prepareSupplyJobs(): bool {
		IllegalStateException::assertTrue(!$this->frozen);

		if (empty($this->unsuppliedPersistActions)) return false;

		do {
			$updatePersistActions = array();

			while (null !== ($persistAction = array_pop($this->unsuppliedPersistActions))) {
				if ($persistAction->isDisabled()) continue;

				if ($persistAction->isNew()) {
					$this->refreshSupplyJob($this->persistSupplyJobs[] = new PersistSupplyJob($persistAction));
					$updatePersistActions[] = $persistAction;
					continue;
				}

				if (null !== ($persistSupplyJob = $this->checkDiff($persistAction))) {
					$updatePersistActions[] = $persistAction;
					$this->persistSupplyJobs[] = $persistSupplyJob;
				} else {
					$this->emptyPersistActions[] = $persistAction;
				}
			}

			while ($this->announceUpdateLifecylcEvents($updatePersistActions)) {
				$persistOperation = new PersistOperation($this->actionQueue);
				foreach ($updatePersistActions as $updatePersistAction) {
					$persistOperation->cascade($updatePersistAction->getEntityObj());
				}

				foreach ($this->persistSupplyJobs as $persistSupplyJob) {
					$this->refreshSupplyJob($persistSupplyJob);
				}

				$updatePersistActions = array();
				foreach ($this->emptyPersistActions as $key => $emptyPersistAction) {
					if ($emptyPersistAction->isDisabled()) continue;

					if (null !== ($persistSupplyJob = $this->checkDiff($emptyPersistAction))) {
						$updatePersistActions[] = $emptyPersistAction;
						unset($this->emptyPersistActions[$key]);
						$this->persistSupplyJobs[] = $persistSupplyJob;
					}
				}
			}
		} while (!empty($this->unsuppliedPersistActions));

		return true;
	}

	/**
	 * @param PersistActionAdapter $persistAction
	 * @return \n2n\persistence\orm\store\action\supply\PersistSupplyJob|null
	 */
	private function checkDiff(PersistActionAdapter $persistAction) {
		$entityModel = $persistAction->getEntityModel();
		$entity = $persistAction->getEntityObj();
		$hasher = new ValueHashColFactory($entityModel, $persistAction->getActionQueue()->getMagicContext());

		$values = array();
		$valueHashCol = $hasher->create($entity, $values);
		$oldValueHashCol = $this->actionQueue->getPersistenceContext()
				->getValueHashColByEntityObj($entity);

		if ($valueHashCol->matches($oldValueHashCol)) {
			return null;
		}

		$supplyJob = new PersistSupplyJob($persistAction, $oldValueHashCol);
		$supplyJob->setValues($values);
		$supplyJob->setValueHashCol($valueHashCol);

		$supplyJob->prepare();
		return $supplyJob;
	}

	private function refreshSupplyJob(PersistSupplyJob $supplyJob) {
		if ($supplyJob->isDisabled()) return;

		$persistAction = $supplyJob->getPersistAction();

		$entityModel = $persistAction->getEntityModel();
		$entity = $persistAction->getEntityObj();

		$hasher = new ValueHashColFactory($entityModel, $this->actionQueue->getMagicContext());
		$values = array();
		$valueHashCol = $hasher->create($entity, $values);

// 		cascade could destroy this
// 		if ($valueHashes === $supplyJob->getValueHashes()) return;

		$supplyJob->setValues($values);
		$supplyJob->setValueHashCol($valueHashCol);

		$supplyJob->prepare();
	}

	/**
	 * @param PersistAction[] $persistActions
	 * @return bool
	 */
	private function announceUpdateLifecylcEvents(array $persistActions): bool {
		$callbacksInvoked = false;

		foreach ($persistActions as $persistAction) {
			if ($persistAction->isDisabled()) continue;

			if ($this->actionQueue->announceLifecycleEvent(new LifecycleEvent(
					($persistAction->isNew() ? LifecycleEvent::PRE_PERSIST : LifecycleEvent::PRE_UPDATE),
					$persistAction->getEntityObj(), $persistAction->getEntityModel(), $persistAction->getId(),
					$persistAction->getMeta()))) {
				$callbacksInvoked = true;
			}

			$that = $this;
			$persistAction->executeAtEnd(function () use ($that, $persistAction) {
				$that->actionQueue->announceLifecycleEvent(new LifecycleEvent(
						($persistAction->isNew() ? LifecycleEvent::POST_PERSIST : LifecycleEvent::POST_UPDATE),
						$persistAction->getEntityObj(), $persistAction->getEntityModel(), $persistAction->getId(),
						$persistAction->getMeta()));
			});
		}

		return $callbacksInvoked;
	}
}
