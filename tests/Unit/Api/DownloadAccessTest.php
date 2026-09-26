<?php
/**
 * Tests for the download access decision.
 *
 * @package LightweightPlugins\LMS
 */

declare(strict_types=1);

namespace LightweightPlugins\LMS\Tests\Unit\Api;

use Brain\Monkey\Functions;
use LightweightPlugins\LMS\Api\DownloadAccess;
use LightweightPlugins\LMS\Options;
use LightweightPlugins\LMS\Tests\Unit\MonkeyTestCase;

/**
 * Regression: GET /lms/v1/download/{id} streamed every attachment that no
 * course or lesson listed (other plugins' private files included) to anyone.
 *
 * @covers \LightweightPlugins\LMS\Api\DownloadAccess
 */
final class DownloadAccessTest extends MonkeyTestCase {

	protected function setUp(): void {
		parent::setUp();
		Options::clear_cache();
		Functions\stubTranslationFunctions();
		Functions\when( 'get_option' )->justReturn( [] );
		Functions\when( 'wp_parse_args' )->alias(
			static fn ( mixed $args, array $defaults = [] ): array => array_merge( $defaults, (array) $args )
		);
		Functions\when( 'apply_filters' )->returnArg( 2 );
		Functions\when( 'user_can' )->justReturn( false );
	}

	protected function tearDown(): void {
		Options::clear_cache();
		parent::tearDown();
	}

	/**
	 * @param array<int, array<string, mixed>> $posts Post fields by ID.
	 * @param array<int, array<string, mixed>> $meta  Meta by post ID (unprefixed keys).
	 */
	private function site( array $posts, array $meta ): void {
		Functions\when( 'get_post' )->alias(
			static fn ( int $id ): ?\WP_Post => isset( $posts[ $id ] ) ? new \WP_Post( [ 'ID' => $id ] + $posts[ $id ] ) : null
		);
		Functions\when( 'get_post_meta' )->alias(
			static fn ( int $id, string $key ): mixed => $meta[ $id ][ substr( $key, strlen( Options::META_PREFIX ) ) ] ?? ''
		);
	}

	public function test_file_owned_by_nothing_is_not_found(): void {
		$error = DownloadAccess::check( [], 0 );

		$this->assertInstanceOf( \WP_Error::class, $error );
		$this->assertSame( 404, $error->get_error_data()['status'] );
	}

	public function test_file_of_an_open_course_is_served_to_guests(): void {
		$this->site( [ 5 => [ 'post_type' => 'course' ] ], [ 5 => [ 'access_type' => 'open' ] ] );

		$this->assertNull( DownloadAccess::check( [ 5 ], 0 ) );
	}

	public function test_file_of_a_draft_course_is_not_found(): void {
		$this->site(
			[
				5 => [
					'post_type'   => 'course',
					'post_status' => 'draft',
				],
			],
			[ 5 => [ 'access_type' => 'open' ] ]
		);

		$this->assertSame( 404, DownloadAccess::check( [ 5 ], 7 )->get_error_data()['status'] );
	}

	public function test_file_of_a_lesson_with_a_deleted_course_is_not_found(): void {
		$this->site( [ 9 => [ 'post_type' => 'lesson' ] ], [ 9 => [ 'lesson_course_id' => 42 ] ] );

		$this->assertSame( 404, DownloadAccess::check( [ 9 ], 7 )->get_error_data()['status'] );
	}

	public function test_paid_course_file_asks_guests_to_log_in(): void {
		$this->site( [ 5 => [ 'post_type' => 'course' ] ], [ 5 => [ 'access_type' => 'paid' ] ] );

		$error = DownloadAccess::check( [ 5 ], 0 );

		$this->assertSame( 'unauthorized', $error->get_error_code() );
	}

	public function test_any_accessible_owner_allows_regardless_of_order(): void {
		// Course 3 is paid (guest denied), course 5 is open: the file is served.
		$this->site(
			[
				3 => [ 'post_type' => 'course' ],
				5 => [ 'post_type' => 'course' ],
			],
			[
				3 => [ 'access_type' => 'paid' ],
				5 => [ 'access_type' => 'open' ],
			]
		);

		$this->assertNull( DownloadAccess::check( [ 3, 5 ], 0 ) );
	}
}
