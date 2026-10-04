<?php

namespace Tools\GooglePageSpeed;

class OptionActions
{
	/** @var array<string, array{0: class-string, 1: string}> */
	private const ACTIONS = [
		'eliminateStyleSheetsThatBlockDisplay' => [self::class, 'eliminateStyleSheetsThatBlockDisplay'],
		'eliminateScriptsThatBlockDisplay' => [self::class, 'eliminateScriptsThatBlockDisplay'],
		'addLoadingLazyAttributeAllTagsImg' => [self::class, 'addLoadingLazyAttributeAllTagsImg'],
		'addDecodingAsyncAttributeAllTagsImg' => [self::class, 'addDecodingAsyncAttributeAllTagsImg'],
		'deferYandexMetrika' => [ScriptDeferral::class, 'deferYandexMetrika'],
		'deferGoogleAnalytics' => [ScriptDeferral::class, 'deferGoogleAnalytics'],
		// 'deferJivoChat' => [ScriptDeferral::class, 'deferJivoChat'],
	];

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

	public static function eliminateScriptsThatBlockDisplay(&$content)
	{
		$content = preg_replace_callback(
			'/<script\b[^>]*\bsrc\s*=[^>]*>/i',
			static function ($match) {
				$tag = $match[0];
				if (preg_match('/\b(?:async|defer)\b/i', $tag)) {
					return $tag;
				}

				return preg_replace('/<script\b/i', '<script defer', $tag, 1);
			},
			$content
		);
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
