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
// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Uninstall cleanup.
$wpdb->query(
	$wpdb->prepare(
		"DELETE FROM {$wpdb->postmeta} WHERE meta_key LIKE %s",
		'_lw_lms_%'
	)
);

// Drop custom tables.
$lw_lms_progress_table = $wpdb->prefix . 'lms_progress';
// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.SchemaChange, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Uninstall cleanup.
$wpdb->query( "DROP TABLE IF EXISTS {$lw_lms_progress_table}" );

$lw_lms_access_table = $wpdb->prefix . 'lms_access';
// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.SchemaChange, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Uninstall cleanup.
$wpdb->query( "DROP TABLE IF EXISTS {$lw_lms_access_table}" );

// Flush rewrite rules.
flush_rewrite_rules();
