<?php

namespace n2n\persistence\orm\store\datadefinition\mock;

use n2n\persistence\meta\structure\common\DropMetaEntityRequestAdapter;
use n2n\persistence\Pdo;
use n2n\spec\dbo\meta\structure\MetaEntity;

class DropMetaEntityRequestMock extends DropMetaEntityRequestAdapter {

	function __construct(MetaEntity $metaEntity, private MetaManagerMock $metaManager) {
		parent::__construct($metaEntity);
	}

	function execute(Pdo $dbh) {
		$this->metaManager->logExecutedRequest($this);
	}

	function __toString(): string {
		return 'DROP ' . $this->getMetaEntity()->getName();
	}
}
