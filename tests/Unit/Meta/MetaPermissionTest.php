<?php
/**
 * Tests for the meta value permission checks.
 *
 * @package LightweightPlugins\LMS
 */

declare(strict_types=1);

namespace LightweightPlugins\LMS\Tests\Unit\Meta;

use Brain\Monkey\Functions;
use LightweightPlugins\LMS\Meta\MetaPermission;
use LightweightPlugins\LMS\Tests\Unit\MonkeyTestCase;

/**
 * Attachment IDs a user may list on a course or lesson.
 *
 * @covers \LightweightPlugins\LMS\Meta\MetaPermission
 */
final class MetaPermissionTest extends MonkeyTestCase {

	protected function setUp(): void {
		parent::setUp();
		// The user may edit attachment 8 and course 12; course 30 is someone else's.
		Functions\when( 'current_user_can' )->alias(
			static fn ( string $cap, int $id = 0 ): bool => 'edit_post' === $cap && in_array( $id, [ 8, 12 ], true )
		);
		Functions\when( 'maybe_unserialize' )->alias( static fn ( string $v ): mixed => unserialize( $v ) ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.serialize_unserialize
	}

	protected function tearDown(): void {
		unset( $GLOBALS['wpdb'] );
		parent::tearDown();
	}

	public function test_own_attachment_can_be_attached(): void {
		$this->owners( [] );

		$this->assertTrue( MetaPermission::can_attach( 8 ) );
	}

	public function test_foreign_attachment_on_no_editable_course_is_refused(): void {
		$this->owners( [ 30 => 9 ] );

		$this->assertFalse( MetaPermission::can_attach( 9 ) );
	}

	public function test_foreign_attachment_already_on_an_editable_course_is_kept(): void {
		$this->owners( [ 12 => 9 ] );

		$this->assertTrue( MetaPermission::can_attach( 9 ) );
	}

	public function test_edit_is_checked_on_the_given_post(): void {
		$this->assertTrue( MetaPermission::can_edit( 12 ) );
		$this->assertFalse( MetaPermission::can_edit( 30 ) );
	}

	/**
	 * @runInSeparateProcess
	 * @preserveGlobalState disabled
	 */
	public function test_wp_cli_skips_the_checks(): void {
		define( 'WP_CLI', true );
		$this->owners( [] );

		$this->assertTrue( MetaPermission::can_edit( 30 ) );
		$this->assertTrue( MetaPermission::can_attach( 9 ) );
	}

	/**
	 * Stub $wpdb so AttachmentOwners::find() sees these owner rows.
	 *
	 * @param array<int, int> $owners Owner post ID => listed attachment ID.
	 * @return void
	 */
	private function owners( array $owners ): void {
		$rows = [];

		foreach ( $owners as $post_id => $attachment_id ) {
			$rows[] = (object) [
				'post_id'    => (string) $post_id,
				'meta_value' => serialize( [ [ 'id' => $attachment_id ] ] ), // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.serialize_serialize
			];
		}

		$GLOBALS['wpdb'] = new class( $rows ) { // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited
			public string $postmeta = 'wp_postmeta';
			public string $posts    = 'wp_posts';
			public function __construct( private array $rows ) {}
			public function esc_like( string $v ): string {
				return $v;
			}
			public function prepare( string $query, mixed ...$args ): string {
				return $query;
			}
			public function get_results( string $query ): array {
				return $this->rows;
			}
		};
	}
}
