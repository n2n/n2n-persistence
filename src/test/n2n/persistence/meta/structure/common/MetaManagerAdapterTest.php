<?php

namespace n2n\persistence\meta\structure\common;

use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\MockObject\Exception;
use n2n\persistence\orm\store\datadefinition\mock\MetaManagerMock;
use n2n\persistence\orm\store\datadefinition\mock\TableMock;
use n2n\persistence\Pdo;
use n2n\spec\dbo\meta\structure\Table;

class MetaManagerAdapterTest extends TestCase {

	private MetaManagerMock $metaManager;
	private DatabaseAdapter $database;

	/**
	 * @throws Exception
	 */
	function setUp(): void {
		$this->metaManager = new MetaManagerMock($this->createMock(Pdo::class),
				[new TableMock('table_a'), new TableMock('table_b')]);
		$this->database = $this->metaManager->createDatabase();
	}

	private function addStringColumn(string $tableName, string $columnName): void {
		$table = $this->database->getMetaEntityByName($tableName);
		$this->assertInstanceOf(Table::class, $table);
		$table->createColumnFactory()->createStringColumn($columnName, 255);
	}

	function testAltersOfDifferentTablesAreAllExecuted(): void {
		$this->addStringColumn('table_a', 'col');
		$this->addStringColumn('table_b', 'col');
		$this->metaManager->flush();

		$this->assertSame(['ALTER table_a', 'ALTER table_b'],
				$this->metaManager->getExecutedRequestDescriptions());
	}

	function testAltersOfSameTableAreMerged(): void {
		$this->addStringColumn('table_a', 'col1');
		$this->addStringColumn('table_a', 'col2');
		$this->metaManager->flush();

		$this->assertSame(['ALTER table_a'], $this->metaManager->getExecutedRequestDescriptions());
	}

	function testAlterAfterCreateIsIgnored(): void {
		$this->database->createMetaEntityFactory()->createTable('table_c');
		$this->addStringColumn('table_c', 'col');
		$this->addStringColumn('table_a', 'col');
		$this->metaManager->flush();

		$this->assertSame(['CREATE table_c', 'ALTER table_a'],
				$this->metaManager->getExecutedRequestDescriptions());
	}

	function testCreateNeutralizedByDrop(): void {
		$this->database->createMetaEntityFactory()->createTable('table_c');
		$this->database->removeMetaEntityByName('table_c');
		$this->metaManager->flush();

		$this->assertEmpty($this->metaManager->getExecutedRequests());
	}
}
