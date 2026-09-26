<?php
/**
 * Tests for the personal data exporter and eraser.
 *
 * @package LightweightPlugins\LMS
 */

declare(strict_types=1);

namespace LightweightPlugins\LMS\Tests\Unit\Privacy;

use Brain\Monkey\Functions;
use LightweightPlugins\LMS\Privacy\PersonalDataEraser;
use LightweightPlugins\LMS\Privacy\PersonalDataExporter;
use LightweightPlugins\LMS\Tests\Unit\MonkeyTestCase;

/**
 * Regression: the plugin had no personal data exporter or eraser, and a
 * deleted user's enrollment, progress and quiz rows stayed forever.
 *
 * @covers \LightweightPlugins\LMS\Privacy\PersonalDataExporter
 * @covers \LightweightPlugins\LMS\Privacy\PersonalDataEraser
 * @covers \LightweightPlugins\LMS\Privacy\PersonalDataQueries
 */
final class PersonalDataTest extends MonkeyTestCase {

	protected function setUp(): void {
		parent::setUp();
		Functions\stubTranslationFunctions();
		Functions\when( 'get_user_by' )->justReturn( (object) [ 'ID' => 7 ] );
		Functions\when( 'get_the_title' )->alias( static fn ( int $id ): string => 'Post ' . $id );
		Functions\when( 'current_time' )->justReturn( '2026-09-26 12:00:00' );
		Functions\when( 'wp_cache_delete' )->justReturn( true );
		Functions\when( 'apply_filters' )->returnArg( 2 );
	}

	protected function tearDown(): void {
		unset( $GLOBALS['wpdb'] );
		parent::tearDown();
	}

	/**
	 * In-memory tables keyed by name suffix.
	 *
	 * @param array<string, array<int, array<string, mixed>>> $tables Rows.
	 * @return object
	 */
	private function db( array $tables ): object {
		return new class( $tables ) {
			public string $prefix   = 'wp_';
			public string $usermeta = 'wp_usermeta';
			/** @var array<int, string> */
			public array $deletes = [];
			public function __construct( public array $tables ) {}
			public function esc_like( string $v ): string {
				return $v;
			}
			public function prepare( string $query, mixed ...$args ): string {
				return vsprintf( str_replace( '%s', "'%s'", $query ), $args );
			}
			public function get_results( string $query ): array {
				preg_match( '/FROM wp_(\w+)/', $query, $m );
				return array_map( static fn ( array $r ): object => (object) $r, $this->tables[ $m[1] ] ?? [] );
			}
			public function delete( string $table, array $where ): int {
				$name                  = substr( $table, 3 );
				$count                 = count( $this->tables[ $name ] ?? [] );
				$this->tables[ $name ] = [];
				$this->deletes[]       = $name;
				return $count;
			}
			public function query( string $query ): int {
				$this->deletes[] = $query;
				if ( str_starts_with( $query, 'DELETE FROM wp_lms_access' ) ) {
					$before                       = count( $this->tables['lms_access'] );
					$this->tables['lms_access'] = array_values( array_filter( $this->tables['lms_access'], static fn ( array $r ): bool => 'active' === $r['status'] ) );
					return $before - count( $this->tables['lms_access'] );
				}
				return 0;
			}
		};
	}

	private function site(): object {
		return $this->db(
			[
				'lms_access'        => [
					[
						'id'         => 1,
						'course_id'  => 42,
						'source'     => 'woocommerce',
						'status'     => 'active',
						'granted_at' => '2026-01-01 10:00:00',
						'expires_at' => null,
					],
					[
						'id'         => 2,
						'course_id'  => 43,
						'source'     => 'manual',
						'status'     => 'revoked',
						'granted_at' => '2026-01-01 10:00:00',
						'expires_at' => null,
					],
				],
				'lms_progress'      => [
					[
						'id'           => 5,
						'course_id'    => 42,
						'lesson_id'    => 10,
						'status'       => 'completed',
						'completed_at' => '2026-02-01 10:00:00',
					],
				],
				'lms_quiz_attempts' => [
					[
						'id'           => 9,
						'lesson_id'    => 10,
						'submitted_at' => '2026-02-01 10:00:00',
						'percentage'   => '50.00',
						'passed'       => 0,
						'answers'      => '[{"id":"q1","given_text":"my answer"}]',
					],
				],
			]
		);
	}

	public function test_export_lists_enrollments_progress_and_quiz_answers(): void {
		$GLOBALS['wpdb'] = $this->site();

		$export = PersonalDataExporter::export( 'a@example.test', 1 );
		$groups = array_column( $export['data'], 'group_id' );

		$this->assertTrue( $export['done'] );
		$this->assertSame( [ 'lw-lms-enrollments', 'lw-lms-enrollments', 'lw-lms-progress', 'lw-lms-quiz-attempts' ], $groups );
		$this->assertStringContainsString( 'my answer', (string) json_encode( $export['data'][3] ) );
		$this->assertSame( 'Post 42 (#42)', $export['data'][0]['data'][0]['value'] );
	}

	public function test_export_of_an_unknown_email_is_empty(): void {
		Functions\when( 'get_user_by' )->justReturn( false );

		$this->assertSame(
			[
				'data' => [],
				'done' => true,
			],
			PersonalDataExporter::export( 'nobody@example.test', 1 )
		);
	}

	public function test_erase_keeps_active_enrollments_by_default(): void {
		$GLOBALS['wpdb'] = $this->site();

		$result = PersonalDataEraser::erase( 'a@example.test', 1 );

		$this->assertTrue( $result['items_removed'] );
		$this->assertTrue( $result['items_retained'] );
		$this->assertCount( 1, $result['messages'] );
		$this->assertSame( [], $GLOBALS['wpdb']->tables['lms_progress'] );
		$this->assertSame( [], $GLOBALS['wpdb']->tables['lms_quiz_attempts'] );
		$this->assertSame( [ 1 ], array_column( $GLOBALS['wpdb']->tables['lms_access'], 'id' ) );
	}

	public function test_deleting_a_user_removes_every_lms_row(): void {
		Functions\when( 'is_multisite' )->justReturn( false );
		$GLOBALS['wpdb'] = $this->site();

		PersonalDataEraser::on_user_deleted( 7 );

		$this->assertSame( [], $GLOBALS['wpdb']->tables['lms_access'] );
		$this->assertSame( [], $GLOBALS['wpdb']->tables['lms_progress'] );
		$this->assertSame( [], $GLOBALS['wpdb']->tables['lms_quiz_attempts'] );
	}
}
