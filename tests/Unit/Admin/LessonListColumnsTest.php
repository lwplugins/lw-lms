<?php
/**
 * Tests for the lesson list columns.
 *
 * @package LightweightPlugins\LMS
 */

declare(strict_types=1);

namespace LightweightPlugins\LMS\Tests\Unit\Admin;

use Brain\Monkey\Functions;
use LightweightPlugins\LMS\Admin\LessonListColumns;
use LightweightPlugins\LMS\Tests\Unit\MonkeyTestCase;

/**
 * Empty cells used a bare em dash and the quiz summary a middle dot; they
 * now read as words.
 *
 * @covers \LightweightPlugins\LMS\Admin\LessonListColumns
 */
final class LessonListColumnsTest extends MonkeyTestCase {

	protected function setUp(): void {
		parent::setUp();
		Functions\stubTranslationFunctions();
		Functions\stubEscapeFunctions();
	}

	/**
	 * @dataProvider provide_empty_cells
	 */
	public function test_empty_cells_read_as_words( string $column, string $expected ): void {
		Functions\when( 'get_post_meta' )->justReturn( '' );

		ob_start();
		LessonListColumns::render_column( $column, 10 );
		$html = (string) ob_get_clean();

		$this->assertSame( '<span class="lw-lms-muted">' . $expected . '</span>', $html );
	}

	public static function provide_empty_cells(): array {
		return [
			'no course' => [ 'lw_lms_course', 'No course' ],
			'no quiz'   => [ 'lw_lms_quiz', 'No quiz' ],
		];
	}

	public function test_quiz_summary_has_no_glyphs(): void {
		Functions\when( '_n' )->alias( static fn ( string $one, string $many, int $n ): string => 1 === $n ? $one : $many );
		Functions\when( 'get_option' )->justReturn( [] );
		Functions\when( 'wp_parse_args' )->alias(
			static fn ( mixed $args, array $defaults = [] ): array => array_merge( $defaults, (array) $args )
		);
		Functions\when( 'get_post_meta' )->justReturn(
			[
				'pass_percentage' => 70,
				'questions'       => [
					[
						'id'      => 'q1',
						'type'    => 'boolean',
						'prompt'  => 'P?',
						'correct' => true,
					],
					[
						'id'      => 'q2',
						'type'    => 'boolean',
						'prompt'  => 'Q?',
						'correct' => false,
					],
				],
			]
		);

		ob_start();
		LessonListColumns::render_column( 'lw_lms_quiz', 10 );

		$this->assertSame( '2 questions, pass mark 70%', ob_get_clean() );
	}
}
