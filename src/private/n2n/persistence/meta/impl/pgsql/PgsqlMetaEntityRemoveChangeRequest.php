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

use n2n\persistence\Pdo;
use n2n\persistence\meta\structure\common\ChangeRequest;

class PgsqlMetaEntityRemoveChangeRequest implements ChangeRequest {
	private $metaEntity;

	public function __construct(PgsqlMetaEntity $metaEntity) {
		$this->metaEntity = $metaEntity;
	}

	public function getMetaEntity() {
		return $this->metaEntity;
	}

	public function execute(Pdo $dbh) {
		if ($this->metaEntity instanceof PgsqlTable) {
			$sql = 'DROP TABLE IF EXISTS ' . $dbh->quoteField('users') . ';';
		} elseif ($this->metaEntity instanceof PgsqlView) {
			$sql = 'DROP VIEW IF EXISTS ' . $dbh->quoteField('users') . ';';
		}
		$stmt = $dbh->prepare($sql);
		$stmt->execute();
	}
}
