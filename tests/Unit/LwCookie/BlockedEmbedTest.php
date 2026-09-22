<?php
/**
 * Tests for the pre-blocked (LW Cookie) lesson video markup.
 *
 * @package LightweightPlugins\LMS
 */

declare(strict_types=1);

namespace LightweightPlugins\LMS\Tests\Unit\LwCookie;

use Brain\Monkey\Functions;
use LightweightPlugins\LMS\LwCookie\BlockedEmbed;
use LightweightPlugins\LMS\Meta\VideoParser;
use LightweightPlugins\LMS\Tests\Unit\MonkeyTestCase;

/**
 * The markup must match what LW Cookie's guard.js restores in place
 * (restoreAllowed): an iframe[data-lw-blocked="1"] with data-lw-category and
 * data-lw-original-src, whose previousSibling is the .lw-cookie-embed-block
 * placeholder.
 *
 * @covers \LightweightPlugins\LMS\LwCookie\BlockedEmbed
 */
final class BlockedEmbedTest extends MonkeyTestCase {

	protected function setUp(): void {
		parent::setUp();
		Functions\stubEscapeFunctions();
		Functions\stubTranslationFunctions();
	}

	public function test_iframe_has_no_src_so_nothing_loads_before_consent(): void {
		$iframe = $this->iframe( $this->dom() );

		$this->assertFalse( $iframe->hasAttribute( 'src' ) );
	}

	public function test_iframe_carries_lw_cookie_blocked_attributes(): void {
		$iframe = $this->iframe( $this->dom() );

		$this->assertSame( '1', $iframe->getAttribute( 'data-lw-blocked' ) );
		$this->assertSame( 'marketing', $iframe->getAttribute( 'data-lw-category' ) );
		$this->assertSame( 'https://player.vimeo.com/video/76979871', $iframe->getAttribute( 'data-lw-original-src' ) );
		$this->assertStringContainsString( 'display:none', $iframe->getAttribute( 'style' ) );
	}

	public function test_placeholder_is_the_iframes_previous_sibling(): void {
		$sibling = $this->iframe( $this->dom() )->previousSibling;

		$this->assertInstanceOf( \DOMElement::class, $sibling );
		$this->assertContains( 'lw-cookie-embed-block', explode( ' ', $sibling->getAttribute( 'class' ) ) );
	}

	public function test_placeholder_explains_the_block_and_offers_a_consent_button(): void {
		$dom    = $this->dom();
		$msg    = $dom->getElementsByTagName( 'p' )->item( 0 );
		$button = $dom->getElementsByTagName( 'button' )->item( 0 );

		$this->assertNotNull( $msg );
		$this->assertSame( 'lw-cookie-embed-block__msg', $msg->getAttribute( 'class' ) );
		$this->assertSame( 'To watch this video, accept the required cookies.', $msg->textContent );

		$this->assertNotNull( $button );
		$this->assertSame( 'button', $button->getAttribute( 'type' ) );
		$this->assertSame( 'lw-cookie-embed-block__btn', $button->getAttribute( 'class' ) );
		$this->assertSame( 'marketing', $button->getAttribute( 'data-lw-lms-consent' ) );
		$this->assertSame( 'Accept & play video', $button->textContent );
	}

	public function test_player_box_holds_only_the_placeholder_and_the_iframe(): void {
		$box = $this->dom()->getElementsByTagName( 'div' )->item( 0 );

		$this->assertNotNull( $box );
		$this->assertSame( 'lw-lms-video lw-lms-video--blocked', $box->getAttribute( 'class' ) );
		$this->assertSame( 2, $box->childNodes->length );
	}

	public function test_category_is_escaped(): void {
		$html = BlockedEmbed::render( VideoParser::parse( 'https://vimeo.com/1' ), 'x" onclick="alert(1)' );

		$this->assertStringNotContainsString( '" onclick="', $html );
	}

	private function dom(): \DOMDocument {
		$html = BlockedEmbed::render( VideoParser::parse( 'https://vimeo.com/76979871' ), 'marketing' );

		$dom      = new \DOMDocument();
		$previous = libxml_use_internal_errors( true );
		$dom->loadHTML( '<?xml encoding="utf-8"?><body>' . $html . '</body>', LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD );
		libxml_clear_errors();
		libxml_use_internal_errors( $previous );

		return $dom;
	}

	private function iframe( \DOMDocument $dom ): \DOMElement {
		$iframe = $dom->getElementsByTagName( 'iframe' )->item( 0 );
		$this->assertInstanceOf( \DOMElement::class, $iframe );

		return $iframe;
	}
}
