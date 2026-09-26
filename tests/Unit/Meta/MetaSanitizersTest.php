<?php
/**
 * Tests for the registered meta sanitizers.
 *
 * @package LightweightPlugins\LMS
 */

declare(strict_types=1);

namespace LightweightPlugins\LMS\Tests\Unit\Meta;

use Brain\Monkey\Functions;
use LightweightPlugins\LMS\Meta\MetaAuth;
use LightweightPlugins\LMS\Meta\MetaSanitizers;
use LightweightPlugins\LMS\Tests\Unit\MonkeyTestCase;

/**
 * Regression: REST-registered meta had no sanitize_callback, so core REST
 * writes (/wp/v2/course, the block editor) skipped the metabox sanitizers:
 * any access_type, raw video embed URLs, unsanitized sections.
 *
 * @covers \LightweightPlugins\LMS\Meta\MetaSanitizers
 * @covers \LightweightPlugins\LMS\Meta\MetaAuth
 */
final class MetaSanitizersTest extends MonkeyTestCase {

	protected function setUp(): void {
		parent::setUp();
		Functions\when( 'sanitize_text_field' )->alias( static fn ( string $v ): string => trim( strip_tags( $v ) ) ); // phpcs:ignore WordPress.WP.AlternativeFunctions.strip_tags_strip_tags
		Functions\when( 'sanitize_textarea_field' )->alias( static fn ( string $v ): string => strip_tags( $v ) ); // phpcs:ignore WordPress.WP.AlternativeFunctions.strip_tags_strip_tags
		Functions\when( 'absint' )->alias( static fn ( mixed $v ): int => abs( (int) $v ) );
		Functions\when( 'esc_url_raw' )->alias(
			static fn ( string $url ): string => preg_match( '#^https?://#i', $url ) ? $url : ''
		);
	}

	public function test_access_type_is_limited_to_the_three_types(): void {
		$this->assertSame( 'paid', MetaSanitizers::access_type( 'paid' ) );
		$this->assertSame( 'open', MetaSanitizers::access_type( 'open' ) );
		$this->assertSame( 'free', MetaSanitizers::access_type( 'vip' ) );
		$this->assertSame( 'free', MetaSanitizers::access_type( [ 'paid' ] ) );
	}

	public function test_id_lists_keep_unique_positive_ints(): void {
		$this->assertSame( [ 3, 5 ], MetaSanitizers::id_list( [ '3', 5, 0, 'x', 3, -0 ] ) );
		$this->assertSame( [], MetaSanitizers::id_list( 'a' ) );
	}

	public function test_durations_map_product_to_days(): void {
		$this->assertSame( [ '12' => 30 ], MetaSanitizers::durations( [ '12' => '30', 'x' => 5, '0' => 3 ] ) );
	}

	public function test_variation_pairs_keep_the_parent_colon_variation_form(): void {
		$this->assertSame( [ '10:11' ], MetaSanitizers::variation_pairs( [ '10:11', '10:x', 5, '10:11' ] ) );
	}

	public function test_attachments_keep_known_fields_only(): void {
		$this->assertSame(
			[
				[
					'id'          => 8,
					'title'       => 'Notes',
					'description' => '',
				],
			],
			MetaSanitizers::attachments(
				[
					[
						'id'    => '8',
						'title' => '<b>Notes</b>',
						'evil'  => 1,
					],
					[ 'id' => 0 ],
				]
			)
		);
	}

	public function test_video_is_rederived_from_its_url(): void {
		$video = MetaSanitizers::video(
			[
				'url'      => 'https://youtu.be/abc',
				'provider' => 'self',
				'embed'    => 'javascript:alert(1)',
			]
		);

		$this->assertSame( 'youtube', $video['provider'] );
		$this->assertSame( 'https://www.youtube.com/embed/abc', $video['embed'] );
		$this->assertSame( $video, MetaSanitizers::video( $video ), 'idempotent' );
	}

	public function test_unsafe_video_url_is_dropped(): void {
		$this->assertSame( '', MetaSanitizers::video( [ 'url' => 'javascript:alert(1)' ] )['embed'] );
	}

	public function test_progression_and_drip_rules_are_normalized(): void {
		$this->assertSame( 'free', MetaSanitizers::progression( 'random' ) );
		$this->assertSame( 'linear', MetaSanitizers::progression( 'linear' ) );
		$this->assertSame( 'none', MetaSanitizers::course_delay( [ 'mode' => 'previous', 'value' => 2 ] )['mode'] );
		$this->assertSame( 999, MetaSanitizers::drip_rule( [ 'mode' => 'enrollment', 'value' => 5000, 'unit' => 'day' ] )['value'] );
	}

	public function test_section_id_keeps_case_and_drops_other_characters(): void {
		$this->assertSame( 'Sec_A-1', MetaSanitizers::section_id( 'Sec_A-1 <>!' ) );
	}

	public function test_meta_writes_need_edit_post_on_that_post(): void {
		Functions\when( 'user_can' )->alias(
			static fn ( int $user, string $cap, int $post ): bool => 3 === $user && 'edit_post' === $cap && 5 === $post
		);

		$this->assertTrue( MetaAuth::can_edit( false, '_lw_lms_access_type', 5, 3 ) );
		$this->assertFalse( MetaAuth::can_edit( false, '_lw_lms_access_type', 6, 3 ) );
		$this->assertFalse( MetaAuth::can_edit( false, '_lw_lms_access_type', 5, 4 ) );
	}
}
