<?php
/**
 * Tests for the enrollment list query builder.
 *
 * @package LightweightPlugins\LMS
 */

declare(strict_types=1);

namespace LightweightPlugins\LMS\Tests\Unit\Access;

use LightweightPlugins\LMS\Access\EnrollmentList;
use LightweightPlugins\LMS\Tests\Unit\MonkeyTestCase;

/**
 * @covers \LightweightPlugins\LMS\Access\EnrollmentList
 */
final class EnrollmentListTest extends MonkeyTestCase {

	private const NOW = '2026-09-26 10:00:00';

	protected function setUp(): void {
		parent::setUp();
		$GLOBALS['wpdb'] = (object) [ 'prefix' => 'wp_' ];
	}

	protected function tearDown(): void {
		unset( $GLOBALS['wpdb'] );
		parent::tearDown();
	}

	public function test_without_filters_matches_every_row(): void {
		[ $from, $where, $args ] = EnrollmentList::where( [], self::NOW, 'wp_users', '' );

		$this->assertSame( 'wp_lms_access a', $from );
		$this->assertSame( '1=1', $where );
		$this->assertSame( [], $args );
	}

	public function test_every_value_goes_through_a_placeholder(): void {
		[ $from, $where, $args ] = EnrollmentList::where(
			[
				'course' => 5,
				'user'   => 9,
				'source' => "manual' OR 1=1 --",
				'status' => 'active',
			],
			self::NOW,
			'wp_users',
			'ann'
		);

		$this->assertStringContainsString( 'INNER JOIN wp_users u ON u.ID = a.user_id', $from );
		$this->assertStringNotContainsString( 'OR 1=1', $where );
		$this->assertSame( [ 5, 9, "manual' OR 1=1 --", self::NOW, '%ann%', '%ann%', '%ann%' ], $args );
	}

	/**
	 * @dataProvider provide_statuses
	 */
	public function test_status_filter_clauses( string $status, string $clause, array $args ): void {
		[ , $where, $values ] = EnrollmentList::where( [ 'status' => $status ], self::NOW, 'wp_users', '' );

		$this->assertStringContainsString( $clause, $where );
		$this->assertSame( $args, $values );
	}

	public static function provide_statuses(): array {
		return [
			'active'  => [ 'active', "a.status = 'active' AND (a.expires_at IS NULL OR a.expires_at > %s)", [ self::NOW ] ],
			'expired' => [ 'expired', 'a.expires_at IS NOT NULL AND a.expires_at <= %s', [ self::NOW ] ],
			'revoked' => [ 'revoked', "a.status = 'revoked'", [] ],
		];
	}

	/**
	 * @dataProvider provide_rows
	 */
	public function test_status_of_a_row( string $status, ?string $expires, string $expected ): void {
		$row = (object) [
			'status'     => $status,
			'expires_at' => $expires,
		];

		$this->assertSame( $expected, EnrollmentList::status_of( $row, self::NOW ) );
	}

	public static function provide_rows(): array {
		return [
			'lifetime'        => [ 'active', null, 'active' ],
			'ends later'      => [ 'active', '2026-10-01 00:00:00', 'active' ],
			'ended'           => [ 'active', '2026-09-01 00:00:00', 'expired' ],
			'revoked'         => [ 'revoked', null, 'revoked' ],
			'revoked, ended'  => [ 'revoked', '2026-09-01 00:00:00', 'revoked' ],
		];
	}
}
