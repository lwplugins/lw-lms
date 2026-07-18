<?php
/**
 * Characterization tests for video URL parsing.
 *
 * VideoParser::parse() uses only PHP built-ins, so these are plain unit tests.
 *
 * @package LightweightPlugins\LMS
 */

declare(strict_types=1);

namespace LightweightPlugins\LMS\Tests\Unit\Meta;

use LightweightPlugins\LMS\Meta\VideoParser;
use PHPUnit\Framework\TestCase;

/**
 * @covers \LightweightPlugins\LMS\Meta\VideoParser
 */
final class VideoParserTest extends TestCase {

	public function test_empty_url_returns_empty_result(): void {
		$result = VideoParser::parse( '   ' );

		$this->assertSame( '', $result['provider'] );
		$this->assertNull( $result['video_id'] );
		$this->assertSame( '', $result['embed'] );
	}

	/**
	 * @dataProvider provide_youtube_urls
	 */
	public function test_parses_youtube( string $url, string $expected_id ): void {
		$result = VideoParser::parse( $url );

		$this->assertSame( 'youtube', $result['provider'] );
		$this->assertSame( $expected_id, $result['video_id'] );
		$this->assertSame( "https://www.youtube.com/embed/{$expected_id}", $result['embed'] );
	}

	/**
	 * @return array<string, array{0: string, 1: string}>
	 */
	public static function provide_youtube_urls(): array {
		return array(
			'watch'  => array( 'https://www.youtube.com/watch?v=dQw4w9WgXcQ', 'dQw4w9WgXcQ' ),
			'embed'  => array( 'https://www.youtube.com/embed/dQw4w9WgXcQ', 'dQw4w9WgXcQ' ),
			'short'  => array( 'https://youtu.be/dQw4w9WgXcQ', 'dQw4w9WgXcQ' ),
		);
	}

	public function test_parses_vimeo(): void {
		$result = VideoParser::parse( 'https://vimeo.com/123456789' );

		$this->assertSame( 'vimeo', $result['provider'] );
		$this->assertSame( '123456789', $result['video_id'] );
		$this->assertSame( 'https://player.vimeo.com/video/123456789', $result['embed'] );
	}

	public function test_parses_wistia(): void {
		$result = VideoParser::parse( 'https://myco.wistia.com/medias/abc123XYZ' );

		$this->assertSame( 'wistia', $result['provider'] );
		$this->assertSame( 'abc123XYZ', $result['video_id'] );
	}

	public function test_unknown_url_is_treated_as_self_hosted(): void {
		$url    = 'https://cdn.example.com/lesson.mp4';
		$result = VideoParser::parse( $url );

		$this->assertSame( 'self', $result['provider'] );
		$this->assertNull( $result['video_id'] );
		$this->assertSame( $url, $result['embed'] );
	}
}
