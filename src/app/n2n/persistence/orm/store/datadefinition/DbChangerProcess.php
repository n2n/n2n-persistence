<?php

namespace n2n\persistence\orm\store\datadefinition;

use n2n\persistence\orm\model\EntityModelManager;
use n2n\spec\dbo\meta\structure\MetaManager;

class DbChangerProcess {

	function __construct(private EntityModelManager $entityModelManager) {

	}

	function execute(MetaManager $metaManager, DbUpdateMode $mode): void {
		$ddChanger = new DdChanger($this->entityModelManager);

		$database = $metaManager->createDatabase();
		$ddChanger->updateDatabase($database, $mode);

		$metaManager->flush();
	}
}