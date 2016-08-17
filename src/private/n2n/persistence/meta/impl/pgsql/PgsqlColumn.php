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

use n2n\persistence\meta\structure\Column;
use n2n\persistence\meta\structure\common\ColumnAdapter;

class PgsqlColumn extends ColumnAdapter implements PgsqlManagedColumn {
	/**
	 * (non-PHPdoc)
	 * @see n2n\persistence\meta.Column::equals()
	 */
	public function equals(Column $column) {
		return ($column->getName() == $this->getName())
				&& ($column->getTable()->equals($this->getTable()))
				&& $this->equalsType($column);
	}

	/**
	 * (non-PHPdoc)
	 * @see n2n\persistence\meta.Column::equalsType()
	 */
	public function equalsType(Column $column) {
		return ($column->isNullAllowed() == $this->isNullAllowed())
				&& ($column->getDefaultValue() == $this->getDefaultValue())
				&& ($column->isValueGenerated() == $this->isValueGenerated())
				&& ($column->getIndexes() === $this->getIndexes());
	}

	public function copy($newColumnName = null) {
		if (is_null($newColumnName)) $newColumnName = $this->getName();
		$column = new PgsqlColumn($newColumnName);
		$column->setAttrs($this->getAttrs());
		$column->setDefaultValue($this->getDefaultValue());
		$column->setNullAllowed($this->isNullAllowed());
		$column->setValueGenerated($this->isValueGenerated());

		return $column;
	}

	public function getTypeForCurrentState() {
		$attrs = $this->getAttrs();
		return $attrs['data_type'];
	}
}
