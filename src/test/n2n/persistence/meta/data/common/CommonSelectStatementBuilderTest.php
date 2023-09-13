<?php

namespace n2n\persistence\meta\data\common;

use n2n\core\config\PersistenceUnitConfig;
use n2n\persistence\ext\mock\DialectMock;
use n2n\persistence\Pdo;
use PHPUnit\Framework\TestCase;
use n2n\persistence\meta\data\QueryTable;
use n2n\persistence\meta\data\LockMode;
use n2n\persistence\meta\data\QueryFragmentBuilder;

class CommonSelectStatementBuilderTest extends TestCase {



	private function createPdo(): Pdo {
		return new Pdo(new PersistenceUnitConfig('default', 'sqlite::memory:', '', '',
				PersistenceUnitConfig::TIL_SERIALIZABLE, DialectMock::class));
	}

	function testWithLock(): void {
		$builderMock = $this->createMock(QueryFragmentBuilder::class);
		$builderMock->expects($this->once())->method('addTable')
				->with('holeradio');
		$builderMock->expects($this->once())->method('toSql')
				->willReturn('"holeradio"');

		$factoryMock = $this->createMock(QueryFragmentBuilderFactory::class);
		$factoryMock->expects($this->any())->method('create')->willReturn($builderMock);

		$builder = new CommonSelectStatementBuilder($this->createPdo(), $factoryMock, new CommonSelectLockBuilder());
		$builder->addFrom(new QueryTable('holeradio'));
		$builder->setLockMode(LockMode::PESSIMISTIC_WRITE);

		$this->assertEquals('SELECT * FROM "holeradio" FOR UPDATE', $builder->toSqlString());
	}
}