<?php

namespace n2n\persistence\orm;

class CascadeTypeTest extends \PHPUnit\Framework\TestCase {
	public function testBuildString(){
		//some real examples with CascadeType const values
		$this->assertEquals('none', CascadeType::buildString(CascadeType::NONE));
		$this->assertEquals('persist', CascadeType::buildString(CascadeType::PERSIST));
		$this->assertEquals('persist, remove', CascadeType::buildString(CascadeType::PERSIST|CascadeType::REMOVE));
		$this->assertEquals('persist, merge, remove', CascadeType::buildString(CascadeType::PERSIST|CascadeType::REMOVE|CascadeType::MERGE));
		$this->assertEquals('all', CascadeType::buildString(CascadeType::PERSIST|CascadeType::REMOVE|CascadeType::MERGE|CascadeType::DETACH|CascadeType::REFRESH));
		$this->assertEquals('all', CascadeType::buildString(CascadeType::ALL));

		//all options as integer from 0-31 NONE to ALL
		$this->assertEquals('none', CascadeType::buildString(0));
		$this->assertEquals('persist', CascadeType::buildString(1));
		$this->assertEquals('merge', CascadeType::buildString(2));
		$this->assertEquals('persist, merge', CascadeType::buildString(3));
		$this->assertEquals('remove', CascadeType::buildString(4));
		$this->assertEquals('persist, remove', CascadeType::buildString(5));
		$this->assertEquals('merge, remove', CascadeType::buildString(6));
		$this->assertEquals('persist, merge, remove', CascadeType::buildString(7));
		$this->assertEquals('refresh', CascadeType::buildString(8));
		$this->assertEquals('persist, refresh', CascadeType::buildString(9));
		$this->assertEquals('merge, refresh', CascadeType::buildString(10));
		$this->assertEquals('persist, merge, refresh', CascadeType::buildString(11));
		$this->assertEquals('remove, refresh', CascadeType::buildString(12));
		$this->assertEquals('persist, remove, refresh', CascadeType::buildString(13));
		$this->assertEquals('merge, remove, refresh', CascadeType::buildString(14));
		$this->assertEquals('persist, merge, remove, refresh', CascadeType::buildString(15));
		$this->assertEquals('detach', CascadeType::buildString(16));
		$this->assertEquals('persist, detach', CascadeType::buildString(17));
		$this->assertEquals('merge, detach', CascadeType::buildString(18));
		$this->assertEquals('persist, merge, detach', CascadeType::buildString(19));
		$this->assertEquals('remove, detach', CascadeType::buildString(20));
		$this->assertEquals('persist, remove, detach', CascadeType::buildString(21));
		$this->assertEquals('merge, remove, detach', CascadeType::buildString(22));
		$this->assertEquals('persist, merge, remove, detach', CascadeType::buildString(23));
		$this->assertEquals('refresh, detach', CascadeType::buildString(24));
		$this->assertEquals('persist, refresh, detach', CascadeType::buildString(25));
		$this->assertEquals('merge, refresh, detach', CascadeType::buildString(26));
		$this->assertEquals('persist, merge, refresh, detach', CascadeType::buildString(27));
		$this->assertEquals('remove, refresh, detach', CascadeType::buildString(28));
		$this->assertEquals('persist, remove, refresh, detach', CascadeType::buildString(29));
		$this->assertEquals('merge, remove, refresh, detach', CascadeType::buildString(30));
		$this->assertEquals('all', CascadeType::buildString(31));


		//NONE is ignored if something else exist
		$this->assertEquals('persist', CascadeType::buildString(CascadeType::NONE|CascadeType::PERSIST));

		//input order of CascadeTypes don't matter output is always this order => persist, merge, remove, refresh, detach
		$this->assertEquals('persist, merge', CascadeType::buildString(CascadeType::PERSIST|CascadeType::MERGE));
		$this->assertEquals('persist, merge', CascadeType::buildString(CascadeType::MERGE|CascadeType::PERSIST));

		//some multi type integer examples, if the combined numbers contain all 5 options the result is all
		$this->assertEquals('all', CascadeType::buildString(1|2|4|8|16));
		$this->assertEquals('all', CascadeType::buildString(3|30));
		$this->assertEquals('all', CascadeType::buildString(15|18));

		//bits over 31 (32=1)will be ignored 43-32 = 11 therefore the same as 11
		$this->assertEquals('persist, merge, refresh', CascadeType::buildString(43));
		$this->assertEquals(CascadeType::buildString(11), CascadeType::buildString(43));
		//bits over 31 (32=1) will be ignored 63-32 = 31 therefore the same as 31
		$this->assertEquals('all', CascadeType::buildString(63));
		$this->assertEquals(CascadeType::buildString(31), CascadeType::buildString(63));
		//bits over 31 (64=1)(32=0) will be ignored 83-64 = 19 therefore the same as 19
		$this->assertEquals('persist, merge, detach', CascadeType::buildString(83));
		$this->assertEquals(CascadeType::buildString(19), CascadeType::buildString(83));
		//bits over 31 (64=1)(32=1) will be ignored 103-64-32 = 7 therefore the same as 7
		$this->assertEquals('persist, merge, remove', CascadeType::buildString(103));
		$this->assertEquals(CascadeType::buildString(7), CascadeType::buildString(103));

	}
}