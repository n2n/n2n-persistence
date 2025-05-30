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

use n2n\persistence\orm\LifecycleEvent;
use n2n\persistence\orm\LifecycleListener;
use n2n\persistence\orm\store\PersistenceContext;
use n2n\util\magic\MagicContext;
use n2n\persistence\Pdo;

interface ActionQueue {

	function getMagicContext(): MagicContext;
	/**
	 * @return PersistenceContext
	 */
	public function getPersistenceContext(): PersistenceContext;
	/**
	 * @param Action $action
	 */
	public function add(Action $action, bool $prepend = false);
	/**
	 * @param object $entity
	 * @param bool $manageIfTransient
	 * @return PersistAction
	 */
	public function getPersistAction($entity);

	/**
	 * @param object $entity
	 * @param bool $ignoreRemovedState
	 * @return PersistAction
	 */
	public function getOrCreatePersistAction(object $entity, bool $ignoreRemovedState = false): PersistAction;
	/**
	 * @param object $object
	 */
	public function containsPersistAction($entity);
	/**
	 * @param $entity
	 * @return RemoveAction returns null if object already removed or has state new
	 */
	public function getRemoveAction($entity);
	/**
	 * @param $entity
	 * @return RemoveAction returns null if object already removed or has state new
	 */
	public function getOrCreateRemoveAction($entity);
	/**
	 * @param object $object
	 */
	public function containsRemoveAction($entity);
	/**
	 * @param object $entity
	 * @param string $type
	 */
	public function announceLifecycleEvent(LifecycleEvent $event);

	/**
	 * @param LifecycleListener $listener
	 */
	public function registerLifecycleListener(LifecycleListener $listener);
	
	/**
	 * @param LifecycleListener $listener
	 */
	public function unregisterLifecycleListener(LifecycleListener $listener);
	
	/**
	 * 
	 */
	public function flush(Pdo $pdo);
	
	/**
	 * 
	 */
	public function commit();
	
	/**
	 * 
	 */
	public function clear();
	
//	/**
//	 * @param \Closure $closure
//	 */
//	public function executeAtStart(\Closure $closure);
//
//	/**
//	 * @param \Closure $closure
//	 */
//	public function executeAtEnd(\Closure $closure);
	
	/**
	 * @param \Closure $closure
	 */
	public function executeAtPrepareCycleEnd(\Closure $closure);
}
