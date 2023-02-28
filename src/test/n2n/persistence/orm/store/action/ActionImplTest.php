<?php
namespace n2n\persistence\orm\store\action;

use PHPUnit\Framework\TestCase;
use n2n\persistence\ext\PdoPool;

class ActionImplTest extends TestCase {

	function setUp(): void {
		new PdoPool();
	}

}

