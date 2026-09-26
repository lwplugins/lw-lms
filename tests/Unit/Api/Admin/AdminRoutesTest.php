<?php
/**
 * Tests for the admin route permissions.
 *
 * @package LightweightPlugins\LMS
 */

declare(strict_types=1);

namespace LightweightPlugins\LMS\Tests\Unit\Api\Admin;

use Brain\Monkey\Functions;
use LightweightPlugins\LMS\Api\Admin\AdminRoutes;
use LightweightPlugins\LMS\Tests\Unit\MonkeyTestCase;

/**
 * Regression: 2.0 let every manage_lms user read and change the plugin
 * settings, which needed manage_options before.
 *
 * @covers \LightweightPlugins\LMS\Api\Admin\AdminRoutes
 */
final class AdminRoutesTest extends MonkeyTestCase {

	/**
	 * @dataProvider provide_capabilities
	 *
	 * @param array<int, string> $caps     Granted capabilities.
	 * @param bool               $screen   May open the screen and Overview.
	 * @param bool               $settings May read and save settings.
	 * @param bool               $learners May use the learner sections.
	 */
	public function test_permissions_per_capability( array $caps, bool $screen, bool $settings, bool $learners ): void {
		Functions\when( 'current_user_can' )->alias( static fn ( string $cap ): bool => in_array( $cap, $caps, true ) );

		$this->assertSame(
			[ $screen, $settings, $learners ],
			[ AdminRoutes::can_open_screen(), AdminRoutes::can_manage_settings(), AdminRoutes::can_manage_learners() ]
		);
	}

	public static function provide_capabilities(): array {
		return [
			'LMS manager only'    => [ [ 'manage_lms' ], true, false, true ],
			'administrator only'  => [ [ 'manage_options' ], true, true, false ],
			'both'                => [ [ 'manage_lms', 'manage_options' ], true, true, true ],
			'editor (edit_posts)' => [ [ 'edit_posts' ], false, false, false ],
		];
	}
}
