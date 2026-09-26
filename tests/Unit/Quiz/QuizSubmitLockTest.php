<?php
/**
 * Tests for the quiz submission lock.
 *
 * @package LightweightPlugins\LMS
 */

declare(strict_types=1);

namespace LightweightPlugins\LMS\Tests\Unit\Quiz;

use LightweightPlugins\LMS\Quiz\QuizSubmitLock;
use LightweightPlugins\LMS\Tests\Unit\MonkeyTestCase;

/**
 * Regression: the throttle was check-then-insert, so parallel submissions
 * all passed the cooldown and the daily cap.
 *
 * @covers \LightweightPlugins\LMS\Quiz\QuizSubmitLock
 */
final class QuizSubmitLockTest extends MonkeyTestCase {

	protected function tearDown(): void {
		unset( $GLOBALS['wpdb'] );
		parent::tearDown();
	}

	/**
	 * A $wpdb whose advisory locks behave like MySQL's: one holder per name.
	 *
	 * @param bool $supported Whether GET_LOCK exists (false = returns NULL).
	 * @return object
	 */
	private function wpdb( bool $supported = true ): object {
		return new class( $supported ) {
			public string $prefix = 'wp_';
			/** @var array<string, bool> */
			public array $held = [];
			public function __construct( private bool $supported ) {}
			public function prepare( string $query, mixed ...$args ): string {
				return str_replace( '%s', $args[0], str_replace( '%d', (string) ( $args[1] ?? '' ), $query ) );
			}
			public function get_var( string $query ): ?string {
				if ( ! $this->supported ) {
					return null;
				}
				preg_match( '/_LOCK\((lwlms_quiz_\w+)/', $query, $m );
				if ( str_starts_with( $query, 'SELECT RELEASE_LOCK' ) ) {
					unset( $this->held[ $m[1] ] );
					return '1';
				}
				if ( isset( $this->held[ $m[1] ] ) ) {
					return '0';
				}
				$this->held[ $m[1] ] = true;
				return '1';
			}
		};
	}

	public function test_a_parallel_submission_of_the_same_quiz_is_refused(): void {
		$GLOBALS['wpdb'] = $this->wpdb();

		$this->assertTrue( QuizSubmitLock::acquire( 7, 10 ) );
		$this->assertFalse( QuizSubmitLock::acquire( 7, 10 ) );
	}

	public function test_other_learners_and_lessons_are_not_blocked(): void {
		$GLOBALS['wpdb'] = $this->wpdb();
		QuizSubmitLock::acquire( 7, 10 );

		$this->assertTrue( QuizSubmitLock::acquire( 8, 10 ) );
		$this->assertTrue( QuizSubmitLock::acquire( 7, 11 ) );
	}

	public function test_release_lets_the_next_submission_in(): void {
		$GLOBALS['wpdb'] = $this->wpdb();
		QuizSubmitLock::acquire( 7, 10 );

		QuizSubmitLock::release( 7, 10 );

		$this->assertTrue( QuizSubmitLock::acquire( 7, 10 ) );
	}

	public function test_without_lock_support_submissions_are_not_blocked(): void {
		$GLOBALS['wpdb'] = $this->wpdb( false );

		$this->assertNull( QuizSubmitLock::acquire( 7, 10 ) );
	}

	public function test_name_is_scoped_and_fits_mysql_limit(): void {
		$GLOBALS['wpdb'] = $this->wpdb();

		$name = QuizSubmitLock::name( PHP_INT_MAX, PHP_INT_MAX );

		$this->assertMatchesRegularExpression( '/^lwlms_quiz_[0-9a-f]{12}_/', $name );
		$this->assertLessThanOrEqual( 64, strlen( $name ) );
	}
}
