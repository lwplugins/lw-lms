<?php
/**
 * Course Transformer.
 *
 * @package LightweightPlugins\LMS
 */

declare(strict_types=1);

namespace LightweightPlugins\LMS\Api\Transformers;

use LightweightPlugins\LMS\Options;
use LightweightPlugins\LMS\Access\AccessChecker;
use LightweightPlugins\LMS\Progress\ProgressCalculator;
use LightweightPlugins\LMS\Taxonomies\CourseCategory;
use LightweightPlugins\LMS\Taxonomies\CourseLevel;
use LightweightPlugins\LMS\PostTypes\Lesson;

/**
 * Transforms course data for API responses.
 */
final class CourseTransformer {

	/**
	 * Transform course for list view.
	 *
	 * @param \WP_Post $post    Course post.
	 * @param int|null $user_id User ID.
	 * @return array
	 */
	public static function transform_list_item( \WP_Post $post, ?int $user_id = null ): array {
		if ( null === $user_id ) {
			$user_id = get_current_user_id();
		}

		return [
			'id'           => $post->ID,
			'title'        => $post->post_title,
			'slug'         => $post->post_name,
			'excerpt'      => get_the_excerpt( $post ),
			'thumbnail'    => get_the_post_thumbnail_url( $post, 'large' ),
			'categories'   => self::get_taxonomy_terms( $post->ID, CourseCategory::TAXONOMY ),
			'level'        => self::get_single_term( $post->ID, CourseLevel::TAXONOMY ),
			'duration'     => Options::get_post_meta( $post->ID, 'duration', '' ),
			'lesson_count' => ProgressCalculator::get_total_lessons( $post->ID ),
			'access'       => [
				'type'       => AccessChecker::get_access_type( $post->ID ),
				'has_access' => AccessChecker::has_course_access( $post->ID, $user_id ),
			],
		];
	}

