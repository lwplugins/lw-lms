<?php
/**
 * Sanitizers of the registered post meta.
 *
 * @package LightweightPlugins\LMS
 */

declare(strict_types=1);

namespace LightweightPlugins\LMS\Meta;

use LightweightPlugins\LMS\Access\NewCourseDefaults;
use LightweightPlugins\LMS\Admin\Metaboxes\LessonPlacement;
use LightweightPlugins\LMS\Admin\Metaboxes\SectionSanitizer;
use LightweightPlugins\LMS\Drip\DripRule;
use LightweightPlugins\LMS\Drip\DripSettings;

/**
 * The sanitize_callback of every course and lesson meta key.
 *
 * WordPress runs a registered sanitize_callback on every write of the key
 * (update_post_meta(), core REST, the block editor), so the metaboxes, the
 * CLI and /wp/v2/course|lesson all store the same cleaned shape. Each
 * callback accepts its own output unchanged.
 */
final class MetaSanitizers {

	/**
	 * Access type: open, free or paid; anything else becomes free.
	 *
	 * @param mixed $value Value.
	 * @return string
	 */
	public static function access_type( mixed $value ): string {
		return is_string( $value ) && in_array( $value, NewCourseDefaults::ACCESS_TYPES, true ) ? $value : 'free';
	}

	/**
	 * List of positive IDs (products, subscriptions, plans, lessons).
	 *
	 * @param mixed $value Value.
	 * @return array<int, int>
	 */
	public static function id_list( mixed $value ): array {
		if ( ! is_array( $value ) ) {
			return [];
		}

		$ids = array_filter( array_map( 'absint', array_filter( $value, 'is_numeric' ) ) );

		return array_values( array_unique( $ids ) );
	}

	/**
	 * Product durations: product ID => days.
	 *
	 * @param mixed $value Value.
	 * @return array<int|string, int>
	 */
	public static function durations( mixed $value ): array {
		$clean = [];

		foreach ( is_array( $value ) ? $value : [] as $product_id => $days ) {
			if ( absint( $product_id ) > 0 && is_numeric( $days ) ) {
				$clean[ (string) absint( $product_id ) ] = absint( $days );
			}
		}

		return $clean;
	}

	/**
	 * Subscription variation pairs ("parent:variation").
	 *
	 * @param mixed $value Value.
	 * @return array<int, string>
	 */
	public static function variation_pairs( mixed $value ): array {
		$pairs = [];

		foreach ( is_array( $value ) ? $value : [] as $pair ) {
			if ( is_string( $pair ) && 1 === preg_match( '/^\d+:\d+$/', $pair ) ) {
				$pairs[] = $pair;
			}
		}

		return array_values( array_unique( $pairs ) );
	}

	/**
	 * Course sections (same rules as the course builder).
	 *
	 * @param mixed $value Value.
	 * @return array<int, array<string, mixed>>
	 */
	public static function sections( mixed $value ): array {
		return is_array( $value ) ? SectionSanitizer::sanitize( $value ) : [];
	}

	/**
	 * Attachments list: [{id, title, description}].
	 *
	 * @param mixed $value Value.
	 * @return array<int, array{id: int, title: string, description: string}>
	 */
	public static function attachments( mixed $value ): array {
		$clean = [];

		foreach ( is_array( $value ) ? $value : [] as $item ) {
			$id = is_array( $item ) && isset( $item['id'] ) && is_numeric( $item['id'] ) ? absint( $item['id'] ) : 0;

			if ( $id > 0 ) {
				$clean[] = [
					'id'          => $id,
					'title'       => sanitize_text_field( (string) ( $item['title'] ?? '' ) ),
					'description' => sanitize_textarea_field( (string) ( $item['description'] ?? '' ) ),
				];
			}
		}

		return $clean;
	}

	/**
	 * Plain text (duration, instructor).
	 *
	 * @param mixed $value Value.
	 * @return string
	 */
	public static function text( mixed $value ): string {
		return is_scalar( $value ) ? sanitize_text_field( (string) $value ) : '';
	}

	/**
	 * Non-negative integer (lesson order).
	 *
	 * @param mixed $value Value.
	 * @return int
	 */
	public static function absint( mixed $value ): int {
		return is_numeric( $value ) ? absint( $value ) : 0;
	}

	/**
	 * Parent course of a lesson: a course ID, else 0.
	 *
	 * @param mixed $value Value.
	 * @return int
	 */
	public static function course_id( mixed $value ): int {
		return LessonPlacement::course( self::absint( $value ) );
	}

	/**
	 * Section ID of a lesson (characters only; case kept).
	 *
	 * @param mixed $value Value.
	 * @return string
	 */
	public static function section_id( mixed $value ): string {
		return SectionSanitizer::section_id( $value );
	}

	/**
	 * Lesson video: re-derived from its URL by the same parser the metabox
	 * uses, so provider, ID and embed URL can never be written directly.
	 *
	 * @param mixed $value Value.
	 * @return array<string, mixed>
	 */
	public static function video( mixed $value ): array {
		$url = is_array( $value ) && isset( $value['url'] ) && is_string( $value['url'] ) ? $value['url'] : '';

		return VideoParser::parse( esc_url_raw( $url ) );
	}

	/**
	 * Course progression: free or linear.
	 *
	 * @param mixed $value Value.
	 * @return string
	 */
	public static function progression( mixed $value ): string {
		return DripSettings::PROGRESSION_LINEAR === $value ? DripSettings::PROGRESSION_LINEAR : DripSettings::PROGRESSION_FREE;
	}

	/**
	 * Delay before a course opens (none or after enrollment).
	 *
	 * @param mixed $value Value.
	 * @return array{mode: string, value: int, unit: string}
	 */
	public static function course_delay( mixed $value ): array {
		return DripRule::normalize( $value, [ DripRule::MODE_NONE, DripRule::MODE_ENROLLMENT ] );
	}

	/**
	 * Drip rule of a lesson.
	 *
	 * @param mixed $value Value.
	 * @return array{mode: string, value: int, unit: string}
	 */
	public static function drip_rule( mixed $value ): array {
		return DripRule::normalize( $value );
	}
}
