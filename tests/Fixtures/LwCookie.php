<?php
/**
 * Minimal stand-in for the LW Cookie plugin, for tests only.
 *
 * Mirrors the two LW Cookie classes the LMS integration reads. Loaded only
 * inside tests that run in a separate process, so the "LW Cookie is not
 * installed" tests keep a clean global state.
 *
 * @package LightweightPlugins\LMS
 */

declare(strict_types=1);

namespace LightweightPlugins\Cookie {

	/**
	 * Stand-in for LightweightPlugins\Cookie\Options.
	 */
	final class Options {

		/**
		 * Option values returned by get().
		 *
		 * @var array<string, mixed>
		 */
		public static array $values = [
			'enabled'          => true,
			'content_blocking' => true,
		];

		/**
		 * Get an option.
		 *
		 * @param string $key      Option key.
		 * @param mixed  $fallback Default value.
		 * @return mixed
		 */
		public static function get( string $key, mixed $fallback = null ): mixed {
			return self::$values[ $key ] ?? $fallback;
		}
	}
}

namespace LightweightPlugins\Cookie\Blocking {

	/**
	 * Stand-in for LightweightPlugins\Cookie\Blocking\Entities.
	 */
	final class Entities {

		/**
		 * Domain => category map.
		 *
		 * @return array<string, string>
		 */
		public static function get_domains(): array {
			return [
				'youtube.com'      => 'marketing',
				'vimeo.com'        => 'marketing',
				'player.vimeo.com' => 'marketing',
			];
		}
	}
}
