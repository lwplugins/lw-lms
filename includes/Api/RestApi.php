<?php
/**
 * REST API initialization.
 *
 * @package LightweightPlugins\LMS
 */

declare(strict_types=1);

namespace LightweightPlugins\LMS\Api;

use LightweightPlugins\LMS\Api\Controllers\CoursesController;
use LightweightPlugins\LMS\Api\Controllers\LessonsController;
use LightweightPlugins\LMS\Api\Controllers\ProgressController;
use LightweightPlugins\LMS\Api\Controllers\DownloadController;

/**
 * Initializes the REST API.
 */
final class RestApi {

	/**
	 * API namespace.
	 */
	public const NAMESPACE = 'lms/v1';

	/**
	 * Initialize the REST API.
	 *
	 * @return void
	 */
	public function init(): void {
		add_action( 'rest_api_init', [ $this, 'register_routes' ] );
	}

	/**
	 * Register REST API routes.
	 *
	 * @return void
	 */
	public function register_routes(): void {
		$controllers = [
			new CoursesController(),
			new LessonsController(),
			new ProgressController(),
			new DownloadController(),
		];

		foreach ( $controllers as $controller ) {
			$controller->register_routes();
		}
	}
}
