<?php
/**
 * Quiz attempts as CSV.
 *
 * @package LightweightPlugins\LMS
 */

declare(strict_types=1);

namespace LightweightPlugins\LMS\Api\Admin\QuizAttempts;

/**
 * Builds the CSV export of quiz attempts. Only what a results sheet needs:
 * the learner's user ID and display name (no email address), lesson,
 * course, score and date; never the answers. Pure: no WordPress calls.
 */
final class AttemptCsv {

	/**
	 * Most rows one export holds.
	 */
	public const MAX_ROWS = 5000;

	/**
	 * CSV text: a header row, then one row per attempt.
	 *
	 * @param array<int, string>               $header Column titles.
	 * @param array<int, array<string, mixed>> $rows   Presented attempt rows (AttemptPresenter::row()).
	 * @param array{0: string, 1: string}      $yes_no Words for passed / not passed.
	 * @return string
	 */
	public static function build( array $header, array $rows, array $yes_no ): string {
		$lines = [ self::line( $header ) ];

		foreach ( $rows as $row ) {
			$lines[] = self::line(
				[
					(string) $row['id'],
					(string) $row['submittedAt'],
					(string) $row['userId'],
					(string) ( $row['user']['name'] ?? '' ),
					(string) ( $row['lesson']['title'] ?? '' ),
					(string) ( $row['course']['title'] ?? '' ),
					(string) $row['score'],
					(string) $row['scoredQuestions'],
					number_format( (float) $row['percentage'], 1, '.', '' ),
					$row['passed'] ? $yes_no[0] : $yes_no[1],
				]
			);
		}

		// A BOM so spreadsheet apps read the file as UTF-8.
		return "\xEF\xBB\xBF" . implode( "\r\n", $lines ) . "\r\n";
	}

	/**
	 * One CSV line.
	 *
	 * @param array<int, string> $cells Cells.
	 * @return string
	 */
	private static function line( array $cells ): string {
		return implode( ',', array_map( [ self::class, 'cell' ], $cells ) );
	}

	/**
	 * One quoted cell. A cell a spreadsheet would run as a formula (starting
	 * with =, +, -, @, tab or carriage return) gets a leading apostrophe.
	 *
	 * @param string $value Cell text.
	 * @return string
	 */
	public static function cell( string $value ): string {
		if ( '' !== $value && false !== strpos( "=+-@\t\r", $value[0] ) ) {
			$value = "'" . $value;
		}

		return '"' . str_replace( '"', '""', $value ) . '"';
	}
}
