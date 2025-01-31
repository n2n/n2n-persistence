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

use n2n\persistence\orm\EntityManager;
use n2n\persistence\orm\LifecycleEvent;
use n2n\reflection\magic\MagicMethodInvoker;
use n2n\persistence\orm\LifecycleListener;
use n2n\util\magic\MagicContext;
use n2n\reflection\ReflectionUtils;
use n2n\persistence\orm\LifecycleUtils;
use n2n\util\ex\IllegalStateException;
use n2n\reflection\magic\MagicUtils;
use n2n\persistence\orm\model\EntityModel;
use n2n\persistence\orm\store\PersistenceContext;
use n2n\persistence\Pdo;

class ActionQueueImpl implements ActionQueue {
	protected $em;
	protected $magicContext;
	protected $actionQueueListeners = array();
	/**
	 * @var Action[]
	 */
	protected array $actionJobs = array();
	protected $atStartClosures = array();
	protected $atEndClosures = array();
	protected $atPrepareCycleEndClosures = array();
	private $persistActionPool;
	private $removeActionPool;
	private $flushing = false;
	private $bufferedEvents = array();
	private \WeakMap $entityListenersMap;
	/**
	 * @var LifecycleListener[]
	 */
	private $lifecylceListeners = array();

	const MAGIC_ENTITY_OBJ_PARAM = 'entityObj';

	public function __construct(private PersistenceContext $persistenceContext,
			EntityManager $em, ?MagicContext $magicContext) {
		$this->em = $em;
		$this->magicContext = $magicContext;
		$this->persistActionPool = new PersistActionPool($this);
		$this->removeActionPool = new RemoveActionPool($this, $this->persistActionPool);
		$this->entityListenersMap = new \WeakMap();
	}

	public function getEntityManager() {
		return $this->em;
	}

	function getMagicContext(): MagicContext {
		return $this->magicContext;
	}

	function getPersistenceContext(): PersistenceContext {
		return $this->persistenceContext;
	}

	public function removeAction($entity) {
		$this->persistActionPool->removeAction($entity);
		$this->removeActionPool->removeAction($entity);
	}

	public function containsPersistAction($entity) {
		return $this->persistActionPool->containsAction($entity);
	}
	/* (non-PHPdoc)
	 * @see \n2n\persistence\orm\store\action\ActionQueue::getOrCreatePersistAction()
	 */
	public function getOrCreatePersistAction($entity) {
		$this->removeActionPool->removeAction($entity);
		return $this->persistActionPool->getOrCreateAction($entity);
	}

	public function getPersistAction($entity) {
		return $this->persistActionPool->getAction($entity);
	}

	public function containsRemoveAction($entity) {
		return $this->removeActionPool->containsAction($entity);
	}
	/* (non-PHPdoc)
	 * @see \n2n\persistence\orm\store\action\ActionQueue::getOrCreateRemoveAction()
	 */
	public function getOrCreateRemoveAction($entity) {
		$this->persistActionPool->removeAction($entity);
		return $this->removeActionPool->getOrCreateAction($entity);
	}

	public function getRemoveAction($entity) {
		return $this->removeActionPool->getAction($entity);
	}

	public function add(Action $action, bool $prepend = false) {
		if (!$prepend) {
			$this->actionJobs[spl_object_hash($action)] = $action;
			return;
		}

		$this->actionJobs = [spl_object_hash($action) => $action] + $this->actionJobs;
	}

	public function remove(Action $action) {
		unset($this->actionJobs[spl_object_hash($action)]);
	}

// 	public function announceLifecycleEvent(LifecycleEvent $event) {
// 		foreach ($this->actionQueueListeners as $actionQueueListener) {
// 			$actionQueueListener->onLifecycleEvent($event);
// 		}
// 	}

// 	protected function supplyMetaIdOnInit(ActionMeta $meta, Entity $object) {
// 		$that = $this;
// 		$this->initClosures[] = function() use ($that, $meta, $object) {
// 			$meta->setId($that->getPersistenceContext()->getIdOfEntity($object));
// 		};
// 	}

//	public function executeAtStart(\Closure $closure) {
//		$this->atStartClosures[] = $closure;
//	}
//	/* (non-PHPdoc)
//	 * @see \n2n\persistence\orm\store\Action::executeAtEnd()
//	 */
//	public function executeAtEnd(\Closure $closure) {
//		$this->atEndClosures[] = $closure;
//	}

	public function executeAtPrepareCycleEnd(\Closure $closure) {
		$this->atPrepareCycleEndClosures[] = $closure;
	}

	protected function triggerAtStartClosures() {
		while (null !== ($closure = array_shift($this->atStartClosures))) {
			$closure($this);
		}
	}

	protected function triggerAtEndClosures() {
		while (null !== ($closure = array_shift($this->atEndClosures))) {
			$closure($this);
		}
	}

	protected function triggerAtPrepareCycleEndClosures() {
		if (empty($this->atPrepareCycleEndClosures)) return false;

		while (null !== ($closure = array_shift($this->atPrepareCycleEndClosures))) {
			$closure($this);
		}

		return true;
	}

