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
namespace n2n\persistence\meta\structure;

interface Table extends MetaEntity {
	/**
	 * @return Index[]
	 */
	public function getIndexes();
	/**
	 * @return Index
	 */
	public function getPrimaryKey();
	/**
	 * @return array
	 */
	public function getColumns();
	/**
	 * @param string $name
	 * @return Column
	 * @throws UnknownColumnException
	 */
	public function getColumnByName($name);
	/**
	 * 
	 * @param array $columns
	 */
	public function setColumns(array $columns);
	/**
	 * @param Column $column
	 */
	public function addColumn(Column $column);
	/**
	 * @param string $name
	 * @return bool
	 */
	public function containsColumnName($name);
	/**
	 * @param string $name
	 */
	public function removeColumnByName($name);
	
	public function removeAllColumns();
	/**
	 * @param string $name
	 * @param string $type
	 * @param array $columnNames
	 * @return Index
	 */
	public function createIndex($type, array $columnNames, $name = null);
	/**
	 * @param string $name
	 * @return string
	 */
	public function removeIndexByName($name);
	/**
	 * 
	 */
	public function removeAllIndexes();
	/**
	 * Creates and returns a copy of the current table. The created table is 
	 * not applied to any database.
	 * @param string $newTableName Name of created table.
	 * @return Table
	 */
	public function copy($newTableName = null);
	/**
	 * @return ColumnFactory
	 */
	public function createColumnFactory();
	
}
