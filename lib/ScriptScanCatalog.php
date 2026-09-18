<?php

namespace Tools\GooglePageSpeed;

/**
 * Реестр для сканера скриптов: что скрывать и какие «знакомые» пресеты предлагать.
 *
 * hide — не показывать в результатах (ядро Bitrix, Метрика/GA — ими занимаются опции).
 * presets — узнаваемые сторонние скрипты: цвет, атрибут, автодобавление в правила.
 */
class ScriptScanCatalog
{
	/**
	 * Паттерны URL, которые полностью исключаются из выдачи сканера.
	 * Ключ — причина для счётчика: core | analytics.
	 *
	 * @return array<int, array{reason: string, pattern: string}>
	 */
	public static function getHideRules(): array
	{
		return [
			['reason' => 'core', 'pattern' => '/\/bitrix\/js\//i'],
			['reason' => 'core', 'pattern' => '/\/bitrix\/cache\//i'],
			['reason' => 'core', 'pattern' => '/\/bitrix\/components\//i'],
			['reason' => 'core', 'pattern' => '/\/bitrix\/templates\//i'],
			['reason' => 'core', 'pattern' => '/\/bitrix\/panel\//i'],
			['reason' => 'core', 'pattern' => '/\/bitrix\/themes\//i'],
			['reason' => 'core', 'pattern' => '/\/bitrix\/tools\//i'],
			['reason' => 'core', 'pattern' => '/kernel_main|kernel_main_v1/i'],
			['reason' => 'core', 'pattern' => '/\/bitrix\/js\/main\//i'],

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
				'label' => 'Facebook',
				'color' => '#6366f1',
				'attribute' => 'async',
				'pattern' => '/connect\.facebook\.net|facebook\.com\/tr/i',
				'publicPart' => 'connect.facebook.net',
			],
			[
				'id' => 'tiktok',
				'label' => 'TikTok',
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
