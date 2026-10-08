<?php

namespace Tools\GooglePageSpeed;

/**
 * Единый каталог опций вкладки «Опции» (левая + правая колонки).
 *
 * Метаданные только для UI / install (в БД не пишутся):
 * - PANEL: main | defer
 * - PARENT: CODE_OPTION родителя-heading (для вложенных строк)
 * - HINT: подсказка под названием в админке
 */
class OptionsDefinitions
{
	/** Поля строки b_gps_options (PANEL / PARENT / HINT в БД не пишутся). */
	private const DB_FIELDS = [
		'ACTIVE',
		'CODE_OPTION',
		'NAME_OPTION',
		'OPTION_ACTION',
		'OPTION_TYPE',
		'LIMITATION',
	];

	/**
	 * Все опции вкладки в порядке UI.
	 *
	 * @return list<array<string, mixed>>
	 */
	public static function getAll(): array
	{
		return [
			[
				'ACTIVE' => 'N',
				'CODE_OPTION' => 'ELIMINATE_STYLE_SHEETS_THAT_BLOCK_DISPLAY',
				'NAME_OPTION' => 'Устранить таблицы стилей, блокирующие рендеринг',
				'OPTION_ACTION' => 'eliminateStyleSheetsThatBlockDisplay',
				'OPTION_TYPE' => 'function',
				'LIMITATION' => 'for-everyone',
				'PANEL' => 'main',
			],
			[
				'ACTIVE' => 'N',
				'CODE_OPTION' => 'NONBLOCKING_GOOGLE_FONTS_CSS',
				'NAME_OPTION' => 'Убрать блокировку рендера у Google Fonts',
				'OPTION_ACTION' => 'deferGoogleFontsStylesheet',
				'OPTION_TYPE' => 'function',
				'LIMITATION' => 'for-everyone',
				'PANEL' => 'main',
				'HINT' => 'Только HTML-тег <link rel="stylesheet" href="https://fonts.googleapis.com/…">. '
					. 'Если шрифт подключён через @import в файле стилей или в <style> — опция его не увидит, необходимо вынесите в <link>.',
			],
			[
				'ACTIVE' => 'N',
				'CODE_OPTION' => 'ELIMINATE_SCRIPTS_THAT_BLOCK_DISPLAY',
				'NAME_OPTION' => 'Устранить скрипты, блокирующие рендеринг',
				'OPTION_ACTION' => '',
				'OPTION_TYPE' => 'heading',
				'LIMITATION' => 'for-everyone',
				'PANEL' => 'main',
			],
			[
				'ACTIVE' => 'N',
				'CODE_OPTION' => 'ELIMINATE_SCRIPTS_GENERAL_JS',
				'NAME_OPTION' => 'Общий JS',
				'OPTION_ACTION' => 'eliminateScriptsGeneralJs',
				'OPTION_TYPE' => 'function',
				'LIMITATION' => 'for-everyone',
				'PANEL' => 'main',
				'PARENT' => 'ELIMINATE_SCRIPTS_THAT_BLOCK_DISPLAY',
			],
			[
				'ACTIVE' => 'N',
				'CODE_OPTION' => 'ELIMINATE_SCRIPTS_ASPRO_JS',
				'NAME_OPTION' => 'Aspro Js',
				'OPTION_ACTION' => 'eliminateScriptsAsproJs',
				'OPTION_TYPE' => 'function',
				'LIMITATION' => 'for-everyone',
				'PANEL' => 'main',
				'PARENT' => 'ELIMINATE_SCRIPTS_THAT_BLOCK_DISPLAY',
			],
			[
				'ACTIVE' => 'N',
				'CODE_OPTION' => 'ADD_LOADING_LAZY_ATTRIBUTE_ALL_TAGS_IMG',
				'NAME_OPTION' => 'Добавить атрибут loading="lazy" всем тэгам img',
				'OPTION_ACTION' => 'addLoadingLazyAttributeAllTagsImg',
				'OPTION_TYPE' => 'function',
				'LIMITATION' => 'for-everyone',
				'PANEL' => 'main',
			],
			[
				'ACTIVE' => 'N',
				'CODE_OPTION' => 'ADD_DECODING_ASYNC_ATTRIBUTE_ALL_TAGS_IMG',
				'NAME_OPTION' => 'Добавить decoding="async" остальным тэгам img',
				'OPTION_ACTION' => 'addDecodingAsyncAttributeAllTagsImg',
				'OPTION_TYPE' => 'function',
				'LIMITATION' => 'for-everyone',
				'PANEL' => 'main',
			],
			[
				'ACTIVE' => 'N',
				'CODE_OPTION' => 'DEFER_YANDEX_METRIKA',
				'NAME_OPTION' => 'Отложить Яндекс.Метрику',
				'OPTION_ACTION' => 'deferYandexMetrika',
				'OPTION_TYPE' => 'function',
				'LIMITATION' => 'for-everyone',
				'PANEL' => 'defer',
			],
			[
				'ACTIVE' => 'N',
				'CODE_OPTION' => 'DEFER_GOOGLE_ANALYTICS',
				'NAME_OPTION' => 'Отложить Google Analytics',
				'OPTION_ACTION' => 'deferGoogleAnalytics',
				'OPTION_TYPE' => 'function',
				'LIMITATION' => 'for-everyone',
				'PANEL' => 'defer',
			],
			[
				'ACTIVE' => 'N',
				'CODE_OPTION' => 'DEFER_GOOGLE_TAG_MANAGER',
				'NAME_OPTION' => 'Отложить Google Tag Manager',
				'OPTION_ACTION' => 'deferGoogleTagManager',
				'OPTION_TYPE' => 'function',
				'LIMITATION' => 'for-everyone',
				'PANEL' => 'defer',
			],
			[
				'ACTIVE' => 'N',
				'CODE_OPTION' => 'DEFER_ROISTAT',
				'NAME_OPTION' => 'Отложить Roistat',
				'OPTION_ACTION' => 'deferRoistat',
				'OPTION_TYPE' => 'function',
				'LIMITATION' => 'for-everyone',
				'PANEL' => 'defer',
			],
			[
				'ACTIVE' => 'N',
				'CODE_OPTION' => 'DEFER_ENVYBOX',
				'NAME_OPTION' => 'Отложить Envybox',
				'OPTION_ACTION' => 'deferEnvybox',
				'OPTION_TYPE' => 'function',
				'LIMITATION' => 'for-everyone',
				'PANEL' => 'defer',
			],
			[
				'ACTIVE' => 'N',
				'CODE_OPTION' => 'DEFER_CALLTOUCH',
				'NAME_OPTION' => 'Отложить Calltouch',
				'OPTION_ACTION' => 'deferCalltouch',
				'OPTION_TYPE' => 'function',
				'LIMITATION' => 'for-everyone',
				'PANEL' => 'defer',
			],
			[
				'ACTIVE' => 'N',
				'CODE_OPTION' => 'DEFER_CDN_INPUTMASK',
				'NAME_OPTION' => 'Отложить Inputmask (CDN)',
				'OPTION_ACTION' => 'deferCdnInputmask',
				'OPTION_TYPE' => 'function',
				'LIMITATION' => 'for-everyone',
				'PANEL' => 'defer',
			],
			// [
			// 	'ACTIVE' => 'N',
			// 	'CODE_OPTION' => 'DEFER_JIVOCHAT',
			// 	'NAME_OPTION' => 'Отложить JivoChat',
			// 	'OPTION_ACTION' => 'deferJivoChat',
			// 	'OPTION_TYPE' => 'function',
			// 	'LIMITATION' => 'for-everyone',
			// 	'PANEL' => 'defer',
			// ],
		];
	}

