<?php
/**
 * Diviskit Optin — uninstall.
 *
 * Drops the subscriber table and removes all plugin data.
 * NOTE: the subscriber table is the legal DOI record — deleting it destroys
 * your Einwilligungsnachweise. Export CSV (admin → Diviskit Optin)
 * BEFORE uninstalling if you need to keep the proof.
 *
 * Also removes leftovers from the former sk-mailerlite-doi install, in
 * case the 0.4.0 migration never ran.
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
    exit;
}

delete_option( 'diviskit_optin_options' );
delete_option( 'diviskit_optin_templates' );
delete_option( 'diviskit_optin_db_version' );
wp_clear_scheduled_hook( 'diviskit_optin_daily' );

// Legacy sk-mailerlite-doi data (pre-rename).
delete_option( 'skml_doi_options' );
delete_option( 'skml_doi_db_version' );
wp_clear_scheduled_hook( 'skml_doi_daily' );

global $wpdb;
// phpcs:disable WordPress.DB.DirectDatabaseQuery -- uninstall intentionally drops the plugin tables.
$wpdb->query( $wpdb->prepare( 'DROP TABLE IF EXISTS %i', $wpdb->prefix . 'diviskit_optin_subscribers' ) );
$wpdb->query( $wpdb->prepare( 'DROP TABLE IF EXISTS %i', $wpdb->prefix . 'skml_subscribers' ) );
// phpcs:enable WordPress.DB.DirectDatabaseQuery
