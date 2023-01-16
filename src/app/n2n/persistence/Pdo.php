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
namespace n2n\persistence;

use n2n\persistence\meta\MetaData;
use n2n\reflection\ReflectionUtils;
use n2n\core\container\TransactionManager;
use n2n\core\container\Transaction;
use n2n\core\container\CommitFailedException;
use n2n\util\ex\IllegalStateException;
use n2n\persistence\meta\Dialect;
use n2n\core\config\PersistenceUnitConfig;
use n2n\core\ext\N2nMonitor;

class Pdo {
	private ?\PDO $pdo = null;
	private Dialect $dialect;
	private PdoLogger $logger;
	private MetaData $metaData;
	private array $listeners = array();

	public function __construct(private PersistenceUnitConfig $persistenceUnitConfig,
			private ?TransactionManager $transactionManager = null, private ?float $slowQueryTime = null,
			private ?N2nMonitor $n2nMonitor = null) {
		$this->logger = new PdoLogger($this->getDataSourceName(), $slowQueryTime, $this->n2nMonitor);

		$dialectClass = ReflectionUtils::createReflectionClass($persistenceUnitConfig->getDialectClassName());
		if (!$dialectClass->implementsInterface('n2n\\persistence\\meta\\Dialect')) {
			throw new \InvalidArgumentException('Dialect class must implement n2n\\persistence\\meta\\Dialect: '
					. $dialectClass->getName());
		}
		$this->dialect = $dialectClass->newInstance();
		$this->metaData = new MetaData($this, $this->dialect);

		$transactionManager?->registerResource(new PdoTransactionalResource(
				function(Transaction $transaction) {
					$this->performBeginTransaction($transaction);
				},
				function(Transaction $transaction) {
					return $this->prepareCommit($transaction);
				},
				function(Transaction $transaction) {
					try {
						$this->performCommit($transaction);
					} catch (PdoCommitException $e) {
						throw new CommitFailedException('Pdo commit failed. Reason: ' . $e->getMessage(), 0, $e);
					}
				},
				function(Transaction $transaction) {
					$this->performRollBack($transaction);
				},
				function() {
					$this->release();
				}));
	}

	function release(): void {
		if ($this->pdo !== null && $this->pdo->inTransaction()) {
			throw new IllegalStateException('Can not release connection while in transaction.');
		}

		$this->pdo = null;
	}

	function reconnect(): void {
		$this->release();

		try {
			$this->pdo = new \PDO($this->persistenceUnitConfig->getDsnUri(), $this->persistenceUnitConfig->getUser(),
					$this->persistenceUnitConfig->getPassword(), array(\PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION,
							\PDO::ATTR_STATEMENT_CLASS => array('n2n\persistence\PdoStatement', array())));
		} catch (\PDOException $e) {
			throw new PdoException($e);
		}

		$this->dialect->initializeConnection($this, $this->persistenceUnitConfig);
	}

	function isConnected(): bool {
		return $this->pdo !== null;
	}

	private function pdo() {
		if ($this->pdo === null) {
			$this->reconnect();
		}

		return $this->pdo;
	}

	/**
	 * @return TransactionManager
	 */
	public function getTransactionManager() {
		return $this->transactionManager;
	}
	/**
	 * @return string
	 */
	public function getDataSourceName() {
		return $this->persistenceUnitConfig->getName();
	}
	/**
	 * @return PdoLogger
	 */
	public function getLogger() {
		return $this->logger;
	}
	/**
	 *
	 * @param mixed $pdo
	 * @return boolean
	 */
	public function equals(mixed $pdo) {
		if (!($pdo instanceof Pdo)) return false;

		return $this->getDataSourceName() === $pdo->getDataSourcename();
	}

	function inTransaction(): bool {
		return $this->pdo()->inTransaction();
	}

	/**
	 *
	 * @return PdoStatement
	 */
	public function prepare($statement, $driverOptions = array()) {
		try {
			$mtime = microtime(true);

			$stmt = $this->pdo()->prepare($statement, $driverOptions);

			$this->logger->addPreparation($statement, (microtime(true) - $mtime));
			$stmt->setLogger($this->logger);

			return $stmt;
		} catch (PDOException $e) {
			throw new PdoStatementException($e, $statement);
		}
	}

