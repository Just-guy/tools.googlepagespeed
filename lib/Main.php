<?php

namespace Tools\GooglePageSpeed;

use Bitrix\Main\Application;
use Bitrix\Main\Context;
use Bitrix\Main\Web\Json;

class Main
{
	static $module_id = "tools.googlepagespeed";
	private const CACHE_TTL = 3600;
	private const CACHE_DIR = 'tools_googlepagespeed';

	private const ALLOWED_OPTION_METHODS = [
		'eliminateStyleSheetsThatBlockDisplay',
		'eliminateScriptsThatBlockDisplay',
		'addLoadingLazyAttributeAllTagsImg',
		'deferYandexMetrika',
		'deferGoogleAnalytics',
		// 'deferJivoChat',
	];

	/** Опции с закомментированной логикой — не показывать и не выполнять. */
	private const DISABLED_OPTION_CODES = [
		'DEFER_JIVOCHAT',
	];

	/** @var array{urls: string[], inlines: string[], stubs: array<string, string>} */
	private static $deferQueue = [
		'urls' => [],
		'inlines' => [],
		'stubs' => [],
	];

	public static function OnEndBufferContent(&$content)
	{
		self::$deferQueue = [
			'urls' => [],
			'inlines' => [],
			'stubs' => [],
		];

		if (self::shouldSkipBufferContent($content)) {
			return;
		}

		$templateArrayLinksCss = self::getLinksCssStyles(["ACTIVE" => "Y"]);
		$templateArrayLinksJS = self::getLinksJsScripts(["ACTIVE" => "Y"]);
		$arrayOptions = self::getOptions(["ACTIVE" => "Y"]);

		if (!empty($templateArrayLinksCss)) {
			foreach ($templateArrayLinksCss as $value) {
				if (preg_match('(' . $value['STRING_REGULAR_EXPRESSION'] . '(\?\d+){0,})', $content, $url) && !empty($value['STRING_REGULAR_EXPRESSION'])) {
					$arrayLinkCss[] = '<link href="' . $url[0] . '" rel="' . $value['ROLE'] . '" as="' . $value['TYPE'] . '">';
				}
			}

			if (!empty($arrayLinkCss)) {
				self::insertAfterOpeningHead($content, implode("\n", $arrayLinkCss));
			}
		}

		foreach ($arrayOptions as $valueOption) {
			if ($valueOption["LIMITATION"] == 'for-gps-robot') {
				if (!self::thisRobot()) continue;
			}

			if ($valueOption['OPTION_TYPE'] == 'regular-expression') {
				$regularExpressionArray = unserialize(
					htmlspecialcharsback($valueOption['OPTION_ACTION']),
					['allowed_classes' => false]
				);
				if (!is_array($regularExpressionArray)) {
					continue;
				}

				foreach ($regularExpressionArray as $regularExpression) {
					if (preg_match('/' . $regularExpression . '/msU', $content)) {
						$content = preg_replace('/' . $regularExpression . '/msU', '', $content);
					}
				}
			}

			if ($valueOption['OPTION_TYPE'] == 'function') {
				$methodName = htmlspecialcharsback($valueOption['OPTION_ACTION']);
				if (!in_array($methodName, self::ALLOWED_OPTION_METHODS, true)) {
					continue;
				}

				self::$methodName($content);
			}
		}

		if (!empty($templateArrayLinksJS)) {
			foreach ($templateArrayLinksJS as $value) {
				self::addAttributeToMatchingScripts($content, $value);
			}
		}

		self::injectDeferRuntime($content);
	}

	/**
	 * Модуль правит только публичный HTML-документ.
	 * Админка, AJAX, CLI и ответы без <head> (JSON, фрагменты) пропускаем.
	 */
	private static function shouldSkipBufferContent($content): bool
	{
		if (!is_string($content) || $content === '') {
			return true;
		}

		if (PHP_SAPI === 'cli') {
			return true;
		}

		$request = Context::getCurrent()->getRequest();
		if ($request->isAdminSection() || $request->isAjaxRequest()) {
			return true;
		}

		if (defined('PUBLIC_AJAX_MODE') && PUBLIC_AJAX_MODE) {
			return true;
		}

		if (!preg_match('/<head\b/i', $content)) {
			return true;
		}

		return false;
	}

