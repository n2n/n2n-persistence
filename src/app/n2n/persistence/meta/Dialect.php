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
 * Bert Hofmänner.......: Idea, Frontend UI, Community Leader, Marketing
 * Thomas Günther.......: Developer, Hangar
 */
namespace n2n\persistence\meta;

use n2n\io\InputStream;
use n2n\persistence\meta\structure\Column;
use n2n\persistence\PersistenceUnitConfig;
use n2n\persistence\Pdo;

interface Dialect {
	const DEFAULT_ESCAPING_CHARACTER = '\\';
	/**
	 * 
	 */
	public function __construct();
	/**
	 * @return string
	 */
	public function getName();
	/**
	 * @param Pdo $dbh
	 * @param \n2n\persistence\DataSourceConfiguration $data
	 */
	public function initializeConnection(Pdo $dbh, PersistenceUnitConfig $data);
	/**
	 * @param Pdo $dbh
	 * @return \n2n\persistence\meta\Database
	 */
	public function createMetaDatabase(Pdo $dbh);
	/**
	 * @param string $str
	 */
	public function quoteField($str);
	/**
	 * Quotes the like wildcard chars
	 * @param unknown $pattern
	 */
	public function escapeLikePattern($pattern);
	/**
	 * Returns the escape character used in {@link Dialect::escapeLikePattern()}. 
	 * @return string
	 */
	public function getLikeEscapeCharacter();
	/**
	 * @param Pdo $dbh
	 * @return \n2n\persistence\meta\data\SelectStatementBuilder
	 */
	public function createSelectStatementBuilder(Pdo $dbh);
	/**
	 * @param Pdo $dbh
	 * @return \n2n\persistence\meta\data\UpdateStatementBuilder
	 */
	public function createUpdateStatementBuilder(Pdo $dbh);
	/**
	 * @param Pdo $dbh
	 * @return \n2n\persistence\meta\data\InsertStatementBuilder
	 */
	public function createInsertStatementBuilder(Pdo $dbh);
	/**
	 * 
	 * @param Pdo $dbh
	 * @return \n2n\persistence\meta\data\DeleteStatementBuilder
	 */
	public function createDeleteStatementBuilder(Pdo $dbh);
	/**
	 * @param Pdo $dbh
	 * @param InputStream $inputStream
	 * @return \n2n\persistence\meta\data\Importer
	 */
	public function createImporter(Pdo $dbh, InputStream $inputStream);
	/**
	 * @return \n2n\persistence\meta\OrmDialectConfig
	 */
	public function getOrmDialectConfig();
	/**
	 * @return bool
	 */
	public function isLastInsertIdSupported();
	/**
	 * @param string $sequenceName
	 * @return mixed
	 */
	public function generateSequenceValue(Pdo $dbh, $sequenceName);
	/**
	 * @param Column $column
	 */
	public function applyIdentifierGeneratorToColumn(Pdo $dbh, Column $column, $sequenceName);
}
