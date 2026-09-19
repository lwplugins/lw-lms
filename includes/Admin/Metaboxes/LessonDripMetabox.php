<?php
/**
 * Lesson Drip metabox.
 *
 * @package LightweightPlugins\LMS
 */

declare(strict_types=1);

namespace LightweightPlugins\LMS\Admin\Metaboxes;

use LightweightPlugins\LMS\Drip\DripRule;
use LightweightPlugins\LMS\Drip\DripSettings;
use LightweightPlugins\LMS\Options;
use LightweightPlugins\LMS\PostTypes\Lesson;

/**
 * When a single lesson opens: right away, so long after enrollment, or so
 * long after the lesson before it is completed.
 */
final class LessonDripMetabox {

	use DripFieldsTrait;

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
			'lw_lms_lesson_drip',
			__( 'Drip', 'lw-lms' ),
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
		wp_nonce_field( 'lw_lms_lesson_drip', 'lw_lms_lesson_drip_nonce' );

		$rule      = DripSettings::lesson_rule( $post->ID );
		$course_id = (int) Options::get_post_meta( $post->ID, 'lesson_course_id', 0 );
		?>
		<div class="lw-lms-lesson-drip">
			<p>
				<label for="lw_lms_lesson_drip_mode"><strong><?php esc_html_e( 'Opens', 'lw-lms' ); ?></strong></label>
				<select id="lw_lms_lesson_drip_mode" name="lw_lms_lesson_drip_mode" class="widefat">
					<?php foreach ( self::mode_labels() as $mode => $label ) : ?>
						<option value="<?php echo esc_attr( $mode ); ?>" <?php selected( $rule['mode'], $mode ); ?>>
							<?php echo esc_html( $label ); ?>
						</option>
					<?php endforeach; ?>
				</select>
			</p>

			<p>
				<label for="lw_lms_lesson_drip_value"><strong><?php esc_html_e( 'After', 'lw-lms' ); ?></strong></label><br />
				<?php $this->render_delay_fields( 'lw_lms_lesson_drip', $rule ); ?>
			</p>

			<?php if ( $course_id > 0 && ! DripSettings::is_linear( $course_id ) ) : ?>
				<p class="description">
					<?php esc_html_e( 'This course uses free progression, so its lessons are never held back. Switch the course to linear for drip to apply.', 'lw-lms' ); ?>
				</p>
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
		if ( ! isset( $_POST['lw_lms_lesson_drip_nonce'] ) ) {
			return;
		}

		if ( ! wp_verify_nonce( sanitize_key( $_POST['lw_lms_lesson_drip_nonce'] ), 'lw_lms_lesson_drip' ) ) {
			return;
		}

		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
			return;
		}

		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}

		$rule = $this->read_rule(
			'lw_lms_lesson_drip',
			$this->read_mode( 'lw_lms_lesson_drip_mode' ),
			DripRule::MODES
		);

		Options::set_post_meta( $post_id, DripSettings::META_LESSON_RULE, $rule );
	}

	/**
	 * Labels of the modes a lesson supports.
	 *
	 * @return array<string, string>
	 */
	private static function mode_labels(): array {
		return [
			DripRule::MODE_NONE       => __( 'Right away', 'lw-lms' ),
			DripRule::MODE_ENROLLMENT => __( 'A while after enrollment', 'lw-lms' ),
			DripRule::MODE_PREVIOUS   => __( 'A while after the previous lesson is completed', 'lw-lms' ),
		];
	}
}
