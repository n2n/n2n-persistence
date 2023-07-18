<?php

namespace n2n\persistence\ext;

use PHPUnit\Framework\TestCase;
use n2n\core\cache\impl\NullAppCache;
use n2n\core\config\AppConfig;
use n2n\core\config\GeneralConfig;
use n2n\core\config\WebConfig;
use n2n\core\config\MailConfig;
use n2n\core\config\IoConfig;
use n2n\core\config\FilesConfig;
use n2n\core\config\ErrorConfig;
use n2n\core\config\DbConfig;
use n2n\l10n\L10nConfig;
use n2n\l10n\PseudoL10nConfig;
use n2n\core\cache\impl\EphemeralAppCache;
use n2n\core\config\PersistenceUnitConfig;
use n2n\core\config\OrmConfig;
use n2n\core\config\N2nLocaleConfig;
use n2n\core\container\impl\AppN2nContext;
use n2n\core\container\TransactionManager;
use n2n\core\module\ModuleManager;
use n2n\core\VarStore;
use n2n\persistence\orm\EntityManager;
use n2n\persistence\ext\mock\PseudoEntityMock;
use n2n\persistence\orm\EntityManagerFactory;
use n2n\persistence\ext\mock\DialectMock;
use n2n\context\LookupManager;
use n2n\persistence\Pdo;
use n2n\persistence\PdoTransactionalResource;
use n2n\persistence\orm\LazyEntityManager;
use n2n\core\N2nApplication;
use n2n\core\cache\AppCache;
use n2n\util\io\fs\FsPath;

class PersistenceN2nExtensionTest extends TestCase {

	private AppConfig $appConfig;
	private N2nApplication $n2nApplication;
	private PersistenceN2nExtension $persistenceN2nExtension;

	function setUp(): void {

		$this->appConfig = new AppConfig(
				dbConfig: new DbConfig([
					new PersistenceUnitConfig('default', 'sqlite::memory:', '', '',
							'SERIALIZABLE', DialectMock::class,
							false, null)
				]),
				ormConfig: new OrmConfig([PseudoEntityMock::class], []));

		$this->n2nApplication = new N2nApplication($this->createMock(VarStore::class),
				$this->createMock(ModuleManager::class),
				new EphemeralAppCache(),
				$this->appConfig, null);

		$this->persistenceN2nExtension = new PersistenceN2nExtension($this->n2nApplication);
	}

	function testIf() {

		$tm = new TransactionManager();

		$n2nContext1 = new AppN2nContext($tm, $this->n2nApplication);

		$this->persistenceN2nExtension->applyToN2nContext($n2nContext1);

		$addonContexts = $n2nContext1->getAddonContexts();
		$this->assertCount(1, $addonContexts);

		/**
		 * @var PersistenceAddOnContext $persistenceAddonContext
		 */
		$persistenceAddonContext = $addonContexts[0];
		$this->assertInstanceOf(PersistenceAddOnContext::class, $persistenceAddonContext);

		$emf = $n2nContext1->lookup(EntityManagerFactory::class);

		$tx = $tm->createTransaction();
		$em = $emf->getTransactional();

		$resources = array_values($tm->getResources());
		$this->assertCount(2, $resources);
		$this->assertInstanceOf(PdoTransactionalResource::class, $resources[0]);
		$this->assertInstanceOf(LazyEntityManager::class, $resources[1]);

		$tx->commit();


		$resources = array_values($tm->getResources());
		$this->assertCount(1, $resources);
		$this->assertEquals(1, $this->persistenceN2nExtension->getActivePdoPoolsNum());


		$n2nContext2 = new AppN2nContext($tm, $this->n2nApplication);
		$this->persistenceN2nExtension->applyToN2nContext($n2nContext2);


		$resources = array_values($tm->getResources());
		$this->assertCount(1, $resources);
		$this->assertEquals(1, $this->persistenceN2nExtension->getActivePdoPoolsNum());


		$emf = $n2nContext2->lookup(EntityManagerFactory::class);
		$pdo = $n2nContext2->lookup(PdoPool::class)->getPdo();
		$this->assertTrue($pdo->isConnected());

		$tx = $tm->createTransaction();
		$em = $emf->getTransactional();

		$resources = array_values($tm->getResources());
		$this->assertCount(2, $resources);
		$this->assertInstanceOf(PdoTransactionalResource::class, $resources[0]);
		$this->assertInstanceOf(LazyEntityManager::class, $resources[1]);

		$tx->commit();



		$n2nContext1->finalize();

		$resources = array_values($tm->getResources());
		$this->assertCount(1, $resources);
		$this->assertEquals(1, $this->persistenceN2nExtension->getActivePdoPoolsNum());
		$this->assertTrue($pdo->isConnected());

		$n2nContext2->finalize();

		$resources = array_values($tm->getResources());
		$this->assertCount(0, $resources);
		$this->assertEquals(0, $this->persistenceN2nExtension->getActivePdoPoolsNum());
		$this->assertFalse($pdo->isConnected());
		$this->assertTrue($pdo->isClosed());
		$this->assertFalse($em->isOpen());

	}

}