<?php
/*
 * Copyright (c) 2012-2016, Hofmänner New Media.
 * DO NOT ALTER OR REMOVE COPYRIGHT NOTICES OR THIS FILE HEADER.
 *
 * This file is part of the N2N FRAMEWORK.
 *
 * The N2N FRAMEWORK is free software: you can redistribute it and/or modify it under the terms of
 * the GNU Lesser General Public License as published by the Free Software Foundation, either
 * version 2.1 of the License, or (at your option) any later version.
 *
 * N2N is distributed in the hope that it will be useful, but WITHOUT ANY WARRANTY; without even
 * the implied warranty of MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU Lesser General Public License for more details: http://www.gnu.org/licenses/
 *
 * The following people participated in this project:
 *
 * Andreas von Burg.....: Architect, Lead Developer
 * Bert Hofmänner.......: Idea, Community Leader, Marketing
 * Thomas Günther.......: Developer, Hangar
 */
namespace n2n\persistence\meta\impl\pgsql;

use n2n\persistence\meta\structure\common\AlterMetaEntityRequest;
use n2n\persistence\meta\structure\IndexType;
use n2n\persistence\Pdo;

class PgsqlMetaEntityAlterChangeRequest implements AlterMetaEntityRequest {
	private $metaEntity;

	public function __construct(PgsqlMetaEntity $metaEntity) {
		$this->setMetaEntity($metaEntity);
	}

	private function setMetaEntity(PgsqlMetaEntity $metaEntity) {
		$this->metaEntity = $metaEntity;
	}

	public function getMetaEntity() {
		return $this->metaEntity;
	}

	public function execute(Pdo $dbh) {
		$sql = null;
		$quotedEntityName = $dbh->quote($this->getMetaEntity()->getName());

		if ($this->getMetaEntity() instanceof PgsqlView) {
			$sql = 'DROP VIEW ' . $quotedEntityName . ';';
			$sql .= 'CREATE VIEW ' . $quotedEntityName . ' AS ' . $dbh->quote($this->getMetaEntity()->getQuery()) . ';';
		} elseif ($this->getMetaEntity() instanceof PgsqlTable) {
			$metaEntityBuilder = new PgsqlMetaEntityBuilder($dbh, $this->getMetaEntity()->getDatabase());

			$currentTable = $metaEntityBuilder->createMetaEntity($this->getMetaEntity()->getName());
			$currentTableColumns = $currentTable->getColumns();
			$newTableColumns = $this->getMetaEntity()->getColumns();

			$addableColumns = array_diff($newTableColumns, $currentTableColumns);
			$changableColumns = array_intersect($currentTableColumns, $newTableColumns);
			$deleteableColumns = array_diff($currentTableColumns, $newTableColumns);

			if (sizeof($deleteableColumns)) {
				foreach ($deleteableColumns as $deleteableColumn) {
					$sql .= ' ALTER TABLE ' . $quotedEntityName . ' DROP COLUMN ' 
							. $dbh->quote($deleteableColumn->getName()) . ';';
				}
			}

			if (sizeof($addableColumns)) {
				foreach ($addableColumns as $addableColumn) {
					$sql .= ' ALTER TABLE ' . $quotedEntityName . ' ADD COLUMN ' . $dbh->quote($addableColumn->getName())
						. ' TYPE ' . $addableColumn->getType() . (!is_null($addableColumn->getSize()) ? '(' . intval($addableColumn->getSize()) . ')' : '')
						. ($addableColumn->isNullAllowed() ? ' NULL ' : ' NOT NULL ')
						. (!is_null($addableColumn->getDefaultValue()) ? ' SET DEFAULT ' . $dbh->quote($addableColumn->getDefaultValue()) . ' ' : ' ') . ';';
				}
			}

			if (sizeof($changableColumns)) {
				foreach ($changableColumns as $changeableColumn) {
					$sql .= ' ALTER TABLE ' . $quotedEntityName . ' ALTER COLUMN ' . $dbh->quote($changeableColumn->getName())
						. ' TYPE ' . $changeableColumn->getType() . (!is_null($changeableColumn->getSize()) ? '(' . intval($changeableColumn->getSize()) . ')' : '')
						. (!is_null($changeableColumn->getDefaultValue()) ? ' SET DEFAULT ' . $dbh->quote($changeableColumn->getDefaultValue()) . ' ' : ' ') . ';';
				}
			}

			$currentTableIndexes = $metaEntityBuilder->createMetaEntity($this->getMetaEntity()->getName())->getIndexes();
			$newTableIndexes = $this->getMetaEntity()->getIndexes();

			$addableIndexes = array_diff($newTableIndexes, $currentTableIndexes);
			$changeableIndexes = array_intersect($currentTableIndexes, $newTableIndexes);
			$deleteableIndexes = array_diff($currentTableIndexes, $newTableIndexes);

			if (sizeof($deleteableIndexes)) {
				foreach ($deleteableIndexes as $deleteableIndex) {
					$sql .= ' ALTER TABLE ' . $quotedEntityName . ' DROP CONSTRAINT ' . $deleteableIndex->getType() . ' ' . $deleteableIndex->getName() . ';';
				}
			}

			if (sizeof($addableIndexes)) {
				foreach ($addableIndexes as $addableIndex) {
					switch ($addableIndex->getType()) {
						case IndexType::UNIQUE:
							$sql .= ' CREATE UNIQUE INDEX ' . $dbh->quote($addableIndex->getName()) . ' ON ' . $quotedEntityName . ' (' . $dbh->quote(implode(',', $addableIndex->getColumns())) . ');';
							break;
						case IndexType::PRIMARY:
							$sql .= ' ALTER TABLE ' . $quotedEntityName . ' ADD CONSTRAINT ' 
									. $dbh->quote($addableIndex->getName()) . ' PRIMARY KEY ' 
									. ' (' . $dbh->quote(implode(',', $addableIndex->getColumns())) . ');';
							break;
						default:
							$sql .= ' CREATE INDEX ' . $dbh->quote($addableIndex->getName()) . ' ON ' . $quotedEntityName . ' (' . $dbh->quote(implode(',', $addableIndex->getColumns())) . ');';
					}
				}
			}

			if (sizeof($changeableIndexes)) {
				foreach ($changeableIndexes as $changeableIndex) {
					$sql .= ' ALTER INDEX ' . $dbh->quote($changeableIndex->getName())
						. (!is_null($changeableIndex->getTable()) ? ' SET TABLESPACE ' . $dbh->quote($changeableIndex->getTable()->getName()) : '')
						. (sizeof($changeableIndex->getColumns()) ? ' SET COLUMNS ' . ' (' . $dbh->quote(implode(',', $changeableIndex->getColumns())) : '') . ');';
				}
			}
		}
		$dbh->exec($sql);
	}
}
