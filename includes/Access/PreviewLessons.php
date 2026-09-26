<?php
/**
 * Preview lessons of a course.
 *
 * @package LightweightPlugins\LMS
 */

declare(strict_types=1);

namespace LightweightPlugins\LMS\Access;

use LightweightPlugins\LMS\Options;

/**
 * The one place that reads a course's preview lessons.
 *
 * They are stored on the course (`preview_lesson_ids`), never on the lesson.
 * The "Enable preview lessons" setting is the switch: with it off, no lesson
 * is a preview anywhere (access, drip, REST payloads), while the stored list
 * is kept so turning it back on restores the previews.
 */
final class PreviewLessons {

	/**
	 * Stored preview lesson IDs of a course, as ints.
	 *
	 * @param int $course_id Course ID.
	 * @return array<int, int>
	 */
	public static function stored( int $course_id ): array {
		$ids = Options::get_post_meta( $course_id, 'preview_lesson_ids', [] );

		return is_array( $ids ) ? array_values( array_filter( array_map( 'intval', $ids ) ) ) : [];
	}

	/**
	 * Whether the preview lessons feature is on.
	 *
	 * @return bool
	 */
	public static function enabled(): bool {
		return (bool) Options::get( 'enable_preview_lessons', true );
	}

	/**
	 * Preview lesson IDs that currently count (empty when the feature is off).
	 *
	 * @param int $course_id Course ID.
	 * @return array<int, int>
	 */
	public static function active( int $course_id ): array {
		return self::enabled() ? self::stored( $course_id ) : [];
	}
}
