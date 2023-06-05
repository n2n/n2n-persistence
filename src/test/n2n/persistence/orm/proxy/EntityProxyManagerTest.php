<?php

namespace n2n\persistence\orm\proxy;

use PHPUnit\Framework\TestCase;
use n2n\persistence\orm\proxy\mock\EntityProxyMock;

class EntityProxyManagerTest extends TestCase {


	function testTypes(): void {
		$listenerMock = $this->createMock(EntityProxyAccessListener::class);

		$this->assertNotNull(EntityProxyManager::getInstance()
				->createProxy(new \ReflectionClass(EntityProxyMock::class), $listenerMock));
	}
}