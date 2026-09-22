<?php
/**
 * Tests for the LW Cookie integration glue.
 *
 * @package LightweightPlugins\LMS
 */

declare(strict_types=1);

namespace LightweightPlugins\LMS\Tests\Unit\LwCookie;

use Brain\Monkey\Filters;
use Brain\Monkey\Functions;
use LightweightPlugins\LMS\LwCookie\Integration;
use LightweightPlugins\LMS\Meta\VideoParser;
use LightweightPlugins\LMS\Tests\Unit\MonkeyTestCase;
use LightweightPlugins\LMS\Video\EmbedRenderer;

/**
 * Regression for issue #18: with LW Cookie content blocking on, the lesson
 * video must reach the visitor as LW Cookie's placeholder instead of a black
 * box. Tests that load the LW Cookie stand-in run in a separate process so
 * the "not installed" cases keep a clean global state.
 *
 * @covers \LightweightPlugins\LMS\LwCookie\Integration
 */
final class IntegrationTest extends MonkeyTestCase {

	protected function setUp(): void {
		parent::setUp();
		Functions\stubEscapeFunctions();
		Functions\stubTranslationFunctions();
		Functions\when( 'wp_parse_url' )->alias( 'parse_url' );
	}

	public function test_video_markup_is_untouched_without_lw_cookie(): void {
		$video = VideoParser::parse( 'https://vimeo.com/76979871' );
		$html  = EmbedRenderer::render( $video );

		$this->assertFalse( Integration::is_blocking_active() );
		$this->assertSame( $html, Integration::filter_video_html( $html, $video ) );
	}

	public function test_no_script_without_lw_cookie(): void {
		$enqueued = self::capture_enqueues();

		Integration::enqueue_script();

		$this->assertSame( [], $enqueued->getArrayCopy() );
	}

	/**
	 * @runInSeparateProcess
	 * @preserveGlobalState disabled
	 */
	public function test_unconsented_vimeo_player_becomes_the_lw_cookie_placeholder(): void {
		self::boot_lw_cookie();
		Filters\expectApplied( 'lw_cookie_is_category_allowed' )
			->once()
			->with( false, 'marketing' )
			->andReturn( false );

		$video = VideoParser::parse( 'https://vimeo.com/76979871' );
		$html  = Integration::filter_video_html( EmbedRenderer::render( $video ), $video );

		$this->assertStringContainsString( 'lw-cookie-embed-block', $html );
		$this->assertStringContainsString( 'data-lw-blocked="1"', $html );
		$this->assertStringContainsString( 'data-lw-original-src="https://player.vimeo.com/video/76979871"', $html );
		$this->assertDoesNotMatchRegularExpression( '/<iframe[^>]*\ssrc=/', $html );
	}

	/**
	 * @runInSeparateProcess
	 * @preserveGlobalState disabled
	 */
	public function test_consented_visitor_gets_the_player(): void {
		self::boot_lw_cookie();
		Filters\expectApplied( 'lw_cookie_is_category_allowed' )->once()->andReturn( true );

		$video = VideoParser::parse( 'https://vimeo.com/76979871' );
		$html  = EmbedRenderer::render( $video );

		$this->assertSame( $html, Integration::filter_video_html( $html, $video ) );
	}

	/**
	 * @runInSeparateProcess
	 * @preserveGlobalState disabled
	 */
	public function test_player_is_untouched_when_content_blocking_is_off(): void {
		self::boot_lw_cookie( '1.7.5', [ 'content_blocking' => false ] );

		$video = VideoParser::parse( 'https://vimeo.com/76979871' );
		$html  = EmbedRenderer::render( $video );

		$this->assertFalse( Integration::is_blocking_active() );
		$this->assertSame( $html, Integration::filter_video_html( $html, $video ) );
	}

	/**
	 * @runInSeparateProcess
	 * @preserveGlobalState disabled
	 */
	public function test_player_is_untouched_when_the_banner_is_disabled(): void {
		self::boot_lw_cookie( '1.7.5', [ 'enabled' => false ] );

		$this->assertFalse( Integration::is_blocking_active() );
	}

	/**
	 * @runInSeparateProcess
	 * @preserveGlobalState disabled
	 */
	public function test_lw_cookie_without_in_place_restore_is_left_alone(): void {
		self::boot_lw_cookie( '1.7.0' );

		$this->assertFalse( Integration::is_blocking_active() );
	}

	/**
	 * @runInSeparateProcess
	 * @preserveGlobalState disabled
	 */
	public function test_consent_button_script_is_enqueued_while_blocking(): void {
		self::boot_lw_cookie();
		define( 'LW_LMS_URL', 'https://example.com/wp-content/plugins/lw-lms/' );
		define( 'LW_LMS_VERSION', '9.9.9' );

		$enqueued = self::capture_enqueues();

		Integration::enqueue_script();

		$this->assertSame(
			[ [ 'lw-lms-video-consent', 'https://example.com/wp-content/plugins/lw-lms/assets/js/video-consent.js', [], '9.9.9', true ] ],
			$enqueued->getArrayCopy()
		);
	}

	/**
	 * Record every wp_enqueue_script() call.
	 *
	 * @return \ArrayObject<int, array<int, mixed>>
	 */
	private static function capture_enqueues(): \ArrayObject {
		$calls = new \ArrayObject();
		Functions\when( 'wp_enqueue_script' )->alias(
			static function ( ...$args ) use ( $calls ): void {
				$calls[] = $args;
			}
		);

		return $calls;
	}

	/**
	 * Load the LW Cookie stand-in with the given version and options.
	 *
	 * @param string               $version LW Cookie version.
	 * @param array<string, mixed> $options Option overrides.
	 * @return void
	 */
	private static function boot_lw_cookie( string $version = '1.7.5', array $options = [] ): void {
		require_once dirname( __DIR__, 2 ) . '/Fixtures/LwCookie.php';

		define( 'LW_COOKIE_VERSION', $version );
		\LightweightPlugins\Cookie\Options::$values = array_merge( \LightweightPlugins\Cookie\Options::$values, $options );
	}
}
