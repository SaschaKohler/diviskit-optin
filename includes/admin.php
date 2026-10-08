<?php
/**
 * Skit Optin — admin: tabbed page (Subscriber / Mail-Templates /
 * Einstellungen) in the Skit admin design, CSV export, template CRUD.
 *
 * Menu placement follows the suite pattern: submenu under the Skit
 * Agent menu when the agent plugin is present, standalone top-level
 * menu otherwise.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/* ==========================================================================
   MENU + SETTINGS REGISTRATION
   ========================================================================== */

function skit_admin_menu() {
    if ( class_exists( 'Diviskit_Agent' ) ) {
        $GLOBALS['skit_page_hook'] = add_submenu_page(
            'diviskit',
            'Skit Optin',
            'Optin',
            'manage_options',
            'skit-optin',
            'skit_admin_page'
        );
    } else {
        $GLOBALS['skit_page_hook'] = add_menu_page(
            'Skit Optin',
            'Skit Optin',
            'manage_options',
            'skit-optin',
            'skit_admin_page',
            skit_menu_icon(),
            58
        );
    }
}
add_action( 'admin_menu', 'skit_admin_menu' );

/**
 * Skit mark as the standalone top-level menu icon — same recoloring
 * trick as in Skit Agent/Consent (admin chrome expects light glyphs).
 */
function skit_menu_icon() {
    $svg_path = SKIT_OPTIN_PATH . 'assets/skit-mark.svg';
    if ( is_readable( $svg_path ) ) {
        $svg = file_get_contents( $svg_path );
        if ( false !== $svg ) {
            return 'data:image/svg+xml;base64,' . base64_encode( str_replace( 'fill="black"', 'fill="#f0f0f1"', $svg ) );
        }
    }
    return 'dashicons-email-alt';
}

function skit_admin_init() {
    register_setting( 'skit_optin_group', SKIT_OPTIN_OPTION, array( 'sanitize_callback' => 'skit_sanitize_options' ) );
}
add_action( 'admin_init', 'skit_admin_init' );

function skit_admin_assets( $hook ) {
    if ( empty( $GLOBALS['skit_page_hook'] ) || $GLOBALS['skit_page_hook'] !== $hook ) {
        return;
    }
    wp_enqueue_style( 'skit-optin-admin', SKIT_OPTIN_URL . 'assets/skit-admin.css', array( 'dashicons' ), SKIT_OPTIN_VERSION );
    wp_enqueue_style( 'skit-optin-page', SKIT_OPTIN_URL . 'assets/admin.css', array( 'skit-optin-admin' ), SKIT_OPTIN_VERSION );
    wp_enqueue_script( 'skit-optin-admin', SKIT_OPTIN_URL . 'assets/admin.js', array(), SKIT_OPTIN_VERSION, true );
    wp_localize_script( 'skit-optin-admin', 'SKIT_ADMIN', array(
        // Sample values for the live preview — same map the mailer uses.
        'vars' => skit_template_vars( 'max@example.com', skit_confirm_url( str_repeat( 'a', 64 ) ) ),
    ) );
}
add_action( 'admin_enqueue_scripts', 'skit_admin_assets' );

/**
 * Plugins list: direct links to settings + templates.
 */
function skit_action_links( $links ) {
    $url = admin_url( 'admin.php?page=skit-optin' );
    array_unshift( $links,
        '<a href="' . esc_url( $url . '&tab=settings' ) . '">Einstellungen</a>',
        '<a href="' . esc_url( $url . '&tab=templates' ) . '">Mail-Templates</a>'
    );
    return $links;
}
add_filter( 'plugin_action_links_' . plugin_basename( SKIT_OPTIN_PATH . 'skit-optin.php' ), 'skit_action_links' );

/**
 * One-time notice after activation pointing at the settings tab.
 */
function skit_activation_notice() {
    if ( ! get_transient( 'skit_optin_activated' ) || ! current_user_can( 'manage_options' ) ) {
        return;
    }
    delete_transient( 'skit_optin_activated' );
    printf(
        '<div class="notice notice-success is-dismissible"><p>%s <a href="%s">%s</a></p></div>',
        esc_html( 'Skit Optin ist aktiviert.' ),
        esc_url( admin_url( 'admin.php?page=skit-optin&tab=settings' ) ),
        esc_html( 'Jetzt einrichten →' )
    );
}
add_action( 'admin_notices', 'skit_activation_notice' );

