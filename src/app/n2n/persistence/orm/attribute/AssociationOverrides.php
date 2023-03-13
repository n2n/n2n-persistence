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
namespace n2n\persistence\orm\attribute;

use Attribute;
use n2n\util\type\ArgUtils;

#[Attribute(Attribute::TARGET_CLASS | Attribute::TARGET_PROPERTY)]
class AssociationOverrides {

	/**
	 * @param JoinColumn[] $joinColumnsMap
	 * @param JoinTable[] $joinTablesMap
	 */
	public function __construct(private array $joinColumnsMap = [], private array $joinTablesMap = []) {
		ArgUtils::valArray($this->joinColumnsMap, JoinColumn::class);
		ArgUtils::valArray($this->joinTablesMap, JoinTable::class);
	}

	/**
	 * @return JoinColumn[]
	 * @deprecated
	 */
	public function getJoinColumns() {
		return $this->joinColumnsMap;
	}

	/**
	 * @return JoinTable[]
	 * @deprecated
	 */
	public function getJoinTables() {
		return $this->joinTablesMap;
	}

	/**
	 * @return JoinColumn[]
	 */
	public function getJoinColumnsMap(): array {
		return $this->joinColumnsMap;
	}

	/**
	 * @return JoinTable[]
	 */
	public function getJoinTablesMap(): array {
		return $this->joinTablesMap;
	}


}
