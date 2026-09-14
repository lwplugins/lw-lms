<?php
/**
 * WP-CLI command: lw-lms lesson get-quiz.
 *
 * @package LightweightPlugins\LMS
 */

declare(strict_types=1);

namespace LightweightPlugins\LMS\CLI;

use LightweightPlugins\LMS\Quiz\QuizRepository;

/**
 * Print a lesson's quiz (with answers).
 */
final class LessonGetQuizCommand {

	/**
	 * Print a lesson's quiz.
	 *
	 * The JSON output is the stored document, including correct answers, and
	 * can be fed back to `set-quiz` unchanged.
	 *
	 * ## OPTIONS
	 *
	 * <lesson>
	 * : Lesson ID or slug.
	 *
	 * [--format=<format>]
	 * : Output format.
	 * ---
	 * default: json
	 * options:
	 *   - json
	 *   - table
	 * ---
	 *
	 * ## EXAMPLES
	 *
	 *     wp lw-lms lesson get-quiz 99 > quiz.json
	 *     wp lw-lms lesson get-quiz intro-lesson --format=table
	 *
	 * @param array<int, string>    $args       Positional args. [0] = lesson ref.
	 * @param array<string, string> $assoc_args Associative args.
	 * @return void
	 */
	public function __invoke( array $args, array $assoc_args ): void {
		if ( empty( $args[0] ) ) {
			\WP_CLI::error( 'Lesson ID or slug is required.' );
		}

		$lesson_id = CliResolver::lesson_id( $args[0] );
		$quiz      = QuizRepository::get( $lesson_id );

		if ( null === $quiz ) {
			\WP_CLI::error( sprintf( 'Lesson #%d has no quiz.', $lesson_id ) );
		}

		if ( 'table' === ( $assoc_args['format'] ?? 'json' ) ) {
			$items = array_map(
				static fn ( array $question ): array => [
					'id'      => $question['id'],
					'type'    => $question['type'],
					'prompt'  => $question['prompt'],
					'options' => isset( $question['options'] ) ? count( $question['options'] ) : '',
				],
				$quiz['questions']
			);

			\WP_CLI\Utils\format_items( 'table', $items, [ 'id', 'type', 'prompt', 'options' ] );
			return;
		}

		\WP_CLI::line( (string) wp_json_encode( $quiz, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES ) );
	}
}
