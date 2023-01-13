<?php
namespace n2n\persistence\ext;
use n2n\core\container\impl\AddOnContext;
use n2n\core\container\impl\AppN2nContext;
use n2n\persistence\orm\EntityManager;
use n2n\persistence\orm\model\EntityModelFactory;
use n2n\util\magic\impl\SimpleMagicContext;
use n2n\persistence\orm\EntityManagerFactory;

class PersistenceAddOnContext extends SimpleMagicContext implements AddOnContext {

	function __construct(private PdoPool $pdoPool) {
		parent::__construct([
			PdoPool::class => $pdoPool,
			EntityManager::class => fn () => $pdoPool->getEntityManagerFactory()->getExtended(),
			EntityManagerFactory::class => fn () => $pdoPool->getEntityManagerFactory()
		]);
	}

	function copyTo(AppN2nContext $appN2NContext): void {
		$pdoPool = PdoPool::createFromAppN2nContext($appN2NContext);

		if ($pdoPool->getTransactionManager() === $this->pdoPool->getTransactionManager()) {
			foreach ($this->pdoPool->getInitializedPdos() as $puName => $pdo) {
				$pdoPool->setPdo($puName, $pdo);
			}
		}

		$appN2NContext->addAddonContext(new PersistenceAddOnContext($pdoPool));
	}

	function finalize(): void {
	}
}
