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

use n2n\persistence\meta\structure\common\DatabaseAdapter;

use n2n\persistence\meta\structure\MetaEntity;

use n2n\persistence\meta\impl\mssql\management\MssqlDropMetaEntityRequest;

use n2n\persistence\meta\impl\mssql\management\MssqlCreateMetaEntityRequest;

use n2n\persistence\meta\impl\mssql\management\MssqlAlterMetaEntityRequest;

use n2n\persistence\Pdo;

class MssqlDatabase extends DatabaseAdapter {

	private $name;
	private $charset;
	private $attrs;
	
	/**
	 * @var  n2n\persistence\meta\impl\mssql\MssqlMetaEntityFactory
	 */
	private $metaEntityFactory;
	
	/**
	 * @var  n2n\persistence\meta\impl\mssql\MssqlMetaEntityBuilder
	 */
	private $metaEntityBuilder;
	
	public function __construct(Pdo $dbh) {
		parent::__construct($dbh);
		$this->metaEntityBuilder = new MssqlMetaEntityBuilder($dbh, $this);
	}
	
	public function getName() {
		if (!$this->name) {
			$sql = 'SELECT DB_NAME() as database_name';
			$statement = $this->dbh->prepare($sql);
			$statement->execute();
			$result = $statement->fetch(Pdo::FETCH_ASSOC);
			$this->name = $result['database_name'];
		}
		return $this->name;
	}
	
	public function getCharset() {
		if (!$this->charset) {
			$sql = 'SELECT collation_name FROM sys.' . $this->dbh->quoteField('databases') . ' WHERE name = :name';
			$statement = $this->dbh->prepare($sql);
			$statement->execute(array(':name' => $this->getName()));
			$result = $statement->fetch(Pdo::FETCH_ASSOC);
			$this->charset = $result['collation_name'];
		}
		return $this->charset;
	}
	
	public function getAttrs() {
		if (!$this->attrs) {
			$sql = 'SELECT * from sys.' . $this->dbh->quoteField('databases') . ' where name = :name';
			$statement = $this->dbh->prepare($sql);
			$statement->execute(array(':name' => $this->getName()));
			$result = $statement->fetch(Pdo::FETCH_ASSOC);
			$this->attrs = $result;
		}
		return $this->attrs;
	}

	public function createMetaEntityFactory() {
		if (!$this->metaEntityFactory) {
			$this->metaEntityFactory = new MssqlMetaEntityFactory($this);
		}
		return $this->metaEntityFactory;
	}
	
	public function createAlterMetaEntityRequest(MetaEntity $metaEntity) {
		return new MssqlAlterMetaEntityRequest($metaEntity);
	}
	
	public function createCreateMetaEntityRequest(MetaEntity $metaEntity) {
		return new MssqlCreateMetaEntityRequest($metaEntity);
	}
	
	public function createDropMetaEntityRequest(MetaEntity $metaEntity) {
		return new MssqlDropMetaEntityRequest($metaEntity);
	}
	
	
	public function createBackuper(array $metaEntities = null) {
		return new MssqlBackuper($this->dbh, $this, $metaEntities);
	}

	protected function getPersistedMetaEntities() {
		$metaEntities = array();
		$sql = 'SELECT * FROM information_schema.' . $this->dbh->quoteField('TABLES') . ' WHERE TABLE_CATALOG = :TABLE_CATALOG;';
		$statement = $this->dbh->prepare($sql);
		$statement->execute(array(':TABLE_CATALOG' => $this->getName()));
		while (null != ($result = $statement->fetch(Pdo::FETCH_ASSOC))) {
			$metaEntities[$result['TABLE_NAME']] = $this->metaEntityBuilder->createMetaEntity($result['TABLE_NAME']);
		}
		return $metaEntities;
	}
}
