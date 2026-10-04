<?php

namespace Tools\GooglePageSpeed;

use Bitrix\Main\Web\Json;

class ScriptDeferral
{
	/** @var array{urls: string[], styles: string[], inlines: string[], stubs: array<string, string>} */
	private static $queue = [
		'urls' => [],
		'styles' => [],
		'inlines' => [],
		'stubs' => [],
	];

	public static function reset(): void
	{
		self::$queue = [
			'urls' => [],
			'styles' => [],
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
			'/mc\.yandex\.ru\/(?:metrika|watch)|\/bitrix\/js\/yandex\.metrika\//i',
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
	 * Отложить Google Tag Manager (gtm.js).
	 */
	public static function deferGoogleTagManager(&$content): void
	{
		self::queueStub(
			'dataLayer',
			'window.dataLayer=window.dataLayer||[];'
		);
		self::collectDeferredScripts(
			$content,
			'/googletagmanager\.com\/gtm\.js/i',
			'/googletagmanager\.com\/gtm\.js|\'gtm\.start\'|GTM-[A-Z0-9]+/i'
		);
		$content = preg_replace(
			'/<noscript>\s*<iframe\b[^>]*googletagmanager\.com[^>]*>.*?<\/iframe>\s*<\/noscript>/is',
			'',
			$content
		) ?? $content;
	}

	/**
	 * Отложить Roistat (+ Bitrix24 widget integration).
	 */
	public static function deferRoistat(&$content): void
	{
		self::collectDeferredScripts(
			$content,
			'/cloud\.roistat\.com/i',
			'/cloud\.roistat\.com|roistatProjectId|roistatLanguage/i'
		);
		$content = preg_replace(
			'/<!--\s*Roistat Counter Start\s*-->\s*<!--\s*Roistat Counter End\s*-->/is',
			'',
			$content
		) ?? $content;
		$content = preg_replace(
			'/<!--\s*BEGIN BITRIX24 WIDGET INTEGRATION WITH ROISTAT\s*-->\s*<!--\s*END BITRIX24 WIDGET INTEGRATION WITH ROISTAT\s*-->/is',
			'',
			$content
		) ?? $content;
	}

	/**
	 * Отложить Envybox / Whitesaas (обратный звонок).
	 */
	public static function deferEnvybox(&$content): void
	{
		self::collectStylesheets($content, '/cdn\.envybox\.io\/widget\/cbk\.css/i');
		self::collectDeferredScripts(
			$content,
			'/cdn\.envybox\.io\/widget\/cbk\.js/i',
			'/cdn\.envybox\.io|whitesaas_code|callbackkiller/i'
		);
		$content = preg_replace(
			'/<!--\s*obratny zvonok\s*-->\s*<!--\s*\/obratny zvonok\s*-->/is',
			'',
			$content
		) ?? $content;
	}

	/**
	 * Отложить Calltouch.
	 */
	public static function deferCalltouch(&$content): void
	{
		self::queueStub(
			'ct',
			'window.ct=window.ct||function(){(window.ct.callbacks=window.ct.callbacks||[]).push(arguments)};'
		);
		self::collectDeferredScripts(
			$content,
			'/mod\.calltouch\.ru|api\.calltouch\.ru/i',
			'/mod\.calltouch\.ru|CalltouchDataObject|calltracking_params/i'
		);
		$content = preg_replace(
			'/<!--\s*calltouch\s*-->\s*<!--\s*\/calltouch\s*-->/is',
			'',
			$content
		) ?? $content;
	}

	/**
	 * Отложить jquery.inputmask с CDN (cdnjs).
	 */
	public static function deferCdnInputmask(&$content): void
	{
		self::collectDeferredScripts(
			$content,
			'/cdnjs\.cloudflare\.com\/ajax\/libs\/(?:jquery\.)?inputmask/i',
			'/cdnjs\.cloudflare\.com\/ajax\/libs\/(?:jquery\.)?inputmask/i'
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
	 * Убирает подходящие <link stylesheet> и складывает href в очередь.
	 */
	private static function collectStylesheets(string &$content, string $hrefRegex): void
	{
		$content = preg_replace_callback(
			'/<noscript>\s*<link\b[^>]*\bhref\s*=\s*([\'"])(.*?)\1[^>]*>\s*<\/noscript>/is',
			static function ($match) use ($hrefRegex) {
				$href = html_entity_decode($match[2], ENT_QUOTES | ENT_HTML5, 'UTF-8');
				if (preg_match($hrefRegex, $href)) {
					return '';
				}
				return $match[0];
			},
			$content
		) ?? $content;

		$content = preg_replace_callback(
			'/<link\b([^>]*)>/is',
			static function ($match) use ($hrefRegex) {
				$attrs = $match[1];
				if (!preg_match('/\bhref\s*=\s*([\'"])(.*?)\1/i', $attrs, $hrefMatch)) {
					return $match[0];
				}
				$href = html_entity_decode($hrefMatch[2], ENT_QUOTES | ENT_HTML5, 'UTF-8');
				if (!preg_match($hrefRegex, $href)) {
					return $match[0];
				}
				self::$queue['styles'][] = $href;
				return '';
			},
			$content
		) ?? $content;
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
		$styles = array_values(array_unique(array_filter(self::$queue['styles'])));
		$inlines = array_values(array_filter(self::$queue['inlines'], static function ($code) {
			return is_string($code) && $code !== '';
		}));
		$stubs = array_values(self::$queue['stubs']);

		if ($urls === [] && $styles === [] && $inlines === [] && $stubs === []) {
			return;
		}

		$urlsJson = Json::encode($urls);
		$stylesJson = Json::encode($styles);
		$inlinesJson = Json::encode($inlines);
		$stubsJs = implode("\n", $stubs);

		$runtime = <<<HTML
			<script data-gps-defer-runtime="1">
			(function(){
			{$stubsJs}
			var gpsU={$urlsJson};
			var gpsCss={$stylesJson};
			var gpsI={$inlinesJson};
			var gpsDone=false;
			function gpsRun(){
				if(gpsDone){return;}
				gpsDone=true;
				var i,s,l;
				for(i=0;i<gpsCss.length;i++){
					l=document.createElement('link');
					l.rel='stylesheet';
					l.href=gpsCss[i];
					(document.head||document.documentElement).appendChild(l);
				}
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
			var gpsEv=['scroll','mousemove','touchstart','keydown','click'];
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
