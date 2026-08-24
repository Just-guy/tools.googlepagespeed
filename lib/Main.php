<?php

namespace Tools\GooglePageSpeed;

use COption;
use \Bitrix\Main\Application;
use \Bitrix\Main\Context;
use Bitrix\Main\Page\Asset;

class Main
{
	static $module_id = "tools.googlepagespeed";
	private const CACHE_TTL = 3600;
	private const CACHE_DIR = 'tools_googlepagespeed';

	private const ALLOWED_OPTION_METHODS = [
		'eliminateStyleSheetsThatBlockDisplay',
		'eliminateScriptsThatBlockDisplay',
		'addLoadingLazyAttributeAllTagsImg',
	];

	public static function OnEndBufferContent(&$content)
	{
		if (self::shouldSkipBufferContent($content)) {
			return;
		}

		$templateArrayLinksCss = self::getLinksCssStyles(["ACTIVE" => "Y"]);
		$templateArrayLinksJS = self::getLinksJsScripts(["ACTIVE" => "Y"]);
		$arrayOptions = self::getOptions(["ACTIVE" => "Y"]);

		if (!empty($templateArrayLinksCss)) {
			foreach ($templateArrayLinksCss as $value) {
				if (preg_match('(' . $value['STRING_REGULAR_EXPRESSION'] . '(\?\d+){0,})', $content, $url) && !empty($value['STRING_REGULAR_EXPRESSION'])) {
					$arrayLinkCss[] = '<link href="' . $url[0] . '" rel="' . $value['ROLE'] . '" as="' . $value['TYPE'] . '">';
				}
			}

			if (!empty($arrayLinkCss)) {
				self::insertAfterOpeningHead($content, implode("\n", $arrayLinkCss));
			}
		}

		foreach ($arrayOptions as $valueOption) {
			if ($valueOption["LIMITATION"] == 'for-gps-robot') {
				if (!self::thisRobot()) continue;
			}

			if ($valueOption['OPTION_TYPE'] == 'regular-expression') {
				$regularExpressionArray = unserialize(
					htmlspecialcharsback($valueOption['OPTION_ACTION']),
					['allowed_classes' => false]
				);
				if (!is_array($regularExpressionArray)) {
					continue;
				}

				foreach ($regularExpressionArray as $regularExpression) {
					if (preg_match('/' . $regularExpression . '/msU', $content)) {
						$content = preg_replace('/' . $regularExpression . '/msU', '', $content);
					}
				}
			}

			if ($valueOption['OPTION_TYPE'] == 'function') {
				$methodName = htmlspecialcharsback($valueOption['OPTION_ACTION']);
				if (!in_array($methodName, self::ALLOWED_OPTION_METHODS, true)) {
					continue;
				}

				self::$methodName($content);
			}
		}

		if (!empty($templateArrayLinksJS)) {
			foreach ($templateArrayLinksJS as $value) {
				self::addAttributeToMatchingScripts($content, $value);
			}
		}
	}

	/**
	 * Модуль правит только публичный HTML-документ.
	 * Админка, AJAX, CLI и ответы без <head> (JSON, фрагменты) пропускаем.
	 */
	private static function shouldSkipBufferContent($content): bool
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

	/**
	 * Вставляет HTML сразу после открывающего <head>, не затирая тег и атрибуты.
	 */
	private static function insertAfterOpeningHead(&$content, string $html): void
	{
		$content = preg_replace(
			'/(<head\b[^>]*>)/i',
			'$1' . "\n" . $html,
			$content,
			1
		);
	}

	/**
	 * Вешает async/defer на открывающий <script src="...">, а не на кусок «src...».
	 */
	private static function addAttributeToMatchingScripts(&$content, array $value): void
	{
		$pattern = (string)($value['STRING_REGULAR_EXPRESSION'] ?? '');
		$attr = strtolower(trim((string)($value['ATTRIBUTE'] ?? '')));
		if ($pattern === '' || ($attr !== 'async' && $attr !== 'defer')) {
			return;
		}

		$content = preg_replace_callback(
			'/<script\b[^>]*>/i',
			static function ($match) use ($pattern, $attr) {
				$tag = $match[0];
				if (!preg_match('/\bsrc\s*=\s*("[^"]*"|\'[^\']*\')/i', $tag, $srcMatch)) {
					return $tag;
				}
				if (!preg_match('/' . $pattern . '/', $srcMatch[1])) {
					return $tag;
				}
				if (preg_match('/\b(?:async|defer)\b/i', $tag)) {
					return $tag;
				}

				return preg_replace('/<script\b/i', '<script ' . $attr, $tag, 1);
			},
			$content
		);
	}

