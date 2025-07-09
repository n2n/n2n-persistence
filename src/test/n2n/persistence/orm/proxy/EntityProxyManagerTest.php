<?php

namespace n2n\persistence\orm\proxy;

use PHPUnit\Framework\TestCase;
use n2n\persistence\orm\proxy\mock\EntityProxyMock;

class EntityProxyManagerTest extends TestCase {


	function testTypes(): void {
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
		$this->assertFalse($called);

		$this->assertNotNull($mockProxyMock->staticReturnTest());
		$this->assertFalse($called);

		$this->assertNotNull($mockProxyMock->someAccessMethod());

		$this->assertTrue($called);
	}
}