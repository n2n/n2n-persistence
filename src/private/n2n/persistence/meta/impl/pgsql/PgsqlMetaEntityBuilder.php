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

use n2n\persistence\Pdo;
use n2n\persistence\meta\impl\pgsql\PgsqlDatabase;
use n2n\persistence\meta\impl\pgsql\PgsqlView;
use n2n\persistence\meta\impl\pgsql\PgsqlTable;

use n2n\persistence\meta\impl\pgsql\PgsqlBinaryColumn;
use n2n\persistence\meta\impl\pgsql\PgsqlDateTimeColumn;
use n2n\persistence\meta\impl\pgsql\PgsqlEnumColumn;
use n2n\persistence\meta\impl\pgsql\PgsqlFixedPointColumn;
use n2n\persistence\meta\impl\pgsql\PgsqlFloatingPointColumn;
use n2n\persistence\meta\impl\pgsql\PgsqlIntegerColumn;
use n2n\persistence\meta\impl\pgsql\PgsqlStringColumn;
use n2n\persistence\meta\impl\pgsql\PgsqlTextColumn;
use n2n\persistence\meta\impl\pgsql\PgsqlColumn;

class PgsqlMetaEntityBuilder {
	const TABLE_TYPE_BASE_TABLE = 'BASE TABLE';
	const TABLE_TYPE_VIEW = 'VIEW';

	private $dbh;
	private $database;

	public function __construct(Pdo $dbh, PgsqlDatabase $database) {
		$this->dbh = $dbh;
		$this->database = $database;
	}

	public function createMetaEntity($metaEntityName) {
		$stmt = $this->dbh->prepare('SELECT * FROM information_schema.tables WHERE table_catalog = ? AND table_name = ?');
		$stmt->execute(array($this->database->getName(), $metaEntityName));
		$result = $stmt->fetch(Pdo::FETCH_ASSOC);

		$metaEntity = null;
		switch ($result['table_type']) {
			case self::TABLE_TYPE_VIEW:
				$stmt = $this->dbh->prepare('select view_definition from INFORMATION_SCHEMA.VIEWS where table_catalog = ? AND table_name = ?');
				$stmt->execute(array($this->database->getName(), $metaEntityName));
				$result = $stmt->fetch(Pdo::FETCH_ASSOC);

				$metaEntity = new PgsqlView($metaEntityName, $result['view_definition']);
				$metaEntity->setDatabase($this->database);
				break;
			case self::TABLE_TYPE_BASE_TABLE:
				$metaEntity = new PgsqlTable($metaEntityName);
				$metaEntity->setColumns($this->getColumnsForTablename($metaEntityName));
				$metaEntity->setDatabase($this->database);
				foreach ($this->getIndexesForTablename($metaEntityName) as $index) {
					$metaEntity->createIndex($index['indtype'], explode(',', substr($index['indcolumns'], 1, -1)), $index['indname']);
				}
				break;
		}
		return $metaEntity;
	}

