<?php

namespace Tools\GooglePageSpeed\Psi;

use Bitrix\Main\Application;
use Bitrix\Main\IO\Directory;
use Bitrix\Main\IO\File;
use Bitrix\Main\Web\Json;

/**
 * Файловое хранилище серий variance: /upload/tools.googlepagespeed/variance/{id}/
 */
class VarianceStorage
{
	private const RELATIVE_ROOT = '/upload/tools.googlepagespeed/variance';
	private const LIST_LIMIT = 30;

	public static function getAbsoluteRoot(): string
	{
		$docRoot = rtrim((string)Application::getDocumentRoot(), '/');
		return $docRoot . self::RELATIVE_ROOT;
	}

	public static function ensureRoot(): void
	{
		$root = self::getAbsoluteRoot();
		if (!Directory::isDirectoryExists($root)) {
			Directory::createDirectory($root);
		}
	}

	/**
	 * @param list<string> $strategies mobile|desktop
	 * @return array{ok: bool, error: ?string, runId: ?string, path: ?string, meta: ?array}
	 */
	public static function createRun(string $url, int $n, array $strategies, string $labelPrefix = ''): array
	{
		$n = max(1, $n);
		$strategies = self::normalizeStrategies($strategies);
		if ($strategies === []) {
			return ['ok' => false, 'error' => 'Выберите хотя бы одно устройство.', 'runId' => null, 'path' => null, 'meta' => null];
		}

		$labelPrefix = self::normalizeLabelPrefix($labelPrefix);

		$host = self::hostFromUrl($url);
		$stamp = self::mskStamp();
		$runId = $stamp . '_' . $host;

		self::ensureRoot();
		$path = self::getAbsoluteRoot() . '/' . $runId;
		if (Directory::isDirectoryExists($path)) {
			$runId .= '_' . substr(uniqid('', true), -4);
			$path = self::getAbsoluteRoot() . '/' . $runId;
		}

		Directory::createDirectory($path);

		$meta = [
			'id' => $runId,
			'url' => $url,
			'n' => $n,
			'strategies' => $strategies,
			'labelPrefix' => $labelPrefix,
			'status' => 'running',
			'startedAt' => self::mskNowIso(),
			'finishedAt' => null,
			'items' => [],
		];

		if (!self::writeJson($path . '/meta.json', $meta)) {
			return ['ok' => false, 'error' => 'Не удалось записать meta.json.', 'runId' => null, 'path' => null, 'meta' => null];
		}

		return ['ok' => true, 'error' => null, 'runId' => $runId, 'path' => $path, 'meta' => $meta];
	}

	public static function getRunPath(string $runId): ?string
	{
		$runId = self::sanitizeRunId($runId);
		if ($runId === null) {
			return null;
		}
		$path = self::getAbsoluteRoot() . '/' . $runId;
		return Directory::isDirectoryExists($path) ? $path : null;
	}

	/**
	 * @return array<string, mixed>|null
	 */
	public static function readMeta(string $runId): ?array
	{
		$path = self::getRunPath($runId);
		if ($path === null) {
			return null;
		}
		return self::readJson($path . '/meta.json');
	}

	/**
	 * @param array<string, mixed> $meta
	 */
	public static function writeMeta(string $runId, array $meta): bool
	{
		$path = self::getRunPath($runId);
		if ($path === null) {
			return false;
		}
		return self::writeJson($path . '/meta.json', $meta);
	}

