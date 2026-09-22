<?php
/**
 * Tests for the LW Cookie consent decision on lesson videos.
 *
 * @package LightweightPlugins\LMS
 */

declare(strict_types=1);

namespace LightweightPlugins\LMS\Tests\Unit\LwCookie;

use Brain\Monkey\Functions;
use LightweightPlugins\LMS\LwCookie\EmbedGate;
use LightweightPlugins\LMS\Meta\VideoParser;
use LightweightPlugins\LMS\Tests\Unit\MonkeyTestCase;

/**
 * @covers \LightweightPlugins\LMS\LwCookie\EmbedGate
 */
final class EmbedGateTest extends MonkeyTestCase {

	/**
	 * Same shape and order as LW Cookie's Entities::get_domains().
	 */
	private const DOMAINS = [
		'youtube.com'      => 'marketing',
		'vimeo.com'        => 'marketing',
		'player.vimeo.com' => 'functional',
		'google.com/maps'  => 'functional',
		'maps.google.com'  => 'functional',
	];

	private const PLAYER = '<div class="lw-lms-video"><iframe src="https://player.vimeo.com/video/1"></iframe></div>';

	protected function setUp(): void {
		parent::setUp();
		Functions\stubEscapeFunctions();
		Functions\stubTranslationFunctions();
		Functions\when( 'wp_parse_url' )->alias( 'parse_url' );
	}

	/**
	 * @dataProvider provide_urls
	 */
	public function test_resolves_the_category_like_the_lw_cookie_guard( string $url, ?string $expected ): void {
		$this->assertSame( $expected, $this->gate( false )->category_for( $url ) );
	}

	public static function provide_urls(): array {
		return [
			'subdomain of a listed host, first match wins' => [ 'https://player.vimeo.com/video/1', 'marketing' ],
			'www is ignored'                               => [ 'https://www.youtube.com/embed/abc', 'marketing' ],
			'host is case-insensitive'                     => [ 'https://WWW.YouTube.com/embed/abc', 'marketing' ],
			'path-based entry'                             => [ 'https://www.google.com/maps/embed?pb=1', 'functional' ],
			'unlisted host'                                => [ 'https://fast.wistia.net/embed/iframe/x', null ],
			'look-alike host is not a subdomain'           => [ 'https://notvimeo.com/video/1', null ],
			'relative url'                                 => [ '/video.mp4', null ],
			'empty url'                                    => [ '', null ],
		];
	}

	public function test_blocks_the_player_when_the_category_is_not_consented(): void {
		$html = $this->gate( false )->filter( self::PLAYER, VideoParser::parse( 'https://vimeo.com/76979871' ) );

		$this->assertStringContainsString( 'data-lw-blocked="1"', $html );
		$this->assertStringContainsString( 'data-lw-category="marketing"', $html );
		$this->assertStringContainsString( 'lw-cookie-embed-block', $html );
		$this->assertDoesNotMatchRegularExpression( '/<iframe[^>]*\ssrc=/', $html );
	}

	public function test_keeps_the_player_when_the_category_is_consented(): void {
		$html = $this->gate( true )->filter( self::PLAYER, VideoParser::parse( 'https://vimeo.com/76979871' ) );

		$this->assertSame( self::PLAYER, $html );
	}

	public function test_asks_about_the_category_of_the_player_host(): void {
		$asked = [];
		$gate  = new EmbedGate(
			self::DOMAINS,
			static function ( string $category ) use ( &$asked ): bool {
				$asked[] = $category;
				return false;
			}
		);

		$gate->filter( self::PLAYER, VideoParser::parse( 'https://www.google.com/maps/embed?pb=1' ) );
		$this->assertSame( [], $asked, 'Not an iframe video provider: no consent question.' );

		$gate->filter( self::PLAYER, VideoParser::parse( 'https://www.youtube.com/watch?v=abc' ) );
		$this->assertSame( [ 'marketing' ], $asked );
	}

	public function test_keeps_players_on_hosts_lw_cookie_does_not_block(): void {
		$html = $this->gate( false )->filter( self::PLAYER, VideoParser::parse( 'https://fast.wistia.net/embed/iframe/x' ) );

		$this->assertSame( self::PLAYER, $html );
	}

	public function test_never_touches_self_hosted_video(): void {
		$gate  = new EmbedGate( [ 'example.com' => 'marketing' ], static fn(): bool => false );
		$video = '<div class="lw-lms-video"><video src="https://example.com/a.mp4"></video></div>';

		$this->assertSame( $video, $gate->filter( $video, VideoParser::parse( 'https://example.com/a.mp4' ) ) );
	}

	public function test_empty_markup_stays_empty(): void {
		$this->assertSame( '', $this->gate( false )->filter( '', VideoParser::parse( 'https://vimeo.com/1' ) ) );
	}

	private function gate( bool $allowed ): EmbedGate {
		return new EmbedGate( self::DOMAINS, static fn(): bool => $allowed );
	}
}
