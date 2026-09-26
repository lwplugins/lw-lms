<?php
/**
 * Tests for the quiz attempts REST controller.
 *
 * @package LightweightPlugins\LMS
 */

declare(strict_types=1);

namespace LightweightPlugins\LMS\Tests\Unit\Api\Admin;

use Brain\Monkey\Functions;
use LightweightPlugins\LMS\Api\Admin\QuizAttemptsController;
use WP_Error;
use WP_REST_Request;

/**
 * @covers \LightweightPlugins\LMS\Api\Admin\QuizAttemptsController
 */
final class QuizAttemptsControllerTest extends AdminRestTestCase {

	/**
	 * Fake wpdb: one stored attempt (id 5), records DELETE queries.
	 *
	 * @return object
	 */
	private function db(): object {
		return new class() {
			public string $prefix  = 'wp_';
			public array $queries  = [];
			public function prepare( string $query, mixed ...$args ): array {
				return [ $query, $args ];
			}
			public function get_row( array $prepared ): ?object {
				return 5 === $prepared[1][0] ? (object) [
					'id'               => 5,
					'user_id'          => 7,
					'lesson_id'        => 0,
					'course_id'        => 0,
					'percentage'       => '50.00',
					'score'            => 1,
					'scored_questions' => 2,
					'passed'           => 0,
					'answers'          => '[{"id":"q1","type":"single","prompt":"P","given_text":"A","correct":false,"correct_text":"B"}]',
					'submitted_at'     => '2026-09-20 10:00:00',
				] : null;
			}
			public function query( array $prepared ): int {
				$this->queries[] = $prepared;
				return count( $prepared[1] );
			}
		};
	}

	protected function setUp(): void {
		parent::setUp();
		$GLOBALS['wpdb'] = $this->db();
		Functions\when( 'get_userdata' )->justReturn( false );
		Functions\when( 'mysql2date' )->alias( static fn ( $format, $date ) => $date );
	}

	/**
	 * @dataProvider provide_bad_ids
	 */
	public function test_bulk_delete_rejects_a_bad_selection( mixed $ids ): void {
		$result = ( new QuizAttemptsController() )->delete_many( new WP_REST_Request( [ 'ids' => $ids ], 'POST' ) );

		$this->assertInvalid( $result, [ 'ids' ] );
		$this->assertSame( [], $GLOBALS['wpdb']->queries );
	}

	public static function provide_bad_ids(): array {
		return [
			'none'      => [ [] ],
			'not list'  => [ 'all' ],
			'string id' => [ [ '5' ] ],
			'zero'      => [ [ 0 ] ],
			'too many'  => [ range( 1, QuizAttemptsController::MAX_DELETE + 1 ) ],
		];
	}

	public function test_bulk_delete_removes_each_id_once(): void {
		$data = $this->data( ( new QuizAttemptsController() )->delete_many( new WP_REST_Request( [ 'ids' => [ 5, 6, 5 ] ], 'POST' ) ) );

		$this->assertSame( [ 'deleted' => 2 ], $data );
		$this->assertSame( [ 5, 6 ], $GLOBALS['wpdb']->queries[0][1] );
	}

	public function test_an_unknown_attempt_is_not_found(): void {
		$result = ( new QuizAttemptsController() )->get_attempt( new WP_REST_Request( [ 'id' => '9' ] ) );

		$this->assertInstanceOf( WP_Error::class, $result );
		$this->assertSame( 404, $result->get_error_data()['status'] );
	}

	public function test_an_lms_manager_sees_the_answers_but_not_the_key(): void {
		$this->caps = [ 'manage_lms' => true ];

		$data = $this->data( ( new QuizAttemptsController() )->get_attempt( new WP_REST_Request( [ 'id' => '5' ] ) ) );

		$this->assertFalse( $data['showsAnswers'] );
		$this->assertSame( 'A', $data['answers'][0]['givenText'] );
		$this->assertArrayNotHasKey( 'correctText', $data['answers'][0] );
	}

	public function test_an_administrator_sees_the_answer_key(): void {
		$data = $this->data( ( new QuizAttemptsController() )->get_attempt( new WP_REST_Request( [ 'id' => '5' ] ) ) );

		$this->assertTrue( $data['showsAnswers'] );
		$this->assertSame( 'B', $data['answers'][0]['correctText'] );
	}
}