	/**
	 * @param array<string, mixed> $body полный ответ PSI
	 * @param array<string, mixed> $metrics score + CWV lab
	 */
	public static function saveItem(string $runId, string $strategy, int $n, array $body, array $metrics): bool
	{
		$path = self::getRunPath($runId);
		if ($path === null) {
			return false;
		}

		$strategy = strtolower($strategy);
		$file = $path . '/psi-' . $strategy . '-' . $n . '.json';
		if (!self::writeJson($file, $body)) {
			return false;
		}

		$meta = self::readMeta($runId);
		if ($meta === null) {
			return false;
		}

		$items = is_array($meta['items'] ?? null) ? $meta['items'] : [];
		$items = array_values(array_filter(
			$items,
			static function ($item) use ($strategy, $n) {
				return !(
					is_array($item)
					&& ($item['strategy'] ?? '') === $strategy
					&& (int)($item['n'] ?? 0) === $n
				);
			}
		));
		$items[] = [
			'strategy' => $strategy,
			'n' => $n,
			'score' => (int)($metrics['score'] ?? 0),
			'fcp' => $metrics['fcp'] ?? null,
			'lcp' => $metrics['lcp'] ?? null,
			'tbt' => $metrics['tbt'] ?? null,
			'cls' => $metrics['cls'] ?? null,
			'fcpDisplay' => $metrics['fcpDisplay'] ?? null,
			'lcpDisplay' => $metrics['lcpDisplay'] ?? null,
			'tbtDisplay' => $metrics['tbtDisplay'] ?? null,
			'clsDisplay' => $metrics['clsDisplay'] ?? null,
			'file' => 'psi-' . $strategy . '-' . $n . '.json',
		];
		$meta['items'] = $items;

		return self::writeMeta($runId, $meta);
	}

	/**
	 * @param array<string, mixed> $summary
	 */
	public static function saveSummary(string $runId, array $summary, string $html = '', string $markdown = ''): bool
	{
		$path = self::getRunPath($runId);
		if ($path === null) {
			return false;
		}

		$ok = self::writeJson($path . '/summary.json', $summary);
		if ($html !== '') {
			File::putFileContents($path . '/summary.html', $html);
		}
		if ($markdown !== '') {
			File::putFileContents($path . '/summary.md', $markdown);
		}
		return $ok;
	}

	/**
	 * @return array<string, mixed>|null
	 */
	public static function readSummary(string $runId): ?array
	{
		$path = self::getRunPath($runId);
		if ($path === null) {
			return null;
		}
		return self::readJson($path . '/summary.json');
	}

	public static function readSummaryHtml(string $runId): ?string
	{
		$path = self::getRunPath($runId);
		if ($path === null) {
			return null;
		}
		$file = $path . '/summary.html';
		if (!is_file($file)) {
			return null;
		}
		$raw = File::getFileContents($file);
		return ($raw === false || $raw === '') ? null : $raw;
	}

	/**
	 * @return list<array{
	 *   id: string,
	 *   label: string,
	 *   url: string,
	 *   n: int,
	 *   strategies: list<string>,
	 *   status: string,
	 *   startedAt: ?string,
	 *   finishedAt: ?string
	 * }>
	 */
	public static function listRuns(int $limit = self::LIST_LIMIT): array
	{
		self::ensureRoot();
		$root = self::getAbsoluteRoot();
		$dirs = [];
		foreach (scandir($root) ?: [] as $name) {
			if ($name === '.' || $name === '..') {
				continue;
			}
			$full = $root . '/' . $name;
			if (is_dir($full) && is_file($full . '/meta.json')) {
				$dirs[] = $name;
			}
		}

		rsort($dirs, SORT_STRING);

		$result = [];
		foreach ($dirs as $name) {
			if (count($result) >= $limit) {
				break;
			}
			$meta = self::readMeta($name);
			if ($meta === null) {
				continue;
			}
			$strategies = is_array($meta['strategies'] ?? null) ? $meta['strategies'] : [];
			$status = (string)($meta['status'] ?? 'unknown');
			$startedAt = isset($meta['startedAt']) ? (string)$meta['startedAt'] : null;
			$result[] = [
				'id' => (string)($meta['id'] ?? $name),
				'label' => self::formatRunLabel($meta, $name),
				'url' => (string)($meta['url'] ?? ''),
				'n' => (int)($meta['n'] ?? 0),
				'strategies' => array_values(array_map('strval', $strategies)),
				'status' => $status,
				'startedAt' => $startedAt,
				'finishedAt' => isset($meta['finishedAt']) ? (string)$meta['finishedAt'] : null,
			];
		}

		return $result;
	}

