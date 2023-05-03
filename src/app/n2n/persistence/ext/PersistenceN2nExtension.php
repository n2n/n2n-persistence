<?php

namespace n2n\persistence\ext;

use n2n\core\ext\N2nExtension;
use n2n\core\container\impl\AppN2nContext;
use n2n\core\config\AppConfig;
use n2n\persistence\orm\model\EntityModelManager;
use n2n\persistence\orm\model\EntityModelFactory;
use n2n\core\container\TransactionManager;
use n2n\core\config\PersistenceUnitConfig;
use n2n\core\ext\N2nMonitor;
use n2n\util\ex\IllegalStateException;
use n2n\core\cache\AppCache;

class PersistenceN2nExtension implements N2nExtension {
	/**
	 * @var PersistenceUnitConfig[] $persistenceUnitConfigs;
	 */
	private array $persistenceUnitConfigs = [];
	private EntityModelManager $entityModelManager;
	private \SplObjectStorage $pdoPoolsMaps;

	private ?float $slowQueryTime;

	public function __construct(AppConfig $appConfig, AppCache $appCache) {
		$ormConfig = $appConfig->orm();

		$this->slowQueryTime = $appConfig->error()->getMonitorSlowQueryTime();

		foreach ($appConfig->db()->getPersistenceUnitConfigs() as $persistenceUnitConfig) {
			$this->persistenceUnitConfigs[$persistenceUnitConfig->getName()] = $persistenceUnitConfig;
		}

		$this->entityModelManager = new EntityModelManager($ormConfig->getEntityClassNames(),
				new EntityModelFactory($ormConfig->getEntityPropertyProviderClassNames(),
						$ormConfig->getNamingStrategyClassName()));

		$this->pdoPoolsMaps = new \SplObjectStorage();
	}

	function getActivePdoPoolsNum(): int {
		return $this->pdoPoolsMaps->count();
	}

	private function obtainPdoPool(TransactionManager $transactionManager, ?N2nMonitor $n2NMonitor): PdoPoolUsage {
		if (!$this->pdoPoolsMaps->offsetExists($transactionManager)) {
			$this->pdoPoolsMaps->offsetSet($transactionManager, new PdoPoolUsage(new PdoPool(
					$this->persistenceUnitConfigs, $transactionManager, $this->slowQueryTime, $n2NMonitor)));
		}

		return $this->pdoPoolsMaps->offsetGet($transactionManager);
	}

	private function releaseUsage(PdoPoolUsage $pdoPoolUsage, PersistenceAddOnContext $persistenceAddOnContext) {
		$pdoPoolUsage->unregisterUsage($persistenceAddOnContext);

		if ($pdoPoolUsage->countUsages() > 0) {
			return;
		}

		$pdoPoolUsage->pdoPool->clear();
		$this->pdoPoolsMaps->offsetUnset($pdoPoolUsage->pdoPool->getTransactionManager());
	}

	function setUp(AppN2nContext $appN2nContext): void {
		$pdoPoolUsage = $this->obtainPdoPool($appN2nContext->getTransactionManager(), $appN2nContext->getMonitor());

		$persistenceAddOnContext = new PersistenceAddOnContext(
				new EmPool($pdoPoolUsage->pdoPool, $this->entityModelManager, $appN2nContext),
				function (PersistenceAddOnContext $persistenceAddOnContext) use ($pdoPoolUsage) {
					$this->releaseUsage($pdoPoolUsage, $persistenceAddOnContext);
				});

		$pdoPoolUsage->registerUsage($persistenceAddOnContext);

		$appN2nContext->addAddonContext($persistenceAddOnContext);
	}
}

class PdoPoolUsage {
	private \SplObjectStorage $usages;

	function __construct(public readonly PdoPool $pdoPool) {
		$this->usages = new \SplObjectStorage();
	}

	function registerUsage(PersistenceAddOnContext $persistenceAddOnContext): void {
		IllegalStateException::assertTrue(!$this->usages->offsetExists($persistenceAddOnContext));

		$this->usages->attach($persistenceAddOnContext);
	}

	function unregisterUsage(PersistenceAddOnContext $persistenceAddOnContext): void {
		IllegalStateException::assertTrue($this->usages->offsetExists($persistenceAddOnContext));

		$this->usages->detach($persistenceAddOnContext);
	}

	function countUsages(): int {
		return $this->usages->count();
	}
}