	/**
	 * Вставляет HTML сразу после открывающего <head>, не затирая тег и атрибуты.
	 */
	private static function insertAfterOpeningHead(&$content, string $html): void
	{
		$content = preg_replace(
			'/(<head\b[^>]*>)/i',
			'$1' . "\n" . $html,
			$content,
			1
		);
	}

	/**
	 * Вешает async/defer на открывающий <script src="...">, а не на кусок «src...».
	 */
	private static function addAttributeToMatchingScripts(&$content, array $value): void
	{
		$pattern = (string)($value['STRING_REGULAR_EXPRESSION'] ?? '');
		$attr = strtolower(trim((string)($value['ATTRIBUTE'] ?? '')));
		if ($pattern === '' || ($attr !== 'async' && $attr !== 'defer')) {
			return;
		}

		$content = preg_replace_callback(
			'/<script\b[^>]*>/i',
			static function ($match) use ($pattern, $attr) {
				$tag = $match[0];
				if (!preg_match('/\bsrc\s*=\s*("[^"]*"|\'[^\']*\')/i', $tag, $srcMatch)) {
					return $tag;
				}
				if (!preg_match('/' . $pattern . '/', $srcMatch[1])) {
					return $tag;
				}
				if (preg_match('/\b(?:async|defer)\b/i', $tag)) {
					return $tag;
				}

				return preg_replace('/<script\b/i', '<script ' . $attr, $tag, 1);
			},
			$content
		);
	}

	public static function eliminateStyleSheetsThatBlockDisplay(&$content)
	{
		$content = preg_replace_callback(
			'/<link\b[^>]*>/i',
			static function ($match) {
				$tag = $match[0];
				if (!preg_match('/\brel\s*=\s*(["\']?)stylesheet\1/i', $tag)) {
					return $tag;
				}
				if (preg_match('/\bmedia\s*=\s*(["\']?)print\1/i', $tag)) {
					return $tag;
				}

				$deferred = preg_replace('/\smedia\s*=\s*("[^"]*"|\'[^\']*\'|[^\s>]+)/i', '', $tag);
				$deferred = preg_replace('/\s*\/?>$/', ' media="print" onload="this.media=\'all\'"$0', $deferred);

				return $deferred . '<noscript>' . $tag . '</noscript>';
			},
			$content
		);
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
		$isFirstImg = true;
		$content = preg_replace_callback(
			'/<img\b[^>]*>/i',
			static function ($match) use (&$isFirstImg) {
				$tag = $match[0];
				if ($isFirstImg) {
					$isFirstImg = false;
					return $tag;
				}
				if (preg_match('/\b(?:loading|fetchpriority|decoding|data-src)\s*=/i', $tag)) {
					return $tag;
				}

				return preg_replace('/<img\b/i', '<img loading="lazy"', $tag, 1);
			},
			$content
		);
	}

	/**
	 * Пресет E: отложить Яндекс.Метрику (idle + interaction).
	 */
	public static function deferYandexMetrika(&$content): void
	{
		self::queueDeferStub(
			'ym',
			'window.ym=window.ym||function(){(window.ym.a=window.ym.a||[]).push(arguments)};window.ym.l=1*new Date();'
		);
		self::collectDeferredScripts(
			$content,
			'/mc\.yandex\.ru\/(?:metrika|watch)/i',
			'/ym\s*\(|Ya\.Metrika|Yandex\.Metrika|mc\.yandex\.ru\/metrika/i'
		);
	}

	/**
	 * Пресет E: отложить Google Analytics / gtag.js.
	 */
	public static function deferGoogleAnalytics(&$content): void
	{
		self::queueDeferStub(
			'gtag',
			'window.dataLayer=window.dataLayer||[];window.gtag=window.gtag||function(){window.dataLayer.push(arguments);};'
		);
		self::collectDeferredScripts(
			$content,
			'/googletagmanager\.com\/gtag\/js|google-analytics\.com\/analytics\.js|www\.google-analytics\.com/i',
			'/\bgtag\s*\(|function\s+gtag\b|google-analytics\.com\/analytics\.js|gtag\/js\?id=/i'
		);
	}

