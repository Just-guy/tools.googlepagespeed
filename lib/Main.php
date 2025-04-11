<?php

namespace Tools\GooglePageSpeed;

use COption;
use \Bitrix\Main\Context;
use Bitrix\Main\Page\Asset;

class Main
{
	static $module_id = "tools.googlepagespeed";
	static $arrayEliminateResourcesThatBlock = "";

	public static function OnPageStart() {}

	public static function OnProlog() {}

	public static function OnAfterEpilog() {}

	public static function OnBeforeEndBufferContent() {}

	public static function OnEndBufferContent(&$content)
	{
		$isAdmin = Context::getCurrent()->getRequest()->isAdminSection();
		if($isAdmin) continue;
		
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
				$stringLinkCss = implode("\r\n", $arrayLinkCss);
				$content = preg_replace('/(<head.*?>)/i', $stringLinkCss, $content, 1);
			}
		}

		foreach ($arrayOptions as $valueOption) {
			if ($valueOption['ACTIVE'] != 'Y') continue;
			if ($valueOption["LIMITATION"] == 'for-gps-robot') {
				if (!self::thisRobot()) continue;
			}

			if ($valueOption['OPTION_TYPE'] == 'regular-expression') {
				$regularExpressionArray = unserialize(htmlspecialcharsback($valueOption['OPTION_ACTION']));

				foreach ($regularExpressionArray as $regularExpression) {
					if (preg_match('/' . $regularExpression . '/msU', $content)) {
						$content = preg_replace('/' . $regularExpression . '/msU', '', $content);
					}
				}
			}

			if ($valueOption['OPTION_TYPE'] == 'function') {
				$methodName  = htmlspecialcharsback($valueOption['OPTION_ACTION']);

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

	private static function getLinkForBlockingResources($content)
	{
		preg_match_all('/<link href="(.*)".*>/msU', $content, $matches);
		return $matches;
	}

	public static function eliminateResourcesThatBlockDisplay(&$content)
	{
		$eliminateResourcesThatBlock = self::getLinkForBlockingResources($content);

		if (empty($eliminateResourcesThatBlock)) return;

		foreach ($eliminateResourcesThatBlock[1] as $value) {
			self::$arrayEliminateResourcesThatBlock .= "<link href='" . $value . "' rel='preload' as='style'>\r\n";
		}

		if (!empty(self::$arrayEliminateResourcesThatBlock)) {
			$content = preg_replace('/(<head.*?>)/i', "<head>\r\n" . self::$arrayEliminateResourcesThatBlock, $content, 1);
		}
	}

	public static function getLinksCssStyles($filter = [])
	{
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
