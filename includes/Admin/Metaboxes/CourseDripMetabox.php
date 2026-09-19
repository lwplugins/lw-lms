<?php
/**
 * Course Progression metabox.
 *
 * @package LightweightPlugins\LMS
 */

declare(strict_types=1);

namespace LightweightPlugins\LMS\Admin\Metaboxes;

use LightweightPlugins\LMS\Drip\DripRule;
use LightweightPlugins\LMS\Drip\DripSettings;
use LightweightPlugins\LMS\Options;
use LightweightPlugins\LMS\PostTypes\Course;

/**
 * Course-level progression: free or linear, plus the delay before the
 * course opens for a newly enrolled learner.
 */
final class CourseDripMetabox {

	use DripFieldsTrait;

	/**
	 * Constructor.
	 */
	public function __construct() {
		add_action( 'add_meta_boxes', [ $this, 'register' ] );
		add_action( 'save_post_' . Course::POST_TYPE, [ $this, 'save' ] );
	}

	/**
	 * Register the metabox.
	 *
	 * @return void
	 */
	public function register(): void {
		add_meta_box(
			'lw_lms_course_drip',
			__( 'Progression & Drip', 'lw-lms' ),
			[ $this, 'render' ],
			Course::POST_TYPE,
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
		wp_nonce_field( 'lw_lms_course_drip', 'lw_lms_course_drip_nonce' );

		$progression = DripSettings::progression( $post->ID );
		$delay       = DripSettings::course_delay( $post->ID );
		?>
		<div class="lw-lms-course-drip">
			<p>
				<label for="lw_lms_progression"><strong><?php esc_html_e( 'Progression', 'lw-lms' ); ?></strong></label>
				<select id="lw_lms_progression" name="lw_lms_progression" class="widefat">
					<option value="<?php echo esc_attr( DripSettings::PROGRESSION_FREE ); ?>" <?php selected( $progression, DripSettings::PROGRESSION_FREE ); ?>>
						<?php esc_html_e( 'Free — any lesson, any order', 'lw-lms' ); ?>
					</option>
					<option value="<?php echo esc_attr( DripSettings::PROGRESSION_LINEAR ); ?>" <?php selected( $progression, DripSettings::PROGRESSION_LINEAR ); ?>>
						<?php esc_html_e( 'Linear — one lesson after the other', 'lw-lms' ); ?>
					</option>
				</select>
			</p>

			<p>
				<label for="lw_lms_course_delay_value"><strong><?php esc_html_e( 'Course opens after enrollment', 'lw-lms' ); ?></strong></label><br />
				<?php $this->render_delay_fields( 'lw_lms_course_delay', $delay ); ?>
			</p>

			<p class="description">
				<?php esc_html_e( 'Drip schedules — here, on the sections and on the lessons — only take effect in linear mode. Zero means the course opens immediately.', 'lw-lms' ); ?>
			</p>
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
		if ( ! isset( $_POST['lw_lms_course_drip_nonce'] ) ) {
			return;
		}

		if ( ! wp_verify_nonce( sanitize_key( $_POST['lw_lms_course_drip_nonce'] ), 'lw_lms_course_drip' ) ) {
			return;
		}

		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
			return;
		}

		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}

		$progression = $this->read_mode( 'lw_lms_progression' );

		Options::set_post_meta(
			$post_id,
			DripSettings::META_PROGRESSION,
			DripSettings::PROGRESSION_LINEAR === $progression ? DripSettings::PROGRESSION_LINEAR : DripSettings::PROGRESSION_FREE
		);

		// The course delay only knows "so long after enrollment", so the mode
		// follows the number the editor typed.
		$delay = $this->read_rule(
			'lw_lms_course_delay',
			DripRule::MODE_ENROLLMENT,
			[ DripRule::MODE_NONE, DripRule::MODE_ENROLLMENT ]
		);

		Options::set_post_meta( $post_id, DripSettings::META_COURSE_DELAY, $delay['value'] > 0 ? $delay : DripRule::none() );
	}
}
