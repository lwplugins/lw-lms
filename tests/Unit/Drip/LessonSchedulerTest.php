<?php
/**
 * Tests for the lesson lock schedule.
 *
 * @package LightweightPlugins\LMS
 */

declare(strict_types=1);

namespace LightweightPlugins\LMS\Tests\Unit\Drip;

use DateTimeZone;
use LightweightPlugins\LMS\Drip\DripClock;
use LightweightPlugins\LMS\Drip\DripRule;
use LightweightPlugins\LMS\Drip\LessonScheduler;
use PHPUnit\Framework\TestCase;

/**
 * @covers \LightweightPlugins\LMS\Drip\LessonScheduler
 */
final class LessonSchedulerTest extends TestCase {

	private const START = 1767225600; // 2026-01-01 00:00:00 UTC.
	private const DAY   = 86400;

	public function test_open_lessons_are_absent_from_the_result(): void {
		$locks = $this->locks( [ 'plan' => $this->plan( [ 1, 2 ] ) ] );

		$this->assertArrayNotHasKey( 1, $locks );
	}

	public function test_later_lesson_is_locked_until_the_previous_one_is_completed(): void {
		$locks = $this->locks( [ 'plan' => $this->plan( [ 1, 2 ] ) ] );

		$this->assertSame(
			[
				'reason'       => 'sequence',
				'available_at' => null,
			],
			$locks[2]
		);
	}

	public function test_lesson_opens_once_the_previous_one_is_completed(): void {
		$locks = $this->locks(
			[
				'plan'      => $this->plan( [ 1, 2 ] ),
				'completed' => [ 1 => self::START + self::DAY ],
			]
		);

		$this->assertSame( [], $locks );
	}

	public function test_completed_lesson_is_never_locked(): void {
		$plan = $this->plan( [ 1, 2 ] );
		$plan['lesson_rules'][2] = $this->rule( DripRule::MODE_ENROLLMENT, 30, 'day' );

		$locks = $this->locks(
			[
				'plan'      => $plan,
				'completed' => [
					1 => self::START,
					2 => self::START,
				],
			]
		);

		$this->assertSame( [], $locks );
	}

	public function test_preview_lesson_is_never_locked(): void {
		$plan            = $this->plan( [ 1, 2 ] );
		$plan['exempt']  = [ 2 ];

		$locks = $this->locks( [ 'plan' => $plan ] );

		$this->assertSame( [], $locks );
	}

	public function test_enrollment_rule_locks_the_lesson_until_the_delay_passes(): void {
		$plan                    = $this->plan( [ 1 ] );
		$plan['lesson_rules'][1] = $this->rule( DripRule::MODE_ENROLLMENT, 2, 'day' );

		$locks = $this->locks( [ 'plan' => $plan ] );

		$this->assertSame(
			[
				'reason'       => 'schedule',
				'available_at' => self::START + ( 2 * self::DAY ),
			],
			$locks[1]
		);
	}

	public function test_scheduled_lesson_opens_when_its_moment_arrives(): void {
		$plan                    = $this->plan( [ 1 ] );
		$plan['lesson_rules'][1] = $this->rule( DripRule::MODE_ENROLLMENT, 2, 'day' );

		$locks = $this->locks(
			[
				'plan' => $plan,
				'now'  => self::START + ( 2 * self::DAY ),
			]
		);

		$this->assertSame( [], $locks );
	}

	public function test_previous_rule_counts_from_the_completion_of_the_previous_lesson(): void {
		$plan                    = $this->plan( [ 1, 2 ] );
		$plan['lesson_rules'][2] = $this->rule( DripRule::MODE_PREVIOUS, 3, 'day' );
		$finished_first          = self::START + ( 5 * self::DAY );

		$locks = $this->locks(
			[
				'plan'      => $plan,
				'completed' => [ 1 => $finished_first ],
				'now'       => $finished_first,
			]
		);

		$this->assertSame(
			[
				'reason'       => 'schedule',
				'available_at' => $finished_first + ( 3 * self::DAY ),
			],
			$locks[2]
		);
	}

	public function test_previous_rule_on_the_first_lesson_counts_from_enrollment(): void {
		$plan                    = $this->plan( [ 1 ] );
		$plan['lesson_rules'][1] = $this->rule( DripRule::MODE_PREVIOUS, 1, 'day' );

		$locks = $this->locks( [ 'plan' => $plan ] );

		$this->assertSame( self::START + self::DAY, $locks[1]['available_at'] );
	}

	public function test_sequence_lock_wins_over_a_known_unlock_time(): void {
		// The lesson has a date-based rule that already passed, but the
		// learner has not finished the lesson before it.
		$plan                    = $this->plan( [ 1, 2 ] );
		$plan['lesson_rules'][2] = $this->rule( DripRule::MODE_ENROLLMENT, 1, 'day' );

		$locks = $this->locks(
			[
				'plan' => $plan,
				'now'  => self::START + ( 10 * self::DAY ),
			]
		);

		$this->assertSame(
			[
				'reason'       => 'sequence',
				'available_at' => null,
			],
			$locks[2]
		);
	}

	public function test_course_delay_locks_every_lesson(): void {
		$plan                  = $this->plan( [ 1, 2 ] );
		$plan['course_delay']  = $this->rule( DripRule::MODE_ENROLLMENT, 1, 'week' );

		$locks = $this->locks( [ 'plan' => $plan ] );

		$this->assertSame( self::START + ( 7 * self::DAY ), $locks[1]['available_at'] );
		$this->assertSame( 'sequence', $locks[2]['reason'] );
	}

