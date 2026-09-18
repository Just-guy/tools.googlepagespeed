<?php

namespace Tools\GooglePageSpeed;

use Bitrix\Main\Web\HttpClient;
use Bitrix\Main\Web\Uri;

/**
 * HTTP-скан публичной страницы: собирает &lt;script src&gt;, фильтрует и классифицирует.
 * Поддерживает несколько URL в одном запросе (через запятую, ; или перевод строки).
 */
class PageScriptScanner
{
	private const TIMEOUT = 12;
	private const MAX_BODY_BYTES = 2097152; // 2 MiB
	private const MAX_URLS = 5;

	/**
	 * @param string[] $existingPublicParts уже добавленные в правила STRING_PUBLIC_PART
	 * @return array{
	 *   ok: bool,
	 *   error: ?string,
	 *   stats: array<string, int>,
	 *   scripts: list<array<string, mixed>>,
	 *   scannedUrls: list<string>,
	 *   failedUrls: list<array{url: string, error: string}>
	 * }
	 */
	public static function scan(string $pageUrlInput, array $existingPublicParts = []): array
	{
		$urls = self::parseUrlList($pageUrlInput);
		if ($urls === []) {
			return self::fail('Укажите URL страницы.');
		}

		if (count($urls) > self::MAX_URLS) {
			return self::fail('Слишком много URL (максимум ' . self::MAX_URLS . ').');
		}

		$existingNormalized = [];
		foreach ($existingPublicParts as $part) {
			$part = trim((string)$part);
			if ($part !== '') {
				$existingNormalized[] = mb_strtolower($part);
			}
		}

		$stats = self::emptyStats();
		$scripts = [];
		$seenPublic = [];
		$scannedUrls = [];
		$failedUrls = [];

		foreach ($urls as $pageUrl) {
			$one = self::fetchParsedScripts($pageUrl);
			if (!$one['ok']) {
				$failedUrls[] = ['url' => $pageUrl, 'error' => (string)$one['error']];
				continue;
			}

			$scannedUrls[] = $pageUrl;
			$stats['found'] += count($one['parsed']);

			foreach ($one['parsed'] as $item) {
				self::classifyInto(
					$item,
					$stats,
					$scripts,
					$seenPublic,
					$existingNormalized
				);
			}
		}

		if ($scannedUrls === []) {
			$firstError = $failedUrls[0]['error'] ?? 'Не удалось загрузить страницы.';
			if (count($failedUrls) > 1) {
				$firstError .= ' (ошибок: ' . count($failedUrls) . ')';
			}
			if (self::listHasLoopbackHost($urls)) {
				$firstError .= ' Для localhost укажите домен из настроек сайта Bitrix (SERVER_NAME)'
					. ' или URL, доступный PHP с этой среды (OpenServer / VM / контейнер).';
			}
			return self::fail($firstError);
		}

		usort($scripts, static function ($a, $b) {
			$ap = $a['preset'] !== null ? 0 : 1;
			$bp = $b['preset'] !== null ? 0 : 1;
			if ($ap !== $bp) {
				return $ap - $bp;
			}
			return strcmp($a['publicPart'], $b['publicPart']);
		});

		$error = null;
		if ($failedUrls !== []) {
			$parts = [];
			foreach ($failedUrls as $fail) {
				$parts[] = $fail['url'] . ' — ' . $fail['error'];
			}
			$error = 'Часть URL не удалось просканировать: ' . implode('; ', $parts);
		}

		return [
			'ok' => true,
			'error' => $error,
			'stats' => $stats,
			'scripts' => $scripts,
			'scannedUrls' => $scannedUrls,
			'failedUrls' => $failedUrls,
		];
	}

	/**
	 * Разбор списка URL: перевод строки, запятая или точка с запятой.
	 *
	 * @return list<string>
	 */
	public static function parseUrlList(string $raw): array
	{
		$raw = trim($raw);
		if ($raw === '') {
			return [];
		}

		$chunks = preg_split('/[\n\r,;]+/', $raw) ?: [];
		$urls = [];
		$seen = [];

		foreach ($chunks as $chunk) {
			$url = self::normalizePageUrl(trim($chunk));
			if ($url === '') {
				continue;
			}
			$key = mb_strtolower($url);
			if (isset($seen[$key])) {
				continue;
			}
			$seen[$key] = true;
			$urls[] = $url;
		}

		return $urls;
	}

