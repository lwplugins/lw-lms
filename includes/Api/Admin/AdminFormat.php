<?php
/**
 * Shared formatting of admin REST payloads.
 *
 * @package LightweightPlugins\LMS
 */

declare(strict_types=1);

namespace LightweightPlugins\LMS\Api\Admin;

/**
 * Dates, users and posts as the admin screens show them. Dates are formatted
 * on the server with the site's own date and time formats.
 */
final class AdminFormat {

	/**
	 * A site-local MySQL datetime (granted_at, submitted_at), formatted.
	 *
	 * @param string $local Site-local MySQL datetime.
	 * @return string
	 */
	public static function local_datetime( string $local ): string {
		$formatted = mysql2date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), $local );

		return is_string( $formatted ) ? $formatted : $local;
	}

	/**
	 * A UTC MySQL datetime (expires_at) as a site-time date, or null.
	 *
	 * @param string|null $utc UTC MySQL datetime.
	 * @return string|null
	 */
	public static function utc_date( ?string $utc ): ?string {
		if ( null === $utc || '' === $utc ) {
			return null;
		}

		$timestamp = strtotime( $utc . ' UTC' );

		if ( false === $timestamp ) {
			return null;
		}

		$formatted = wp_date( (string) get_option( 'date_format' ), $timestamp );

		return is_string( $formatted ) ? $formatted : null;
	}

	/**
	 * A user as the lists show them, or null when the user no longer exists.
	 *
	 * @param int  $user_id    User ID.
	 * @param bool $with_email Include the email address.
	 * @return array<string, mixed>|null
	 */
	public static function user( int $user_id, bool $with_email = true ): ?array {
		$user = get_userdata( $user_id );

		if ( ! $user ) {
			return null;
		}

		$out = [
			'id'   => $user_id,
			'name' => '' !== $user->display_name ? $user->display_name : $user->user_login,
		];

		if ( $with_email ) {
			$out['email'] = $user->user_email;
		}

		return $out;
	}

	/**
	 * A course or lesson as the lists show them, or null when it is gone.
	 *
	 * @param int $post_id Post ID.
	 * @return array<string, mixed>|null
	 */
	public static function post( int $post_id ): ?array {
		$post = $post_id > 0 ? get_post( $post_id ) : null;

		if ( ! $post ) {
			return null;
		}

		$edit = get_edit_post_link( $post_id, 'raw' );

		return [
			'id'      => $post_id,
			'title'   => '' !== $post->post_title ? $post->post_title : __( '(no title)', 'lw-lms' ),
			'editUrl' => is_string( $edit ) ? $edit : '',
		];
	}

	/**
	 * Load the users and posts of a page of rows in two queries.
	 *
	 * @param array<int, int> $user_ids User IDs.
	 * @param array<int, int> $post_ids Post IDs.
	 * @return void
	 */
	public static function prime( array $user_ids, array $post_ids ): void {
		$user_ids = array_values( array_unique( array_filter( $user_ids ) ) );
		$post_ids = array_values( array_unique( array_filter( $post_ids ) ) );

		if ( [] !== $user_ids && function_exists( 'cache_users' ) ) {
			cache_users( $user_ids );
		}

		if ( [] !== $post_ids && function_exists( '_prime_post_caches' ) ) {
			_prime_post_caches( $post_ids, false, false );
		}
	}
}
