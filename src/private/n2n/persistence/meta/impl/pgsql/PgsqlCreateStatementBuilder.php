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

use n2n\persistence\meta\structure\IndexType;
use n2n\persistence\Pdo;

class PgsqlCreateStatementBuilder {
	private $dbh;
	private $metaEntity;
	private $sequenceNeeded;
	private $enumCreateTypeSqlStatements = array();
	private $newTypeNames = array();

	public function __construct(Pdo $dbh) {
		$this->dbh = $dbh;
		$this->sequenceNeeded = true;
	}

	public function setMetaEntity(PgsqlMetaEntity $metaEntity) {
		$this->metaEntity = $metaEntity;
	}

	public function getMetaEntity() {
		return $this->metaEntity;
	}

	public function setSequenceNeeded($sequenceNeeded) {
		$this->sequenceNeeded = (bool) $sequenceNeeded;
	}

	public function isSequenceNeeded() {
		return (bool) $this->sequenceNeeded;
	}

	private function addNewTypeName($typeName) {
		$this->newTypeNames[] = $typeName;
	}

	private function getNewTypeNames() {
		return $this->newTypeNames;
	}

	public function toSqlString() {
		$sqlString = null;
		foreach ($this->generateSqlStatements() as $sql) {
			$sqlString .= $sql . "\n";
		}
		return $sqlString;
	}

	public function executeSqlStatements() {
		foreach ($this->generateSqlStatements() as $sqlStatement) {
			if ($sqlStatement != "\n") $this->dbh->exec($sqlStatement);
		}
	}

	public function generateSqlStatements() {
		$sqlStatements = array();

		if ($this->getMetaEntity() instanceof PgsqlTable) {
			if ($this->sequenceNeeded) {
				foreach ($this->generateSequenceSql() as $sequenceValue) {
					$sqlStatements[] = $sequenceValue;
				}
				$this->sequenceNeeded = false;
			}
			$sqlStatements[] = ' DROP TABLE IF EXISTS ' . $this->dbh->quoteField($this->getMetaEntity()->getName()) . '; ';
			$sql = ' CREATE TABLE ' . $this->dbh->quoteField($this->getMetaEntity()->getName()) . ' (';
			$sql .= implode(', ', $this->getTableColumnsSql($this->dbh)) . '); ';
			$sqlStatements[] = $sql;
			$sqlStatements = array_merge($sqlStatements, $this->getTableIndexesSql($this->dbh));
		} elseif ($this->getMetaEntity() instanceof PgsqlView) {
			$sqlStatements[] = ' DROP VIEW IF EXISTS ' . $this->dbh->quoteField($this->getMetaEntity()->getName()) . '; ';
			$sqlStatements[] = ' CREATE OR REPLACE VIEW ' . $this->dbh->quoteField($this->getMetaEntity()->getName())
					. ' AS ' . $this->getMetaEntity()->getQuery()
					. (substr($this->getMetaEntity()->getQuery(), -1) == ';' ? '' : ';');
		}
		$returnArray = array_merge(array("\n"), $this->getEnumCreateTypeSqlStatements(), $sqlStatements);
		$this->enumCreateTypeSqlStatements = array();
		return $returnArray;
	}

	private function generateSequenceSql() {
		$stmt = $this->dbh->prepare('SELECT * FROM INFORMATION_SCHEMA.sequences WHERE sequence_catalog = ?');
		$stmt->execute(array($this->metaEntity->getDatabase()->getName()));
		$result = $stmt->fetchAll(Pdo::FETCH_ASSOC);

		$sequenceSqlArray = array();
		foreach ($result as $row) {
			$sequenceSqlArray[] = ' DROP SEQUENCE IF EXISTS ' . $row['sequence_name'] . ';';
			$sequenceSqlArray[] = ' CREATE SEQUENCE ' . $row['sequence_name']
					. ' INCREMENT ' . $row['increment']
					. ' MINVALUE ' . $row['minimum_value']
					. ' MAXVALUE ' . $row['maximum_value']
					. ' START ' . $row['start_value']
					. ' CACHE 1;';
		}
		return $sequenceSqlArray;
	}

