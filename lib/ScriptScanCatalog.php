<?php

namespace Tools\GooglePageSpeed;

/**
 * Реестр для сканера скриптов: что скрывать и какие «знакомые» пресеты предлагать.
 *
 * hide — системные path Bitrix / аналитика (якорь на начало path после хоста;
 * не трогает /local/.../bitrix/... и не скрывает /bitrix/templates/).
 * presets — сторонние скрипты: цвет, атрибут, автодобавление в правила.
 */
class ScriptScanCatalog
{
	/**
	 * Правила исключения из выдачи сканера.
	 * Для path Bitrix: якорь `^(?:https?://[^/]+)?/bitrix/...` — только корень сайта.
	 * /bitrix/templates/ намеренно нет.
	 *
	 * @return array<int, array{reason: string, pattern: string}>
	 */
	public static function getHideRules(): array
	{
		return [
			['reason' => 'core', 'pattern' => '#^(?:https?://[^/]+)?/bitrix/js/#i'],
			['reason' => 'core', 'pattern' => '#^(?:https?://[^/]+)?/bitrix/cache/#i'],
			['reason' => 'core', 'pattern' => '#^(?:https?://[^/]+)?/bitrix/components/#i'],
			['reason' => 'core', 'pattern' => '#^(?:https?://[^/]+)?/bitrix/panel/#i'],
			['reason' => 'core', 'pattern' => '#^(?:https?://[^/]+)?/bitrix/themes/#i'],
			['reason' => 'core', 'pattern' => '#^(?:https?://[^/]+)?/bitrix/tools/#i'],
			['reason' => 'core', 'pattern' => '#^(?:https?://[^/]+)?/bitrix/resources/#i'],
			['reason' => 'core', 'pattern' => '#^(?:https?://[^/]+)?/bitrix/admin/#i'],
			['reason' => 'core', 'pattern' => '#^(?:https?://[^/]+)?/bitrix/modules/#i'],
			['reason' => 'core', 'pattern' => '/kernel_main|kernel_main_v1/i'],

			['reason' => 'analytics', 'pattern' => '/mc\.yandex\.(?:ru|com)/i'],
			['reason' => 'analytics', 'pattern' => '/yandex\.ru\/metrika|yandex\.com\/metrika/i'],
			['reason' => 'analytics', 'pattern' => '/googletagmanager\.com/i'],
			['reason' => 'analytics', 'pattern' => '/google-analytics\.com/i'],
		];
	}