/* ==========================================================================
   PAGE SHELL (Skit design: header + tab nav + cards)
   ========================================================================== */

function skit_admin_page() {
    if ( ! current_user_can( 'manage_options' ) ) {
        return;
    }

    $tabs = array(
        'subscribers' => 'Subscriber',
        'templates'   => 'Mail-Templates',
        'settings'    => 'Einstellungen',
    );
    // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only tab switch, no mutation.
    $tab = isset( $_GET['tab'] ) ? sanitize_key( wp_unslash( $_GET['tab'] ) ) : 'subscribers';
    if ( ! isset( $tabs[ $tab ] ) ) {
        $tab = 'subscribers';
    }
    ?>
    <div class="wrap">
      <h1 class="screen-reader-text"><?php echo esc_html( get_admin_page_title() ); ?></h1>
      <div class="skit-admin">
        <header class="skit-header">
          <div>
            <div class="skit-brand">
              <img src="<?php echo esc_url( SKIT_OPTIN_URL . 'assets/skit-wordmark.svg' ); ?>" alt="Skit" width="112" height="42" />
              <span class="skit-edition">Optin</span>
            </div>
            <p>Eigenes Double-Opt-In für Newsletter — Mail-Templates, DSGVO-Einwilligungsnachweis, Provider-Sync</p>
          </div>
          <a href="<?php echo esc_url( home_url( '/' ) ); ?>" target="_blank" rel="noopener noreferrer" class="button"><span class="dashicons dashicons-visibility" aria-hidden="true"></span>Formular testen</a>
        </header>
        <nav class="skit-nav" aria-label="Skit Optin pages">
          <?php foreach ( $tabs as $id => $label ) : ?>
            <a href="<?php echo esc_url( admin_url( 'admin.php?page=skit-optin&tab=' . $id ) ); ?>"<?php if ( $tab === $id ) echo ' aria-current="page"'; ?>><?php echo esc_html( $label ); ?></a>
          <?php endforeach; ?>
        </nav>
        <div class="skit-content">
          <?php
          if ( 'templates' === $tab ) {
              skit_admin_templates_tab();
          } elseif ( 'settings' === $tab ) {
              skit_admin_settings_tab();
          } else {
              skit_admin_subscribers_tab();
          }
          ?>
        </div>
        <footer class="skit-footer">
          Skit Optin <?php echo esc_html( SKIT_OPTIN_VERSION ); ?>
          · Shortcode <code>[skit_optin_form]</code> (Legacy: <code>[skml_doi_form]</code>)
        </footer>
      </div>
    </div>
    <?php
}

/* ==========================================================================
   TAB: SETTINGS
   ========================================================================== */

function skit_field( $key, $type = 'text', $placeholder = '', $desc = '' ) {
    $o = skit_options();
    printf(
        '<input type="%s" name="%s[%s]" value="%s" class="regular-text" placeholder="%s">',
        esc_attr( $type ),
        esc_attr( SKIT_OPTIN_OPTION ),
        esc_attr( $key ),
        esc_attr( $o[ $key ] ),
        esc_attr( $placeholder )
    );
    if ( $desc ) {
        printf( '<p class="description">%s</p>', esc_html( $desc ) );
    }
}

function skit_field_area( $key, $desc = '' ) {
    $o = skit_options();
    printf(
        '<textarea name="%s[%s]" rows="3" class="large-text">%s</textarea>',
        esc_attr( SKIT_OPTIN_OPTION ),
        esc_attr( $key ),
        esc_textarea( $o[ $key ] )
    );
    if ( $desc ) {
        printf( '<p class="description">%s</p>', esc_html( $desc ) );
    }
}

