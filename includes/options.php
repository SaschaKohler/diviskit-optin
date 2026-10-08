<?php
/**
 * Skit Optin — options, defaults, sanitization.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

function skit_defaults() {
    return array(
        // Provider
        'provider'             => 'mailerlite',
        'ml_api_token'         => '',
        'ml_group_id'          => '',
        'brevo_api_key'        => '',
        'brevo_list_id'        => '',
        'webhook_url'          => '',
        'webhook_secret'       => '',

        // Spam-Schutz (Keys + aktiviert, sonst nur Honeypot + Rate-Limit)
        'recaptcha_enabled'    => 1,
        'recaptcha_site_key'   => '',
        'recaptcha_secret_key' => '',

        // Einwilligung (wording is stored per signup as legal proof)
        'consent_text'         => 'Ich möchte News und Updates per E-Mail erhalten. Mit dem Absenden bestätige ich, dass meine Angaben gemäß der Datenschutzerklärung verarbeitet werden.',

        // Optionale Interessen (Checkboxen über der Consent-Zeile), frei
        // editierbar: [{slug, label, group}] — group = optionale
        // MailerLite-Group-ID, die bestätigte Subscriber zusätzlich bekommen.
        'interests_enabled'    => 1,
        'interests_heading'    => 'Wofür interessierst du dich? (optional)',
        'interests'            => array(),

        // Formular-Texte
        'form_heading'         => 'Newsletter',
        'form_subline'         => 'Updates zu neuen Releases, Features und Divi-5-Tipps — kein Spam.',
        'form_placeholder'     => 'E-Mail-Adresse',
        'form_button'          => 'Anmelden',
        'form_success'         => 'Fast geschafft! Bitte bestätige deine Anmeldung über den Link in der E-Mail, die wir dir gerade gesendet haben.',
        'form_success_heading' => 'Fast geschafft!',

        // Bestätigungs-Mail — Texte stehen den Mail-Templates als
        // {{subject}}, {{heading}}, {{intro}}, {{button_text}}, {{footer}}
        // zur Verfügung; das Layout kommt aus dem aktiven Template.
        'mail_subject'         => 'Bitte bestätige deine Newsletter-Anmeldung',
        'mail_heading'         => 'Fast geschafft!',
        'mail_intro'           => 'Bitte bestätige deine Anmeldung zu unserem Newsletter mit einem Klick auf den Button.',
        'mail_button'          => 'Anmeldung bestätigen',
        'mail_footer'          => 'Du hast dich nicht angemeldet? Dann kannst du diese E-Mail einfach ignorieren — es wird nichts weiter passieren.',

        // Aktives Mail-Template (ID aus skit_templates())
        'active_template'      => 'default',

        // Rechtsseiten (erscheinen in Mail + Formular)
        'policy_url'           => '/datenschutz/',
        'imprint_url'          => '/impressum/',

        // Redirects nach Klick auf den Bestätigungslink (leer = Startseite + ?optin=…)
        'redirect_confirm'     => '',
        'redirect_error'       => '',

        // Token-Lebensdauer in Stunden
        'token_ttl'            => 48,
    );
}

/**
 * Legacy interest field map (<=0.5.0): fixed slug => option keys.
 */
function skit_legacy_interest_keys() {
    return array(
        'sk_consent' => array( 'label_key' => 'int_sk_consent_label', 'group_key' => 'int_sk_consent_group' ),
        'vendokit'   => array( 'label_key' => 'int_vendokit_label',   'group_key' => 'int_vendokit_group' ),
        'agent'      => array( 'label_key' => 'int_agent_label',      'group_key' => 'int_agent_group' ),
    );
}

function skit_options() {
    $stored = get_option( SKIT_OPTIN_OPTION, array() );
    $o      = wp_parse_args( $stored, skit_defaults() );

    // Migration <=0.5.0: feste int_*-Felder → interests-Liste.
    if ( ! isset( $stored['interests'] ) ) {
        $migrated = array();
        foreach ( skit_legacy_interest_keys() as $slug => $keys ) {
            $label = trim( (string) ( isset( $o[ $keys['label_key'] ] ) ? $o[ $keys['label_key'] ] : '' ) );
            if ( '' === $label ) {
                continue;
            }
            $migrated[] = array(
                'slug'  => $slug,
                'label' => $label,
                'group' => trim( (string) ( isset( $o[ $keys['group_key'] ] ) ? $o[ $keys['group_key'] ] : '' ) ),
            );
        }
        $o['interests'] = $migrated;
        foreach ( skit_legacy_interest_keys() as $keys ) {
            unset( $o[ $keys['label_key'] ], $o[ $keys['group_key'] ] );
        }
    }
    return $o;
}

function skit_opt( $key ) {
    $o = skit_options();
    return isset( $o[ $key ] ) ? $o[ $key ] : '';
}

/**
 * reCAPTCHA is active only when the toggle is on AND both keys are set —
 * lets you keep prod keys stored while disabling the check on DDEV.
 */
