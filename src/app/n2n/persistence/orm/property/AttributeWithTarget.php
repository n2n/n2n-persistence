<?php

namespace n2n\persistence\orm\property;

interface AttributeWithTarget {
	public function getTargetEntity(): ?string;
}