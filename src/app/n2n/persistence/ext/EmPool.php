<?php
namespace n2n\persistence\ext;

use n2n\persistence\orm\model\EntityModelManager;
use n2n\util\magic\MagicContext;
use n2n\persistence\orm\LazyEntityManagerFactory;
use n2n\persistence\Pdo;
use n2n\persistence\orm\EntityManager;
use n2n\persistence\orm\proxy\EntityProxyManager;
use n2n\persistence\orm\EntityManagerFactory;
use n2n\persistence\orm\model\EntityModelFactory;

class EmPool {
	private EntityProxyManager $entityProxyManager;
	/**
	 * @var EntityManagerFactory[] $entityManagerFactories
	 */
	private array $entityManagerFactories = array();

	function __construct(private PdoPool $pdoPool, private EntityModelManager $entityModelManager,
			private MagicContext $magicContext) {

		$this->entityProxyManager = EntityProxyManager::getInstance();
	}

	function getPdoPool(): PdoPool {
		return $this->pdoPool;
	}

	function getMagicContext(): MagicContext {
		return $this->magicContext;
	}

	public function getEntityManagerFactory(?string $persistenceUnitName = null): EntityManagerFactory {
		if ($persistenceUnitName === null) {
			$persistenceUnitName = PdoPool::DEFAULT_DS_NAME;
		}

		if (!isset($this->entityManagerFactories[$persistenceUnitName])) {
			$this->entityManagerFactories[$persistenceUnitName]
					= new LazyEntityManagerFactory($persistenceUnitName, $this);
		}

		return $this->entityManagerFactories[$persistenceUnitName];
	}

	/**
	 * @param Pdo $dbh
	 * @return EntityManager
	 */
	private function createEntityManagerFactory($persistenceUnitName = null) {
		return new LazyEntityManagerFactory($persistenceUnitName, $this);
	}
	/**
	 * @return EntityModelManager
	 */
	public function getEntityModelManager() {
		return $this->entityModelManager;
	}
	/**
	 * @return EntityProxyManager
	 */
	public function getEntityProxyManager() {
		return $this->entityProxyManager;
	}

	function clear() {
		$entityManagerFactories = $this->entityManagerFactories;
		$this->entityManagerFactories = [];

		foreach ($entityManagerFactories as $entityManagerFactory) {
			$entityManagerFactory->clear();
		}
	}
}