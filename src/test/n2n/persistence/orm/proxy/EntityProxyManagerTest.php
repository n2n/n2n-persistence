<?php

namespace n2n\persistence\orm\proxy;

use PHPUnit\Framework\TestCase;
use n2n\persistence\orm\proxy\mock\EntityProxyMock;
use n2n\persistence\orm\EntityManager;
use n2n\persistence\orm\model\EntityModel;

class EntityProxyManagerTest extends TestCase {

	function testLazyGhost(): void {
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
		$this->assertFalse(EntityProxyManager::getInstance()->isProxyInitialized($mockProxyMock));
		$this->assertFalse($called);

		$mockProxyMock->nonAccessMethod();
		$this->assertFalse(EntityProxyManager::getInstance()->isProxyInitialized($mockProxyMock));
		$this->assertFalse($called);

		$this->assertNotNull($mockProxyMock->someAccessMethod());
		$this->assertTrue(EntityProxyManager::getInstance()->isProxyInitialized($mockProxyMock));
		$this->assertTrue($called);
	}

	function testInitializeProxy(): void {
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
		$this->assertFalse(EntityProxyManager::getInstance()->isProxyInitialized($mockProxyMock));
		$this->assertFalse($called);

		EntityProxyManager::getInstance()->initializeProxy($mockProxyMock);
		$this->assertTrue(EntityProxyManager::getInstance()->isProxyInitialized($mockProxyMock));
		$this->assertTrue($called);
	}

	function testExtractProxyId(): void {
		$entityManagerMock = $this->createMock(EntityManager::class);
		$entityManagerMock->expects($this->never())->method('getLoadingQueue');
		$entityModelMock = $this->createMock(EntityModel::class);

		$listener = new EntityProxyAccessListener($entityManagerMock, $entityModelMock, 1);
		$mockProxyMock = EntityProxyManager::getInstance()
				->createProxy(new \ReflectionClass(EntityProxyMock::class), $listener);

		$this->assertInstanceOf(EntityProxyMock::class, $mockProxyMock);
		$this->assertFalse(EntityProxyManager::getInstance()->isProxyInitialized($mockProxyMock));

		$this->assertSame(1, EntityProxyManager::getInstance()->extractProxyId($mockProxyMock));
		$this->assertFalse(EntityProxyManager::getInstance()->isProxyInitialized($mockProxyMock));
	}

	function testDisposeProxyAccessListenerOf(): void {
		$entityManagerMock = $this->createMock(EntityManager::class);
		$entityManagerMock->expects($this->never())->method('getLoadingQueue');
		$entityModelMock = $this->createMock(EntityModel::class);

		$listener = new EntityProxyAccessListener($entityManagerMock, $entityModelMock, 1);
		$mockProxyMock = EntityProxyManager::getInstance()
				->createProxy(new \ReflectionClass(EntityProxyMock::class), $listener);

		$this->assertInstanceOf(EntityProxyMock::class, $mockProxyMock);
		$this->assertFalse(EntityProxyManager::getInstance()->isProxyInitialized($mockProxyMock));

		EntityProxyManager::getInstance()->disposeProxyAccessListenerOf($mockProxyMock);
		$this->assertNotNull($mockProxyMock->someAccessMethod());
		$this->assertTrue(EntityProxyManager::getInstance()->isProxyInitialized($mockProxyMock));
	}
}