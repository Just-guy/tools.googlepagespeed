<?php

namespace Tools\GooglePageSpeed;

use Bitrix\Main\Context;

class BufferGuard
{
	/**
	 * Модуль правит только публичный HTML-документ.
	 * Админка, AJAX, CLI и ответы без <head> (JSON, фрагменты) пропускаем.
	 */
	public static function shouldSkip($content): bool
	{
		if (!is_string($content) || $content === '') {
			return true;
		}

		if (PHP_SAPI === 'cli') {
			return true;
		}

		$request = Context::getCurrent()->getRequest();
		if ($request->isAdminSection() || $request->isAjaxRequest()) {
			return true;
		}

		if (defined('PUBLIC_AJAX_MODE') && PUBLIC_AJAX_MODE) {
			return true;
		}

		if (!preg_match('/<head\b/i', $content)) {
			return true;
		}

		return false;
	}
}
