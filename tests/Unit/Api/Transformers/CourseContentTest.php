<?php
/**
 * Tests for the course description fields.
 *
 * @package LightweightPlugins\LMS
 */

declare(strict_types=1);

namespace LightweightPlugins\LMS\Tests\Unit\Api\Transformers;

use Brain\Monkey\Filters;
use Brain\Monkey\Functions;
use LightweightPlugins\LMS\Api\Transformers\CourseContent;
use LightweightPlugins\LMS\Tests\Unit\MonkeyTestCase;

/**
 * Course content honors the post password.
 *
 * @covers \LightweightPlugins\LMS\Api\Transformers\CourseContent
 */
final class CourseContentTest extends MonkeyTestCase {

	protected function setUp(): void {
		parent::setUp();
		Filters\expectApplied( 'the_content' )->zeroOrMoreTimes()->andReturnFirstArg();
		Functions\when( 'post_password_required' )->justReturn( true );
	}

	public function test_public_course_returns_its_content(): void {
		Functions\when( 'current_user_can' )->justReturn( false );

		$this->assertSame( [ 'content' => 'About' ], CourseContent::full( $this->course( '' ) ) );
	}

	public function test_protected_course_hides_its_content_from_visitors(): void {
		Functions\when( 'current_user_can' )->justReturn( false );

		$this->assertSame(
			[
				'content'           => '',
				'content_protected' => true,
			],
			CourseContent::full( $this->course( 'secret' ) )
		);
	}

	public function test_protected_course_is_shown_to_its_editors(): void {
		Functions\when( 'current_user_can' )->justReturn( true );

		$this->assertSame( [ 'content' => 'About' ], CourseContent::full( $this->course( 'secret' ) ) );
	}

	public function test_protected_course_is_shown_after_the_password_is_entered(): void {
		Functions\when( 'current_user_can' )->justReturn( false );
		Functions\when( 'post_password_required' )->justReturn( false );

		$this->assertSame( [ 'content' => 'About' ], CourseContent::full( $this->course( 'secret' ) ) );
	}

	public function test_list_item_flags_only_protected_courses(): void {
		Functions\when( 'current_user_can' )->justReturn( false );

		$this->assertSame( [ 'content_protected' => true ], CourseContent::list_item( $this->course( 'secret' ) ) );
		$this->assertSame( [], CourseContent::list_item( $this->course( '' ) ) );
	}

	private function course( string $password ): \WP_Post {
		return new \WP_Post(
			[
				'ID'            => 42,
				'post_type'     => 'course',
				'post_content'  => 'About',
				'post_password' => $password,
			]
		);
	}
}
