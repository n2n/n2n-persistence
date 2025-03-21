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
namespace n2n\persistence\orm\store\action;

use n2n\persistence\Pdo;

interface Action {
	const PRIORITY_DEFAULT = 2;
	const PRIORITY_REMOVE = 1;

	/**
	 * @return int
	 */
	function getPriority(): int;
	/**
	 * Can be called multiple times.
	 */
	public function execute(Pdo $pdo): void;
	/**
	 * @param \Closure $closure
	 */
	public function executeAtStart(\Closure $closure);
	/**
	 * @param \Closure $closure
	 */
	public function executeAtEnd(\Closure $closure);
	/**
	 * @param Action $actionJob
	 */
	public function addDependent(Action $actionJob);
	/**
	 * @return Action[]
	 */
	public function getDependents();
	/**
	 * @param Action[] $dependents
	 */
	public function setDependents(array $dependents);
}
