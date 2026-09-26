<?php
/**
 * Tests for the progress abilities' permission and validation.
 *
 * @package LightweightPlugins\LMS
 */

declare(strict_types=1);

namespace LightweightPlugins\LMS\Tests\Unit\SiteManager;

use Brain\Monkey\Functions;
use LightweightPlugins\LMS\Options;
use LightweightPlugins\LMS\SiteManager\Abilities\AbilityPermissions;
use LightweightPlugins\LMS\SiteManager\Service\ProgressService;
use LightweightPlugins\LMS\Tests\Unit\MonkeyTestCase;

/**
 * Regression: get-progress / set-progress only needed edit_posts, so any
 * Contributor could read or complete any learner's lessons.
 *
 * @covers \LightweightPlugins\LMS\SiteManager\Abilities\AbilityPermissions
 * @covers \LightweightPlugins\LMS\SiteManager\Service\ProgressService
 */
final class ProgressAbilitiesSecurityTest extends MonkeyTestCase {

	protected function setUp(): void {
		parent::setUp();
		Options::clear_cache();
		Functions\stubTranslationFunctions();
	}

	protected function tearDown(): void {
		Options::clear_cache();
		parent::tearDown();
	}

	public function test_manage_lms_key_rejects_edit_posts_only_users(): void {
		Functions\when( 'current_user_can' )->alias( static fn ( string $cap ): bool => 'edit_posts' === $cap );

		$callback = ( new AbilityPermissions() )->callback( AbilityPermissions::MANAGE_LMS );

		$this->assertFalse( $callback() );
	}

	public function test_manage_lms_key_accepts_manage_lms_or_manage_options(): void {
		$callback = ( new AbilityPermissions() )->callback( AbilityPermissions::MANAGE_LMS );

		Functions\when( 'current_user_can' )->alias( static fn ( string $cap ): bool => 'manage_lms' === $cap );
		$this->assertTrue( $callback() );

		Functions\when( 'current_user_can' )->alias( static fn ( string $cap ): bool => 'manage_options' === $cap );
		$this->assertTrue( $callback() );
	}

	public function test_manage_lms_key_is_never_delegated_to_site_manager(): void {
		$manager = new class() {
			public function callback( string $key ): callable {
				return static fn (): bool => true;
			}
		};
		Functions\when( 'current_user_can' )->justReturn( false );

		$callback = ( new AbilityPermissions( $manager ) )->callback( AbilityPermissions::MANAGE_LMS );

		$this->assertFalse( $callback() );
	}

	public function test_set_progress_rejects_a_lesson_from_another_course(): void {
		Functions\when( 'sanitize_key' )->returnArg();
		Functions\when( 'get_userdata' )->justReturn( (object) [ 'ID' => 7 ] );
		Functions\when( 'get_post' )->alias(
			static fn ( int $id ): \WP_Post => new \WP_Post(
				[
					'ID'        => $id,
					'post_type' => 10 === $id ? 'lesson' : 'course',
				]
			)
		);
		Functions\when( 'get_post_meta' )->justReturn( 99 );

		$result = ProgressService::set_progress(
			[
				'user_id'   => 7,
				'course_id' => 42,
				'lesson_id' => 10,
				'status'    => 'completed',
			]
		);

		$this->assertInstanceOf( \WP_Error::class, $result );
		$this->assertSame( 'lesson_not_in_course', $result->get_error_code() );
	}

	public function test_set_progress_rejects_a_non_course_course_id(): void {
		Functions\when( 'sanitize_key' )->returnArg();
		Functions\when( 'get_userdata' )->justReturn( (object) [ 'ID' => 7 ] );
		Functions\when( 'get_post' )->alias(
			static fn ( int $id ): \WP_Post => new \WP_Post(
				[
					'ID'        => $id,
					'post_type' => 10 === $id ? 'lesson' : 'page',
				]
			)
		);

		$result = ProgressService::set_progress(
			[
				'user_id'   => 7,
				'course_id' => 42,
				'lesson_id' => 10,
				'status'    => 'completed',
			]
		);

		$this->assertSame( 'course_not_found', $result->get_error_code() );
	}
}
