<?php

namespace n2n\persistence\orm\proxy;

interface ProxyAccessListener {

	function onAccess(object $obj): void;

	function dispose(): void;

	function getId(): mixed;
}