<?php
/**
 * LW Cookie consent decision for a lesson video.
 *
 * @package LightweightPlugins\LMS
 */

declare(strict_types=1);

namespace LightweightPlugins\LMS\LwCookie;

use LightweightPlugins\LMS\Video\EmbedRenderer;

/**
 * Decides whether a lesson video player may be sent as is, or has to wait
 * for consent in LW Cookie's blocked form. Holds no LW Cookie dependency
 * itself: the domain map and the consent check are handed in.
 */
final class EmbedGate {

	/**
	 * Host (or host/path) => consent category, in LW Cookie's order.
	 *
	 * @var array<string, string>
	 */
	private array $domains;

	/**
	 * Consent check: category => whether the visitor accepted it.
	 *
	 * @var \Closure
	 */
	private \Closure $is_allowed;

	/**
	 * Constructor.
	 *
	 * @param array<string, string> $domains    Host => category map.
	 * @param \Closure              $is_allowed Receives a category, returns bool.
	 */
	public function __construct( array $domains, \Closure $is_allowed ) {
		$this->domains    = $domains;
		$this->is_allowed = $is_allowed;
	}

	/**
	 * Player markup to send: unchanged, or blocked until consent.
	 *
	 * @param string $html  Player markup.
	 * @param array  $video Stored video data.
	 * @return string
	 */
	public function filter( string $html, array $video ): string {
		if ( '' === $html || ! in_array( $video['provider'] ?? '', EmbedRenderer::IFRAME_PROVIDERS, true ) ) {
			return $html;
		}

		$category = $this->category_for( (string) ( $video['embed'] ?? '' ) );

		if ( null === $category || ( $this->is_allowed )( $category ) ) {
			return $html;
		}

		return BlockedEmbed::render( $video, $category );
	}

	/**
	 * Consent category LW Cookie assigns to a URL — the same lookup as
	 * getCategoryForUrl() in its guard.js: the first entry wins, matching the
	 * host (without `www.`) or any subdomain of it, or, for entries with a
	 * path such as `google.com/maps`, any URL that contains it.
	 *
	 * @param string $url Embed URL.
	 * @return string|null Category, or null when LW Cookie does not block it.
	 */
	public function category_for( string $url ): ?string {
		$host = wp_parse_url( $url, PHP_URL_HOST );

		if ( ! is_string( $host ) || '' === $host ) {
			return null;
		}

		$host = strtolower( $host );
		if ( str_starts_with( $host, 'www.' ) ) {
			$host = substr( $host, 4 );
		}

		foreach ( $this->domains as $domain => $category ) {
			$domain = (string) $domain;

			if ( $host === $domain || str_ends_with( $host, '.' . $domain ) ) {
				return $category;
			}

			if ( str_contains( $domain, '/' ) && str_contains( $url, $domain ) ) {
				return $category;
			}
		}

		return null;
	}
}
