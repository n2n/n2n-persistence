<?php

namespace n2n\persistence;

use n2n\core\config\PersistenceUnitConfig;
use PHPUnit\Framework\TestCase;
use n2n\persistence\ext\mock\DialectMock;

class PdoTest extends TestCase {

	function setUp(): void {

		gc_collect_cycles();
		$pdo = $this->createPdo(true);
		$pdo->exec('DROP TABLE IF EXISTS holeradio');
		$pdo->close();
		$pdo = null;
	}

	private function createPersistenceUnitConfig(bool $persistent): PersistenceUnitConfig {
		return new PersistenceUnitConfig('default', 'sqlite::memory:', '', '',
				PersistenceUnitConfig::TIL_SERIALIZABLE, DialectMock::class,
				persistent: $persistent);
	}

	private function createNativePdo(bool $persistent): \PDO {
		return (new DialectMock())->createPDO($this->createPersistenceUnitConfig($persistent));
	}

	private function createPdo(bool $persistent): Pdo {
		return new Pdo($this->createPersistenceUnitConfig($persistent));
	}

	function testPersistent() {
		$pdo = $this->createPdo(true);

		$this->createTable($pdo);

		$this->assertTrue($this->checkIfTableExists($pdo));

		$pdo->close();
		$pdo = null;

		$pdo = $this->createPdo(true);

		$this->assertTrue($this->checkIfTableExists($pdo));

		$pdo->close();
	}

	function testNotPersistent() {
		$pdo = $this->createPdo(false);

		$this->createTable($pdo);

		$this->assertTrue($this->checkIfTableExists($pdo));

		$pdo->close();
		$pdo = null;

		$pdo = $this->createPdo(false);

		$this->assertFalse($this->checkIfTableExists($pdo));

		$pdo->close();
	}


	function testPersistentDirtyTransaction() {
		$pdo = $this->createPdo(true);


		$this->createTable($pdo);
		$this->assertTrue($this->checkIfTableExists($pdo));

		$this->assertFalse($pdo->inTransaction());
		$pdo->beginTransaction();
		$this->assertTrue($pdo->inTransaction());

		$pdo = null;
		gc_collect_cycles();

		$pdo = $this->createNativePdo(true);
		$this->assertFalse($pdo->inTransaction());

		$this->assertTrue($this->checkIfTableExists($pdo));
	}

	function testPersistentDirtyTransaction2() {
		$pdo = $this->createNativePdo(true);

		$this->createTable($pdo);
		$this->assertTrue($this->checkIfTableExists($pdo));

		$this->assertFalse($pdo->inTransaction());
		$pdo->beginTransaction();
		$this->assertTrue($pdo->inTransaction());


		$pdo = null;
		gc_collect_cycles();


		$pdo = $this->createNativePdo(true);
		$this->assertTrue($this->checkIfTableExists($pdo));


		$pdo = $this->createPdo(true);
		$this->assertFalse($pdo->inTransaction());
	}

	private function createTable(Pdo|\PDO $pdo): void {
		$pdo->exec('CREATE TABLE holeradio ( id INT PRIMARY KEY )');
	}

	private function checkIfTableExists(Pdo|\PDO $pdo): bool {
		$stmt = $pdo->prepare('PRAGMA table_list');
		$stmt->execute();

		foreach ($stmt->fetchAll(\PDO::FETCH_ASSOC) as $tableResult) {
			if ($tableResult['name'] === 'holeradio') {
				return true;
			}

			return false;
		}

		return false;
	}
}