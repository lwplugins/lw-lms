<?php
/**
 * Option identity and ordering for single-choice questions.
 *
 * @package LightweightPlugins\LMS
 */

declare(strict_types=1);

namespace LightweightPlugins\LMS\Quiz;

/**
 * Answers travel as option ids, so the order the learner sees never has to
 * match the stored order. Pure: no WordPress calls.
 *
 * An option may carry an author-supplied `id`; otherwise it gets the
 * positional fallback `o{index}`. Author ids are resolved first, so a
 * hand-written id that happens to look positional still points at its own
 * option.
 */
final class QuizOptions {

	/**
	 * Ids of a question's options, in stored order.
	 *
	 * @param array<int, array<string, mixed>> $options Options.
	 * @return array<int, string>
	 */
	public static function ids( array $options ): array {
		$ids = [];

		foreach ( $options as $index => $option ) {
			$ids[] = isset( $option['id'] ) ? (string) $option['id'] : 'o' . $index;
		}

		return $ids;
	}

	/**
	 * Resolve a submitted answer to an index into the stored options.
	 *
	 * Accepts an option id, or a 0-based index into the stored order (int or
	 * digit string) for clients written against 1.8.0.
	 *
	 * @param array<int, array<string, mixed>> $options Options.
	 * @param mixed                            $answer  Submitted answer.
	 * @return int|null Index, or null when the answer matches no option.
	 */
	public static function resolve( array $options, mixed $answer ): ?int {
		if ( is_string( $answer ) ) {
			$explicit = array_search( $answer, self::author_ids( $options ), true );

			if ( is_int( $explicit ) ) {
				return $explicit;
			}

			$positional = array_search( $answer, self::ids( $options ), true );

			if ( is_int( $positional ) ) {
				return $positional;
			}
		}

		$index = null;

		if ( is_int( $answer ) ) {
			$index = $answer;
		} elseif ( is_string( $answer ) && ctype_digit( $answer ) ) {
			$index = (int) $answer;
		}

		return null !== $index && isset( $options[ $index ] ) ? $index : null;
	}

	/**
	 * Randomize option order (Fisher-Yates on a copy).
	 *
	 * @param array<int, array<string, mixed>> $options Options.
	 * @return array<int, array<string, mixed>>
	 */
	public static function shuffle( array $options ): array {
		$shuffled = array_values( $options );

		for ( $i = count( $shuffled ) - 1; $i > 0; $i-- ) {
			$j = random_int( 0, $i );

			[ $shuffled[ $i ], $shuffled[ $j ] ] = [ $shuffled[ $j ], $shuffled[ $i ] ];
		}

		return $shuffled;
	}

	/**
	 * Author-supplied ids only, index-aligned with the stored options.
	 *
	 * @param array<int, array<string, mixed>> $options Options.
	 * @return array<int, string|null>
	 */
	private static function author_ids( array $options ): array {
		return array_map(
			static fn ( array $option ): ?string => isset( $option['id'] ) ? (string) $option['id'] : null,
			$options
		);
	}
}
