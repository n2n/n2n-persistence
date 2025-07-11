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

use n2n\persistence\ext\PdoPool;
use n2n\persistence\orm\criteria\compare\CriteriaComparator;
use n2n\persistence\orm\criteria\BaseCriteria;
use n2n\persistence\orm\criteria\item\CrIt;
use n2n\persistence\orm\store\PersistenceContext;
use n2n\persistence\orm\model\EntityModelManager;
use n2n\persistence\orm\store\action\ActionQueueImpl;
use n2n\persistence\orm\store\operation\PersistOperation;
use n2n\persistence\orm\store\operation\MergeOperationImpl;
use n2n\persistence\orm\store\operation\RemoveOperation;
use n2n\persistence\orm\store\operation\DetachOperation;
use n2n\util\ex\IllegalStateException;
use n2n\persistence\orm\store\operation\RefreshOperation;
use n2n\persistence\orm\nql\NqlParser;
use n2n\persistence\Pdo;
use n2n\persistence\orm\criteria\item\CriteriaProperty;
use n2n\persistence\orm\store\LoadingQueue;
use n2n\persistence\orm\criteria\item\CriteriaFunction;
use n2n\persistence\orm\store\action\ActionQueue;
use n2n\util\magic\MagicContext;
use n2n\persistence\orm\criteria\Criteria;
use ReflectionClass;
use n2n\core\container\TransactionManager;
use n2n\core\container\TransactionalResource;
use n2n\core\container\Transaction;
use n2n\persistence\ext\EmPool;

class LazyEntityManager implements EntityManager, TransactionalResource {
	private $closed = false;
	private ?Pdo $pdo = null;
	private ?TransactionManager $tm = null;
	private $pdoListener = null;
	private $dataSource;
	private $emPool;
	private $entityModelManager;
	private $persistenceContext;
	private $actionQueue;
	private $loadingQueue;
	private $nqlParser;

	public function __construct(private string $dataSourceName, EmPool $emPool, private bool $transactionalScoped,
			private bool $clearOnResourcesRelease = false) {
		$this->emPool = $emPool;
		$this->entityModelManager = $emPool->getEntityModelManager();
		$this->persistenceContext = new PersistenceContext($this->entityModelManager);
		$this->actionQueue = new ActionQueueImpl($this->persistenceContext, $this, $emPool->getMagicContext());
		$this->loadingQueue = new LoadingQueue($this->persistenceContext, $this->actionQueue);
		$this->nqlParser = new NqlParser($this, $this->entityModelManager);
	}

	public function getEntityModelManager(): EntityModelManager {
		$this->ensureEntityManagerOpen();
		return $this->entityModelManager;
	}

	public function getPersistenceContext(): PersistenceContext {
		$this->ensureEntityManagerOpen();
		return $this->persistenceContext;
	}

	public function getActionQueue(): ActionQueue {
		$this->ensureEntityManagerOpen();
		return $this->actionQueue;
	}
	
	public function getLoadingQueue(): LoadingQueue {
		$this->ensureEntityManagerOpen();
		return $this->loadingQueue;
	}
	
	public function getMagicContext(): MagicContext {
		return $this->emPool->getMagicContext();
	}

	/**
	 * @return Pdo
	 */
	public function getPdo(): Pdo {
		if ($this->pdo !== null) {
			return $this->pdo;
		}

		$pdo = $this->emPool->getPdoPool()->getPdo($this->dataSourceName);
		$this->bindPdo($pdo, $this->emPool->getPdoPool()->getTransactionManager());
		return $pdo;
	}
	/**
	 * @param Pdo $pdo
	 * @throws IllegalStateException
	 */
	public function bindPdo(Pdo $pdo, ?TransactionManager $tm): void {
		$this->ensureEntityManagerOpen();
		
		if ($this->pdo !== null) {
			throw new IllegalStateException('Pdo already bound.');
		}
		
		$this->pdo = $pdo;
		$this->tm = $tm;

		$this->tm?->registerResource($this);
	}

	public function beginTransaction(Transaction $transaction): void {
	}

	public function prepareCommit(Transaction $transaction): void {
		if (!$this->isOpen() || $transaction->isReadOnly()) {
			return;
		}

		$this->flush();
		$this->actionQueue->commit();
	}

	public function requestCommit(Transaction $transaction): void {
	}

	public function commit(Transaction $transaction): void {
		if ($this->transactionalScoped) {
			$this->close();
		}
	}

	public function rollBack(Transaction $transaction): void {
		if ($this->transactionalScoped) {
			$this->close();
		}
	}

	function release(): void {
		if ($this->clearOnResourcesRelease) {
			$this->clear();
		}
	}
	
	/**
	 *
	 * @param ReflectionClass $class
	 * @param string $entityAlias
	 * @return BaseCriteria
	 */
	public function createCriteria(): Criteria {
		$this->ensureEntityManagerOpen();
		return new BaseCriteria($this);
	}
	
	/**
	 *
	 * @param string|ReflectionClass $class
	 * @param array $matches
	 * @param array $order
	 * @param int $limit
	 * @param int $num
	 * @return BaseCriteria
	 */
	public function createSimpleCriteria(string|ReflectionClass $class, ?array $matches = null, ?array $order = null,
			?int $limit = null, ?int $num = null): Criteria {
		$this->ensureEntityManagerOpen();
			
		$criteria = $this->createCriteria();
		$criteria->select(self::SIMPLE_ALIAS);
		$criteria->from($class, self::SIMPLE_ALIAS);

		$whereSelector = $criteria->where();
		foreach ((array) $matches as $propertyExpression => $constant) {
			if ($constant instanceof CriteriaProperty || $constant instanceof CriteriaFunction) {
				$constant = $this->preCriteriaItem($constant);
			}

			$whereSelector->match(
					$this->preCriteriaItem(CrIt::pf($propertyExpression)),
					CriteriaComparator::OPERATOR_EQUAL, $constant);
		}

		foreach ((array) $order as $propertyExpression => $direction) {
			$criteria->order($this->preCriteriaItem(CrIt::pf($propertyExpression)), $direction);
		}
	
		$criteria->limit($limit, $num);

		return $criteria;
	}
	
