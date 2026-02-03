<?php
/**
 * Lesson Transformer.
 *
 * @package LightweightPlugins\LMS
 */

declare(strict_types=1);

namespace LightweightPlugins\LMS\Api\Transformers;

use LightweightPlugins\LMS\Options;
use LightweightPlugins\LMS\PostTypes\Course;
use LightweightPlugins\LMS\PostTypes\Lesson;

/**
 * Transforms lesson data for API responses.
 */
final class LessonTransformer {

	/**
	 * Transform lesson for API response.
	 *
	 * @param \WP_Post $post    Lesson post.
	 * @param int|null $user_id User ID.
	 * @return array
	 */
	public static function transform( \WP_Post $post, ?int $user_id = null ): array { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.FoundAfterLastUsed -- Reserved for future use.
		$course_id  = (int) Options::get_post_meta( $post->ID, 'lesson_course_id', 0 );
		$section_id = Options::get_post_meta( $post->ID, 'lesson_section_id', '' );
		$video      = Options::get_post_meta( $post->ID, 'video', [] );

		$data = [
			'id'          => $post->ID,
			'title'       => $post->post_title,
			'content'     => apply_filters( 'the_content', $post->post_content ), // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- Using WordPress core filter.
			'course'      => self::get_course_info( $course_id ),
			'section'     => self::get_section_info( $course_id, $section_id ),
			'order'       => (int) Options::get_post_meta( $post->ID, 'lesson_order', 0 ),
			'duration'    => Options::get_post_meta( $post->ID, 'duration', '' ),
			'video'       => ! empty( $video ) ? $video : null,
			'attachments' => self::get_attachments( $post->ID ),
			'navigation'  => self::get_navigation( $post->ID, $course_id, $section_id ),
		];

		// Raw content for editors.
		if ( current_user_can( 'edit_posts' ) ) {
			$data['content_raw'] = $post->post_content;
		}

		return $data;
	}

	/**
	 * Get course info.
	 *
	 * @param int $course_id Course ID.
	 * @return array|null
	 */
	private static function get_course_info( int $course_id ): ?array {
		if ( ! $course_id ) {
			return null;
		}

		$course = get_post( $course_id );
		if ( ! $course || Course::POST_TYPE !== $course->post_type ) {
			return null;
		}

		return [
			'id'    => $course->ID,
			'title' => $course->post_title,
		];
	}

	/**
	 * Get section info.
	 *
	 * @param int    $course_id  Course ID.
	 * @param string $section_id Section ID.
	 * @return array|null
	 */
	private static function get_section_info( int $course_id, string $section_id ): ?array {
		if ( ! $section_id || ! $course_id ) {
			return null;
		}

		$sections = Options::get_post_meta( $course_id, 'course_sections', [] );

		foreach ( $sections as $section ) {
			if ( $section['id'] === $section_id ) {
				return [
					'id'    => $section['id'],
					'title' => $section['title'],
				];
			}
		}

		return null;
	}

	/**
	 * Get attachments for a lesson.
	 *
	 * @param int $lesson_id Lesson ID.
	 * @return array
	 */
	private static function get_attachments( int $lesson_id ): array {
		$attachments = Options::get_post_meta( $lesson_id, 'attachments', [] );
		$result      = [];

		foreach ( $attachments as $attachment ) {
			$id = $attachment['id'] ?? 0;
			if ( ! $id ) {
				continue;
			}

			$post = get_post( $id );
			if ( ! $post ) {
				continue;
			}

			$file_path = get_attached_file( $id );

			$result[] = [
				'id'           => $id,
				'title'        => $attachment['title'] ? $attachment['title'] : $post->post_title,
				'filename'     => basename( $file_path ),
				'mime_type'    => get_post_mime_type( $id ),
				'size'         => file_exists( $file_path ) ? filesize( $file_path ) : 0,
				'download_url' => rest_url( 'lms/v1/download/' . $id ),
			];
		}

		return $result;
	}

	/**
	 * Get navigation (previous/next lesson).
	 *
	 * @param int    $lesson_id  Current lesson ID.
	 * @param int    $course_id  Course ID.
	 * @param string $section_id Section ID.
	 * @return array
	 */
	private static function get_navigation( int $lesson_id, int $course_id, string $section_id ): array { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.FoundAfterLastUsed -- Reserved for section-based navigation.
		$all_lessons   = self::get_all_course_lessons( $course_id );
		$current_index = array_search( $lesson_id, array_column( $all_lessons, 'ID' ), true );

		$previous = null;
		$next     = null;

		if ( false !== $current_index ) {
			if ( $current_index > 0 ) {
				$prev_lesson = $all_lessons[ $current_index - 1 ];
				$previous    = [
					'id'    => $prev_lesson->ID,
					'title' => $prev_lesson->post_title,
				];
			}

			if ( $current_index < count( $all_lessons ) - 1 ) {
				$next_lesson = $all_lessons[ $current_index + 1 ];
				$next        = [
					'id'    => $next_lesson->ID,
					'title' => $next_lesson->post_title,
				];
			}
		}

		return [
			'previous' => $previous,
			'next'     => $next,
		];
	}

	/**
	 * Get all lessons for a course in order.
	 *
	 * @param int $course_id Course ID.
	 * @return array
	 */
	private static function get_all_course_lessons( int $course_id ): array {
		return get_posts(
			[
				'post_type'      => Lesson::POST_TYPE,
				'post_status'    => 'publish',
				'posts_per_page' => -1,
				'meta_key'       => Options::META_PREFIX . 'lesson_order', // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key -- Required for lesson ordering.
				'orderby'        => 'meta_value_num',
				'order'          => 'ASC',
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
	}
}