function skit_admin_settings_tab() {
    $o         = skit_options();
    $providers = skit_providers();
    $provider  = isset( $providers[ $o['provider'] ] ) ? $o['provider'] : 'mailerlite';

    // Connectivity check for the selected provider (needs its key/url set).
    $has_key   = 'none' === $provider;
    foreach ( $providers[ $provider ]['fields'] as $key => $label ) {
        if ( false !== stripos( $key, 'key' ) || false !== stripos( $key, 'token' ) || false !== stripos( $key, 'url' ) ) {
            $has_key = '' !== trim( (string) $o[ $key ] );
        }
    }
    $api_status = '';
    if ( $has_key && 'none' !== $provider ) {
        $ping       = skit_provider_ping();
        $api_status = is_wp_error( $ping )
            ? '<span class="skit-status skit-status--error">' . esc_html( $ping->get_error_message() ) . '</span>'
            : '<span class="skit-status skit-status--success">Verbindung ok</span>';
    }
    ?>
    <form method="post" action="options.php">
      <?php settings_fields( 'skit_optin_group' ); ?>

      <section class="skit-card">
        <div class="skit-section-heading">
          <h2>Provider</h2>
          <?php if ( $api_status ) : ?><?php echo $api_status; // phpcs:ignore ?><?php endif; ?>
        </div>
        <table class="form-table" role="presentation">
          <tr>
            <th scope="row"><label for="skit-provider">List-Provider</label></th>
            <td>
              <select id="skit-provider" name="<?php echo esc_attr( SKIT_OPTIN_OPTION ); ?>[provider]">
                <?php foreach ( $providers as $id => $p ) : ?>
                  <option value="<?php echo esc_attr( $id ); ?>" <?php selected( $provider, $id ); ?>><?php echo esc_html( $p['label'] ); ?></option>
                <?php endforeach; ?>
              </select>
            </td>
          </tr>
          <?php foreach ( $providers as $id => $p ) : ?>
            <?php foreach ( $p['fields'] as $key => $label ) : ?>
              <tr class="skit-pfield" data-provider="<?php echo esc_attr( $id ); ?>">
                <th scope="row"><?php echo esc_html( $label ); ?></th>
                <td><?php skit_field( $key, ( false !== stripos( $key, 'key' ) || false !== stripos( $key, 'token' ) || false !== stripos( $key, 'secret' ) ) ? 'password' : 'text' ); ?></td>
              </tr>
            <?php endforeach; ?>
            <?php if ( '' !== $p['hint'] ) : ?>
              <tr class="skit-pfield" data-provider="<?php echo esc_attr( $id ); ?>">
                <th></th><td><p class="description"><?php echo esc_html( $p['hint'] ); ?></p></td>
              </tr>
            <?php endif; ?>
          <?php endforeach; ?>
        </table>
      </section>

      <section class="skit-card">
        <h2>Spam-Schutz (reCAPTCHA v3)</h2>
        <table class="form-table" role="presentation">
          <tr><th scope="row">reCAPTCHA aktiv</th><td>
            <label><input type="checkbox" name="<?php echo esc_attr( SKIT_OPTIN_OPTION ); ?>[recaptcha_enabled]" value="1" <?php checked( ! empty( $o['recaptcha_enabled'] ) ); ?>>
              Spam-Prüfung einschalten</label>
            <p class="description">Auf lokaler DDEV-Dev ausgeschaltet lassen — Keys bleiben gespeichert, greifen aber erst wenn aktiviert. Honeypot + Rate-Limit laufen immer.</p>
          </td></tr>
          <tr><th scope="row">reCAPTCHA Site Key</th><td><?php skit_field( 'recaptcha_site_key', 'text', '', 'v3 (unsichtbar). Wirkt nur, wenn „reCAPTCHA aktiv“ an ist.' ); ?></td></tr>
          <tr><th scope="row">reCAPTCHA Secret</th><td><?php skit_field( 'recaptcha_secret_key', 'password' ); ?></td></tr>
        </table>
      </section>

      <section class="skit-card">
        <h2>Einwilligung</h2>
        <table class="form-table" role="presentation">
          <tr><th scope="row">Consent-Text</th><td><?php skit_field_area( 'consent_text', 'Wird neben der Checkbox gezeigt UND bei jeder Anmeldung als Nachweis gespeichert. Bei Änderung des Wortlauts gilt die alte Version für Bestandsnachweise.' ); ?></td></tr>
          <tr><th scope="row">Datenschutz-URL</th><td><?php skit_field( 'policy_url', 'text', '/datenschutz/', 'Link im Formular + Footer der Bestätigungsmail.' ); ?></td></tr>
          <tr><th scope="row">Impressum-URL</th><td><?php skit_field( 'imprint_url', 'text', '/impressum/' ); ?></td></tr>
        </table>
      </section>

      <section class="skit-card">
        <h2>Produkt-Interessen (Warteliste)</h2>
        <table class="form-table" role="presentation">
          <tr><th scope="row">Checkboxen aktiv</th><td>
            <label><input type="checkbox" name="<?php echo esc_attr( SKIT_OPTIN_OPTION ); ?>[interests_enabled]" value="1" <?php checked( ! empty( $o['interests_enabled'] ) ); ?>>
              Optionale Interessen-Checkboxen im Formular zeigen</label>
          </td></tr>
          <tr><th scope="row">Zwischenüberschrift</th><td><?php skit_field( 'interests_heading', 'text', 'Wofür interessierst du dich? (optional)' ); ?></td></tr>
          <tr>
            <th scope="row">Interessen</th>
            <td>
              <div id="skit-interests" data-option="<?php echo esc_attr( SKIT_OPTIN_OPTION ); ?>">
                <?php foreach ( (array) $o['interests'] as $i => $it ) : ?>
                  <div class="skit-int-row">
                    <input type="hidden" name="<?php echo esc_attr( SKIT_OPTIN_OPTION ); ?>[interests][<?php echo (int) $i; ?>][slug]" value="<?php echo esc_attr( $it['slug'] ); ?>">
                    <input type="text" name="<?php echo esc_attr( SKIT_OPTIN_OPTION ); ?>[interests][<?php echo (int) $i; ?>][label]" value="<?php echo esc_attr( $it['label'] ); ?>" placeholder="Label" class="regular-text">
                    <input type="text" name="<?php echo esc_attr( SKIT_OPTIN_OPTION ); ?>[interests][<?php echo (int) $i; ?>][group]" value="<?php echo esc_attr( $it['group'] ); ?>" placeholder="ML-Group-ID (optional)" class="regular-text">
                    <button type="button" class="button skit-int-del" title="Entfernen">−</button>
                  </div>
                <?php endforeach; ?>
              </div>
              <p><button type="button" class="button" id="skit-int-add">+ Interesse hinzufügen</button></p>
              <p class="description">Pro Zeile eine optionale Checkbox im Formular. Die ML-Group-ID ist MailerLite-spezifisch: bestätigte Subscriber mit diesem Interesse werden zusätzlich in diese Gruppe geschrieben. Beim Webhook-Provider gehen gewählte Interessen als Slug-Liste mit.</p>
            </td>
          </tr>
        </table>
      </section>

      <section class="skit-card">
        <h2>Formular-Texte</h2>
        <table class="form-table" role="presentation">
          <tr><th scope="row">Überschrift</th><td><?php skit_field( 'form_heading' ); ?></td></tr>
          <tr><th scope="row">Subline</th><td><?php skit_field( 'form_subline' ); ?></td></tr>
          <tr><th scope="row">Placeholder</th><td><?php skit_field( 'form_placeholder' ); ?></td></tr>
          <tr><th scope="row">Button</th><td><?php skit_field( 'form_button' ); ?></td></tr>
          <tr><th scope="row">Erfolg: Überschrift</th><td><?php skit_field( 'form_success_heading' ); ?></td></tr>
          <tr><th scope="row">Erfolg: Text</th><td><?php skit_field_area( 'form_success', 'Nach dem Absenden — Hinweis auf die Bestätigungsmail.' ); ?></td></tr>
        </table>
      </section>

      <section class="skit-card">
        <h2>Bestätigungs-Mail — Texte</h2>
        <p class="description">Diese Texte stehen den Mail-Templates als <code>{{subject}}</code>, <code>{{heading}}</code>, <code>{{intro}}</code>, <code>{{button_text}}</code>, <code>{{footer}}</code> zur Verfügung. Das HTML-Layout selbst bearbeitest du im Tab
          <a href="<?php echo esc_url( admin_url( 'admin.php?page=skit-optin&tab=templates' ) ); ?>">Mail-Templates</a>.</p>
        <table class="form-table" role="presentation">
          <tr><th scope="row">Betreff</th><td><?php skit_field( 'mail_subject' ); ?></td></tr>
          <tr><th scope="row">Überschrift</th><td><?php skit_field( 'mail_heading' ); ?></td></tr>
          <tr><th scope="row">Intro</th><td><?php skit_field_area( 'mail_intro' ); ?></td></tr>
          <tr><th scope="row">Button</th><td><?php skit_field( 'mail_button' ); ?></td></tr>
          <tr><th scope="row">Footer</th><td><?php skit_field_area( 'mail_footer', 'Kleingedrucktes unter der Mail (z.B. „Nicht angemeldet? Einfach ignorieren.“).' ); ?></td></tr>
        </table>
      </section>

      <section class="skit-card">
        <h2>Technik</h2>
        <table class="form-table" role="presentation">
          <tr><th scope="row">Redirect nach Bestätigung</th><td><?php skit_field( 'redirect_confirm', 'url', 'https://…/danke/', 'Leer = Startseite mit ?optin=confirmed.' ); ?></td></tr>
          <tr><th scope="row">Redirect bei Fehler</th><td><?php skit_field( 'redirect_error', 'url', 'https://…/fehler/', 'Leer = Startseite mit ?optin=error.' ); ?></td></tr>
          <tr><th scope="row">Token-TTL (Stunden)</th><td><?php skit_field( 'token_ttl', 'number', '48' ); ?></td></tr>
        </table>
      </section>

      <?php submit_button(); ?>
    </form>
    <?php
}

