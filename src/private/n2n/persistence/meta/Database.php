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
namespace n2n\persistence\meta;

use n2n\persistence\meta\structure\MetaEntity;

interface Database {
	/**
	 * @return string
	 */
	public function getName();
	/**
	 * @return string
	 */
	public function getCharset();
	/**
	 * @return \n2n\persistence\meta\structure\MetaEntity[]
	 */
	public function getMetaEntities();
	/**
	 * @param array $metaEntities
	 */
	public function setMetaEntities(array $metaEntities);
	/**
	 * @param string $name
	 * @return MetaEntity
	 * @throws UnknownMetaEntityException
	 */
	public function getMetaEntityByName($name);
	/**
	 * @param MetaEntity $metaEntity
	 */
	public function addMetaEntity(MetaEntity $metaEntity);
	/**
	 * @param string $name
	 */
	public function removeMetaEntityByName($name);
	/**
	 * @param string $name
	 * @return bool
	 */
	public function containsMetaEntityName($name);
	/**
	 * @return array
	 */
	public function getAttrs();
	/**
	 * @return \n2n\persistence\meta\structure\MetaEntityFactory
	 */
	public function createMetaEntityFactory();
	/**
	 * All changes of tables, views and columns which belong to this database are saved. 
	 */
	public function flush();
	/**
	 * 
	 * @param array $metaEnties an array of MetaEntities or MetaEntity names.
	 * @return \n2n\persistence\meta\structure\Backuper
	 */
	public function createBackuper(array $metaEnties);
}
