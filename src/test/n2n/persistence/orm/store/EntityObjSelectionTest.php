<?php

namespace n2n\persistence\orm\store;

use PHPUnit\Framework\TestCase;
use n2n\persistence\orm\store\action\ActionQueueImpl;
use n2n\persistence\orm\model\EntityModel;
use n2n\persistence\orm\store\action\mock\SimpleEntityListener;
use n2n\util\magic\MagicContext;
use n2n\util\magic\impl\SimpleMagicContext;
use n2n\persistence\orm\model\EntityModelFactory;
use n2n\persistence\orm\store\action\mock\SimpleEntityPropertyProviderMock;
use n2n\persistence\orm\model\EntityModelManager;
use n2n\persistence\orm\store\action\mock\SimpleEntityMock;
use n2n\persistence\orm\EntityManager;
use n2n\persistence\orm\query\select\EntityObjSelection;
use n2n\persistence\orm\query\from\meta\SimpleTreePointMeta;
use n2n\persistence\orm\query\from\BaseEntityTreePoint;
use n2n\persistence\orm\query\QueryState;
use n2n\persistence\orm\proxy\EntityProxyAccessListener;
use n2n\persistence\orm\proxy\EntityProxyManager;
use n2n\persistence\orm\CorruptedDataException;
use n2n\persistence\orm\query\from\meta\SimpleDiscriminatorSelection;
use n2n\persistence\orm\query\select\EagerValueSelection;
use n2n\persistence\orm\query\select\LazyValueBuilder;

class EntityObjSelectionTest extends TestCase {

	private PersistenceContext $persistenceContext;
	private ActionQueueImpl $actionQueue;
	private LoadingQueue $loadingQueue;
	private EntityModel $entityModel;
	private SimpleEntityListener $listener;
	private MagicContext $magicContext;

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
		$this->loadingQueue = new LoadingQueue($this->persistenceContext, $this->actionQueue);
	}

	/**
	 * @throws CorruptedDataException
	 */
	function testUninitializedProxy(): void {
		$accessListener = $this->createMock(EntityProxyAccessListener::class);
		$accessListener->expects($this->never())->method('onAccess');
		$accessListener->expects($this->never())->method('getId');

		$entityObj = EntityProxyManager::getInstance()->createProxy(new \ReflectionClass(SimpleEntityMock::class),
				$accessListener);
		$this->persistenceContext->manageEntityObj($entityObj, $this->entityModel);
		$this->persistenceContext->identifyManagedEntityObj($entityObj, 3);

		$queryState = $this->createMock(QueryState::class);
		$treePoint = new BaseEntityTreePoint($queryState, new SimpleTreePointMeta($queryState, $this->entityModel));
		$selection = new EntityObjSelection($this->entityModel, $treePoint, $this->persistenceContext,
				$this->loadingQueue);
		$subSelection = $selection->getSelectionGroup()->getSelectionByKey(null);
		$this->assertInstanceOf(SimpleDiscriminatorSelection::class, $subSelection);
		$subSelection->setValue(3);
		$subSelection = $selection->getSelectionGroup()->getSelectionByKey(
				$this->entityModel->getEntityPropertyByName('id')->toPropertyString());
		$this->assertInstanceOf(EagerValueSelection::class, $subSelection);
		$subSelection->setValue(3);

		$this->assertInstanceOf(LazyValueBuilder::class, $selection->createValueBuilder());
	}

}