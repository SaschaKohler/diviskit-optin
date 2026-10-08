<?php
/**
 * Plugin Name: Skit Optin
 * Plugin URI: https://github.com/SaschaKohler/skit-optin
 * Description: Eigenes Double-Opt-In für Newsletter-Signups: gebrandete deutsche Bestätigungsmail aus frei editierbaren Mail-Templates, DSGVO-Einwilligungsnachweis mit Consent-Text, reCAPTCHA v3 + Honeypot. Bestätigte Subscriber werden an den gewählten Provider (MailerLite, Brevo, generischer Webhook) übergeben — deren kostenpflichtiges DOI wird umgangen.
 * Version: 0.7.0
 * Author: Sascha Kohler
 * Author URI: https://diviskit.com
 * License: GPLv2 or later
 * Text Domain: skit-optin
 * Requires at least: 6.2
 * Requires PHP: 7.4
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

if ( ! defined( 'SKIT_OPTIN_VERSION' ) ) {
    define( 'SKIT_OPTIN_VERSION', '0.7.0' );
}
if ( ! defined( 'SKIT_OPTIN_DB_VERSION' ) ) {
    define( 'SKIT_OPTIN_DB_VERSION', '2' );
}
if ( ! defined( 'SKIT_OPTIN_PATH' ) ) {
    define( 'SKIT_OPTIN_PATH', plugin_dir_path( __FILE__ ) );
}
if ( ! defined( 'SKIT_OPTIN_URL' ) ) {
    define( 'SKIT_OPTIN_URL', plugin_dir_url( __FILE__ ) );
}
if ( ! defined( 'SKIT_OPTIN_OPTION' ) ) {
    define( 'SKIT_OPTIN_OPTION', 'skit_optin_options' );
}
if ( ! defined( 'SKIT_OPTIN_TEMPLATES_OPTION' ) ) {
    define( 'SKIT_OPTIN_TEMPLATES_OPTION', 'skit_optin_templates' );
}

require_once SKIT_OPTIN_PATH . 'includes/options.php';
require_once SKIT_OPTIN_PATH . 'includes/subscribers.php';
require_once SKIT_OPTIN_PATH . 'includes/templates.php';
require_once SKIT_OPTIN_PATH . 'includes/mailer.php';
require_once SKIT_OPTIN_PATH . 'includes/providers.php';
require_once SKIT_OPTIN_PATH . 'includes/rest.php';
require_once SKIT_OPTIN_PATH . 'includes/form.php';

if ( is_admin() ) {
    require_once SKIT_OPTIN_PATH . 'includes/admin.php';
}

/**
 * On activation: create the subscriber table, seed the templates option
 * (autoload off — template HTML can be sizeable) and schedule the daily
 * housekeeping event (expire stale pending rows, retry failed syncs).
 */
function skit_activate() {
    skit_migrate_legacy();
    skit_create_table();
    skit_templates_init();
    if ( ! wp_next_scheduled( 'skit_optin_daily' ) ) {
        wp_schedule_event( time() + HOUR_IN_SECONDS, 'daily', 'skit_optin_daily' );
    }
    set_transient( 'skit_optin_activated', 1, MINUTE_IN_SECONDS );
}
register_activation_hook( __FILE__, 'skit_activate' );

function skit_deactivate() {
    wp_clear_scheduled_hook( 'skit_optin_daily' );
}
register_deactivation_hook( __FILE__, 'skit_deactivate' );

/**
 * Daily housekeeping: expire pending tokens past their TTL and retry the
 * provider sync for confirmed rows that never made it to the list.
 */
function skit_daily_tasks() {
    skit_expire_pending();
    skit_retry_provider_sync();
}
add_action( 'skit_optin_daily', 'skit_daily_tasks' );

/**
 * Legacy migrations — the plugin was renamed twice:
 *   sk-mailerlite-doi (<=0.3.0) → diviskit-optin (0.4.x–0.6.x) → skit-optin
 * Each step copies the options, renames the subscriber table and moves the
 * cron hook. Runs on every load but is a no-op once the legacy
 * options/table are gone.
 */
