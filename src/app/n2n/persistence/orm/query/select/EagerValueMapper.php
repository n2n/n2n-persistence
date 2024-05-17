<?php

namespace n2n\persistence\orm\query\select;

interface EagerValueMapper {
	function __invoke(mixed $value): mixed;
}