<?php
namespace n2n\persistence\orm\attribute;

use Attribute;

#[Attribute(Attribute::TARGET_PROPERTY)]
class OrderBy {
	public function __construct(private array $orderDefs) {

	}

	public function getOrderDefs() {
		return $this->orderDefs;
	}
}