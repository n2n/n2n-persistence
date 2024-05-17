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

use n2n\spec\dbo\meta\data\QueryItem;
use n2n\persistence\orm\query\QueryState;
use n2n\persistence\Pdo;
use n2n\persistence\orm\criteria\compare\ColumnComparable;
use n2n\persistence\orm\query\select\Selection;

interface BasicEntityProperty extends ColumnEntityProperty, ColumnComparableEntityProperty {
	
	/**
	 * @param mixed $value can never be null
	 * @throws \InvalidArgumentException
	 * @return string
	 */
	public function valueToRep(mixed $value): string;
	
	/**
	 * @param string $rep
	 * @return mixed
	 * @throws \InvalidArgumentException
	 */
	public function repToValue(string $rep): mixed;
	
	/**
	 * @param mixed $raw
	 * @param Pdo $pdo
	 * @return mixed
	 * @throws \InvalidArgumentException
	 */
	public function parseValue(mixed $raw, Pdo $pdo): mixed;
	
	/**
	 * @param mixed $value
	 * @param Pdo $pdo
	 * @return mixed
	 * @throws \InvalidArgumentException
	 */
	public function buildRaw(mixed $value, Pdo $pdo): mixed;
	
	/**
	 * @param QueryItem $queryItem
	 * @param QueryState $queryState
	 * @return \n2n\persistence\orm\query\select\Selection
	 */
	public function createSelectionFromQueryItem(QueryItem $queryItem, QueryState $queryState): Selection;
	
	public function createColumnComparableFromQueryItem(QueryItem $queryItem, QueryState $queryState): ColumnComparable;
}
