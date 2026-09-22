<?php
/**
 * Lesson video markup in LW Cookie's blocked form.
 *
 * @package LightweightPlugins\LMS
 */

declare(strict_types=1);

namespace LightweightPlugins\LMS\LwCookie;

use LightweightPlugins\LMS\Video\EmbedRenderer;

/**
 * Renders a lesson video the way LW Cookie's guard leaves a blocked embed:
 * a `.lw-cookie-embed-block` placeholder followed by an iframe without `src`
 * that carries `data-lw-blocked`, `data-lw-category` and
 * `data-lw-original-src`. LW Cookie styles the placeholder, and whenever the
 * visitor saves consent its guard (restoreAllowed) puts the `src` back and
 * removes the placeholder — so the video loads in place.
 *
 * Rendered on the server rather than left to the guard's MutationObserver:
 * nothing is requested from the video host before consent, and the
 * placeholder does not depend on how the frontend wraps or inserts the player.
 */
final class BlockedEmbed {

	/**
	 * Fills the 16:9 box; LW Cookie's own CSS does the rest.
	 */
	private const PLACEHOLDER_STYLE = 'position:absolute;inset:0;margin:0;min-height:0;';

	/**
	 * Blocked player markup.
	 *
	 * @param array  $video    Stored video data of an iframe provider.
	 * @param string $category LW Cookie consent category the player needs.
	 * @return string
	 */
	public static function render( array $video, string $category ): string {
		$placeholder = sprintf(
			'<div class="lw-cookie-embed-block lw-lms-video__consent" style="%1$s"><p class="lw-cookie-embed-block__msg">%2$s</p><button type="button" class="lw-cookie-embed-block__btn" data-lw-lms-consent="%3$s">%4$s</button></div>',
			esc_attr( self::PLACEHOLDER_STYLE ),
			esc_html__( 'To watch this video, accept the required cookies.', 'lw-lms' ),
			esc_attr( $category ),
			esc_html__( 'Accept & play video', 'lw-lms' )
		);

		$iframe = EmbedRenderer::iframe(
			[
				'data-lw-blocked'      => '1',
				'data-lw-category'     => $category,
				'data-lw-original-src' => (string) ( $video['embed'] ?? '' ),
				'style'                => EmbedRenderer::FILL_STYLE . 'display:none;',
			]
		);

		// No whitespace in between: the guard only drops the placeholder when
		// it is the iframe's previousSibling node.
		return EmbedRenderer::box( $placeholder . $iframe, 'blocked' );
	}
}
