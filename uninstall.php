<?php
/**
 * Removes the plugin data on uninstall, only when the site owner chose it in the settings.
 *
 * @package Moonetix_Offerwall
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

$moonetix_offerwall_settings = get_option( 'moonetix_offerwall_settings', array() );
if ( empty( $moonetix_offerwall_settings['delete_data'] ) ) {
	return;
}

global $wpdb;
$moonetix_offerwall_table = $wpdb->prefix . 'moonetix_offerwall_log';
// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.SchemaChange -- removing the plugin's own table on uninstall.
$wpdb->query( $wpdb->prepare( 'DROP TABLE IF EXISTS %i', $moonetix_offerwall_table ) );
delete_metadata( 'user', 0, $wpdb->get_blog_prefix() . 'moonetix_offerwall_balance', '', true );
delete_metadata( 'user', 0, $wpdb->get_blog_prefix() . 'moonetix_offerwall_toasts', '', true );
delete_option( 'moonetix_offerwall_settings' );
delete_option( 'moonetix_offerwall_db_version' );
delete_option( 'moonetix_offerwall_last_failure' );
delete_option( 'moonetix_offerwall_welcome' );
delete_option( 'moonetix_offerwall_page' );
