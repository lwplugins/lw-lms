<?php
/**
 * Options Service for LW Site Manager abilities.
 *
 * @package LightweightPlugins\LMS
 */

declare(strict_types=1);

namespace LightweightPlugins\LMS\SiteManager\Service;

use LightweightPlugins\LMS\Options;

/**
 * Executes the get-options ability.
 */
final class OptionsService {

	/**
	 * Get global LW LMS plugin options.
	 *
	 * @param array<string, mixed> $input Input parameters (unused).
	 * @return array<string, mixed>
	 */
	public static function get_options( array $input ): array { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.Found -- Required by ability callback interface.
		return [
			'success' => true,
			'options' => Options::get_all(),
		];
	}
}
