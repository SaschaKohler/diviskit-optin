<?php
/**
 * Skit Optin — REST endpoints.
 *
 * POST /wp-json/skit-optin/v1/subscribe  { email, consent, website (honeypot), recaptcha }
 * GET  /wp-json/skit-optin/v1/confirm?token=…  → confirm + provider sync + redirect
 *
 * Legacy namespaces skml/v1 (sk-mailerlite-doi <=0.3.0) and
 * diviskit-optin/v1 (diviskit-optin 0.4–0.6) stay registered:
 * confirmation links already sent by mail must keep working.
 *
 * Both endpoints are public by design — spam surface is covered by
 * honeypot, per-IP rate limit and optional reCAPTCHA v3 (score-based,
 * invisible).
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

function skit_rest_init() {
    foreach ( array( 'skit-optin/v1', 'diviskit-optin/v1', 'skml/v1' ) as $ns ) {
        register_rest_route( $ns, '/subscribe', array(
            'methods'             => 'POST',
            'permission_callback' => '__return_true',
            'callback'            => 'skit_rest_subscribe',
            'args'                => array(
                'email'     => array( 'required' => true, 'type' => 'string' ),
                'consent'   => array( 'required' => true, 'type' => 'boolean' ),
                'interests' => array( 'type' => 'array', 'default' => array(), 'items' => array( 'type' => 'string' ) ),
                'website'   => array( 'type' => 'string', 'default' => '' ), // honeypot
                'recaptcha' => array( 'type' => 'string', 'default' => '' ),
            ),
        ) );

        register_rest_route( $ns, '/confirm', array(
            'methods'             => 'GET',
            'permission_callback' => '__return_true',
            'callback'            => 'skit_rest_confirm',
            'args'                => array(
                'token' => array( 'required' => true, 'type' => 'string' ),
            ),
        ) );
    }
}
add_action( 'rest_api_init', 'skit_rest_init' );

/* ------------------------------------------------------------------ */
/*  POST /subscribe                                                    */
/* ------------------------------------------------------------------ */

function skit_rest_subscribe( WP_REST_Request $req ) {
    $success = array( 'ok' => true, 'message' => skit_opt( 'form_success' ) );

    // Honeypot: bots filling the hidden "website" field get a silent OK.
    if ( '' !== trim( (string) $req->get_param( 'website' ) ) ) {
        return $success;
    }

    // Per-IP rate limit: 5 submits / 10 min.
    $ip    = skit_client_ip();
    $key   = 'skit_rl_' . md5( $ip ? $ip : 'na' );
    $count = (int) get_transient( $key );
    if ( $count >= 5 ) {
        return new WP_Error( 'skit_rate', 'Zu viele Anfragen. Bitte später erneut versuchen.', array( 'status' => 429 ) );
    }
    set_transient( $key, $count + 1, 10 * MINUTE_IN_SECONDS );

    $email = sanitize_email( $req->get_param( 'email' ) );
    if ( ! is_email( $email ) ) {
        return new WP_Error( 'skit_email', 'Bitte gib eine gültige E-Mail-Adresse ein.', array( 'status' => 400 ) );
    }

    if ( ! $req->get_param( 'consent' ) ) {
        return new WP_Error( 'skit_consent', 'Bitte bestätige die Einwilligung.', array( 'status' => 400 ) );
    }

    $captcha = skit_verify_recaptcha( (string) $req->get_param( 'recaptcha' ) );
    if ( is_wp_error( $captcha ) ) {
        return $captcha;
    }

    // Already fully subscribed → neutral success, no new mail, no enumeration.
    $existing = skit_find_by_email( $email );
    if ( $existing && 'confirmed' === $existing['status'] && $existing['ml_synced_at'] ) {
        return $success;
    }

    $interests = skit_sanitize_interests( (array) $req->get_param( 'interests' ) );

    $row = skit_upsert_pending( $email, (string) skit_opt( 'consent_text' ), $interests );
    if ( ! $row['id'] ) {
        return new WP_Error( 'skit_store', 'Speichern fehlgeschlagen. Bitte später erneut versuchen.', array( 'status' => 500 ) );
    }

    if ( ! skit_send_confirm_mail( $email, $row['token'] ) ) {
        return new WP_Error( 'skit_mail', 'Die Bestätigungs-E-Mail konnte nicht gesendet werden. Bitte später erneut versuchen.', array( 'status' => 500 ) );
    }

    return $success;
}

