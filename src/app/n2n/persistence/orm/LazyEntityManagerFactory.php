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


use n2n\util\ex\IllegalStateException;
use n2n\persistence\ext\PdoPool;
use n2n\persistence\ext\EmPool;

class LazyEntityManagerFactory implements EntityManagerFactory {
	private $persistenceUnitName;
	private $emPool;
	
	private $shared;
	private $transactionalEm;
	
	/**
	 * @param string|null $persistenceUnitName
	 * @param PdoPool $emPool
	 */
	public function __construct(?string $persistenceUnitName, EmPool $emPool) {
		$this->persistenceUnitName = $persistenceUnitName;
		$this->emPool = $emPool;
	}
	
	/**
	 * {@inheritDoc}
	 * @see \n2n\persistence\orm\EntityManagerFactory::getTransactional()
	 */
	public function getTransactional() {
		if ($this->transactionalEm !== null && $this->transactionalEm->isOpen()) {
			return $this->transactionalEm;
		}
		
		$pdo = $this->emPool->getPdoPool()->getPdo($this->persistenceUnitName);
		if (!$pdo->inTransaction()) {
			throw new IllegalStateException('No tranaction open.');
		}
		
		$this->transactionalEm = new LazyEntityManager($this->persistenceUnitName, $this->emPool, true);
		$this->transactionalEm->bindPdo($pdo, $this->emPool->getTransactionManager());
	
		return $this->transactionalEm;
	}	
	
	/**
	 * {@inheritDoc}
	 * @see \n2n\persistence\orm\EntityManagerFactory::getExtended()
	 */
	public function getExtended() {
		if (!isset($this->shared)) {
			$this->shared = $this->create(true);
		}
		return $this->shared;
	}
	
	/**
	 * {@inheritDoc}
	 * @see \n2n\persistence\orm\EntityManagerFactory::create()
	 */
	public function create(bool $clearOnResourcesRelease) {
		return new LazyEntityManager($this->persistenceUnitName, $this->emPool, false, false);
	}

	function clear(): void {
		if ($this->shared !== null) {
			$this->shared->close();
			$this->shared = null;
		}

		if ($this->transactionalEm !== null) {
			$this->transactionalEm->close();
			$this->transactionalEm = null;
		}
	}
}

// class TransactionalEmContainer implements PdoListener {
// 	private $em;
	
// 	public function __construct(EntityManager $em) {
// 		$dbh = $em->getPdo();
// 		if (!$dbh->inTransaction()) {
// 			throw new ContainerConflictException(SysTextUtils::get('n2n_error_persitence_orm_no_transaction_active'));
// 		}
// 		$dbh->registerListener($this);
		
// 		$this->em = $em;
// 	}
	
// 	public function isAvailable() {
// 		return isset($this->em);
// 	}
	
// 	public function getEntityManager() {
// 		return $this->em;
// 	}
	
// 	public function onTransactionEvent(TransactionEvent $e) {
// 		if ($e->getType() == TransactionEvent::TYPE_COMMITTED 
// 				|| $e->getType() == TransactionEvent::TYPE_ROLLED_BACK) {
// 			if ($this->isAvailable()) {
// 				$this->em->close();
// 				$this->em = null;
// 			}
// 		}
// 	}
// }