	private function getTableColumnsSql(Pdo $dbh) {
		$columns = $this->getMetaEntity()->getColumns();

		foreach ($columns as $column) {
			$typeName = $column->getTypeForCurrentState();

			if ($column instanceof PgsqlEnumColumn) {
				for ($i = 0; $i <= PHP_INT_MAX; $i++) {
					$typeName = 'enum_type_' . $i;
					if (!$this->containsTypeName($typeName) && !in_array($typeName, $this->getNewTypeNames())) {
						$this->addNewTypeName($typeName);
						break;
					}
				}
				if (sizeof($column->getValues())) {
					$this->addEnumCreateTypeSqlStatement(' CREATE TYPE ' . $typeName
							. ' AS ENUM (\'' . implode('\',\'', $column->getValues()) . '\');');
				}
			}

			$columnArrayValue = null;
			$columnAttrs = $column->getAttrs();
			$columnArrayValue = $column->getName() . ' ' . $typeName;

			$columnArrayValue .= (isset($columnAttrs['collctype']) ? ' COLLATION '
					. $dbh->quoteField($columnAttrs['collctype']) : '') . ($column->isNullAllowed() ? ' NULL ' : ' NOT NULL ');
			if (isset($columnAttrs['column_default'])) {
				$columnArrayValue .= ' DEFAULT ';
				if (substr($columnAttrs['column_default'], 0, 7) == 'nextval') {
					$columnArrayValue .= $columnAttrs['column_default'];
				} else {
					$columnArrayValue .= $columnAttrs['column_default'];
				}
			}
			$columnArray[] = $columnArrayValue;
		}

		return $columnArray;
	}

	private function getTableIndexesSql(Pdo $dbh) {
		$indexArray = array();
		$indexes = $this->getMetaEntity()->getIndexes();
		if (sizeof($indexes)) {
			foreach ($indexes as $index) {
				$indexAttrs = $index->getAttrs();

				$indexColumnArray = array();
				foreach ($index->getColumns() as $indexColumn) {
					$indexColumnArray[] = $indexColumn->getName();
				}

				$indexArray[] = ' ALTER TABLE ' . $dbh->quoteField($this->getMetaEntity()->getName())
						. ' DROP CONSTRAINT IF EXISTS ' . $dbh->quoteField($index->getName()) . ';';
				$indexArray[] = ' DROP INDEX IF EXISTS ' . $dbh->quoteField($index->getName()) . ';';

				if (strtoupper($index->getType()) == strtoupper(IndexType::UNIQUE)) {
					$indexArray[] = ' CREATE UNIQUE INDEX ' . $dbh->quoteField($index->getName()) . ' ON '
							. $this->metaEntity->getName() . ' (' . implode(',', $indexColumnArray) . '); ';
					$indexArray[] = ' ALTER TABLE ' . $dbh->quoteField($this->getMetaEntity()->getName())
							. ' ADD ' . $index->getType() . ' USING INDEX '  . $dbh->quoteField($index->getName()) . ';';
				} elseif (strtoupper($index->getType()) == strtoupper(IndexType::PRIMARY) . ' KEY'
						|| strtoupper($index->getType()) == strtoupper(IndexType::PRIMARY)) {
					$indexArray[] = ' ALTER TABLE ' . $dbh->quoteField($this->getMetaEntity()->getName())
							. ' ADD CONSTRAINT ' . $dbh->quoteField($index->getName()) . ' '
							. (strtoupper($index->getType()) == strtoupper(IndexType::PRIMARY)
									? strtoupper($index->getType()) . ' KEY' : '')
							. ' (' . implode(',', $indexColumnArray) . ');';
				} elseif (strtoupper($index->getType()) == strtoupper(IndexType::INDEX)) {
					$indexArray[] = ' CREATE INDEX ' . $dbh->quoteField($index->getName()) . ' ON '
							. $dbh->quoteField($this->getMetaEntity()->getName()) . ' (' . implode(',', $indexColumnArray) . ');';
				}
			}
		}
		return $indexArray;
	}

	private function addEnumCreateTypeSqlStatement($sqlStatement) {
		$this->enumCreateTypeSqlStatements[] = $sqlStatement;
	}

	private function getEnumCreateTypeSqlStatements() {
		return $this->enumCreateTypeSqlStatements;
	}

	private function containsTypeName($name) {
		$sql = 'SELECT n.nspname AS enum_schema,  
					t.typname AS enum_name,  
					e.enumlabel AS enum_value
				FROM pg_type t 
					JOIN pg_enum e ON t.oid = e.enumtypid
					JOIN pg_catalog.pg_namespace n ON n.oid = t.typnamespace
				WHERE t.typname = ?
				LIMIT 1;';
		$stmt = $this->dbh->prepare($sql);
		$stmt->execute(array($name));

		return $stmt->fetchAll(Pdo::FETCH_ASSOC);
	}
}
