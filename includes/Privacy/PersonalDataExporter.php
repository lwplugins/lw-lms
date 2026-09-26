<?php
/**
 * Personal data exporter.
 *
 * @package LightweightPlugins\LMS
 */

declare(strict_types=1);

namespace LightweightPlugins\LMS\Privacy;

/**
 * Exports a user's course enrollments, lesson progress and quiz attempts
 * (with their answers) for Tools → Export Personal Data.
 */
final class PersonalDataExporter {

	/**
	 * Quiz attempts per export page.
	 */
	public const PER_PAGE = 100;

	/**
	 * Export one page.
	 *
	 * @param string $email Email address.
	 * @param int    $page  Page, from 1.
	 * @return array{data: array<int, array<string, mixed>>, done: bool}
	 */
	public static function export( string $email, int $page = 1 ): array {
		$user = get_user_by( 'email', $email );

		if ( ! $user ) {
			return [
				'data' => [],
				'done' => true,
			];
		}

		$page = max( 1, $page );
		$data = [];

		if ( 1 === $page ) {
			foreach ( PersonalDataQueries::access( (int) $user->ID ) as $row ) {
				$data[] = self::item(
					'lw-lms-enrollments',
					__( 'Course enrollments', 'lw-lms' ),
					'lw-lms-access-' . $row->id,
					[
						__( 'Course', 'lw-lms' )  => self::title( (int) $row->course_id ),
						__( 'Source', 'lw-lms' )  => $row->source,
						__( 'Status', 'lw-lms' )  => $row->status,
						__( 'Granted', 'lw-lms' ) => $row->granted_at,
						__( 'Expires', 'lw-lms' ) => $row->expires_at ? $row->expires_at . ' UTC' : __( 'Never', 'lw-lms' ),
					]
				);
			}

			foreach ( PersonalDataQueries::progress( (int) $user->ID ) as $row ) {
				$data[] = self::item(
					'lw-lms-progress',
					__( 'Lesson progress', 'lw-lms' ),
					'lw-lms-progress-' . $row->id,
					[
						__( 'Course', 'lw-lms' )    => self::title( (int) $row->course_id ),
						__( 'Lesson', 'lw-lms' )    => self::title( (int) $row->lesson_id ),
						__( 'Status', 'lw-lms' )    => $row->status,
						__( 'Completed', 'lw-lms' ) => (string) $row->completed_at,
					]
				);
			}
		}

		$attempts = PersonalDataQueries::quiz_attempts( (int) $user->ID, self::PER_PAGE, ( $page - 1 ) * self::PER_PAGE );

		foreach ( $attempts as $row ) {
			$data[] = self::item(
				'lw-lms-quiz-attempts',
				__( 'Quiz attempts', 'lw-lms' ),
				'lw-lms-quiz-attempt-' . $row->id,
				[
					__( 'Lesson', 'lw-lms' )    => self::title( (int) $row->lesson_id ),
					__( 'Submitted', 'lw-lms' ) => $row->submitted_at,
					__( 'Score', 'lw-lms' )     => $row->percentage . '%',
					__( 'Passed', 'lw-lms' )    => $row->passed ? __( 'Yes', 'lw-lms' ) : __( 'No', 'lw-lms' ),
					__( 'Answers', 'lw-lms' )   => (string) $row->answers,
				]
			);
		}

		return [
			'data' => $data,
			'done' => count( $attempts ) < self::PER_PAGE,
		];
	}

	/**
	 * One export item.
	 *
	 * @param string                $group_id    Group ID.
	 * @param string                $group_label Group label.
	 * @param string                $item_id     Item ID.
	 * @param array<string, string> $fields      Label => value.
	 * @return array<string, mixed>
	 */
	private static function item( string $group_id, string $group_label, string $item_id, array $fields ): array {
		$data = [];

		foreach ( $fields as $name => $value ) {
			$data[] = [
				'name'  => $name,
				'value' => (string) $value,
			];
		}

		return [
			'group_id'    => $group_id,
			'group_label' => $group_label,
			'item_id'     => $item_id,
			'data'        => $data,
		];
	}

	/**
	 * Post title with its ID (the post may be gone).
	 *
	 * @param int $post_id Post ID.
	 * @return string
	 */
	private static function title( int $post_id ): string {
		$title = $post_id ? get_the_title( $post_id ) : '';

		return '' !== $title ? sprintf( '%s (#%d)', $title, $post_id ) : sprintf( '#%d', $post_id );
	}
}
