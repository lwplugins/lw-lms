<?php
/**
 * Markup for the lesson Quiz metabox.
 *
 * @package LightweightPlugins\LMS
 */

declare(strict_types=1);

namespace LightweightPlugins\LMS\Admin\Quiz;

/**
 * Renders the readable preview plus the JSON editor.
 */
final class QuizMetaboxRenderer {

	/**
	 * Render the metabox body.
	 *
	 * @param array<string, mixed>|null $quiz     Stored quiz, if any.
	 * @param array<string, mixed>|null $rejected Rejected submission (json, message).
	 * @return void
	 */
	public static function render( ?array $quiz, ?array $rejected ): void {
		$json = null !== $rejected
			? (string) ( $rejected['json'] ?? '' )
			: ( null !== $quiz ? (string) wp_json_encode( $quiz, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES ) : '' );

		if ( null !== $rejected ) {
			printf(
				'<div class="notice notice-error inline"><p>%s</p></div>',
				esc_html( (string) ( $rejected['message'] ?? '' ) )
			);
		}

		if ( null !== $quiz ) {
			self::render_preview( $quiz );
		}

		?>
		<p>
			<label for="lw_lms_quiz_json"><strong><?php esc_html_e( 'Quiz JSON', 'lw-lms' ); ?></strong></label>
		</p>
		<textarea id="lw_lms_quiz_json" name="lw_lms_quiz_json" rows="14" class="widefat code" spellcheck="false"><?php echo esc_textarea( $json ); ?></textarea>
		<p class="description">
			<?php esc_html_e( 'Question types: single (exactly one correct option), boolean (true/false), open (free text, never scored). Leave empty to remove the quiz. Saved only if valid — the error names the exact field.', 'lw-lms' ); ?>
		</p>
		<?php
	}

	/**
	 * Readable listing of the stored quiz.
	 *
	 * @param array<string, mixed> $quiz Stored quiz.
	 * @return void
	 */
	private static function render_preview( array $quiz ): void {
		$questions = $quiz['questions'] ?? [];

		printf(
			'<p><strong>%s</strong> %s</p>',
			esc_html__( 'Stored quiz:', 'lw-lms' ),
			esc_html(
				sprintf(
					/* translators: 1: number of questions, 2: pass percentage. */
					_n( '%1$d question, pass at %2$s%%', '%1$d questions, pass at %2$s%%', count( $questions ), 'lw-lms' ),
					count( $questions ),
					isset( $quiz['pass_percentage'] ) ? (string) $quiz['pass_percentage'] : __( 'the global default', 'lw-lms' )
				)
			)
		);

		echo '<ol class="lw-lms-quiz-preview">';

		foreach ( $questions as $question ) {
			printf(
				'<li><strong>%s</strong> <em>(%s)</em>',
				esc_html( (string) $question['prompt'] ),
				esc_html( (string) $question['type'] )
			);

			self::render_answer( $question );

			echo '</li>';
		}

		echo '</ol>';
	}

	/**
	 * The answer part of one question.
	 *
	 * @param array<string, mixed> $question Normalized question.
	 * @return void
	 */
	private static function render_answer( array $question ): void {
		if ( 'single' === $question['type'] ) {
			echo '<ul style="margin:4px 0 0 18px;">';

			foreach ( $question['options'] as $option ) {
				printf(
					'<li>%s%s</li>',
					esc_html( (string) $option['text'] ),
					! empty( $option['correct'] ) ? ' <span class="dashicons dashicons-yes" style="color:#008a20;"></span>' : ''
				);
			}

			echo '</ul>';
			return;
		}

		if ( 'boolean' === $question['type'] ) {
			printf(
				'<div style="margin-left:18px;">%s <strong>%s</strong></div>',
				esc_html__( 'Correct:', 'lw-lms' ),
				$question['correct'] ? esc_html__( 'true', 'lw-lms' ) : esc_html__( 'false', 'lw-lms' )
			);
			return;
		}

		if ( isset( $question['sample'] ) ) {
			printf(
				'<div style="margin-left:18px;">%s %s</div>',
				esc_html__( 'Sample answer:', 'lw-lms' ),
				esc_html( (string) $question['sample'] )
			);
		}
	}
}
