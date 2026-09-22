<?php
/**
 * Tests for the lesson video player markup.
 *
 * @package LightweightPlugins\LMS
 */

declare(strict_types=1);

namespace LightweightPlugins\LMS\Tests\Unit\Video;

use Brain\Monkey\Filters;
use Brain\Monkey\Functions;
use LightweightPlugins\LMS\Meta\VideoParser;
use LightweightPlugins\LMS\Tests\Unit\MonkeyTestCase;
use LightweightPlugins\LMS\Video\EmbedRenderer;

/**
 * @covers \LightweightPlugins\LMS\Video\EmbedRenderer
 */
final class EmbedRendererTest extends MonkeyTestCase {

	protected function setUp(): void {
		parent::setUp();
		Functions\stubEscapeFunctions();
		Functions\stubTranslationFunctions();
	}

	public function test_iframe_providers_get_a_player_iframe_with_the_embed_url(): void {
		$html = EmbedRenderer::render( VideoParser::parse( 'https://vimeo.com/76979871' ) );

		$this->assertStringContainsString( '<div class="lw-lms-video"', $html );
		$this->assertStringContainsString( '<iframe', $html );
		$this->assertStringContainsString( 'src="https://player.vimeo.com/video/76979871"', $html );
		$this->assertStringContainsString( ' allowfullscreen', $html );
		$this->assertStringContainsString( 'title="Lesson video"', $html );
	}

	/**
	 * @dataProvider provide_iframe_urls
	 */
	public function test_every_iframe_provider_is_embedded( string $url, string $embed ): void {
		$html = EmbedRenderer::render( VideoParser::parse( $url ) );

		$this->assertStringContainsString( 'src="' . $embed . '"', $html );
		$this->assertStringContainsString( '<iframe', $html );
	}

	public static function provide_iframe_urls(): array {
		return [
			'youtube' => [ 'https://www.youtube.com/watch?v=abc123', 'https://www.youtube.com/embed/abc123' ],
			'vimeo'   => [ 'https://player.vimeo.com/video/42', 'https://player.vimeo.com/video/42' ],
			'wistia'  => [ 'https://fast.wistia.net/embed/iframe/xyz9', 'https://fast.wistia.net/embed/iframe/xyz9' ],
		];
	}

	public function test_self_hosted_video_gets_a_video_element(): void {
		$html = EmbedRenderer::render( VideoParser::parse( 'https://example.com/lesson.mp4' ) );

		$this->assertStringContainsString( '<video', $html );
		$this->assertStringContainsString( 'src="https://example.com/lesson.mp4"', $html );
		$this->assertStringContainsString( ' controls', $html );
		$this->assertStringNotContainsString( '<iframe', $html );
	}

	public function test_no_markup_without_a_playable_video(): void {
		$this->assertSame( '', EmbedRenderer::render( VideoParser::parse( '' ) ) );
		$this->assertSame(
			'',
			EmbedRenderer::render(
				[
					'provider' => 'dailymotion',
					'embed'    => 'https://example.com/x',
				]
			)
		);
	}

	public function test_the_embed_url_is_escaped_as_a_url(): void {
		// Stand-in for esc_url()'s character whitelist, which drops quotes.
		Functions\when( 'esc_url' )->alias( static fn( string $url ): string => str_replace( '"', '', $url ) );

		$html = EmbedRenderer::render(
			[
				'provider' => 'youtube',
				'embed'    => 'https://www.youtube.com/embed/a"onload="alert(1)',
			]
		);

		$this->assertStringContainsString( 'src="https://www.youtube.com/embed/aonload=alert(1)"', $html );
	}

	public function test_markup_goes_through_the_video_html_filter(): void {
		$video = VideoParser::parse( 'https://vimeo.com/76979871' );

		Filters\expectApplied( 'lw_lms_video_html' )
			->once()
			->with( \Mockery::type( 'string' ), $video )
			->andReturn( '<p>filtered</p>' );

		$this->assertSame( '<p>filtered</p>', EmbedRenderer::render( $video ) );
	}

	public function test_a_non_string_filter_result_is_ignored(): void {
		$video = VideoParser::parse( 'https://vimeo.com/76979871' );

		Filters\expectApplied( 'lw_lms_video_html' )->once()->andReturn( null );

		$this->assertStringContainsString( '<iframe', EmbedRenderer::render( $video ) );
	}

	public function test_payload_adds_the_markup_and_keeps_the_stored_fields(): void {
		$video   = VideoParser::parse( 'https://vimeo.com/76979871' );
		$payload = EmbedRenderer::payload( $video );

		$this->assertIsArray( $payload );
		$this->assertSame( $video, array_diff_key( $payload, [ 'html' => true ] ) );
		$this->assertStringContainsString( '<iframe', $payload['html'] );
	}

	public function test_payload_is_null_without_video_data(): void {
		$this->assertNull( EmbedRenderer::payload( [] ) );
		$this->assertNull( EmbedRenderer::payload( '' ) );
	}

	public function test_payload_passes_a_non_array_meta_through(): void {
		$this->assertSame( 'https://vimeo.com/1', EmbedRenderer::payload( 'https://vimeo.com/1' ) );
	}
}
