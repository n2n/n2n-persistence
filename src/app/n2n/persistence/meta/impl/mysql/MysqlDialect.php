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
namespace n2n\persistence\meta\impl\mysql;

use n2n\io\InputStream;
use n2n\persistence\meta\data\common\CommonInsertStatementBuilder;
use n2n\persistence\meta\data\common\CommonDeleteStatementBuilder;
use n2n\persistence\meta\data\common\CommonUpdateStatementBuilder;
use n2n\persistence\meta\data\common\CommonSelectStatementBuilder;
use n2n\persistence\meta\structure\InvalidColumnAttributesException;
use n2n\persistence\meta\structure\IntegerColumn;
use n2n\persistence\meta\structure\Column;
use n2n\core\N2N;
use n2n\persistence\Pdo;
use n2n\persistence\meta\impl\DialectAdapter;
use n2n\core\SysTextUtils;
use n2n\persistence\PersistenceUnitConfig;

class MysqlDialect extends DialectAdapter {
	/* (non-PHPdoc)
	 * @see \n2n\persistence\meta\Dialect::__construct()
	 */
	public function __construct() {}
	
	public function getName() {
		return 'Mysql';
	}
	
	public function initializeConnection(Pdo $dbh, PersistenceUnitConfig $dataSourceConfiguration) {
		$dbh->exec('SET NAMES ' . $dbh->quote(N2N::CHARSET_MIN)); 
		$dbh->exec('SET SESSION TRANSACTION ISOLATION LEVEL ' . $dataSourceConfiguration->getTransactionIsolationLevel());
		$dbh->exec('SET SESSION sql_mode = \'STRICT_ALL_TABLES\'');
	}
	
	public function createMetaDatabase(Pdo $dbh) {
		return new MysqlDatabase($dbh);
	}
	/**
	 *
	 * @param string $str
	 */
	public function quoteField($str) {
		return "`" . str_replace("`", "``", (string) $str) . "`";
	}
	/**
	 *
	 * @return SelectStatementBuilder
	 */
	public function createSelectStatementBuilder(Pdo $dbh) {
		return new CommonSelectStatementBuilder($dbh, new MysqlQueryFragmentBuilderFactory($dbh));
	}
	
	public function createUpdateStatementBuilder(Pdo $dbh) {
		return new CommonUpdateStatementBuilder($dbh, new MysqlQueryFragmentBuilderFactory($dbh));
	}
	
	public function createInsertStatementBuilder(Pdo $dbh) {
		return new CommonInsertStatementBuilder($dbh, new MysqlQueryFragmentBuilderFactory($dbh));
	}
	
	public function createDeleteStatementBuilder(Pdo $dbh) {
		return new CommonDeleteStatementBuilder($dbh, new MysqlQueryFragmentBuilderFactory($dbh));
	}
	
	public function getOrmDialectConfig() {
		return new MysqlOrmDialectConfig();
	}

	public function isLastInsertIdSupported() {
		return true;
	}
	
	public function generateSequenceValue(Pdo $dbh, $sequenceName) {
		return null;
	}
	
	public function applyIdentifierGeneratorToColumn(Pdo $dbh, Column $column, $sequenceName = null) {
		if (!($column instanceof IntegerColumn)) {
			throw new InvalidColumnAttributesException('Invalid generated identifier column \"' . $column->getName() 
					. '\" for Table \"' . $column->getTable()->getName() 
					. '\". Column must be of type \"' . IntegerColumn::class . "\". Given column type is \"" . get_class($column) . "\"");
		}
		//the Value automatically gets Generated Identifier if the column type is Integer
		//this triggers a changerequest -> type will be changed to INTEGER
		$column->setNullAllowed(false);
		$column->setValueGenerated(true);
		return $column;
	}
	
	public function createImporter(Pdo $dbh, InputStream $inputStream) {
		return new MysqlImporter($dbh, $inputStream);
	}

}
