<?php

namespace n2n\persistence\ext;

use n2n\core\ext\N2nExtension;
use n2n\core\container\impl\AppN2nContext;

class PersistenceN2nExtension implements N2nExtension {

	public function __construct() {
	}

	function setUp(AppN2nContext $appN2nContext): void {
		$appN2nContext->addAddonContext(new PersistenceAddOnContext(PdoPool::createFromAppN2nContext($appN2nContext)));
	}
}