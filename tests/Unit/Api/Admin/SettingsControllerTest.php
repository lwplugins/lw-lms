<?php
/**
 * Tests for the settings REST controller.
 *
 * @package LightweightPlugins\LMS
 */

declare(strict_types=1);

namespace LightweightPlugins\LMS\Tests\Unit\Api\Admin;

use Brain\Monkey\Functions;
use LightweightPlugins\LMS\Api\Admin\SettingsController;
use LightweightPlugins\LMS\Options;
use WP_Error;
use WP_REST_Request;

/**
 * @covers \LightweightPlugins\LMS\Api\Admin\SettingsController
 * @covers \LightweightPlugins\LMS\Api\Admin\Settings\SettingsInput
 * @covers \LightweightPlugins\LMS\Api\Admin\Settings\SettingsSchema
 * @covers \LightweightPlugins\LMS\Api\Admin\Settings\SettingsResponse
 */
final class SettingsControllerTest extends AdminRestTestCase {

	protected function setUp(): void {
		parent::setUp();
		Functions\when( 'rest_url' )->alias( static fn ( string $path = '' ): string => 'https://example.test/wp-json/' . $path );
	}

	/**
	 * POST a body.
	 *
	 * @param array<string, mixed> $body Body.
	 * @return mixed
	 */
	private function post( array $body ) {
		return $this->data( ( new SettingsController() )->save_settings( new WP_REST_Request( $body, 'POST' ) ) );
	}

	/**
	 * Stored options.
	 *
	 * @return array<string, mixed>
	 */
	private function saved(): array {
		return (array) ( $this->store[ Options::OPTION_NAME ] ?? [] );
	}

	public function test_saving_one_switch_never_turns_the_others_off(): void {
		$this->store[ Options::OPTION_NAME ] = array_merge( Options::get_defaults(), [ 'require_quiz_pass' => true ] );

		$this->post( [ 'auto_enroll_admins' => true ] );

		$this->assertTrue( $this->saved()['auto_enroll_admins'] );
		$this->assertTrue( $this->saved()['require_quiz_pass'] );
		$this->assertTrue( $this->saved()['woo_enabled'] );
	}

	public function test_an_invalid_field_saves_nothing(): void {
		$result = $this->post(
			[
				'auto_enroll_admins'   => true,
				'quiz_pass_percentage' => 150,
			]
		);

		$this->assertInvalid( $result, [ 'quiz_pass_percentage' ] );
		$this->assertSame( [], $this->saved() );
	}

	/**
	 * @dataProvider provide_invalid_values
	 */
	public function test_rejects_values_outside_the_rules( string $key, mixed $value ): void {
		$this->assertInvalid( $this->post( [ $key => $value ] ), [ $key ] );
	}

	public static function provide_invalid_values(): array {
		return [
			'page size zero'        => [ 'courses_per_page', 0 ],
			'page size too big'     => [ 'courses_per_page', 101 ],
			'fraction'              => [ 'quiz_pass_percentage', '50.5' ],
			'unknown access type'   => [ 'default_access_type', 'members' ],
			'switch as string'      => [ 'woo_enabled', 'yes' ],
			'unknown setting'       => [ 'show_progress_bar', true ],
			'access type as number' => [ 'default_access_type', 1 ],
		];
	}

	public function test_a_numeric_string_is_saved_as_a_number(): void {
		$this->post( [ 'courses_per_page' => '25' ] );

		$this->assertSame( 25, $this->saved()['courses_per_page'] );
	}

	public function test_a_too_large_body_is_refused(): void {
		$request = new WP_REST_Request( [], 'POST', '', str_repeat( ' ', SettingsController::MAX_BYTES + 1 ) );

		$result = ( new SettingsController() )->save_settings( $request );

		$this->assertInstanceOf( WP_Error::class, $result );
		$this->assertSame( 413, $result->get_error_data()['status'] );
	}

	public function test_get_returns_typed_options_and_the_public_endpoints(): void {
		$this->store[ Options::OPTION_NAME ] = [ 'courses_per_page' => '12' ];

		$data = ( new SettingsController() )->get_settings()->get_data();

		$this->assertSame( 12, $data['options']['courses_per_page'] );
		$this->assertSame( [ 'min' => 1, 'max' => 100 ], $data['meta']['ranges']['courses_per_page'] );
		$this->assertSame( [ 'open', 'free', 'paid' ], $data['meta']['enums']['default_access_type'] );
		$this->assertCount( 8, $data['meta']['endpoints'] );
	}
}
