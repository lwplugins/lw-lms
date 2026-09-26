<?php
/**
 * Tests for the quiz attempt CSV.
 *
 * @package LightweightPlugins\LMS
 */

declare(strict_types=1);

namespace LightweightPlugins\LMS\Tests\Unit\Api\Admin\QuizAttempts;

use LightweightPlugins\LMS\Api\Admin\QuizAttempts\AttemptCsv;
use PHPUnit\Framework\TestCase;

/**
 * @covers \LightweightPlugins\LMS\Api\Admin\QuizAttempts\AttemptCsv
 */
final class AttemptCsvTest extends TestCase {

	public function test_builds_a_header_and_one_line_per_attempt_without_email(): void {
		$csv = AttemptCsv::build(
			[ 'ID', 'Submitted' ],
			[
				[
					'id'              => 5,
					'submittedAt'     => '2026-09-20 10:00:00',
					'userId'          => 7,
					'user'            => [
						'name'  => 'Kiss "Anna"',
						'email' => 'anna@example.test',
					],
					'lesson'          => [ 'title' => 'Intro' ],
					'course'          => null,
					'score'           => 3,
					'scoredQuestions' => 4,
					'percentage'      => 75.0,
					'passed'          => false,
				],
			],
			[ 'Yes', 'No' ]
		);

		$this->assertStringStartsWith( "\xEF\xBB\xBF\"ID\",\"Submitted\"\r\n", $csv );
		$this->assertStringContainsString( '"5","2026-09-20 10:00:00","7","Kiss ""Anna""","Intro","","3","4","75.0","No"', $csv );
		$this->assertStringNotContainsString( 'anna@example.test', $csv );
	}

	/**
	 * @dataProvider provide_formulas
	 */
	public function test_a_cell_a_spreadsheet_would_run_is_neutralised( string $value ): void {
		$this->assertSame( '"\'' . $value . '"', AttemptCsv::cell( $value ) );
	}

	public static function provide_formulas(): array {
		return [
			'equals' => [ '=HYPERLINK(1)' ],
			'plus'   => [ '+1' ],
			'minus'  => [ '-1' ],
			'at'     => [ '@SUM(A1)' ],
		];
	}

	public function test_plain_text_stays_as_is(): void {
		$this->assertSame( '"Lesson 1"', AttemptCsv::cell( 'Lesson 1' ) );
	}
}
