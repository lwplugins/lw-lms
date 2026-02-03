<?php
/**
 * User Profile enrollment management.
 *
 * @package LightweightPlugins\LMS
 */

declare(strict_types=1);

namespace LightweightPlugins\LMS\Admin;

use LightweightPlugins\LMS\Admin\UserProfile\EnrollmentRenderer;
use LightweightPlugins\LMS\Admin\UserProfile\EnrollmentHandler;

/**
 * Adds course enrollment UI to user profile pages.
 */
final class UserProfile {

	/**
	 * Renderer instance.
	 *
	 * @var EnrollmentRenderer
	 */
	private EnrollmentRenderer $renderer;

	/**
	 * Handler instance.
	 *
	 * @var EnrollmentHandler
	 */
	private EnrollmentHandler $handler;

	/**
	 * Constructor.
	 */
	public function __construct() {
		$this->renderer = new EnrollmentRenderer();
		$this->handler  = new EnrollmentHandler();

		// Display hooks.
		add_action( 'show_user_profile', [ $this, 'render' ] );
		add_action( 'edit_user_profile', [ $this, 'render' ] );

		// Save hooks.
		add_action( 'personal_options_update', [ $this, 'save' ] );
		add_action( 'edit_user_profile_update', [ $this, 'save' ] );
	}

	/**
	 * Render the enrollment section.
	 *
	 * @param \WP_User $user User object.
	 * @return void
	 */
	public function render( \WP_User $user ): void {
		if ( ! current_user_can( 'manage_lms' ) ) {
			return;
		}

		$this->renderer->render_section( $user->ID );
	}

	/**
	 * Save enrollment changes.
	 *
	 * @param int $user_id User ID.
	 * @return void
	 */
	public function save( int $user_id ): void {
		if ( ! current_user_can( 'manage_lms' ) ) {
			return;
		}

		$this->handler->handle_save( $user_id );
	}
}
