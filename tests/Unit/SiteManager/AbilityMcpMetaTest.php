<?php
/**
 * Tests that the Site Manager abilities opt in to MCP discovery.
 *
 * @package LightweightPlugins\LMS
 */

declare(strict_types=1);

namespace LightweightPlugins\LMS\Tests\Unit\SiteManager;

use Brain\Monkey\Functions;
use LightweightPlugins\LMS\SiteManager\Abilities\AbilityPermissions;
use LightweightPlugins\LMS\SiteManager\Abilities\CourseAbilities;
use LightweightPlugins\LMS\SiteManager\Abilities\OptionsAbilities;
use LightweightPlugins\LMS\SiteManager\Abilities\ProgressAbilities;
use LightweightPlugins\LMS\Tests\Unit\MonkeyTestCase;

/**
 * Regression: the abilities carried show_in_rest but no `mcp` meta, so LW
 * Site Manager's MCP server (which only auto-exposes site-manager/*) never
 * listed them.
 *
 * @covers \LightweightPlugins\LMS\SiteManager\Abilities\AbilityMeta
 */
final class AbilityMcpMetaTest extends MonkeyTestCase {

	/**
	 * Registers the abilities with a deny-all permission manager.
	 *
	 * @return array<string, array<string, mixed>> Registration args by ability name.
	 */
	private function registered(): array {
		$registered = [];

		Functions\stubTranslationFunctions();
		Functions\when( 'wp_register_ability' )->alias(
			static function ( string $name, array $args ) use ( &$registered ): void {
				$registered[ $name ] = $args;
			}
		);

		$permissions = new AbilityPermissions(
			new class() {
				public function callback( string $check ): callable {
					return static fn (): bool => false;
				}
			}
		);

		CourseAbilities::register( $permissions );
		OptionsAbilities::register( $permissions );
		ProgressAbilities::register( $permissions );

		return $registered;
	}

	public function test_every_ability_is_a_public_mcp_tool(): void {
		$registered = $this->registered();

		$this->assertNotEmpty( $registered );
		foreach ( $registered as $name => $args ) {
			$this->assertSame(
				[
					'public' => true,
					'type'   => 'tool',
				],
				$args['meta']['mcp'] ?? null,
				$name
			);
			$this->assertTrue( $args['meta']['show_in_rest'], $name );
		}
	}

	public function test_mcp_exposure_does_not_bypass_the_permission_callbacks(): void {
		Functions\when( 'current_user_can' )->justReturn( false );

		foreach ( $this->registered() as $name => $args ) {
			$this->assertFalse( (bool) call_user_func( $args['permission_callback'] ), $name );
		}
	}
}
