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

interface SelectStatementBuilder {
	public function addSelectColumn(QueryItem $item, $asName = null);
	/**
	 * @param bool $distinct
	 */
	public function setDistinct($distinct);
	public function addFrom(QueryResult $queryResult, $alias = null);
	/**
	 * @return QueryComparator
	 */
	public function addJoin($joinType, QueryResult $queryResult, $alias = null, QueryComparator $onComparator = null);
	/**
	 * @return QueryComparator
	 */
	public function getWhereComparator();
	public function addGroup(QueryItem $queryItem);
	public function addOrderBy(QueryItem $queryItem, $direction);
	/**
	 * @return QueryComparator
	 */
	public function getHavingComparator();
	public function setLimit($limit, $num = null);

	function setLockMode(?LockMode $lockMode): void;

	public function toSqlString();
	/**
	 * @return QueryResult
	 */
	public function toQueryResult();
	
	/**
	 * @return \n2n\persistence\meta\data\QueryResult
	 */
	public function toFromQueryResult(): QueryResult;
}
