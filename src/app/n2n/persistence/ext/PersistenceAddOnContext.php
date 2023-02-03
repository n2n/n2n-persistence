<?php
namespace n2n\persistence\ext;
use n2n\core\container\impl\AddOnContext;
use n2n\core\container\impl\AppN2nContext;
use n2n\persistence\orm\EntityManager;
use n2n\persistence\orm\model\EntityModelFactory;
use n2n\util\magic\impl\SimpleMagicContext;
use n2n\persistence\orm\EntityManagerFactory;

class PersistenceAddOnContext implements AddOnContext {

	private SimpleMagicContext $simpleMagicContext;

	function __construct(private PdoPool $pdoPool) {
		$this->simpleMagicContext = new SimpleMagicContext([
			PdoPool::class => $pdoPool,
			EntityManager::class => fn () => $pdoPool->getEntityManagerFactory()->getExtended(),
			EntityManagerFactory::class => fn () => $pdoPool->getEntityManagerFactory()
		]);
	}

	function hasMagicObject(string $id): bool {
		return $this->simpleMagicContext->has($id);
	}

	function lookupMagicObject(string $id, bool $required = true, string $contextNamespace = null): mixed {
		return $this->lookupMagicObject($id, false, $contextNamespace);
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
