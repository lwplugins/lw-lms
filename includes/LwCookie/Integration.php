<?php
/**
 * LW Cookie Integration.
 *
 * When LW Cookie blocks third-party embeds, a lesson video the visitor has not
 * consented to yet is sent in LW Cookie's blocked form (placeholder + iframe
 * without src), which LW Cookie then loads in place once consent is given.
 * Without LW Cookie, or with its content blocking off, nothing changes.
 *
 * @package LightweightPlugins\LMS
 */

declare(strict_types=1);

namespace LightweightPlugins\LMS\LwCookie;

/**
 * Hooks the lesson video markup into LW Cookie's consent flow.
 */
final class Integration {

	/**
	 * First LW Cookie release whose guard restores blocked iframes in place
	 * and whose consent API offers LWCookie.acceptCategory().
	 */
	private const MIN_VERSION = '1.7.1';

	/**
	 * LW Cookie classes read at runtime (feature-detected, never required).
	 */
	private const OPTIONS  = 'LightweightPlugins\\Cookie\\Options';
	private const ENTITIES = 'LightweightPlugins\\Cookie\\Blocking\\Entities';

	/**
	 * Register hooks. Safe to call when LW Cookie is not installed.
	 *
	 * @return void
	 */
	public static function init(): void {
		add_filter( 'lw_lms_video_html', [ self::class, 'filter_video_html' ], 10, 2 );
		add_action( 'wp_enqueue_scripts', [ self::class, 'enqueue_script' ] );
	}

	/**
	 * Send an unconsented player in LW Cookie's blocked form.
	 *
	 * @param mixed $html  Player markup.
	 * @param mixed $video Stored video data.
	 * @return mixed
	 */
	public static function filter_video_html( $html, $video ) {
		if ( ! is_string( $html ) || ! is_array( $video ) || ! self::is_blocking_active() ) {
			return $html;
		}

		$gate = new EmbedGate(
			self::domains(),
			static function ( string $category ): bool {
				// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- LW Cookie's public consent filter.
				return (bool) apply_filters( 'lw_cookie_is_category_allowed', false, $category );
			}
		);

		return $gate->filter( $html, $video );
	}

	/**
	 * Load the placeholder button handler while LW Cookie is blocking.
	 *
	 * The button is part of markup LW Cookie did not build, so its own click
	 * handler is not attached; a delegated listener covers players inserted
	 * after page load too.
	 *
	 * @return void
	 */
	public static function enqueue_script(): void {
		if ( ! self::is_blocking_active() ) {
			return;
		}

		wp_enqueue_script(
			'lw-lms-video-consent',
			LW_LMS_URL . 'assets/js/video-consent.js',
			[],
			LW_LMS_VERSION,
			true
		);
	}

	/**
	 * Whether LW Cookie is active and blocks embeds on the frontend.
	 *
	 * @return bool
	 */
	public static function is_blocking_active(): bool {
		if ( ! defined( 'LW_COOKIE_VERSION' ) ) {
			return false;
		}

		if ( version_compare( (string) constant( 'LW_COOKIE_VERSION' ), self::MIN_VERSION, '<' ) ) {
			return false;
		}

		$get = [ self::OPTIONS, 'get' ];
		if ( ! is_callable( $get ) ) {
			return false;
		}

		return (bool) call_user_func( $get, 'enabled' ) && (bool) call_user_func( $get, 'content_blocking' );
	}

	/**
	 * LW Cookie's embed host => consent category map.
	 *
	 * @return array<string, string>
	 */
	private static function domains(): array {
		$get     = [ self::ENTITIES, 'get_domains' ];
		$domains = is_callable( $get ) ? call_user_func( $get ) : [];

		return is_array( $domains ) ? $domains : [];
	}
}