	function supply(): void {
		IllegalStateException::assertTrue(
				!$this->persistActionPool->isFrozend() && !$this->removeActionPool->isFrozend(),
				'ActionQueue is already supplied and in a too advanced state to be further modified.');

		$this->triggerAtStartClosures();

		$this->flushing = true;

		do {
			do {
				$this->persistActionPool->prepareSupplyJobs();
			} while ($this->removeActionPool->prepareSupplyJobs() || $this->triggerAtPrepareCycleEndClosures());
		} while ($this->triggerPreFinilizeAttempt()
		&& ($this->persistActionPool->prepareSupplyJobs() || $this->removeActionPool->prepareSupplyJobs()));

		$this->persistActionPool->freeze();
		$this->removeActionPool->freeze();

		$this->persistActionPool->supply();
		$this->removeActionPool->supply();
	}

	public function flush(Pdo $pdo): void {
		IllegalStateException::assertTrue(
				$this->persistActionPool->isFrozend() && $this->removeActionPool->isFrozend(),
				'Is not supplied nor frozen.');

		uasort($this->actionJobs, fn (Action $aj1, Action $aj2) => $aj1->getPriority() - $aj2->getPriority());

		while (null != ($job = array_shift($this->actionJobs))) {
			$job->execute($pdo);
		}

		$this->persistActionPool->clear();
		$this->removeActionPool->clear();

		$this->triggerAtEndClosures();

		$this->flushing = false;

		while (null !== ($event = array_shift($this->bufferedEvents))) {
			$this->triggerLifecycleEvent($event);
		}
	}

	public function commit() {
		$this->persistenceContext->detachNotManagedEntityObjs();
	}

	public function clear(): void {
		$this->removeActionPool->clear();
		$this->persistActionPool->clear();
		$this->actionJobs = [];
		$this->entityListenersMap = new \WeakMap();
	}

	public function announceLifecycleEvent(LifecycleEvent $event): bool {
		switch ($event->getType()) {
			case LifecycleEvent::PRE_PERSIST:
			case LifecycleEvent::PRE_REMOVE:
			case LifecycleEvent::PRE_UPDATE:
				return $this->triggerLifecycleEvent($event);

			case LifecycleEvent::POST_LOAD:
				$this->triggerLifecycleEvent($event);
				if ($this->flushing) {
					$this->persistActionPool->getOrCreateAction($event->getEntityObj(), false);
				}
				break;

			default:
				IllegalStateException::assertTrue($this->flushing);
				$this->bufferedEvents[] = $event;
		}

		return false;
	}

	private function triggerLifecycleEvent(LifecycleEvent $event) {
		$triggered = $this->triggerLifecycleCallbacks($event);

		foreach ($this->lifecylceListeners as $listener) {
			$listener->onLifecycleEvent($event, $this->em);
			$triggered = true;
		}

		return $triggered;
	}

	private function triggerLifecycleCallbacks(LifecycleEvent $event) {
		$eventType = $event->getType();
		$entityModel = $event->getEntityModel();

		$methods = $entityModel->getLifecycleMethodsByEventType($eventType);
		$entityListenerClasses = $entityModel->getEntityListenerClasses();

		if (empty($methods) && empty($entityListenerClasses)) {
			return false;
		}

		$entityObj = $event->getEntityObj();
		$methodInvoked = false;

		$methodInvoker = new MagicMethodInvoker($this->magicContext);
		$methodInvoker->setClassParamObject(EntityModel::class, $entityModel);

		$paramClass = $entityModel->getClass();
		do {
			$methodInvoker->setClassParamObject($paramClass->getName(), $entityObj);
			foreach ($paramClass->getInterfaceNames() as $interfaceName) {
				$methodInvoker->setClassParamObject($interfaceName, $entityObj);;
			}
		} while (false !== ($paramClass = $paramClass->getParentClass()));

		$methodInvoker->setParamValue(self::MAGIC_ENTITY_OBJ_PARAM, $entityObj);
		$methodInvoker->setClassParamObject(EntityManager::class, $this->em);
		$methodInvoker->setClassParamObject(LifecycleEvent::class, $event);

		foreach ($methods as $method) {
			$method->setAccessible(true);
			$methodInvoker->invoke($entityObj, $method);
			$methodInvoked = true;
		}

		foreach ($entityListenerClasses as $entityListenerClass) {
			$callbackMethod = LifecycleUtils::findEventMethod($entityListenerClass, $eventType);
			if ($callbackMethod !== null) {
				$callbackMethod->setAccessible(true);
				$methodInvoker->invoke($this->lookupEntityListener($entityObj, $entityListenerClass), $callbackMethod);
				$methodInvoked = true;
			}
		}

		return $methodInvoked;
	}

	private function lookupEntityListener(object $entityObj, \ReflectionClass $entityListenerClass) {
		$entityListenerClassName = $entityListenerClass->getName();

		if (!isset($this->entityListenersMap[$entityObj][$entityListenerClassName])) {
			$entityListeners = $this->entityListenersMap[$entityObj] ?? [];
			$entityListeners[$entityListenerClassName] = $this->magicContext->lookup($entityListenerClassName);
			$this->entityListenersMap[$entityObj] = $entityListeners;
		}

		return $this->entityListenersMap[$entityObj][$entityListenerClassName];
	}

	private function triggerPreFinilizeAttempt() {
		$triggered = false;

		foreach ($this->lifecylceListeners as $listener) {
			$listener->onPreFinalized($this->em);
			$triggered = true;
		}

		return $triggered;
	}

	public function registerLifecycleListener(LifecycleListener $listener) {
		$this->lifecylceListeners[spl_object_hash($listener)] = $listener;
	}

	public function unregisterLifecycleListener(LifecycleListener $listener) {
		unset($this->lifecylceListeners[spl_object_hash($listener)]);
	}
}
