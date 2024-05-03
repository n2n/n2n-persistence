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
namespace n2n\persistence\orm\query\from\meta;

use n2n\spec\dbo\meta\data\QueryItem;
use n2n\persistence\orm\CorruptedDataException;
use n2n\persistence\PdoStatement;
use n2n\persistence\orm\query\select\EagerValueBuilder;
use n2n\util\type\TypeUtils;

class SingleTableDiscriminatorSelection implements DiscriminatorSelection {
	private $discriminatorColumn;
	private $discriminatedEntityModels;

	private $value;

	public function __construct(QueryItem $discriminatorColumn, array $discriminatedEntityModels) {
		$this->discriminatorColumn = $discriminatorColumn;
		$this->discriminatedEntityModels = $discriminatedEntityModels;
	}
	/* (non-PHPdoc)
	 * @see \n2n\persistence\orm\query\from\meta\DiscriminatorSelection::determineEntityModel()
	 */
	public function determineEntityModel() {
		//		if ($this->value === null) return null;

		if (isset($this->discriminatedEntityModels[$this->value])) {
			return $this->discriminatedEntityModels[$this->value];
		}

		throw new CorruptedDataException('Unknown discriminator value \'' . TypeUtils::buildScalar($this->value)
			. '\'. Following allowed: ' . implode(', ', array_keys($this->discriminatedEntityModels)));
	}
	/* (non-PHPdoc)
	 * @see \n2n\persistence\orm\query\select\Selection::getSelectQueryItems()
	 */
	public function getSelectQueryItems() {
		return array($this->discriminatorColumn);
	}

	/* (non-PHPdoc)
	 * @see \n2n\persistence\orm\query\select\Selection::bindColumns()
	 */
	public function bindColumns(PdoStatement $stmt, array $columnAliases) {
		$stmt->shareBindColumn($columnAliases[0], $this->value);
	}
	/* (non-PHPdoc)
	 * @see \n2n\persistence\orm\query\select\Selection::createValueBuilder()
	 */
	public function createValueBuilder() {
		if (null !== ($entityModel = $this->determineEntityModel())) {
			return new EagerValueBuilder($entityModel->getClass());
		}

		return new EagerValueBuilder(null);
	}
}
