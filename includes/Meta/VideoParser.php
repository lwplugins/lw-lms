<?php
/**
 * Video URL Parser.
 *
 * @package LightweightPlugins\LMS
 */

declare(strict_types=1);

namespace LightweightPlugins\LMS\Meta;

/**
 * Parses video URLs and extracts provider information.
 */
final class VideoParser {

	/**
	 * Parse a video URL and return video data.
	 *
	 * @param string $url Video URL.
	 * @return array{url: string, provider: string, video_id: string|null, embed: string, duration: string}
	 */
	public static function parse( string $url ): array {
		$url = trim( $url );

		if ( empty( $url ) ) {
			return self::get_empty_result();
		}

		// YouTube.
		$youtube = self::parse_youtube( $url );
		if ( $youtube ) {
			return $youtube;
		}

		// Vimeo.
		$vimeo = self::parse_vimeo( $url );
		if ( $vimeo ) {
			return $vimeo;
		}

		// Wistia.
		$wistia = self::parse_wistia( $url );
		if ( $wistia ) {
			return $wistia;
		}

		// Self-hosted (default).
		return self::parse_self_hosted( $url );
	}

	/**
	 * Parse YouTube URL.
	 *
	 * @param string $url Video URL.
	 * @return array|null
	 */
	private static function parse_youtube( string $url ): ?array {
		$patterns = [
			'/youtube\.com\/watch\?v=([^&\s]+)/',
			'/youtube\.com\/embed\/([^?\s]+)/',
			'/youtu\.be\/([^?\s]+)/',
		];

		foreach ( $patterns as $pattern ) {
			if ( preg_match( $pattern, $url, $matches ) ) {
				$video_id = $matches[1];
				return [
					'url'      => $url,
					'provider' => 'youtube',
					'video_id' => $video_id,
					'embed'    => "https://www.youtube.com/embed/{$video_id}",
					'duration' => '',
				];
			}
		}

		return null;
	}

	/**
	 * Parse Vimeo URL.
	 *
	 * @param string $url Video URL.
	 * @return array|null
	 */
	private static function parse_vimeo( string $url ): ?array {
		$patterns = [
			'/vimeo\.com\/(\d+)/',
			'/player\.vimeo\.com\/video\/(\d+)/',
		];

		foreach ( $patterns as $pattern ) {
			if ( preg_match( $pattern, $url, $matches ) ) {
				$video_id = $matches[1];
				return [
					'url'      => $url,
					'provider' => 'vimeo',
					'video_id' => $video_id,
					'embed'    => "https://player.vimeo.com/video/{$video_id}",
					'duration' => '',
				];
			}
		}

		return null;
	}

	/**
	 * Parse Wistia URL.
	 *
	 * @param string $url Video URL.
	 * @return array|null
	 */
	private static function parse_wistia( string $url ): ?array {
		$patterns = [
			'/wistia\.com\/medias\/([a-zA-Z0-9]+)/',
			'/fast\.wistia\.net\/embed\/iframe\/([a-zA-Z0-9]+)/',
		];

		foreach ( $patterns as $pattern ) {
			if ( preg_match( $pattern, $url, $matches ) ) {
				$video_id = $matches[1];
				return [
					'url'      => $url,
					'provider' => 'wistia',
					'video_id' => $video_id,
					'embed'    => "https://fast.wistia.net/embed/iframe/{$video_id}",
					'duration' => '',
				];
			}
		}

		return null;
	}

	/**
	 * Parse self-hosted video URL.
	 *
	 * @param string $url Video URL.
	 * @return array
	 */
	private static function parse_self_hosted( string $url ): array {
		return [
			'url'      => $url,
			'provider' => 'self',
			'video_id' => null,
			'embed'    => $url,
			'duration' => '',
		];
	}

	/**
	 * Get empty result array.
	 *
	 * @return array
	 */
	private static function get_empty_result(): array {
		return [
			'url'      => '',
			'provider' => '',
			'video_id' => null,
			'embed'    => '',
			'duration' => '',
		];
	}
}
