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

use n2n\persistence\meta\impl\oracle\management\OracleDropMetaEntityRequest;

use n2n\persistence\meta\impl\oracle\management\OracleCreateMetaEntityRequest;

use n2n\persistence\meta\impl\oracle\management\OracleAlterMetaEntityRequest;

use n2n\persistence\meta\structure\MetaEntity;

use n2n\persistence\Pdo;

use n2n\persistence\meta\structure\common\DatabaseAdapter;

class OracleDatabase extends DatabaseAdapter {
	
	/**
	 * @var n2n\persistence\meta\impl\oracle\OracleMetaEntityFactory
	 */
	private $metaEntityFactory;
	
	private $charset;
	private $name;
	private $attrs;
	
	public function __construct(Pdo $dbh) {
		parent::__construct($dbh);
		$this->metaEntityBuilder = new OracleMetaEntityBuilder($dbh, $this);
	}
	
	public function getName() {
		if (!($this->name)) {
			$sql = 'SELECT SYS_CONTEXT(\'userenv\',\'instance_name\') AS NAME FROM DUAL';
			$statement = $this->dbh->prepare($sql);
			$statement->execute();
			$result = $statement->fetch(Pdo::FETCH_ASSOC);
			$this->name = $result['NAME'];
		}		
		return $this->name;
	} 
	
	public function getCharset() {
		if (!($this->charset)) {
			$sql = 'SELECT * FROM NLS_DATABASE_PARAMETERS  WHERE PARAMETER = \'NLS_CHARACTERSET\'';
			$statement = $this->dbh->prepare($sql);
			$statement->execute();
			$result = $statement->fetch(Pdo::FETCH_ASSOC);
			$this->charset = $result['VALUE'];
		}
		return $this->charset;
	}
	
	public function getAttrs() {
		if (!($this->attrs)) {
			$sql = 'SELECT * FROM product_component_version';
			$statement = $this->dbh->prepare($sql);
			$statement->execute(array(':TABLE_SCHEMA' => $this->getName()));
			$results = $statement->fetchAll(Pdo::FETCH_ASSOC);
			$this->attrs = $results;
		}
		return $this->attrs;
	}

	/**
	 * @return n2n\persistence\meta\impl\oracle\OracleMetaEntityFactory
	 */
	public function createMetaEntityFactory() {
		if (!(isset($this->metaEntityFactory))) {
			$this->metaEntityFactory = new OracleMetaEntityFactory($this);
		}
		return $this->metaEntityFactory;
	}
	
	public function createAlterMetaEntityRequest(MetaEntity $metaEntity) {
		return new OracleAlterMetaEntityRequest($metaEntity);
	}
	
	public function createCreateMetaEntityRequest(MetaEntity $metaEntity) {
		return new OracleCreateMetaEntityRequest($metaEntity);
	}
	
	public function createDropMetaEntityRequest(MetaEntity $metaEntity) {
		return new OracleDropMetaEntityRequest($metaEntity);
	}
	
	
	/**
	* @return n2n\persistence\meta\impl\oracle\Backuper
	*/
	
	public function createBackuper(array $metaEnities = null) {
		return new OracleBackuper($this->dbh, $this, $metaEnities);
	}
	
	protected function getPersistedMetaEntities() {
		$metaEntities = array();
		//First check for tables
		$statement = $this->dbh->prepare('SELECT * FROM user_tables WHERE tablespace_name = :users');
		$statement->execute(array(':users' => 'USERS'));
		
		while (null != ($result =  $statement->fetch(Pdo::FETCH_ASSOC))) {
			$metaEntities[$result['TABLE_NAME']] = $this->metaEntityBuilder->createTable($result['TABLE_NAME']);
		}
		
		//Then for tables
		$statement = $this->dbh->prepare('SELECT * FROM user_views');
		$statement->execute();
		
		while (null != ($result = $statement->fetch(Pdo::FETCH_ASSOC))) {
			$metaEntities[$result['VIEW_NAME']] = $this->metaEntityBuilder->createView($result['VIEW_NAME']);
		}
		return $metaEntities;
	}
	
}
