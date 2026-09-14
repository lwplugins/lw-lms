<?php
/**
 * Tests for per-question aggregation.
 *
 * @package LightweightPlugins\LMS
 */

declare(strict_types=1);

namespace LightweightPlugins\LMS\Tests\Unit\Quiz;

use LightweightPlugins\LMS\Quiz\QuizQuestionStats;
use PHPUnit\Framework\TestCase;

/**
 * @covers \LightweightPlugins\LMS\Quiz\QuizQuestionStats
 */
final class QuizQuestionStatsTest extends TestCase {

	public function test_counts_correct_and_wrong_per_question(): void {
		$stats = QuizQuestionStats::aggregate(
			[
				[ self::scored( 'q1', true ), self::scored( 'q2', false ) ],
				[ self::scored( 'q1', false ), self::scored( 'q2', false ) ],
				[ self::scored( 'q1', true ), self::scored( 'q2', false ) ],
			]
		);

		$this->assertSame(
			[
				[ 'q1', 3, 2, 1, 66.7 ],
				[ 'q2', 3, 0, 3, 0.0 ],
			],
			array_map(
				static fn ( array $row ): array => [ $row['id'], $row['answered'], $row['correct'], $row['wrong'], $row['correct_ratio'] ],
				$stats
			)
		);
	}

	public function test_open_questions_are_counted_as_answered_or_skipped_only(): void {
		$stats = QuizQuestionStats::aggregate(
			[
				[ self::open( 'q_open', 'something' ) ],
				[ self::open( 'q_open', null ) ],
			]
		);

		$this->assertSame( 1, $stats[0]['answered'] );
		$this->assertSame( 1, $stats[0]['skipped'] );
		$this->assertSame( 0, $stats[0]['correct'] );
		$this->assertNull( $stats[0]['correct_ratio'] );
	}

	public function test_keeps_first_seen_question_order(): void {
		$stats = QuizQuestionStats::aggregate(
			[
				[ self::scored( 'q_b', true ), self::scored( 'q_a', true ) ],
				[ self::scored( 'q_a', true ), self::scored( 'q_c', true ) ],
			]
		);

		$this->assertSame( [ 'q_b', 'q_a', 'q_c' ], array_column( $stats, 'id' ) );
	}

	public function test_shows_the_newest_wording_of_a_reworded_question(): void {
		$stats = QuizQuestionStats::aggregate(
			[
				[ [ 'id' => 'q1', 'type' => 'single', 'prompt' => 'Old wording', 'correct' => true ] ],
				[ [ 'id' => 'q1', 'type' => 'single', 'prompt' => 'New wording', 'correct' => true ] ],
			]
		);

		$this->assertSame( 'New wording', $stats[0]['prompt'] );
	}

	public function test_ignores_entries_without_a_question_id(): void {
		$stats = QuizQuestionStats::aggregate( [ [ [ 'prompt' => 'Orphan', 'correct' => true ] ] ] );

		$this->assertSame( [], $stats );
	}

	public function test_empty_input_yields_no_rows(): void {
		$this->assertSame( [], QuizQuestionStats::aggregate( [] ) );
	}

	/**
	 * @return array<string, mixed>
	 */
	private static function scored( string $id, bool $correct ): array {
		return [
			'id'      => $id,
			'type'    => 'single',
			'prompt'  => 'Prompt of ' . $id,
			'correct' => $correct,
		];
	}

	/**
	 * @return array<string, mixed>
	 */
	private static function open( string $id, ?string $given ): array {
		$entry = [
			'id'     => $id,
			'type'   => 'open',
			'prompt' => 'Open ' . $id,
			'scored' => false,
		];

		if ( null !== $given ) {
			$entry['given_text'] = $given;
		}

		return $entry;
	}
}