function skit_recaptcha_active() {
    $o = skit_options();
    return ! empty( $o['recaptcha_enabled'] )
        && '' !== trim( (string) $o['recaptcha_site_key'] )
        && '' !== trim( (string) $o['recaptcha_secret_key'] );
}

/**
 * Interessen-Liste: slug => label + optionale Provider-Group-ID.
 * Nur Einträge mit nicht-leerem Label werden im Formular gezeigt und
 * serverseitig akzeptiert.
 *
 * @return array slug => array( 'label' => string, 'group' => string )
 */
function skit_interests() {
    $out = array();
    foreach ( (array) skit_opt( 'interests' ) as $it ) {
        if ( ! is_array( $it ) ) {
            continue;
        }
        $label = trim( (string) ( isset( $it['label'] ) ? $it['label'] : '' ) );
        if ( '' === $label ) {
            continue;
        }
        $slug = sanitize_key( isset( $it['slug'] ) ? $it['slug'] : '' );
        if ( '' === $slug ) {
            $slug = sanitize_key( $label );
        }
        $out[ $slug ] = array(
            'label' => $label,
            'group' => trim( (string) ( isset( $it['group'] ) ? $it['group'] : '' ) ),
        );
    }
    return $out;
}

function skit_interests_active() {
    $o = skit_options();
    return ! empty( $o['interests_enabled'] ) && array() !== skit_interests();
}

/**
 * Filtert eine Nutzer-Eingabe auf die bekannten Interessen-Slugs.
 * @return string[]
 */
function skit_sanitize_interests( $in ) {
    if ( ! is_array( $in ) ) {
        return array();
    }
    $known = array_keys( skit_interests() );
    return array_values( array_intersect( $known, array_map( 'sanitize_key', $in ) ) );
}

/**
 * Whitelist sanitize for the single options array.
 */
function skit_sanitize_options( $in ) {
    $out = skit_options();
    if ( ! is_array( $in ) ) {
        return $out;
    }

    $provider = isset( $in['provider'] ) ? sanitize_key( $in['provider'] ) : '';
    if ( isset( skit_providers()[ $provider ] ) ) {
        $out['provider'] = $provider;
    }

    $text_keys = array(
        'ml_api_token', 'ml_group_id', 'brevo_api_key', 'brevo_list_id',
        'webhook_secret',
        'recaptcha_site_key', 'recaptcha_secret_key',
        'form_heading', 'form_subline', 'form_placeholder', 'form_button',
        'form_success', 'form_success_heading',
        'mail_subject', 'mail_heading', 'mail_intro', 'mail_button', 'mail_footer',
        'interests_heading',
    );
    foreach ( $text_keys as $key ) {
        if ( isset( $in[ $key ] ) ) {
            $out[ $key ] = sanitize_text_field( $in[ $key ] );
        }
    }

    if ( isset( $in['active_template'] ) && null !== skit_template( sanitize_key( $in['active_template'] ) ) ) {
        $out['active_template'] = sanitize_key( $in['active_template'] );
    }

    if ( isset( $in['consent_text'] ) ) {
        $out['consent_text'] = sanitize_textarea_field( $in['consent_text'] );
    }

    foreach ( array( 'policy_url', 'imprint_url', 'redirect_confirm', 'redirect_error', 'webhook_url' ) as $key ) {
        if ( isset( $in[ $key ] ) ) {
            $out[ $key ] = esc_url_raw( trim( $in[ $key ] ) );
        }
    }

    // Interessen-Repeater: [{slug?, label, group?}] — Slug fehlt →
    // wird aus dem Label abgeleitet; gleiche Slugs werden dedupliziert.
    if ( isset( $in['interests'] ) && is_array( $in['interests'] ) ) {
        $list = array();
        foreach ( $in['interests'] as $it ) {
            if ( ! is_array( $it ) ) {
                continue;
            }
            $label = isset( $it['label'] ) ? sanitize_text_field( $it['label'] ) : '';
            if ( '' === trim( $label ) ) {
                continue;
            }
            $slug = isset( $it['slug'] ) ? sanitize_key( $it['slug'] ) : '';
            if ( '' === $slug ) {
                $slug = sanitize_key( $label );
            }
            $group         = isset( $it['group'] ) ? sanitize_text_field( $it['group'] ) : '';
            $list[ $slug ] = array( 'slug' => $slug, 'label' => $label, 'group' => $group );
            if ( count( $list ) >= 20 ) {
                break;
            }
        }
        $out['interests'] = array_values( $list );
    }

    $out['recaptcha_enabled'] = empty( $in['recaptcha_enabled'] ) ? 0 : 1;
    $out['interests_enabled'] = empty( $in['interests_enabled'] ) ? 0 : 1;

    $out['token_ttl'] = min( 168, max( 1, (int) ( isset( $in['token_ttl'] ) ? $in['token_ttl'] : 48 ) ) );

    return $out;
}
