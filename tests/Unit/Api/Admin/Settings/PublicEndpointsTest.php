<?php
/**
 * Tests for the public endpoint reference list.
 *
 * @package LightweightPlugins\LMS
 */

declare(strict_types=1);

namespace LightweightPlugins\LMS\Tests\Unit\Api\Admin\Settings;

use Brain\Monkey\Functions;
use LightweightPlugins\LMS\Api\Admin\Settings\PublicEndpoints;
use LightweightPlugins\LMS\Api\Controllers\CoursesController;
use LightweightPlugins\LMS\Api\Controllers\DownloadController;
use LightweightPlugins\LMS\Api\Controllers\LessonsController;
use LightweightPlugins\LMS\Api\Controllers\ProgressController;
use LightweightPlugins\LMS\Api\Controllers\QuizController;
use LightweightPlugins\LMS\Api\RestApi;
use LightweightPlugins\LMS\Tests\Unit\MonkeyTestCase;

/**
 * Regression: the classic Advanced tab listed six endpoints by hand and
 * missed GET /progress/course/{id} and POST /lessons/{id}/quiz.
 *
 * @covers \LightweightPlugins\LMS\Api\Admin\Settings\PublicEndpoints
 */
final class PublicEndpointsTest extends MonkeyTestCase {

	public function test_lists_every_route_the_public_api_registers(): void {
		Functions\stubTranslationFunctions();
		Functions\when( 'get_option' )->justReturn( [] );
		Functions\when( 'wp_parse_args' )->alias( static fn ( $a, $d = [] ): array => array_merge( (array) $d, (array) $a ) );
		$registered = [];
		Functions\when( 'register_rest_route' )->alias(
			static function ( string $namespace, string $route, array $args ) use ( &$registered ): bool {
				if ( RestApi::NAMESPACE !== $namespace ) {
					return true;
				}
				$endpoints = isset( $args['methods'] ) ? [ $args ] : $args;
				foreach ( $endpoints as $endpoint ) {
					$path         = (string) preg_replace( '/\(\?P<(\w+)>[^)]+\)/', '{$1}', $route );
					$registered[] = $endpoint['methods'] . ' ' . $path;
				}
				return true;
			}
		);

		foreach ( [ CoursesController::class, LessonsController::class, ProgressController::class, DownloadController::class, QuizController::class ] as $controller ) {
			( new $controller() )->register_routes();
		}

		$listed = array_map( static fn ( array $e ): string => $e['method'] . ' ' . $e['path'], PublicEndpoints::all() );
		sort( $listed );
		sort( $registered );

		$this->assertSame( $registered, $listed );
	}
}
