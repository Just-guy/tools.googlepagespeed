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
			[
				'ACTIVE' => 'N',
				'CODE_OPTION' => 'DEFER_GOOGLE_TAG_MANAGER',
				'NAME_OPTION' => 'Отложить Google Tag Manager',
				'OPTION_ACTION' => 'deferGoogleTagManager',
				'OPTION_TYPE' => 'function',
				'LIMITATION' => 'for-everyone',
			],
			[
				'ACTIVE' => 'N',
				'CODE_OPTION' => 'DEFER_ROISTAT',
				'NAME_OPTION' => 'Отложить Roistat',
				'OPTION_ACTION' => 'deferRoistat',
				'OPTION_TYPE' => 'function',
				'LIMITATION' => 'for-everyone',
			],
			[
				'ACTIVE' => 'N',
				'CODE_OPTION' => 'DEFER_ENVYBOX',
				'NAME_OPTION' => 'Отложить Envybox',
				'OPTION_ACTION' => 'deferEnvybox',
				'OPTION_TYPE' => 'function',
				'LIMITATION' => 'for-everyone',
			],
			[
				'ACTIVE' => 'N',
				'CODE_OPTION' => 'DEFER_CALLTOUCH',
				'NAME_OPTION' => 'Отложить Calltouch',
				'OPTION_ACTION' => 'deferCalltouch',
				'OPTION_TYPE' => 'function',
				'LIMITATION' => 'for-everyone',
			],
			[
				'ACTIVE' => 'N',
				'CODE_OPTION' => 'DEFER_CDN_INPUTMASK',
				'NAME_OPTION' => 'Отложить Inputmask (CDN)',
				'OPTION_ACTION' => 'deferCdnInputmask',
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

		self::disableObsoleteCutOptions();
	}

	/**
	 * Скрытые «Вырезать…» (Метрика/GA/GTM): снять ACTIVE, чтобы не отрабатывали из БД.
	 */
	public static function disableObsoleteCutOptions(): void
	{
		$obsolete = ['YANDEX_METRIKA', 'GOOGLE_ANALYTICS', 'GOOGLE_TAG_MANAGER'];
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