	/**
	 * Данные всех серий для графиков сравнения (хронологически: старые слева).
	 *
	 * @return list<array{
	 *   id: string,
	 *   label: string,
	 *   shortLabel: string,
	 *   byStrategy: array<string, array{
	 *     score: array{min: int, median: int, max: int}|null,
	 *     FCP: array{min: ?float, median: ?float, max: ?float}|null,
	 *     LCP: array{min: ?float, median: ?float, max: ?float}|null,
	 *     TBT: array{min: ?float, median: ?float, max: ?float}|null,
	 *     CLS: array{min: ?float, median: ?float, max: ?float}|null
	 *   }>
	 * }>
	 */
	public static function listChartSeries(int $limit = self::LIST_LIMIT): array
	{
		$runs = self::listRuns($limit);
		$usedLabels = [];
		$out = [];

		foreach ($runs as $run) {
			$id = (string)($run['id'] ?? '');
			if ($id === '') {
				continue;
			}
			$meta = self::readMeta($id);
			$summary = self::readSummary($id);
			if ($summary === null && is_array($meta) && !empty($meta['items'])) {
				$summary = VarianceAggregator::fromMeta($meta);
			}
			if (!is_array($summary)) {
				continue;
			}
			$byStrategyRaw = is_array($summary['byStrategy'] ?? null) ? $summary['byStrategy'] : [];
			$byStrategy = [];
			foreach ($byStrategyRaw as $strategy => $block) {
				if (!is_array($block)) {
					continue;
				}
				$byStrategy[(string)$strategy] = [
					'score' => is_array($block['score'] ?? null) ? [
						'min' => (int)$block['score']['min'],
						'median' => (int)$block['score']['median'],
						'max' => (int)$block['score']['max'],
					] : null,
					'FCP' => self::chartMetricBlock($block['FCP'] ?? null),
					'LCP' => self::chartMetricBlock($block['LCP'] ?? null),
					'TBT' => self::chartMetricBlock($block['TBT'] ?? null),
					'CLS' => self::chartMetricBlock($block['CLS'] ?? null),
				];
			}
			if ($byStrategy === []) {
				continue;
			}

			$short = self::formatChartShortLabel(is_array($meta) ? $meta : [], (string)$run['label'], $usedLabels);
			$usedLabels[$short] = true;

			$out[] = [
				'id' => $id,
				'label' => (string)$run['label'],
				'shortLabel' => $short,
				'byStrategy' => $byStrategy,
			];
		}

		return array_reverse($out);
	}

	/**
	 * @param mixed $block
	 * @return array{min: ?float, median: ?float, max: ?float}|null
	 */
	private static function chartMetricBlock($block): ?array
	{
		if (!is_array($block)) {
			return null;
		}
		$min = $block['min'] ?? null;
		$median = $block['median'] ?? null;
		$max = $block['max'] ?? null;
		if ($median === null && $min === null && $max === null) {
			return null;
		}
		return [
			'min' => $min === null || $min === '' ? null : (float)$min,
			'median' => $median === null || $median === '' ? null : (float)$median,
			'max' => $max === null || $max === '' ? null : (float)$max,
		];
	}

	/**
	 * @param array<string, mixed> $meta
	 * @param array<string, true> $usedLabels
	 */
	private static function formatChartShortLabel(array $meta, string $fullLabel, array $usedLabels): string
	{
		$prefix = self::normalizeLabelPrefix((string)($meta['labelPrefix'] ?? ''));
		$startedAt = (string)($meta['startedAt'] ?? '');
		$datePart = $startedAt !== '' ? str_replace('T', ' ', substr($startedAt, 0, 16)) : '';

		$base = $prefix !== '' ? $prefix : ($datePart !== '' ? $datePart : $fullLabel);
		if (function_exists('mb_substr')) {
			$base = mb_substr($base, 0, 28);
		} else {
			$base = substr($base, 0, 28);
		}
		$base = trim($base);
		if ($base === '') {
			$base = 'серия';
		}

		if (!isset($usedLabels[$base])) {
			return $base;
		}
		if ($datePart !== '' && $prefix !== '') {
			$withDate = $prefix . ' ' . substr($datePart, 5, 11);
			if (!isset($usedLabels[$withDate])) {
				return $withDate;
			}
		}
		$n = 2;
		while (isset($usedLabels[$base . ' (' . $n . ')'])) {
			$n++;
		}
		return $base . ' (' . $n . ')';
	}

