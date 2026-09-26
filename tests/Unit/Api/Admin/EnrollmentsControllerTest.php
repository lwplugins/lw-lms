<?php
/**
 * Tests for the enrollments REST controller.
 *
 * @package LightweightPlugins\LMS
 */

declare(strict_types=1);

namespace LightweightPlugins\LMS\Tests\Unit\Api\Admin;

use Brain\Monkey\Functions;
use LightweightPlugins\LMS\Api\Admin\EnrollmentsController;
use WP_Error;
use WP_REST_Request;

/**
 * @covers \LightweightPlugins\LMS\Api\Admin\EnrollmentsController
 */
final class EnrollmentsControllerTest extends AdminRestTestCase {

	protected function setUp(): void {
		parent::setUp();
		Functions\when( 'current_time' )->justReturn( '2026-09-26 10:00:00' );
		Functions\when( 'wp_date' )->justReturn( '2026-09-26' );
	}

	public function test_an_invalid_filter_is_reported_instead_of_ignored(): void {
		$result = ( new EnrollmentsController() )->list_enrollments( new WP_REST_Request( [ 'status' => 'pending' ] ) );

		$this->assertInvalid( $result, [ 'status' ] );
	}

	public function test_an_invalid_grant_grants_nothing(): void {
		Functions\when( 'get_userdata' )->justReturn( false );
		Functions\when( 'get_post' )->justReturn( null );
		Functions\expect( 'apply_filters' )->never();

		$result = ( new EnrollmentsController() )->grant( new WP_REST_Request( [ 'user_id' => 1, 'course_id' => 2 ], 'POST' ) );

		$this->assertInvalid( $result, [ 'user_id', 'course_id' ] );
	}

	public function test_a_too_large_grant_body_is_refused(): void {
		$request = new WP_REST_Request( [], 'POST', '', str_repeat( ' ', EnrollmentsController::MAX_BYTES + 1 ) );

		$result = ( new EnrollmentsController() )->grant( $request );

		$this->assertInstanceOf( WP_Error::class, $result );
		$this->assertSame( 413, $result->get_error_data()['status'] );
	}

	public function test_revoking_again_is_a_harmless_no_op(): void {
		$GLOBALS['wpdb'] = new class() {
			public string $prefix = 'wp_';
			public function prepare( string $query, mixed ...$args ): string {
				return $query;
			}
			public function get_results(): array {
				return [];
			}
		};

		$response = ( new EnrollmentsController() )->revoke( new WP_REST_Request( [ 'user' => '7', 'course' => '40' ], 'DELETE' ) );

		$this->assertSame( 200, $response->status );
		$this->assertSame(
			[
				'revoked'  => false,
				'userId'   => 7,
				'courseId' => 40,
			],
			$response->get_data()
		);
	}
}