	private function preCriteriaItem($criteriaItem) {
		if ($criteriaItem instanceof CriteriaProperty) {
			return $criteriaItem->prep(self::SIMPLE_ALIAS);
		}
		
		if (!($criteriaItem instanceof CriteriaFunction)) {
			return $criteriaItem;
		}
		
		$newParameters = array();
		foreach ($criteriaItem->getParameters() as $parameter) {
			$newParameters[] = $this->preCriteriaItem($parameter);
		}
		return new CriteriaFunction($criteriaItem->getName(), $newParameters);
	}
	
	public function createNqlCriteria($nql, array $params = array()): Criteria {
		$this->ensureEntityManagerOpen();
		
		return $this->nqlParser->parse($nql, $params);
	}
	
	public function find(string|ReflectionClass $class, $id): mixed {
		$this->ensureEntityManagerOpen();
		
		if ($id === null) return null;
		
		$entityModel = $this->entityModelManager->getEntityModelByClass($class);
		
		if (null !== ($entity = $this->persistenceContext->getManagedEntityObj($entityModel, $id))) {
			return $entity;
		}
		
		return $this->createSimpleCriteria($class, array($entityModel->getIdDef()->getPropertyName() => $id))
				->toQuery()->fetchSingle();
	}
	
	public function getReference(string|ReflectionClass $class, $id): mixed {
		return $this->getPersistenceContext()->getOrCreateEntityProxy(
				$this->entityModelManager->getEntityModelByClass($class), $id, $this);
	}
	
	private function ensureEntityManagerOpen() {
		if (!$this->closed) return;
		
		throw new IllegalStateException('EntityManager closed');
	}
	
	private function ensureTransactionOpen($operationName) {
		$this->ensureEntityManagerOpen();
		
		$pdo = $this->getPdo();
		
		if (!$pdo->inTransaction()) {
			throw new TransactionRequiredException($operationName 
					. ' operation requires transaction.');
		}
		
		$transactionManager = $pdo->getTransactionManager();
		if ($transactionManager === null) return;
		
		if ($transactionManager->isReadyOnly()) {
			throw new IllegalStateException($operationName 
					. ' operation disallowed in ready only transaction.');
		}
	}
	
	public function merge(object $entity): mixed {
		$this->ensureTransactionOpen('Merge');
		
		$mergeOperation = new MergeOperationImpl($this->actionQueue);
		return $mergeOperation->mergeEntity($entity);
	}
	
	public function persist(object $entity): void {
		$this->ensureTransactionOpen('Persist');
		
		$persistOperation = new PersistOperation($this->actionQueue, true);
		$persistOperation->cascade($entity);
	}
	
	public function refresh(object $entity): void {
		$this->ensureEntityManagerOpen();
		
		$refreshOperation = new RefreshOperation($this);
		$refreshOperation->cascade($entity);
	}
	
	public function remove(object $entity): void {
		$this->ensureTransactionOpen('Remove');
		
		$removeOperation = new RemoveOperation($this->actionQueue);
		$removeOperation->cascade($entity);
	}

	public function detach(object $entity): void {
		$this->ensureEntityManagerOpen();
			
		$removeOperation = new DetachOperation($this->actionQueue);
		$removeOperation->cascade($entity);
	}
	
//	public function swap($entity, $newEntity) {
//		$this->ensureTransactionOpen('Swap');
//
//		$tcaq = $this->getPersistenceContext()->createTypeChangeActionQueue();
//		$tcaq->initialize($entity, $newEntity);
//		$tcaq->activate();
//	}
	
	public function flush(): void {
		$this->ensureTransactionOpen('Flush');

		$this->actionQueue->supply();
		$this->actionQueue->flush($this->getPdo());
	}
	
	public function close(): void {
		if ($this->closed) return;

		$this->tm?->unregisterResource($this);

		$this->clear();
		
		$this->closed = true;
		$this->pdo = null;
		$this->tm = null;
		$this->persistenceContext = null;
		$this->actionQueue = null;
		$this->loadingQueue = null;
		$this->entityModelManager = null;
		$this->nqlParser = null;
	}
	/* (non-PHPdoc)
	 * @see \n2n\persistence\orm\EntityManager::contains()
	 */
	public function contains($entityObj): bool {
		$this->ensureEntityManagerOpen();
		
		return $this->getPersistenceContext()->containsManagedEntityObj($entityObj);
	}
	/* (non-PHPdoc)
	 * @see \n2n\persistence\orm\EntityManager::clear()
	 */
	public function clear(): void {
		$this->ensureEntityManagerOpen();
		
		$this->persistenceContext->clear();
		$this->actionQueue->clear();
	}
	/* (non-PHPdoc)
	 * @see \n2n\persistence\orm\EntityManager::isOpen()
	 */
	public function isOpen(): bool {
		return !$this->closed;
	}
	
	public function registerLifecycleListener(LifecycleListener $listener): void {
		$this->ensureEntityManagerOpen();
		
		$this->actionQueue->registerLifecycleListener($listener);
	}
	
	public function getScope(): string {
		return $this->transactionalScoped ? self::SCOPE_TRANSACTION : self::SCOPE_EXTENDED;
	}


}
