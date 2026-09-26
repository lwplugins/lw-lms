<?php
/**
 * Tests for the quiz attempt throttle.
 *
 * @package LightweightPlugins\LMS
 */

declare(strict_types=1);

namespace LightweightPlugins\LMS\Tests\Unit\Quiz;

use Brain\Monkey\Functions;
use LightweightPlugins\LMS\Quiz\QuizThrottle;
use LightweightPlugins\LMS\Tests\Unit\MonkeyTestCase;

/**
 * Regression: quiz submissions had no limit at all.
 *
 * @covers \LightweightPlugins\LMS\Quiz\QuizThrottle
 */
final class QuizThrottleTest extends MonkeyTestCase {

	private const NOW = '2026-09-26 12:00:00';

	public function test_first_attempt_is_allowed(): void {
		$this->assertNull( QuizThrottle::retry_after( null, 0, null, self::NOW, 15, 20 ) );
	}

	public function test_cooldown_blocks_a_quick_retry_and_reports_the_wait(): void {
		$this->assertSame( 10, QuizThrottle::retry_after( '2026-09-26 11:59:55', 1, '2026-09-26 11:59:55', self::NOW, 15, 20 ) );
	}

	public function test_retry_after_the_cooldown_is_allowed(): void {
		$this->assertNull( QuizThrottle::retry_after( '2026-09-26 11:59:40', 1, '2026-09-26 11:59:40', self::NOW, 15, 20 ) );
	}

	public function test_daily_cap_waits_until_the_oldest_attempt_leaves_the_window(): void {
		$this->assertSame( 3600, QuizThrottle::retry_after( '2026-09-26 11:00:00', 20, '2026-09-25 13:00:00', self::NOW, 15, 20 ) );
	}

	public function test_zero_turns_limits_off(): void {
		$this->assertNull( QuizThrottle::retry_after( '2026-09-26 11:59:59', 500, '2026-09-26 00:00:00', self::NOW, 0, 0 ) );
	}

	public function test_check_returns_a_429_error_while_cooling_down(): void {
		Functions\stubTranslationFunctions();
		Functions\when( 'apply_filters' )->returnArg( 2 );
		Functions\when( 'current_time' )->justReturn( self::NOW );
		Functions\when( 'get_user_meta' )->justReturn( [ 'submitted_at' => '2026-09-26 11:59:58' ] );
		$GLOBALS['wpdb'] = new class() {
			public string $prefix = 'wp_';
			public function prepare( string $query, mixed ...$args ): string {
				return $query;
			}
			public function get_row( string $query ): object {
				return (object) [
					'attempts' => 1,
					'oldest'   => '2026-09-26 11:59:58',
				];
			}
		};

		$error = QuizThrottle::check( 7, 10 );
		unset( $GLOBALS['wpdb'] );

		$this->assertInstanceOf( \WP_Error::class, $error );
		$this->assertSame( 'quiz_rate_limited', $error->get_error_code() );
		$this->assertSame( 429, $error->get_error_data()['status'] );
		$this->assertSame( 13, $error->get_error_data()['retry_after'] );
	}
}
