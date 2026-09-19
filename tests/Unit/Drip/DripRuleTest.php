<?php
/**
 * Tests for drip rule normalization.
 *
 * @package LightweightPlugins\LMS
 */

declare(strict_types=1);

namespace LightweightPlugins\LMS\Tests\Unit\Drip;

use LightweightPlugins\LMS\Drip\DripRule;
use PHPUnit\Framework\TestCase;

/**
 * @covers \LightweightPlugins\LMS\Drip\DripRule
 */
final class DripRuleTest extends TestCase {

	public function test_keeps_a_valid_rule(): void {
		$rule = DripRule::normalize(
			[
				'mode'  => 'enrollment',
				'value' => 3,
				'unit'  => 'week',
			]
		);

		$this->assertSame(
			[
				'mode'  => 'enrollment',
				'value' => 3,
				'unit'  => 'week',
			],
			$rule
		);
	}

	public function test_casts_numeric_strings_from_form_input(): void {
		$rule = DripRule::normalize(
			[
				'mode'  => 'previous',
				'value' => '2',
				'unit'  => 'day',
			]
		);

		$this->assertSame( 2, $rule['value'] );
	}

	/**
	 * @dataProvider provide_invalid_rules
	 *
	 * @param mixed $raw Stored or submitted value.
	 */
	public function test_unusable_input_becomes_no_drip( $raw ): void {
		$this->assertSame( DripRule::none(), DripRule::normalize( $raw ) );
	}

	/**
	 * @return array<string, array{0: mixed}>
	 */
	public static function provide_invalid_rules(): array {
		return [
			'null'            => [ null ],
			'empty string'    => [ '' ],
			'scalar'          => [ 'enrollment' ],
			'no mode'         => [ [ 'value' => 3 ] ],
			'unknown mode'    => [ [ 'mode' => 'lunar_eclipse' ] ],
			'mode none'       => [ [ 'mode' => 'none' ] ],
			'list, not a map' => [ [ 'enrollment', 3, 'day' ] ],
		];
	}

	public function test_unknown_unit_falls_back_to_days(): void {
		$rule = DripRule::normalize(
			[
				'mode'  => 'enrollment',
				'value' => 5,
				'unit'  => 'fortnight',
			]
		);

		$this->assertSame( 'day', $rule['unit'] );
		$this->assertSame( 5, $rule['value'] );
	}

	public function test_negative_value_becomes_zero(): void {
		$rule = DripRule::normalize(
			[
				'mode'  => 'enrollment',
				'value' => -4,
				'unit'  => 'day',
			]
		);

		$this->assertSame( 0, $rule['value'] );
	}

	public function test_value_is_capped(): void {
		$rule = DripRule::normalize(
			[
				'mode'  => 'enrollment',
				'value' => 100000,
				'unit'  => 'month',
			]
		);

		$this->assertSame( DripRule::MAX_VALUE, $rule['value'] );
	}

	public function test_course_delay_rejects_the_previous_mode(): void {
		$rule = DripRule::normalize(
			[
				'mode'  => 'previous',
				'value' => 2,
				'unit'  => 'day',
			],
			[ DripRule::MODE_NONE, DripRule::MODE_ENROLLMENT ]
		);

		$this->assertSame( DripRule::none(), $rule );
	}

	public function test_no_drip_rule_is_not_active(): void {
		$this->assertFalse( DripRule::is_active( DripRule::none() ) );
	}

	public function test_rule_with_a_mode_is_active_even_without_a_delay(): void {
		$rule = DripRule::normalize(
			[
				'mode'  => 'previous',
				'value' => 0,
				'unit'  => 'day',
			]
		);

		$this->assertTrue( DripRule::is_active( $rule ) );
	}
}
