<?php
namespace n2n\persistence\ext;
use n2n\core\container\impl\AddOnContext;
use n2n\core\container\impl\AppN2nContext;
use n2n\core\container\PdoPool;

class PersistenceAddOnContext implements AddOnContext {

	function copyTo(AppN2nContext $appN2NContext) {

		if ($keepTransactionContext) {
			$pdoPool = $appN2nContext->lookup(PdoPool::class);
			foreach ($n2nContext->lookup(PdoPool::class)->getInitializedPdos() as $puName => $pdo) {
				$pdoPool->setPdo($puName, $pdo);
			}
		}
	}
}
