<?php
/**
 * Lesson Video Metabox.
 *
 * @package LightweightPlugins\LMS
 */

declare(strict_types=1);

namespace LightweightPlugins\LMS\Admin\Metaboxes;

use LightweightPlugins\LMS\PostTypes\Lesson;
use LightweightPlugins\LMS\Options;
use LightweightPlugins\LMS\Meta\VideoParser;

/**
 * Handles the Lesson Video metabox.
 */
final class LessonVideoMetabox {

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
			'lw_lms_lesson_video',
			__( 'Video', 'lw-lms' ),
			[ $this, 'render' ],
			Lesson::POST_TYPE,
			'normal',
			'high'
		);
	}

	/**
	 * Render the metabox.
	 *
	 * @param \WP_Post $post Current post.
	 * @return void
	 */
	public function render( \WP_Post $post ): void {
		wp_nonce_field( 'lw_lms_lesson_video', 'lw_lms_lesson_video_nonce' );

		$video = Options::get_post_meta( $post->ID, 'video', [] );
		$url   = $video['url'] ?? '';
		?>
		<div class="lw-lms-lesson-video">
			<p>
				<label for="lw_lms_video_url"><strong><?php esc_html_e( 'Video URL', 'lw-lms' ); ?></strong></label>
				<input type="url" id="lw_lms_video_url" name="lw_lms_video_url" value="<?php echo esc_url( $url ); ?>" class="widefat" placeholder="https://www.youtube.com/watch?v=..." />
				<span class="description">
					<?php esc_html_e( 'Supported: YouTube, Vimeo, Wistia, or direct video URL (.mp4, .webm)', 'lw-lms' ); ?>
				</span>
			</p>

			<?php if ( ! empty( $video['embed'] ) ) : ?>
				<div class="lw-lms-video-preview">
					<p><strong><?php esc_html_e( 'Preview', 'lw-lms' ); ?></strong></p>
					<?php if ( in_array( $video['provider'] ?? '', [ 'youtube', 'vimeo', 'wistia' ], true ) ) : ?>
						<iframe src="<?php echo esc_url( $video['embed'] ); ?>" width="560" height="315" frameborder="0" allowfullscreen></iframe>
					<?php elseif ( 'self' === ( $video['provider'] ?? '' ) ) : ?>
						<video src="<?php echo esc_url( $video['embed'] ); ?>" width="560" controls></video>
					<?php endif; ?>
					<p class="description">
						<?php
						printf(
							/* translators: %s: video provider name */
							esc_html__( 'Provider: %s', 'lw-lms' ),
							esc_html( ucfirst( $video['provider'] ?? 'unknown' ) )
						);
						?>
					</p>
				</div>
			<?php endif; ?>
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
		if ( ! isset( $_POST['lw_lms_lesson_video_nonce'] ) ) {
			return;
		}

		if ( ! wp_verify_nonce( sanitize_key( $_POST['lw_lms_lesson_video_nonce'] ), 'lw_lms_lesson_video' ) ) {
			return;
		}

		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
			return;
		}

		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}

		// Parse and save video data.
		if ( isset( $_POST['lw_lms_video_url'] ) ) {
			$url   = esc_url_raw( wp_unslash( $_POST['lw_lms_video_url'] ) );
			$video = VideoParser::parse( $url );
			Options::set_post_meta( $post_id, 'video', $video );
		}
	}
}
