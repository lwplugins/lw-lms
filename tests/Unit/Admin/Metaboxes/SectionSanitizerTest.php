<?php
/**
 * Tests for the course builder's section sanitizer.
 *
 * @package LightweightPlugins\LMS
 */

declare(strict_types=1);

namespace LightweightPlugins\LMS\Tests\Unit\Admin\Metaboxes;

use Brain\Monkey\Functions;
use LightweightPlugins\LMS\Admin\Metaboxes\SectionSanitizer;
use LightweightPlugins\LMS\Drip\DripRule;
use LightweightPlugins\LMS\Tests\Unit\MonkeyTestCase;

/**
 * @covers \LightweightPlugins\LMS\Admin\Metaboxes\SectionSanitizer
 */
final class SectionSanitizerTest extends MonkeyTestCase {

	protected function setUp(): void {
		parent::setUp();
		Functions\when( 'sanitize_text_field' )->returnArg();
	}

	public function test_keeps_the_fields_a_section_is_made_of(): void {
		$clean = SectionSanitizer::sanitize(
			[
				[
					'id'          => 'sec_intro',
					'title'       => 'Intro',
					'description' => 'The basics',
					'order'       => '2',
					'drip'        => [
						'mode'  => 'previous',
						'value' => 3,
						'unit'  => 'day',
					],
				],
			]
		);

		$this->assertSame(
			[
				[
					'id'          => 'sec_intro',
					'title'       => 'Intro',
					'description' => 'The basics',
					'order'       => 2,
					'drip'        => [
						'mode'  => 'previous',
						'value' => 3,
						'unit'  => 'day',
					],
				],
			],
			$clean
		);
	}

	public function test_section_without_a_drip_rule_gets_the_empty_one(): void {
		$clean = SectionSanitizer::sanitize( [ [ 'id' => 'sec_a' ] ] );

		$this->assertSame( DripRule::none(), $clean[0]['drip'] );
	}

	public function test_garbage_drip_rule_is_dropped(): void {
		$clean = SectionSanitizer::sanitize(
			[
				[
					'id'   => 'sec_a',
					'drip' => 'whenever',
				],
			]
		);

		$this->assertSame( DripRule::none(), $clean[0]['drip'] );
	}

	public function test_unknown_keys_do_not_survive(): void {
		$clean = SectionSanitizer::sanitize(
			[
				[
					'id'      => 'sec_a',
					'evil'    => '<script>alert(1)</script>',
					'lessons' => [ 1, 2, 3 ],
				],
			]
		);

		$this->assertSame( [ 'id', 'title', 'description', 'order', 'drip' ], array_keys( $clean[0] ) );
	}

	public function test_section_id_keeps_its_case_so_lessons_stay_attached(): void {
		$clean = SectionSanitizer::sanitize( [ [ 'id' => 'section-4F2A_b' ] ] );

		$this->assertSame( 'section-4F2A_b', $clean[0]['id'] );
	}

	public function test_characters_that_do_not_belong_in_an_id_are_stripped(): void {
		$clean = SectionSanitizer::sanitize( [ [ 'id' => 'sec a"><b>' ] ] );

		$this->assertSame( 'secab', $clean[0]['id'] );
	}

	/**
	 * @dataProvider provide_unusable_rows
	 *
	 * @param mixed $row A row that cannot become a section.
	 */
	public function test_rows_that_are_not_sections_are_dropped( $row ): void {
		$this->assertSame( [], SectionSanitizer::sanitize( [ $row ] ) );
	}

	/**
	 * @return array<string, array{0: mixed}>
	 */
	public static function provide_unusable_rows(): array {
		return [
			'string'      => [ 'sec_a' ],
			'no id'       => [ [ 'title' => 'Nameless' ] ],
			'empty id'    => [ [ 'id' => '' ] ],
			'id of junk'  => [ [ 'id' => '///' ] ],
			'id is array' => [ [ 'id' => [ 'sec_a' ] ] ],
		];
	}
}
