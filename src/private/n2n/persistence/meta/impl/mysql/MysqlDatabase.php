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

use n2n\persistence\meta\impl\mysql\management\MysqlDropMetaEntityRequest;

use n2n\persistence\meta\impl\mysql\management\MysqlCreateMetaEntityRequest;

use n2n\persistence\meta\impl\mysql\management\MysqlAlterMetaEntityRequest;

use n2n\persistence\meta\structure\MetaEntity;

use n2n\persistence\meta\structure\common\DatabaseAdapter;

use n2n\persistence\Pdo;

use n2n\persistence\meta\impl\mysql\MysqlMetaEntityBuilder;

class MysqlDatabase extends DatabaseAdapter {
	
	/**
	 * @var n2n\persistence\meta\impl\mysql\MetaEntityFactory
	 */
	private $metaEntityFactory;
	
	/**
	 * @var n2n\persistence\meta\impl\mysql\MysqlMetaEntityBuilder
	 */
	private $metaEntityBuilder;
	
	private $name;
	private $charset;
	private $attrs;
	
	public function __construct(Pdo $dbh) {
		parent::__construct($dbh);
		$this->metaEntityBuilder = new MysqlMetaEntityBuilder($dbh, $this);
	}
	
	public function getName() {
		if (!($this->name)) {
			$sql = 'SELECT DATABASE() as name;';
			$statement = $this->dbh->prepare($sql);
			$statement->execute();
			$result = $statement->fetch(Pdo::FETCH_ASSOC);
			$this->name = $result['name'];
		}		
		return $this->name;
	} 
	
	public function getCharset() {
		if (!($this->charset)) {
			$sql = 'SHOW VARIABLES LIKE "character_set_database"';
			$statement = $this->dbh->prepare($sql);
			$statement->execute();
			$result = $statement->fetch(Pdo::FETCH_ASSOC);
			$this->charset = $result['Value'];
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

	/**
	 * @return n2n\persistence\meta\impl\mysql\MysqlMetaEntityFactory
	 */
	public function createMetaEntityFactory() {
		if (!(isset($this->metaEntityFactory))) {
			$this->metaEntityFactory = new MysqlMetaEntityFactory($this);
		}
		return $this->metaEntityFactory;
	}
	
	public function createAlterMetaEntityRequest(MetaEntity $metaEntity) {
		return new MysqlAlterMetaEntityRequest($metaEntity);
	}
	
	public function createCreateMetaEntityRequest(MetaEntity $metaEntity) {
		return new MysqlCreateMetaEntityRequest($metaEntity);
	}
	
	public function createDropMetaEntityRequest(MetaEntity $metaEntity) {
		return new MysqlDropMetaEntityRequest($metaEntity);
	}
	
	
	/**
	* @return n2n\persistence\meta\impl\mysql\Backuper
	*/
	
	public function createBackuper(array $metaEnities = null) {
		return new MysqlBackuper($this->dbh, $this, $metaEnities);
	}
	
	protected function getPersistedMetaEntities() {
		$metaEntities = array();
		$sql = 'SELECT * FROM information_schema.TABLES WHERE TABLE_SCHEMA = :TABLE_SCHEMA;';
		$statement = $this->dbh->prepare($sql);
		$statement->execute(array(':TABLE_SCHEMA' => $this->getName()));
		while (null != ($result =  $statement->fetch(Pdo::FETCH_ASSOC))) {
			$metaEntities[$result['TABLE_NAME']] = $this->metaEntityBuilder->createMetaEntity($result['TABLE_NAME']);
		}
		return $metaEntities;
	}
}
