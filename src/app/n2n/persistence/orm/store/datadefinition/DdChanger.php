<?php

namespace n2n\persistence\orm\store\datadefinition;

use n2n\util\ex\IllegalStateException;
use n2n\spec\dbo\meta\structure\Table;
use n2n\util\ex\ExUtils;
use n2n\persistence\orm\model\EntityModelManager;
use n2n\spec\dbo\meta\structure\Database;
use n2n\persistence\orm\model\EntityModel;

class DdChanger {

	function __construct(private EntityModelManager $entityModelManager) {

	}

	function updateDatabase(Database $database, DbUpdateMode $mode): void {
		if ($mode->isDatabaseCleared()) {
			$this->clearDatabase($database);
		}

		foreach ($this->entityModelManager->getEntityClasses() as $entityClass) {
			$entityModel = $this->entityModelManager->getEntityModelByClass($entityClass);
			$updateProcess = $this->createTableUpdateProcess($database, $entityModel);
			if ($mode->isTableCleared()) {
				$this->clearTable($updateProcess->table);
			}

			$entityModel->updateDataDefinition($updateProcess);
		}
	}

	private function clearDatabase(Database $database): void {
		foreach ($database->getMetaEntities() as $metaEntity) {
			$database->removeMetaEntityByName($metaEntity->getName());
		}
	}

	private function clearTable(Table $table): void {
		foreach ($table->getIndexes() as $index) {
			$table->removeIndexByName($index->getName());
		}

		foreach ($table->getColumns() as $column) {
			$table->removeColumnByName($column->getName());
		}
	}

	private function createTableUpdateProcess(Database $database, EntityModel $entityModel): DdTableUpdateProcess {
		$tableName = $entityModel->getTableName();

		if (!$database->containsMetaEntityName($tableName)) {
			$metaEntity = $database->createMetaEntityFactory()->createTable($tableName);
		} else {
			$metaEntity = ExUtils::try(fn () => $database->getMetaEntityByName($tableName));
			IllegalStateException::assertTrue($metaEntity instanceof Table);
		}

		return new DdTableUpdateProcess($metaEntity);
	}
}