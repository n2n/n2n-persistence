<?php

namespace n2n\persistence\orm;

use n2n\persistence\orm\proxy\EntityProxyAccessListener;
use n2n\persistence\orm\proxy\mock\EntityProxyMock;
use n2n\persistence\orm\proxy\EntityProxyManager;
use n2n\persistence\orm\model\EntityModel;
use PHPUnit\Framework\TestCase;

class OrmUtilsTest extends TestCase {

	function testInitialize(): void {
		$called = false;
		$listenerMock = $this->createMock(EntityProxyAccessListener::class);
		$listenerMock->expects($this->once())->method('onAccess')
				->with($this->callback(function (object $obj) use (&$called) {
					$this->assertInstanceOf(EntityProxyMock::class, $obj);
					$this->assertFalse($called);
					$called = true;
					return true;
				}));

		$mockProxyMock = EntityProxyManager::getInstance()
				->createProxy(new \ReflectionClass(EntityProxyMock::class), $listenerMock);

		$this->assertInstanceOf(EntityProxyMock::class, $mockProxyMock);
		$this->assertFalse(OrmUtils::isInitialized($mockProxyMock));
		$this->assertFalse($called);

		OrmUtils::initialize($mockProxyMock);
		$this->assertTrue(OrmUtils::isInitialized($mockProxyMock));
		$this->assertTrue($called);
	}

	function testIsInitializedNull(): void {
		$this->assertTrue(OrmUtils::isInitialized(null));
	}

	function testExtractId(): void {
		$entityManagerMock = $this->createMock(EntityManager::class);
		$entityManagerMock->expects($this->any())->method('getLoadingQueue');
		$entityModelMock = $this->createMock(EntityModel::class);

		$listener = new EntityProxyAccessListener($entityManagerMock, $entityModelMock, 1);
		$mockProxyMock = EntityProxyManager::getInstance()
				->createProxy(new \ReflectionClass(EntityProxyMock::class), $listener);

		$this->assertInstanceOf(EntityProxyMock::class, $mockProxyMock);
		$this->assertFalse(OrmUtils::isInitialized($mockProxyMock));

		$this->assertSame(1, OrmUtils::extractId($mockProxyMock));
		$this->assertFalse(OrmUtils::isInitialized($mockProxyMock));

		EntityProxyManager::getInstance()->disposeProxyAccessListenerOf($mockProxyMock);

		$this->assertNull(OrmUtils::extractId($mockProxyMock));
		$this->assertTrue(OrmUtils::isInitialized($mockProxyMock));

	}

	function testExtractIdNull(): void {
		$this->assertNull(OrmUtils::extractId(null));
	}

}