	/**
	 * Transform full course data.
	 *
	 * @param \WP_Post $post    Course post.
	 * @param int|null $user_id User ID.
	 * @return array
	 */
	public static function transform_full( \WP_Post $post, ?int $user_id = null ): array {
		if ( null === $user_id ) {
			$user_id = get_current_user_id();
		}

		$has_access  = AccessChecker::has_course_access( $post->ID, $user_id );
		$access_info = AccessChecker::get_access_info( $post->ID, $user_id );

		$data = [
			'id'         => $post->ID,
			'title'      => $post->post_title,
			'slug'       => $post->post_name,
			'excerpt'    => get_the_excerpt( $post ),
			'thumbnail'  => get_the_post_thumbnail_url( $post, 'large' ),
			'categories' => self::get_taxonomy_terms( $post->ID, CourseCategory::TAXONOMY ),
			'level'      => self::get_single_term( $post->ID, CourseLevel::TAXONOMY ),
			'duration'   => Options::get_post_meta( $post->ID, 'duration', '' ),
			'access'     => $access_info,
		];

		// Add content only if user has access.
		if ( $has_access ) {
			$data['content'] = apply_filters( 'the_content', $post->post_content ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- Using WordPress core filter.

			// Only include raw content for editors.
			if ( current_user_can( 'edit_posts' ) ) {
				$data['content_raw'] = $post->post_content;
			}
		}

		// Always include sections and lessons structure.
		$data['sections']                = self::get_sections_with_lessons( $post->ID, $user_id, $has_access );
		$data['lessons_without_section'] = self::get_orphan_lessons( $post->ID, $user_id, $has_access );
		$data['attachments']             = $has_access ? self::get_attachments( $post->ID ) : [];

		// Progress for authenticated users.
		if ( $user_id ) {
			$data['progress'] = ProgressCalculator::calculate( $user_id, $post->ID );
		}

		return $data;
	}

	/**
	 * Get taxonomy terms for a post.
	 *
	 * @param int    $post_id  Post ID.
	 * @param string $taxonomy Taxonomy name.
	 * @return array
	 */
	private static function get_taxonomy_terms( int $post_id, string $taxonomy ): array {
		$terms = get_the_terms( $post_id, $taxonomy );

		if ( ! $terms || is_wp_error( $terms ) ) {
			return [];
		}

		return array_map(
			function ( $term ) {
				return [
					'id'   => $term->term_id,
					'name' => $term->name,
					'slug' => $term->slug,
				];
			},
			$terms
		);
	}

	/**
	 * Get single term for a post.
	 *
	 * @param int    $post_id  Post ID.
	 * @param string $taxonomy Taxonomy name.
	 * @return array|null
	 */
	private static function get_single_term( int $post_id, string $taxonomy ): ?array {
		$terms = get_the_terms( $post_id, $taxonomy );

		if ( ! $terms || is_wp_error( $terms ) ) {
			return null;
		}

		$term = reset( $terms );
		return [
			'id'   => $term->term_id,
			'name' => $term->name,
			'slug' => $term->slug,
		];
	}

	/**
	 * Get sections with their lessons.
	 *
	 * @param int  $course_id  Course ID.
	 * @param int  $user_id    User ID.
	 * @param bool $has_access Whether user has access.
	 * @return array
	 */
	private static function get_sections_with_lessons( int $course_id, int $user_id, bool $has_access ): array {
		$sections = Options::get_post_meta( $course_id, 'course_sections', [] );
		$result   = [];

		foreach ( $sections as $section ) {
			$section_lessons = self::get_section_lessons( $course_id, $section['id'], $user_id, $has_access );

			$result[] = [
				'id'          => $section['id'],
				'title'       => $section['title'],
				'description' => $section['description'] ?? '',
				'order'       => $section['order'] ?? 0,
				'lessons'     => $section_lessons,
			];
		}

		return $result;
	}

	/**
	 * Get lessons for a section.
	 *
	 * @param int    $course_id  Course ID.
	 * @param string $section_id Section ID.
	 * @param int    $user_id    User ID.
	 * @param bool   $has_access Whether user has course access.
	 * @return array
	 */
	private static function get_section_lessons( int $course_id, string $section_id, int $user_id, bool $has_access ): array {
		$lessons = get_posts(
			[
				'post_type'      => Lesson::POST_TYPE,
				'post_status'    => 'publish',
				'posts_per_page' => -1,
				'meta_key'       => Options::META_PREFIX . 'lesson_order',
				'orderby'        => 'meta_value_num',
				'order'          => 'ASC',
				'meta_query'     => [
					'relation' => 'AND',
					[
						'key'     => Options::META_PREFIX . 'lesson_course_id',
						'value'   => $course_id,
						'compare' => '=',
						'type'    => 'NUMERIC',
					],
					[
						'key'     => Options::META_PREFIX . 'lesson_section_id',
						'value'   => $section_id,
						'compare' => '=',
					],
				],
			]
		);

		return self::transform_lessons_list( $lessons, $course_id, $user_id, $has_access );
	}

	/**
	 * Get lessons without a section.
	 *
	 * @param int  $course_id  Course ID.
	 * @param int  $user_id    User ID.
	 * @param bool $has_access Whether user has access.
	 * @return array
	 */
	private static function get_orphan_lessons( int $course_id, int $user_id, bool $has_access ): array {
		$lessons = get_posts(
			[
				'post_type'      => Lesson::POST_TYPE,
				'post_status'    => 'publish',
				'posts_per_page' => -1,
				'meta_key'       => Options::META_PREFIX . 'lesson_order',
				'orderby'        => 'meta_value_num',
				'order'          => 'ASC',
				'meta_query'     => [
					'relation' => 'AND',
					[
						'key'     => Options::META_PREFIX . 'lesson_course_id',
						'value'   => $course_id,
						'compare' => '=',
						'type'    => 'NUMERIC',
					],
					[
						'relation' => 'OR',
						[
							'key'     => Options::META_PREFIX . 'lesson_section_id',
							'compare' => 'NOT EXISTS',
						],
						[
							'key'     => Options::META_PREFIX . 'lesson_section_id',
							'value'   => '',
							'compare' => '=',
						],
					],
				],
			]
		);

		return self::transform_lessons_list( $lessons, $course_id, $user_id, $has_access );
	}

	/**
	 * Transform lessons list for course response.
	 *
	 * @param array $lessons    Lessons array.
	 * @param int   $course_id  Course ID.
	 * @param int   $user_id    User ID.
	 * @param bool  $has_access Whether user has course access.
	 * @return array
	 */
	private static function transform_lessons_list( array $lessons, int $course_id, int $user_id, bool $has_access ): array {
		$preview_ids = Options::get_post_meta( $course_id, 'preview_lesson_ids', [] );
		$completed   = $user_id ? ProgressCalculator::get_total_lessons( $course_id ) : [];

		return array_map(
			function ( $lesson ) use ( $preview_ids, $has_access, $user_id, $completed ) {
				$is_preview   = in_array( $lesson->ID, $preview_ids, true );
				$is_completed = ProgressCalculator::is_lesson_completed( $user_id, $lesson->ID );

				return [
					'id'         => $lesson->ID,
					'title'      => $lesson->post_title,
					'order'      => (int) Options::get_post_meta( $lesson->ID, 'lesson_order', 0 ),
					'duration'   => Options::get_post_meta( $lesson->ID, 'duration', '' ),
					'preview'    => $is_preview,
					'accessible' => $has_access || ( $is_preview && $user_id > 0 ),
					'completed'  => $is_completed,
				];
			},
			$lessons
		);
	}

	/**
	 * Get attachments for a course.
	 *
	 * @param int $course_id Course ID.
	 * @return array
	 */
	private static function get_attachments( int $course_id ): array {
		$attachments = Options::get_post_meta( $course_id, 'attachments', [] );
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

			$result[] = [
				'id'           => $id,
				'title'        => $attachment['title'] ? $attachment['title'] : $post->post_title,
				'filename'     => basename( get_attached_file( $id ) ),
				'mime_type'    => get_post_mime_type( $id ),
				'size'         => filesize( get_attached_file( $id ) ),
				'download_url' => rest_url( 'lms/v1/download/' . $id ),
			];
		}

		return $result;
	}
}
