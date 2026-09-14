<?php
/**
 * Tests for the learner-facing quiz payload.
 *
 * @package LightweightPlugins\LMS
 */

declare(strict_types=1);

namespace LightweightPlugins\LMS\Tests\Unit\Quiz;

use LightweightPlugins\LMS\Quiz\QuizPublicView;
use PHPUnit\Framework\TestCase;

/**
 * @covers \LightweightPlugins\LMS\Quiz\QuizPublicView
 */
final class QuizPublicViewTest extends TestCase {

	public function test_no_correct_key_anywhere_in_payload(): void {
		$view = QuizPublicView::build( self::quiz(), 80.0 );

		$keys = [];
		array_walk_recursive(
			$view,
			static function ( $value, $key ) use ( &$keys ): void {
				$keys[] = $key;
			}
		);

		$this->assertNotContains( 'correct', $keys );
		$this->assertStringNotContainsString( '"correct"', (string) json_encode( $view ) );
	}

	public function test_keeps_prompts_option_texts_and_open_sample(): void {
		$view = QuizPublicView::build( self::quiz(), 80.0 );

		$this->assertSame(
			[
				[
					'id'      => 'q_single',
					'type'    => 'single',
					'prompt'  => 'Pick one',
					'options' => [ [ 'text' => 'A' ], [ 'text' => 'B' ] ],
				],
				[
					'id'     => 'q_bool',
					'type'   => 'boolean',
					'prompt' => 'True?',
				],
				[
					'id'     => 'q_open',
					'type'   => 'open',
					'prompt' => 'Explain',
					'sample' => 'Sample answer',
				],
			],
			$view['questions']
		);
	}

	public function test_resolves_threshold_and_shuffle_default(): void {
		$view = QuizPublicView::build( self::quiz(), 65.0 );

		$this->assertSame( 65.0, $view['pass_percentage'] );
		$this->assertFalse( $view['shuffle_options'] );
	}

	/**
	 * @return array<string, mixed>
	 */
	private static function quiz(): array {
		return [
			'questions' => [
				[
					'id'      => 'q_single',
					'type'    => 'single',
					'prompt'  => 'Pick one',
					'options' => [
						[
							'text'    => 'A',
							'correct' => false,
						],
						[
							'text'    => 'B',
							'correct' => true,
						],
					],
				],
				[
					'id'      => 'q_bool',
					'type'    => 'boolean',
					'prompt'  => 'True?',
					'correct' => true,
				],
				[
					'id'     => 'q_open',
					'type'   => 'open',
					'prompt' => 'Explain',
					'sample' => 'Sample answer',
				],
			],
		];
	}
}
