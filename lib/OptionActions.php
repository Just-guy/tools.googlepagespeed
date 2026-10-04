<?php

namespace Tools\GooglePageSpeed;

class OptionActions
{
	/** @var array<string, array{0: class-string, 1: string}> */
	private const ACTIONS = [
		'eliminateStyleSheetsThatBlockDisplay' => [self::class, 'eliminateStyleSheetsThatBlockDisplay'],
		'eliminateScriptsGeneralJs' => [self::class, 'eliminateScriptsGeneralJs'],
		'eliminateScriptsAsproJs' => [self::class, 'eliminateScriptsAsproJs'],
		'cutYandexMetrika' => [self::class, 'cutYandexMetrika'],
		'addLoadingLazyAttributeAllTagsImg' => [self::class, 'addLoadingLazyAttributeAllTagsImg'],
		'addDecodingAsyncAttributeAllTagsImg' => [self::class, 'addDecodingAsyncAttributeAllTagsImg'],
		'deferYandexMetrika' => [ScriptDeferral::class, 'deferYandexMetrika'],
		'deferGoogleAnalytics' => [ScriptDeferral::class, 'deferGoogleAnalytics'],
		'deferGoogleTagManager' => [ScriptDeferral::class, 'deferGoogleTagManager'],
		'deferRoistat' => [ScriptDeferral::class, 'deferRoistat'],
		'deferEnvybox' => [ScriptDeferral::class, 'deferEnvybox'],
		'deferCalltouch' => [ScriptDeferral::class, 'deferCalltouch'],
		'deferCdnInputmask' => [ScriptDeferral::class, 'deferCdnInputmask'],
		// 'deferJivoChat' => [ScriptDeferral::class, 'deferJivoChat'],
	];

	/** Общий JS вне ядра Bitrix (jQuery и т.п.). */
	private const GENERAL_JS_SRC = '/(?:\/(?:js\/)?jquery(?:-\d+(?:\.\d+)*)?(?:\.min)?\.js(?:\?|#|$))/i';

	/** Скрипты решений Aspro в head (speed.min.js и аналоги). */
	private const ASPRO_JS_SRC = '/(?:\/speed\.min\.js(?:\?|#|$))/i';

	private const RB_SCRIPTS_OPEN = '<!--gps-rb-scripts-->';
	private const RB_SCRIPTS_CLOSE = '<!--/gps-rb-scripts-->';

	/** Подстроки src пикселей/счётчиков (в нижнем регистре). */
	private const IMG_PIXEL_MARKERS = [
		'mc.yandex.',
		'an.yandex.ru',
		'google-analytics.com',
		'googletagmanager.com',
		'googleadservices.com',
		'facebook.com/tr',
		'vk.com/rtrg',
		'top-fwz1.mail.ru',
		'counter.yadro.ru',
		'bat.bing.com',
	];

	/**
	 * Опции атрибутов img для install / миграции БД.
	 */
	public static function getImgAttributeOptionDefinitions(): array
	{
		return [
			[
				'ACTIVE' => 'N',
				'CODE_OPTION' => 'ADD_DECODING_ASYNC_ATTRIBUTE_ALL_TAGS_IMG',
				'NAME_OPTION' => 'Добавить decoding="async" остальным тэгам img',
				'OPTION_ACTION' => 'addDecodingAsyncAttributeAllTagsImg',
				'OPTION_TYPE' => 'function',
				'LIMITATION' => 'for-everyone',
			],
		];
	}

	/**
	 * Добавляет опции img-атрибутов в БД / убирает устаревшие.
	 */
	public static function ensureImgAttributeOptions(): void
	{
		$existing = [];
		foreach (SettingsProvider::getOptions([]) as $row) {
			$code = (string)($row['CODE_OPTION'] ?? '');
			if ($code !== '') {
				$existing[$code] = (int)($row['ID'] ?? 0);
			}
		}

		$changed = false;

		// Устаревшая опция fetchpriority (авто-LCP на img оказалась ненадёжной)
		$obsoleteCodes = ['ADD_FETCHPRIORITY_HIGH_FIRST_IMG'];
		foreach ($obsoleteCodes as $code) {
			if (!empty($existing[$code])) {
				GPSOptionsTable::delete($existing[$code]);
				unset($existing[$code]);
				$changed = true;
			}
		}

		foreach (self::getImgAttributeOptionDefinitions() as $def) {
			if (isset($existing[$def['CODE_OPTION']])) {
				continue;
			}
			GPSOptionsTable::add($def);
			$changed = true;
		}

		if ($changed) {
			SettingsProvider::clearCache();
		}
	}

