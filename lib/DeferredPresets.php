<?php

namespace Tools\GooglePageSpeed;

class DeferredPresets
{
	/**
	 * Пресеты правой колонки «Отложить скрипты» (из OptionsDefinitions).
	 */
	public static function getOptionDefinitions(): array
	{
		$rows = [];
		foreach (OptionsDefinitions::forPanel('defer') as $def) {
			$rows[] = OptionsDefinitions::toDbRow($def);
		}

		return $rows;
	}

	/**
	 * Добавляет пресеты в БД, если их ещё нет (для уже установленных модулей).
	 */
	public static function ensureOptions(): void
	{
		$existing = [];
		foreach (SettingsProvider::getOptions([]) as $row) {
			$code = (string)($row['CODE_OPTION'] ?? '');
			if ($code !== '') {
				$existing[$code] = true;
			}
		}

		$added = false;
		foreach (self::getOptionDefinitions() as $def) {
			if (isset($existing[$def['CODE_OPTION']])) {
				continue;
			}
			GPSOptionsTable::add($def);
			$added = true;
		}

		if ($added) {
			SettingsProvider::clearCache();
		}

		self::disableObsoleteCutOptions();
	}

	/**
	 * Скрытые «Вырезать…» (Метрика/GA/GTM): снять ACTIVE, чтобы не отрабатывали из БД.
	 */
	public static function disableObsoleteCutOptions(): void
	{
		$obsolete = OptionsDefinitions::getHiddenOptionCodes();
		$changed = false;
		foreach (SettingsProvider::getOptions([]) as $row) {
			$code = (string)($row['CODE_OPTION'] ?? '');
			if (!in_array($code, $obsolete, true)) {
				continue;
			}
			if ((string)($row['ACTIVE'] ?? 'N') === 'N') {
				continue;
			}
			GPSOptionsTable::update((int)$row['ID'], ['ACTIVE' => 'N']);
			$changed = true;
		}
		if ($changed) {
			SettingsProvider::clearCache();
		}
	}
}
