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
namespace n2n\persistence\orm\annotation;

use n2n\reflection\annotation\ClassAnnotation;
use n2n\reflection\annotation\ClassAnnotationTrait;
use n2n\reflection\annotation\AnnotationTrait;
use n2n\util\type\ArgUtils;
use n2n\persistence\orm\attribute\DiscriminatorColumn;
use n2n\reflection\attribute\legacy\LegacyAnnotation;

/**
 * @deprecated use { @link DiscriminatorColumn }
 */
class AnnoDiscriminatorColumn implements ClassAnnotation, LegacyAnnotation {
	use ClassAnnotationTrait, AnnotationTrait;
	
	private $columnName;
	
	public function __construct($columnName) {
		ArgUtils::assertTrue(is_scalar($columnName) && 0 < mb_strlen($columnName));
		$this->columnName = $columnName;
	}
	
	public function getColumnName() {
		return $this->columnName;
	}

	public function getAttributeName(): string {
		return DiscriminatorColumn::class;
	}

	public function toAttributeInstance() {
		return new DiscriminatorColumn($this->columnName);
	}
}
