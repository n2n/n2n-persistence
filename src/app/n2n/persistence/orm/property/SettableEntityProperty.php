<?php

namespace n2n\persistence\orm\property;

use n2n\persistence\orm\query\update\Settable;

interface SettableEntityProperty {

	function createSettable(): Settable;
}