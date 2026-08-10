<?php

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
    exit;
}

$settings = get_option( 'wpitk_settings', array() );

if ( empty( $settings['remove_data_on_uninstall'] ) ) {
    return;
}

global $wpdb;
$table = $wpdb->prefix . 'wpitk_webhook_logs';
$wpdb->query( "DROP TABLE IF EXISTS {$table}" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.DirectDatabaseQuery.SchemaChange

delete_option( 'wpitk_settings' );
delete_option( 'wpitk_version' );
