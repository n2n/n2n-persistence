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
namespace n2n\persistence\meta\structure\common;

use n2n\persistence\meta\structure\Table;

use n2n\persistence\meta\structure\View;

use n2n\persistence\meta\structure\MetaEntity;

use n2n\persistence\meta\Database;

abstract class MetaEntityAdapter implements MetaEntity {
	private $name;
	/**
	 * @var Database
	 */
	private $database;
	private $attrs;

	/**
	 * @var <MetaEntityChangeListener>
	 */
	private $changeListeners;
	
	public function __construct($name) {
		$this->name = $name;
		$this->changeListeners = array();
		$this->attrs = array();
	}
	
	public function getName() {
		return $this->name;
	}
	
	public function setName($name) {
		if ($name === $this->name) return; 
		$this->getDatabase()->removeMetaEntityByName($this->name);
		if ($this instanceof View) {
			$this->getDatabase()->createMetaEntityFactory()->createView($name, $this->getQuery());
		} elseif ($this instanceof Table) {
			$this->getDatabase()->addMetaEntity($this->copy($name));
		}
	}
	/** 
	 * @return n2n\persistence\meta\Database
	 */
	public function getDatabase() {
		return $this->database;
	}
	
	public function setDatabase(Database $database) {
		$this->database = $database;
	}
	
	public function getAttrs() {
		return $this->attrs;
	}
	
	public function setAttrs(array $attrs) {
		$this->attrs = $attrs;
	} 
	
	public function registerChangeListener(MetaEntityChangeListener $changeListener) {
		$this->changeListeners[spl_object_hash($changeListener)] = $changeListener;
	}
	
	public function unregisterChangeListener(MetaEntityChangeListener $changeListener) {
		unset($this->changeListeners[spl_object_hash($changeListener)]);
	}
	
	public function equals(MetaEntity $metaEntity) {
		return ($metaEntity->getName() === $this->getName())
				&& (get_class($metaEntity) === get_class($this));
	}
	
	protected function triggerChangeListeners() {
		foreach($this->changeListeners as $changeListener) {
			$changeListener->onMetaEntityChange($this);
		}
	}
}
