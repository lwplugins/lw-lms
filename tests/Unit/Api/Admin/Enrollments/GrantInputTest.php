<?php
/**
 * Tests for manual grant validation.
 *
 * @package LightweightPlugins\LMS
 */

declare(strict_types=1);

namespace LightweightPlugins\LMS\Tests\Unit\Api\Admin\Enrollments;

use Brain\Monkey\Functions;
use LightweightPlugins\LMS\Api\Admin\Enrollments\GrantInput;
use LightweightPlugins\LMS\Tests\Unit\MonkeyTestCase;
use WP_Post;

/**
 * @covers \LightweightPlugins\LMS\Api\Admin\Enrollments\GrantInput
 */
final class GrantInputTest extends MonkeyTestCase {

	private const TODAY = '2026-09-26';

	protected function setUp(): void {
		parent::setUp();
		Functions\stubTranslationFunctions();
		Functions\when( 'get_userdata' )->alias( static fn ( int $id ) => 7 === $id ? (object) [ 'ID' => 7 ] : false );
		Functions\when( 'get_post' )->alias(
			static function ( int $id ) {
				$posts = [
					40 => new WP_Post( [ 'ID' => 40, 'post_type' => 'course' ] ),
					41 => new WP_Post( [ 'ID' => 41, 'post_type' => 'lesson' ] ),
					42 => new WP_Post( [ 'ID' => 42, 'post_type' => 'course', 'post_status' => 'trash' ] ),
				];
				return $posts[ $id ] ?? null;
			}
		);
		Functions\when( 'get_gmt_from_date' )->alias( static fn ( string $date ): string => $date . ' (utc)' );
	}

	public function test_a_valid_lifetime_grant(): void {
		$input = new GrantInput(
			[
				'user_id'   => 7,
				'course_id' => '40',
				'expires'   => null,
			],
			self::TODAY
		);

		$this->assertSame( [], $input->errors );
		$this->assertSame( 7, $input->user_id );
		$this->assertSame( 40, $input->course_id );
		$this->assertNull( $input->expires_at );
	}

	public function test_an_end_date_ends_at_the_end_of_that_day(): void {
		$input = new GrantInput(
			[
				'user_id'   => 7,
				'course_id' => 40,
				'expires'   => self::TODAY,
			],
			self::TODAY
		);

		$this->assertSame( '2026-09-26 23:59:59 (utc)', $input->expires_at );
	}

	/**
	 * @dataProvider provide_invalid
	 */
	public function test_rejects( array $body, array $fields ): void {
		$input = new GrantInput( $body, self::TODAY );

		$this->assertSame( $fields, array_keys( $input->errors ) );
	}

	public static function provide_invalid(): array {
		$ok = [
			'user_id'   => 7,
			'course_id' => 40,
		];

		return [
			'nothing'           => [ [], [ 'user_id', 'course_id' ] ],
			'unknown user'      => [ array_merge( $ok, [ 'user_id' => 8 ] ), [ 'user_id' ] ],
			'lesson not course' => [ array_merge( $ok, [ 'course_id' => 41 ] ), [ 'course_id' ] ],
			'trashed course'    => [ array_merge( $ok, [ 'course_id' => 42 ] ), [ 'course_id' ] ],
			'past date'         => [ array_merge( $ok, [ 'expires' => '2026-09-25' ] ), [ 'expires' ] ],
			'not a date'        => [ array_merge( $ok, [ 'expires' => 'next week' ] ), [ 'expires' ] ],
			'user id as float'  => [ array_merge( $ok, [ 'user_id' => 7.5 ] ), [ 'user_id' ] ],
		];
	}
}