	private static function normalizePageUrl(string $pageUrl): string
	{
		if ($pageUrl === '') {
			return '';
		}

		// Только путь — без хоста сканер не знает сайт (в UI пресеты клеят origin).
		if (str_starts_with($pageUrl, '/') && !str_starts_with($pageUrl, '//')) {
			return '';
		}

		if (str_starts_with($pageUrl, '//')) {
			$pageUrl = 'https:' . $pageUrl;
		} elseif (!preg_match('#^https?://#i', $pageUrl)) {
			$pageUrl = 'https://' . ltrim($pageUrl, '/');
		}

		$uri = new Uri($pageUrl);
		if ($uri->getHost() === '') {
			return '';
		}

		return $pageUrl;
	}

	/**
	 * Origin публичного сайта для пресетов сканера (scheme://SERVER_NAME).
	 * Без привязки к Docker / OpenServer / VM — берём домен сайта Bitrix.
	 */
	public static function getPublicOrigin(): string
	{
		$host = self::getSiteServerHost();
		$scheme = 'http';

		try {
			$request = \Bitrix\Main\Context::getCurrent()->getRequest();
			if ($request->isHttps()) {
				$scheme = 'https';
			}
			if ($host === '') {
				$httpHost = trim((string)$request->getHttpHost());
				if ($httpHost !== '') {
					$host = $httpHost;
				}
			}
		} catch (\Throwable $e) {
			// CLI / нет контекста
		}

		if ($host === '') {
			return '';
		}

		return $scheme . '://' . $host;
	}

	/**
	 * @return array{ok: bool, error: ?string, parsed: list<array{src: string, nonBlocking: bool}>}
	 */
	private static function fetchParsedScripts(string $pageUrl): array
	{
		$http = new HttpClient([
			'redirect' => true,
			'redirectMax' => 5,
			'socketTimeout' => self::TIMEOUT,
			'streamTimeout' => self::TIMEOUT,
			'version' => HttpClient::HTTP_1_1,
		]);
		$http->setHeader('User-Agent', 'tools.googlepagespeed-scanner/1.0');
		$http->setHeader('Accept', 'text/html,application/xhtml+xml;q=0.9,*/*;q=0.8');

		$fetchUrl = self::resolveFetchUrl($pageUrl);

		$body = $http->get($fetchUrl);
		$status = (int)$http->getStatus();

		if ($body === false || $status < 200 || $status >= 400) {
			return [
				'ok' => false,
				'error' => $status > 0 ? 'HTTP ' . $status : 'таймаут или сеть',
				'parsed' => [],
			];
		}

		if (strlen($body) > self::MAX_BODY_BYTES) {
			$body = substr($body, 0, self::MAX_BODY_BYTES);
		}

		if (!preg_match('/<html\b/i', $body) && !preg_match('/<head\b/i', $body)) {
			return [
				'ok' => false,
				'error' => 'ответ не HTML',
				'parsed' => [],
			];
		}

		$finalUrl = $fetchUrl;
		if (method_exists($http, 'getEffectiveUrl')) {
			$effective = $http->getEffectiveUrl();
			if (is_string($effective) && $effective !== '') {
				$finalUrl = $effective;
			}
		}

		return [
			'ok' => true,
			'error' => null,
			'parsed' => self::extractScripts($body, $finalUrl),
		];
	}

	/**
	 * Loopback (localhost / 127.0.0.1) часто недоступен PHP как «сайт»
	 * (отдельный контейнер, другая VM, другой vhost). Тогда ходим на SERVER_NAME сайта.
	 * Если SERVER_NAME тоже loopback или пуст — оставляем URL как есть (типичный OpenServer).
	 */
	private static function resolveFetchUrl(string $pageUrl): string
	{
		$uri = new Uri($pageUrl);
		$host = strtolower((string)$uri->getHost());

		if (!self::isLoopbackHost($host)) {
			return $pageUrl;
		}

		$siteHost = self::getSiteServerHost();
		$siteHostOnly = strtolower((string)preg_replace('/:\d+$/', '', $siteHost));
		if ($siteHost === '' || self::isLoopbackHost($siteHostOnly)) {
			return $pageUrl;
		}

		$scheme = $uri->getScheme() ?: 'http';
		$path = $uri->getPath();
		if ($path === '' || $path === null) {
			$path = '/';
		}
		$query = $uri->getQuery();
		$fetchUrl = $scheme . '://' . $siteHost . $path;
		if (is_string($query) && $query !== '') {
			$fetchUrl .= '?' . $query;
		}

		return $fetchUrl;
	}

