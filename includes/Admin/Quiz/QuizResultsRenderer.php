<?php
/**
 * Markup for the Quiz Results page.
 *
 * @package LightweightPlugins\LMS
 */

declare(strict_types=1);

namespace LightweightPlugins\LMS\Admin\Quiz;

/**
 * Renders the lesson filter, the per-learner table and the per-question
 * statistics. Presentation only — every value comes from QuizAttemptQueries.
 */
final class QuizResultsRenderer {

	/**
	 * Lesson selector.
	 *
	 * @param array<int, string> $lessons   Lesson id => title.
	 * @param int                $lesson_id Selected lesson.
	 * @param string             $page_slug Admin page slug.
	 * @return void
	 */
	public static function render_filter( array $lessons, int $lesson_id, string $page_slug ): void {
		?>
		<form method="get" style="margin:16px 0;">
			<input type="hidden" name="page" value="<?php echo esc_attr( $page_slug ); ?>" />
			<label for="lw-lms-lesson"><?php esc_html_e( 'Lesson', 'lw-lms' ); ?></label>
			<select name="lesson_id" id="lw-lms-lesson">
				<?php foreach ( $lessons as $id => $title ) : ?>
					<option value="<?php echo esc_attr( (string) $id ); ?>" <?php selected( $id, $lesson_id ); ?>>
						<?php echo esc_html( $title ); ?>
					</option>
				<?php endforeach; ?>
			</select>
			<?php submit_button( __( 'Show', 'lw-lms' ), 'secondary', '', false ); ?>
		</form>
		<?php
	}

	/**
	 * Attempt and learner counts.
	 *
	 * @param int $attempts Attempt count.
	 * @param int $learners Distinct learners.
	 * @return void
	 */
	public static function render_summary( int $attempts, int $learners ): void {
		printf(
			'<p>%s</p>',
			esc_html(
				sprintf(
					/* translators: 1: attempt count, 2: learner count. */
					__( '%1$d attempts by %2$d learners.', 'lw-lms' ),
					$attempts,
					$learners
				)
			)
		);
	}

	/**
	 * One row per learner.
	 *
	 * @param array<int, object> $rows Learner rows.
	 * @return void
	 */
	public static function render_learners( array $rows ): void {
		echo '<table class="wp-list-table widefat striped"><thead><tr>';
		self::headers(
			[
				__( 'Learner', 'lw-lms' ),
				__( 'Attempts', 'lw-lms' ),
				__( 'Best', 'lw-lms' ),
				__( 'Last', 'lw-lms' ),
				__( 'Passed', 'lw-lms' ),
				__( 'Last attempt', 'lw-lms' ),
			]
		);
		echo '</tr></thead><tbody>';

		foreach ( $rows as $row ) {
			$user = get_userdata( (int) $row->user_id );

			echo '<tr>';
			printf( '<td>%s</td>', esc_html( $user ? $user->display_name : sprintf( '#%d', (int) $row->user_id ) ) );
			printf( '<td>%d</td>', (int) $row->attempts );
			printf( '<td>%s%%</td>', esc_html( (string) round( (float) $row->best_percentage, 2 ) ) );
			printf( '<td>%s%%</td>', esc_html( (string) round( (float) $row->last_percentage, 2 ) ) );
			printf(
				'<td>%s</td>',
				( (int) $row->ever_passed ) === 1
					? '<span class="dashicons dashicons-yes" style="color:#008a20;"></span>'
					: '<span class="dashicons dashicons-minus"></span>'
			);
			printf( '<td>%s</td>', esc_html( (string) $row->last_submitted_at ) );
			echo '</tr>';
		}

		echo '</tbody></table>';
	}

	/**
	 * Pager under the learner table.
	 *
	 * @param int $total    Total learners.
	 * @param int $per_page Rows per page.
	 * @param int $paged    Current page.
	 * @return void
	 */
	public static function render_pager( int $total, int $per_page, int $paged ): void {
		$pages = (int) ceil( $total / max( 1, $per_page ) );

		if ( $pages < 2 ) {
			return;
		}

		$links = paginate_links(
			[
				'base'    => add_query_arg( 'paged', '%#%' ),
				'format'  => '',
				'current' => $paged,
				'total'   => $pages,
				'type'    => 'plain',
			]
		);

		if ( $links ) {
			printf( '<p class="tablenav-pages">%s</p>', wp_kses_post( $links ) );
		}
	}

	/**
	 * Per-question statistics.
	 *
	 * @param array<int, array<string, mixed>> $stats Question rows.
	 * @return void
	 */
	public static function render_question_stats( array $stats ): void {
		printf( '<h2>%s</h2>', esc_html__( 'Per question', 'lw-lms' ) );

		if ( [] === $stats ) {
			printf( '<p>%s</p>', esc_html__( 'No stored answers yet. Attempts made before this version have no answer snapshot.', 'lw-lms' ) );
			return;
		}

		echo '<table class="wp-list-table widefat striped"><thead><tr>';
		self::headers(
			[
				__( 'Question', 'lw-lms' ),
				__( 'Type', 'lw-lms' ),
				__( 'Answered', 'lw-lms' ),
				__( 'Correct', 'lw-lms' ),
				__( 'Wrong', 'lw-lms' ),
				__( 'Correct %', 'lw-lms' ),
			]
		);
		echo '</tr></thead><tbody>';

		foreach ( $stats as $row ) {
			echo '<tr>';
			printf( '<td>%s</td>', esc_html( (string) $row['prompt'] ) );
			printf( '<td>%s</td>', esc_html( (string) $row['type'] ) );
			printf( '<td>%d</td>', (int) $row['answered'] );
			printf( '<td>%d</td>', (int) $row['correct'] );
			printf( '<td>%d</td>', (int) $row['wrong'] );
			printf( '<td>%s</td>', null === $row['correct_ratio'] ? '&mdash;' : esc_html( $row['correct_ratio'] . '%' ) );
			echo '</tr>';
		}

		echo '</tbody></table>';
	}

	/**
	 * Table header cells.
	 *
	 * @param array<int, string> $labels Column labels.
	 * @return void
	 */
	private static function headers( array $labels ): void {
		foreach ( $labels as $label ) {
			printf( '<th scope="col">%s</th>', esc_html( $label ) );
		}
	}
}