	/**
	 * Пресет E: отложить JivoChat.
	 * Временно отключено — не добавлять в ALLOWED_OPTION_METHODS / пресеты.
	 */
	// public static function deferJivoChat(&$content): void
	// {
	// 	self::collectDeferredScripts(
	// 		$content,
	// 		'/code\.jivo\.ru|cdn\.jivo\.ru|jivosite\.com|jivo\.ru\/widget/i',
	// 		'/jivo_(?:api|onLoadCallback)|jivosite|code\.jivo\.ru/i'
	// 	);
	// }

	/**
	 * Определения пресетов отложенной загрузки для install / миграции БД.
	 */
	public static function getDeferredPresetOptionDefinitions(): array
	{
		return [
			[
				'ACTIVE' => 'N',
				'CODE_OPTION' => 'DEFER_YANDEX_METRIKA',
				'NAME_OPTION' => 'Отложить Яндекс.Метрику (idle / взаимодействие)',
				'OPTION_ACTION' => 'deferYandexMetrika',
				'OPTION_TYPE' => 'function',
				'LIMITATION' => 'for-everyone',
			],
			[
				'ACTIVE' => 'N',
				'CODE_OPTION' => 'DEFER_GOOGLE_ANALYTICS',
				'NAME_OPTION' => 'Отложить Google Analytics (idle / взаимодействие)',
				'OPTION_ACTION' => 'deferGoogleAnalytics',
				'OPTION_TYPE' => 'function',
				'LIMITATION' => 'for-everyone',
			],
			// [
			// 	'ACTIVE' => 'N',
			// 	'CODE_OPTION' => 'DEFER_JIVOCHAT',
			// 	'NAME_OPTION' => 'Отложить JivoChat (idle / взаимодействие)',
			// 	'OPTION_ACTION' => 'deferJivoChat',
			// 	'OPTION_TYPE' => 'function',
			// 	'LIMITATION' => 'for-everyone',
			// ],
		];
	}

	/**
	 * Добавляет пресеты в БД, если их ещё нет (для уже установленных модулей).
	 */
	public static function ensureDeferredPresetOptions(): void
	{
		$existing = [];
		foreach (self::getOptions([]) as $row) {
			$code = (string)($row['CODE_OPTION'] ?? '');
			if ($code !== '') {
				$existing[$code] = true;
			}
		}

		$added = false;
		foreach (self::getDeferredPresetOptionDefinitions() as $def) {
			if (isset($existing[$def['CODE_OPTION']])) {
				continue;
			}
			GPSOptionsTable::add($def);
			$added = true;
		}

		if ($added) {
			self::clearRulesCache();
		}
	}

	private static function queueDeferStub(string $id, string $js): void
	{
		self::$deferQueue['stubs'][$id] = $js;
	}

	/**
	 * Убирает подходящие script из HTML и складывает src/inline в очередь отложенной загрузки.
	 */
	private static function collectDeferredScripts(string &$content, string $srcRegex, string $inlineBodyRegex): void
	{
		$content = preg_replace_callback(
			'/<script\b([^>]*)>(.*?)<\/script>/is',
			static function ($match) use ($srcRegex, $inlineBodyRegex) {
				$attrs = $match[1];
				$body = $match[2];

				if (preg_match('/\bsrc\s*=\s*([\'"])(.*?)\1/i', $attrs, $srcMatch)) {
					$src = html_entity_decode($srcMatch[2], ENT_QUOTES | ENT_HTML5, 'UTF-8');
					if (preg_match($srcRegex, $src)) {
						self::$deferQueue['urls'][] = $src;
						return '';
					}
					return $match[0];
				}

				if ($body !== '' && preg_match($inlineBodyRegex, $body)) {
					self::$deferQueue['inlines'][] = $body;
					return '';
				}

				return $match[0];
			},
			$content
		);
	}

