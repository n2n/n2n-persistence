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
namespace n2n\persistence\meta\data;

use n2n\spec\dbo\meta\data\QueryPart;
use n2n\spec\dbo\meta\data\QueryItem;
use n2n\spec\dbo\meta\data\QueryFragmentBuilder;

class QueryPartGroup implements QueryItem {
	protected $queryParts = array();
	
	public function __construct(?QueryPart $queryPart = null) {
		if ($queryPart !== null) {
			$this->addQueryPart($queryPart);
		}
	}
	
	public function addQueryPart(QueryPart $queryPart) {
		$this->queryParts[] = $queryPart;
	}
	
	public function getQueryParts() {
		return $this->queryParts;
	}

	public function buildItem(QueryFragmentBuilder $fragmentBuilder): void {
		// removed for empty IN and NOT IN groups. For example he.email NOT IN ()
		// if (empty($this->queryParts)) return;
		
		$fragmentBuilder->openGroup();
		foreach ($this->queryParts as $key => $queryPart) {
			if ($key > 0) {
				$fragmentBuilder->addSeparator();
			}
			$queryPart->buildItem($fragmentBuilder);
		}
		$fragmentBuilder->closeGroup();
	}
	
	public function equals($obj): bool {
		return $obj instanceof QueryPartGroup && $this->queryParts === $obj->queryParts;
	}
}