	public static function deleteRun(string $runId): bool
	{
		$path = self::getRunPath($runId);
		if ($path === null) {
			return false;
		}
		Directory::deleteDirectory($path);
		return !Directory::isDirectoryExists($path);
	}

	/**
	 * @param list<string> $strategies
	 * @return list<string>
	 */
	public static function normalizeStrategies(array $strategies): array
	{
		$out = [];
		foreach ($strategies as $s) {
			$s = strtolower(trim((string)$s));
			if (($s === 'mobile' || $s === 'desktop') && !in_array($s, $out, true)) {
				$out[] = $s;
			}
		}
		return $out;
	}

	/**
	 * URL для прогона из текущего сайта Bitrix.
	 *
	 * @return array{ok: bool, url: ?string, error: ?string}
	 */
	public static function buildProbeUrlFromSite(): array
	{
		$serverName = (string)(\Bitrix\Main\Context::getCurrent()->getServer()->getServerName() ?: '');
		if ($serverName === '' && defined('SITE_SERVER_NAME')) {
			$serverName = (string)SITE_SERVER_NAME;
		}
		$serverName = preg_replace('#^https?://#i', '', $serverName) ?: '';
		$serverName = rtrim($serverName, '/');
		if ($serverName === '') {
			return [
				'ok' => false,
				'url' => null,
				'error' => 'Не удалось определить домен сайта (SITE_SERVER_NAME / ServerName пуст). Задайте домен сайта в настройках Bitrix или введите URL вручную.',
			];
		}
		return [
			'ok' => true,
			'url' => 'https://' . $serverName . '/',
			'error' => null,
		];
	}

	/**
	 * Нормализация и лёгкая проверка URL прогона (http/https).
	 *
	 * @return array{ok: bool, url: ?string, error: ?string}
	 */
	public static function normalizeProbeUrl(string $url): array
	{
		$url = trim($url);
		if ($url === '') {
			return [
				'ok' => false,
				'url' => null,
				'error' => 'Укажите URL для прогона.',
			];
		}
		if (!preg_match('#^https?://#i', $url)) {
			$url = 'https://' . ltrim($url, '/');
		}
		$parts = parse_url($url);
		if ($parts === false || empty($parts['scheme']) || empty($parts['host'])) {
			return [
				'ok' => false,
				'url' => null,
				'error' => 'Некорректный URL. Пример: https://ideal-mf.ru/',
			];
		}
		$scheme = strtolower((string)$parts['scheme']);
		if ($scheme !== 'http' && $scheme !== 'https') {
			return [
				'ok' => false,
				'url' => null,
				'error' => 'Допустимы только http и https.',
			];
		}
		$path = (string)($parts['path'] ?? '/');
		if ($path === '') {
			$path = '/';
		}
		$query = isset($parts['query']) && $parts['query'] !== '' ? '?' . $parts['query'] : '';
		$normalized = $scheme . '://' . $parts['host'];
		if (!empty($parts['port'])) {
			$normalized .= ':' . (int)$parts['port'];
		}
		$normalized .= $path . $query;

		return [
			'ok' => true,
			'url' => $normalized,
			'error' => null,
		];
	}

