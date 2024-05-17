<?php

namespace n2n\persistence\orm\query\select;

use n2n\persistence\orm\CorruptedDataException;

interface EagerValueMapper {
	/**
	 * @param mixed $value
	 * @return mixed
	 * @throws CorruptedDataException
	 */
	function __invoke(mixed $value): mixed;
}