<?php
/**
 * WP-CLI command: lw-lms lesson delete-quiz.
 *
 * @package LightweightPlugins\LMS
 */

declare(strict_types=1);

namespace LightweightPlugins\LMS\CLI;

use LightweightPlugins\LMS\Quiz\QuizRepository;

/**
 * Remove a lesson's quiz.
 */
final class LessonDeleteQuizCommand {

	/**
	 * Remove a lesson's quiz. Learners' last-attempt records are kept.
	 *
	 * ## OPTIONS
	 *
	 * <lesson>
	 * : Lesson ID or slug.
	 *
	 * ## EXAMPLES
	 *
	 *     wp lw-lms lesson delete-quiz 99
	 *
	 * @param array<int, string>    $args       Positional args. [0] = lesson ref.
	 * @param array<string, string> $assoc_args Associative args (unused).
	 * @return void
	 */
	public function __invoke( array $args, array $assoc_args ): void { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.FoundAfterLastUsed -- WP-CLI command signature.
		if ( empty( $args[0] ) ) {
			\WP_CLI::error( 'Lesson ID or slug is required.' );
		}

		$lesson_id = CliResolver::lesson_id( $args[0] );

		if ( null === QuizRepository::get( $lesson_id ) ) {
			\WP_CLI::warning( sprintf( 'Lesson #%d has no quiz.', $lesson_id ) );
			return;
		}

		QuizRepository::delete( $lesson_id );

		\WP_CLI::success( sprintf( 'Quiz removed from lesson #%d.', $lesson_id ) );
	}
}
