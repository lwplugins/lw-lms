<?php
/**
 * Quiz document validation.
 *
 * @package LightweightPlugins\LMS
 */

declare(strict_types=1);

namespace LightweightPlugins\LMS\Quiz;

/**
 * Validates a decoded quiz JSON document and returns its canonical form.
 *
 * Strict by design: unknown keys are rejected (with their path) instead of
 * being dropped, so whatever is accepted comes back identical from get-quiz,
 * and importer typos fail loudly instead of silently losing data.
 */
final class QuizNormalizer {

	/**
	 * Allowed root keys, in canonical output order.
	 */
	private const ROOT_KEYS = [ 'pass_percentage', 'shuffle_options', 'questions' ];

	/**
	 * Allowed keys per question type, in canonical output order.
	 */
	private const QUESTION_KEYS = [
		'single'  => [ 'id', 'type', 'prompt', 'options' ],
		'boolean' => [ 'id', 'type', 'prompt', 'correct' ],
		'open'    => [ 'id', 'type', 'prompt', 'sample' ],
	];

	/**
	 * Allowed keys of a single-choice option.
	 */
	private const OPTION_KEYS = [ 'id', 'text', 'correct' ];

	/**
	 * Question and option id: starts with a letter, so PHP never turns it into
	 * an int key.
	 */
	private const ID_PATTERN = '/^[A-Za-z][A-Za-z0-9_-]{0,63}$/';

	/**
	 * Validate a decoded quiz document and return it in canonical form.
	 *
	 * @param mixed $data Decoded JSON (associative arrays).
	 * @return array<string, mixed>
	 * @throws InvalidQuizException When the document is invalid.
	 */
	public static function normalize( mixed $data ): array {
		$root = self::object( $data, 'quiz' );
		self::assert_keys( $root, self::ROOT_KEYS, 'quiz' );

		$quiz = [];

		if ( array_key_exists( 'pass_percentage', $root ) ) {
			$value = $root['pass_percentage'];
			if ( ! ( is_int( $value ) || is_float( $value ) ) || $value < 0 || $value > 100 ) {
				self::fail( 'quiz.pass_percentage must be a number between 0 and 100.' );
			}
			$quiz['pass_percentage'] = $value;
		}

		if ( array_key_exists( 'shuffle_options', $root ) ) {
			$quiz['shuffle_options'] = self::boolean( $root['shuffle_options'], 'quiz.shuffle_options' );
		}

		$questions = $root['questions'] ?? null;
		if ( ! is_array( $questions ) || [] === $questions || ! self::is_list( $questions ) ) {
			self::fail( 'quiz.questions must be a non-empty list of questions.' );
		}

		$seen = [];
		foreach ( $questions as $index => $question ) {
			$quiz['questions'][] = self::question( $question, "quiz.questions[{$index}]", $seen );
		}

		return $quiz;
	}

	/**
	 * Validate one question.
	 *
	 * @param mixed               $data Question data.
	 * @param string              $path Path for error messages.
	 * @param array<string, bool> $seen Question ids seen so far.
	 * @return array<string, mixed>
	 */
	private static function question( mixed $data, string $path, array &$seen ): array {
		$question = self::object( $data, $path );
		$type     = $question['type'] ?? null;

		if ( ! is_string( $type ) || ! isset( self::QUESTION_KEYS[ $type ] ) ) {
			self::fail( "{$path}.type must be one of: single, boolean, open." );
		}

		self::assert_keys( $question, self::QUESTION_KEYS[ $type ], $path );

		$id = $question['id'] ?? null;
		if ( ! is_string( $id ) || 1 !== preg_match( self::ID_PATTERN, $id ) ) {
			self::fail( "{$path}.id must start with a letter and use only letters, digits, _ or - (max 64)." );
		}
		if ( isset( $seen[ $id ] ) ) {
			self::fail( "{$path}.id duplicates question {$id}." );
		}
		$seen[ $id ] = true;

		$result = [
			'id'     => $id,
			'type'   => $type,
			'prompt' => self::text( $question['prompt'] ?? null, "{$path}.prompt" ),
		];

		if ( 'single' === $type ) {
			$result['options'] = self::options( $question['options'] ?? null, "{$path}.options" );
		} elseif ( 'boolean' === $type ) {
			$result['correct'] = self::boolean( $question['correct'] ?? null, "{$path}.correct" );
		} elseif ( array_key_exists( 'sample', $question ) ) {
			if ( ! is_string( $question['sample'] ) ) {
				self::fail( "{$path}.sample must be a string." );
			}
			$result['sample'] = $question['sample'];
		}

		return $result;
	}

