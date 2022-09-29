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

use n2n\persistence\orm\attribute\ManyToOne;
use n2n\reflection\attribute\legacy\LegacyAnnotation;

/**
 * @deprecated use { @link ManyToOne }
 */
class AnnoManyToOne extends OrmRelationAnnotation implements LegacyAnnotation {
	public function __construct(\ReflectionClass $targetEntityClass, int $cascadeType = null, 
			string $fetchType = null) {
		if (3 < count(func_get_args())) {
			throw new \InvalidArgumentException('Maximum parameter number for AnnoManyToOne is 3.');
		}
				
		parent::__construct($targetEntityClass, $cascadeType, $fetchType);
	}

	public function getAttributeName(): string {
		return ManyToOne::class;
	}

	public function toAttributeInstance() {
		return new ManyToOne($this->getTargetEntityClass(), $this->getCascadeType(), $this->getFetchType());
	}
}