	public static function run(string $methodName, &$content): bool
	{
		if (!isset(self::ACTIONS[$methodName])) {
			return false;
		}

		[$class, $method] = self::ACTIONS[$methodName];
		$class::$method($content);

		return true;
	}

	public static function eliminateStyleSheetsThatBlockDisplay(&$content)
	{
		$content = preg_replace_callback(
			'/<link\b[^>]*>/i',
			static function ($match) {
				$tag = $match[0];
				if (!self::linkRelIsBlockingStylesheet($tag)) {
					return $tag;
				}

				$media = self::linkAttribute($tag, 'media');
				if ($media !== null && strcasecmp(trim($media), 'print') === 0) {
					return $tag;
				}
				if (self::linkAttribute($tag, 'onload') !== null) {
					return $tag;
				}

				$applyMedia = 'all';
				if ($media !== null && trim($media) !== '' && !preg_match('/^(all|screen)$/i', trim($media))) {
					$applyMedia = trim($media);
				}
				$applyMedia = str_replace(['\\', "'"], ['\\\\', "\\'"], $applyMedia);

				$deferred = preg_replace(
					'/(?:^|\s)media\s*=\s*(?:"[^"]*"|\'[^\']*\'|[^\s"\'=<>`]+)/i',
					'',
					$tag
				);
				$deferred = preg_replace(
					'/\s*\/?>$/',
					' media="print" onload="this.media=\'' . $applyMedia . '\'"$0',
					$deferred,
					1
				);

				return $deferred . '<noscript>' . $tag . '</noscript>';
			},
			$content
		);
	}

	/**
	 * rel именно stylesheet и без alternate. Не путать с this.rel внутри onload.
	 */
	private static function linkRelIsBlockingStylesheet(string $tag): bool
	{
		$rel = self::linkAttribute($tag, 'rel');
		if ($rel === null || trim($rel) === '') {
			return false;
		}

		$tokens = preg_split('/\s+/', strtolower(trim($rel)));
		return in_array('stylesheet', $tokens, true) && !in_array('alternate', $tokens, true);
	}

	/**
	 * Значение атрибута тега link. Имя должно быть отдельным атрибутом, не суффиксом (this.rel, data-rel).
	 */
	private static function linkAttribute(string $tag, string $name): ?string
	{
		if (!preg_match(
			'/(?:^|\s)' . preg_quote($name, '/') . '\s*=\s*(?:"([^"]*)"|\'([^\']*)\'|([^\s"\'=<>`]+))/i',
			$tag,
			$m
		)) {
			return null;
		}

		if ($m[1] !== '') {
			return $m[1];
		}
		if ($m[2] !== '') {
			return $m[2];
		}
		if (($m[3] ?? '') !== '') {
			return $m[3];
		}

		return '';
	}

	/**
	 * Вырезать Яндекс.Метрику из HTML (модуль yandex.metrika + классический счётчик).
	 * Надёжнее старых regexp в БД: Bitrix вставляет /bitrix/js/yandex.metrika/script.js
	 * и блок <!-- Yandex.Metrika counter -->, а не только «ручной» сниппет.
	 */
	public static function cutYandexMetrika(&$content): void
	{
		$content = preg_replace(
			'/<!--\s*Yandex\.Metrika counter\s*-->.*?<!--\s*\/Yandex\.Metrika counter\s*-->/is',
			'',
			$content
		);

		$content = preg_replace(
			'/<script\b[^>]*\bsrc\s*=\s*([\'"])[^\'"]*(?:\/yandex\.metrika\/|mc\.yandex\.(?:ru|com)\/metrika)[^\'"]*\1[^>]*>\s*<\/script>/is',
			'',
			$content
		);

		$content = preg_replace(
			'/<script\b[^>]*>[^<]*\(function\s*\(\s*m\s*,\s*e\s*,\s*t\s*,\s*r\s*,\s*i\s*,\s*k\s*,\s*a\s*\).*?mc\.yandex\.(?:ru|com)[^<]*<\/script>/is',
			'',
			$content
		);

		$content = preg_replace(
			'/<noscript\b[^>]*>.*?mc\.yandex\.(?:ru|com)[^<]*<\/noscript>/is',
			'',
			$content
		);

		$content = preg_replace(
			'/<script\b[^>]*>\s*window\.dataLayerName\s*=.*?<\/script>/is',
			'',
			$content
		);

		$content = preg_replace(
			'/<script\b[^>]*>\s*window\[window\.dataLayerName\]\s*=.*?<\/script>/is',
			'',
			$content
		);

		$content = preg_replace(
			'/<script\b[^>]*>\s*window\.counters\s*=\s*\[[^\]]*\];\s*<\/script>/is',
			'',
			$content
		);
	}

