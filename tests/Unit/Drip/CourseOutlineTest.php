<?php
/**
 * Tests for the course outline order.
 *
 * @package LightweightPlugins\LMS
 */

declare(strict_types=1);

namespace LightweightPlugins\LMS\Tests\Unit\Drip;

use LightweightPlugins\LMS\Drip\CourseOutline;
use PHPUnit\Framework\TestCase;

/**
 * @covers \LightweightPlugins\LMS\Drip\CourseOutline
 */
final class CourseOutlineTest extends TestCase {

	public function test_lessons_without_a_section_come_before_the_sections(): void {
		$sequence = CourseOutline::sequence(
			[
				self::lesson( 10, 'sec_b', 3 ),
				self::lesson( 11, '', 1 ),
				self::lesson( 12, 'sec_a', 2 ),
			],
			[ self::section( 'sec_a', 1 ), self::section( 'sec_b', 2 ) ]
		);

		$this->assertSame( [ 11, 12, 10 ], array_column( $sequence, 'id' ) );
	}

	public function test_sections_follow_their_own_order_not_the_stored_order(): void {
		$sequence = CourseOutline::sequence(
			[
				self::lesson( 20, 'sec_intro', 1 ),
				self::lesson( 21, 'sec_basics', 2 ),
			],
			[ self::section( 'sec_basics', 2 ), self::section( 'sec_intro', 1 ) ]
		);

		$this->assertSame( [ 20, 21 ], array_column( $sequence, 'id' ) );
	}

	public function test_lessons_inside_a_group_are_ordered_by_lesson_order(): void {
		$sequence = CourseOutline::sequence(
			[
				self::lesson( 30, 'sec_a', 9 ),
				self::lesson( 31, 'sec_a', 2 ),
				self::lesson( 32, 'sec_a', 5 ),
			],
			[ self::section( 'sec_a', 1 ) ]
		);

		$this->assertSame( [ 31, 32, 30 ], array_column( $sequence, 'id' ) );
	}

	public function test_equal_order_values_fall_back_to_the_lesson_id(): void {
		$sequence = CourseOutline::sequence(
			[
				self::lesson( 45, '', 0 ),
				self::lesson( 44, '', 0 ),
			],
			[]
		);

		$this->assertSame( [ 44, 45 ], array_column( $sequence, 'id' ) );
	}

	public function test_lesson_in_a_deleted_section_is_left_out_of_the_sequence(): void {
		// Such a lesson is invisible in the course outline, so it must not
		// become a prerequisite nobody can reach.
		$sequence = CourseOutline::sequence(
			[
				self::lesson( 50, '', 1 ),
				self::lesson( 51, 'sec_gone', 2 ),
			],
			[]
		);

		$this->assertSame( [ 50 ], array_column( $sequence, 'id' ) );
	}

	public function test_sequence_keeps_the_section_of_each_lesson(): void {
		$sequence = CourseOutline::sequence(
			[ self::lesson( 60, 'sec_a', 1 ) ],
			[ self::section( 'sec_a', 1 ) ]
		);

		$this->assertSame(
			[
				[
					'id'      => 60,
					'section' => 'sec_a',
				],
			],
			$sequence
		);
	}

	public function test_course_without_lessons_yields_an_empty_sequence(): void {
		$this->assertSame( [], CourseOutline::sequence( [], [ self::section( 'sec_a', 1 ) ] ) );
	}

	/**
	 * @return array{id: int, section: string, order: int}
	 */
	private static function lesson( int $id, string $section, int $order ): array {
		return [
			'id'      => $id,
			'section' => $section,
			'order'   => $order,
		];
	}

	/**
	 * @return array{id: string, order: int}
	 */
	private static function section( string $id, int $order ): array {
		return [
			'id'    => $id,
			'order' => $order,
		];
	}
}
