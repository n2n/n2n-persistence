<?php

namespace n2n\persistence\orm\store\datadefinition\mock;

use n2n\persistence\meta\structure\common\RenameMetaEntityRequestAdapter;
use n2n\persistence\Pdo;
use n2n\spec\dbo\meta\structure\MetaEntity;

class RenameMetaEntityRequestMock extends RenameMetaEntityRequestAdapter {

	function __construct(MetaEntity $metaEntity, string $oldName, string $newName,
			private MetaManagerMock $metaManager) {
		parent::__construct($metaEntity, $oldName, $newName);
	}

	function execute(Pdo $dbh) {
		$this->metaManager->logExecutedRequest($this);
	}

	function __toString(): string {
		return 'RENAME ' . $this->oldName . ' TO ' . $this->newName;
	}
}
