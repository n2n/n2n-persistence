<?php

namespace n2n\persistence\orm\store\datadefinition\mock;

use n2n\persistence\meta\structure\common\DatabaseAdapter;
use n2n\spec\dbo\meta\structure\MetaEntityFactory;

class DatabaseMock extends DatabaseAdapter {

	function createMetaEntityFactory(): MetaEntityFactory {
		return new MetaEntityFactoryMock($this);
	}
}
