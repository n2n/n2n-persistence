<?php

namespace n2n\persistence\orm\store;

use n2n\persistence\orm\store\action\ActionQueueImpl;
use n2n\persistence\orm\model\EntityModel;
use n2n\persistence\orm\store\action\mock\SimpleEntityListener;
use n2n\util\magic\MagicContext;
use PHPUnit\Framework\MockObject\Exception;
use n2n\util\magic\impl\SimpleMagicContext;
use n2n\persistence\orm\model\EntityModelFactory;
use n2n\persistence\orm\store\action\mock\SimpleEntityPropertyProviderMock;
use n2n\persistence\orm\model\EntityModelManager;
use n2n\persistence\orm\store\action\mock\SimpleEntityMock;
use PHPUnit\Framework\TestCase;
use n2n\persistence\orm\EntityManager;
use n2n\persistence\orm\proxy\EntityProxyManagerTest;
use n2n\persistence\orm\proxy\EntityProxyManager;

class PersistenceContextTest extends TestCase {

	private PersistenceContext $persistenceContext;
	private ActionQueueImpl $actionQueue;
	private EntityModel $entityModel;
	private SimpleEntityListener $listener;
	private MagicContext $magicContext;

	/**
	 * @throws Exception
	 */
	function setUp(): void {

		$this->listener = new SimpleEntityListener();
		$this->magicContext = new SimpleMagicContext([SimpleEntityListener::class => $this->listener]);
		$factory = new EntityModelFactory([SimpleEntityPropertyProviderMock::class]);
		$entityModelManager = new EntityModelManager([SimpleEntityMock::class], $factory);
		$this->persistenceContext = new PersistenceContext($entityModelManager);
		$this->entityModel = $entityModelManager->getEntityModelByClass(SimpleEntityMock::class);
//		$this->actionQueue = new ActionQueueImpl($this->persistenceContext,
//				$this->createMock(EntityManager::class),
//				$this->magicContext);

	}

	function testGetOrCreateEntityProxy(): void {
		$emMock = $this->createMock(EntityManager::class);
		$emMock->expects($this->never())->method('getLoadingQueue');

		$entityProxy = $this->persistenceContext->getOrCreateEntityProxy($this->entityModel, 1, $emMock);

		$this->assertTrue($this->persistenceContext->containsManagedEntityObj($entityProxy));
		$this->assertFalse(EntityProxyManager::getInstance()->isProxyInitialized($entityProxy));
	}


}