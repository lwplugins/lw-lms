<?php
/**
 * Drip Settings.
 *
 * @package LightweightPlugins\LMS
 */

declare(strict_types=1);

namespace LightweightPlugins\LMS\Drip;

use LightweightPlugins\LMS\Options;

/**
 * Reads the drip configuration off course, section and lesson meta.
 *
 * Everything comes back normalized, so callers never see half-written or
 * hand-edited meta.
 */
final class DripSettings {

	/**
	 * Lessons can be taken in any order (default).
	 */
	public const PROGRESSION_FREE = 'free';

	/**
	 * Lessons open one after the other; drip schedules apply.
	 */
	public const PROGRESSION_LINEAR = 'linear';

	/**
	 * Course meta key holding the progression mode.
	 */
	public const META_PROGRESSION = 'progression';

	/**
	 * Course meta key holding the delay before the course opens.
	 */
	public const META_COURSE_DELAY = 'drip_delay';

	/**
	 * Lesson meta key holding the lesson's own rule.
	 */
	public const META_LESSON_RULE = 'drip';

	/**
	 * Section array key holding the section's rule.
	 */
	public const SECTION_RULE_KEY = 'drip';

	/**
	 * Progression mode of a course.
	 *
	 * @param int $course_id Course ID.
	 * @return string
	 */
	public static function progression( int $course_id ): string {
		$stored = Options::get_post_meta( $course_id, self::META_PROGRESSION, self::PROGRESSION_FREE );

		return self::PROGRESSION_LINEAR === $stored ? self::PROGRESSION_LINEAR : self::PROGRESSION_FREE;
	}

	/**
	 * Whether a course runs in linear mode.
	 *
	 * Drip schedules are only honoured in linear mode: with free progression
	 * a learner can open anything, so there is nothing to pace.
	 *
	 * @param int $course_id Course ID.
	 * @return bool
	 */
	public static function is_linear( int $course_id ): bool {
		return self::PROGRESSION_LINEAR === self::progression( $course_id );
	}

	/**
	 * Delay before a course opens, counted from enrollment.
	 *
	 * @param int $course_id Course ID.
	 * @return array{mode: string, value: int, unit: string}
	 */
	public static function course_delay( int $course_id ): array {
		return DripRule::normalize(
			Options::get_post_meta( $course_id, self::META_COURSE_DELAY, [] ),
			[ DripRule::MODE_NONE, DripRule::MODE_ENROLLMENT ]
		);
	}

	/**
	 * A lesson's own rule.
	 *
	 * @param int $lesson_id Lesson ID.
	 * @return array{mode: string, value: int, unit: string}
	 */
	public static function lesson_rule( int $lesson_id ): array {
		return DripRule::normalize( Options::get_post_meta( $lesson_id, self::META_LESSON_RULE, [] ) );
	}

	/**
	 * A section's rule, read off the section row of the course meta.
	 *
	 * @param array<string, mixed> $section Section row.
	 * @return array{mode: string, value: int, unit: string}
	 */
	public static function section_rule( array $section ): array {
		return DripRule::normalize( $section[ self::SECTION_RULE_KEY ] ?? [] );
	}
}
