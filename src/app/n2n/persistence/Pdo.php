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
use n2n\core\container\TransactionManager;
use n2n\core\container\Transaction;
use n2n\core\container\err\CommitFailedException;
use n2n\util\ex\IllegalStateException;
use n2n\persistence\meta\Dialect;
use n2n\core\ext\N2nMonitor;
use n2n\util\type\TypeUtils;
use n2n\spec\dbo\Dbo;
use n2n\spec\dbo\meta\structure\MetaManager;
use n2n\spec\dbo\meta\data\UpdateStatementBuilder;
use n2n\spec\dbo\meta\data\InsertStatementBuilder;
use n2n\spec\dbo\meta\data\SelectStatementBuilder;
use n2n\spec\dbo\meta\data\DeleteStatementBuilder;
use n2n\spec\dbo\DboStatement;

class Pdo implements Dbo {
	private ?\PDO $pdo = null;
	private PdoLogger $logger;
	private ?MetaData $metaData = null;
	private ?Dialect $dialect = null;
	private array $listeners = array();

	private PdoTransactionalResource|PdoReleasableResource $pdoTransactionalResource;

	public function __construct(private string $dataSourceName, Dialect $dialect,
			private ?TransactionManager $transactionManager = null, ?float $slowQueryTime = null,
			?N2nMonitor $n2nMonitor = null, private PdoBindMode $bindMode = PdoBindMode::FULL) {
		$this->logger = new PdoLogger($this->getDataSourceName(), $slowQueryTime, $n2nMonitor);

		$this->dialect = $dialect;
		$this->metaData = new MetaData($this, $dialect);

		if (!$bindMode->isTransactionIncluded()) {
			$this->pdoTransactionalResource = new PdoReleasableResource(
					function() use ($bindMode) {
						$this->release();
					});
			return;
		}

		$this->pdoTransactionalResource = new PdoTransactionalResource(
				function(Transaction $transaction) use ($bindMode) {
					$this->performBeginTransaction($transaction, $transaction->isReadOnly());
				},
				function(Transaction $transaction) use ($bindMode) {
					if (!$bindMode->isTransactionIncluded()) {
						return true;
					}

					return $this->prepareCommit($transaction);
				},
				function(Transaction $transaction) use ($bindMode) {
					try {
						$this->performCommit($transaction);
					} catch (PdoCommitException $e) {
						throw new CommitFailedException('Pdo commit failed. Reason: ' . $e->getMessage(), 0, $e);
					}
				},
				function(Transaction $transaction) use ($bindMode){
					$this->performRollBack($transaction);
				},
				function() use ($bindMode) {
					$this->release();
				});
	}

	function __destruct() {
		$this->disconnect();
	}


//	function fork(): Pdo {
//		$pdo = new Pdo($this->persistenceUnitConfig, $this->transactionManager, $this->slowQueryTime, $this->n2nMonitor);
//		$pdo->pdo = $this->pdo;
//		return $pdo;
//	}

	function release(): void {
		$this->ensureNotClosed();

		if ($this->pdo !== null && $this->pdo->inTransaction()) {
			throw new IllegalStateException('Can not release connection while in transaction.');
		}

		$this->disconnect();
	}

	private function disconnect(): void {
		if ($this->pdo !== null && $this->pdo->inTransaction()) {
			$this->pdo->rollBack();
		}

		$this->pdo = null;
		$this->transactionManager?->unregisterResource($this->pdoTransactionalResource);
	}

	function reconnect(): void {
		$this->release();

		$this->pdo = $this->dialect->createPDO($this->logger);

		$this->pdo->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);

		if ($this->pdo->inTransaction()) {
			throw new IllegalStateException('PDO has already an open transaction after connecting.');
//			$this->pdo->rollBack();
		}

