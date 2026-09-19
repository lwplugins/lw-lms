<?php
/**
 * Lesson Scheduler.
 *
 * @package LightweightPlugins\LMS
 */

declare(strict_types=1);

namespace LightweightPlugins\LMS\Drip;

/**
 * Works out which lessons of a course are still closed for one learner, and
 * why: either because the lesson before is not finished yet ("sequence"), or
 * because its moment has not arrived ("schedule").
 *
 * Pure: everything it needs — the outline, the rules, what the learner has
 * completed and when, and the current time — is handed to it.
 */
final class LessonScheduler {

	/**
	 * Locked because the learner has not finished what comes before.
	 */
	public const REASON_SEQUENCE = 'sequence';

	/**
	 * Locked until a moment in time.
	 */
	public const REASON_SCHEDULE = 'schedule';

	/**
	 * Clock used for the delays.
	 *
	 * @var DripClock
	 */
	private DripClock $clock;

	/**
	 * Constructor.
	 *
	 * @param DripClock $clock Clock used for the delays.
	 */
	public function __construct( DripClock $clock ) {
		$this->clock = $clock;
	}

	/**
	 * Locked lessons of a course for one learner.
	 *
	 * @param array{sequence: array<int, array{id: int, section: string}>, sections: array<int, array{id: string, rule: array{mode: string, value: int, unit: string}}>, lesson_rules: array<int, array{mode: string, value: int, unit: string}>, course_delay: array{mode: string, value: int, unit: string}, exempt: array<int, int>} $plan  Course plan.
	 * @param int                                                                                                                                                                                                                                                                                                                       $start Enrollment timestamp.
	 * @param array<int, int>                                                                                                                                                                                                                                                                                                           $completed Lesson id => completion timestamp.
	 * @param int                                                                                                                                                                                                                                                                                                                       $now   Current timestamp.
	 * @return array<int, array{reason: string, available_at: int|null}> Locked lessons only.
	 */
	public function locks( array $plan, int $start, array $completed, int $now ): array {
		$sequence = $plan['sequence'];
		$exempt   = array_flip( $plan['exempt'] );
		$locks    = [];

		foreach ( $sequence as $index => $item ) {
			$lesson_id = $item['id'];

			if ( isset( $completed[ $lesson_id ] ) || isset( $exempt[ $lesson_id ] ) ) {
				continue;
			}

			// Linear progression: the lesson before this one must be done.
			$previous_id = $index > 0 ? $sequence[ $index - 1 ]['id'] : null;
			$blocked     = null !== $previous_id && ! isset( $completed[ $previous_id ] );
			$times       = [];

			if ( DripRule::is_active( $plan['course_delay'] ) ) {
				$times[] = $this->clock->add( $start, $plan['course_delay'] );
			}

			$constraints = [
				$this->section_opens( $plan, $item['section'], $start, $completed ),
				$this->lesson_opens( $plan, $lesson_id, $previous_id, $start, $completed ),
			];

			foreach ( $constraints as $constraint ) {
				if ( null === $constraint ) {
					// Waiting on something that is not finished yet.
					$blocked = true;
					continue;
				}

				if ( 0 !== $constraint ) {
					$times[] = $constraint;
				}
			}

			$lock = $this->resolve( $blocked, $times, $now );

			if ( null !== $lock ) {
				$locks[ $lesson_id ] = $lock;
			}
		}

		return $locks;
	}

	/**
	 * Turn the collected constraints into a lock, or null when the lesson is open.
	 *
	 * @param bool            $blocked Whether a prerequisite is unfinished.
	 * @param array<int, int> $times   Moments the lesson cannot open before.
	 * @param int             $now     Current timestamp.
	 * @return array{reason: string, available_at: int|null}|null
	 */
	private function resolve( bool $blocked, array $times, int $now ): array|null {
		if ( $blocked ) {
			return [
				'reason'       => self::REASON_SEQUENCE,
				'available_at' => null,
			];
		}

		if ( ! $times ) {
			return null;
		}

		$available_at = max( $times );

		if ( $available_at <= $now ) {
			return null;
		}

		return [
			'reason'       => self::REASON_SCHEDULE,
			'available_at' => $available_at,
		];
	}

	/**
	 * When the lesson's section opens: a timestamp, 0 when it does not
	 * constrain the lesson, or null while its prerequisite is unfinished.
	 *
	 * @param array<string, mixed> $plan      Course plan.
	 * @param string               $section   Section id of the lesson.
	 * @param int                  $start     Enrollment timestamp.
	 * @param array<int, int>      $completed Lesson id => completion timestamp.
	 * @return int|null
	 */
	private function section_opens( array $plan, string $section, int $start, array $completed ): ?int {
		$index = null;

		foreach ( $plan['sections'] as $position => $row ) {
			if ( $row['id'] === $section ) {
				$index = $position;
				break;
			}
		}

		if ( null === $index ) {
			return 0;
		}

		$rule = $plan['sections'][ $index ]['rule'];

		if ( ! DripRule::is_active( $rule ) ) {
			return 0;
		}

		if ( DripRule::MODE_PREVIOUS !== $rule['mode'] ) {
			return $this->clock->add( $start, $rule );
		}

		$finished = $this->previous_section_finished( $plan, $index, $completed );

		if ( false === $finished ) {
			return null;
		}

		return $this->clock->add( null === $finished ? $start : $finished, $rule );
	}

	/**
	 * When the section before the given one was finished: the completion
	 * timestamp, null when there is nothing before it (or it holds no
	 * lessons), or false while it is unfinished.
	 *
	 * @param array<string, mixed> $plan      Course plan.
	 * @param int                  $index     Position of the current section.
	 * @param array<int, int>      $completed Lesson id => completion timestamp.
	 * @return int|null|false
	 */
	private function previous_section_finished( array $plan, int $index, array $completed ) {
		if ( 0 === $index ) {
			return null;
		}

		$previous = $plan['sections'][ $index - 1 ]['id'];
		$members  = [];

		foreach ( $plan['sequence'] as $item ) {
			if ( $item['section'] === $previous ) {
				$members[] = $item['id'];
			}
		}

		if ( ! $members ) {
			return null;
		}

		$finished_at = 0;

		foreach ( $members as $member ) {
			if ( ! isset( $completed[ $member ] ) ) {
				return false;
			}

			$finished_at = max( $finished_at, $completed[ $member ] );
		}

		return $finished_at;
	}

	/**
	 * When the lesson's own rule opens it: a timestamp, 0 when it has no
	 * rule, or null while the lesson before it is unfinished.
	 *
	 * @param array<string, mixed> $plan        Course plan.
	 * @param int                  $lesson_id   Lesson id.
	 * @param int|null             $previous_id Lesson before it in the outline.
	 * @param int                  $start       Enrollment timestamp.
	 * @param array<int, int>      $completed   Lesson id => completion timestamp.
	 * @return int|null
	 */
	private function lesson_opens( array $plan, int $lesson_id, ?int $previous_id, int $start, array $completed ): ?int {
		$rule = $plan['lesson_rules'][ $lesson_id ] ?? DripRule::none();

		if ( ! DripRule::is_active( $rule ) ) {
			return 0;
		}

		if ( DripRule::MODE_PREVIOUS !== $rule['mode'] || null === $previous_id ) {
			return $this->clock->add( $start, $rule );
		}

		if ( ! isset( $completed[ $previous_id ] ) ) {
			return null;
		}

		return $this->clock->add( $completed[ $previous_id ], $rule );
	}
}