	public function query(string $statement, ?int $fetchMode = null, ...$fetchModeArgs) {
		try {
			$mtime = microtime(true);
			$query = $this->pdo()->query($statement, $fetchMode, $fetchModeArgs);
			$this->logger->addQuery($statement, (microtime(true) - $mtime));
			return $query;
		} catch (\PDOException $e) {
			throw new PdoStatementException($e, $statement);
		}
	}
	/**
	 *
	 * @return int
	 */
	public function exec(string $statement) {
		try {
			$mtime = microtime(true);
			$stmt = $this->pdo()->exec($statement);
			$this->logger->addExecution($statement, (microtime(true) - $mtime));
			return $stmt;
		} catch (\PDOException $e) {
			throw new PdoStatementException($e, $statement);
		}
	}
	/**
	 * (non-PHPdoc)
	 * @see PDO::beginTransaction()
	 */
	public function beginTransaction() {
		if ($this->transactionManager === null) {
			$this->performBeginTransaction();
			return;
		}

		if (!$this->transactionManager->hasOpenTransaction()) {
			$this->transactionManager->createTransaction();
		}
	}
	/**
	 * (non-PHPdoc)
	 * @see PDO::commit()
	 */
	public function commit() {
		if ($this->transactionManager === null) {
			$this->prepareCommit();
			$this->performCommit();
		}

		if ($this->transactionManager->hasOpenTransaction()) {
			$this->transactionManager->getRootTransaction()->commit();
		}
	}
	/**
	 * (non-PHPdoc)
	 * @see PDO::rollBack()
	 */
	public function rollBack() {
		if ($this->transactionManager === null) {
			$this->performRollBack();
			return;
		}

		if ($this->transactionManager->hasOpenTransaction()) {
			$this->transactionManager->getRootTransaction()->rollBack();
		}
	}


	private function performBeginTransaction(Transaction $transaction = null) {
		$this->triggerTransactionEvent(TransactionEvent::TYPE_ON_BEGIN, $transaction);
		$mtime = microtime(true);
		$this->pdo()->beginTransaction();
		$this->logger->addTransactionBegin(microtime(true) - $mtime);
		$this->triggerTransactionEvent(TransactionEvent::TYPE_BEGAN, $transaction);
	}

	private function prepareCommit(Transaction $transaction = null) {
		$this->triggerTransactionEvent(TransactionEvent::TYPE_ON_COMMIT, $transaction);
		return true;
	}

	private function performCommit(Transaction $transaction = null) {
		$mtime = microtime(true);

		$preErr = error_get_last();
		$result = @$this->pdo()->commit();
		$postErr = error_get_last();

		// Problem: Warining: Error while sending QUERY packet. PID=223316 --> $this->pdo()->commit() will return true but
		// triggers warning.
		// http://php.net/manual/de/pdo.transactions.php
		if (!$result || $preErr !== $postErr) {
			throw new PdoCommitException($postErr['message'] ?? 'Commit failed due to unknown reason.');
		}

		$this->logger->addTransactionCommit(microtime(true) - $mtime);
		$this->triggerTransactionEvent(TransactionEvent::TYPE_COMMITTED, $transaction);
	}

	private function performRollBack(Transaction $transaction = null) {
		$this->triggerTransactionEvent(TransactionEvent::TYPE_ON_ROLL_BACK, $transaction);
		$mtime = microtime(true);
		$this->pdo()->rollBack();
		$this->logger->addTransactionRollBack(microtime(true) - $mtime);
		$this->triggerTransactionEvent(TransactionEvent::TYPE_ROLLED_BACK, $transaction);
	}

	function quote(string $string, int $type = \PDO::PARAM_STR): string {
		return $this->pdo()->quote($string, $type);
	}

	/**
	 *
	 * @param string $field
	 */
	public function quoteField($field) {
		return $this->dialect->quoteField($field);
	}

	function lastInsertId(string $name = null) {
		return $this->pdo()->lastInsertId($name);
	}
// 	/**
// 	 *
// 	 */
// 	public function dumpLog() {
// 		if(isset($this->log)) $this->log->dump();
// 	}

	/**
	 * @return \n2n\persistence\meta\MetaData
	 */
	public function getMetaData() {
		return $this->metaData;
	}

	private function triggerTransactionEvent($type, Transaction $transaction = null) {
		$e = new TransactionEvent($type, $transaction);
		foreach ($this->listeners as $listener) {
			$listener->onTransactionEvent($e);
		}
	}
	/**
	 * @param PdoListener $listener
	 */
	public function registerListener(PdoListener $listener) {
		$this->listeners[spl_object_hash($listener)] = $listener;
	}
	/**
	 * @param PdoListener $listener
	 */
	public function unregisterListener(PdoListener $listener) {
		unset($this->listeners[spl_object_hash($listener)]);
	}
}

class TransactionEvent {
	const TYPE_ON_BEGIN = 'begin';
	const TYPE_BEGAN = 'began';
	const TYPE_ON_COMMIT = 'onCommit';
	const TYPE_COMMITTED = 'committed';
	const TYPE_ON_ROLL_BACK = 'onRollback';
	const TYPE_ROLLED_BACK = 'rollBacked';

	private $type;
	private $transaction;

	public function __construct($type, Transaction $transaction = null) {
		$this->type = $type;
		$this->transaction = $transaction;
	}

	public function getType() {
		return $this->type;
	}
	/**
	 * @return Transaction
	 */
	public function getTransaction() {
		return $this->transaction;
	}
}
