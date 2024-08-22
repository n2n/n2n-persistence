<?php

namespace n2n\persistence\orm\store\action\meta;

use n2n\persistence\orm\property\EntityProperty;

class ActionMetaUtil {

	function __construct(private ActionMeta $actionMeta) {

	}

	/**
	 * @param EntityProperty $entityProperty
	 * @return EntityProperty[]
	 */
	private function flattenEntityProperties(EntityProperty $entityProperty): array {
		$entityProperties = [$entityProperty];
		if (!$entityProperty->hasEmbeddedEntityPropertyCollection()) {
			return $entityProperties;
		}

		foreach ($entityProperty->getEmbeddedEntityPropertyCollection()->getEntityProperties()
				as $entityProperty) {
			array_push($entityProperties, ...$this->flattenEntityProperties($entityProperty));
		}

		return $entityProperties;
	}

	function containsChangesFor(EntityProperty $entityProperty): bool {
		$entityProperties = $this->flattenEntityProperties($entityProperty);

		$markedEntityProperties = $this->actionMeta->getMarkedEntityProperties();
		$items = $this->actionMeta->getItems();
		foreach ($entityProperties as $entityProperty) {
			if (in_array($entityProperty, $markedEntityProperties)) {
				return true;
			}

			foreach ($items as $item) {
				if (in_array($entityProperty, $item->getEntityProperties())) {
					return true;
				}
			}
		}

		return false;
	}

	function containsChangesForAnyBut(EntityProperty $entityProperty): bool {
		$entityProperties = $this->flattenEntityProperties($entityProperty);

		$markedEntityProperties = $this->actionMeta->getMarkedEntityProperties();
		$items = $this->actionMeta->getItems();
		foreach ($entityProperties as $entityProperty) {
			if (!in_array($entityProperty, $markedEntityProperties)) {
				return true;
			}

			foreach ($items as $item) {
				if (!in_array($entityProperty, $item->getEntityProperties())) {
					return true;
				}
			}
		}

		return false;
	}
}