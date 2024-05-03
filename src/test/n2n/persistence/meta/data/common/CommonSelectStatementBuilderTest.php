<?php

namespace n2n\persistence\meta\data\common;

use n2n\core\config\PersistenceUnitConfig;
use n2n\persistence\ext\mock\DialectMock;
use n2n\persistence\Pdo;
use PHPUnit\Framework\TestCase;
use n2n\spec\dbo\meta\data\impl\QueryTable;
use n2n\persistence\PdoFactory;
use n2n\spec\dbo\meta\data\QueryFragmentBuilder;
use n2n\spec\dbo\meta\data\QueryLockMode;

class CommonSelectStatementBuilderTest extends TestCase {

	private function createPdo(): Pdo {
		return PdoFactory::createFromPersistenceUnitConfig(
				new PersistenceUnitConfig('default', 'sqlite::memory:', '', '',
						PersistenceUnitConfig::TIL_SERIALIZABLE, DialectMock::class));
	}

	function testWithLock(): void {
		$builderMock = $this->createMock(QueryFragmentBuilder::class);
		$builderMock->expects($this->exactly(3))->method('addTable')
				->with('holeradio');
		$builderMock->expects($this->exactly(3))->method('toSql')
				->willReturn('"holeradio"');

		$factoryMock = $this->createMock(QueryFragmentBuilderFactory::class);
		$factoryMock->expects($this->any())->method('create')->willReturn($builderMock);

		$builder = new CommonSelectStatementBuilder($this->createPdo(), $factoryMock, new CommonSelectLockBuilder());
		$builder->addFrom(new QueryTable('holeradio'));

		$builder->setLockMode(QueryLockMode::FOR_UPDATE);
		$this->assertEquals('SELECT * FROM "holeradio" FOR UPDATE', $builder->toSqlString());

		$builder->setLockMode(QueryLockMode::FOR_UPDATE_NOWAIT);
		$this->assertEquals('SELECT * FROM "holeradio" FOR UPDATE NOWAIT', $builder->toSqlString());

		$builder->setLockMode(QueryLockMode::FOR_UPDATE_SKIP_LOCKED);
		$this->assertEquals('SELECT * FROM "holeradio" FOR UPDATE SKIP LOCKED', $builder->toSqlString());
	}
}