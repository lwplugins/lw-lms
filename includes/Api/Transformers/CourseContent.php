<?php
/**
 * Course description fields of the REST payload.
 *
 * @package LightweightPlugins\LMS
 */

declare(strict_types=1);

namespace LightweightPlugins\LMS\Api\Transformers;

/**
 * The `content` of a course, honoring its post password.
 *
 * The course description is public, except on a password-protected course:
 * there it stays empty (with `content_protected: true`) unless the viewer may
 * edit the course or has entered the password (post_password_required()).
 * The `content_protected` key is only present on such a course.
 */
final class CourseContent {

	/**
	 * Whether the course content is hidden from the current viewer.
	 *
	 * @param \WP_Post $post Course post.
	 * @return bool
	 */
	public static function is_protected( \WP_Post $post ): bool {
		if ( '' === (string) $post->post_password ) {
			return false;
		}

		return ! current_user_can( 'edit_post', $post->ID ) && post_password_required( $post );
	}

	/**
	 * Fields of the full course view: `content` (+ `content_protected`).
	 *
	 * @param \WP_Post $post Course post.
	 * @return array<string, mixed>
	 */
	public static function full( \WP_Post $post ): array {
		if ( self::is_protected( $post ) ) {
			return [
				'content'           => '',
				'content_protected' => true,
			];
		}

		return [
			'content' => apply_filters( 'the_content', $post->post_content ), // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- Using WordPress core filter.
		];
	}

	/**
	 * Fields of a course list item: `content_protected` when protected.
	 *
	 * The list item carries no content; its excerpt is already replaced by
	 * core on a password-protected post.
	 *
	 * @param \WP_Post $post Course post.
	 * @return array<string, bool>
	 */
	public static function list_item( \WP_Post $post ): array {
		return self::is_protected( $post ) ? [ 'content_protected' => true ] : [];
	}
}
