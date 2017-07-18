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
namespace n2n\persistence\orm\nql;

use n2n\util\StringUtils;
use n2n\persistence\orm\criteria\item\CriteriaFunction;

class NqlUtils {
	
	public static function isNoticeableKeyword($str) {
		return in_array(mb_strtoupper($str), Nql::getNoticeableKeyWords());
	}
	
	public static function isPlaceholder($str) {
		return StringUtils::startsWith(Nql::PLACHOLDER_PREFIX, $str);
	}
	
	public static function isFunction($str) {
		return in_array(mb_strtoupper($str), CriteriaFunction::getNames());
	}
	
	public static function isCriteria($str) {
		return mb_strpos(mb_strtoupper($str), Nql::KEYWORD_FROM) !== false 
				|| mb_strpos(mb_strtoupper($str), Nql::KEYWORD_SELECT) !== false;
	}
	
	public static function isQuoted($str) {
		return StringUtils::startsWith('"', $str) && StringUtils::endsWith('"', $str);
	}
	
	public static function isQuotationMark($token) {
		return $token === Nql::QUOTATION_MARK;
	}
	
	public static function removeQuotationMarks($expression) {
		if ((StringUtils::startsWith('"', $expression) && StringUtils::endsWith('"', $expression))
				/* || (StringUtils::startsWith('`', $entityName) && StringUtils::endsWith('`', $entityName)) */) {
			$expression = mb_substr($expression, 1, -1);
		}
		return $expression;
	}
}
