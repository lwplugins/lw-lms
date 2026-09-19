<?php
/**
 * Section sanitizer.
 *
 * @package LightweightPlugins\LMS
 */

declare(strict_types=1);

namespace LightweightPlugins\LMS\Admin\Metaboxes;

use LightweightPlugins\LMS\Drip\DripRule;
use LightweightPlugins\LMS\Drip\DripSettings;

/**
 * Cleans the section list the course builder posts as JSON: keeps the fields
 * a section is made of, normalizes its drip rule and drops anything else.
 */
final class SectionSanitizer {

	/**
	 * Sanitize a submitted section list.
	 *
	 * @param array<int, mixed> $sections Decoded sections.
	 * @return array<int, array<string, mixed>>
	 */
	public static function sanitize( array $sections ): array {
		$clean = [];

		foreach ( $sections as $section ) {
			if ( ! is_array( $section ) ) {
				continue;
			}

			$id = self::section_id( $section['id'] ?? '' );

			if ( '' === $id ) {
				continue;
			}

			$clean[] = [
				'id'                           => $id,
				'title'                        => sanitize_text_field( (string) ( $section['title'] ?? '' ) ),
				'description'                  => sanitize_text_field( (string) ( $section['description'] ?? '' ) ),
				'order'                        => (int) ( $section['order'] ?? 0 ),
				DripSettings::SECTION_RULE_KEY => DripRule::normalize( $section[ DripSettings::SECTION_RULE_KEY ] ?? [] ),
			];
		}

		return $clean;
	}

	/**
	 * Keep a section id to the characters ids are made of.
	 *
	 * Case is preserved: lessons point at this id by value, so rewriting it
	 * would detach them from their section.
	 *
	 * @param mixed $raw Submitted id.
	 * @return string
	 */
	private static function section_id( mixed $raw ): string {
		if ( ! is_string( $raw ) && ! is_numeric( $raw ) ) {
			return '';
		}

		return (string) preg_replace( '/[^A-Za-z0-9_\-]/', '', (string) $raw );
	}
}
