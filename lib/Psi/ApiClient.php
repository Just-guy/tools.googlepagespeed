<?php

namespace Tools\GooglePageSpeed\Psi;

use Bitrix\Main\Web\HttpClient;
use Bitrix\Main\Web\Json;

/**
 * Клиент PageSpeed Insights API v5 (один lab-прогон).
 */
class ApiClient
{
	private const ENDPOINT = 'https://www.googleapis.com/pagespeedonline/v5/runPagespeed';
	private const TIMEOUT = 180;

	/** @var list<string> */
	private const CATEGORIES = [
		'performance',
		'accessibility',
		'best-practices',
		'seo',
	];

	/**
	 * @return array{
	 *   ok: bool,
	 *   error: ?string,
	 *   httpCode: int,
	 *   body: ?array,
	 *   metrics: ?array{
	 *     score: int,
	 *     fcp: ?float,
	 *     lcp: ?float,
	 *     tbt: ?float,
	 *     cls: ?float,
	 *     fcpDisplay: ?string,
	 *     lcpDisplay: ?string,
	 *     tbtDisplay: ?string,
	 *     clsDisplay: ?string
	 *   }
	 * }
	 */
	public static function run(string $url, string $strategy, string $apiKey, string $locale = 'ru'): array
	{
		$strategy = strtolower(trim($strategy));
		if ($strategy !== 'mobile' && $strategy !== 'desktop') {
			return self::fail('Некорректный strategy (нужен mobile или desktop).', 0);
		}

		$apiKey = trim($apiKey);
		if ($apiKey === '') {
			return self::fail('Не задан ключ API PageSpeed Insights.', 0);
		}

		$url = trim($url);
		if ($url === '') {
			return self::fail('Не задан URL для прогона.', 0);
		}

		$parts = [
			'url=' . rawurlencode($url),
			'key=' . rawurlencode($apiKey),
			'strategy=' . rawurlencode($strategy),
			'locale=' . rawurlencode($locale !== '' ? $locale : 'ru'),
		];
		foreach (self::CATEGORIES as $category) {
			$parts[] = 'category=' . rawurlencode($category);
		}
		$requestUrl = self::ENDPOINT . '?' . implode('&', $parts);

		$http = new HttpClient([
			'socketTimeout' => self::TIMEOUT,
			'streamTimeout' => self::TIMEOUT,
			'redirect' => true,
			'version' => HttpClient::HTTP_1_1,
		]);
		$http->setHeader('Accept', 'application/json');

		$raw = $http->get($requestUrl);
		$httpCode = (int)$http->getStatus();

		if ($raw === false || $raw === '') {
			$err = $http->getError();
			$msg = is_array($err) && $err !== []
				? implode('; ', array_map('strval', $err))
				: 'Пустой ответ PSI API.';
			return self::fail(self::sanitizeErrorMessage($msg), $httpCode);
		}

		try {
			$body = Json::decode($raw);
		} catch (\Throwable $e) {
			return self::fail('Не удалось разобрать JSON ответа PSI.', $httpCode);
		}

		if (!is_array($body)) {
			return self::fail('Некорректный JSON ответа PSI.', $httpCode);
		}

		if (isset($body['error']) && is_array($body['error'])) {
			$message = (string)($body['error']['message'] ?? 'Ошибка PSI API');
			$code = (int)($body['error']['code'] ?? $httpCode);
			return self::fail(self::sanitizeErrorMessage($message), $code > 0 ? $code : $httpCode);
		}

		if ($httpCode < 200 || $httpCode >= 300) {
			return self::fail('HTTP ' . $httpCode . ' от PSI API.', $httpCode, $body);
		}

		if (!isset($body['lighthouseResult']) || !is_array($body['lighthouseResult'])) {
			return self::fail('В ответе PSI нет lighthouseResult.', $httpCode, $body);
		}

		$metrics = self::extractMetrics($body['lighthouseResult']);
		if ($metrics === null) {
			return self::fail('Не удалось извлечь Performance score из ответа PSI.', $httpCode, $body);
		}

		return [
			'ok' => true,
			'error' => null,
			'httpCode' => $httpCode,
			'body' => $body,
			'metrics' => $metrics,
		];
	}

	/**
	 * @param array<string, mixed> $lighthouseResult
	 * @return array{
	 *   score: int,
	 *   fcp: ?float,
	 *   lcp: ?float,
	 *   tbt: ?float,
	 *   cls: ?float,
	 *   fcpDisplay: ?string,
	 *   lcpDisplay: ?string,
	 *   tbtDisplay: ?string,
	 *   clsDisplay: ?string
	 * }|null
	 */
	public static function extractMetrics(array $lighthouseResult): ?array
	{
		$categories = $lighthouseResult['categories'] ?? null;
		if (!is_array($categories) || !isset($categories['performance']['score'])) {
			return null;
		}

		$scoreRaw = $categories['performance']['score'];
		if (!is_numeric($scoreRaw)) {
			return null;
		}

		$audits = is_array($lighthouseResult['audits'] ?? null)
			? $lighthouseResult['audits']
			: [];

		return [
			'score' => (int)round((float)$scoreRaw * 100),
			'fcp' => self::auditNumeric($audits, 'first-contentful-paint'),
			'lcp' => self::auditNumeric($audits, 'largest-contentful-paint'),
			'tbt' => self::auditNumeric($audits, 'total-blocking-time'),
			'cls' => self::auditNumeric($audits, 'cumulative-layout-shift'),
			'fcpDisplay' => self::auditDisplay($audits, 'first-contentful-paint'),
			'lcpDisplay' => self::auditDisplay($audits, 'largest-contentful-paint'),
			'tbtDisplay' => self::auditDisplay($audits, 'total-blocking-time'),
			'clsDisplay' => self::auditDisplay($audits, 'cumulative-layout-shift'),
		];
	}

	/**
	 * @param array<string, mixed> $audits
	 */
	private static function auditNumeric(array $audits, string $id): ?float
	{
		$value = $audits[$id]['numericValue'] ?? null;
		return is_numeric($value) ? (float)$value : null;
	}

	/**
	 * @param array<string, mixed> $audits
	 */
	private static function auditDisplay(array $audits, string $id): ?string
	{
		$value = $audits[$id]['displayValue'] ?? null;
		return is_string($value) && $value !== '' ? $value : null;
	}

	/**
	 * Убрать ключ API из текста ошибки, если Google его отразил.
	 */
	private static function sanitizeErrorMessage(string $message): string
	{
		return (string)preg_replace('/AIza[0-9A-Za-z_-]{10,}/', '[api-key]', $message);
	}

	/**
	 * @param array<string, mixed>|null $body
	 * @return array{ok: bool, error: string, httpCode: int, body: ?array, metrics: null}
	 */
	private static function fail(string $error, int $httpCode, ?array $body = null): array
	{
		return [
			'ok' => false,
			'error' => $error,
			'httpCode' => $httpCode,
			'body' => $body,
			'metrics' => null,
		];
	}
}