/* ==========================================================================
   TAB: MAIL TEMPLATES
   ========================================================================== */

function skit_tpl_url( $args = array() ) {
    return admin_url( 'admin.php?page=skit-optin&tab=templates&' . http_build_query( $args ) );
}

function skit_tpl_action_url( $action, $id ) {
    return wp_nonce_url(
        admin_url( 'admin-post.php?action=skit_tpl_' . $action . '&template=' . rawurlencode( $id ) ),
        'skit_tpl_' . $action . '_' . $id
    );
}

function skit_admin_templates_tab() {
    // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only view selection; mutations go through admin-post.php with nonces.
    $action = isset( $_GET['action'] ) ? sanitize_key( wp_unslash( $_GET['action'] ) ) : '';
    // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only view selection; mutations go through admin-post.php with nonces.
    $id     = isset( $_GET['template'] ) ? sanitize_key( wp_unslash( $_GET['template'] ) ) : '';

    if ( 'edit' === $action || 'new' === $action ) {
        skit_admin_template_edit( $id );
        return;
    }

    skit_admin_templates_list();
}

function skit_admin_templates_list() {
    $templates = skit_templates();
    $active    = (string) skit_opt( 'active_template' );
    if ( ! isset( $templates[ $active ] ) ) {
        $active = 'default';
    }
    // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- displays a notice slug from a redirect; value is whitelisted below.
    $notice  = isset( $_GET['skit_notice'] ) ? sanitize_key( wp_unslash( $_GET['skit_notice'] ) ) : '';
    $notices = array(
        'saved'      => array( 'success', 'Template gespeichert.' ),
        'activated'  => array( 'success', 'Template aktiviert.' ),
        'duplicated' => array( 'success', 'Template dupliziert.' ),
        'deleted'    => array( 'success', 'Template gelöscht.' ),
        'reset'      => array( 'success', 'Template auf Werkseinstellung zurückgesetzt.' ),
        'tested'     => array( 'success', 'Testmail an deine Admin-Adresse gesendet.' ),
        'testfail'   => array( 'error', 'Testmail konnte nicht gesendet werden (wp_mail fehlgeschlagen).' ),
        'invalid'    => array( 'error', 'Aktion fehlgeschlagen (unbekanntes oder geschütztes Template).' ),
    );
    ?>
    <?php if ( isset( $notices[ $notice ] ) ) : ?>
      <div class="notice notice-<?php echo esc_attr( $notices[ $notice ][0] ); ?> is-dismissible"><p><?php echo esc_html( $notices[ $notice ][1] ); ?></p></div>
    <?php endif; ?>

    <section class="skit-card">
      <div class="skit-section-heading">
        <h2>Mail-Templates</h2>
        <a href="<?php echo esc_url( skit_tpl_url( array( 'action' => 'new' ) ) ); ?>" class="button button-primary">Neues Template</a>
      </div>
      <p class="skit-muted">Templates sind vollständige HTML-Mails mit <code>{{platzhaltern}}</code>. Pflicht: <code>{{confirm_url}}</code> — ohne Bestätigungslink kein Double-Opt-In. Das aktive Template wird für jede Bestätigungsmail genutzt.</p>

      <table class="widefat striped">
        <thead><tr>
          <th>Name</th><th>Status</th><th>Aktionen</th>
        </tr></thead>
        <tbody>
        <?php foreach ( $templates as $tpl ) : ?>
          <tr>
            <td>
              <strong><?php echo esc_html( $tpl['name'] ); ?></strong>
              <?php if ( ! empty( $tpl['builtin'] ) ) : ?><span class="skit-muted">(eingebaut)</span><?php endif; ?>
              <br><code><?php echo esc_html( $tpl['id'] ); ?></code>
            </td>
            <td>
              <?php if ( $tpl['id'] === $active ) : ?>
                <span class="skit-status skit-status--success">aktiv</span>
              <?php else : ?><span class="skit-muted">—</span><?php endif; ?>
            </td>
            <td>
              <a href="<?php echo esc_url( skit_tpl_url( array( 'action' => 'edit', 'template' => $tpl['id'] ) ) ); ?>">Bearbeiten</a>
              · <a href="<?php echo esc_url( skit_tpl_action_url( 'duplicate', $tpl['id'] ) ); ?>">Duplizieren</a>
              · <a href="<?php echo esc_url( skit_tpl_action_url( 'test', $tpl['id'] ) ); ?>">Testmail</a>
              <?php if ( $tpl['id'] !== $active ) : ?>
                · <a href="<?php echo esc_url( skit_tpl_action_url( 'activate', $tpl['id'] ) ); ?>">Aktivieren</a>
              <?php endif; ?>
              <?php if ( ! empty( $tpl['builtin'] ) ) : ?>
                · <a href="<?php echo esc_url( skit_tpl_action_url( 'reset', $tpl['id'] ) ); ?>">Werkseinstellung</a>
              <?php else : ?>
                · <a href="<?php echo esc_url( skit_tpl_action_url( 'delete', $tpl['id'] ) ); ?>"
                     onclick="return confirm('Template wirklich löschen?');" style="color:#9b252a;">Löschen</a>
              <?php endif; ?>
            </td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </section>
    <?php
}

