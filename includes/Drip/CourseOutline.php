<?php
/**
 * Course Outline.
 *
 * @package LightweightPlugins\LMS
 */

declare(strict_types=1);

namespace LightweightPlugins\LMS\Drip;

/**
 * Puts a course's lessons into the single order the learner walks them in:
 * the order the course builder shows — lessons outside any section first,
 * then the sections by their own order, each with its lessons by
 * lesson_order.
 *
 * This is what "the previous lesson" means for linear progression, so it has
 * to be defined even when lesson_order values repeat or a lesson points at a
 * section that no longer exists (those are invisible in the outline and are
 * left out, rather than becoming a prerequisite nobody can reach).
 */
final class CourseOutline {

	/**
	 * Build the ordered sequence of a course.
	 *
	 * @param array<int, array{id: int, section: string, order: int}> $lessons  Course lessons.
	 * @param array<int, mixed>                                       $sections Section rows from course meta.
	 * @return array<int, array{id: int, section: string}>
	 */
	public static function sequence( array $lessons, array $sections ): array {
		$grouped = [ '' => [] ];

		foreach ( self::section_ids( $sections ) as $section_id ) {
			$grouped[ $section_id ] = [];
		}

		foreach ( $lessons as $lesson ) {
			$section = $lesson['section'];

			if ( ! isset( $grouped[ $section ] ) ) {
				// Lesson of a deleted section: not part of the outline.
				continue;
			}

			$grouped[ $section ][] = $lesson;
		}

		$sequence = [];

		foreach ( $grouped as $section_id => $group ) {
			usort( $group, [ self::class, 'compare' ] );

			foreach ( $group as $lesson ) {
				$sequence[] = [
					'id'      => $lesson['id'],
					'section' => (string) $section_id,
				];
			}
		}

		return $sequence;
	}

	/**
	 * Section ids in the order the course presents them.
	 *
	 * @param array<int, mixed> $sections Section rows from course meta.
	 * @return array<int, string>
	 */
	public static function section_ids( array $sections ): array {
		$rows = [];

		foreach ( $sections as $index => $section ) {
			if ( ! is_array( $section ) || ! isset( $section['id'] ) || '' === $section['id'] ) {
				continue;
			}

			$rows[] = [
				'id'    => (string) $section['id'],
				'order' => (int) ( $section['order'] ?? 0 ),
				'index' => $index,
			];
		}

		usort(
			$rows,
			static function ( array $a, array $b ): int {
				return [ $a['order'], $a['index'] ] <=> [ $b['order'], $b['index'] ];
			}
		);

		return array_column( $rows, 'id' );
	}

	/**
	 * Order two lessons of the same group.
	 *
	 * @param array{id: int, order: int} $a First lesson.
	 * @param array{id: int, order: int} $b Second lesson.
	 * @return int
	 */
	private static function compare( array $a, array $b ): int {
		return [ $a['order'], $a['id'] ] <=> [ $b['order'], $b['id'] ];
	}
}
