<?php

namespace n2n\persistence\orm\store\datadefinition;

use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\MockObject\Exception;
use n2n\persistence\orm\model\EntityModelFactory;
use n2n\persistence\orm\model\EntityModelManager;
use n2n\persistence\orm\store\action\mock\SimpleEntityMock;
use n2n\persistence\orm\store\action\mock\SimpleEntityPropertyProviderMock;
use n2n\persistence\orm\store\datadefinition\mock\MetaManagerMock;
use n2n\persistence\orm\store\datadefinition\mock\TableMock;
use n2n\persistence\meta\structure\common\CommonView;
use n2n\persistence\meta\structure\common\CommonStringColumn;
use n2n\persistence\Pdo;
use n2n\spec\dbo\meta\structure\Database;
use n2n\spec\dbo\meta\structure\Table;
use n2n\spec\dbo\meta\structure\MetaEntity;
use n2n\spec\dbo\meta\structure\IndexType;
use n2n\util\ex\IllegalStateException;
use n2n\spec\dbo\err\DboException;

class DdChangerTest extends TestCase {

	private DdChanger $ddChanger;
	private string $tableName;

	function setUp(): void {
		$factory = new EntityModelFactory([SimpleEntityPropertyProviderMock::class]);
		$entityModelManager = new EntityModelManager([SimpleEntityMock::class], $factory);
		$this->tableName = $entityModelManager->getEntityModelByClass(SimpleEntityMock::class)->getTableName();
		$this->ddChanger = new DdChanger($entityModelManager);
	}

	/**
	 * @param MetaEntity[] $persistedMetaEntities
	 * @throws Exception
	 */
	private function createMetaManager(array $persistedMetaEntities = []): MetaManagerMock {
		return new MetaManagerMock($this->createMock(Pdo::class), $persistedMetaEntities);
	}

	/**
	 * @param string[] $columnNames
	 * @param string[] $indexColumnNames index name => column name
	 */
	private function createTable(string $name, array $columnNames = [], array $indexColumnNames = []): TableMock {
		$table = new TableMock($name);
		foreach ($columnNames as $columnName) {
			$table->createColumnFactory()->createStringColumn($columnName, 255);
		}
		foreach ($indexColumnNames as $indexName => $columnName) {
			$table->createIndex(IndexType::INDEX, [$columnName], $indexName);
		}
		return $table;
	}

	/**
	 * @throws DboException
	 */
	private function getTable(Database $database, string $name): Table {
		$table = $database->getMetaEntityByName($name);
		$this->assertInstanceOf(Table::class, $table);
		return $table;
	}

	private function getColumnNames(Table $table): array {
		return array_values(array_map(fn ($c) => $c->getName(), $table->getColumns()));
	}

	private function getIndexNames(Table $table): array {
		return array_values(array_map(fn ($i) => $i->getName(), $table->getIndexes()));
	}

	private function assertEntityColumns(Table $table, array $additionalColumnNames = []): void {
		$this->assertEqualsCanonicalizing(['id', 'name', ...$additionalColumnNames], $this->getColumnNames($table));
		foreach (['id', 'name'] as $columnName) {
			$this->assertInstanceOf(CommonStringColumn::class, $table->getColumnByName($columnName));
		}
	}

	/**
	 * @throws Exception
	 */
	function testUpdateTableCreatesMissingTable(): void {
		$metaManager = $this->createMetaManager([$this->createTable('other_table', ['col'])]);
		$database = $metaManager->createDatabase();

		$this->ddChanger->updateDatabase($database, DbUpdateMode::UPDATE_TABLE);
		$metaManager->flush();

		$this->assertSame(['CREATE ' . $this->tableName], $metaManager->getExecutedRequestDescriptions());
		$this->assertEntityColumns($metaManager->getExecutedRequests()[0]->getMetaEntity());
	}

	/**
	 * @throws Exception
	 */
	function testUpdateTableAltersExistingTable(): void {
		$metaManager = $this->createMetaManager([
			$this->createTable($this->tableName, ['name', 'other_col'], ['other_idx' => 'other_col']),
			$this->createTable('other_table', ['col'])
		]);
		$database = $metaManager->createDatabase();

		$this->ddChanger->updateDatabase($database, DbUpdateMode::UPDATE_TABLE);
		$metaManager->flush();

		$this->assertSame(['ALTER ' . $this->tableName], $metaManager->getExecutedRequestDescriptions());

		// unknown columns and indexes are left untouched
		$table = $this->getTable($database, $this->tableName);
		$this->assertEntityColumns($table, ['other_col']);
		$this->assertSame(['other_idx'], $this->getIndexNames($table));
		$this->assertSame(['col'], $this->getColumnNames($this->getTable($database, 'other_table')));
	}

	/**
	 * @throws Exception
	 * @throws DboException
	 */
	function testRecreateTablesClearsExistingTable(): void {
		$metaManager = $this->createMetaManager([
			$this->createTable($this->tableName, ['name', 'other_col'], ['other_idx' => 'other_col']),
			$this->createTable('other_table', ['col'])
		]);
		$database = $metaManager->createDatabase();

		$this->ddChanger->updateDatabase($database, DbUpdateMode::RECREATE_TABLES);
		$metaManager->flush();

		$this->assertSame(['ALTER ' . $this->tableName], $metaManager->getExecutedRequestDescriptions());

		$table = $this->getTable($database, $this->tableName);
		$this->assertEntityColumns($table);
		$this->assertEmpty($table->getIndexes());
		// tables which don't belong to an entity are kept
		$this->assertSame(['col'], $this->getColumnNames($this->getTable($database, 'other_table')));
	}

	/**
	 * @throws Exception
	 */
	function testRecreateDatabaseDropsAllMetaEntities(): void {
		$metaManager = $this->createMetaManager([
			$this->createTable($this->tableName, ['name', 'other_col']),
			$this->createTable('other_table', ['col']),
			new CommonView('some_view', 'SELECT 1')
		]);
		$database = $metaManager->createDatabase();

		$this->ddChanger->updateDatabase($database, DbUpdateMode::RECREATE_DATABASE);
		$metaManager->flush();

		$this->assertSame([
			'DROP ' . $this->tableName,
			'DROP other_table',
			'DROP some_view',
			'CREATE ' . $this->tableName
		], $metaManager->getExecutedRequestDescriptions());

		$this->assertCount(1, $database->getMetaEntities());
		$this->assertEntityColumns($this->getTable($database, $this->tableName));
	}

	/**
	 * @throws Exception
	 */
	function testExistingMetaEntityIsNoTable(): void {
		$metaManager = $this->createMetaManager([new CommonView($this->tableName, 'SELECT 1')]);
		$database = $metaManager->createDatabase();

		try {
			$this->ddChanger->updateDatabase($database, DbUpdateMode::UPDATE_TABLE);
			$this->fail('IllegalStateException expected.');
		} catch (IllegalStateException $e) {
		}

		$metaManager->flush();
		$this->assertEmpty($metaManager->getExecutedRequests());
	}
}