	private static function injectDeferRuntime(string &$content): void
	{
		$urls = array_values(array_unique(array_filter(self::$deferQueue['urls'])));
		$inlines = array_values(array_filter(self::$deferQueue['inlines'], static function ($code) {
			return is_string($code) && $code !== '';
		}));
		$stubs = array_values(self::$deferQueue['stubs']);

		if ($urls === [] && $inlines === [] && $stubs === []) {
			return;
		}

		$urlsJson = Json::encode($urls);
		$inlinesJson = Json::encode($inlines);
		$stubsJs = implode("\n", $stubs);

		$runtime = <<<HTML
<script data-gps-defer-runtime="1">
(function(){
{$stubsJs}
var gpsU={$urlsJson};
var gpsI={$inlinesJson};
var gpsDone=false;
function gpsRun(){
	console.log('gpsRun');
	if(gpsDone){return;}
	gpsDone=true;
	var i,s;
	for(i=0;i<gpsU.length;i++){
		s=document.createElement('script');
		s.src=gpsU[i];
		s.async=true;
		(document.head||document.documentElement).appendChild(s);
	}
	for(i=0;i<gpsI.length;i++){
		s=document.createElement('script');
		s.text=gpsI[i];
		(document.head||document.documentElement).appendChild(s);
	}
}
var gpsEv=['scroll','mousemove','touchstart','keydown'];
for(var e=0;e<gpsEv.length;e++){
	window.addEventListener(gpsEv[e],gpsRun,{once:true,passive:true});
}
if('requestIdleCallback' in window){
	requestIdleCallback(gpsRun,{timeout:5000});
}else{
	window.addEventListener('load',function(){setTimeout(gpsRun,2500);});
}
})();
</script>
HTML;

		if (preg_match('/<\/body>/i', $content)) {
			$content = preg_replace('/<\/body>/i', $runtime . "\n</body>", $content, 1);
			return;
		}

		$content .= $runtime;
	}

	public static function getLinksCssStyles($filter = [])
	{
		$rows = self::getCachedTableRows('css_styles', ConnectedCssStyleTable::class);
		return self::filterCachedRows($rows, $filter);
	}

	public static function getLinksJsScripts($filter = [])
	{
		$rows = self::getCachedTableRows('js_scripts', ConnectedJsScriptTable::class);
		return self::filterCachedRows($rows, $filter);
	}

	public static function getOptions($filter = [])
	{
		$rows = self::getCachedTableRows('options', GPSOptionsTable::class);
		$rows = self::filterCachedRows($rows, $filter);

		return array_values(array_filter($rows, static function ($row) {
			$code = (string)($row['CODE_OPTION'] ?? '');
			return $code === '' || !in_array($code, self::DISABLED_OPTION_CODES, true);
		}));
	}

	public static function clearRulesCache(): void
	{
		Application::getInstance()->getManagedCache()->cleanDir(self::CACHE_DIR);
	}

	/**
	 * Одна выборка таблицы на TTL, дальше фильтр в PHP.
	 * Админка и публичка делят один кеш.
	 */
	private static function getCachedTableRows(string $cacheId, string $tableClass): array
	{
		$cache = Application::getInstance()->getManagedCache();
		if ($cache->read(self::CACHE_TTL, $cacheId, self::CACHE_DIR)) {
			$rows = $cache->get($cacheId);
			return is_array($rows) ? $rows : [];
		}

		$rows = [];
		$result = $tableClass::getList(['select' => ['*'], 'order' => ['ID' => 'ASC']]);
		while ($row = $result->fetch()) {
			$rows[] = $row;
		}

		$cache->set($cacheId, $rows);
		return $rows;
	}

	private static function filterCachedRows(array $rows, array $filter): array
	{
		if ($filter === []) {
			return $rows;
		}

		return array_values(array_filter($rows, static function ($row) use ($filter) {
			foreach ($filter as $field => $value) {
				if (($row[$field] ?? null) !== $value) {
					return false;
				}
			}
			return true;
		}));
	}

	public static function thisRobot()
	{
		$userAgent = Application::getInstance()->getContext()->getServer()->getUserAgent();
		if (strpos($userAgent, "Lighthouse") !== false) {
			return true;
		}

		return false;
	}
}
