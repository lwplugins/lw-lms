<?php
/**
 * Tests for the quiz save error route.
 *
 * @package LightweightPlugins\LMS
 */

declare(strict_types=1);

namespace LightweightPlugins\LMS\Tests\Unit\Api\Admin;

use Brain\Monkey\Functions;
use LightweightPlugins\LMS\Api\Admin\QuizErrorController;
use LightweightPlugins\LMS\Tests\Unit\MonkeyTestCase;

/**
 * Regression: in the block editor a rejected quiz JSON was dropped silently;
 * the editor script now reads the rejection from this route.
 *
 * @covers \LightweightPlugins\LMS\Api\Admin\QuizErrorController
 * @covers \LightweightPlugins\LMS\Admin\Quiz\QuizSaveError
 */
final class QuizErrorControllerTest extends MonkeyTestCase {

	public function test_returns_the_pending_rejection_message(): void {
		Functions\expect( 'get_transient' )->once()->with( 'lw_lms_quiz_error_12' )->andReturn(
			[
				'json'    => '{',
				'message' => 'Invalid JSON: Syntax error',
			]
		);

		$response = ( new QuizErrorController() )->get_error( new \WP_REST_Request( [ 'id' => 12 ] ) );

		$this->assertSame( [ 'error' => 'Invalid JSON: Syntax error' ], $response->get_data() );
	}

	public function test_returns_null_when_the_last_save_worked(): void {
		Functions\when( 'get_transient' )->justReturn( false );

		$response = ( new QuizErrorController() )->get_error( new \WP_REST_Request( [ 'id' => 12 ] ) );

		$this->assertSame( [ 'error' => null ], $response->get_data() );
	}

	/**
	 * @dataProvider provide_permissions
	 */
	public function test_only_lesson_editors_may_ask( string $post_type, bool $can_edit, bool $expected ): void {
		Functions\when( 'get_post_type' )->justReturn( $post_type );
		Functions\when( 'current_user_can' )->justReturn( $can_edit );

		$this->assertSame( $expected, QuizErrorController::can_edit( new \WP_REST_Request( [ 'id' => 12 ] ) ) );
	}

	public static function provide_permissions(): array {
		return [
			'lesson editor'        => [ 'lesson', true, true ],
			'lesson, cannot edit'  => [ 'lesson', false, false ],
			'not a lesson'         => [ 'post', true, false ],
		];
	}
}