	/**
	 * Перевести опцию YANDEX_METRIKA с устаревших regexp на cutYandexMetrika.
	 */
	public static function ensureYandexMetrikaCutOption(): void
	{
		foreach (SettingsProvider::getOptions([]) as $row) {
			if ((string)($row['CODE_OPTION'] ?? '') !== 'YANDEX_METRIKA') {
				continue;
			}

			$type = (string)($row['OPTION_TYPE'] ?? '');
			$action = (string)($row['OPTION_ACTION'] ?? '');
			if ($type === 'function' && $action === 'cutYandexMetrika') {
				return;
			}

			GPSOptionsTable::update((int)$row['ID'], [
				'NAME_OPTION' => 'Вырезать скрипты Яндекс метрики',
				'OPTION_ACTION' => 'cutYandexMetrika',
				'OPTION_TYPE' => 'function',
			]);
			SettingsProvider::clearCache();
			return;
		}
	}

	/**
	 * Общий JS (jQuery и др.): убрать render-blocking из <head>.
	 * Не defer — перенос сразу после <body>, до mid-body inline (CheckTopMenuDotted и т.п.).
	 */
	public static function eliminateScriptsGeneralJs(&$content)
	{
		self::relocateMatchingHeadScripts($content, self::GENERAL_JS_SRC);
	}

	/**
	 * Aspro JS (speed.min.js и др. шаблонные в head).
	 */
	public static function eliminateScriptsAsproJs(&$content)
	{
		self::relocateMatchingHeadScripts($content, self::ASPRO_JS_SRC);
	}

	/**
	 * @deprecated Оставлено для совместимости; используйте eliminateScriptsGeneralJs / AsproJs.
	 */
	public static function eliminateScriptsThatBlockDisplay(&$content)
	{
		self::eliminateScriptsGeneralJs($content);
		self::eliminateScriptsAsproJs($content);
	}

	/**
	 * Вырезать sync &lt;script src&gt; из &lt;head&gt; по allowlist и вставить после &lt;body&gt;
	 * в общий блок <!--gps-rb-scripts-->, чтобы несколько опций сохраняли порядок вызовов.
	 */
	private static function relocateMatchingHeadScripts(string &$content, string $allowSrc): void
	{
		$headEnd = stripos($content, '</head>');
		if ($headEnd === false) {
			return;
		}

		$head = substr($content, 0, $headEnd);
		$rest = substr($content, $headEnd);
		$moved = [];

		$head = preg_replace_callback(
			'/<script\b([^>]*)>(.*?)<\/script>/is',
			static function ($match) use ($allowSrc, &$moved) {
				$attrs = $match[1];
				if (!preg_match('/\bsrc\s*=\s*([\'"])(.*?)\1/i', $attrs, $srcMatch)) {
					return $match[0];
				}
				if (preg_match('/\b(?:async|defer)\b/i', $attrs)) {
					return $match[0];
				}
				if (!preg_match($allowSrc, $srcMatch[2])) {
					return $match[0];
				}

				$moved[] = $match[0];
				return '';
			},
			$head
		);

		if ($moved === []) {
			return;
		}

		$chunk = implode("\n", $moved) . "\n";
		$content = $head . $rest;

		$open = self::RB_SCRIPTS_OPEN;
		$close = self::RB_SCRIPTS_CLOSE;

		if (preg_match(
			'/' . preg_quote($open, '/') . '(.*?)' . preg_quote($close, '/') . '/is',
			$content,
			$blockMatch,
			PREG_OFFSET_CAPTURE
		)) {
			$inner = $blockMatch[1][0] . $chunk;
			$replacement = $open . "\n" . $inner . $close;
			$content = substr_replace($content, $replacement, (int)$blockMatch[0][1], strlen($blockMatch[0][0]));
			return;
		}

		$wrapped = $open . "\n" . $chunk . $close . "\n";
		if (preg_match('/<body\b[^>]*>/i', $content, $bodyMatch, PREG_OFFSET_CAPTURE)) {
			$pos = (int)$bodyMatch[0][1] + strlen($bodyMatch[0][0]);
			$content = substr($content, 0, $pos) . "\n" . $wrapped . substr($content, $pos);
			return;
		}

		if (preg_match('/<\/body>/i', $content)) {
			$content = preg_replace('/<\/body>/i', $wrapped . '</body>', $content, 1);
			return;
		}

		$content .= $wrapped;
	}

