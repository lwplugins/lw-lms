<?php
/**
 * Lesson Quiz metabox.
 *
 * @package LightweightPlugins\LMS
 */

declare(strict_types=1);

namespace LightweightPlugins\LMS\Admin\Metaboxes;

use LightweightPlugins\LMS\Admin\Quiz\QuizMetaboxRenderer;
use LightweightPlugins\LMS\PostTypes\Lesson;
use LightweightPlugins\LMS\Quiz\InvalidQuizException;
use LightweightPlugins\LMS\Quiz\QuizNormalizer;
use LightweightPlugins\LMS\Quiz\QuizRepository;

/**
 * Edits the lesson's quiz as JSON, validated by the same normalizer the CLI
 * uses — so an editor gets the exact error path an importer would.
 *
 * Deliberately a textarea with a readable preview, not a question builder.
 */
final class LessonQuizMetabox {

	/**
	 * Transient prefix for a rejected submission.
	 */
	private const ERROR_TRANSIENT = 'lw_lms_quiz_error_';

	/**
	 * Constructor.
	 */
	public function __construct() {
		add_action( 'add_meta_boxes', [ $this, 'register' ] );
		add_action( 'save_post_' . Lesson::POST_TYPE, [ $this, 'save' ] );
		add_action( 'admin_notices', [ $this, 'render_error_notice' ] );
	}

	/**
	 * Register the metabox.
	 *
	 * @return void
	 */
	public function register(): void {
		add_meta_box(
			'lw_lms_lesson_quiz',
			__( 'Quiz', 'lw-lms' ),
			[ $this, 'render' ],
			Lesson::POST_TYPE,
			'normal',
			'default'
		);
	}

	/**
	 * Render the metabox.
	 *
	 * @param \WP_Post $post Current post.
	 * @return void
	 */
	public function render( \WP_Post $post ): void {
		wp_nonce_field( 'lw_lms_lesson_quiz', 'lw_lms_lesson_quiz_nonce' );

		$rejected = get_transient( self::ERROR_TRANSIENT . $post->ID );
		$quiz     = QuizRepository::get( $post->ID );

		if ( is_array( $rejected ) ) {
			delete_transient( self::ERROR_TRANSIENT . $post->ID );
		}

		QuizMetaboxRenderer::render( $quiz, is_array( $rejected ) ? $rejected : null );
	}

	/**
	 * Save the metabox data.
	 *
	 * @param int $post_id Post ID.
	 * @return void
	 */
	public function save( int $post_id ): void {
		if ( ! isset( $_POST['lw_lms_lesson_quiz_nonce'] ) ) {
			return;
		}

		if ( ! wp_verify_nonce( sanitize_key( $_POST['lw_lms_lesson_quiz_nonce'] ), 'lw_lms_lesson_quiz' ) ) {
			return;
		}

		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
			return;
		}

		if ( ! current_user_can( 'edit_post', $post_id ) || ! isset( $_POST['lw_lms_quiz_json'] ) ) {
			return;
		}

		// Not sanitize_text_field(): this is a JSON document, and stripping it
		// would silently mangle prompts. It is validated below instead.
		$raw = trim( (string) wp_unslash( $_POST['lw_lms_quiz_json'] ) ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized

		if ( '' === $raw ) {
			QuizRepository::delete( $post_id );
			return;
		}

		try {
			$quiz = QuizNormalizer::normalize( json_decode( $raw, true, 512, JSON_THROW_ON_ERROR ) );
		} catch ( \JsonException $e ) {
			$this->reject( $post_id, $raw, __( 'Invalid JSON:', 'lw-lms' ) . ' ' . $e->getMessage() );
			return;
		} catch ( InvalidQuizException $e ) {
			$this->reject( $post_id, $raw, $e->getMessage() );
			return;
		}

		QuizRepository::save( $post_id, $quiz );
	}

	/**
	 * Show why the last submission was not saved.
	 *
	 * @return void
	 */
	public function render_error_notice(): void {
		$screen = get_current_screen();

		if ( ! $screen || Lesson::POST_TYPE !== $screen->post_type ) {
			return;
		}

		$post_id  = (int) get_the_ID();
		$rejected = $post_id ? get_transient( self::ERROR_TRANSIENT . $post_id ) : false;

		if ( ! is_array( $rejected ) ) {
			return;
		}

		printf(
			'<div class="notice notice-error"><p><strong>%s</strong> %s</p></div>',
			esc_html__( 'Quiz not saved:', 'lw-lms' ),
			esc_html( (string) ( $rejected['message'] ?? '' ) )
		);
	}

	/**
	 * Keep the rejected input so the editor does not lose their work.
	 *
	 * @param int    $post_id Post ID.
	 * @param string $raw     Submitted JSON.
	 * @param string $message Validation message.
	 * @return void
	 */
	private function reject( int $post_id, string $raw, string $message ): void {
		set_transient(
			self::ERROR_TRANSIENT . $post_id,
			[
				'json'    => $raw,
				'message' => $message,
			],
			MINUTE_IN_SECONDS * 10
		);
	}
}