	public function test_the_latest_constraint_decides_the_unlock_time(): void {
		$plan                    = $this->plan( [ 1 ] );
		$plan['course_delay']    = $this->rule( DripRule::MODE_ENROLLMENT, 10, 'day' );
		$plan['lesson_rules'][1] = $this->rule( DripRule::MODE_ENROLLMENT, 2, 'day' );

		$locks = $this->locks( [ 'plan' => $plan ] );

		$this->assertSame( self::START + ( 10 * self::DAY ), $locks[1]['available_at'] );
	}

	public function test_section_rule_applies_to_every_lesson_of_the_section(): void {
		$plan = $this->plan(
			[ 1, 2 ],
			[
				1 => 'sec_a',
				2 => 'sec_a',
			],
			[ 'sec_a' => $this->rule( DripRule::MODE_ENROLLMENT, 4, 'day' ) ]
		);

		$locks = $this->locks(
			[
				'plan'      => $plan,
				'completed' => [ 1 => self::START ],
			]
		);

		$this->assertSame( self::START + ( 4 * self::DAY ), $locks[2]['available_at'] );
	}

	public function test_section_previous_rule_waits_for_the_whole_previous_section(): void {
		$plan = $this->plan(
			[ 1, 2, 3 ],
			[
				1 => 'sec_a',
				2 => 'sec_a',
				3 => 'sec_b',
			],
			[
				'sec_a' => DripRule::none(),
				'sec_b' => $this->rule( DripRule::MODE_PREVIOUS, 1, 'day' ),
			]
		);
		$first_done  = self::START + self::DAY;
		$second_done = self::START + ( 3 * self::DAY );

		$locks = $this->locks(
			[
				'plan'      => $plan,
				'completed' => [
					1 => $first_done,
					2 => $second_done,
				],
				'now'       => $second_done,
			]
		);

		$this->assertSame( $second_done + self::DAY, $locks[3]['available_at'] );
	}

	public function test_section_previous_rule_is_a_sequence_lock_while_the_section_runs(): void {
		$plan = $this->plan(
			[ 1, 2, 3 ],
			[
				1 => 'sec_a',
				2 => 'sec_a',
				3 => 'sec_b',
			],
			[
				'sec_a' => DripRule::none(),
				'sec_b' => $this->rule( DripRule::MODE_PREVIOUS, 1, 'day' ),
			]
		);

		$locks = $this->locks(
			[
				'plan'      => $plan,
				'completed' => [ 1 => self::START ],
			]
		);

		$this->assertSame(
			[
				'reason'       => 'sequence',
				'available_at' => null,
			],
			$locks[3]
		);
	}

	public function test_first_section_previous_rule_counts_from_enrollment(): void {
		$plan = $this->plan(
			[ 1 ],
			[ 1 => 'sec_a' ],
			[ 'sec_a' => $this->rule( DripRule::MODE_PREVIOUS, 2, 'day' ) ]
		);

		$locks = $this->locks( [ 'plan' => $plan ] );

		$this->assertSame( self::START + ( 2 * self::DAY ), $locks[1]['available_at'] );
	}

	public function test_previous_section_without_lessons_counts_from_enrollment(): void {
		$plan = $this->plan(
			[ 1 ],
			[ 1 => 'sec_b' ],
			[
				'sec_a' => DripRule::none(),
				'sec_b' => $this->rule( DripRule::MODE_PREVIOUS, 2, 'day' ),
			]
		);

		$locks = $this->locks( [ 'plan' => $plan ] );

		$this->assertSame( self::START + ( 2 * self::DAY ), $locks[1]['available_at'] );
	}

	/**
	 * Run the scheduler over a plan.
	 *
	 * @param array<string, mixed> $case Keys: plan, completed, now.
	 * @return array<int, array{reason: string, available_at: int|null}>
	 */
	private function locks( array $case ): array {
		$scheduler = new LessonScheduler( new DripClock( new DateTimeZone( 'UTC' ) ) );

		return $scheduler->locks(
			$case['plan'],
			self::START,
			$case['completed'] ?? [],
			$case['now'] ?? self::START
		);
	}

	/**
	 * Build a plan: lesson ids in outline order, optional section per lesson,
	 * optional section rules.
	 *
	 * @param array<int, int>                             $lesson_ids    Lesson ids in order.
	 * @param array<int, string>                          $sections_of   Lesson id => section id.
	 * @param array<string, array<string, mixed>>         $section_rules Section id => rule.
	 * @return array<string, mixed>
	 */
	private function plan( array $lesson_ids, array $sections_of = [], array $section_rules = [] ): array {
		$sequence = [];

		foreach ( $lesson_ids as $lesson_id ) {
			$sequence[] = [
				'id'      => $lesson_id,
				'section' => $sections_of[ $lesson_id ] ?? '',
			];
		}

		$sections = [];

		foreach ( $section_rules as $section_id => $rule ) {
			$sections[] = [
				'id'   => $section_id,
				'rule' => $rule,
			];
		}

		return [
			'sequence'     => $sequence,
			'sections'     => $sections,
			'lesson_rules' => [],
			'course_delay' => DripRule::none(),
			'exempt'       => [],
		];
	}

	/**
	 * @return array{mode: string, value: int, unit: string}
	 */
	private function rule( string $mode, int $value, string $unit ): array {
		return [
			'mode'  => $mode,
			'value' => $value,
			'unit'  => $unit,
		];
	}
}
