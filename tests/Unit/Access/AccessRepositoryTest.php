<?php
/**
 * Tests for access grants and revocations.
 *
 * @package LightweightPlugins\LMS
 */

declare(strict_types=1);

namespace LightweightPlugins\LMS\Tests\Unit\Access;

use Brain\Monkey\Functions;
use LightweightPlugins\LMS\Access\AccessRepository;
use LightweightPlugins\LMS\Tests\Unit\MonkeyTestCase;

/**
 * Regression: manual/free grants store source_id NULL but were looked up
 * with `source_id = 0`, so every grant added a row; revoke() flipped only the
 * first active row and the learner kept access.
 *
 * @covers \LightweightPlugins\LMS\Access\AccessRepository
 */
final class AccessRepositoryTest extends MonkeyTestCase {

	protected function setUp(): void {
		parent::setUp();
		Functions\when( 'apply_filters' )->returnArg( 2 );
		Functions\when( 'current_time' )->justReturn( '2026-09-26 12:00:00' );
	}

	protected function tearDown(): void {
		unset( $GLOBALS['wpdb'] );
		parent::tearDown();
	}

	/**
	 * In-memory access table.
	 *
	 * @param array<int, array<string, mixed>> $rows Rows by id.
	 * @return object
	 */
	private function table( array $rows = [] ): object {
		return new class( $rows ) {
			public string $prefix = 'wp_';
			public string $last   = '';
			public function __construct( public array $rows ) {}
			public function prepare( string $query, mixed ...$args ): array {
				return [ $query, $args ];
			}
			public function get_var( array $prepared ): mixed {
				[ $query, $args ] = $prepared;
				$this->last       = $query;
				$by_source_id     = ! str_contains( $query, 'source_id IS NULL' );
				[ $user, $course, $key ] = $args;
				foreach ( $this->rows as $id => $row ) {
					$match = $by_source_id ? $row['source_id'] === $key : ( null === $row['source_id'] && $row['source'] === $key );
					if ( $row['user_id'] === $user && $row['course_id'] === $course && $match ) {
						return (string) $id;
					}
				}
				return null;
			}
			public function get_results( array $prepared ): array {
				[ , $args ]      = $prepared;
				[ $user, $course ] = $args;
				$found           = [];
				foreach ( $this->rows as $id => $row ) {
					if ( $row['user_id'] === $user && $row['course_id'] === $course && 'active' === $row['status'] ) {
						$found[] = (object) [
							'id'     => $id,
							'source' => $row['source'],
						];
					}
				}
				return $found;
			}
			public function insert( string $table, array $data ): int {
				$this->rows[ count( $this->rows ) + 1 ] = $data;
				return 1;
			}
			public function update( string $table, array $data, array $where ): int {
				$id = (int) $where['id'];
				if ( ! isset( $this->rows[ $id ] ) || ( isset( $where['status'] ) && $this->rows[ $id ]['status'] !== $where['status'] ) ) {
					return 0;
				}
				$this->rows[ $id ] = array_merge( $this->rows[ $id ], $data );
				return 1;
			}
		};
	}

	public function test_manual_grant_twice_keeps_one_row(): void {
		Functions\when( 'do_action' )->justReturn( null );
		$GLOBALS['wpdb'] = $this->table();

		AccessRepository::grant( 7, 42, 'manual' );
		AccessRepository::grant( 7, 42, 'manual' );

		$this->assertCount( 1, $GLOBALS['wpdb']->rows );
		$this->assertStringContainsString( 'source_id IS NULL', $GLOBALS['wpdb']->last );
		$this->assertStringContainsString( 'source = %s', $GLOBALS['wpdb']->last );
	}

	public function test_order_grant_twice_keeps_one_row(): void {
		Functions\when( 'do_action' )->justReturn( null );
		$GLOBALS['wpdb'] = $this->table();

		AccessRepository::grant( 7, 42, 'woocommerce', 555 );
		AccessRepository::grant( 7, 42, 'woocommerce', 555 );

		$this->assertCount( 1, $GLOBALS['wpdb']->rows );
	}

	public function test_manual_grant_does_not_reuse_another_sources_row(): void {
		Functions\when( 'do_action' )->justReturn( null );
		$GLOBALS['wpdb'] = $this->table(
			[
				1 => [
					'user_id'   => 7,
					'course_id' => 42,
					'source'    => 'free',
					'source_id' => null,
					'status'    => 'active',
				],
			]
		);

		AccessRepository::grant( 7, 42, 'manual' );

		$this->assertCount( 2, $GLOBALS['wpdb']->rows );
	}

	public function test_revoke_flips_every_active_row_and_reports_each_source(): void {
		$sources = [];
		Functions\when( 'do_action' )->alias(
			static function ( string $hook, int $user, int $course, string $source ) use ( &$sources ): void {
				$sources[] = $source;
			}
		);
		$row             = static fn ( string $source, string $status = 'active' ): array => [
			'user_id'   => 7,
			'course_id' => 42,
			'source'    => $source,
			'source_id' => null,
			'status'    => $status,
		];
		$GLOBALS['wpdb'] = $this->table(
			[
				1 => $row( 'woocommerce' ),
				2 => $row( 'manual' ),
				3 => $row( 'manual', 'revoked' ),
			]
		);

		$this->assertTrue( AccessRepository::revoke( 7, 42 ) );
		$this->assertSame( 'revoked', $GLOBALS['wpdb']->rows[1]['status'] );
		$this->assertSame( 'revoked', $GLOBALS['wpdb']->rows[2]['status'] );
		$this->assertSame( [ 'woocommerce', 'manual' ], $sources );
	}

	public function test_revoke_without_active_rows_returns_false(): void {
		Functions\expect( 'do_action' )->never();
		$GLOBALS['wpdb'] = $this->table();

		$this->assertFalse( AccessRepository::revoke( 7, 42 ) );
	}
}
