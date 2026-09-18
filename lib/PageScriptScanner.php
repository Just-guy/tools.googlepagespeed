<?php

namespace Tools\GooglePageSpeed;

use Bitrix\Main\Web\HttpClient;
use Bitrix\Main\Web\Uri;

/**
 * HTTP-скан публичной страницы: собирает &lt;script src&gt;, фильтрует и классифицирует.
 */
class PageScriptScanner
{
	private const TIMEOUT = 12;
	private const MAX_BODY_BYTES = 2097152; // 2 MiB

	/**
	 * @param string[] $existingPublicParts уже добавленные в правила STRING_PUBLIC_PART
	 * @return array{
	 *   ok: bool,
	 *   error: ?string,
	 *   stats: array<string, int>,
	 *   scripts: list<array<string, mixed>>
	 * }
	 */
	public static function scan(string $pageUrl, array $existingPublicParts = []): array
	{
		$pageUrl = trim($pageUrl);
		if ($pageUrl === '') {
			return self::fail('Укажите URL страницы.');
		}

		if (!preg_match('#^https?://#i', $pageUrl)) {
			$pageUrl = 'https://' . ltrim($pageUrl, '/');
		}

		$uri = new Uri($pageUrl);
		if ($uri->getHost() === '') {
			return self::fail('Некорректный URL.');
		}

		$http = new HttpClient([
			'redirect' => true,
			'redirectMax' => 5,
			'socketTimeout' => self::TIMEOUT,
			'streamTimeout' => self::TIMEOUT,
			'version' => HttpClient::HTTP_1_1,
		]);
		$http->setHeader('User-Agent', 'tools.googlepagespeed-scanner/1.0');
		$http->setHeader('Accept', 'text/html,application/xhtml+xml;q=0.9,*/*;q=0.8');

		$body = $http->get($pageUrl);
		$status = (int)$http->getStatus();

		if ($body === false || $status < 200 || $status >= 400) {
			return self::fail(
				$status > 0
					? 'Страница недоступна (HTTP ' . $status . ').'
					: 'Не удалось загрузить страницу (таймаут или сеть).'
			);
		}

		if (strlen($body) > self::MAX_BODY_BYTES) {
			$body = substr($body, 0, self::MAX_BODY_BYTES);
		}

		if (!preg_match('/<html\b/i', $body) && !preg_match('/<head\b/i', $body)) {
			return self::fail('Ответ не похож на HTML (возможно, редирект на авторизацию или JSON).');
		}

		$finalUrl = $pageUrl;
		if (method_exists($http, 'getEffectiveUrl')) {
			$effective = $http->getEffectiveUrl();
			if (is_string($effective) && $effective !== '') {
				$finalUrl = $effective;
			}
		}
		$parsed = self::extractScripts($body, $finalUrl);

		$stats = [
			'found' => count($parsed),
			'hiddenCore' => 0,
			'hiddenAnalytics' => 0,
			'hiddenNonBlocking' => 0,
			'shown' => 0,
			'presetMatched' => 0,
			'alreadyInRules' => 0,
		];

		$existingNormalized = [];
		foreach ($existingPublicParts as $part) {
			$part = trim((string)$part);
			if ($part !== '') {
				$existingNormalized[] = mb_strtolower($part);
			}
		}

		$scripts = [];
		$seenPublic = [];

		foreach ($parsed as $item) {
			$src = $item['src'];
			$hasAsyncDefer = $item['nonBlocking'];

			if ($hasAsyncDefer) {
				$stats['hiddenNonBlocking']++;
				continue;
			}

			$hide = ScriptScanCatalog::matchHide($src);
			if ($hide !== null) {
				if ($hide['reason'] === 'core') {
					$stats['hiddenCore']++;
				} else {
					$stats['hiddenAnalytics']++;
				}
				continue;
			}

			$preset = ScriptScanCatalog::matchPreset($src);
			$publicPart = $preset['publicPart'] ?? self::suggestPublicPart($src);

			$publicKey = mb_strtolower($publicPart);
			if (isset($seenPublic[$publicKey])) {
				continue;
			}
			$seenPublic[$publicKey] = true;

			$already = false;
			foreach ($existingNormalized as $existing) {
				if ($existing === $publicKey || str_contains($src, $existing) || str_contains($existing, $publicKey)) {
					$already = true;
					break;
				}
			}

			if ($already) {
				$stats['alreadyInRules']++;
			}

			$row = [
				'src' => $src,
				'publicPart' => $publicPart,
				'preset' => $preset,
				'alreadyInRules' => $already,
				'autoAdd' => $preset !== null && !$already,
				'attribute' => $preset['attribute'] ?? 'defer',
			];

			if ($preset !== null) {
				$stats['presetMatched']++;
			}

			$scripts[] = $row;
			$stats['shown']++;
		}

		usort($scripts, static function ($a, $b) {
			$ap = $a['preset'] !== null ? 0 : 1;
			$bp = $b['preset'] !== null ? 0 : 1;
			if ($ap !== $bp) {
				return $ap - $bp;
			}
			return strcmp($a['publicPart'], $b['publicPart']);
		});

		return [
			'ok' => true,
			'error' => null,
			'stats' => $stats,
			'scripts' => $scripts,
		];
	}

