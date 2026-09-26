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
}
