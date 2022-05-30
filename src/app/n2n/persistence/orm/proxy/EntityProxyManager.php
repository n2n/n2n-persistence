<?php
/*
 * Copyright (c) 2012-2016, Hofmänner New Media.
 * DO NOT ALTER OR REMOVE COPYRIGHT NOTICES OR THIS FILE HEADER.
 *
 * This file is part of the N2N FRAMEWORK.
 *
 * The N2N FRAMEWORK is free software: you can redistribute it and/or modify it under the terms of
 * the GNU Lesser General Public License as published by the Free Software Foundation, either
 * version 2.1 of the License, or (at your option) any later version.
 *
 * N2N is distributed in the hope that it will be useful, but WITHOUT ANY WARRANTY; without even
 * the implied warranty of MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU Lesser General Public License for more details: http://www.gnu.org/licenses/
 *
 * The following people participated in this project:
 *
 * Andreas von Burg.....: Architect, Lead Developer
 * Bert Hofmänner.......: Idea, Frontend UI, Community Leader, Marketing
 * Thomas Günther.......: Developer, Hangar
 */
namespace n2n\persistence\orm\proxy;

use n2n\util\ex\NotYetImplementedException;
use n2n\reflection\ReflectionUtils;
use n2n\util\StringUtils;
use \InvalidArgumentException;
use ReflectionClass;
use n2n\util\ex\IllegalStateException;
use n2n\util\type\ArgUtils;

class EntityProxyManager {
	const PROXY_NAMESPACE_PREFIX = 'n2n\\persistence\\orm\\proxy\\entities';
	const PROXY_ACCESS_LISTENR_PROPERTY = '_accessListener';
	const PROXY_TRIGGER_ACCESS_METHOD = '_triggerOnAccess';
	const PROXY_DUP_SUFFIX = '_';

	private static $instance = null;

	private $proxyClasses = array();
	private $accessListenerPropertyNames = array();

	private function __construct() {
	}

	/**
	 * @return EntityProxyManager
	 */
	static function getInstance() {
		if (self::$instance === null) {
			self::$instance = new EntityProxyManager();
		}

		return self::$instance;
	}
	/**
	 * @param ReflectionClass $class
	 * @param EntityProxyAccessListener $proxyAccessListener
	 * @return object
	 * @throws CanNotCreateEntityProxyClassException
	 */
	public function createProxy(ReflectionClass $class, EntityProxyAccessListener $proxyAccessListener) {
		$className = $class->getName();
		if (!isset($this->proxyClasses[$className])) {
			$this->proxyClasses[$className] = $this->createProxyClass($class);
		}

		$proxyClass = $this->proxyClasses[$className];
		$proxy = ReflectionUtils::createObject($proxyClass);
		$property = $this->getAccessibleListenerProperty($proxyClass);
		$property->setValue($proxy, $proxyAccessListener);

		return $proxy;
	}

	private function getAccessibleListenerProperty(\ReflectionClass $proxyClass): \ReflectionProperty {
		$proxyClassName = $proxyClass->getName();
		IllegalStateException::assertTrue(isset($this->accessListenerPropertyNames[$proxyClassName]));

		$property = $proxyClass->getProperty($this->accessListenerPropertyNames[$proxyClassName]);
		$property->setAccessible(true);
		return $property;
	}

	/**
	 * @param object $entity
	 * @return EntityProxyAccessListener|null
	 */
	private function retrieveAccessListener(object $entity, bool $unset): ?EntityProxyAccessListener {
		if (!($entity instanceof EntityProxy)) {
			return null;
		}

		$property = $this->getAccessibleListenerProperty(new ReflectionClass($entity));
		$accessListener = $property->getValue($entity);

		if ($unset) {
			$property->setValue($entity,null);
		}

		if ($accessListener === null || $accessListener instanceof EntityProxyAccessListener) {
			return $accessListener;
		}

		throw new IllegalStateException(get_class($entity) . '::' . $this->accessListenerPropertyNames[$className]
				. ' corrupted.');
	}

	/**
	 * @param EntityProxy $proxy
	 * @throws \n2n\persistence\orm\EntityNotFoundException
	 */
	public function initializeProxy(EntityProxy $proxy) {
		$this->retrieveAccessListener($proxy, true)?->onAccess($proxy);
	}

	public function isProxyInitialized(EntityProxy $proxy): bool {
		return null === $this->retrieveAccessListener($proxy, false);
	}

	public function disposeProxyAccessListenerOf($entity) {
		$this->retrieveAccessListener($entity, true)?->dispose();
	}

