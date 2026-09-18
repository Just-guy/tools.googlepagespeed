<?php

namespace Tools\GooglePageSpeed;

use Bitrix\Main\Application;

class SettingsProvider
{
	private const CACHE_TTL = 3600;
	private const CACHE_DIR = 'tools_googlepagespeed';

	/** Опции с закомментированной логикой — не показывать и не выполнять. */
	private const DISABLED_OPTION_CODES = [
		'DEFER_JIVOCHAT',
	];

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
		$rows = self::filterCachedRows($rows, $filter);

		return array_values(array_filter($rows, static function ($row) {
			$code = (string)($row['CODE_OPTION'] ?? '');
			return $code === '' || !in_array($code, self::DISABLED_OPTION_CODES, true);
		}));
	}

	public static function clearCache(): void
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
		$result = $tableClass::getList(['select' => ['*'], 'order' => ['ID' => 'ASC']]);
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
}