	/**
	 * @return list<array{src: string, nonBlocking: bool}>
	 */
	private static function extractScripts(string $html, string $baseUrl): array
	{
		$result = [];
		if (!preg_match_all('/<script\b([^>]*)>/i', $html, $matches, PREG_SET_ORDER)) {
			return $result;
		}

		foreach ($matches as $match) {
			$attrs = $match[1];
			if (!preg_match('/\bsrc\s*=\s*(["\'])([^"\']+)\1/i', $attrs, $srcMatch)
				&& !preg_match('/\bsrc\s*=\s*([^\s>]+)/i', $attrs, $srcMatch)
			) {
				continue;
			}

			$rawSrc = html_entity_decode(trim($srcMatch[2] ?? $srcMatch[1], " \t\"'"), ENT_QUOTES | ENT_HTML5, 'UTF-8');
			if ($rawSrc === '' || str_starts_with($rawSrc, 'data:')) {
				continue;
			}

			$absolute = self::resolveUrl($baseUrl, $rawSrc);
			$normalized = self::normalizeSrc($absolute);
			if ($normalized === '') {
				continue;
			}

			$nonBlocking = (bool)preg_match('/\b(?:async|defer)\b/i', $attrs);

			$result[] = [
				'src' => $normalized,
				'nonBlocking' => $nonBlocking,
			];
		}

		return $result;
	}

	public static function normalizeSrc(string $src): string
	{
		$src = trim($src);
		if ($src === '') {
			return '';
		}

		$parts = parse_url($src);
		if ($parts === false) {
			return preg_replace('/[?#].*$/', '', $src) ?? $src;
		}

		$scheme = isset($parts['scheme']) ? strtolower($parts['scheme']) . '://' : '';
		$host = $parts['host'] ?? '';
		$port = isset($parts['port']) ? ':' . $parts['port'] : '';
		$path = $parts['path'] ?? '';

		if ($host !== '') {
			return $scheme . $host . $port . $path;
		}

		return $path !== '' ? $path : preg_replace('/[?#].*$/', '', $src) ?? $src;
	}

	public static function suggestPublicPart(string $normalizedSrc): string
	{
		$parts = parse_url($normalizedSrc);
		if ($parts === false) {
			return mb_substr($normalizedSrc, 0, 120);
		}

		$path = (string)($parts['path'] ?? '');
		$host = (string)($parts['host'] ?? '');

		if ($path !== '' && str_starts_with($path, '/')) {
			// Локальные и относительные пути — без query (уже срезан)
			if ($host === '' || self::isLikelySameSitePath($path)) {
				return mb_substr($path, 0, 160);
			}
			// Внешний: distinctive host + короткий хвост файла
			$file = basename($path);
			if ($file !== '' && $file !== '/') {
				return mb_substr($host . '/' . $file, 0, 160);
			}
			return mb_substr($host . $path, 0, 160);
		}

		if ($host !== '') {
			return mb_substr($host, 0, 120);
		}

		return mb_substr($normalizedSrc, 0, 120);
	}

	private static function isLikelySameSitePath(string $path): bool
	{
		return (bool)preg_match('#^/(local|upload|images|js|scripts|assets|static)/#i', $path);
	}

	private static function resolveUrl(string $baseUrl, string $src): string
	{
		if (preg_match('#^https?://#i', $src)) {
			return $src;
		}
		if (str_starts_with($src, '//')) {
			$scheme = parse_url($baseUrl, PHP_URL_SCHEME) ?: 'https';
			return $scheme . ':' . $src;
		}

		$base = new Uri($baseUrl);
		if (str_starts_with($src, '/')) {
			$port = $base->getPort();
			$portPart = ($port && $port != 80 && $port != 443) ? ':' . $port : '';
			return $base->getScheme() . '://' . $base->getHost() . $portPart . $src;
		}

		$basePath = $base->getPath() ?: '/';
		$dir = preg_replace('#/[^/]*$#', '/', $basePath) ?: '/';
		$port = $base->getPort();
		$portPart = ($port && $port != 80 && $port != 443) ? ':' . $port : '';

		return $base->getScheme() . '://' . $base->getHost() . $portPart . $dir . $src;
	}

	/**
	 * @return array{ok: bool, error: string, stats: array<string, int>, scripts: array}
	 */
	private static function fail(string $message): array
	{
		return [
			'ok' => false,
			'error' => $message,
			'stats' => [
				'found' => 0,
				'hiddenCore' => 0,
				'hiddenAnalytics' => 0,
				'hiddenNonBlocking' => 0,
				'shown' => 0,
				'presetMatched' => 0,
				'alreadyInRules' => 0,
			],
			'scripts' => [],
		];
	}
}
