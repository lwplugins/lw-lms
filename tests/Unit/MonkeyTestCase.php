<?php
/**
 * Base test case wiring Brain Monkey setup/teardown.
 *
 * @package LightweightPlugins\LMS
 */

declare(strict_types=1);

namespace LightweightPlugins\LMS\Tests\Unit;

use Brain\Monkey;
use PHPUnit\Framework\TestCase;

/**
 * Extends PHPUnit's TestCase with Brain Monkey lifecycle hooks so WordPress
 * functions can be stubbed without loading WordPress.
 */
abstract class MonkeyTestCase extends TestCase {

	/**
	 * Set up Brain Monkey before each test.
	 *
	 * @return void
	 */
	protected function setUp(): void {
		parent::setUp();
		Monkey\setUp();
	}

	/**
	 * Tear down Brain Monkey after each test.
	 *
	 * @return void
	 */
	protected function tearDown(): void {
		// Count Brain Monkey / Mockery expectations (expect()->once(), …) as
		// assertions, so a test that only sets expectations is not "risky".
		$this->addToAssertionCount( \Mockery::getContainer()->mockery_getExpectationCount() );
		Monkey\tearDown();
		parent::tearDown();
	}
}