	/**
	 * Заголовок + подпункты «Общий JS» / «Aspro Js» для eliminate scripts.
	 */
	public static function ensureEliminateScriptsOption(): void
	{
		$byCode = [];
		foreach (SettingsProvider::getOptions([]) as $row) {
			$code = (string)($row['CODE_OPTION'] ?? '');
			if ($code !== '') {
				$byCode[$code] = $row;
			}
		}

		$changed = false;

		if (isset($byCode['ELIMINATE_SCRIPTS_THAT_BLOCK_DISPLAY'])) {
			$parent = $byCode['ELIMINATE_SCRIPTS_THAT_BLOCK_DISPLAY'];
			$needUpdate =
				($parent['OPTION_TYPE'] ?? '') !== 'heading'
				|| (string)($parent['NAME_OPTION'] ?? '') !== 'Устранить скрипты, блокирующие рендеринг'
				|| (string)($parent['OPTION_ACTION'] ?? '') !== '';

			if ($needUpdate) {
				GPSOptionsTable::update((int)$parent['ID'], [
					'ACTIVE' => 'N',
					'NAME_OPTION' => 'Устранить скрипты, блокирующие рендеринг',
					'OPTION_ACTION' => '',
					'OPTION_TYPE' => 'heading',
					'LIMITATION' => 'for-everyone',
				]);
				$changed = true;
			}
			$wasActive = (($parent['ACTIVE'] ?? 'N') === 'Y' && ($parent['OPTION_TYPE'] ?? '') === 'function');
		} else {
			GPSOptionsTable::add([
				'ACTIVE' => 'N',
				'CODE_OPTION' => 'ELIMINATE_SCRIPTS_THAT_BLOCK_DISPLAY',
				'NAME_OPTION' => 'Устранить скрипты, блокирующие рендеринг',
				'OPTION_ACTION' => '',
				'OPTION_TYPE' => 'heading',
				'LIMITATION' => 'for-everyone',
			]);
			$wasActive = false;
			$changed = true;
		}

		$children = [
			[
				'CODE_OPTION' => 'ELIMINATE_SCRIPTS_GENERAL_JS',
				'NAME_OPTION' => 'Общий JS',
				'OPTION_ACTION' => 'eliminateScriptsGeneralJs',
			],
			[
				'CODE_OPTION' => 'ELIMINATE_SCRIPTS_ASPRO_JS',
				'NAME_OPTION' => 'Aspro Js',
				'OPTION_ACTION' => 'eliminateScriptsAsproJs',
			],
		];

		foreach ($children as $child) {
			if (isset($byCode[$child['CODE_OPTION']])) {
				$row = $byCode[$child['CODE_OPTION']];
				$needUpdate =
					(string)($row['NAME_OPTION'] ?? '') !== $child['NAME_OPTION']
					|| (string)($row['OPTION_ACTION'] ?? '') !== $child['OPTION_ACTION']
					|| (string)($row['OPTION_TYPE'] ?? '') !== 'function';
				if ($needUpdate) {
					GPSOptionsTable::update((int)$row['ID'], [
						'NAME_OPTION' => $child['NAME_OPTION'],
						'OPTION_ACTION' => $child['OPTION_ACTION'],
						'OPTION_TYPE' => 'function',
					]);
					$changed = true;
				}
				continue;
			}

			GPSOptionsTable::add([
				'ACTIVE' => $wasActive ? 'Y' : 'N',
				'CODE_OPTION' => $child['CODE_OPTION'],
				'NAME_OPTION' => $child['NAME_OPTION'],
				'OPTION_ACTION' => $child['OPTION_ACTION'],
				'OPTION_TYPE' => 'function',
				'LIMITATION' => 'for-everyone',
			]);
			$changed = true;
		}

		if ($changed) {
			SettingsProvider::clearCache();
		}
	}