	/**
	 * @return list<array<string, mixed>>
	 */
	public static function forPanel(string $panel): array
	{
		$out = [];
		foreach (self::getAll() as $def) {
			if (($def['PANEL'] ?? 'main') === $panel) {
				$out[] = $def;
			}
		}

		return $out;
	}

	public static function getByCode(string $code): ?array
	{
		foreach (self::getAll() as $def) {
			if (($def['CODE_OPTION'] ?? '') === $code) {
				return $def;
			}
		}

		return null;
	}

	/**
	 * @return list<string>
	 */
	public static function getChildCodes(string $parentCode): array
	{
		$codes = [];
		foreach (self::getAll() as $def) {
			if (($def['PARENT'] ?? '') === $parentCode) {
				$codes[] = (string)$def['CODE_OPTION'];
			}
		}

		return $codes;
	}

	/**
	 * Скрытые в UI коды (устаревшие «Вырезать…»).
	 *
	 * @return list<string>
	 */
	public static function getHiddenOptionCodes(): array
	{
		return ['YANDEX_METRIKA', 'GOOGLE_ANALYTICS', 'GOOGLE_TAG_MANAGER'];
	}

	/**
	 * Строка для GPSOptionsTable::add / update (без PANEL/PARENT).
	 *
	 * @param array<string, mixed> $def
	 * @return array<string, mixed>
	 */
	public static function toDbRow(array $def): array
	{
		$row = [];
		foreach (self::DB_FIELDS as $field) {
			if (!array_key_exists($field, $def)) {
				continue;
			}
			$row[$field] = $def[$field];
		}

		return $row;
	}

	/**
	 * Все опции вкладки в виде строк БД (порядок UI) — для install.
	 *
	 * @return list<array<string, mixed>>
	 */
	public static function getInstallRows(): array
	{
		$rows = [];
		foreach (self::getAll() as $def) {
			$rows[] = self::toDbRow($def);
		}

		return $rows;
	}
}
