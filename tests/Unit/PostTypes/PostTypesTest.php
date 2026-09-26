<?php
/**
 * Tests for the post type registrations.
 *
 * @package LightweightPlugins\LMS
 */

declare(strict_types=1);

namespace LightweightPlugins\LMS\Tests\Unit\PostTypes;

use Brain\Monkey\Functions;
use LightweightPlugins\LMS\PostTypes\Course;
use LightweightPlugins\LMS\PostTypes\Lesson;
use LightweightPlugins\LMS\Tests\Unit\MonkeyTestCase;

/**
 * Both types are headless (not publicly queryable): the rewrite slug and
 * query var they declared only produced unused rewrite rules, so both are
 * turned off. They keep the post capabilities.
 *
 * @covers \LightweightPlugins\LMS\PostTypes\Course
 * @covers \LightweightPlugins\LMS\PostTypes\Lesson
 */
final class PostTypesTest extends MonkeyTestCase {

	/**
	 * @dataProvider provide_types
	 */
	public function test_type_is_headless_without_rewrite_rules_or_query_var( callable $register, string $type ): void {
		Functions\stubTranslationFunctions();
		$args = [];
		Functions\when( 'register_post_type' )->alias(
			static function ( string $name, array $given ) use ( &$args, $type ): void {
				if ( $name === $type ) {
					$args = $given;
				}
			}
		);

		$register();

		$this->assertFalse( $args['public'] );
		$this->assertFalse( $args['publicly_queryable'] );
		$this->assertTrue( $args['show_in_rest'] );
		$this->assertSame( 'post', $args['capability_type'] );
		$this->assertFalse( $args['rewrite'] );
		$this->assertFalse( $args['query_var'] );
	}

	public static function provide_types(): array {
		return [
			'course' => [ [ Course::class, 'register' ], 'course' ],
			'lesson' => [ [ Lesson::class, 'register' ], 'lesson' ],
		];
	}
}
