<?php

namespace n2n\persistence\orm\store\action;

use PHPUnit\Framework\TestCase;
use n2n\persistence\orm\EntityManager;
use n2n\persistence\orm\store\PersistenceContext;
use n2n\persistence\orm\model\EntityModelFactory;
use n2n\persistence\orm\store\action\mock\SimpleEntityMock;
use n2n\persistence\orm\store\action\mock\SimpleEntityPropertyProviderMock;
use n2n\persistence\orm\model\EntityModel;
use n2n\persistence\orm\model\EntityModelManager;
use n2n\util\magic\impl\MagicMethodInvoker;
use n2n\util\magic\impl\SimpleMagicContext;
use n2n\persistence\orm\store\action\mock\SimpleEntityListener;
use n2n\persistence\orm\store\ValueHashColFactory;
use n2n\util\magic\MagicContext;
use n2n\persistence\orm\store\EntityInfo;
use n2n\persistence\orm\store\PersistenceOperationException;
use PHPUnit\Framework\MockObject\Exception;

class ActionQueueImplTest extends TestCase {

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
		$this->actionQueue = new ActionQueueImpl($this->persistenceContext,
				$this->createMock(EntityManager::class),
				$this->magicContext);

	}

	function testNewPersistOnPrePersist() {
		$entityObj1 = new SimpleEntityMock('holeradio-1');
		$entityObj2 = new SimpleEntityMock('holeradio-2');

		$this->actionQueue->getOrCreatePersistAction($entityObj1);

		$this->assertTrue($this->actionQueue->containsPersistAction($entityObj1));

		$this->listener->onPrePersist = function () use ($entityObj1, $entityObj2) {
			$this->actionQueue->getOrCreatePersistAction($entityObj2);
			$this->listener->onPrePersist = null;

			$this->assertCount(1, $this->listener->events);
			$entityObj1->name = 'holeradio-1-1';
		};

		$this->actionQueue->supply();

		$this->assertCount(2, $this->listener->events);
	}

	function testNewUpdateOnPreUpdate() {
		$entityObj1 = new SimpleEntityMock('holeradio-1');
		$entityObj1->setId(1);
		$entityObj2 = new SimpleEntityMock('holeradio-2');
		$entityObj2->setId(2);
		$entityObj3 = new SimpleEntityMock('holeradio-3');
		$entityObj3->setId(3);

		$hasher = new ValueHashColFactory($this->entityModel, $this->magicContext);

		$this->persistenceContext->manageEntityObj($entityObj1, $this->entityModel);
		$this->persistenceContext->identifyManagedEntityObj($entityObj1, $entityObj1->getId());
		$this->persistenceContext->updateValueHashes($entityObj1, $hasher->create($entityObj1));

		$this->persistenceContext->manageEntityObj($entityObj2, $this->entityModel);
		$this->persistenceContext->identifyManagedEntityObj($entityObj2, $entityObj2->getId());
		$this->persistenceContext->updateValueHashes($entityObj2, $hasher->create($entityObj2));

		$this->persistenceContext->manageEntityObj($entityObj3, $this->entityModel);
		$this->persistenceContext->identifyManagedEntityObj($entityObj3, $entityObj3->getId());
		$this->persistenceContext->updateValueHashes($entityObj3, $hasher->create($entityObj3));

//		$this->actionQueue->getOrCreatePersistAction($entityObj1);
//		$this->actionQueue->getOrCreatePersistAction($entityObj2);


		$entityObj1->name = 'holeradio-1-1';

		$this->listener->onPreUpdate = function () use ($entityObj1, $entityObj2, $entityObj3) {

			$this->assertTrue($this->actionQueue->containsPersistAction($entityObj1));
			$this->listener->onPreUpdate = null;

			$this->assertCount(1, $this->listener->events);
			$entityObj2->name = 'holeradio-1-1';
		};

		$this->actionQueue->supply();

		$this->assertCount(2, $this->listener->events);
	}

	function testForceRemovedToManaged(): void {
		$entityObj1 = new SimpleEntityMock('holeradio-1');
		$entityObj1->setId(1);

		$hasher = new ValueHashColFactory($this->entityModel, $this->magicContext);

		$this->persistenceContext->manageEntityObj($entityObj1, $this->entityModel);
		$this->persistenceContext->identifyManagedEntityObj($entityObj1, $entityObj1->getId());
		$this->persistenceContext->updateValueHashes($entityObj1, $hasher->create($entityObj1));


		$this->assertEquals(EntityInfo::STATE_MANAGED,
				$this->persistenceContext->getEntityInfo($entityObj1)->getState());

		$this->actionQueue->getOrCreateRemoveAction($entityObj1);
		$this->assertEquals(EntityInfo::STATE_REMOVED,
				$this->persistenceContext->getEntityInfo($entityObj1)->getState());

		$this->actionQueue->getOrCreatePersistAction($entityObj1, true);
		$this->assertEquals(EntityInfo::STATE_MANAGED,
				$this->persistenceContext->getEntityInfo($entityObj1)->getState());
	}

	function testRemovedToManaged(): void {
		$entityObj1 = new SimpleEntityMock('holeradio-1');
		$entityObj1->setId(1);

		$hasher = new ValueHashColFactory($this->entityModel, $this->magicContext);

		$this->persistenceContext->manageEntityObj($entityObj1, $this->entityModel);
		$this->persistenceContext->identifyManagedEntityObj($entityObj1, $entityObj1->getId());
		$this->persistenceContext->updateValueHashes($entityObj1, $hasher->create($entityObj1));


		$this->assertEquals(EntityInfo::STATE_MANAGED,
				$this->persistenceContext->getEntityInfo($entityObj1)->getState());

		$this->actionQueue->getOrCreateRemoveAction($entityObj1);
		$this->assertEquals(EntityInfo::STATE_REMOVED,
				$this->persistenceContext->getEntityInfo($entityObj1)->getState());

		$this->expectException(PersistenceOperationException::class);
		$this->actionQueue->getOrCreatePersistAction($entityObj1);

	}
}