	/**
	 * Validate the options of a single-choice question.
	 *
	 * @param mixed  $data Options data.
	 * @param string $path Path for error messages.
	 * @return array<int, array<string, mixed>>
	 */
	private static function options( mixed $data, string $path ): array {
		if ( ! is_array( $data ) || count( $data ) < 2 || ! self::is_list( $data ) ) {
			self::fail( "{$path} must be a list of at least 2 options." );
		}

		$options = [];
		$correct = 0;
		$seen    = [];

		foreach ( $data as $index => $item ) {
			$item_path = "{$path}[{$index}]";
			$option    = self::object( $item, $item_path );
			self::assert_keys( $option, self::OPTION_KEYS, $item_path );

			$entry = [];

			// Optional, but recommended: a stable id keeps earlier attempts
			// meaningful when the options are later reordered.
			if ( array_key_exists( 'id', $option ) ) {
				$id = $option['id'];

				if ( ! is_string( $id ) || 1 !== preg_match( self::ID_PATTERN, $id ) ) {
					self::fail( "{$item_path}.id must start with a letter and use only letters, digits, _ or - (max 64)." );
				}
				if ( isset( $seen[ $id ] ) ) {
					self::fail( "{$item_path}.id duplicates option {$id}." );
				}

				$seen[ $id ] = true;
				$entry['id'] = $id;
			}

			$entry['text'] = self::text( $option['text'] ?? null, "{$item_path}.text" );

			// An explicit "correct": false is kept, so the document round-trips as given.
			if ( array_key_exists( 'correct', $option ) ) {
				$entry['correct'] = self::boolean( $option['correct'], "{$item_path}.correct" );
				$correct         += $entry['correct'] ? 1 : 0;
			}

			$options[] = $entry;
		}

		if ( 1 !== $correct ) {
			self::fail( "{$path} must mark exactly one option as correct." );
		}

		return $options;
	}

	/**
	 * Require a JSON object (associative array).
	 *
	 * @param mixed  $data Value.
	 * @param string $path Path for error messages.
	 * @return array<string, mixed>
	 */
	private static function object( mixed $data, string $path ): array {
		if ( ! is_array( $data ) || ( [] !== $data && self::is_list( $data ) ) ) {
			self::fail( "{$path} must be an object." );
		}

		return $data;
	}

	/**
	 * Reject keys outside the allowed set.
	 *
	 * @param array<string, mixed> $data    Object.
	 * @param array<int, string>   $allowed Allowed keys.
	 * @param string               $path    Path for error messages.
	 * @return void
	 */
	private static function assert_keys( array $data, array $allowed, string $path ): void {
		$unknown = array_diff( array_map( 'strval', array_keys( $data ) ), $allowed );

		if ( [] !== $unknown ) {
			self::fail( sprintf( '%s has unknown key(s): %s.', $path, implode( ', ', $unknown ) ) );
		}
	}

	/**
	 * Require a non-blank string (returned untrimmed, so it round-trips).
	 *
	 * @param mixed  $value Value.
	 * @param string $path  Path for error messages.
	 * @return string
	 */
	private static function text( mixed $value, string $path ): string {
		if ( ! is_string( $value ) || '' === trim( $value ) ) {
			self::fail( "{$path} must be a non-empty string." );
		}

		return $value;
	}

	/**
	 * Require a boolean.
	 *
	 * @param mixed  $value Value.
	 * @param string $path  Path for error messages.
	 * @return bool
	 */
	private static function boolean( mixed $value, string $path ): bool {
		if ( ! is_bool( $value ) ) {
			self::fail( "{$path} must be true or false." );
		}

		return $value;
	}

	/**
	 * Whether an array is a list (keys 0..n-1 in order).
	 *
	 * Local stand-in for array_is_list(), which needs PHP 8.1; the plugin
	 * supports PHP 8.0.
	 *
	 * @param array<mixed> $data Array.
	 * @return bool
	 */
	private static function is_list( array $data ): bool {
		return array_values( $data ) === $data;
	}

	/**
	 * Abort validation.
	 *
	 * Declared `void` with a `never` docblock type: the native `never` return
	 * type needs PHP 8.1, and the plugin supports PHP 8.0. Static analysis
	 * still knows the call does not return.
	 *
	 * @param string $message Plain-text message naming the offending path.
	 * @return never
	 * @throws InvalidQuizException Always.
	 */
	private static function fail( string $message ): void {
		// phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Plain-text message for WP-CLI / JSON, never rendered as HTML.
		throw new InvalidQuizException( $message );
	}
}