function skit_admin_template_edit( $id ) {
    $is_new = ( '' === $id );
    $tpl    = $is_new
        ? array( 'id' => '', 'name' => '', 'html' => skit_factory_template_html() )
        : skit_template( $id );

    if ( ! $tpl ) {
        wp_safe_redirect( skit_tpl_url( array( 'skit_notice' => 'invalid' ) ) );
        exit;
    }

    // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- displays an error message from a redirect; escaped on output.
    $err = isset( $_GET['skit_error'] ) ? sanitize_text_field( wp_unslash( $_GET['skit_error'] ) ) : '';
    ?>
    <section class="skit-card">
      <div class="skit-section-heading">
        <h2><?php echo $is_new ? 'Neues Mail-Template' : 'Mail-Template bearbeiten'; // phpcs:ignore ?></h2>
        <a class="button" href="<?php echo esc_url( skit_tpl_url() ); ?>">← Zurück zur Übersicht</a>
      </div>
      <?php if ( '' !== $err ) : ?>
        <div class="skit-callout skit-callout--error"><p><?php echo esc_html( $err ); ?></p></div>
      <?php endif; ?>

      <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
        <input type="hidden" name="action" value="skit_tpl_save">
        <input type="hidden" name="tpl_id" value="<?php echo esc_attr( $tpl['id'] ); ?>">
        <?php wp_nonce_field( 'skit_tpl_save' ); ?>

        <div class="skit-editor-grid">
          <div>
            <p>
              <label for="skit-tpl-name"><strong>Name</strong></label><br>
              <input type="text" id="skit-tpl-name" name="tpl_name" value="<?php echo esc_attr( $tpl['name'] ); ?>" class="regular-text" required>
            </p>
            <p>
              <label for="skit-tpl-html"><strong>HTML</strong> <span class="skit-muted">— Vorschau rechts aktualisiert sich live</span></label><br>
              <textarea id="skit-tpl-html" name="tpl_html" rows="30" class="large-text code skit-tpl-textarea"><?php echo esc_textarea( $tpl['html'] ); ?></textarea>
            </p>
            <details class="skit-placeholders">
              <summary><strong>Platzhalter</strong></summary>
              <table class="widefat" style="margin-top:8px;">
                <?php foreach ( skit_template_placeholders() as $ph => $desc ) : ?>
                  <tr><td style="width:150px;"><code><?php echo esc_html( $ph ); ?></code></td><td><?php echo esc_html( $desc ); ?></td></tr>
                <?php endforeach; ?>
              </table>
            </details>
            <p>
              <?php submit_button( 'Template speichern', 'primary', 'submit', false ); ?>
              <a class="button" href="<?php echo esc_url( skit_tpl_url() ); ?>">Abbrechen</a>
            </p>
          </div>
          <div class="skit-preview">
            <p class="skit-muted">Vorschau mit Beispielwerten — endgültige Werte kommen beim Versand aus den Einstellungen.</p>
            <iframe id="skit-tpl-preview" class="skit-preview-frame" title="Mail-Vorschau" sandbox></iframe>
          </div>
        </div>
      </form>
    </section>
    <?php
}

