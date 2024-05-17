<?php

namespace n2n\persistence\orm\criteria\compare;

interface PlaceholderValueMapper {
	function __invoke(mixed $value): float|int|string|bool|null;
}