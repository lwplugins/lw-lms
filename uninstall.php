<?php
/**
 * Uninstall script for LW LMS.
 *
 * @package LightweightPlugins\LMS
 */

// Exit if not uninstalling.
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

global $wpdb;

// Delete options.
delete_option( 'lw_lms_options' );
delete_option( 'lw_lms_db_version' );

// Delete post meta.
$wpdb->query(
	$wpdb->prepare(
		"DELETE FROM {$wpdb->postmeta} WHERE meta_key LIKE %s",
		'_lw_lms_%'
	)
);

// Drop progress table.
$lw_lms_table = $wpdb->prefix . 'lms_progress';
// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Uninstall script.
$wpdb->query( "DROP TABLE IF EXISTS {$lw_lms_table}" );

// Flush rewrite rules.
flush_rewrite_rules();
