<?php
/**
 * The public lms/v1 endpoints, for the admin's reference list.
 *
 * @package LightweightPlugins\LMS
 */

declare(strict_types=1);

namespace LightweightPlugins\LMS\Api\Admin\Settings;

/**
 * Pure data: every route the public API registers, with what it does and who
 * may call it. A unit test compares this list with the routes the public
 * controllers register, so it cannot fall behind again.
 */
final class PublicEndpoints {

	/**
	 * Every public endpoint.
	 *
	 * @return array<int, array{method: string, path: string, description: string, access: string}>
	 */
	public static function all(): array {
		$anyone = __( 'Anyone (access is checked per course and lesson)', 'lw-lms' );
		$login  = __( 'Logged-in users', 'lw-lms' );

		return [
			self::row( 'GET', '/courses', __( 'List courses', 'lw-lms' ), $anyone ),
			self::row( 'GET', '/courses/{id}', __( 'Course details with sections and lessons', 'lw-lms' ), $anyone ),
			self::row( 'GET', '/lessons/{id}', __( 'Lesson content, video and quiz questions', 'lw-lms' ), $anyone ),
			self::row( 'POST', '/lessons/{id}/quiz', __( 'Submit a quiz attempt', 'lw-lms' ), $login ),
			self::row( 'GET', '/progress', __( 'Progress of the current user', 'lw-lms' ), $login ),
			self::row( 'POST', '/progress', __( 'Mark a lesson started or completed', 'lw-lms' ), $login ),
			self::row( 'GET', '/progress/course/{id}', __( 'Progress of the current user in one course', 'lw-lms' ), $login ),
			self::row( 'GET', '/download/{id}', __( 'Download a course or lesson attachment', 'lw-lms' ), $anyone ),
		];
	}

	/**
	 * One endpoint.
	 *
	 * @param string $method      HTTP method.
	 * @param string $path        Path under the namespace.
	 * @param string $description What it does.
	 * @param string $access      Who may call it.
	 * @return array{method: string, path: string, description: string, access: string}
	 */
	private static function row( string $method, string $path, string $description, string $access ): array {
		return [
			'method'      => $method,
			'path'        => $path,
			'description' => $description,
			'access'      => $access,
		];
	}
}
