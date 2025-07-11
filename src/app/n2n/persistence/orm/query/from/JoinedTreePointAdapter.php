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

use n2n\util\type\ArgUtils;
use n2n\persistence\meta\data\JoinType;
use n2n\persistence\meta\data\QueryComparator;
use n2n\persistence\orm\query\from\meta\TreePointMeta;
use n2n\persistence\orm\query\QueryState;

abstract class JoinedTreePointAdapter extends EntityTreePoint implements JoinedTreePoint {
	protected $joinType = JoinType::INNER;
	protected $onComparator;
	
	public function __construct(QueryState $queryState, TreePointMeta $treePointMeta) {
		parent::__construct($queryState, $treePointMeta);
		$this->onComparator = new QueryComparator();
	}
	
	public function setJoinType($joinType) {
		ArgUtils::valEnum($joinType, JoinType::getValues());
		$this->joinType = $joinType;
	}
	
	public function getJoinType(): string {
		return $this->joinType;
	}
	
	public function getOnQueryComparator(): QueryComparator {
		return $this->onComparator;
	}
	
}
