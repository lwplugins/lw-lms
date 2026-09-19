<?php
/**
 * Payload Extension.
 *
 * @package LightweightPlugins\LMS
 */

declare(strict_types=1);

namespace LightweightPlugins\LMS\Api\Transformers;

/**
 * Enforces the contract of the lms/v1 payload filters
 * (`lw_lms_rest_course_list_item`, `lw_lms_rest_course`, `lw_lms_rest_lesson`):
 * companion plugins may add top-level keys, the keys core wrote stay as core
 * wrote them.
 */
final class PayloadExtension {

	/**
	 * Merge a filtered payload back onto the core payload.
	 *
	 * Core keys win on conflict and cannot be removed; keys the filter added
	 * are appended after them. A non-array filter result (e.g. a callback
	 * that forgot to return) is ignored rather than breaking the response.
	 *
	 * @param array $core     Payload as built by the transformer.
	 * @param mixed $filtered Value returned by apply_filters().
	 * @return array
	 */
	public static function merge( array $core, mixed $filtered ): array {
		if ( ! is_array( $filtered ) ) {
			return $core;
		}

		return $core + $filtered;
	}
}
