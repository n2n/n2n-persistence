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
namespace n2n\persistence\orm\property;

use n2n\persistence\orm\query\QueryState;
use n2n\persistence\orm\query\from\meta\TreePointMeta;
use n2n\persistence\orm\query\from\JoinedTreePoint;
use n2n\persistence\orm\query\from\TreePoint;

interface JoinableEntityProperty extends EntityProperty {
	
	/**
	 * @return string[]
	 */
	public function getAvailableJoinTypes(TreePoint $treePoint): array;
	
	/**
	 * @param QueryState $queryState
	 * @param TreePointMeta $queryPoint
	 * @return JoinedTreePoint
	 */
	public function createJoinTreePoint(TreePointMeta $treePointMeta, QueryState $queryState);
}
