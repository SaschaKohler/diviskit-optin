<?php
/**
 * Skit Optin — confirmation mail.
 *
 * Renders the active mail template (admin → Skit Optin → Mail-Templates)
 * and sends it via wp_mail — use an SMTP plugin / mail service for reliable
 * delivery. Template markup should stay table-based with inline styles:
 * email clients don't do modern CSS.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

function skit_confirm_url( $token ) {
    return rest_url( 'skit-optin/v1/confirm?token=' . rawurlencode( $token ) );
}

/**
 * @return bool wp_mail result
 */
function skit_send_confirm_mail( $email, $token ) {
    $o   = skit_options();
    $tpl = skit_active_template();

    $host    = wp_parse_url( home_url(), PHP_URL_HOST );
    $headers = array(
        'Content-Type: text/html; charset=UTF-8',
        'From: ' . get_bloginfo( 'name' ) . ' <noreply@' . $host . '>',
    );

    return wp_mail(
        $email,
        strtr( $o['mail_subject'], skit_template_vars( $email, skit_confirm_url( $token ) ) ),
        skit_render_template( $tpl['html'], $email, skit_confirm_url( $token ) ),
        $headers
    );
}

/**
 * Test mail from the templates admin screen — renders the given template
 * with a dummy token so the confirm link looks exactly like production.
 *
 * @return bool wp_mail result
 */
function skit_send_test_mail( $template_id, $to ) {
    $tpl = skit_template( $template_id );
    if ( ! $tpl || ! is_email( $to ) ) {
        return false;
    }
    $o       = skit_options();
    $url     = skit_confirm_url( str_repeat( 'a', 64 ) );
    $host    = wp_parse_url( home_url(), PHP_URL_HOST );
    $headers = array(
        'Content-Type: text/html; charset=UTF-8',
        'From: ' . get_bloginfo( 'name' ) . ' <noreply@' . $host . '>',
    );

    return wp_mail(
        $to,
        '[TEST] ' . strtr( $o['mail_subject'], skit_template_vars( $to, $url ) ),
        skit_render_template( $tpl['html'], $to, $url ),
        $headers
    );
}
