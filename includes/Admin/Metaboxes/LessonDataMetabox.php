<?php
/**
 * Lesson Data Metabox.
 *
 * @package LightweightPlugins\LMS
 */

declare(strict_types=1);

namespace LightweightPlugins\LMS\Admin\Metaboxes;

use LightweightPlugins\LMS\PostTypes\Lesson;
use LightweightPlugins\LMS\Options;

/**
 * Handles the Lesson Data metabox.
 */
final class LessonDataMetabox {

	/**
	 * Constructor.
	 */
	public function __construct() {
		add_action( 'add_meta_boxes', [ $this, 'register' ] );
		add_action( 'save_post_' . Lesson::POST_TYPE, [ $this, 'save' ] );
	}

	/**
	 * Register the metabox.
	 *
	 * @return void
	 */
	public function register(): void {
		add_meta_box(
			'lw_lms_lesson_data',
			__( 'Lesson Data', 'lw-lms' ),
			[ $this, 'render' ],
			Lesson::POST_TYPE,
			'side',
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
		wp_nonce_field( 'lw_lms_lesson_data', 'lw_lms_lesson_data_nonce' );

		$duration    = Options::get_post_meta( $post->ID, 'duration', '' );
		$attachments = Options::get_post_meta( $post->ID, 'attachments', [] );
		?>
		<div class="lw-lms-lesson-data">
			<p>
				<label for="lw_lms_lesson_duration"><strong><?php esc_html_e( 'Duration', 'lw-lms' ); ?></strong></label>
				<input type="text" id="lw_lms_lesson_duration" name="lw_lms_lesson_duration" value="<?php echo esc_attr( $duration ); ?>" class="widefat" placeholder="<?php esc_attr_e( 'e.g., 15 min', 'lw-lms' ); ?>" />
			</p>

			<p><strong><?php esc_html_e( 'Attachments', 'lw-lms' ); ?></strong></p>
			<div class="lw-lms-attachments-list" id="lw-lms-lesson-attachments">
				<?php
				if ( is_array( $attachments ) ) {
					foreach ( $attachments as $attachment ) {
						$this->render_attachment_row( $attachment );
					}
				}
				?>
			</div>
			<button type="button" class="button lw-lms-add-attachment" id="lw-lms-add-lesson-attachment">
				<?php esc_html_e( 'Add Attachment', 'lw-lms' ); ?>
			</button>

			<input type="hidden" name="lw_lms_lesson_attachments" id="lw-lms-lesson-attachments-data" value="<?php echo esc_attr( wp_json_encode( $attachments ) ); ?>" />
		</div>
		<?php
	}

	/**
	 * Render an attachment row.
	 *
	 * @param array $attachment Attachment data.
	 * @return void
	 */
	private function render_attachment_row( array $attachment ): void {
		$id       = $attachment['id'] ?? 0;
		$title    = $attachment['title'] ?? '';
		$filename = '';

		if ( $id ) {
			$filename = basename( get_attached_file( $id ) );
		}
		?>
		<div class="lw-lms-attachment-row" data-attachment-id="<?php echo esc_attr( (string) $id ); ?>">
			<span class="lw-lms-attachment-name"><?php echo esc_html( $title ? $title : $filename ); ?></span>
			<button type="button" class="button-link lw-lms-remove-attachment"><?php esc_html_e( 'Remove', 'lw-lms' ); ?></button>
		</div>
		<?php
	}

	/**
	 * Save the metabox data.
	 *
	 * @param int $post_id Post ID.
	 * @return void
	 */
	public function save( int $post_id ): void {
		if ( ! isset( $_POST['lw_lms_lesson_data_nonce'] ) ) {
			return;
		}

		if ( ! wp_verify_nonce( sanitize_key( $_POST['lw_lms_lesson_data_nonce'] ), 'lw_lms_lesson_data' ) ) {
			return;
		}

		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
			return;
		}

		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}

		// Save duration.
		if ( isset( $_POST['lw_lms_lesson_duration'] ) ) {
			Options::set_post_meta( $post_id, 'duration', sanitize_text_field( wp_unslash( $_POST['lw_lms_lesson_duration'] ) ) );
		}

		// Save attachments.
		if ( isset( $_POST['lw_lms_lesson_attachments'] ) ) {
			$attachments = json_decode( sanitize_text_field( wp_unslash( $_POST['lw_lms_lesson_attachments'] ) ), true );
			if ( is_array( $attachments ) ) {
				$sanitized = [];
				foreach ( $attachments as $attachment ) {
					$sanitized[] = [
						'id'          => absint( $attachment['id'] ?? 0 ),
						'title'       => sanitize_text_field( $attachment['title'] ?? '' ),
						'description' => sanitize_text_field( $attachment['description'] ?? '' ),
					];
				}
				Options::set_post_meta( $post_id, 'attachments', $sanitized );
			}
		}
	}
}
