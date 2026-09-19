<?php
/**
 * Course Plan.
 *
 * @package LightweightPlugins\LMS
 */

declare(strict_types=1);

namespace LightweightPlugins\LMS\Drip;

use LightweightPlugins\LMS\Options;
use LightweightPlugins\LMS\PostTypes\Lesson;
use LightweightPlugins\LMS\Progress\ProgressQueries;

/**
 * Collects everything LessonScheduler needs about one course: its outline,
 * the rules attached to the course, its sections and its lessons — and, for
 * one learner, what they have completed and when.
 */
final class CoursePlan {

	/**
	 * Build the plan of a course.
	 *
	 * @param int $course_id Course ID.
	 * @return array<string, mixed>
	 */
	public static function build( int $course_id ): array {
		$sections = Options::get_post_meta( $course_id, 'course_sections', [] );
		$sections = is_array( $sections ) ? $sections : [];
		$lessons  = self::lessons( $course_id );

		$lesson_rules = [];

		foreach ( $lessons as $lesson ) {
			$rule = DripSettings::lesson_rule( $lesson['id'] );

			if ( DripRule::is_active( $rule ) ) {
				$lesson_rules[ $lesson['id'] ] = $rule;
			}
		}

		return [
			'sequence'     => CourseOutline::sequence( $lessons, $sections ),
			'sections'     => self::section_rules( $sections ),
			'lesson_rules' => $lesson_rules,
			'course_delay' => DripSettings::course_delay( $course_id ),
			'exempt'       => self::preview_lessons( $course_id ),
		];
	}

	/**
	 * What the learner has completed in the course.
	 *
	 * Rows completed before completion timestamps were stored fall back to
	 * the start of the learner's clock, so a delay measured from them is
	 * never counted from "now".
	 *
	 * @param int $user_id   User ID.
	 * @param int $course_id Course ID.
	 * @param int $start     Start of the learner's course clock.
	 * @return array<int, int> Lesson id => completion timestamp.
	 */
	public static function completed( int $user_id, int $course_id, int $start ): array {
		$completed = [];

		foreach ( ProgressQueries::get_course_progress( $user_id, $course_id ) as $row ) {
			if ( 'completed' !== $row->status ) {
				continue;
			}

			$completed[ (int) $row->lesson_id ] = DripTime::from_mysql( $row->completed_at ) ?? $start;
		}

		return $completed;
	}

	/**
	 * Published lessons of the course with their section and order.
	 *
	 * @param int $course_id Course ID.
	 * @return array<int, array{id: int, section: string, order: int}>
	 */
	private static function lessons( int $course_id ): array {
		$posts = get_posts(
			[
				'post_type'      => Lesson::POST_TYPE,
				'post_status'    => 'publish',
				'posts_per_page' => -1,
				'meta_query'     => [ // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query -- Required for course filtering.
					[
						'key'     => Options::META_PREFIX . 'lesson_course_id',
						'value'   => $course_id,
						'compare' => '=',
						'type'    => 'NUMERIC',
					],
				],
			]
		);

		$lessons = [];

		foreach ( $posts as $post ) {
			$lessons[] = [
				'id'      => (int) $post->ID,
				'section' => (string) Options::get_post_meta( $post->ID, 'lesson_section_id', '' ),
				'order'   => (int) Options::get_post_meta( $post->ID, 'lesson_order', 0 ),
			];
		}

		return $lessons;
	}

	/**
	 * Section rules in the order the course presents its sections.
	 *
	 * @param array<int, mixed> $sections Section rows.
	 * @return array<int, array{id: string, rule: array{mode: string, value: int, unit: string}}>
	 */
	private static function section_rules( array $sections ): array {
		$by_id = [];

		foreach ( $sections as $section ) {
			if ( is_array( $section ) && isset( $section['id'] ) ) {
				$by_id[ (string) $section['id'] ] = $section;
			}
		}

		$rules = [];

		foreach ( CourseOutline::section_ids( $sections ) as $section_id ) {
			$rules[] = [
				'id'   => $section_id,
				'rule' => DripSettings::section_rule( $by_id[ $section_id ] ?? [] ),
			];
		}

		return $rules;
	}

	/**
	 * Preview lessons, which drip never holds back.
	 *
	 * @param int $course_id Course ID.
	 * @return array<int, int>
	 */
	private static function preview_lessons( int $course_id ): array {
		$ids = Options::get_post_meta( $course_id, 'preview_lesson_ids', [] );

		return is_array( $ids ) ? array_values( array_map( 'intval', $ids ) ) : [];
	}
}