	public static function eliminateStyleSheetsThatBlockDisplay(&$content)
	{
		$content = preg_replace_callback(
			'/<link\b[^>]*>/i',
			static function ($match) {
				$tag = $match[0];
				if (!preg_match('/\brel\s*=\s*(["\']?)stylesheet\1/i', $tag)) {
					return $tag;
				}
				if (preg_match('/\bmedia\s*=\s*(["\']?)print\1/i', $tag)) {
					return $tag;
				}

				$deferred = preg_replace('/\smedia\s*=\s*("[^"]*"|\'[^\']*\'|[^\s>]+)/i', '', $tag);
				$deferred = preg_replace('/\s*\/?>$/', ' media="print" onload="this.media=\'all\'"$0', $deferred);

				return $deferred . '<noscript>' . $tag . '</noscript>';
			},
			$content
		);
	}

	public static function eliminateScriptsThatBlockDisplay(&$content)
	{
		$content = preg_replace_callback(
			'/<script\b[^>]*\bsrc\s*=[^>]*>/i',
			static function ($match) {
				$tag = $match[0];
				if (preg_match('/\b(?:async|defer)\b/i', $tag)) {
					return $tag;
				}

				return preg_replace('/<script\b/i', '<script defer', $tag, 1);
			},
			$content
		);
	}

	public static function addLoadingLazyAttributeAllTagsImg(&$content)
	{
		$isFirstImg = true;
		$content = preg_replace_callback(
			'/<img\b[^>]*>/i',
			static function ($match) use (&$isFirstImg) {
				$tag = $match[0];
				if ($isFirstImg) {
					$isFirstImg = false;
					return $tag;
				}
				if (preg_match('/\b(?:loading|fetchpriority|decoding|data-src)\s*=/i', $tag)) {
					return $tag;
				}

				return preg_replace('/<img\b/i', '<img loading="lazy"', $tag, 1);
			},
			$content
		);
	}

	public static function getLinksCssStyles($filter = [])
	{
		$rows = self::getCachedTableRows('css_styles', ConnectedCssStyleTable::class);
		return self::filterCachedRows($rows, $filter);
	}

	public static function getLinksJsScripts($filter = [])
	{
		$rows = self::getCachedTableRows('js_scripts', ConnectedJsScriptTable::class);
		return self::filterCachedRows($rows, $filter);
	}

	public static function getOptions($filter = [])
	{
		$rows = self::getCachedTableRows('options', GPSOptionsTable::class);
		return self::filterCachedRows($rows, $filter);
	}

	public static function clearRulesCache(): void
	{
		Application::getInstance()->getManagedCache()->cleanDir(self::CACHE_DIR);
	}

	/**
	 * Одна выборка таблицы на TTL, дальше фильтр в PHP.
	 * Админка и публичка делят один кеш.
	 */
	private static function getCachedTableRows(string $cacheId, string $tableClass): array
	{
		$cache = Application::getInstance()->getManagedCache();
		if ($cache->read(self::CACHE_TTL, $cacheId, self::CACHE_DIR)) {
			$rows = $cache->get($cacheId);
			return is_array($rows) ? $rows : [];
		}

		$rows = [];
		$result = $tableClass::getList(['select' => ['*']]);
		while ($row = $result->fetch()) {
			$rows[] = $row;
		}

		$cache->set($cacheId, $rows);
		return $rows;
	}

	private static function filterCachedRows(array $rows, array $filter): array
	{
		if ($filter === []) {
			return $rows;
		}

		return array_values(array_filter($rows, static function ($row) use ($filter) {
			foreach ($filter as $field => $value) {
				if (($row[$field] ?? null) !== $value) {
					return false;
				}
			}
			return true;
		}));
	}

	public static function thisRobot()
	{
		$userAgent = \Bitrix\Main\Application::getInstance()->getContext()->getServer()->getUserAgent();
		if (strpos($userAgent, "Lighthouse")) {
			return true;
		} else {
			return false;
		}
	}
}
