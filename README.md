# Diviskit Optin

Your own double opt-in for WordPress newsletter signups — branded
confirmation mails from fully editable templates, GDPR consent proof
(Art. 7), and a provider of your choice: MailerLite, Brevo, or any webhook.

Free software, GPL-2.0. Part of the [Diviskit](https://diviskit.com) suite —
but works on any WordPress theme, Divi not required.

## Why

German/GDPR newsletters need a real double opt-in. Provider-side DOI mails
are unstyled, usually English, or a paid feature. Diviskit Optin runs the
confirmation flow on your own site — the provider only receives the
confirmed contact.

## Features

- `[diviskit_optin_form]` shortcode (legacy alias `[skml_doi_form]`)
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
under Diviskit → Optin (or the standalone menu), place the shortcode.

MailerLite users: keep "Double opt-in for API and integrations" **off** —
the plugin performs the DOI itself.

## Links

- Product page: <https://diviskit.com/diviskit-optin/>
- Suite: <https://github.com/SaschaKohler/diviskit>
