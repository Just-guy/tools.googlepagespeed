<?php

namespace Tools\GooglePageSpeed\Psi;

/**
 * Агрегация lab-метрик серии variance (min / median / max).
 */
class VarianceAggregator
{
	/**
	 * @param array<string, mixed> $meta meta.json с items[]
	 * @return array{
	 *   url: string,
	 *   n: int,
	 *   strategies: list<string>,
	 *   status: string,
	 *   byStrategy: array<string, array{
	 *     scores: list<int>,
	 *     score: array{min: int, median: int, max: int, range: int},
	 *     FCP: array{min: ?float, median: ?float, max: ?float},
	 *     LCP: array{min: ?float, median: ?float, max: ?float},
	 *     TBT: array{min: ?float, median: ?float, max: ?float},
	 *     CLS: array{min: ?float, median: ?float, max: ?float},
	 *     runs: list<array{n: int, score: int, fcp: ?float, lcp: ?float, tbt: ?float, cls: ?float}>
	 *   }>
	 * }
	 */
	public static function fromMeta(array $meta): array
	{
		$strategies = is_array($meta['strategies'] ?? null)
			? array_values(array_map('strval', $meta['strategies']))
			: [];
		$items = is_array($meta['items'] ?? null) ? $meta['items'] : [];

		$byStrategy = [];
		foreach ($strategies as $strategy) {
			$byStrategy[$strategy] = self::aggregateStrategy($items, $strategy);
		}

		// Если в meta strategies пусто — собрать из items
		if ($byStrategy === []) {
			$found = [];
			foreach ($items as $item) {
				if (!is_array($item)) {
					continue;
				}
				$s = (string)($item['strategy'] ?? '');
				if ($s !== '' && !in_array($s, $found, true)) {
					$found[] = $s;
				}
			}
			foreach ($found as $strategy) {
				$byStrategy[$strategy] = self::aggregateStrategy($items, $strategy);
			}
			$strategies = $found;
		}

		return [
			'url' => (string)($meta['url'] ?? ''),
			'n' => (int)($meta['n'] ?? 0),
			'strategies' => $strategies,
			'status' => (string)($meta['status'] ?? ''),
			'byStrategy' => $byStrategy,
		];
	}

