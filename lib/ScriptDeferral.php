<?php

namespace Tools\GooglePageSpeed;

use Bitrix\Main\Web\Json;

class ScriptDeferral
{
	/** @var array{urls: string[], inlines: string[], stubs: array<string, string>} */
	private static $queue = [
		'urls' => [],
		'inlines' => [],
		'stubs' => [],
	];

	public static function reset(): void
	{
		self::$queue = [
			'urls' => [],
			'inlines' => [],
			'stubs' => [],
		];
	}

	/**
	 * Пресет E: отложить Яндекс.Метрику (idle + interaction).
	 */
	public static function deferYandexMetrika(&$content): void
	{
		self::queueStub(
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
		self::queueStub(
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
	 * Временно отключено — не добавлять в OptionActions::ACTIONS / пресеты.
	 */
	// public static function deferJivoChat(&$content): void
	// {
	// 	self::collectDeferredScripts(
	// 		$content,
	// 		'/code\.jivo\.ru|cdn\.jivo\.ru|jivosite\.com|jivo\.ru\/widget/i',
	// 		'/jivo_(?:api|onLoadCallback)|jivosite|code\.jivo\.ru/i'
	// 	);
	// }

	private static function queueStub(string $id, string $js): void
	{
		self::$queue['stubs'][$id] = $js;
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
						self::$queue['urls'][] = $src;
						return '';
					}
					return $match[0];
				}

				if ($body !== '' && preg_match($inlineBodyRegex, $body)) {
					self::$queue['inlines'][] = $body;
					return '';
				}

				return $match[0];
			},
			$content
		);
	}

	public static function injectRuntime(string &$content): void
	{
		$urls = array_values(array_unique(array_filter(self::$queue['urls'])));
		$inlines = array_values(array_filter(self::$queue['inlines'], static function ($code) {
			return is_string($code) && $code !== '';
		}));
		$stubs = array_values(self::$queue['stubs']);

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
				if(gpsDone){return;}
				gpsDone=true;
				var i,s;
				for(i=0;i<gpsU.length;i++){
					console.log('gpsRun', gpsU[i]);
					s=document.createElement('script');
					s.src=gpsU[i];
					s.async=true;
					(document.head||document.documentElement).appendChild(s);
				}
				for(i=0;i<gpsI.length;i++){
					console.log('gpsRun', 'inline#'+(i+1));
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
}
