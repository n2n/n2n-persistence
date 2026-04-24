<?php

namespace n2n\persistence;

use n2n\core\config\PersistenceUnitConfig;
use PHPUnit\Framework\TestCase;
use n2n\persistence\ext\mock\DialectMock;
use n2n\core\container\TransactionManager;
use n2n\spec\tx\TransactionIsolationLevel;

class PdoLoggerTest extends TestCase {


	private function createPersistenceUnitConfig(bool $persistent,
			TransactionIsolationLevel $readOnlyTransactionIsolationLevel = TransactionIsolationLevel::TIL_REPEATABLE_READ): PersistenceUnitConfig {
		return new PersistenceUnitConfig('default', 'sqlite::memory:', '', '',
				TransactionIsolationLevel::TIL_SERIALIZABLE, DialectMock::class,
				persistent: $persistent, readOnlyTransactionIsolationLevel: $readOnlyTransactionIsolationLevel);
	}

	private function createNativePdo(bool $persistent): \PDO {
		return (new DialectMock($this->createPersistenceUnitConfig($persistent)))->createPDO();
	}

	private function createPdo(bool $persistent,
			TransactionIsolationLevel $readOnlyTransactionIsolationLevel = TransactionIsolationLevel::TIL_REPEATABLE_READ,
			?TransactionManager $transactionManager = null): Pdo {
		return PdoFactory::createFromPersistenceUnitConfig(
				$this->createPersistenceUnitConfig($persistent, $readOnlyTransactionIsolationLevel),
				$transactionManager);
	}

	function testBeginTransaction() {
		$pdo = $this->createPdo(true);
		$pdo->getLogger()->setCapturing(true);

		$pdo->beginTransaction();
		$this->assertCount(1, $pdo->getLogger()->getEntries());
		$this->assertEquals('begin transaction', $pdo->getLogger()->getEntries()[0]['type']);
		$pdo->close();
	}

	function testLogExec() {
		$pdo = $this->createPdo(true);
		$pdo->getLogger()->setCapturing(true);

		$pdo->exec('SELECT 1');

		$this->assertCount(1, $pdo->getLogger()->getEntries());
		$this->assertEquals('SELECT 1', $pdo->getLogger()->getEntries()[0]['sql']);
		$pdo->close();
	}
}