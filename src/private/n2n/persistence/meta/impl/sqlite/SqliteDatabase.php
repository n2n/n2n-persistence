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

use n2n\persistence\meta\impl\sqlite\management\SqliteDropMetaEntityRequest;

use n2n\persistence\meta\impl\sqlite\management\SqliteCreateMetaEntityRequest;

use n2n\persistence\meta\impl\sqlite\management\SqliteAlterMetaEntityRequest;

use n2n\persistence\meta\structure\common\DatabaseAdapter;

use n2n\persistence\meta\structure\MetaEntity;

use n2n\persistence\Pdo;

class SqliteDatabase extends DatabaseAdapter {

	
	const RESERVED_NAME_PREFIX = 'sqlite_';
	
	/**
	 * @var SqliteMetaEntityBuilder
	 */
	private $metaEntityBuilder;
	
	/**
	 * @var SqliteMetaEntityFactory
	 */
	private $metaEntityFactory;
	
	private $charset;
	
	private $attrs;
	
	public function __construct(Pdo $dbh) {
		parent::__construct($dbh);
		$this->metaEntityBuilder = new SqliteMetaEntityBuilder($dbh, $this);
		$this->metaEntityFactory = new SqliteMetaEntityFactory($this);
	}
	
	public function getName() {		
		return 'main';
	} 
	
	public function getCharset() {
		if (!($this->charset)) {
			$sql = 'pragma ' . $this->dbh->quoteField($this->getName()) . '.encoding';
			$statement = $this->dbh->prepare($sql);
			$statement->execute();
			$result = $statement->fetch(Pdo::FETCH_ASSOC);
			$this->charset = $result['encoding'];
		}
		
		return $this->charset;
	}
	
	public function getAttrs() {
		if (!($this->attrs)) {
			$sql = 'SHOW VARIABLES';
			$statement = $this->dbh->prepare($sql);
			$statement->execute(array(':TABLE_SCHEMA' => $this->getName()));
			$results = $statement->fetchAll(Pdo::FETCH_ASSOC);
			$this->attrs = $results;
		}
		return $this->attrs;
	}
	
	/* (non-PHPdoc)
	 * @see n2n\persistence\meta\structure\column.DatabaseAdapter::createMetaEntityFactory()
	 */
	public function createMetaEntityFactory() {
		return $this->metaEntityFactory;
	}
	
	public function createAlterMetaEntityRequest(MetaEntity $metaEntity) {
		return new SqliteAlterMetaEntityRequest($metaEntity);
	}
	
	public function createCreateMetaEntityRequest(MetaEntity $metaEntity) {
		return new SqliteCreateMetaEntityRequest($metaEntity);
	}
	
	public function createDropMetaEntityRequest(MetaEntity $metaEntity) {
		return new SqliteDropMetaEntityRequest($metaEntity);
	}
	
	/**
	* @return Backuper
	*/
	
	public function createBackuper(array $metaEnities = null) {
		return new SqliteBackuper($this->dbh, $this, $metaEnities);
	}
	
	protected function getPersistedMetaEntities() {
		$metaEntities = array();
		$sql = 'SELECT * FROM ' . $this->dbh->quoteField($this->getName()) . '.sqlite_master WHERE type in (:type_table, :type_view) AND  '
				. $this->dbh->quoteField('name') . 'NOT LIKE :reserved_names';
		$statement = $this->dbh->prepare($sql);
		$statement->execute(
				array(':type_table' => SqliteMetaEntityBuilder::TYPE_TABLE, 
						':type_view' => SqliteMetaEntityBuilder::TYPE_VIEW,
						':reserved_names' => self::RESERVED_NAME_PREFIX . '%'));
		while (null != ($result =  $statement->fetch(Pdo::FETCH_ASSOC))) {
			$metaEntities[$result['name']] = $this->metaEntityBuilder->createMetaEntity($result['name']);
		}
		return $metaEntities;
	}
}
