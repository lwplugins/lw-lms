<?php
/**
 * Lesson video player markup.
 *
 * @package LightweightPlugins\LMS
 */

declare(strict_types=1);

namespace LightweightPlugins\LMS\Video;

/**
 * Builds the player markup for a lesson's stored video data.
 *
 * LW LMS has no frontend of its own: lesson pages are built by the host theme
 * or app from the REST API. Shipping ready markup (`video.html`) next to the
 * bare embed URL lets integrations such as a consent manager decide what the
 * visitor actually receives, instead of every frontend hand-rolling an iframe.
 */
final class EmbedRenderer {

	/**
	 * Providers played through an iframe.
	 */
	public const IFRAME_PROVIDERS = [ 'youtube', 'vimeo', 'wistia' ];

	/**
	 * Makes a player (or its placeholder) fill the 16:9 box.
	 */
	public const FILL_STYLE = 'position:absolute;inset:0;width:100%;height:100%;border:0;';

	/**
	 * The 16:9 box every player sits in.
	 */
	private const BOX_STYLE = 'position:relative;width:100%;aspect-ratio:16/9;';

	/**
	 * Add the player markup to a stored video, for the REST payload.
	 *
	 * @param mixed $video Stored video meta.
	 * @return mixed Video data with `html`; null without video data; any
	 *               other stored shape is passed through untouched, as before.
	 */
	public static function payload( mixed $video ): mixed {
		if ( empty( $video ) ) {
			return null;
		}

		if ( ! is_array( $video ) ) {
			return $video;
		}

		return array_merge( $video, [ 'html' => self::render( $video ) ] );
	}

	/**
	 * Player markup for a stored video.
	 *
	 * @param array $video Stored video data (url, provider, video_id, embed, duration).
	 * @return string Markup, '' when there is nothing playable.
	 */
	public static function render( array $video ): string {
		$html = self::markup( $video );

		/**
		 * Filters the lesson video player markup (`video.html` in the REST API).
		 *
		 * @since 1.9.1
		 *
		 * @param string $html  Player markup, '' when there is nothing playable.
		 * @param array  $video Stored video data.
		 */
		return self::string_or( apply_filters( 'lw_lms_video_html', $html, $video ), $html );
	}

	/**
	 * A filter result, unless a callback returned something other than a
	 * string (e.g. forgot to return) — then the unfiltered markup.
	 *
	 * @param mixed  $filtered Value returned by apply_filters().
	 * @param string $fallback Unfiltered markup.
	 * @return string
	 */
	private static function string_or( mixed $filtered, string $fallback ): string {
		return is_string( $filtered ) ? $filtered : $fallback;
	}

	/**
	 * Wrap player content in the 16:9 box.
	 *
	 * @param string $inner    Already escaped markup.
	 * @param string $modifier Optional BEM modifier (lw-lms-video--{modifier}).
	 * @return string
	 */
	public static function box( string $inner, string $modifier = '' ): string {
		$class = '' === $modifier ? 'lw-lms-video' : 'lw-lms-video lw-lms-video--' . $modifier;

		return sprintf(
			'<div class="%1$s" style="%2$s">%3$s</div>',
			esc_attr( $class ),
			esc_attr( self::BOX_STYLE ),
			$inner
		);
	}

	/**
	 * Player iframe. Given attributes override the defaults.
	 *
	 * @param array<string, string> $attributes Attributes, e.g. [ 'src' => $url ].
	 * @return string
	 */
	public static function iframe( array $attributes ): string {
		$defaults = [
			'class'           => 'lw-lms-video__frame',
			'title'           => __( 'Lesson video', 'lw-lms' ),
			'allow'           => 'autoplay; picture-in-picture; encrypted-media',
			'allowfullscreen' => '',
			'loading'         => 'lazy',
			'style'           => self::FILL_STYLE,
		];

		return '<iframe' . self::attributes( array_merge( $defaults, $attributes ) ) . '></iframe>';
	}

	/**
	 * Unfiltered markup for a stored video.
	 *
	 * @param array $video Stored video data.
	 * @return string
	 */
	private static function markup( array $video ): string {
		$src      = (string) ( $video['embed'] ?? '' );
		$provider = (string) ( $video['provider'] ?? '' );

		if ( '' === $src ) {
			return '';
		}

		if ( in_array( $provider, self::IFRAME_PROVIDERS, true ) ) {
			return self::box( self::iframe( [ 'src' => $src ] ) );
		}

		if ( 'self' === $provider ) {
			$attributes = [
				'class'    => 'lw-lms-video__frame',
				'src'      => $src,
				'controls' => '',
				'preload'  => 'metadata',
				'style'    => self::FILL_STYLE,
			];

			return self::box( '<video' . self::attributes( $attributes ) . '></video>' );
		}

		return '';
	}

	/**
	 * Escaped attribute string. URL-valued attributes (`src`, `data-*-src`)
	 * go through esc_url(), an empty value renders a boolean attribute.
	 *
	 * @param array<string, string> $attributes Attribute name => value.
	 * @return string
	 */
	private static function attributes( array $attributes ): string {
		$out = '';

		foreach ( $attributes as $name => $value ) {
			if ( '' === $value ) {
				$out .= ' ' . esc_attr( $name );
				continue;
			}

			$escaped = str_ends_with( $name, 'src' ) ? esc_url( $value ) : esc_attr( $value );
			$out    .= sprintf( ' %s="%s"', esc_attr( $name ), $escaped );
		}

		return $out;
	}
}
