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
namespace n2n\persistence\orm;

use n2n\persistence\orm\store\PersistenceOperationException;
use n2n\persistence\orm\store\LoadingQueue;
use n2n\persistence\Pdo;
use n2n\persistence\orm\store\PersistenceContext;
use n2n\persistence\orm\model\EntityModelManager;
use n2n\util\magic\MagicContext;
use n2n\persistence\orm\store\action\ActionQueue;
use n2n\persistence\orm\criteria\Criteria;
use ReflectionClass;
use n2n\persistence\orm\nql\NqlParseException;

interface EntityManager {
	const SIMPLE_ALIAS = 'e';
	
	const SCOPE_EXTENDED = 'extended';
	const SCOPE_TRANSACTION = 'transaction'; 

	public function getPdo(): Pdo;

	public function getPersistenceContext(): PersistenceContext;

	public function getEntityModelManager(): EntityModelManager;

	public function getMagicContext(): MagicContext;

	public function getActionQueue(): ActionQueue;

	public function getLoadingQueue(): LoadingQueue;

	public function createCriteria(): Criteria;

	/**
	 *
	 * @param ReflectionClass $class
	 * @param array|null $matches
	 * @param array|null $order
	 * @param int|null $limit
	 * @param int|null $num
	 * @return Criteria
	 */
	public function createSimpleCriteria(ReflectionClass $class, array $matches = null,
			array $order = null, int $limit = null, int $num = null): Criteria;

	/**
	 * @param string $nql
	 * @param array $params
	 * @return Criteria
	 * @throws NqlParseException
	 */
	public function createNqlCriteria(string $nql, array $params = array()): Criteria;

	/**
	 * @param ReflectionClass $class
	 * @param mixed $id
	 * @return mixed
	 */
	public function find(string|ReflectionClass $class, mixed $id): mixed;

	/**
	 * Get an instance, whose state may be lazily fetched.
	 * @param ReflectionClass $class
	 * @param mixed $id
	 * @return mixed
	 */
	public function getReference(ReflectionClass $class, mixed $id): mixed;

	/**
	 * Merge the state of the given entity into the current persistence context. 
	 * @param object $entity
	 * @return mixed the managed instance that the state was merged to 
	 */
	public function merge(object $entity): mixed;

	/**
	 * Make an instance managed and persistent.
	 * @param object $entity
	 * @return void
	 */
	public function persist(object $entity): void;

	/**
	 * Refresh the state of the instance from the database, overwriting changes made to the entity, if any. 
	 * @param mixed $entity
	 * @throws PersistenceOperationException
	 * @throws EntityNotFoundException if the entity no longer exists in the database
	 */
	public function refresh(object $entity): void;

	/**
	 * Remove the entity instance. 
	 * @param object $entity
	 */
	public function remove(object $entity): void;

	/**
	 * Remove the given entity from the persistence context, causing a managed entity to become detached. Unflushed 
	 * changes made to the entity if any (including removal of the entity), will not be synchronized to the database. 
	 * Entities which previously referenced the detached entity will continue to reference it. 
	 * @param object $entity
	 */
	public function detach(object $entity): void;

	/**
	 * Synchronize the persistence context to the underlying database.
	 */
	public function flush(): void;

	/**
	 * Check if the instance is a managed entity instance belonging to the current persistence context. 
	 * @param object $entity
	 * @return boolean indicating if entity is in persistence context 
	 */
	public function contains(object $entity): bool;

	/**
	 * Clear the persistence context, causing all managed entities to become detached. Changes made to entities that
	 * have not been flushed to the database will not be persisted.
	 */
	public function clear(): void;

	/**
	 * 
	 */
	public function close(): void;

	/**
	 * Determine whether the entity manager is open. 
	 * @return bool
	 */
	public function isOpen(): bool;

	public function registerLifecycleListener(LifecycleListener $listener): void;
	
	public function getScope(): string;
}
