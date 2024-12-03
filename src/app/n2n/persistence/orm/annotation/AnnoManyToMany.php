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

use n2n\persistence\orm\attribute\ManyToMany;
use n2n\reflection\attribute\legacy\LegacyAnnotation;

/**
 * @deprecated use { @link ManyToMany }
 */
class AnnoManyToMany extends MappableOrmRelationAnnotation implements LegacyAnnotation {
	public function __construct(\ReflectionClass $targetEntityClass, ?string $mappedBy = null,
			?int $cascadeType = null, ?string $fetchType = null) {
		
		parent::__construct($targetEntityClass, $mappedBy, $cascadeType, $fetchType);
	}

	public function getAttributeName(): string {
		return ManyToMany::class;
	}

	public function toAttributeInstance() {
		return new ManyToMany($this->getTargetEntityClass()->getName(), $this->getMappedBy(), $this->getCascadeType(),
				$this->getFetchType());
	}
}
