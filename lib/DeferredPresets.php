<?php

namespace Tools\GooglePageSpeed;

class DeferredPresets
{
	/**
	 * Определения пресетов отложенной загрузки для install / миграции БД.
	 */
	public static function getOptionDefinitions(): array
	{
		return [
			[
				'ACTIVE' => 'N',
				'CODE_OPTION' => 'DEFER_YANDEX_METRIKA',
				'NAME_OPTION' => 'Отложить Яндекс.Метрику',
				'OPTION_ACTION' => 'deferYandexMetrika',
				'OPTION_TYPE' => 'function',
				'LIMITATION' => 'for-everyone',
			],
			[
				'ACTIVE' => 'N',
				'CODE_OPTION' => 'DEFER_GOOGLE_ANALYTICS',
				'NAME_OPTION' => 'Отложить Google Analytics',
				'OPTION_ACTION' => 'deferGoogleAnalytics',
				'OPTION_TYPE' => 'function',
				'LIMITATION' => 'for-everyone',
			],
			// [
			// 	'ACTIVE' => 'N',
			// 	'CODE_OPTION' => 'DEFER_JIVOCHAT',
			// 	'NAME_OPTION' => 'Отложить JivoChat',
			// 	'OPTION_ACTION' => 'deferJivoChat',
			// 	'OPTION_TYPE' => 'function',
			// 	'LIMITATION' => 'for-everyone',
			// ],
		];
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
	}
}
