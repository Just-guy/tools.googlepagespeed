<?php

namespace Tools\GooglePageSpeed;

use Bitrix\Main\Application;

class RobotDetector
{
	public static function isPageSpeedRobot(): bool
	{
		$userAgent = (string)Application::getInstance()->getContext()->getServer()->getUserAgent();
		if ($userAgent === '') {
			return false;
		}

		return (bool)preg_match(
			'/Lighthouse|Chrome-Lighthouse|PageSpeed|PTST|GTmetrix|Pingdom|Speed Insights/i',
			$userAgent
		);
	}
}
