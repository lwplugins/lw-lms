<?php
/**
 * Tests for the attachment lookup patterns.
 *
 * @package LightweightPlugins\LMS
 */

declare(strict_types=1);

namespace LightweightPlugins\LMS\Tests\Unit\Api;

use LightweightPlugins\LMS\Api\AttachmentMetaPattern;
use PHPUnit\Framework\TestCase;

/**
 * The download endpoint finds the course or lesson an attachment belongs to
 * by matching the stored meta. The meta is a serialized PHP array, so a
 * JSON-shaped pattern never matched it and every protected file was served
 * to anyone who asked.
 *
 * @covers \LightweightPlugins\LMS\Api\AttachmentMetaPattern
 */
final class AttachmentMetaPatternTest extends TestCase {

	public function test_matches_an_attachment_stored_with_an_integer_id(): void {
		$meta = serialize( // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.serialize_serialize -- Reproduces what WordPress stores.
			[
				[
					'id'          => 8,
					'title'       => 'Notes',
					'description' => '',
				],
			]
		);

		$this->assertTrue( self::meta_contains_attachment( $meta, 8 ) );
	}

	public function test_matches_an_attachment_stored_with_a_string_id(): void {
		// Rows written before the metabox cast the id.
		$meta = serialize( [ [ 'id' => '8' ] ] ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.serialize_serialize -- Reproduces what WordPress stores.

		$this->assertTrue( self::meta_contains_attachment( $meta, 8 ) );
	}

	public function test_does_not_match_a_different_attachment(): void {
		$meta = serialize( [ [ 'id' => 81 ] ] ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.serialize_serialize -- Reproduces what WordPress stores.

		$this->assertFalse( self::meta_contains_attachment( $meta, 8 ) );
	}

	public function test_does_not_match_another_field_that_holds_the_number(): void {
		$meta = serialize( // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.serialize_serialize -- Reproduces what WordPress stores.
			[
				[
					'id'    => 12,
					'order' => 8,
				],
			]
		);

		$this->assertFalse( self::meta_contains_attachment( $meta, 8 ) );
	}

	public function test_finds_an_attachment_among_several(): void {
		$meta = serialize( // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.serialize_serialize -- Reproduces what WordPress stores.
			[
				[ 'id' => 3 ],
				[ 'id' => 8 ],
				[ 'id' => 15 ],
			]
		);

		$this->assertTrue( self::meta_contains_attachment( $meta, 8 ) );
	}

	public function test_multi_digit_ids_are_measured_correctly(): void {
		$meta = serialize( [ [ 'id' => '1234' ] ] ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.serialize_serialize -- Reproduces what WordPress stores.

		$this->assertTrue( self::meta_contains_attachment( $meta, 1234 ) );
	}

	/**
	 * Does any of the fragments appear in the stored meta, the way the
	 * LIKE query asks the database?
	 *
	 * @param string $meta          Serialized meta value.
	 * @param int    $attachment_id Attachment ID.
	 * @return bool
	 */
	private static function meta_contains_attachment( string $meta, int $attachment_id ): bool {
		foreach ( AttachmentMetaPattern::fragments( $attachment_id ) as $fragment ) {
			if ( str_contains( $meta, $fragment ) ) {
				return true;
			}
		}

		return false;
	}
}
