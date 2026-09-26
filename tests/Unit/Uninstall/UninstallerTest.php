<?php
/**
 * Tests for the uninstall cleanup.
 *
 * @package LightweightPlugins\LMS
 */

declare(strict_types=1);

namespace LightweightPlugins\LMS\Tests\Unit\Uninstall;

use Brain\Monkey\Functions;
use LightweightPlugins\LMS\Tests\Unit\MonkeyTestCase;
use LightweightPlugins\LMS\Uninstall\Uninstaller;

/**
 * Regression: uninstall.php ignored delete_data_on_uninstall and always
 * dropped every enrollment, progress row and quiz attempt.
 *
 * @covers \LightweightPlugins\LMS\Uninstall\Uninstaller
 */
final class UninstallerTest extends MonkeyTestCase {

	protected function tearDown(): void {
		unset( $GLOBALS['wpdb'] );
		parent::tearDown();
	}

	/**
	 * A $wpdb double that records every query.
	 *
	 * @return object
	 */
	private function wpdb(): object {
		return new class() {
			public string $prefix   = 'wp_';
			public string $postmeta = 'wp_postmeta';
			public string $usermeta = 'wp_usermeta';
			/** @var array<int, string> */
			public array $queries = [];
			public function esc_like( string $v ): string {
				return addcslashes( $v, '_%\\' );
			}
			public function prepare( string $query, mixed ...$args ): string {
				return vsprintf( str_replace( '%s', "'%s'", $query ), $args );
			}
			public function query( string $query ): int {
				$this->queries[] = $query;
				return 1;
			}
		};
	}

	public function test_default_off_keeps_everything(): void {
		$GLOBALS['wpdb'] = $this->wpdb();
		Functions\when( 'get_option' )->justReturn( [ 'delete_data_on_uninstall' => false ] );
		Functions\expect( 'delete_option' )->never();

		$this->assertFalse( Uninstaller::run_site() );
		$this->assertSame( [], $GLOBALS['wpdb']->queries );
	}

	public function test_missing_options_keep_everything(): void {
		$this->assertFalse( Uninstaller::opted_in( false ) );
		$this->assertFalse( Uninstaller::opted_in( [] ) );
		$this->assertTrue( Uninstaller::opted_in( [ 'delete_data_on_uninstall' => true ] ) );
	}

	public function test_opt_in_deletes_tables_meta_options_and_caps(): void {
		$GLOBALS['wpdb'] = $this->wpdb();
		Functions\when( 'get_option' )->justReturn( [ 'delete_data_on_uninstall' => true ] );

		$removed = [];
		$role    = new class( $removed ) {
			public function __construct( private array &$removed ) {}
			public function remove_cap( string $cap ): void {
				$this->removed[] = $cap;
			}
		};
		Functions\when( 'wp_roles' )->justReturn( (object) [ 'role_objects' => [ 'administrator' => $role ] ] );

		$deleted = [];
		Functions\when( 'delete_option' )->alias(
			static function ( string $name ) use ( &$deleted ): bool {
				$deleted[] = $name;
				return true;
			}
		);

		$this->assertTrue( Uninstaller::run_site() );

		$sql = implode( "\n", $GLOBALS['wpdb']->queries );
		// The meta prefix is LIKE-escaped: "_" is not a wildcard any more.
		$this->assertStringContainsString( "DELETE FROM wp_postmeta WHERE meta_key LIKE '\\_lw\\_lms\\_%'", $sql );
		$this->assertStringContainsString( "LIKE '\\_lw\\_lms\\_quiz\\_%'", $sql );
		$this->assertStringContainsString( "LIKE '\\_lw\\_lms\\_course\\_start\\_%'", $sql );
		foreach ( [ 'lms_progress', 'lms_quiz_attempts', 'lms_completion_snapshots', 'lms_access' ] as $table ) {
			$this->assertStringContainsString( 'DROP TABLE IF EXISTS wp_' . $table, $sql );
		}
		$this->assertContains( 'manage_lms', $removed );
		$this->assertSame( [ 'lw_lms_options', 'lw_lms_db_version' ], $deleted );
		// Course and lesson posts are kept.
		$this->assertStringNotContainsString( 'wp_posts', $sql );
	}
}