/* ------------------------------------------------------------------ */
/*  Template admin-post actions                                        */
/* ------------------------------------------------------------------ */

function skit_tpl_redirect( $notice ) {
    wp_safe_redirect( skit_tpl_url( array( 'skit_notice' => $notice ) ) );
    exit;
}

function skit_tpl_error_redirect( $id, WP_Error $err ) {
    wp_safe_redirect( skit_tpl_url( array( 'action' => 'edit', 'template' => $id, 'skit_error' => $err->get_error_message() ) ) );
    exit;
}

function skit_tpl_request_id() {
    // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- nonce is verified in skit_tpl_guard/skit_handle_tpl_save after this lookup.
    return isset( $_REQUEST['template'] ) ? sanitize_key( wp_unslash( $_REQUEST['template'] ) ) : '';
}

function skit_tpl_guard( $action, $id ) {
    if ( ! current_user_can( 'manage_options' ) ) {
        wp_die( 'Forbidden', 403 );
    }
    check_admin_referer( 'skit_tpl_' . $action . '_' . $id );
}

function skit_handle_tpl_save() {
    if ( ! current_user_can( 'manage_options' ) ) {
        wp_die( 'Forbidden', 403 );
    }
    check_admin_referer( 'skit_tpl_save' );

    $id   = isset( $_POST['tpl_id'] ) ? sanitize_key( wp_unslash( $_POST['tpl_id'] ) ) : '';
    $name = isset( $_POST['tpl_name'] ) ? sanitize_text_field( wp_unslash( $_POST['tpl_name'] ) ) : '';
    $html = isset( $_POST['tpl_html'] ) ? wp_unslash( $_POST['tpl_html'] ) : '';
    if ( ! current_user_can( 'unfiltered_html' ) ) {
        $html = wp_kses( $html, skit_allowed_mail_html() );
    }

    $result = skit_save_template( $id, $name, $html );
    if ( is_wp_error( $result ) ) {
        skit_tpl_error_redirect( $id, $result );
    }
    skit_tpl_redirect( 'saved' );
}
add_action( 'admin_post_skit_tpl_save', 'skit_handle_tpl_save' );

