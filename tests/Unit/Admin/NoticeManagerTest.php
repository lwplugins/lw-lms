<?php
/**
 * NoticeManager unit tests.
 *
 * @package LightweightPlugins\LMS
 */

declare(strict_types=1);

namespace LightweightPlugins\LMS\Tests\Unit\Admin;

use Brain\Monkey\Actions;
use Brain\Monkey\Filters;
use Brain\Monkey\Functions;
use LightweightPlugins\LMS\Admin\NoticeManager;
use LightweightPlugins\LMS\Tests\Unit\MonkeyTestCase;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;

final class NoticeManagerTest extends MonkeyTestCase {

	// Counts the Brain Monkey expectations as assertions; the base class does not.
	use MockeryPHPUnitIntegration;

	protected function tearDown(): void {
		unset( $GLOBALS['plugin_page'], $GLOBALS['wp_filter'] );
		parent::tearDown();
	}

	/**
	 * @return array<string, array{0: mixed, 1: bool}>
	 */
	public static function callback_provider(): array {
		return [
			'LW static method string' => [ 'LightweightPlugins\\LMS\\Admin\\AdminNotice::render', true ],
			'LW class array'          => [ [ 'LightweightPlugins\\SEO\\Admin\\NoticeManager', 'open_wrap' ], true ],
			'LW object array'         => [ [ new NoticeManagerTestOwn(), 'render' ], true ],
			'LW closure'              => [ static function (): void {}, true ],
			'core function'           => [ 'wp_admin_notice', false ],
			'theme class array'       => [ [ 'TGM_Plugin_Activation', 'notices' ], false ],
			'theme object array'      => [ [ new \ArrayObject(), 'count' ], false ],
			'global closure'          => [ eval( 'return static function (): void {};' ), false ], // phpcs:ignore Squiz.PHP.Eval.Discouraged -- a closure declared outside any namespace.
			'look-alike namespace'    => [ 'LightweightPluginsFake\\Notice::render', false ],
		];
	}

	/**
	 * @dataProvider callback_provider
	 *
	 * @param mixed $callback Hook callback.
	 * @param bool  $expected Whether it is an LW callback.
	 */
	public function test_tells_lw_callbacks_from_the_rest( $callback, bool $expected ): void {
		$this->assertSame( $expected, NoticeManager::is_own( $callback ) );
	}

	public function test_removes_only_foreign_callbacks_on_lw_pages(): void {
		$this->on_lw_page();
		$foreign             = [ 'TGM_Plugin_Activation', 'notices' ];
		$GLOBALS['wp_filter'] = [
			'admin_notices'     => (object) [
				'callbacks' => [
					10 => [
						'a' => [ 'function' => $foreign ],
						'b' => [ 'function' => 'LightweightPlugins\\LMS\\Admin\\AdminNotice::render' ],
					],
				],
			],
			'all_admin_notices' => (object) [ 'callbacks' => [ 5 => [ 'c' => [ 'function' => 'brooklyn_purchase_notice' ] ] ] ],
		];

		Functions\expect( 'remove_action' )->once()->with( 'admin_notices', $foreign, 10 );
		Functions\expect( 'remove_action' )->once()->with( 'all_admin_notices', 'brooklyn_purchase_notice', 5 );

		NoticeManager::isolate();
	}

	public function test_leaves_other_admin_pages_alone(): void {
		$GLOBALS['plugin_page'] = 'woocommerce';
		Functions\when( 'get_admin_page_parent' )->justReturn( 'woocommerce' );
		$GLOBALS['wp_filter'] = [ 'admin_notices' => (object) [ 'callbacks' => [ 10 => [ 'a' => [ 'function' => 'brooklyn_purchase_notice' ] ] ] ] ];

		Functions\expect( 'remove_action' )->never();

		NoticeManager::isolate();
	}

	public function test_recognises_the_lw_plugins_overview_page(): void {
		$GLOBALS['plugin_page'] = 'lw-plugins';
		Functions\when( 'get_admin_page_parent' )->justReturn( '' );

		$this->assertTrue( NoticeManager::is_lw_page() );
	}

	public function test_adds_the_body_class_once(): void {
		$this->on_lw_page();

		$this->assertSame( 'a lw-plugins-admin-page', NoticeManager::body_class( NoticeManager::body_class( 'a' ) ) );
	}

	public function test_registers_its_hooks_once_however_often_it_is_called(): void {
		( new \ReflectionProperty( NoticeManager::class, 'registered' ) )->setValue( null, false );

		Actions\expectAdded( 'in_admin_header' )->once();
		Actions\expectAdded( 'admin_head' )->once();
		Filters\expectAdded( 'admin_body_class' )->once();

		NoticeManager::register();
		NoticeManager::init();
	}

	private function on_lw_page(): void {
		$GLOBALS['plugin_page'] = 'lw-lms';
		Functions\when( 'get_admin_page_parent' )->justReturn( 'lw-plugins' );
	}
}

/**
 * An object whose class lives in the LW namespace.
 */
final class NoticeManagerTestOwn {

	public function render(): void {}
}
