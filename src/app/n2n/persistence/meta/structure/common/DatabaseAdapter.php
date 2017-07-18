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
namespace n2n\persistence\meta\structure\common;

use n2n\persistence\meta\structure\common\CreateMetaEntityRequest;

use n2n\persistence\meta\structure\common\AlterMetaEntityRequest;

use n2n\persistence\meta\structure\DuplicateMetaElementException;

use n2n\persistence\meta\structure\common\MetaEntityChangeListener;

use n2n\core\SysTextUtils;

use n2n\persistence\meta\structure\MetaEntity;

use n2n\persistence\meta\structure\UnknownMetaEntityException;

use n2n\persistence\Pdo;

use n2n\persistence\meta\Database;

abstract class DatabaseAdapter implements Database, MetaEntityChangeListener {

	/**
	 * @var n2n\persistence\Pdo
	 */
	protected $dbh;

	/**
	 * @var n2n\persistence\meta\structure\common\ChangeRequestQueue
	 */
	private $changeRequestQueue;

	private $metaEntities;
	
	public function __construct(Pdo $dbh) {
		$this->dbh = $dbh;
		$this->changeRequestQueue = new ChangeRequestQueue();
	}

	public function getMetaEntities() {
		if (!isset($this->metaEntities)) {
			$this->metaEntities = $this->getPersistedMetaEntities();
		}
		return $this->metaEntities;
	}

	public function getMetaEntityByName($name) {
		$metaEntities = $this->getMetaEntities();
		if (!(isset($metaEntities[$name]))) {
			throw new UnknownMetaEntityException('Metaentity "' . $name . '" does not exist in Database "' . '" ' . $this->getName());
		}
		return $metaEntities[$name];
	}

	public function containsMetaEntityName($name) {
		try {
			$this->getMetaEntityByName($name);
			return true;
		} catch (UnknownMetaEntityException $e) {
			return false;
		}
	}

	public function setMetaEntities(array $metaEntities) {
		//Find out the metaEntities which need to be deleted
		foreach ($this->metaEntities as $metaEntity) {
			if (!(in_array($metaEntity, $metaEntities))) {
				$this->removeMetaEntity(persistedMetaEntity);
			}
		}

		//Find out the ones who have to be added
		foreach ($metaEntities as $metaEntity) {
			if (!(in_array($metaEntity, $this->metaEntities))) {
				$this->addMetaEntity($metaEntity);
			}
		}
	}

	public function removeMetaEntityByName($name) {
		$this->removeMetaEntity($this->getMetaEntityByName($name));
	}

	public function addMetaEntity(MetaEntity $metaEntity) {
		if (!($this->containsMetaEntityName($metaEntity->getName()))) {
			$this->changeRequestQueue->add($this->createCreateMetaEntityRequest($metaEntity));
			$metaEntity->registerChangeListener($this);
			$metaEntity->setDatabase($this);
			$this->metaEntities[$metaEntity->getName()] = $metaEntity;
		} else {
			if (!($metaEntity->equals($this->getMetaEntityByName($metaEntity->getName())))) {
				throw new DuplicateMetaElementException(
						SysTextUtils::get('n2n_error_persistence_meta_duplicate_meta_entity',
								array('database_name' => $this->getName(), 'table_name' => $metaEntity->getName())));
			}
		}
	}

	public function flush() {
		$this->changeRequestQueue->persist($this->dbh);
	}

	public function onMetaEntityChange(MetaEntity $metaEntity) {
		$changeRequests = $this->changeRequestQueue->getAll();

		//check if alter request already exists for this entity
		foreach ($changeRequests as $changeRequest) {
			if ((($changeRequest instanceof AlterMetaEntityRequest) ||
				($changeRequest instanceof CreateMetaEntityRequest))
			&& ($changeRequest->getMetaEntity() === $metaEntity)) {
				return;
			}
		}

		$this->changeRequestQueue->add($this->createAlterMetaEntityRequest($metaEntity));
	}

	private function removeMetaEntity(MetaEntity $metaEntity) {
		//check if alter request already exists for this entity
		foreach ($this->changeRequestQueue->getAll() as $changeRequest) {
			if ((($changeRequest instanceof CreateMetaEntityRequest))
					&& ($changeRequest->getMetaEntity() === $metaEntity)) {
				$this->changeRequestQueue->remove($changeRequest);
				return;
			}
		}


		$this->changeRequestQueue->add($this->createDropMetaEntityRequest($metaEntity));
		$metaEntity->unregisterChangeListener($this);
		unset($this->metaEntities[$metaEntity->getName()]);
	}
	
	/**
	 * Get all of the persisted MetaEntities of the curren Database
	 */
	protected abstract function getPersistedMetaEntities();
}
