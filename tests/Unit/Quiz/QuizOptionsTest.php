<?php
/**
 * Tests for option identity and shuffling.
 *
 * @package LightweightPlugins\LMS
 */

declare(strict_types=1);

namespace LightweightPlugins\LMS\Tests\Unit\Quiz;

use LightweightPlugins\LMS\Quiz\QuizOptions;
use PHPUnit\Framework\TestCase;

/**
 * @covers \LightweightPlugins\LMS\Quiz\QuizOptions
 */
final class QuizOptionsTest extends TestCase {

	public function test_falls_back_to_positional_ids_when_the_author_gave_none(): void {
		$this->assertSame( [ 'o0', 'o1', 'o2' ], QuizOptions::ids( self::options() ) );
	}

	public function test_author_supplied_ids_win(): void {
		$options       = self::options();
		$options[1]    = [ 'id' => 'opt_two' ] + $options[1];
		$options[2]    = [ 'id' => 'opt_three' ] + $options[2];

		$this->assertSame( [ 'o0', 'opt_two', 'opt_three' ], QuizOptions::ids( $options ) );
	}

	/**
	 * @dataProvider provide_answers
	 */
	public function test_resolves_an_answer_to_the_stored_option_index( mixed $answer, ?int $expected ): void {
		$options    = self::options();
		$options[2] = [ 'id' => 'opt_three' ] + $options[2];

		$this->assertSame( $expected, QuizOptions::resolve( $options, $answer ) );
	}

	public static function provide_answers(): array {
		return [
			'int index'            => [ 1, 1 ],
			'digit string index'   => [ '1', 1 ],
			'positional id'        => [ 'o1', 1 ],
			'author id'            => [ 'opt_three', 2 ],
			'unknown id'           => [ 'nope', null ],
			'out of range index'   => [ 9, null ],
			'negative index'       => [ -1, null ],
			'null'                 => [ null, null ],
			'bool'                 => [ true, null ],
			'array'                => [ [ 0 ], null ],
			// The third option carries an author id, so its positional fallback
			// no longer exists — the public payload never offers 'o2' either.
			'positional id of an option with an author id' => [ 'o2', null ],
		];
	}

	public function test_an_author_id_never_collides_with_another_options_positional_id(): void {
		// A hand-written id that looks positional must still point at its own option.
		$options    = self::options();
		$options[0] = [ 'id' => 'o2' ] + $options[0];

		$this->assertSame( 0, QuizOptions::resolve( $options, 'o2' ) );
	}

	public function test_shuffle_keeps_every_option_exactly_once(): void {
		$options = self::options();

		$shuffled = QuizOptions::shuffle( $options );

		$this->assertCount( 3, $shuffled );
		$this->assertEqualsCanonicalizing( $options, $shuffled );
	}

	public function test_shuffle_eventually_changes_the_order(): void {
		$options = self::options();

		$orders = [];
		for ( $i = 0; $i < 50; $i++ ) {
			$orders[] = implode( ',', array_column( QuizOptions::shuffle( $options ), 'text' ) );
		}

		$this->assertGreaterThan( 1, count( array_unique( $orders ) ) );
	}

	/**
	 * @return array<int, array<string, mixed>>
	 */
	private static function options(): array {
		return [
			[
				'text'    => 'A',
				'correct' => true,
			],
			[ 'text' => 'B' ],
			[
				'text'    => 'C',
				'correct' => false,
			],
		];
	}
}
