<?php

namespace n2n\persistence\orm\store\datadefinition\mock;

use n2n\persistence\meta\structure\common\DateTimeColumnAdapter;

class DateTimeColumnMock extends DateTimeColumnAdapter {

	protected function getParseFormat() {
		return 'Y-m-d H:i:s';
	}

	protected function getBuildFormat() {
		return 'Y-m-d H:i:s';
	}

	function copy($newColumnName = null) {
		$newColumn = new self($newColumnName ?? $this->getName(), $this->dateAvailable, $this->timeAvailable);
		$newColumn->applyCommonAttributes($this);
		return $newColumn;
	}
}