	/**
	 * @param array<string, mixed> $meta
	 */
	private static function formatRunLabel(array $meta, string $fallbackId): string
	{
		$id = (string)($meta['id'] ?? $fallbackId);
		$startedAt = (string)($meta['startedAt'] ?? '');
		$datePart = $startedAt !== '' ? str_replace('T', ' ', substr($startedAt, 0, 16)) : $id;

		$host = self::hostFromUrl((string)($meta['url'] ?? ''));
		$strategies = is_array($meta['strategies'] ?? null) ? $meta['strategies'] : [];
		$devices = [];
		foreach ($strategies as $s) {
			if ($s === 'mobile') {
				$devices[] = 'mobile';
			} elseif ($s === 'desktop') {
				$devices[] = 'desktop';
			}
		}
		$deviceStr = $devices !== [] ? implode('+', $devices) : '—';
		$n = (int)($meta['n'] ?? 0);
		$status = (string)($meta['status'] ?? '');
		$statusRu = [
			'running' => 'идёт',
			'done' => 'готово',
			'stopped' => 'остановлено',
			'error' => 'ошибка',
		][$status] ?? $status;

		$parts = [];
		$prefix = self::normalizeLabelPrefix((string)($meta['labelPrefix'] ?? ''));
		if ($prefix !== '') {
			$parts[] = $prefix;
		}
		$parts[] = $datePart;
		$parts[] = $host;
		$parts[] = $deviceStr;
		$parts[] = 'N=' . $n;
		$parts[] = $statusRu;

		return implode(' · ', $parts);
	}

	public static function normalizeLabelPrefix(string $prefix): string
	{
		$prefix = trim(preg_replace('/\s+/u', ' ', $prefix) ?? '');
		if ($prefix === '') {
			return '';
		}
		// без разделителя «·» и управляющих символов
		$prefix = str_replace(['·', "\0"], '', $prefix);
		if (function_exists('mb_substr')) {
			$prefix = mb_substr($prefix, 0, 40);
		} else {
			$prefix = substr($prefix, 0, 40);
		}
		return trim($prefix);
	}

	private static function hostFromUrl(string $url): string
	{
		$host = parse_url($url, PHP_URL_HOST);
		if (!is_string($host) || $host === '') {
			$host = 'site';
		}
		$host = strtolower($host);
		$host = preg_replace('/[^a-z0-9.-]+/i', '-', $host) ?: 'site';
		return trim($host, '-.') ?: 'site';
	}

	private static function mskStamp(): string
	{
		try {
			$dt = new \DateTime('now', new \DateTimeZone('Europe/Moscow'));
		} catch (\Throwable $e) {
			$dt = new \DateTime('now');
		}
		return $dt->format('Y-m-d_H-i');
	}

	private static function mskNowIso(): string
	{
		try {
			$dt = new \DateTime('now', new \DateTimeZone('Europe/Moscow'));
		} catch (\Throwable $e) {
			$dt = new \DateTime('now');
		}
		return $dt->format('Y-m-d\TH:i:s');
	}

	public static function mskFinishedIso(): string
	{
		return self::mskNowIso();
	}

	private static function sanitizeRunId(string $runId): ?string
	{
		$runId = trim($runId);
		if ($runId === '' || strpos($runId, '..') !== false || strpos($runId, '/') !== false || strpos($runId, '\\') !== false) {
			return null;
		}
		if (!preg_match('/^[a-zA-Z0-9._-]+$/', $runId)) {
			return null;
		}
		return $runId;
	}

	/**
	 * @return array<string, mixed>|null
	 */
	private static function readJson(string $filePath): ?array
	{
		if (!is_file($filePath)) {
			return null;
		}
		$raw = File::getFileContents($filePath);
		if ($raw === false || $raw === '') {
			return null;
		}
		try {
			$data = Json::decode($raw);
		} catch (\Throwable $e) {
			return null;
		}
		return is_array($data) ? $data : null;
	}

	/**
	 * @param array<string, mixed> $data
	 */
	private static function writeJson(string $filePath, array $data): bool
	{
		try {
			$json = Json::encode($data);
		} catch (\Throwable $e) {
			return false;
		}
		return File::putFileContents($filePath, $json) !== false;
	}
}