function skit_handle_tpl_activate() {
    $id = skit_tpl_request_id();
    skit_tpl_guard( 'activate', $id );
    skit_tpl_redirect( skit_activate_template( $id ) ? 'activated' : 'invalid' );
}
add_action( 'admin_post_skit_tpl_activate', 'skit_handle_tpl_activate' );

function skit_handle_tpl_duplicate() {
    $id = skit_tpl_request_id();
    skit_tpl_guard( 'duplicate', $id );
    skit_tpl_redirect( skit_duplicate_template( $id ) ? 'duplicated' : 'invalid' );
}
add_action( 'admin_post_skit_tpl_duplicate', 'skit_handle_tpl_duplicate' );

function skit_handle_tpl_delete() {
    $id = skit_tpl_request_id();
    skit_tpl_guard( 'delete', $id );
    skit_tpl_redirect( skit_delete_template( $id ) ? 'deleted' : 'invalid' );
}
add_action( 'admin_post_skit_tpl_delete', 'skit_handle_tpl_delete' );

function skit_handle_tpl_reset() {
    $id = skit_tpl_request_id();
    skit_tpl_guard( 'reset', $id );
    skit_tpl_redirect( skit_reset_template( $id ) ? 'reset' : 'invalid' );
}
add_action( 'admin_post_skit_tpl_reset', 'skit_handle_tpl_reset' );

function skit_handle_tpl_test() {
    $id = skit_tpl_request_id();
    skit_tpl_guard( 'test', $id );
    $to = wp_get_current_user();
    $ok = $to && $to->user_email ? skit_send_test_mail( $id, $to->user_email ) : false;
    skit_tpl_redirect( $ok ? 'tested' : 'testfail' );
}
add_action( 'admin_post_skit_tpl_test', 'skit_handle_tpl_test' );

