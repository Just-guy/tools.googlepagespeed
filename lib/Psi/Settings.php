<?php

namespace Tools\GooglePageSpeed\Psi;

use Bitrix\Main\Config\Option;

/**
 * Настройки PSI для модуля (ключ API — один на инстанс модуля).
 */
class Settings
{
	private const MODULE_ID = 'tools.googlepagespeed';
	private const OPTION_API_KEY = 'psi_api_key';

	public static function getApiKey(): string
	{
		return trim((string)Option::get(self::MODULE_ID, self::OPTION_API_KEY, ''));
	}

	public static function setApiKey(string $key): void
	{
		Option::set(self::MODULE_ID, self::OPTION_API_KEY, trim($key));
	}

	public static function clearApiKey(): void
	{
		Option::set(self::MODULE_ID, self::OPTION_API_KEY, '');
	}

	public static function hasApiKey(): bool
	{
		return self::getApiKey() !== '';
	}
}