	public static function addLoadingLazyAttributeAllTagsImg(&$content)
	{
		$isFirstMeaningful = true;
		$content = self::mapImgTags($content, static function ($tag, $inNoscript) use (&$isFirstMeaningful) {
			if (self::isImgLcpNoise($tag, $inNoscript)) {
				return $tag;
			}
			if ($isFirstMeaningful) {
				$isFirstMeaningful = false;
				return $tag;
			}
			if (preg_match('/\b(?:loading|data-src)\s*=/i', $tag)) {
				return $tag;
			}

			return preg_replace('/<img\b/i', '<img loading="lazy"', $tag, 1);
		});
	}

	public static function addDecodingAsyncAttributeAllTagsImg(&$content)
	{
		$isFirstMeaningful = true;
		$content = self::mapImgTags($content, static function ($tag, $inNoscript) use (&$isFirstMeaningful) {
			if (self::isImgLcpNoise($tag, $inNoscript)) {
				return $tag;
			}
			if ($isFirstMeaningful) {
				$isFirstMeaningful = false;
				return $tag;
			}
			if (preg_match('/\b(?:decoding|data-src)\s*=/i', $tag)) {
				return $tag;
			}

			return preg_replace('/<img\b/i', '<img decoding="async"', $tag, 1);
		});
	}

	/**
	 * Обходит все `<img>` с учётом смещения (для детекта noscript).
	 *
	 * @param callable(string $tag, bool $inNoscript): string $callback
	 */
	private static function mapImgTags(string $content, callable $callback): string
	{
		if (!preg_match_all('/<img\b[^>]*>/i', $content, $matches, PREG_OFFSET_CAPTURE)) {
			return $content;
		}

		$noscriptRanges = self::collectNoscriptRanges($content);
		$result = '';
		$last = 0;

		foreach ($matches[0] as [$tag, $offset]) {
			$result .= substr($content, $last, $offset - $last);
			$inNoscript = self::isOffsetInRanges((int)$offset, $noscriptRanges);
			$result .= $callback($tag, $inNoscript);
			$last = $offset + strlen($tag);
		}

		return $result . substr($content, $last);
	}

	/**
	 * @return list<array{0: int, 1: int}>
	 */
	private static function collectNoscriptRanges(string $content): array
	{
		$ranges = [];
		if (!preg_match_all('/<noscript\b[^>]*>.*?<\/noscript>/is', $content, $matches, PREG_OFFSET_CAPTURE)) {
			return $ranges;
		}

		foreach ($matches[0] as [$block, $offset]) {
			$ranges[] = [(int)$offset, (int)$offset + strlen($block)];
		}

		return $ranges;
	}

	/**
	 * @param list<array{0: int, 1: int}> $ranges
	 */
	private static function isOffsetInRanges(int $offset, array $ranges): bool
	{
		foreach ($ranges as [$start, $end]) {
			if ($offset >= $start && $offset < $end) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Шум для LCP: noscript, пиксели счётчиков, data:, 1×1, скрытые.
	 */
	private static function isImgLcpNoise(string $tag, bool $inNoscript): bool
	{
		if ($inNoscript) {
			return true;
		}

		if (preg_match('/\bdata-src\s*=/i', $tag) && !preg_match('/\bsrc\s*=/i', $tag)) {
			return true;
		}

		if (!preg_match('/\bsrc\s*=\s*(?:"([^"]*)"|\'([^\']*)\'|([^\s>]+))/i', $tag, $m)) {
			return true;
		}

		$src = trim((string)($m[1] ?: $m[2] ?: $m[3] ?: ''));
		if ($src === '' || stripos($src, 'data:') === 0) {
			return true;
		}

		$srcLower = strtolower($src);
		foreach (self::IMG_PIXEL_MARKERS as $marker) {
			if (strpos($srcLower, $marker) !== false) {
				return true;
			}
		}

		if (preg_match('/(?:left\s*:\s*-\d{3,}px|display\s*:\s*none)/i', $tag)) {
			return true;
		}

		if (
			preg_match('/\bwidth\s*=\s*["\']?1["\']?(?:\s|>|\/)/i', $tag)
			&& preg_match('/\bheight\s*=\s*["\']?1["\']?(?:\s|>|\/)/i', $tag)
		) {
			return true;
		}

		return false;
	}
}