/* ==========================================================================
   TAB: SUBSCRIBER LIST + CSV EXPORT
   ========================================================================== */

function skit_admin_subscribers_tab() {
    $counts  = skit_subscriber_counts();
    $entries = skit_subscriber_entries( 100 );
    ?>
    <section class="skit-card">
      <div class="skit-section-heading">
        <h2>Subscriber</h2>
        <a class="button" href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=skit_export' ), 'skit_export' ) ); ?>"><span class="dashicons dashicons-download" aria-hidden="true"></span>CSV-Export</a>
      </div>
      <p>
        <span class="skit-status skit-status--warning">Pending: <strong><?php echo (int) $counts['pending']; ?></strong></span>
        &nbsp; <span class="skit-status skit-status--success">Confirmed: <strong><?php echo (int) $counts['confirmed']; ?></strong></span>
        &nbsp; <span class="skit-status skit-status--neutral">Expired: <strong><?php echo (int) $counts['expired']; ?></strong></span>
      </p>

      <table class="widefat striped">
        <thead><tr>
          <th>E-Mail</th><th>Status</th><th>Angemeldet</th><th>Bestätigt</th><th>Sync</th><th>IP</th><th>Interessen</th><th>Consent-Text</th>
        </tr></thead>
        <tbody>
        <?php if ( ! $entries ) : ?>
          <tr><td colspan="8"><em>Noch keine Einträge.</em></td></tr>
        <?php endif; ?>
        <?php foreach ( $entries as $e ) : ?>
          <tr>
            <td><?php echo esc_html( $e['email'] ); ?></td>
            <td><?php echo esc_html( $e['status'] ); ?></td>
            <td><?php echo esc_html( $e['created_at'] ); ?></td>
            <td><?php echo esc_html( (string) $e['confirmed_at'] ); ?></td>
            <td>
              <?php if ( $e['ml_synced_at'] ) : ?>
                <span class="skit-status skit-status--success"><?php echo esc_html( $e['ml_synced_at'] ); ?></span>
              <?php elseif ( '' !== (string) $e['ml_error'] ) : ?>
                <span class="skit-status skit-status--error" title="<?php echo esc_attr( $e['ml_error'] ); ?>"><?php echo esc_html( $e['ml_error'] ); ?></span>
              <?php else : ?>—<?php endif; ?>
            </td>
            <td><?php echo esc_html( $e['ip_address'] ); ?></td>
            <td><?php echo esc_html( isset( $e['interests'] ) ? str_replace( ',', ', ', (string) $e['interests'] ) : '' ); ?></td>
            <td><small><?php echo esc_html( wp_trim_words( $e['consent_text'], 15 ) ); ?></small></td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </section>
    <?php
}

function skit_export_csv() {
    if ( ! current_user_can( 'manage_options' ) ) {
        wp_die( 'Forbidden', 403 );
    }
    check_admin_referer( 'skit_export' );

    global $wpdb;
    $table = skit_table();
    // phpcs:ignore WordPress.DB.DirectDatabaseQuery -- custom table, admin-only export.
    $rows  = $wpdb->get_results(
        $wpdb->prepare(
            "SELECT email, status, consent_text, ip_address, user_agent, created_at, confirmed_at, ml_synced_at, interests FROM %i ORDER BY created_at DESC",
            $table
        ),
        ARRAY_A
    );

    nocache_headers();
    header( 'Content-Type: text/csv; charset=utf-8' );
    header( 'Content-Disposition: attachment; filename="skit-optin-subscribers-' . gmdate( 'Y-m-d' ) . '.csv"' );

    $csv_cell = static function ( $val ) {
        return '"' . str_replace( '"', '""', (string) $val ) . '"';
    };
    // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- CSV download, cells are quoted above.
    echo implode( ',', array_map( $csv_cell, array( 'email', 'status', 'consent_text', 'ip_address', 'user_agent', 'created_at', 'confirmed_at', 'ml_synced_at', 'interests' ) ) ), "\r\n";
    foreach ( $rows as $row ) {
        // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- CSV download, cells are quoted above.
        echo implode( ',', array_map( $csv_cell, array_values( $row ) ) ), "\r\n";
    }
    exit;
}
add_action( 'admin_post_skit_export', 'skit_export_csv' );
