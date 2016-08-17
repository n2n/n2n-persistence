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
namespace n2n\persistence\meta\impl\pgsql;

use n2n\persistence\meta\structure\MetaEntityFactory;

class PgsqlMetaEntityFactory implements MetaEntityFactory {
	private $database;

	public function __construct(PgsqlDatabase $database) {
		$this->database = $database;
	}

	public function getDatabase() {
		return $this->database;
	}

	public function createTable($name) {
		$newTable = new PgsqlTable($name);
		$this->database->addMetaEntity($newTable);
		return $newTable;
	}

	public function createView($name, $query) {
		$newView = new PgsqlView($name, $query);
		$this->database->addMetaEntity($newView);
		return $newView;
	}
}
