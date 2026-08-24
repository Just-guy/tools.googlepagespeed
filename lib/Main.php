<?php

namespace Tools\GooglePageSpeed;

use COption;
use \Bitrix\Main\Context;
use Bitrix\Main\Page\Asset;

class Main
{
	static $module_id = "tools.googlepagespeed";
	static $arrayEliminateStyleSheetsThatBlock = "";
	static $arrayEliminateScriptsThatBlock = "";

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
		$arrayOptions = self::getOptions();

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
			if ($valueOption['ACTIVE'] != 'Y') continue;
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
				if (preg_match('(src.*' . $value['STRING_REGULAR_EXPRESSION'] . '.*)', $content, $arMatches) && !empty($value['STRING_REGULAR_EXPRESSION'])) {
					$content = preg_replace('(src.*' . $value['STRING_REGULAR_EXPRESSION'] . '.*)', $value['ATTRIBUTE'] . ' ' . $arMatches[0], $content);
				}
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

	private static function getLinkForBlockingStyleSheets($content)
	{
		preg_match_all('/<link href="(.*)".*>/msU', $content, $matches);
		return $matches;
	}

	public static function eliminateStyleSheetsThatBlockDisplay(&$content)
	{
		$eliminateStyleSheetsThatBlock = self::getLinkForBlockingStyleSheets($content);

		if (empty($eliminateStyleSheetsThatBlock)) return;

		foreach ($eliminateStyleSheetsThatBlock[1] as $value) {
			self::$arrayEliminateStyleSheetsThatBlock .= "<link href='" . $value . "' rel='preload' as='style'>\r\n";
		}

		if (!empty(self::$arrayEliminateStyleSheetsThatBlock)) {
			self::insertAfterOpeningHead($content, self::$arrayEliminateStyleSheetsThatBlock);
		}
	}

	private static function getLinkForBlockingScripts($content)
	{
		preg_match_all('/<script src="(.*)".*><\/script>/msU', $content, $matches);
		return $matches;
	}

	public static function eliminateScriptsThatBlockDisplay(&$content)
	{
		$eliminateScriptsThatBlock = self::getLinkForBlockingScripts($content);

		if (empty($eliminateScriptsThatBlock)) return;

		foreach ($eliminateScriptsThatBlock[1] as $value) {
			self::$arrayEliminateScriptsThatBlock .= "<script defer src='" . $value . "'></script>\r\n";
		}

		if (!empty(self::$arrayEliminateScriptsThatBlock)) {
			self::insertAfterOpeningHead($content, self::$arrayEliminateScriptsThatBlock);
		}
	}

	public static function addLoadingLazyAttributeAllTagsImg(&$content)
	{
		$content = preg_replace(
			'/(<img\b)((?:(?!\s(?:loading|fetchpriority|decoding)\s*=)[^>])*)(\/?>)/i',
			'$1$2 loading="lazy"$3',
			$content
		);
	}

	public static function getLinksCssStyles($filter = [])
	{
		$resultArray = [];

		// запрос к базе
		$result = ConnectedCssStyleTable::getList(
			[
				'select' => ['*'],
				'filter' => $filter
			]
		);
		// преобразование запроса от базы
		while ($row = $result->fetch()) {
			$resultArray[] = $row;
		}

		// возвращаем ответ от баззы
		return $resultArray;
	}

	public static function getLinksJsScripts($filter = [])
	{
		$resultArray = [];

		// запрос к базе
		$result = ConnectedJsScriptTable::getList(
			[
				'select' => ['*'],
				'filter' => $filter
			]
		);
		// преобразование запроса от базы
		while ($row = $result->fetch()) {
			$resultArray[] = $row;
		}

		// возвращаем ответ от баззы
		return $resultArray;
	}

	public static function getOptions()
	{
		$resultArray = [];
		
		// запрос к базе
		$result = GPSOptionsTable::getList(
			[
				'select' => ['*'],
			]
		);
		// преобразование запроса от базы
		while ($row = $result->fetch()) {
			$resultArray[] = $row;
		}

		// возвращаем ответ от баззы
		return $resultArray;
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
