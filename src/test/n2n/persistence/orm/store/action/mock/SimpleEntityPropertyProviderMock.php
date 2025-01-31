<?php

namespace n2n\persistence\orm\store\action\mock;

use n2n\persistence\orm\property\EntityPropertyProvider;
use n2n\reflection\property\PropertyAccessProxy;
use n2n\persistence\orm\property\ClassSetup;
use n2n\persistence\orm\property\impl\ScalarEntityProperty;

class SimpleEntityPropertyProviderMock implements EntityPropertyProvider {


	public function setupPropertyIfSuitable(PropertyAccessProxy $propertyAccessProxy, ClassSetup $classSetup): void {
		$classSetup->provideEntityProperty(new ScalarEntityProperty($propertyAccessProxy,
				$classSetup->requestColumn($propertyAccessProxy->getPropertyName())));
	}
}