<?php

namespace Tools\GooglePageSpeed\Psi;

use Bitrix\Main\HttpRequest;

/**
 * AJAX-действия вкладки «Разброс PSI».
 */
class AdminAjax
{
	/**
	 * @return array<string, mixed>
	 */
	public static function handle(HttpRequest $request): array
	{
		$action = (string)$request->getPost('action');

		switch ($action) {
			case 'gps_psi_save_key':
				return self::saveKey($request);
			case 'gps_psi_delete_key':
				return self::deleteKey();
			case 'gps_psi_start':
				return self::start($request);
			case 'gps_psi_run_one':
				return self::runOne($request);
			case 'gps_psi_finalize':
				return self::finalize($request);
			case 'gps_psi_list_runs':
				return self::listRuns();
			case 'gps_psi_get_run':
				return self::getRun($request);
			case 'gps_psi_delete_run':
				return self::deleteRun($request);
			case 'gps_psi_probe_url':
				return self::probeUrl();
			default:
				return self::fail('Неизвестное действие PSI.');
		}
	}

	/**
	 * @return array<string, mixed>
	 */
	private static function saveKey(HttpRequest $request): array
	{
		$key = trim((string)$request->getPost('api_key'));
		Settings::setApiKey($key);

		return [
			'ok' => true,
			'error' => null,
			'hasKey' => Settings::hasApiKey(),
		];
	}

	/**
	 * @return array<string, mixed>
	 */
	private static function deleteKey(): array
	{
		Settings::clearApiKey();

		return [
			'ok' => true,
			'error' => null,
			'hasKey' => false,
		];
	}

	/**
	 * @return array<string, mixed>
	 */
	private static function probeUrl(): array
	{
		$probe = VarianceStorage::buildProbeUrlFromSite();
		return [
			'ok' => (bool)$probe['ok'],
			'error' => $probe['error'],
			'url' => $probe['url'],
			'hasKey' => Settings::hasApiKey(),
		];
	}

	/**
	 * @return array<string, mixed>
	 */
	private static function start(HttpRequest $request): array
	{
		if (!Settings::hasApiKey()) {
			return self::fail('Сначала сохраните ключ API PageSpeed Insights.');
		}

		$probe = VarianceStorage::normalizeProbeUrl((string)$request->getPost('url'));
		if (!$probe['ok'] || empty($probe['url'])) {
			return self::fail((string)($probe['error'] ?: 'Некорректный URL прогона.'));
		}

		$n = (int)$request->getPost('n');
		if ($n < 1) {
			$n = 5;
		}
		if ($n > 20) {
			return self::fail('Слишком много прогонов (максимум 20).');
		}

		$strategiesRaw = $request->getPost('strategies');
		if (!is_array($strategiesRaw)) {
			$strategiesRaw = [];
		}
		$strategies = VarianceStorage::normalizeStrategies(array_map('strval', $strategiesRaw));
		if ($strategies === []) {
			return self::fail('Выберите хотя бы одно устройство (мобильные / компьютер).');
		}

		$labelPrefix = VarianceStorage::normalizeLabelPrefix((string)$request->getPost('label_prefix'));
		$created = VarianceStorage::createRun((string)$probe['url'], $n, $strategies, $labelPrefix);
		if (!$created['ok']) {
			return self::fail((string)($created['error'] ?: 'Не удалось создать серию.'));
		}

		$queue = [];
		foreach ($strategies as $strategy) {
			for ($i = 1; $i <= $n; $i++) {
				$queue[] = [
					'strategy' => $strategy,
					'n' => $i,
				];
			}
		}

		return [
			'ok' => true,
			'error' => null,
			'runId' => $created['runId'],
			'url' => $probe['url'],
			'n' => $n,
			'strategies' => $strategies,
			'queue' => $queue,
			'total' => count($queue),
			'meta' => $created['meta'],
		];
	}