function skit_migrate_legacy() {
    // phpcs:ignore -- legacy identifiers of this plugin's former names.
    skit_migrate_from( 'skml_doi_options', 'skml_doi_db_version', '', 'skml_subscribers', 'skml_doi_daily' );
    // phpcs:ignore -- legacy identifiers of this plugin's former name (diviskit-optin).
    skit_migrate_from( 'diviskit_optin_options', 'diviskit_optin_db_version', 'diviskit_optin_templates', 'diviskit_optin_subscribers', 'diviskit_optin_daily' );
}
add_action( 'plugins_loaded', 'skit_migrate_legacy', 1 );

/**
 * Migrate one legacy install: options + db version + templates option,
 * subscriber table rename/merge, cron hook move.
 */
function skit_migrate_from( $old_opt, $old_dbv, $old_tpl, $old_table_suffix, $old_cron ) {
    $old_options = get_option( $old_opt );
    if ( false !== $old_options ) {
        if ( false === get_option( SKIT_OPTIN_OPTION ) ) {
            // The builtin template id 'diviskit' was renamed to 'skit'.
            if ( isset( $old_options['active_template'] ) && 'diviskit' === $old_options['active_template'] ) {
                $old_options['active_template'] = 'skit';
            }
            add_option( SKIT_OPTIN_OPTION, $old_options );
        }
        delete_option( $old_opt );
    }

    $old_db_version = get_option( $old_dbv );
    if ( false !== $old_db_version ) {
        if ( false === get_option( 'skit_optin_db_version' ) ) {
            add_option( 'skit_optin_db_version', $old_db_version );
        }
        delete_option( $old_dbv );
    }

    if ( '' !== $old_tpl ) {
        $old_templates = get_option( $old_tpl );
        if ( false !== $old_templates ) {
            if ( false === get_option( SKIT_OPTIN_TEMPLATES_OPTION ) ) {
                if ( is_array( $old_templates ) && isset( $old_templates['diviskit'] ) && ! isset( $old_templates['skit'] ) ) {
                    $old_templates['skit'] = $old_templates['diviskit'];
                    unset( $old_templates['diviskit'] );
                }
                skit_templates_init();
                update_option( SKIT_OPTIN_TEMPLATES_OPTION, $old_templates );
            }
            delete_option( $old_tpl );
        }
    }

    global $wpdb;
    $old_table = $wpdb->prefix . $old_table_suffix;
    $new_table = skit_table();
    // phpcs:disable WordPress.DB.DirectDatabaseQuery -- one-time schema migration, no WP API equivalent.
    if ( $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $old_table ) ) === $old_table ) {
        if ( $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $new_table ) ) !== $new_table ) {
            $wpdb->query( $wpdb->prepare( 'RENAME TABLE %i TO %i', $old_table, $new_table ) );
        } else {
            // Both exist (e.g. new table created before migration ran):
            // merge rows by PK/email uniqueness, then drop the old table.
            $wpdb->query( $wpdb->prepare( 'INSERT IGNORE INTO %i SELECT * FROM %i', $new_table, $old_table ) );
            $wpdb->query( $wpdb->prepare( 'DROP TABLE %i', $old_table ) );
        }
    }
    // phpcs:enable WordPress.DB.DirectDatabaseQuery

    if ( wp_next_scheduled( $old_cron ) ) {
        wp_clear_scheduled_hook( $old_cron );
        if ( ! wp_next_scheduled( 'skit_optin_daily' ) ) {
            wp_schedule_event( time() + HOUR_IN_SECONDS, 'daily', 'skit_optin_daily' );
        }
    }
}

/**
 * Upgrade path: create the table if the plugin was updated without
 * re-activation (e.g. files replaced via ZIP upload).
 */
function skit_maybe_upgrade() {
    if ( get_option( 'skit_optin_db_version' ) !== SKIT_OPTIN_DB_VERSION ) {
        skit_create_table();
    }
}
add_action( 'plugins_loaded', 'skit_maybe_upgrade' );
