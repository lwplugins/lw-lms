<?php
/**
 * WP-CLI command: lw-lms lesson set-quiz.
 *
 * @package LightweightPlugins\LMS
 */

declare(strict_types=1);

namespace LightweightPlugins\LMS\CLI;

use LightweightPlugins\LMS\Quiz\InvalidQuizException;
use LightweightPlugins\LMS\Quiz\QuizNormalizer;
use LightweightPlugins\LMS\Quiz\QuizRepository;

/**
 * Set (replace) a lesson's quiz from a JSON document.
 */
final class LessonSetQuizCommand {

	/**
	 * Set a lesson's quiz from a JSON file.
	 *
	 * Replaces the whole quiz, so re-importing the same file (stable question
	 * ids) updates the quiz instead of duplicating questions.
	 *
	 * ## OPTIONS
	 *
	 * <lesson>
	 * : Lesson ID or slug.
	 *
	 * --file=<file>
	 * : Path to the quiz JSON. Use "-" to read from STDIN.
	 *
	 * ## EXAMPLES
	 *
	 *     wp lw-lms lesson set-quiz 99 --file=quiz.json
	 *     cat quiz.json | wp lw-lms lesson set-quiz intro-lesson --file=-
	 *
	 * @param array<int, string>    $args       Positional args. [0] = lesson ref.
	 * @param array<string, string> $assoc_args Associative args.
	 * @return void
	 */
	public function __invoke( array $args, array $assoc_args ): void {
		if ( empty( $args[0] ) ) {
			\WP_CLI::error( 'Lesson ID or slug is required.' );
		}

		if ( empty( $assoc_args['file'] ) ) {
			\WP_CLI::error( '--file is required.' );
		}

		$lesson_id = CliResolver::lesson_id( $args[0] );
		$raw       = self::read( (string) $assoc_args['file'] );

		try {
			$quiz = QuizNormalizer::normalize( json_decode( $raw, true, 512, JSON_THROW_ON_ERROR ) );
		} catch ( \JsonException $e ) {
			\WP_CLI::error( 'Invalid JSON: ' . $e->getMessage() );
			return;
		} catch ( InvalidQuizException $e ) {
			\WP_CLI::error( $e->getMessage() );
			return;
		}

		if ( ! QuizRepository::save( $lesson_id, $quiz ) ) {
			\WP_CLI::error( sprintf( 'Quiz could not be saved on lesson #%d.', $lesson_id ) );
		}

		\WP_CLI::success( sprintf( 'Quiz saved on lesson #%d (%d questions).', $lesson_id, count( $quiz['questions'] ) ) );
	}

	/**
	 * Read the JSON source.
	 *
	 * @param string $file File path or "-" for STDIN.
	 * @return string
	 */
	private static function read( string $file ): string {
		if ( '-' === $file ) {
			$raw = stream_get_contents( STDIN );
		} else {
			// phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- Local file given on the CLI.
			$raw = is_readable( $file ) ? file_get_contents( $file ) : false;
		}

		if ( false === $raw ) {
			\WP_CLI::error( sprintf( 'Cannot read file: %s', $file ) );
		}

		return $raw;
	}
}
