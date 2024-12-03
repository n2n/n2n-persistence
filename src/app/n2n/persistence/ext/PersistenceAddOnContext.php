<?php
namespace n2n\persistence\ext;
use n2n\core\container\impl\AddOnContext;
use n2n\core\container\impl\AppN2nContext;
use n2n\persistence\orm\EntityManager;
use n2n\persistence\orm\model\EntityModelFactory;
use n2n\util\magic\impl\SimpleMagicContext;
use n2n\persistence\orm\EntityManagerFactory;
use n2n\util\ex\IllegalStateException;

class PersistenceAddOnContext implements AddOnContext {

	private ?SimpleMagicContext $simpleMagicContext = null;

	function __construct(private EmPool $emPool, private \Closure $finalizeCallback) {
		$this->simpleMagicContext = new SimpleMagicContext([
			EmPool::class => $this->emPool,
			PdoPool::class => $emPool->getPdoPool(),
			EntityManager::class => fn () => $emPool->getEntityManagerFactory()->getExtended(),
			EntityManagerFactory::class => fn () => $emPool->getEntityManagerFactory()
		]);
	}

	function hasMagicObject(string $id): bool {
		$this->ensureNotFinalized();

		return $this->simpleMagicContext->has($id);
	}

	function lookupMagicObject(string $id, bool $required = true, ?string $contextNamespace = null): mixed {
		$this->ensureNotFinalized();

		return $this->simpleMagicContext->lookup($id, false, $contextNamespace);
	}

	function isFinalized(): bool {
		return $this->simpleMagicContext === null;
	}

	function ensureNotFinalized(): void {
		if (!$this->isFinalized()) {
			return;
		}

		throw new IllegalStateException(self::class . ' already finalized.');
	}

//	function copyTo(AppN2nContext $appN2nContext): void {
//		if ($appN2nContext->getTransactionManager() === $this->pdoPool->getTransactionManager()) {
//			$pdoPool = $this->pdoPool->fork($appN2nContext);
//		} else {
//			$pdoPool = PdoPool::createFromAppN2nContext($appN2nContext);
//		}
//
//		$appN2nContext->addAddonContext(new PersistenceAddOnContext($pdoPool));
//	}

	function finalize(): void {
		$this->ensureNotFinalized();

		$this->emPool->clear();
		$this->simpleMagicContext = null;

		$c = $this->finalizeCallback;
		unset($this->finalizeCallback);
		$c($this);

	}
}
