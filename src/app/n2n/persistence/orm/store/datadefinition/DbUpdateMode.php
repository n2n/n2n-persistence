<?php

namespace n2n\persistence\orm\store\datadefinition;

enum DbUpdateMode {
	case RECREATE_DATABASE;
	case RECREATE_TABLES;
	case UPDATE_TABLE;

	function isDatabaseCleared(): bool {
		return $this === self::RECREATE_DATABASE;
	}

	function isTableCleared(): bool {
		return $this === self::RECREATE_DATABASE || $this === self::RECREATE_TABLES;
	}

}