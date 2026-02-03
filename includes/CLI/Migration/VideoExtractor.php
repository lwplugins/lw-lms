<?php
/**
 * Video Extractor for LearnDash migration.
 *
 * @package LightweightPlugins\LMS
 */

declare(strict_types=1);

namespace LightweightPlugins\LMS\CLI\Migration;

use LightweightPlugins\LMS\Meta\VideoParser;

/**
 * Extracts video data from LearnDash lessons.
 */
final class VideoExtractor {

	/**
	 * Extract video data from a LearnDash lesson.
	 *
	 * @param \WP_Post $lesson     Lesson post.
	 * @param array    $ld_settings LearnDash settings.
	 * @return array
	 */
	public static function extract( \WP_Post $lesson, array $ld_settings ): array {
		$url = self::from_learndash_meta( $ld_settings );

		if ( empty( $url ) ) {
			$url = self::from_elementor( $lesson->ID );
		}

		if ( empty( $url ) ) {
			$url = self::from_content( $lesson->post_content );
		}

		if ( empty( $url ) ) {
			return [];
		}

		return VideoParser::parse( $url );
	}

	/**
	 * Extract video URL from LearnDash meta.
	 *
	 * @param array $ld_settings LearnDash settings.
	 * @return string
	 */
	private static function from_learndash_meta( array $ld_settings ): string {
		return $ld_settings['sfwd-lessons_lesson_video_url'] ?? '';
	}

	/**
	 * Extract video URL from Elementor data.
	 *
	 * @param int $post_id Post ID.
	 * @return string
	 */
	private static function from_elementor( int $post_id ): string {
		$elementor_data = get_post_meta( $post_id, '_elementor_data', true );

		if ( ! $elementor_data || ! is_string( $elementor_data ) ) {
			return '';
		}

		$data = json_decode( $elementor_data, true );

		if ( ! is_array( $data ) ) {
			return '';
		}

		return self::search_elementor_data( $data );
	}

	/**
	 * Recursively search Elementor data for video URLs.
	 *
	 * @param array $data Elementor data array.
	 * @return string
	 */
	private static function search_elementor_data( array $data ): string {
		foreach ( $data as $key => $value ) {
			if ( ( 'youtube_url' === $key || 'vimeo_url' === $key ) && is_string( $value ) && ! empty( $value ) ) {
				return $value;
			}

			if ( is_array( $value ) ) {
				$result = self::search_elementor_data( $value );
				if ( $result ) {
					return $result;
				}
			}
		}

		return '';
	}

	/**
	 * Extract video URL from post content.
	 *
	 * @param string $content Post content.
	 * @return string
	 */
	private static function from_content( string $content ): string {
		// YouTube.
		if ( preg_match( '/(?:youtube\.com\/(?:watch\?v=|embed\/)|youtu\.be\/)([a-zA-Z0-9_-]+)/', $content, $matches ) ) {
			return 'https://www.youtube.com/watch?v=' . $matches[1];
		}

		// Vimeo.
		if ( preg_match( '/vimeo\.com\/(?:video\/)?(\d+)/', $content, $matches ) ) {
			return 'https://vimeo.com/' . $matches[1];
		}

		// Wistia.
		if ( preg_match( '/wistia\.com\/medias\/([a-zA-Z0-9]+)/', $content, $matches ) ) {
			return 'https://fast.wistia.com/medias/' . $matches[1];
		}

		return '';
	}
}
