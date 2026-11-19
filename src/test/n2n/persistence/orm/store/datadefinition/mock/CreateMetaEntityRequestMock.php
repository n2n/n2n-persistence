<?php

namespace n2n\persistence\orm\store\datadefinition\mock;

use n2n\persistence\meta\structure\common\CreateMetaEntityRequestAdapter;
use n2n\persistence\Pdo;
use n2n\spec\dbo\meta\structure\MetaEntity;

class CreateMetaEntityRequestMock extends CreateMetaEntityRequestAdapter {

	function __construct(MetaEntity $metaEntity, private MetaManagerMock $metaManager) {
		parent::__construct($metaEntity);
	}

	function execute(Pdo $dbh) {
		$this->metaManager->logExecutedRequest($this);
	}

	function __toString(): string {
		return 'CREATE ' . $this->getMetaEntity()->getName();
	}
}