	/**
	 * Известные сторонние скрипты: клик/автодобавление с готовым async|defer.
	 *
	 * @return array<int, array{
	 *   id: string,
	 *   label: string,
	 *   color: string,
	 *   attribute: 'async'|'defer',
	 *   pattern: string,
	 *   publicPart: string
	 * }>
	 */
	public static function getPresets(): array
	{
		return [
			[
				'id' => 'jivo',
				'label' => 'Jivo',
				'color' => '#0ea5e9',
				'attribute' => 'defer',
				'pattern' => '/code\.jivo\.ru|cdn\.jivo\.ru|jivosite\.com|jivo\.ru\/widget/i',
				'publicPart' => 'code.jivo.ru',
			],
			[
				'id' => 'calltouch',
				'label' => 'Calltouch',
				'color' => '#14b8a6',
				'attribute' => 'defer',
				'pattern' => '/calltouch\.ru|mod\.calltouch/i',
				'publicPart' => 'calltouch.ru',
			],
			[
				'id' => 'vk_pixel',
				'label' => 'VK Pixel',
				'color' => '#3b82f6',
				'attribute' => 'async',
				'pattern' => '/vk\.com\/js\/api\/openapi|top-fwz1\.mail\.ru|vk\.com\/rtrg/i',
				'publicPart' => 'vk.com',
			],
			[
				'id' => 'facebook',
				'label' => 'Facebook / Meta Pixel',
				'color' => '#6366f1',
				'attribute' => 'async',
				'pattern' => '/connect\.facebook\.net|facebook\.com\/tr/i',
				'publicPart' => 'connect.facebook.net',
			],
			[
				'id' => 'tiktok',
				'label' => 'TikTok Pixel',
				'color' => '#ec4899',
				'attribute' => 'async',
				'pattern' => '/analytics\.tiktok\.com|tiktok\.com\/i18n\/pixel/i',
				'publicPart' => 'analytics.tiktok.com',
			],
			[
				'id' => 'roistat',
				'label' => 'Roistat',
				'color' => '#f59e0b',
				'attribute' => 'defer',
				'pattern' => '/cloud\.roistat\.com|roistat\.com/i',
				'publicPart' => 'roistat.com',
			],
			[
				'id' => 'vk_ads',
				'label' => 'VK Реклама',
				'color' => '#2563eb',
				'attribute' => 'async',
				'pattern' => '/vk\.com\/ads|ads\.vk\.com|vk\.com\/rtrg/i',
				'publicPart' => 'ads.vk.com',
			],
			[
				'id' => 'mytarget',
				'label' => 'myTarget',
				'color' => '#f97316',
				'attribute' => 'async',
				'pattern' => '/target\.my\.com|mytarget\.ru|top-fwz1\.mail\.ru/i',
				'publicPart' => 'target.my.com',
			],
			[
				'id' => 'mango',
				'label' => 'Mango Office',
				'color' => '#f43f5e',
				'attribute' => 'defer',
				'pattern' => '/mango-office\.ru|widgets\.mango-office\.ru|calltracking\.ru/i',
				'publicPart' => 'mango-office.ru',
			],
			[
				'id' => 'uis',
				'label' => 'UIS',
				'color' => '#8b5cf6',
				'attribute' => 'defer',
				'pattern' => '/uiscom\.ru|uis\.cc|widgets\.uiscom\.ru/i',
				'publicPart' => 'uiscom.ru',
			],
			[
				'id' => 'callibri',
				'label' => 'Callibri',
				'color' => '#06b6d4',
				'attribute' => 'defer',
				'pattern' => '/callibri\.ru|callibri\.com/i',
				'publicPart' => 'callibri.ru',
			],
			[
				'id' => 'carrotquest',
				'label' => 'Carrot quest',
				'color' => '#f97316',
				'attribute' => 'defer',
				'pattern' => '/carrotquest\.io|carrotquest\.com/i',
				'publicPart' => 'carrotquest.io',
			],
			[
				'id' => 'envybox',
				'label' => 'Envybox',
				'color' => '#ef4444',
				'attribute' => 'defer',
				'pattern' => '/envybox\.io|envybox\.ru|envybox\.com/i',
				'publicPart' => 'envybox.io',
			],
			[
				'id' => 'webim',
				'label' => 'Webim',
				'color' => '#22c55e',
				'attribute' => 'defer',
				'pattern' => '/webim\.ru|webim\.cc/i',
				'publicPart' => 'webim.ru',
			],
			[
				'id' => 'smartcaptcha',
				'label' => 'Яндекс SmartCaptcha',
				'color' => '#ffcc00',
				'attribute' => 'async',
				'pattern' => '/smartcaptcha\.yandexcloud\.tech|captcha-api\.yandex\.ru|yandexcloud\.tech\/captcha/i',
				'publicPart' => 'smartcaptcha.yandexcloud.tech',
			],
			[
				'id' => 'recaptcha',
				'label' => 'Google reCAPTCHA',
				'color' => '#4285f4',
				'attribute' => 'async',
				'pattern' => '/google\.com\/recaptcha|gstatic\.com\/recaptcha/i',
				'publicPart' => 'www.google.com/recaptcha',
			],
			[
				'id' => 'turnstile',
				'label' => 'Cloudflare Turnstile',
				'color' => '#f6821f',
				'attribute' => 'async',
				'pattern' => '/challenges\.cloudflare\.com\/turnstile|cloudflare\.com\/turnstile/i',
				'publicPart' => 'challenges.cloudflare.com',
			],
			[
				'id' => '2gis',
				'label' => '2GIS',
				'color' => '#16a34a',
				'attribute' => 'defer',
				'pattern' => '/2gis\.ru|maps\.2gis\.ru|mapgl\.2gis\.com/i',
				'publicPart' => '2gis.ru',
			],
			[
				'id' => 'yookassa',
				'label' => 'ЮKassa',
				'color' => '#8b5cf6',
				'attribute' => 'defer',
				'pattern' => '/yookassa\.ru|yoomoney\.ru|yookassa\.ru\/checkout/i',
				'publicPart' => 'yookassa.ru',
			],
			[
				'id' => 'cloudpayments',
				'label' => 'CloudPayments',
				'color' => '#2563eb',
				'attribute' => 'defer',
				'pattern' => '/cloudpayments\.ru|widget\.cloudpayments\.ru/i',
				'publicPart' => 'cloudpayments.ru',
			],
		];
	}

	/**
	 * Пресеты URL для поля сканера (относительные пути; в UI клеятся к origin сайта).
	 *
	 * @return array<int, array{id: string, label: string, path: string}>
	 */
	public static function getScanUrlPresets(): array
	{
		return [
			['id' => 'home', 'label' => 'Главная', 'path' => '/'],
			['id' => 'catalog', 'label' => 'Каталог', 'path' => '/catalog/'],
			['id' => 'contacts', 'label' => 'Контакты', 'path' => '/contacts/'],
		];
	}

	/**
	 * @return array{reason: string}|null
	 */
	public static function matchHide(string $src): ?array
	{
		foreach (self::getHideRules() as $rule) {
			if (preg_match($rule['pattern'], $src)) {
				return ['reason' => $rule['reason']];
			}
		}

		return null;
	}

	/**
	 * @return array{id: string, label: string, color: string, attribute: string, publicPart: string}|null
	 */
	public static function matchPreset(string $src): ?array
	{
		foreach (self::getPresets() as $preset) {
			if (preg_match($preset['pattern'], $src)) {
				return [
					'id' => $preset['id'],
					'label' => $preset['label'],
					'color' => $preset['color'],
					'attribute' => $preset['attribute'],
					'publicPart' => $preset['publicPart'],
				];
			}
		}

		return null;
	}
}
