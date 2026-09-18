<?php

namespace Tools\GooglePageSpeed;

class Main
{
	static $module_id = "tools.googlepagespeed";

	public static function OnEndBufferContent(&$content)
	{
		ScriptDeferral::reset();

		if (BufferGuard::shouldSkip($content)) {
			return;
		}

		$templateArrayLinksCss = SettingsProvider::getLinksCssStyles(["ACTIVE" => "Y"]);
		$templateArrayLinksJS = SettingsProvider::getLinksJsScripts(["ACTIVE" => "Y"]);
		$arrayOptions = SettingsProvider::getOptions(["ACTIVE" => "Y"]);

		if (!empty($templateArrayLinksCss)) {
			foreach ($templateArrayLinksCss as $value) {
				if (preg_match('(' . $value['STRING_REGULAR_EXPRESSION'] . '(\?\d+){0,})', $content, $url) && !empty($value['STRING_REGULAR_EXPRESSION'])) {
					$arrayLinkCss[] = '<link href="' . $url[0] . '" rel="' . $value['ROLE'] . '" as="' . $value['TYPE'] . '">';
				}
			}

			if (!empty($arrayLinkCss)) {
				HtmlBuffer::insertAfterOpeningHead($content, implode("\n", $arrayLinkCss));
			}
		}

		foreach ($arrayOptions as $valueOption) {
			if ($valueOption["LIMITATION"] == 'for-gps-robot') {
				if (!RobotDetector::isPageSpeedRobot()) {
					continue;
				}
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
				OptionActions::run($methodName, $content);
			}
		}

		if (!empty($templateArrayLinksJS)) {
			foreach ($templateArrayLinksJS as $value) {
				HtmlBuffer::addAttributeToMatchingScripts($content, $value);
			}
		}

		ScriptDeferral::injectRuntime($content);
	}
}
