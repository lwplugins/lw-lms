<?php
/**
 * Persists the drag-and-drop lesson → section + order map.
 *
 * @package LightweightPlugins\LMS
 */

declare(strict_types=1);

namespace LightweightPlugins\LMS\Admin\Metaboxes;

use LightweightPlugins\LMS\Options;
use LightweightPlugins\LMS\PostTypes\Lesson;

/**
 * Reads the JSON payload submitted by the course-builder UI and updates the
 * lesson_section_id + lesson_order metas for every lesson the course owns.
 *
 * Issue #3: previously only the sections array was persisted, leaving the
 * lessons' meta untouched and the editor visually out of sync with the
 * frontend response.
 */
final class LessonAssignmentSaver {

	/**
	 * POST field name that carries the JSON-encoded assignments array.
	 */
	public const FIELD = 'lw_lms_lesson_assignments';

	/**
	 * Persist all assignments for the given course.
	 *
	 * @param int                  $course_id Course id.
	 * @param array<string, mixed> $post      Raw $_POST.
	 * @return int Number of lessons updated.
	 */
	public static function save( int $course_id, array $post ): int {
		if ( empty( $post[ self::FIELD ] ) ) {
			return 0;
		}

		$raw         = sanitize_textarea_field( wp_unslash( (string) $post[ self::FIELD ] ) );
		$assignments = json_decode( $raw, true );

		if ( ! is_array( $assignments ) ) {
			return 0;
		}

		$updated = 0;

		foreach ( $assignments as $entry ) {
			if ( self::apply_entry( $course_id, $entry ) ) {
				++$updated;
			}
		}

		return $updated;
	}

	/**
	 * Apply a single assignment entry. Skips entries pointing at lessons that
	 * are not actually attached to the course currently being saved.
	 *
	 * @param int   $course_id Course id.
	 * @param mixed $entry     Single entry from the JSON payload.
	 * @return bool True when meta was written.
	 */
	private static function apply_entry( int $course_id, mixed $entry ): bool {
		if ( ! is_array( $entry ) || empty( $entry['lesson_id'] ) ) {
			return false;
		}

		$lesson_id = (int) $entry['lesson_id'];
		if ( $lesson_id <= 0 ) {
			return false;
		}

		$lesson = get_post( $lesson_id );
		if ( ! $lesson || Lesson::POST_TYPE !== $lesson->post_type ) {
			return false;
		}

		$attached = (int) Options::get_post_meta( $lesson_id, 'lesson_course_id', 0 );
		if ( $attached !== $course_id ) {
			return false;
		}

		$section_id = isset( $entry['section_id'] ) ? sanitize_text_field( (string) $entry['section_id'] ) : '';
		$order      = isset( $entry['order'] ) ? (int) $entry['order'] : 0;

		Options::set_post_meta( $lesson_id, 'lesson_section_id', $section_id );
		Options::set_post_meta( $lesson_id, 'lesson_order', $order );

		return true;
	}
}
