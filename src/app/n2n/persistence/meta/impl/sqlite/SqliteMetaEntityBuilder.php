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
namespace n2n\persistence\meta\impl\sqlite;

use n2n\persistence\meta\structure\common\CommonIndex;

use n2n\persistence\meta\structure\common\CommonView;

use n2n\persistence\meta\structure\IndexType;

use n2n\persistence\Pdo;

class SqliteMetaEntityBuilder {
	
	const TYPE_TABLE = 'table';
	const TYPE_VIEW = 'view';
	const TYPE_INDEX = 'index';
	
	/**
	 * @var n2n\persistence\Pdo
	 */
	private $dbh;
	
	/**
	 * @var n2n\persistence\meta\impl\sqlite\SQLiteDatabase
	 */
	private $database;
	
	public function __construct(Pdo $dbh, SQLiteDatabase $database) {
		$this->dbh = $dbh;
		$this->database = $database;
	}
	
	/**
	 * @param string $name
	 * @return n2n\persistence\meta\structure\MetaEntity
	 */
	public function createMetaEntity($name) {
		
		$metaEntity = null;
		
		$sql = 'SELECT * FROM ' . $this->dbh->quoteField($this->database->getName()) . '.sqlite_master WHERE type in (:type_table, :type_view) AND name = :name';
		$statement = $this->dbh->prepare($sql);
		$statement->execute(
				array(':type_table' => self::TYPE_TABLE, 
						':type_view' => self::TYPE_VIEW, 
						':name' => $name));
		$result = $statement->fetch(Pdo::FETCH_ASSOC);
		
		$tableType = $result['type'];
		
		switch ($tableType) {
			case self::TYPE_TABLE:
				$table = new SqliteTable($name);
				$table->setColumns($this->getColumnsForTable($table));
				$table->setIndexes($this->getIndexesForTable($table));
				$metaEntity = $table;
				break;
			case self::TYPE_VIEW:
				$view = new CommonView($name, $this->parseViewCreateStatement($result['sql']));
				$metaEntity = $view;
				break;
		}
		$metaEntity->setAttrs($result);
		$metaEntity->setDatabase($this->database);
		$metaEntity->registerChangeListener($this->database);
		return $metaEntity;
	}
	
	private function getColumnsForTable(SqliteTable $table) {
		$columns = array();
		$sql = 'PRAGMA ' . $this->dbh->quoteField($this->database->getName()) 
				. '.table_info(' . $this->dbh->quoteField($table->getName()) . ')';
		$statement = $this->dbh->prepare($sql);
		$statement->execute();
		$numPrimaryKeyColumns = 0;
		$generatedIdentifierColumnName = null;
		while (null != ($row = $statement->fetch(PDO::FETCH_ASSOC))) {
			if ($row['pk']) {
				$numPrimaryKeyColumns++;
			} 
			$column = null;
			if (preg_match('/int/i', $row['type'])) {
				$column = new SqliteIntegerColumn($row['name']);
				if ($row['type'] == 'INTEGER' && $row['pk']) {
					$generatedIdentifierColumnName = $row['name'];
				}
			} elseif(preg_match('/char|clob|text/i', $row['type'])) {
				$column = new SqliteStringColumn($row['name']);
			} elseif(empty($row['type']) || preg_match('/blob/i', $row['type'])) {
				$column = new SqliteBinaryColumn($row['name']);
			} elseif (preg_match('/REAL|FLOA|DOUB/i', $row['type'])) {
				$column = new SqliteFloatingPointColumn($row['name']);
			} elseif($row['type'] == SqliteDateTimeColumn::COLUMN_TYPE_NAME) {
				$column = new SqliteDateTimeColumn($row['name']);
			} else {
				$column = new SqliteFixedPointColumn($row['name']);
			}
			$column->setNullAllowed(!($row['notnull']));
			$column->setDefaultValue($row['dflt_value']);
			$column->setAttrs($row);
			$columns[$row['name']] = $column;
		}

		if (($numPrimaryKeyColumns == 1) &&
				(!(is_null($generatedIdentifierColumnName)))) {
			$this->dbh->getMetaData()->getDialect()->applyIdentifierGeneratorToColumn($this->dbh, $columns[$generatedIdentifierColumnName]);
		}
		return $columns;
	}
	
	private function getIndexesForTable(SqliteTable $table) {
		
		$primaryColumns = false;
		$indexes = array();
		$columns = $table->getColumns();
		$sql = 'PRAGMA ' . $this->dbh->quoteField($this->database->getName()) 
					. '.index_list(' . $this->dbh->quoteField($table->getName()) . ')';
		$statement = $this->dbh->prepare($sql);
		$statement->execute();
		while (null != ($result = $statement->fetch(Pdo::FETCH_ASSOC))) {
			$type = null;
			$name = $result['name'];
			if (!($result['unique'])) {
				$type = IndexType::INDEX;
			} else {
				$indexSql = 'SELECT * FROM ' . $this->dbh->quoteField($this->database->getName()) . '.sqlite_master WHERE name = :name';
				$indexStatement = $this->dbh->prepare($indexSql);
				$indexStatement->execute(array(':name' => $result['name']));
				$indexResult = $indexStatement->fetch(Pdo::FETCH_ASSOC);
				if (is_null($indexResult['sql'])) {
					// there are different ways for sqlite to store the primary keys its not always in the indexList
					// so we always get the Primary Key at the end
					continue;
				} else {
					$type = IndexType::UNIQUE;
				}
			}
	
			$indexColumns = array();
			$columnsSql = 'PRAGMA ' . $this->dbh->quoteField($this->database->getName()) 
					. '.index_info(' . $this->dbh->quoteField($result['name']) . ')';
			$columnsStatement = $this->dbh->prepare($columnsSql);
			$columnsStatement->execute();
			$columnsResults = $columnsStatement->fetchAll(Pdo::FETCH_ASSOC);
			foreach ($columnsResults as $columnResult) {
				$indexColumns[$columnResult['name']] = $columns[$columnResult['name']];
			}
			$index = new CommonIndex($table, $name, $type, $indexColumns);
			$index->setAttrs($result);
			$indexes[$name] = $index;
			
		}
		//get the primary key information informations
		$sql = 'PRAGMA ' . $this->dbh->quoteField($this->database->getName())
		. '.table_info(' . $this->dbh->quoteField($table->getName()) . ')';
		$statement = $this->dbh->prepare($sql);
		$statement->execute();
		$indexColumns = array();
		while (null != ($row = $statement->fetch(PDO::FETCH_ASSOC))) {
			if ($row['pk']) {
				$indexColumns[$row['name']] = $columns[$row['name']];
			}
		}
		if (count($indexColumns)) {
			$indexes[$table->generatePrimaryKeyName()] = new CommonIndex($table, $table->generatePrimaryKeyName(), IndexType::PRIMARY, $indexColumns);
		}	
		return $indexes;
	}
	
	/**
	 * Parse the given create statement and extract the query
	 * @param string $createStatement
	 */
	private function parseViewCreateStatement($createStatement) {
		$matches = preg_split('/AS/i', $createStatement);
		if (isset($matches[1])) {
			return trim($matches[1]);		
		}
		return $createStatement;
	}
}
