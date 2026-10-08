# Skit Optin

Your own double opt-in for WordPress newsletter signups — branded
confirmation mails from fully editable templates, GDPR consent proof
(Art. 7), and a provider of your choice: MailerLite, Brevo, or any webhook.

Free software, GPL-2.0. Works on any WordPress theme, Divi not required.
Formerly known as *diviskit-optin* and *sk-mailerlite-doi* — existing
installs migrate automatically.

## Why

German/GDPR newsletters need a real double opt-in. Provider-side DOI mails
are unstyled, usually English, or a paid feature. Skit Optin runs the
confirmation flow on your own site — the provider only receives the
confirmed contact.

## Features

- `[skit_optin_form]` shortcode (legacy aliases `[diviskit_optin_form]`,
  `[skml_doi_form]`)
- Editable HTML mail templates with placeholders, live preview, test send
- Consent proof: wording, timestamp, IP, user agent — CSV export
- Providers: MailerLite, Brevo, generic webhook (HMAC-SHA256 signed),
  or local only
- Optional interest checkboxes → MailerLite group mapping
- Spam protection: honeypot, per-IP rate limit, optional reCAPTCHA v3
- SHA-256 confirmation tokens with TTL, daily retry of failed syncs
- German-first UI, all texts editable

## Install

Upload the ZIP under Plugins → Add New, activate, configure a provider
under Skit Optin → Settings, place the shortcode.

MailerLite users: keep "Double opt-in for API and integrations" **off** —
the plugin performs the DOI itself.

## Links

- Product page: <https://diviskit.com/skit-optin/>
- Suite: <https://github.com/SaschaKohler/diviskit>