	/**
	 * HTML-отчёт для админки (классы tools-gps-psi-report* — стили на вкладке).
	 *
	 * @param array<string, mixed> $summary результат fromMeta
	 * @param array<string, mixed>|null $meta
	 */
	public static function toHtml(array $summary, ?array $meta = null): string
	{
		$ctx = self::buildContext($summary, $meta);
		$by = $ctx['by'];
		$e = static function ($s): string {
			return htmlspecialchars((string)$s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
		};

		$html = [];
		$html[] = '<div class="tools-gps-psi-report">';
		$html[] = '<header class="tools-gps-psi-report__header">';
		$titleHost = $ctx['labelPrefix'] !== ''
			? ($ctx['labelPrefix'] . ' — ' . $ctx['host'])
			: $ctx['host'];
		$html[] = '<h3 class="tools-gps-psi-report__title">PSI lab variance — ' . $e($titleHost) . '</h3>';
		$html[] = '<ul class="tools-gps-psi-report__meta">';
		$html[] = '<li><span class="tools-gps-psi-report__meta-label">Источник</span> PageSpeed Insights API</li>';
		if ($ctx['labelPrefix'] !== '') {
			$html[] = '<li><span class="tools-gps-psi-report__meta-label">Префикс</span> ' . $e($ctx['labelPrefix']) . '</li>';
		}
		$html[] = '<li><span class="tools-gps-psi-report__meta-label">URL</span> <a class="tools-gps-psi-report__url" href="' . $e($ctx['url']) . '" target="_blank" rel="noopener noreferrer">' . $e($ctx['url']) . '</a></li>';
		$html[] = '<li><span class="tools-gps-psi-report__meta-label">N</span> ' . $e((string)$ctx['n']) . ' × ' . $e(implode(' + ', $ctx['strategies'])) . '</li>';
		if ($ctx['when'] !== '') {
			$html[] = '<li><span class="tools-gps-psi-report__meta-label">Когда</span> ' . $e($ctx['when']) . '</li>';
		}
		$html[] = '<li><span class="tools-gps-psi-report__meta-label">Пауза</span> ~45 с между запросами</li>';
		if ($ctx['statusNote'] !== '') {
			$html[] = '<li class="tools-gps-psi-report__status tools-gps-psi-report__status--' . $e($ctx['status']) . '"><span class="tools-gps-psi-report__meta-label">Статус</span> ' . $e($ctx['statusNote']) . '</li>';
		}
		$html[] = '</ul>';
		$html[] = '</header>';

		$html[] = '<section class="tools-gps-psi-report__section">';
		$html[] = '<h4 class="tools-gps-psi-report__section-title">Performance score</h4>';
		$html[] = '<table class="tools-gps-psi-report__table tools-gps-psi-report__table--score">';
		$html[] = '<thead><tr><th></th><th>min</th><th>median</th><th>max</th><th>размах</th></tr></thead><tbody>';
		foreach (['mobile' => 'Mobile', 'desktop' => 'Desktop'] as $key => $label) {
			if (!isset($by[$key]['score'])) {
				continue;
			}
			$s = $by[$key]['score'];
			$html[] = '<tr><th scope="row">' . $e($label) . '</th>'
				. '<td>' . (int)$s['min'] . '</td>'
				. '<td class="tools-gps-psi-report__median">' . (int)$s['median'] . '</td>'
				. '<td>' . (int)$s['max'] . '</td>'
				. '<td>' . (int)$s['range'] . '</td></tr>';
		}
		$html[] = '</tbody></table>';
		$html[] = '</section>';

		$html[] = '<section class="tools-gps-psi-report__section">';
		$html[] = '<h4 class="tools-gps-psi-report__section-title">Прогоны (score)</h4>';
		$html[] = self::htmlRunsTable($by, $e);
		$html[] = '</section>';

		$html[] = '<section class="tools-gps-psi-report__section">';
		$html[] = '<h4 class="tools-gps-psi-report__section-title">Метрики (min / median / max)</h4>';
		foreach (['mobile' => 'Mobile', 'desktop' => 'Desktop'] as $key => $label) {
			if (!isset($by[$key])) {
				continue;
			}
			$html[] = '<div class="tools-gps-psi-report__device">';
			$html[] = '<h5 class="tools-gps-psi-report__device-title">' . $e($label) . '</h5>';
			$html[] = '<table class="tools-gps-psi-report__table tools-gps-psi-report__table--metrics">';
			$html[] = '<thead><tr><th>Метрика</th><th>min</th><th>median</th><th>max</th></tr></thead><tbody>';
			foreach (['FCP', 'LCP', 'TBT', 'CLS'] as $metric) {
				$block = $by[$key][$metric] ?? null;
				if (!is_array($block)) {
					continue;
				}
				$min = $metric === 'CLS' ? self::fmtCls($block['min']) : self::fmtMs($block['min']);
				$med = $metric === 'CLS' ? self::fmtCls($block['median']) : self::fmtMs($block['median']);
				$max = $metric === 'CLS' ? self::fmtCls($block['max']) : self::fmtMs($block['max']);
				$html[] = '<tr><th scope="row">' . $e($metric) . '</th>'
					. '<td>' . $e($min) . '</td>'
					. '<td class="tools-gps-psi-report__median">' . $e($med) . '</td>'
					. '<td>' . $e($max) . '</td></tr>';
			}
			$html[] = '</tbody></table>';
			$html[] = '</div>';
		}
		$html[] = '</section>';

		$html[] = '<section class="tools-gps-psi-report__section tools-gps-psi-report__section--howto">';
		$html[] = '<h4 class="tools-gps-psi-report__section-title">Как читать</h4>';
		$html[] = '<ul class="tools-gps-psi-report__howto">';
		$html[] = '<li>Сравнивать «до/после» по <strong>медиане</strong>, не по лучшему одиночному прогону.</li>';
		$html[] = '<li>Изменение медианы <strong>меньше половины размаха</strong> baseline — скорее шум.</li>';
		$html[] = '<li>Выход за max baseline (или стабильно выше) — сигнал улучшения.</li>';
		$html[] = '</ul>';
		$html[] = '</section>';

		$html[] = '</div>';

		return implode("\n", $html);
	}

	/**
	 * Текст summary.md (опциональный артефакт рядом с HTML).
	 *
	 * @param array<string, mixed> $summary результат fromMeta
	 * @param array<string, mixed>|null $meta
	 */
	public static function toMarkdown(array $summary, ?array $meta = null): string
	{
		$ctx = self::buildContext($summary, $meta);
		$by = $ctx['by'];

		$titleHost = $ctx['labelPrefix'] !== ''
			? ($ctx['labelPrefix'] . ' — ' . $ctx['host'])
			: $ctx['host'];
		$lines = [];
		$lines[] = '# PSI lab variance — ' . $titleHost;
		$lines[] = '';
		$lines[] = '- **Источник:** PageSpeed Insights API';
		if ($ctx['labelPrefix'] !== '') {
			$lines[] = '- **Префикс:** ' . $ctx['labelPrefix'];
		}
		$lines[] = '- **URL:** ' . $ctx['url'];
		$lines[] = '- **N:** ' . $ctx['n'] . ' прогонов × ' . implode(' + ', $ctx['strategies']);
		if ($ctx['when'] !== '') {
			$lines[] = '- **Когда:** ' . $ctx['when'];
		}
		$lines[] = '- **Пауза:** ~45 с между запросами';
		if ($ctx['statusNote'] !== '') {
			$lines[] = '- **Статус:** ' . $ctx['statusNote'];
		}
		$lines[] = '';
		$lines[] = '## Performance score';
		$lines[] = '';
		$lines[] = '| | min | median | max | размах |';
		$lines[] = '|---|---:|---:|---:|---:|';

		foreach (['mobile' => 'Mobile', 'desktop' => 'Desktop'] as $key => $label) {
			if (!isset($by[$key]['score'])) {
				continue;
			}
			$s = $by[$key]['score'];
			$lines[] = '| **' . $label . '** | ' . $s['min'] . ' | ' . $s['median'] . ' | ' . $s['max'] . ' | ' . $s['range'] . ' |';
		}

		$lines[] = '';
		$lines[] = '## Прогоны (score)';
		$lines[] = '';

		$hasMobile = isset($by['mobile']);
		$hasDesktop = isset($by['desktop']);
		if ($hasMobile && $hasDesktop) {
			$lines[] = '| # | Mobile | Desktop |';
			$lines[] = '|---|---:|---:|';
			$maxN = max(
				count($by['mobile']['runs'] ?? []),
				count($by['desktop']['runs'] ?? [])
			);
			$mobileByN = self::runsByN($by['mobile']['runs'] ?? []);
			$desktopByN = self::runsByN($by['desktop']['runs'] ?? []);
			for ($i = 1; $i <= $maxN; $i++) {
				$m = $mobileByN[$i]['score'] ?? '—';
				$d = $desktopByN[$i]['score'] ?? '—';
				$lines[] = '| ' . $i . ' | ' . $m . ' | ' . $d . ' |';
			}
		} elseif ($hasMobile || $hasDesktop) {
			$key = $hasMobile ? 'mobile' : 'desktop';
			$label = $hasMobile ? 'Mobile' : 'Desktop';
			$lines[] = '| # | ' . $label . ' |';
			$lines[] = '|---|---:|';
			foreach (($by[$key]['runs'] ?? []) as $run) {
				$lines[] = '| ' . (int)$run['n'] . ' | ' . (int)$run['score'] . ' |';
			}
		}

		$lines[] = '';
		$lines[] = '## Метрики (min / median / max)';
		$lines[] = '';

		foreach (['mobile' => 'Mobile', 'desktop' => 'Desktop'] as $key => $label) {
			if (!isset($by[$key])) {
				continue;
			}
			$lines[] = '### ' . $label;
			$lines[] = '';
			$lines[] = '| Метрика | min | median | max |';
			$lines[] = '|---|---|---|---|';
			foreach (['FCP', 'LCP', 'TBT', 'CLS'] as $metric) {
				$block = $by[$key][$metric] ?? null;
				if (!is_array($block)) {
					continue;
				}
				if ($metric === 'CLS') {
					$lines[] = '| ' . $metric . ' | ' . self::fmtCls($block['min']) . ' | ' . self::fmtCls($block['median']) . ' | ' . self::fmtCls($block['max']) . ' |';
				} else {
					$lines[] = '| ' . $metric . ' | ' . self::fmtMs($block['min']) . ' | ' . self::fmtMs($block['median']) . ' | ' . self::fmtMs($block['max']) . ' |';
				}
			}
			$lines[] = '';
		}

		$lines[] = '## Как читать';
		$lines[] = '';
		$lines[] = '- Сравнивать «до/после» по **медиане**, не по лучшему одиночному прогону.';
		$lines[] = '- Изменение медианы **меньше половины размаха** baseline — скорее шум.';
		$lines[] = '- Выход за max baseline (или стабильно выше) — сигнал улучшения.';
		$lines[] = '';

		return implode("\n", $lines);
	}

	/**
	 * @param array<string, mixed> $summary
	 * @param array<string, mixed>|null $meta
	 * @return array{
	 *   url: string,
	 *   host: string,
	 *   n: int,
	 *   strategies: list<string>,
	 *   when: string,
	 *   status: string,
	 *   statusNote: string,
	 *   by: array<string, mixed>
	 * }
	 */
	private static function buildContext(array $summary, ?array $meta): array
	{
		$url = (string)($summary['url'] ?? '');
		$host = parse_url($url, PHP_URL_HOST) ?: $url;
		$n = (int)($summary['n'] ?? 0);
		$strategies = is_array($summary['strategies'] ?? null)
			? array_values(array_map('strval', $summary['strategies']))
			: [];
		$started = is_array($meta) ? (string)($meta['startedAt'] ?? '') : '';
		$finished = is_array($meta) ? (string)($meta['finishedAt'] ?? '') : '';
		$when = '';
		if ($started !== '') {
			$when = str_replace('T', ' ', substr($started, 0, 16));
			if ($finished !== '') {
				$when .= '–' . substr(str_replace('T', ' ', $finished), 11, 5);
			}
			$when .= ' MSK';
		}

		$status = (string)($summary['status'] ?? '');
		$statusNote = '';
		if ($status === 'stopped') {
			$statusNote = 'частичная серия (остановлено)';
		} elseif ($status === 'error') {
			$statusNote = 'ошибка';
		}

		$labelPrefix = '';
		if (is_array($meta)) {
			$labelPrefix = VarianceStorage::normalizeLabelPrefix((string)($meta['labelPrefix'] ?? ''));
		}

		return [
			'url' => $url,
			'host' => is_string($host) ? $host : $url,
			'labelPrefix' => $labelPrefix,
			'n' => $n,
			'strategies' => $strategies,
			'when' => $when,
			'status' => $status,
			'statusNote' => $statusNote,
			'by' => is_array($summary['byStrategy'] ?? null) ? $summary['byStrategy'] : [],
		];
	}

	/**
	 * @param array<string, mixed> $by
	 * @param callable(string): string $e
	 */
	private static function htmlRunsTable(array $by, callable $e): string
	{
		$hasMobile = isset($by['mobile']);
		$hasDesktop = isset($by['desktop']);
		$rows = [];

		if ($hasMobile && $hasDesktop) {
			$rows[] = '<table class="tools-gps-psi-report__table tools-gps-psi-report__table--runs">';
			$rows[] = '<thead><tr><th>#</th><th>Mobile</th><th>Desktop</th></tr></thead><tbody>';
			$maxN = max(
				count($by['mobile']['runs'] ?? []),
				count($by['desktop']['runs'] ?? [])
			);
			$mobileByN = self::runsByN($by['mobile']['runs'] ?? []);
			$desktopByN = self::runsByN($by['desktop']['runs'] ?? []);
			for ($i = 1; $i <= $maxN; $i++) {
				$m = $mobileByN[$i]['score'] ?? '—';
				$d = $desktopByN[$i]['score'] ?? '—';
				$rows[] = '<tr><th scope="row">' . $i . '</th><td>' . $e((string)$m) . '</td><td>' . $e((string)$d) . '</td></tr>';
			}
			$rows[] = '</tbody></table>';
		} elseif ($hasMobile || $hasDesktop) {
			$key = $hasMobile ? 'mobile' : 'desktop';
			$label = $hasMobile ? 'Mobile' : 'Desktop';
			$rows[] = '<table class="tools-gps-psi-report__table tools-gps-psi-report__table--runs">';
			$rows[] = '<thead><tr><th>#</th><th>' . $e($label) . '</th></tr></thead><tbody>';
			foreach (($by[$key]['runs'] ?? []) as $run) {
				$rows[] = '<tr><th scope="row">' . (int)$run['n'] . '</th><td>' . (int)$run['score'] . '</td></tr>';
			}
			$rows[] = '</tbody></table>';
		} else {
			$rows[] = '<p class="tools-gps-psi-report__empty">Нет данных прогонов.</p>';
		}

		return implode("\n", $rows);
	}

	/**
	 * @param list<mixed> $items
	 * @return array{
	 *   scores: list<int>,
	 *   score: array{min: int, median: int, max: int, range: int},
	 *   FCP: array{min: ?float, median: ?float, max: ?float},
	 *   LCP: array{min: ?float, median: ?float, max: ?float},
	 *   TBT: array{min: ?float, median: ?float, max: ?float},
	 *   CLS: array{min: ?float, median: ?float, max: ?float},
	 *   runs: list<array{n: int, score: int, fcp: ?float, lcp: ?float, tbt: ?float, cls: ?float}>
	 * }
	 */
	private static function aggregateStrategy(array $items, string $strategy): array
	{
		$runs = [];
		foreach ($items as $item) {
			if (!is_array($item) || (string)($item['strategy'] ?? '') !== $strategy) {
				continue;
			}
			$runs[] = [
				'n' => (int)($item['n'] ?? 0),
				'score' => (int)($item['score'] ?? 0),
				'fcp' => self::toFloatOrNull($item['fcp'] ?? null),
				'lcp' => self::toFloatOrNull($item['lcp'] ?? null),
				'tbt' => self::toFloatOrNull($item['tbt'] ?? null),
				'cls' => self::toFloatOrNull($item['cls'] ?? null),
			];
		}

		usort($runs, static function ($a, $b) {
			return $a['n'] <=> $b['n'];
		});

		$scores = array_map(static function ($r) {
			return (int)$r['score'];
		}, $runs);

		$scoreStats = self::intStats($scores);

		return [
			'scores' => $scores,
			'score' => $scoreStats,
			'FCP' => self::floatStats(array_column($runs, 'fcp')),
			'LCP' => self::floatStats(array_column($runs, 'lcp')),
			'TBT' => self::floatStats(array_column($runs, 'tbt')),
			'CLS' => self::floatStats(array_column($runs, 'cls')),
			'runs' => $runs,
		];
	}

	/**
	 * @param list<int> $values
	 * @return array{min: int, median: int, max: int, range: int}
	 */
	private static function intStats(array $values): array
	{
		if ($values === []) {
			return ['min' => 0, 'median' => 0, 'max' => 0, 'range' => 0];
		}
		sort($values, SORT_NUMERIC);
		$min = (int)$values[0];
		$max = (int)$values[count($values) - 1];
		return [
			'min' => $min,
			'median' => (int)round(self::median($values)),
			'max' => $max,
			'range' => $max - $min,
		];
	}

	/**
	 * @param list<?float> $values
	 * @return array{min: ?float, median: ?float, max: ?float}
	 */
	private static function floatStats(array $values): array
	{
		$nums = [];
		foreach ($values as $v) {
			if ($v !== null && is_numeric($v)) {
				$nums[] = (float)$v;
			}
		}
		if ($nums === []) {
			return ['min' => null, 'median' => null, 'max' => null];
		}
		sort($nums, SORT_NUMERIC);
		return [
			'min' => $nums[0],
			'median' => self::median($nums),
			'max' => $nums[count($nums) - 1],
		];
	}

	/**
	 * @param list<int|float> $sorted
	 */
	private static function median(array $sorted): float
	{
		$count = count($sorted);
		if ($count === 0) {
			return 0.0;
		}
		$mid = intdiv($count, 2);
		if ($count % 2 === 1) {
			return (float)$sorted[$mid];
		}
		return ((float)$sorted[$mid - 1] + (float)$sorted[$mid]) / 2.0;
	}

	/**
	 * @param mixed $value
	 */
	private static function toFloatOrNull($value): ?float
	{
		return is_numeric($value) ? (float)$value : null;
	}

	/**
	 * @param list<array{n: int, score: int}> $runs
	 * @return array<int, array{n: int, score: int}>
	 */
	private static function runsByN(array $runs): array
	{
		$map = [];
		foreach ($runs as $run) {
			$map[(int)$run['n']] = $run;
		}
		return $map;
	}

	private static function fmtMs(?float $v): string
	{
		if ($v === null) {
			return '—';
		}
		if ($v >= 1000) {
			return number_format($v / 1000, 1, '.', '') . ' s';
		}
		return (string)(int)round($v) . ' ms';
	}

	private static function fmtCls(?float $v): string
	{
		if ($v === null) {
			return '—';
		}
		$s = rtrim(rtrim(number_format($v, 3, '.', ''), '0'), '.');
		return $s === '' ? '0' : $s;
	}
}