	private function createProxyClass(ReflectionClass $class) {
		if ($class->isAbstract()) {
			throw new CanNotCreateEntityProxyClassException('Can not create proxy of abstract class ' . $class->getName() . '.');
		}

		if (sizeof($class->getProperties(\ReflectionProperty::IS_PUBLIC))) {
			throw new CanNotCreateEntityProxyClassException('Can not create proxy of class ' . $class->getName() . ' because it has public properties.');
		}

		$proxyNamespaceName =  self::PROXY_NAMESPACE_PREFIX;
		$namespaceName = $class->getNamespaceName();
		if ($namespaceName) {
			$proxyNamespaceName .= '\\' . $namespaceName;
		}
		$proxyClassName = mb_substr($class->getName(), mb_strlen($namespaceName) + 1);

		$accessListenerPropertyName = self::PROXY_ACCESS_LISTENR_PROPERTY;
		while ($class->hasProperty($accessListenerPropertyName)) {
			$accessListenerPropertyName .= self::PROXY_DUP_SUFFIX;
		}
		$this->accessListenerPropertyNames[$proxyNamespaceName . '\\' . $proxyClassName] = $accessListenerPropertyName;

		$accessMethodName = self::PROXY_TRIGGER_ACCESS_METHOD;
		while ($class->hasMethod($accessMethodName)) {
			$accessMethodName .= self::PROXY_DUP_SUFFIX;
		}

		$phpProxyStr = 'namespace ' . $proxyNamespaceName . ' { '
				. 'class ' . $proxyClassName . ' extends \\' . $class->getName() . ' implements \n2n\persistence\orm\proxy\EntityProxy {'
				. 'private $' . $accessListenerPropertyName . ';'
				. 'private function ' . $accessMethodName . '() {'
				. 'if (null === $this->' . $accessListenerPropertyName . ') return;'
				. '$this->' . $accessListenerPropertyName . '->onAccess($this);'
				. '$this->' . $accessListenerPropertyName . ' = null;'
				. '}';

		foreach ($class->getMethods(\ReflectionMethod::IS_PUBLIC) as $method) {
			if ($method->isStatic()) continue;

			$phpParameterStrs = array();
			$phpParameterCallStrs = array();
			foreach ($method->getParameters() as $parameter) {
				$phpParameterStrs[] = $this->buildPhpParamerStr($parameter);
				$phpParameterCallStrs[] = $this->buildDollar($parameter, false) . $parameter->getName();
			}

			$methodReturnTypeStr = '';
			$isVoidReturn = false;
			if (null !== ($returnType = $method->getReturnType())) {
				$methodReturnTypeStr .= ': ' . $this->buildTypeStr($returnType, $isVoidReturn);
			}

			$phpProxyStr .= "\r\n" . 'public function ' . $method->getName() . '(' . implode(', ', $phpParameterStrs) . ') '
					. $methodReturnTypeStr . ' { '
					. '$this->' . self::PROXY_TRIGGER_ACCESS_METHOD . '(); '
					. ( $isVoidReturn ? '' : 'return ') . 'parent::' . $method->getName() . '(' . implode(', ', $phpParameterCallStrs) . '); '
					. '}';
		}

		$phpProxyStr .= '}'
				. '}';

		if (false === eval($phpProxyStr)) {
			die();
		}

		return new ReflectionClass($proxyNamespaceName . '\\' . $proxyClassName);
	}

	private function buildDollar(\ReflectionParameter $parameter, bool $includeRef) {
		$str = '';
		if ($parameter->isVariadic()) {
			$str .= '...';
		}
		if ($includeRef && $parameter->isPassedByReference()) {
			$str .= '&';
		}
		$str .= '$';
		return $str;
	}

	private function buildPhpParamerStr(\ReflectionParameter $parameter) {
		$phpParamStr = '';

		if (null !== ($type = $parameter->getType())) {
			$phpParamStr .= $this->buildTypeStr($type) . ' ';
		}

		$phpParamStr .= $this->buildDollar($parameter, true) . $parameter->getName();

		if ($parameter->isDefaultValueAvailable()) {
			if ($parameter->isDefaultValueConstant()) {
				$phpParamStr .= ' = ' . $this->buildDefaultConstStr($parameter->getDefaultValueConstantName());
			} else {
				$phpParamStr .= ' = ' . $this->buildValueStr($parameter->getDefaultValue());
			}
		}

		return $phpParamStr;
	}

	private function buildTypeStr(\ReflectionType $type, &$isVoid = false) {
		$typeStrs = [];

		$isVoid = false;
		$isMixed = false;
		if ($type instanceof \ReflectionNamedType) {
			$typeStrs[] = $typeName = self::buildNamedTypeStr($type);
			$isVoid = $typeName === 'void';
			$isMixed = $typeName === 'mixed';
		} else if ($type instanceof \ReflectionUnionType) {
			foreach ($type->getTypes() as $iType) {
				$typeStrs[] = self::buildNamedTypeStr($iType);
			}
		} else {
			throw new InvalidArgumentException('ReflectionNamedType or ReflectionUnionType expected.');
		}

		if (!$isMixed && $type->allowsNull()) {
			$typeStrs[] = 'null';
		}

		return implode('|', $typeStrs);
	}

	private function buildNamedTypeStr(\ReflectionNamedType $namedType) {
		if ($namedType->isBuiltin()) {
			return $namedType->getName();
		}

		return '\\' . $namedType->getName();
	}

	private function buildDefaultConstStr($defaultConstName) {
		if (StringUtils::startsWith('self::', $defaultConstName)) {
			return $defaultConstName;
		}

		return '\\' . $defaultConstName;

	}

	private function buildValueStr($value) {
		if ($value === null) {
			return 'null';
		} else if (is_string($value)) {
			return '\'' . $value . '\'';
		} else if (is_bool($value)) {
			return $value ? 'true' : 'false';
		} else if (is_numeric($value)) {
			return (string) $value;
		} else if (is_array($value)) {
			$fieldStrs = array();
			foreach ($value as $key => $fieldValue) {
				$fieldStrs[] = $this->buildValueStr($key) . ' => ' . $this->buildValueStr($fieldValue);
			}
			return 'array(' . implode(', ', $fieldStrs) . ')';
		}

		throw new \InvalidArgumentException('Cannot print value str of type: ' . gettype($value));
	}

	private function determineDefaultValue($defaultValue) {
		if (is_null($defaultValue)) {
			return 'null';
		}

		if (is_numeric($defaultValue)) {
			return $defaultValue;
		}

		if (is_scalar($defaultValue)) {
			// @todo \"
			return '\'' . addslashes($defaultValue) . '\'';
		}

		if (is_array($defaultValue)) {
			$fields = array();
			foreach ($defaultValue as $key => $value) {
				$fields[] = $this->determineDefaultValue($key) . ' => ' . $value;
			}
			return implode(', ', $fields);
		}

		throw new NotYetImplementedException();
	}
}