	private function getColumnsForTablename($metaEntityName) {
		$stmt = $this->dbh->prepare('
			SELECT * FROM INFORMATION_SCHEMA.columns AS isc
			LEFT JOIN pg_collation AS pc ON isc.collation_name = pc.collname
			WHERE isc.table_catalog = ? AND isc.table_name = ?
		');
		$stmt->execute(array($this->database->getName(), $metaEntityName));
		$result = $stmt->fetchAll(Pdo::FETCH_ASSOC);
		$columns = array();
		foreach ($result as $row) {
			switch ($row['data_type']) {
				case 'date':
					$column = new PgsqlDateTimeColumn($row['column_name'], true, false);

					$column->setNullAllowed($row['is_nullable'] == 'YES' ? true : false);
					unset($row['column_name'], $row['is_nullable']);
					$column->setAttrs($row);

					$columns[$column->getName()] = $column;
					break;
				case 'time with time zone':
				case 'time without time zone':
					$column = new PgsqlDateTimeColumn($row['column_name'], false, true);

					$column->setNullAllowed($row['is_nullable'] == 'YES' ? true : false);
					unset($row['column_name'], $row['is_nullable']);
					$column->setAttrs($row);

					$columns[$column->getName()] = $column;
					break;
				case 'timestamp with time zone':
				case 'timestamp without time zone':
					$column = new PgsqlDateTimeColumn($row['column_name'], true, true);

					$column->setNullAllowed($row['is_nullable'] == 'YES' ? true : false);
					unset($row['column_name'], $row['is_nullable']);
					$column->setAttrs($row);

					$columns[$column->getName()] = $column;
					break;
				case 'bytea':
					$column = new PgsqlBinaryColumn($row['column_name'], (isset($row['character_maximum_length']) ? $row['character_maximum_length'] : (pow(2, 31)-1)));
					$column->setNullAllowed($row['is_nullable'] == 'YES' ? true : false);
					unset($row['column_name'], $row['character_maximum_length'], $row['is_nullable']);
					$column->setAttrs($row);

					$columns[$column->getName()] = $column;
					break;
				case 'money':
				case 'numeric':
					$column = new PgsqlFixedPointColumn($row['column_name'], ($row['numeric_precision'] - $row['numeric_scale']), $row['numeric_scale']);
					$column->setNullAllowed($row['is_nullable'] == 'YES' ? true : false);
					unset($row['column_name'], $row['is_nullable']);
					$column->setAttrs($row);

					$columns[$column->getName()] = $column;
					break;
				case 'double precision':
				case 'real':
					$column = new PgsqlFloatingPointColumn($row['column_name'], $row['numeric_precision']);
					$column->setNullAllowed($row['is_nullable'] == 'YES' ? true : false);
					unset($row['column_name'], $row['numeric_precision'], $row['is_nullable']);
					$column->setAttrs($row);

					$columns[$column->getName()] = $column;
					break;
				case 'bigint':
				case 'integer':
				case 'smallint':
				case 'serial':
				case 'bigserial':
					$stmtInteger = $this->dbh->prepare('
						SELECT * FROM INFORMATION_SCHEMA.check_constraints
						WHERE constraint_catalog = ?
						AND (check_clause LIKE ? AND check_clause NOT LIKE ? AND check_clause NOT LIKE ?)
					');
					$stmtInteger->execute(array($this->database->getName(), '%' . $row['column_name'] . ' > 0%', '%' . $row['column_name'] . ' > 0% %OR%', '%OR% %' . $row['column_name'] . ' > 0%'));
					$integerResult = $stmtInteger->fetchAll(Pdo::FETCH_ASSOC);

					$unsigned = true;
					if (sizeof($integerResult)) $unsigned = false;

					$column = new PgsqlIntegerColumn($row['column_name'], $row['numeric_precision'], $unsigned, $row);
					$column->setNullAllowed($row['is_nullable'] == 'YES' ? true : false);
					unset($row['column_name'], $row['numeric_precision'], $row['is_nullable']);
					$column->setAttrs($row);

					$columns[$column->getName()] = $column;
					break;
				case 'bit varying':
				case 'char':
				case 'character':
				case 'character varying':
					$column = new PgsqlStringColumn($row['column_name'], $row['character_maximum_length'], $row['collctype']);
					$column->setNullAllowed($row['is_nullable'] == 'YES' ? true : false);
					unset($row['$column_name'], $row['character_maximum_length'], $row['collctype'], $row['is_nullable']);
					$column->setAttrs($row);

					$columns[$column->getName()] = $column;
					break;
				case 'text':
					$column = new PgsqlTextColumn($row['column_name'], $row['character_octet_length'], $row['collctype']);
					$column->setNullAllowed($row['is_nullable'] == 'YES' ? true : false);
					unset($row['column_name'], $row['character_octet_length'], $row['collctype'], $row['is_nullable']);
					$column->setAttrs($row);

					$columns[$column->getName()] = $column;
					break;
				case 'USER-DEFINED':
					$stmt = $this->dbh->prepare('SELECT e.enumlabel AS enum_value
							FROM pg_type t 
								JOIN pg_enum e ON t.oid = e.enumtypid
								JOIN pg_catalog.pg_namespace n ON n.oid = t.typnamespace
							WHERE t.typname = ?');
					$stmt->execute(array($row['udt_name']));
					$result = $stmt->fetchAll(Pdo::FETCH_ASSOC);

					$values = array();
					foreach ($result as $resultRow) {
						$values[] = $resultRow['enum_value'];
					}
					if (!is_null($result)) {
						$enumColumn = new PgsqlEnumColumn($row['column_name'], $values);
						$enumColumn->setNullAllowed($row['is_nullable']);
						unset($row['udt_name'], $row['column_name'], $row['enum_value'], $row['is_nullable']);
						$column->setAttrs($row);

						$columns[$enumColumn->getName()] = $enumColumn;
					}
					break;
				case 'abstime':
				case 'aclitem':
				case 'bit':
				case 'boolean':
				case 'box':
				case 'cid':
				case 'cidr':
				case 'circle':
				case 'date':
				case 'daterange':
				case 'gtsvector':
				case 'inet':
				case 'int2vector':
				case 'int4range':
				case 'int8range':
				case 'interval':
				case 'json':
				case 'line':
				case 'lseg':
				case 'macaddr':
				case 'name':
				case 'numrange':
				case 'oid':
				case 'oidvector':
				case 'path':
				case 'pg_node_tree':
				case 'point':
				case 'polygon':
				case 'refcursor':
				case 'regclass':
				case 'regconfig':
				case 'regdictionary':
				case 'regoper':
				case 'regoperator':
				case 'regproc':
				case 'regprocedure':
				case 'regtime':
				case 'reltime':
				case 'smgr':
				case 'tid':
				case 'tinterval':
				case 'tsquery':
				case 'tsrange':
				case 'tstzrange':
				case 'tsvector':
				case 'txid_snapshot':
				case 'uuid':
				case 'xid':
				case 'xml':
				default:
					$column = new PgsqlColumn($row['column_name']);
					$column->setNullAllowed($row['is_nullable'] == 'YES' ? true : false);
					unset($row['column_name'], $row['is_nullable']);
					$column->setAttrs($row);

					$columns[$column->getName()] = $column;
					break;
			}
		}
		return $columns;
	}

	private function getIndexesForTablename($metaEntityName) {
		$stmtPu = $this->dbh->prepare('
			SELECT istc.constraint_name AS indname, istc.constraint_type AS indtype,
				ARRAY (
					SELECT pg_get_indexdef(idx.indexrelid, k + 1, true)
					FROM generate_subscripts(idx.indkey, 1) as k
					ORDER BY k
				) AS indcolumns
			FROM INFORMATION_SCHEMA.table_constraints AS istc
				JOIN pg_index AS idx ON istc.constraint_name = TEXT(idx.indexrelid::regclass)
			WHERE istc.constraint_type != ? AND istc.table_name = ?;');
		$stmtPu->execute(array(PgsqlIndex::TYPE_CHECK, $metaEntityName));
		$stmtPuArray = $stmtPu->fetchAll(Pdo::FETCH_ASSOC);

		$sql = '
			SELECT i.relname AS indname, \'INDEX\' AS indtype,
				ARRAY(
					SELECT pg_get_indexdef(idx.indexrelid, k + 1, true)
					FROM generate_subscripts(idx.indkey, 1) as k
					ORDER BY k
				) AS indcolumns
			FROM pg_index AS idx
				JOIN pg_class AS i ON i.oid = idx.indexrelid
				JOIN pg_am AS am ON i.relam = am.oid
			WHERE TEXT(idx.indrelid::regclass) = ?
				 AND idx.indisprimary != ? AND idx.indisunique != ? ';
		$executeArray = array($metaEntityName, 't', 't');

		$stmt = $this->dbh->prepare($sql);
		$stmt->execute($executeArray);
		$stmtArray = $stmt->fetchAll(Pdo::FETCH_ASSOC);

		return array_merge($stmtPuArray, $stmtArray);
	}
}