/**
 * reCAPTCHA v3 verify: token must be fresh, action "subscribe", and
 * Google's score must clear 0.5. Keys empty → check skipped entirely.
 */
function skit_verify_recaptcha( $token ) {
    if ( ! skit_recaptcha_active() ) {
        return true; // disabled or keys missing → honeypot + rate limit only
    }
    $secret = trim( (string) skit_opt( 'recaptcha_secret_key' ) );
    if ( '' === $token ) {
        return new WP_Error( 'skit_captcha', 'reCAPTCHA fehlt. Bitte Seite neu laden.', array( 'status' => 400 ) );
    }
    $res = wp_remote_post( 'https://www.google.com/recaptcha/api/siteverify', array(
        'timeout' => 10,
        'body'    => array(
            'secret'   => $secret,
            'response' => $token,
            'remoteip' => skit_client_ip(),
        ),
    ) );
    if ( is_wp_error( $res ) ) {
        return new WP_Error( 'skit_captcha_net', 'reCAPTCHA-Prüfung derzeit nicht möglich.', array( 'status' => 502 ) );
    }
    $data = json_decode( wp_remote_retrieve_body( $res ), true );
    if ( empty( $data['success'] ) ) {
        return new WP_Error( 'skit_captcha', 'reCAPTCHA-Prüfung fehlgeschlagen.', array( 'status' => 400 ) );
    }
    if ( isset( $data['action'] ) && 'subscribe' !== $data['action'] ) {
        return new WP_Error( 'skit_captcha_action', 'reCAPTCHA-Action ungültig.', array( 'status' => 400 ) );
    }
    if ( isset( $data['score'] ) && (float) $data['score'] < 0.5 ) {
        return new WP_Error( 'skit_captcha_score', 'Anmeldung wurde als Spam eingestuft.', array( 'status' => 400 ) );
    }
    return true;
}

/* ------------------------------------------------------------------ */
/*  GET /confirm                                                       */
/* ------------------------------------------------------------------ */

function skit_redirect_url( $option_key, $fallback_status ) {
    $url = trim( (string) skit_opt( $option_key ) );
    if ( '' === $url ) {
        $url = add_query_arg( 'optin', $fallback_status, home_url( '/' ) );
    }
    return $url;
}

function skit_rest_confirm( WP_REST_Request $req ) {
    $ok_url  = skit_redirect_url( 'redirect_confirm', 'confirmed' );
    $err_url = skit_redirect_url( 'redirect_error', 'error' );

    $token = sanitize_text_field( $req->get_param( 'token' ) );
    if ( ! preg_match( '/^[a-f0-9]{64}$/', $token ) ) {
        wp_safe_redirect( $err_url );
        exit;
    }

    $row = skit_find_by_token( $token );

    // Unknown token OR already consumed (token_hash cleared on confirm).
    if ( ! $row || 'pending' !== $row['status'] ) {
        wp_safe_redirect( $err_url );
        exit;
    }

    if ( strtotime( $row['expires_at'] ) < current_time( 'timestamp' ) ) {
        global $wpdb;
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery -- custom table, single-row status update.
        $wpdb->update( skit_table(), array( 'status' => 'expired', 'token_hash' => '' ), array( 'id' => (int) $row['id'] ) );
        wp_safe_redirect( $err_url );
        exit;
    }

    skit_confirm_row( (int) $row['id'] );

    // Push to the configured provider; failures are stored on the row and
    // retried by the daily cron.
    $interests = isset( $row['interests'] ) && '' !== (string) $row['interests']
        ? explode( ',', $row['interests'] )
        : array();
    skit_mark_sync_result( (int) $row['id'], skit_push_subscriber( $row['email'], $interests ) );

    wp_safe_redirect( $ok_url );
    exit;
}
