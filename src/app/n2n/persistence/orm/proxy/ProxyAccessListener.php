<?php

namespace n2n\persistence\orm\proxy;

interface ProxyAccessListener {

	function onAccess($entity): void;

	function dispose(): void;

	function getId(): mixed;
}