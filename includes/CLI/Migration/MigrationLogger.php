<?php
/**
 * Migration Logger.
 *
 * @package LightweightPlugins\LMS
 */

declare(strict_types=1);

namespace LightweightPlugins\LMS\CLI\Migration;

/**
 * Handles logging and stats for migration.
 */
final class MigrationLogger {

	/**
	 * Migration statistics.
	 *
	 * @var array
	 */
	private array $stats = [
		'courses_migrated' => 0,
		'courses_skipped'  => 0,
		'lessons_migrated' => 0,
		'lessons_skipped'  => 0,
		'errors'           => [],
	];

	/**
	 * Verbose mode flag.
	 *
	 * @var bool
	 */
	private bool $verbose;

	/**
	 * Constructor.
	 *
	 * @param bool $verbose Whether to show verbose output.
	 */
	public function __construct( bool $verbose = false ) {
		$this->verbose = $verbose;
	}

	/**
	 * Increment a stat counter.
	 *
	 * @param string $key Stat key.
	 * @return void
	 */
	public function increment( string $key ): void {
		if ( isset( $this->stats[ $key ] ) ) {
			++$this->stats[ $key ];
		}
	}

	/**
	 * Add an error message.
	 *
	 * @param string $message Error message.
	 * @return void
	 */
	public function add_error( string $message ): void {
		$this->stats['errors'][] = $message;
	}

	/**
	 * Log a message to WP-CLI.
	 *
	 * @param string $message Message to log.
	 * @return void
	 */
	public function log( string $message ): void {
		if ( defined( 'WP_CLI' ) && \WP_CLI ) {
			\WP_CLI::log( $message );
		}
	}

	/**
	 * Log a verbose message.
	 *
	 * @param string $message Message to log.
	 * @return void
	 */
	public function verbose( string $message ): void {
		if ( $this->verbose ) {
			$this->log( $message );
		}
	}

	/**
	 * Log a success message.
	 *
	 * @param string $message Message to log.
	 * @return void
	 */
	public function success( string $message ): void {
		if ( defined( 'WP_CLI' ) && \WP_CLI ) {
			\WP_CLI::success( $message );
		}
	}

	/**
	 * Log a warning message.
	 *
	 * @param string $message Message to log.
	 * @return void
	 */
	public function warning( string $message ): void {
		if ( defined( 'WP_CLI' ) && \WP_CLI ) {
			\WP_CLI::warning( $message );
		}
	}

	/**
	 * Print migration summary.
	 *
	 * @param bool $dry_run Whether this was a dry run.
	 * @return void
	 */
	public function print_summary( bool $dry_run ): void {
		$this->log( '' );
		$this->log( '========================================' );
		$this->log( 'Migration Summary' );
		$this->log( '========================================' );
		$this->log( sprintf( 'Courses migrated: %d', $this->stats['courses_migrated'] ) );
		$this->log( sprintf( 'Courses skipped:  %d', $this->stats['courses_skipped'] ) );
		$this->log( sprintf( 'Lessons migrated: %d', $this->stats['lessons_migrated'] ) );
		$this->log( sprintf( 'Lessons skipped:  %d', $this->stats['lessons_skipped'] ) );

		if ( ! empty( $this->stats['errors'] ) ) {
			$this->log( '' );
			$this->warning( 'Errors:' );
			foreach ( $this->stats['errors'] as $error ) {
				$this->log( '  - ' . $error );
			}
		}

		if ( $dry_run ) {
			$this->log( '' );
			$this->warning( 'DRY RUN - No changes were made.' );
		}

		$this->log( '========================================' );
	}
}
