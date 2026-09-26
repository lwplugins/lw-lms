<?php
/**
 * Ability meta helpers.
 *
 * @package LightweightPlugins\LMS
 */

declare(strict_types=1);

namespace LightweightPlugins\LMS\SiteManager\Abilities;

/**
 * Shared meta fragments for ability registrations.
 */
final class AbilityMeta {

	/**
	 * Opt-in for LW Site Manager's MCP server: Site Manager only exposes its
	 * own site-manager/* abilities automatically, so companion abilities must
	 * flag themselves as public MCP tools. Authorization is unchanged — every
	 * call still goes through the ability's permission_callback.
	 */
	private const MCP_META = [
		'public' => true,
		'type'   => 'tool',
	];

	/**
	 * Read-only ability metadata.
	 *
	 * @return array<string, mixed>
	 */
	public static function readonly(): array {
		return [
			'show_in_rest' => true,
			'mcp'          => self::MCP_META,
			'annotations'  => [
				'readonly'    => true,
				'destructive' => false,
				'idempotent'  => true,
			],
		];
	}

	/**
	 * Write ability metadata.
	 *
	 * @return array<string, mixed>
	 */
	public static function write(): array {
		return [
			'show_in_rest' => true,
			'mcp'          => self::MCP_META,
			'annotations'  => [
				'readonly'    => false,
				'destructive' => false,
				'idempotent'  => true,
			],
		];
	}
}
