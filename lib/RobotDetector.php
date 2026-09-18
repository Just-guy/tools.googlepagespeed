<?php

namespace Tools\GooglePageSpeed;

use Bitrix\Main\Application;

class RobotDetector
{
	public static function isPageSpeedRobot(): bool
	{
		$userAgent = Application::getInstance()->getContext()->getServer()->getUserAgent();
		return strpos($userAgent, 'Lighthouse') !== false;
	}
}
