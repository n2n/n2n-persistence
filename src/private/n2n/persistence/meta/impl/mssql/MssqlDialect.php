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
namespace n2n\persistence\meta\impl\mssql;

use n2n\io\InputStream;
use n2n\persistence\meta\data\common\CommonInsertStatementBuilder;
use n2n\core\SysTextUtils;
use n2n\persistence\meta\structure\InvalidColumnAttributesException;
use n2n\persistence\meta\structure\IntegerColumn;
use n2n\persistence\meta\data\common\CommonDeleteStatementBuilder;
use n2n\persistence\meta\data\common\CommonUpdateStatementBuilder;
use n2n\persistence\meta\data\common\CommonSelectStatementBuilder;
use n2n\persistence\meta\structure\Column;
use n2n\persistence\PersistenceUnitConfig;
use n2n\persistence\Pdo;
use n2n\persistence\meta\impl\DialectAdapter;

class MssqlDialect extends DialectAdapter {
	
	public function __construct() {
	}
	
	public function getName() {
		return 'Mssql';
	}
	
	public function initializeConnection(Pdo $dbh, PersistenceUnitConfig $dataSourceConfiguration) {
		//collation is set automatically
		$dbh->exec('SET TRANSACTION ISOLATION LEVEL ' . $dataSourceConfiguration->getTransactionIsolationLevel());
	}
	
	public function createMetaDatabase(Pdo $dbh) {
		return new MssqlDatabase($dbh);
	}
	
	public function quoteField($str) {
		return "[" . str_replace("]", "]]", (string) $str) . "]";
	}
	
	public function escapeLikePattern($pattern) {
		return $pattern;
	}
	
	public function createSelectStatementBuilder(Pdo $dbh) {
		return new CommonSelectStatementBuilder($dbh, new MssqlQueryFragmentBuilderFactory($dbh));
	}
	
	public function createUpdateStatementBuilder(Pdo $dbh) {
		return new CommonUpdateStatementBuilder($dbh, new MssqlQueryFragmentBuilderFactory($dbh));
	}
	
	public function createInsertStatementBuilder(Pdo $dbh) {
		return new CommonInsertStatementBuilder($dbh, new MssqlQueryFragmentBuilderFactory($dbh));
	}
	
	public function createDeleteStatementBuilder(Pdo $dbh) {
		return new CommonDeleteStatementBuilder($dbh, new MssqlQueryFragmentBuilderFactory($dbh));
	}
	
	public function getOrmDialectConfig() {
		return new MssqlOrmDialectConfig();
	}
	
	public function isLastInsertIdSupported() {
		return true;
	}
	
	public function generateSequenceValue(Pdo $dbh, $sequenceName) {
		return null;
	}

	public function applyIdentifierGeneratorToColumn(Pdo $dbh, Column $column, $sequenceName = null) {
		if (!($column instanceof IntegerColumn)) {
			throw new InvalidColumnAttributesException(SysTextUtils::get('n2n_error_persistance_invalid_generated_identifier',
							array('required_column_type' => 'n2n\persistence\meta\impl\sqlite\IntegerColumn', 'given_column_type' => get_class($column))));
		}
		//this triggers a changerequest -> type will be changed to INTEGER
		$column->setGeneratedIdentifier(true);
		$column->setNullAllowed(false);
		return $column;
	}
	
	public function isColumnIdentifierGenerator($column) {
		return ($column instanceof MssqlIntegerColumn && $column->isGeneratedIdentifier());
	}
	
	public function createImporter(Pdo $dbh, InputStream $inputStream) {
		return new MssqlImporter($dbh, $inputStream);
	}
}
