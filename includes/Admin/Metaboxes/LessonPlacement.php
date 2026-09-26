<?php
/**
 * Lesson course/section placement.
 *
 * @package LightweightPlugins\LMS
 */

declare(strict_types=1);

namespace LightweightPlugins\LMS\Admin\Metaboxes;

use LightweightPlugins\LMS\Options;
use LightweightPlugins\LMS\PostTypes\Course;

/**
 * Validates the course and section a lesson is saved into.
 *
 * A section ID is kept only when that section exists in the chosen course,
 * so a lesson moved to another course never keeps a section of the old one.
 * Section IDs keep their case (see SectionSanitizer::section_id()).
 */
final class LessonPlacement {

	/**
	 * The course ID when it is a course, else 0.
	 *
	 * @param int $course_id Submitted course ID.
	 * @return int
	 */
	public static function course( int $course_id ): int {
		if ( $course_id <= 0 ) {
			return 0;
		}

		$course = get_post( $course_id );

		return $course instanceof \WP_Post && Course::POST_TYPE === $course->post_type ? $course_id : 0;
	}

	/**
	 * The section ID when it belongs to the course, else ''.
	 *
	 * @param mixed $section_id Submitted section ID.
	 * @param int   $course_id  Validated course ID.
	 * @return string
	 */
	public static function section( mixed $section_id, int $course_id ): string {
		$section_id = SectionSanitizer::section_id( $section_id );

		if ( '' === $section_id || ! $course_id ) {
			return '';
		}

		return in_array( $section_id, array_keys( self::sections( $course_id ) ), true ) ? $section_id : '';
	}

	/**
	 * Section titles of a course, keyed by section ID.
	 *
	 * @param int $course_id Course ID.
	 * @return array<string, string>
	 */
	public static function sections( int $course_id ): array {
		$sections = Options::get_post_meta( $course_id, 'course_sections', [] );
		$result   = [];

		foreach ( is_array( $sections ) ? $sections : [] as $section ) {
			if ( is_array( $section ) && isset( $section['id'] ) && '' !== (string) $section['id'] ) {
				$result[ (string) $section['id'] ] = (string) ( $section['title'] ?? '' );
			}
		}

		return $result;
	}
}
