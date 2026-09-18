<?php

namespace Tools\GooglePageSpeed;

class OptionActions
{
	/** @var array<string, array{0: class-string, 1: string}> */
	private const ACTIONS = [
		'eliminateStyleSheetsThatBlockDisplay' => [self::class, 'eliminateStyleSheetsThatBlockDisplay'],
		'eliminateScriptsThatBlockDisplay' => [self::class, 'eliminateScriptsThatBlockDisplay'],
		'addLoadingLazyAttributeAllTagsImg' => [self::class, 'addLoadingLazyAttributeAllTagsImg'],
		'deferYandexMetrika' => [ScriptDeferral::class, 'deferYandexMetrika'],
		'deferGoogleAnalytics' => [ScriptDeferral::class, 'deferGoogleAnalytics'],
		// 'deferJivoChat' => [ScriptDeferral::class, 'deferJivoChat'],
	];

	public static function run(string $methodName, &$content): bool
	{
		if (!isset(self::ACTIONS[$methodName])) {
			return false;
		}

		[$class, $method] = self::ACTIONS[$methodName];
		$class::$method($content);

		return true;
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
}
