<?php

namespace n2n\persistence\orm\store\operation;

use PHPUnit\Framework\TestCase;
use n2n\persistence\orm\proxy\EntityProxyAccessListener;
use n2n\persistence\orm\proxy\EntityProxyManager;
use n2n\persistence\orm\store\action\mock\SimpleEntityMock;
use n2n\persistence\orm\store\action\mock\SimpleEntityListener;
use n2n\util\magic\impl\SimpleMagicContext;
use n2n\persistence\orm\model\EntityModelFactory;
use n2n\persistence\orm\store\action\mock\SimpleEntityPropertyProviderMock;
use n2n\persistence\orm\model\EntityModelManager;
use n2n\persistence\orm\store\PersistenceContext;
use n2n\persistence\orm\store\action\ActionQueueImpl;
use n2n\persistence\orm\EntityManager;
use n2n\persistence\orm\model\EntityModel;
use n2n\util\magic\MagicContext;
use n2n\persistence\orm\store\ValueHashColFactory;
use n2n\persistence\orm\OrmUtils;

class MergeOperationTest extends TestCase {

	private PersistenceContext $persistenceContext;
	private ActionQueueImpl $actionQueue;
//	private EntityModel $entityModel;
	private SimpleEntityListener $listener;
	private MagicContext $magicContext;

	function setUp(): void {
		$this->listener = new SimpleEntityListener();
		$this->magicContext = new SimpleMagicContext([SimpleEntityListener::class => $this->listener]);
		$factory = new EntityModelFactory([SimpleEntityPropertyProviderMock::class]);
		$entityModelManager = new EntityModelManager([SimpleEntityMock::class], $factory);
		$this->persistenceContext = new PersistenceContext($entityModelManager);
//		$this->entityModel = $entityModelManager->getEntityModelByClass(SimpleEntityMock::class);
		$this->actionQueue = new ActionQueueImpl($this->persistenceContext,
				$this->createMock(EntityManager::class),
				$this->magicContext);
	}

	function testUninitializedProxy(): void {
		$accessListener = $this->createMock(EntityProxyAccessListener::class);
		$accessListener->expects($this->never())->method('onAccess');
		$accessListener->expects($this->never())->method('getId');

		$entityObj1 = EntityProxyManager::getInstance()->createProxy(new \ReflectionClass(SimpleEntityMock::class),
				$accessListener);

		$persistOperation = new MergeOperationImpl($this->actionQueue);
		$this->assertSame($entityObj1, $persistOperation->mergeEntity($entityObj1));

		$this->assertFalse(OrmUtils::isInitialized($entityObj1));
	}
}