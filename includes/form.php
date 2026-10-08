<?php
/**
 * Skit Optin — frontend form (shortcode [skit_optin_form],
 * legacy alias [skml_doi_form] from sk-mailerlite-doi <=0.3.0).
 *
 * Renders the Vision-styled signup card, submits to
 * POST /wp-json/skit-optin/v1/subscribe via fetch — no jQuery.
 * Styling uses the site's Divi global-color CSS vars (var(--gcid-*))
 * with neutral fallbacks so the form also works off-Divi.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

function skit_form_register_assets() {
    wp_register_style( 'skit-form', SKIT_OPTIN_URL . 'assets/form.css', array(), SKIT_OPTIN_VERSION );
    wp_register_script( 'skit-form', SKIT_OPTIN_URL . 'assets/form.js', array(), SKIT_OPTIN_VERSION, true );
}
add_action( 'init', 'skit_form_register_assets' );

function skit_form_shortcode() {
    $o         = skit_options();
    $captcha   = skit_recaptcha_active();
    $sitekey   = $captcha ? trim( (string) $o['recaptcha_site_key'] ) : '';
    $policy    = trim( (string) $o['policy_url'] );
    $interests = skit_interests_active() ? skit_interests() : array();

    wp_enqueue_style( 'skit-form' );
    wp_enqueue_script( 'skit-form' );
    wp_localize_script( 'skit-form', 'SKIT', array(
        'rest'    => esc_url_raw( rest_url( 'skit-optin/v1/subscribe' ) ),
        'error'   => 'Etwas ist schiefgelaufen. Bitte erneut versuchen.',
        'captcha' => $captcha,
        'site'    => $sitekey,
    ) );

    if ( $captcha ) {
        // reCAPTCHA v3 — invisible, token per submit via grecaptcha.execute().
        wp_enqueue_script( 'skit-recaptcha', 'https://www.google.com/recaptcha/api.js?render=' . rawurlencode( $sitekey ), array(), null, true );
    }

    ob_start();
    ?>
    <div class="skit-form" data-skit-form>
      <div class="skit-body">
        <?php if ( '' !== $o['form_heading'] ) : ?>
          <h4 class="skit-heading"><?php echo esc_html( $o['form_heading'] ); ?></h4>
        <?php endif; ?>
        <?php if ( '' !== $o['form_subline'] ) : ?>
          <p class="skit-sub"><?php echo esc_html( $o['form_subline'] ); ?></p>
        <?php endif; ?>

        <form novalidate>
          <input type="email" name="email" required
                 placeholder="<?php echo esc_attr( $o['form_placeholder'] ); ?>"
                 aria-label="<?php echo esc_attr( $o['form_placeholder'] ); ?>"
                 autocomplete="email" class="skit-input">

          <?php if ( $interests ) : ?>
            <fieldset class="skit-interests">
              <?php if ( '' !== trim( (string) $o['interests_heading'] ) ) : ?>
                <legend class="skit-int-legend"><?php echo esc_html( $o['interests_heading'] ); ?></legend>
              <?php endif; ?>
              <?php foreach ( $interests as $slug => $int ) : ?>
                <label class="skit-consent skit-interest">
                  <input type="checkbox" name="interests[]" value="<?php echo esc_attr( $slug ); ?>">
                  <span class="skit-box" aria-hidden="true"></span>
                  <span class="skit-consent-text"><?php echo esc_html( $int['label'] ); ?></span>
                </label>
              <?php endforeach; ?>
            </fieldset>
          <?php endif; ?>

          <label class="skit-consent">
            <input type="checkbox" name="consent" required>
            <span class="skit-box" aria-hidden="true"></span>
            <span class="skit-consent-text">
              <?php echo esc_html( $o['consent_text'] ); ?>
              <?php if ( '' !== $policy ) : ?>
                <a href="<?php echo esc_url( $policy ); ?>">Datenschutzerklärung</a>
              <?php endif; ?>
            </span>
          </label>

          <div class="skit-hp" aria-hidden="true">
            <input type="text" name="website" tabindex="-1" autocomplete="off">
          </div>

          <?php if ( '' !== $sitekey ) : ?>
            <p class="skit-captcha-note">Geschützt durch reCAPTCHA —
              <a href="https://policies.google.com/privacy" rel="noopener" target="_blank">Datenschutz</a> /
              <a href="https://policies.google.com/terms" rel="noopener" target="_blank">Nutzungsbedingungen</a>.</p>
          <?php endif; ?>

          <button type="submit" class="skit-btn">
            <span class="skit-btn-label"><?php echo esc_html( $o['form_button'] ); ?></span>
            <span class="skit-spinner" aria-hidden="true"></span>
          </button>

          <p class="skit-msg" role="status" aria-live="polite"></p>
        </form>
      </div>

      <div class="skit-done" hidden>
        <?php if ( '' !== $o['form_success_heading'] ) : ?>
          <h4 class="skit-heading"><?php echo esc_html( $o['form_success_heading'] ); ?></h4>
        <?php endif; ?>
        <p class="skit-sub"><?php echo esc_html( $o['form_success'] ); ?></p>
      </div>
    </div>
    <?php
    return ob_get_clean();
}
add_shortcode( 'skit_optin_form', 'skit_form_shortcode' );
add_shortcode( 'diviskit_optin_form', 'skit_form_shortcode' ); // legacy diviskit-optin 0.4–0.6
add_shortcode( 'skml_doi_form', 'skit_form_shortcode' ); // legacy sk-mailerlite-doi
