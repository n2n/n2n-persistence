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
namespace n2n\persistence\meta\impl\oracle;

use n2n\io\InputStream;
use n2n\core\SysTextUtils;
use n2n\core\N2N;
use n2n\persistence\meta\data\common\CommonDeleteStatementBuilder;
use n2n\persistence\meta\data\common\CommonInsertStatementBuilder;
use n2n\persistence\meta\data\common\CommonUpdateStatementBuilder;
use n2n\persistence\meta\data\common\CommonSelectStatementBuilder;
use n2n\persistence\meta\structure\Column;
use n2n\persistence\Pdo;
use n2n\persistence\meta\impl\DialectAdapter;
use n2n\persistence\PersistenceUnitConfig;

class OracleDialect extends DialectAdapter {
	
	public function __construct() {}
	
	public function getName() {
		return 'Oracle';
	}
	
	public function initializeConnection(Pdo $dbh, PersistenceUnitConfig $dataSourceConfiguration) {
		$dbh->exec('SET TRANSACTION ISOLATION LEVEL ' . $dataSourceConfiguration->getTransactionIsolationLevel());
		$dbh->exec('ALTER SESSION SET NLS_TIMESTAMP_FORMAT = ' . $dbh->quote('YYYY-MM-DD HH:MI:SS.FF'));
		$dbh->exec('ALTER SESSION SET NLS_DATE_FORMAT = ' . $dbh->quote('YYYY-MM-DD'));
		$dbh->exec('ALTER SESSION SET NLS_TIMESTAMP_TZ_FORMAT = ' . $dbh->quote('YYYY-MM-DD HH:MI:SS.FF TZH:TZM'));
	}
	
	public function createMetaDatabase(Pdo $dbh) {
		return new OracleDatabase($dbh);
	}
	/**
	 *
	 * @param string $str
	 */
	public function quoteField($str) {
		return '"' . str_replace('"', '""', (string) $str) . '"';
		return $str;
	}
	/**
	 *
	 * @return SelectStatementBuilder
	 */
	public function createSelectStatementBuilder(Pdo $dbh) {
		return new CommonSelectStatementBuilder($dbh, new OracleQueryFragmentBuilderFactory($dbh));
	}
	
	public function createUpdateStatementBuilder(Pdo $dbh) {
		return new CommonUpdateStatementBuilder($dbh, new OracleQueryFragmentBuilderFactory($dbh));
	}
	
	public function createInsertStatementBuilder(Pdo $dbh) {
		return new CommonInsertStatementBuilder($dbh, new OracleQueryFragmentBuilderFactory($dbh));
	}
	
	public function createDeleteStatementBuilder(Pdo $dbh) {
		return new CommonDeleteStatementBuilder($dbh, new OracleQueryFragmentBuilderFactory($dbh));
	}
	
	public function getOrmDialectConfig() {
		return new OracleOrmDialectConfig();
	}

	public function isLastInsertIdSupported() {
		return false;
	}
	
	public function generateSequenceValue(Pdo $dbh, $sequenceName) {
		$statement = $dbh->prepare('SELECT ' . $dbh->quoteField($sequenceName) . '.NEXTVAL AS NEXT_INSERT_ID FROM DUAL');
		$statement->execute();
		if (null != ($result = $statement->fetch(Pdo::FETCH_ASSOC))) {
			return $result['NEXT_INSERT_ID'];
		}
		throw new \InvalidArgumentException(SysTextUtils::get('n2n_error_persistence_meta_oracle_sequence_name_invalid', array('sequence_name' => $sequenceName)));
	}
	
	public function applyIdentifierGeneratorToColumn(Pdo $dbh, Column $column, $sequenceName) {
		$dbh = N2N::getPdoPool()->getPdo();
		$statement = $dbh->prepare('SELECT COUNT(SEQUENCE_NAME) as NUM_SEQUENCES FROM USER_SEQUENCES WHERE SEQUENCE_NAME = :SEQUENCE_NAME');
		$statement->execute(array(':SEQUENCE_NAME' => $sequenceName));
		$result = $statement->fetch(Pdo::FETCH_ASSOC);
		if(intval($result['NUM_SEQUENCES']) == 0) {
			$dbh->exec('CREATE SEQUENCE ' . $dbh->quoteField($sequenceName));
		}
	}
	
	
	public function createImporter(Pdo $dbh, InputStream $inputStream) {
		return new OracleImporter($dbh, $inputStream);
	}
}
