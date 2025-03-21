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

use n2n\reflection\annotation\PropertyAnnotation;
use n2n\reflection\annotation\PropertyAnnotationTrait;
use n2n\reflection\annotation\AnnotationTrait;
use n2n\persistence\orm\attribute\Column;
use n2n\persistence\orm\attribute\AssociationOverrides;
use n2n\reflection\attribute\legacy\LegacyAnnotation;

/**
 * @deprecated use { @link Column }
 */
class AnnoColumn implements PropertyAnnotation, LegacyAnnotation {
	use PropertyAnnotationTrait, AnnotationTrait;
	
	private $name;

	public function __construct(?string $name = null, bool $nullable = true, ?int $length = null,
			?int $percision = null, ?int $scale = null) {
		$this->name = $name;
	}
	
	public function getName() {
		return $this->name;
	}
	
	public function isNullable() {
		return $this->nullable;
	}

	public function getAttributeName(): string {
		return Column::class;
	}

	public function toAttributeInstance() {
		return new Column($this->name);
	}
}