		$this->transactionManager?->registerResource($this->pdoTransactionalResource);
	}

	function isConnected(): bool {
		return $this->pdo !== null;
	}

	function close(): void {
		$this->disconnect();

		$this->transactionManager = null;
		$this->metaData = null;
		$this->dialect = null;
	}

	function isClosed(): bool {
		return $this->metaData === null;
	}

	private function ensureNotClosed(): void {
		if (!$this->isClosed()) {
			return;
		}

		throw new IllegalStateException('Pdo closed (datasource name: ' . $this->getDataSourceName()
				. ').');
	}

	private function pdo(): ?\PDO {
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
	public function getDataSourceName(): string {
		return $this->dataSourceName;
	}

	public function getLogger(): PdoLogger {
		return $this->logger;
	}

	function getBindMode(): PdoBindMode {
		return $this->bindMode;
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

	public function prepare($statement, $driverOptions = array()): PdoStatement {
		try {
			$mtime = microtime(true);

			$stmt = new PdoStatement($this->pdo()->prepare($statement, $driverOptions));

			$this->logger->addPreparation($statement, (microtime(true) - $mtime));
			$stmt->setLogger($this->logger);

			return $stmt;
		} catch (\PDOException $e) {
			throw new PdoStatementException($e, $statement);
		}
	}

	public function query(string $statement, ?int $fetchMode = null, ...$fetchModeArgs): ?DboStatement {
		try {
			$mtime = microtime(true);
			$query = $this->pdo()->query($statement, $fetchMode, $fetchModeArgs);
			$this->logger->addQuery($statement, (microtime(true) - $mtime));
		} catch (\PDOException $e) {
			throw new PdoStatementException($e, $statement);
		}

		if ($query === false) {
			return null;
		}

		return new PdoStatement($query);
	}

	public function exec(string $statement): false|int {
		return PDOOperations::exec($this->logger, $this->pdo(), $statement);
	}

	public function beginTransaction(bool $readOnly = false): void {
		if ($this->transactionManager === null || !$this->bindMode->isTransactionIncluded()) {
			$this->performBeginTransaction(null, $readOnly);
			return;
		}

		if (!$this->transactionManager->hasOpenTransaction()) {
			$this->transactionManager->createTransaction();
		}
	}

	public function commit(): void {
		if ($this->transactionManager === null || !$this->bindMode->isTransactionIncluded()) {
			$this->prepareCommit();
			$this->performCommit();
			return;
		}

		if ($this->transactionManager->hasOpenTransaction()) {
			$this->transactionManager->getRootTransaction()->commit();
		}
	}

	public function rollBack(): void {
		if ($this->transactionManager === null || !$this->bindMode->isTransactionIncluded()) {
			$this->performRollBack();
			return;
		}

		if ($this->transactionManager->hasOpenTransaction()) {
			$this->transactionManager->getRootTransaction()->rollBack();
		}
	}

	private function performBeginTransaction(?Transaction $transaction = null, bool $readOnly = false): void {
		$this->triggerTransactionEvent(TransactionEvent::TYPE_ON_BEGIN, $transaction);

		IllegalStateException::assertTrue(!$this->pdo()->inTransaction(),
				'Illegal call, pdo already in transaction.');

		$this->dialect->beginTransaction($this->pdo(), $readOnly, $this->logger);

		if (!$this->pdo()->inTransaction()) {
			throw new IllegalStateException('Dialect call '
					. TypeUtils::prettyMethName(get_class($this->dialect), 'beginTransaction')
					. ' did not start a transaction.');
		}

		$this->triggerTransactionEvent(TransactionEvent::TYPE_BEGAN, $transaction);
	}

	private function prepareCommit(?Transaction $transaction = null): bool {
		$this->triggerTransactionEvent(TransactionEvent::TYPE_ON_COMMIT, $transaction);
		return true;
	}

	private function performCommit(?Transaction $transaction = null): void {
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

	private function performRollBack(?Transaction $transaction = null): void {
		$this->triggerTransactionEvent(TransactionEvent::TYPE_ON_ROLL_BACK, $transaction);
		$mtime = microtime(true);
		$this->pdo()->rollBack();
		$this->logger->addTransactionRollBack(microtime(true) - $mtime);
		$this->triggerTransactionEvent(TransactionEvent::TYPE_ROLLED_BACK, $transaction);
	}

	function quote(string|bool|int|float|null $value): string|null {
		return $this->pdo()->quote($value);
	}

	/**
	 *
	 * @param string $field
	 */
	public function quoteField($field): string {
		return $this->dialect->quoteField($field);
	}

	function lastInsertId(?string $name = null) {
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
	public function getMetaData(): MetaData {
		return $this->metaData;
	}

	private function triggerTransactionEvent($type, ?Transaction $transaction = null) {
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

	function createMetaManager(): MetaManager {
		return $this->getMetaData()->getDialect()->createMetaManager($this);
	}

	function createSelectStatementBuilder(): SelectStatementBuilder {
		return $this->getMetaData()->getDialect()->createSelectStatementBuilder($this);
	}

	function createUpdateStatementBuilder(): UpdateStatementBuilder {
		return $this->getMetaData()->getDialect()->createUpdateStatementBuilder($this);
	}

	function createInsertStatementBuilder(): InsertStatementBuilder {
		return $this->getMetaData()->getDialect()->createInsertStatementBuilder($this);
	}

	function createDeleteStatementBuilder(): DeleteStatementBuilder {
		return $this->getMetaData()->getDialect()->createDeleteStatementBuilder($this);
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

	public function __construct($type, ?Transaction $transaction = null) {
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