	/** Домен из настроек сайта Bitrix (без схемы). */
	private static function getSiteServerHost(): string
	{
		if (!class_exists(\CSite::class)) {
			return '';
		}

		$by = 'sort';
		$order = 'asc';
		$res = \CSite::GetList($by, $order, ['ACTIVE' => 'Y', 'DEFAULT' => 'Y']);
		if ($row = $res->Fetch()) {
			$host = trim((string)($row['SERVER_NAME'] ?? ''));
			if ($host !== '') {
				return $host;
			}
		}

		$res = \CSite::GetList($by, $order, ['ACTIVE' => 'Y']);
		if ($row = $res->Fetch()) {
			return trim((string)($row['SERVER_NAME'] ?? ''));
		}

		return '';
	}

	private static function isLoopbackHost(string $host): bool
	{
		return $host === 'localhost'
			|| $host === '127.0.0.1'
			|| $host === '::1'
			|| $host === '[::1]';
	}

	/** @param list<string> $urls */
	private static function listHasLoopbackHost(array $urls): bool
	{
		foreach ($urls as $url) {
			$host = strtolower((string)(parse_url($url, PHP_URL_HOST) ?: ''));
			if (self::isLoopbackHost($host)) {
				return true;
			}
		}
		return false;
	}

	/**
	 * @param array{src: string, nonBlocking: bool} $item
	 * @param array<string, int> $stats
	 * @param list<array<string, mixed>> $scripts
	 * @param array<string, true> $seenPublic
	 * @param list<string> $existingNormalized
	 */
	private static function classifyInto(
		array $item,
		array &$stats,
		array &$scripts,
		array &$seenPublic,
		array $existingNormalized
	): void {
		$src = $item['src'];

		if ($item['nonBlocking']) {
			$stats['hiddenNonBlocking']++;
			return;
		}

		$hide = ScriptScanCatalog::matchHide($src);
		if ($hide !== null) {
			if ($hide['reason'] === 'core') {
				$stats['hiddenCore']++;
			} else {
				$stats['hiddenAnalytics']++;
			}
			return;
		}

		$preset = ScriptScanCatalog::matchPreset($src);
		$publicPart = $preset['publicPart'] ?? self::suggestPublicPart($src);
		$publicKey = mb_strtolower($publicPart);

		if (isset($seenPublic[$publicKey])) {
			return;
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

		if ($preset !== null) {
			$stats['presetMatched']++;
		}

		$scripts[] = [
			'src' => $src,
			'publicPart' => $publicPart,
			'preset' => $preset,
			'alreadyInRules' => $already,
			'autoAdd' => $preset !== null && !$already,
			'attribute' => $preset['attribute'] ?? 'defer',
		];
		$stats['shown']++;
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
			return mb_substr($normalizedSrc, 0, 160);
		}

		$path = (string)($parts['path'] ?? '');
		$host = (string)($parts['host'] ?? '');

		// Свой сайт и локальные пути — только path (без домена), полный каталог.
		if ($path !== '' && str_starts_with($path, '/')) {
			if ($host === '' || self::isLikelySameSitePath($path)) {
				return mb_substr($path, 0, 160);
			}
			// Сторонний хост: host + полный path, без обрезки до имени файла
			return mb_substr($host . $path, 0, 160);
		}

		if ($host !== '') {
			return mb_substr($host, 0, 120);
		}

		return mb_substr($normalizedSrc, 0, 160);
	}

	private static function isLikelySameSitePath(string $path): bool
	{
		// local/upload/… и всё /bitrix/ — path без домена в списке правил
		return (bool)preg_match('#^/(local|upload|images|js|scripts|assets|static|bitrix)/#i', $path);
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

	/** @return array<string, int> */
	private static function emptyStats(): array
	{
		return [
			'found' => 0,
			'hiddenCore' => 0,
			'hiddenAnalytics' => 0,
			'hiddenNonBlocking' => 0,
			'shown' => 0,
			'presetMatched' => 0,
			'alreadyInRules' => 0,
		];
	}

	/**
	 * @return array{ok: bool, error: string, stats: array<string, int>, scripts: array, scannedUrls: array, failedUrls: array}
	 */
	private static function fail(string $message): array
	{
		return [
			'ok' => false,
			'error' => $message,
			'stats' => self::emptyStats(),
			'scripts' => [],
			'scannedUrls' => [],
			'failedUrls' => [],
		];
	}
}