	/**
	 * @return array<string, mixed>
	 */
	private static function runOne(HttpRequest $request): array
	{
		$runId = trim((string)$request->getPost('run_id'));
		$strategy = strtolower(trim((string)$request->getPost('strategy')));
		$n = (int)$request->getPost('n');

		if ($runId === '' || VarianceStorage::getRunPath($runId) === null) {
			return self::fail('Серия не найдена.');
		}
		if ($strategy !== 'mobile' && $strategy !== 'desktop') {
			return self::fail('Некорректный strategy.');
		}
		if ($n < 1) {
			return self::fail('Некорректный номер прогона.');
		}

		$meta = VarianceStorage::readMeta($runId);
		if ($meta === null) {
			return self::fail('Не удалось прочитать meta серии.');
		}

		$url = (string)($meta['url'] ?? '');
		if ($url === '') {
			return self::fail('В meta серии нет URL.');
		}

		$apiKey = Settings::getApiKey();
		if ($apiKey === '') {
			return self::fail('Не задан ключ API PageSpeed Insights.');
		}

		$result = ApiClient::run($url, $strategy, $apiKey);
		if (!$result['ok'] || !is_array($result['metrics'])) {
			return [
				'ok' => false,
				'error' => (string)($result['error'] ?: 'Ошибка прогона PSI.'),
				'httpCode' => (int)($result['httpCode'] ?? 0),
				'runId' => $runId,
				'strategy' => $strategy,
				'n' => $n,
			];
		}

		$body = is_array($result['body']) ? $result['body'] : [];
		if (!VarianceStorage::saveItem($runId, $strategy, $n, $body, $result['metrics'])) {
			return self::fail('Прогон получен, но не удалось сохранить файл.');
		}

		return [
			'ok' => true,
			'error' => null,
			'httpCode' => (int)$result['httpCode'],
			'runId' => $runId,
			'strategy' => $strategy,
			'n' => $n,
			'metrics' => $result['metrics'],
		];
	}

	/**
	 * @return array<string, mixed>
	 */
	private static function finalize(HttpRequest $request): array
	{
		$runId = trim((string)$request->getPost('run_id'));
		$status = strtolower(trim((string)$request->getPost('status')));
		if (!in_array($status, ['done', 'stopped', 'error'], true)) {
			$status = 'done';
		}

		if ($runId === '' || VarianceStorage::getRunPath($runId) === null) {
			return self::fail('Серия не найдена.');
		}

		$meta = VarianceStorage::readMeta($runId);
		if ($meta === null) {
			return self::fail('Не удалось прочитать meta серии.');
		}

		$meta['status'] = $status;
		$meta['finishedAt'] = VarianceStorage::mskFinishedIso();

		if (!VarianceStorage::writeMeta($runId, $meta)) {
			return self::fail('Не удалось обновить meta серии.');
		}

		$summary = VarianceAggregator::fromMeta($meta);
		$html = VarianceAggregator::toHtml($summary, $meta);
		$markdown = VarianceAggregator::toMarkdown($summary, $meta);

		if (!VarianceStorage::saveSummary($runId, $summary, $html, $markdown)) {
			return self::fail('Не удалось сохранить summary.');
		}

		return [
			'ok' => true,
			'error' => null,
			'runId' => $runId,
			'status' => $status,
			'meta' => $meta,
			'summary' => $summary,
			'html' => $html,
		];
	}

	/**
	 * @return array<string, mixed>
	 */
	private static function listRuns(): array
	{
		return [
			'ok' => true,
			'error' => null,
			'runs' => VarianceStorage::listRuns(),
		];
	}

	/**
	 * @return array<string, mixed>
	 */
	private static function getRun(HttpRequest $request): array
	{
		$runId = trim((string)$request->getPost('run_id'));
		if ($runId === '' || VarianceStorage::getRunPath($runId) === null) {
			return self::fail('Серия не найдена.');
		}

		$meta = VarianceStorage::readMeta($runId);
		if ($meta === null) {
			return self::fail('Не удалось прочитать meta серии.');
		}

		$summary = VarianceStorage::readSummary($runId);
		$html = VarianceStorage::readSummaryHtml($runId);

		// Если серии ещё нет summary (или старая) — пересобрать из meta
		if ($summary === null && !empty($meta['items'])) {
			$summary = VarianceAggregator::fromMeta($meta);
			$html = VarianceAggregator::toHtml($summary, $meta);
		} elseif ($html === null && is_array($summary)) {
			$html = VarianceAggregator::toHtml($summary, $meta);
		}

		return [
			'ok' => true,
			'error' => null,
			'runId' => $runId,
			'meta' => $meta,
			'summary' => $summary,
			'html' => $html,
		];
	}

	/**
	 * @return array<string, mixed>
	 */
	private static function deleteRun(HttpRequest $request): array
	{
		$runId = trim((string)$request->getPost('run_id'));
		if ($runId === '') {
			return self::fail('Не указан id серии.');
		}
		if (VarianceStorage::getRunPath($runId) === null) {
			return self::fail('Серия не найдена.');
		}
		if (!VarianceStorage::deleteRun($runId)) {
			return self::fail('Не удалось удалить серию.');
		}

		return [
			'ok' => true,
			'error' => null,
			'runId' => $runId,
			'runs' => VarianceStorage::listRuns(),
		];
	}

	/**
	 * @return array{ok: false, error: string}
	 */
	private static function fail(string $error): array
	{
		return [
			'ok' => false,
			'error' => $error,
		];
	}
}
