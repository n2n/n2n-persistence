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
namespace n2n\persistence\orm\query\from;

use n2n\spec\dbo\meta\data\SelectStatementBuilder;
use n2n\persistence\orm\query\QueryPoint;
use n2n\persistence\orm\OrmException;

interface TreePoint extends QueryPoint {
	/**
	 * @param SelectStatementBuilder $selectBuilder
	 */
	public function apply(SelectStatementBuilder $selectBuilder);

	function getJoinType(): ?string;

	/**
	 * @param string $fetchType
	 * @return JoinedTreePoint
	 * @throws OrmException
	 */
	public function createPropertyJoinedTreePoint(string $propertyName, $joinType): JoinedTreePoint;
	/**
	 * @param string $propertyName
	 * @param bool $innerJoinRequired
	 * @return JoinedTreePoint
	 * @throws OrmException
	 */
	public function requestPropertyJoinedTreePoint(string $propertyName, bool $innerJoinRequired): JoinedTreePoint;
}
