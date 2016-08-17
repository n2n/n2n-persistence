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
namespace n2n\persistence\orm\property\impl\hangar;

use hangar\entity\model\HangarDef;
use n2n\persistence\orm\property\impl\hangar\relation\ManyToManyPropDef;
use n2n\persistence\orm\property\impl\hangar\scalar\IntegerPropDef;
use n2n\persistence\orm\property\impl\hangar\scalar\StringPropDef;
use n2n\persistence\orm\property\impl\hangar\scalar\BooleanPropDef;
use n2n\persistence\orm\property\impl\hangar\scalar\TextPropDef;
use n2n\persistence\orm\property\impl\hangar\scalar\FixedPointPropDef;
use n2n\persistence\orm\property\impl\hangar\scalar\EnumPropDef;
use n2n\persistence\orm\property\impl\hangar\scalar\FloatingPointPropDef;
use n2n\persistence\orm\property\impl\hangar\scalar\BinaryPropDef;
use n2n\persistence\orm\property\impl\hangar\relation\OneToManyPropDef;
use n2n\persistence\orm\property\impl\hangar\relation\OneToOnePropDef;
use n2n\persistence\orm\property\impl\hangar\relation\ManyToOnePropDef;

class N2nHangarDef implements HangarDef {
	public function getPropDefs() {
		return array(new StringPropDef(), new IntegerPropDef(), new BooleanPropDef(), new DateTimePropDef(),
				new TextPropDef(), new FixedPointPropDef(), new N2nLocalePropDef(), new ManyToOnePropDef(),
				new OneToManyPropDef(), new ManyToManyPropDef(), new OneToOnePropDef(), new ManagedFilePropDef(),
				new EnumPropDef(), new FloatingPointPropDef(), new BinaryPropDef());
	}
}
