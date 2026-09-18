<?php

namespace Tools\GooglePageSpeed;

class HtmlBuffer
{
	/**
	 * Вставляет HTML сразу после открывающего <head>, не затирая тег и атрибуты.
	 */
	public static function insertAfterOpeningHead(&$content, string $html): void
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
	public static function addAttributeToMatchingScripts(&$content, array $value): void
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
}
