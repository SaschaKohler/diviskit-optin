=== Skit Optin ===
Contributors: diviskit
Tags: newsletter, gdpr, double opt-in, email
Requires at least: 6.2
Tested up to: 7.1
Requires PHP: 7.4
Stable tag: 0.7.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Branded double-opt-in confirmation mails, GDPR consent proof, MailerLite/Brevo/webhook sync. Free, GPL, no account needed.

== Description ==

Skit Optin replaces the unstyled — or paid — double-opt-in mails of your
email marketing provider with a confirmation flow that runs entirely on your
own WordPress site:

* Signup form via shortcode `[skit_optin_form]` (legacy aliases:
  `[diviskit_optin_form]`, `[skml_doi_form]`) — lean markup with its own
  minimal styling; on Divi 5 sites it automatically picks up the Global
  Colors (`var(--gcid-*)`)
* Mail templates: create, edit, duplicate and activate complete HTML mails
  in wp-admin — placeholders like `{{confirm_url}}` (required),
  `{{heading}}`, `{{site_name}}` and more; four built-in designs (Standard,
  Skit, Minimal, Dark), live preview and test send
* Consent proof: per signup the exact consent wording shown is stored with
  timestamp, IP and user agent — the standard evidence set for Art. 7 GDPR;
  exportable as CSV
* Provider select: MailerLite or Brevo via API, a generic webhook
  (JSON POST with optional HMAC-SHA256 signature — bridges to Zapier, Make,
  n8n or custom systems), or "local only" without an external service
* Optional interest checkboxes: fully editable list (add/remove rows) —
  selections are stored and mapped to configurable group IDs on MailerLite,
  or sent as slug list to the webhook
* Spam protection: honeypot + per-IP rate limit + optional reCAPTCHA v3
  (invisible); bots get a silent OK, never a confirmation mail
* Token logic: SHA-256-hashed confirmation links with configurable TTL,
  stale signups expire automatically, failed provider syncs retry daily
* Configurable redirects to your own thank-you/error pages
* Runs on any theme — Divi 5 included, never required

IMPORTANT for MailerLite: in Account settings → Subscribe settings keep
"Double opt-in for API and integrations" OFF — the plugin performs the DOI
itself, otherwise subscribers get a second, unstyled MailerLite mail.

== Deutsch ==

Eigenes Double-Opt-In für Newsletter: gebrandete deutsche
Bestätigungsmails aus frei editierbaren Mail-Templates,
Einwilligungsnachweis nach Art. 7 DSGVO (Consent-Text, Zeitstempel, IP,
User-Agent), reCAPTCHA v3 + Honeypot. Bestätigte Subscriber werden an
MailerLite, Brevo oder einen generischen Webhook übergeben — das
kostenpflichtige/unstyled DOI der Anbieter wird umgangen. Läuft auf jedem
WordPress-Theme, Divi ist keine Voraussetzung.

== External Services ==

This plugin connects to external services only for the features you enable:

* MailerLite API (`connect.mailerlite.com`): when MailerLite is selected as
  provider, the confirmed subscriber's email address and configured group
  IDs are sent via your API token.
  https://www.mailerlite.com/legal/privacy-policy
* Brevo API (`api.brevo.com`): when Brevo is selected as provider, the
  confirmed subscriber's email address is sent via your API key.
  https://www.brevo.com/legal/privacypolicy/
* Google reCAPTCHA (`google.com/recaptcha`): when enabled, a script is
  loaded on pages containing the form and the token is verified server-side.
  https://policies.google.com/privacy — https://policies.google.com/terms
* Webhook: when the generic webhook provider is selected, email, selected
  interests, confirmation timestamp and site URL are POSTed as JSON to the
  URL you configure (optionally signed via HMAC-SHA256).

No data is transmitted anywhere when the "local only" provider is active.

== Installation ==

1. Upload the plugin ZIP under Plugins → Add New → Upload, or copy the
   `skit-optin` folder to `wp-content/plugins/`
2. Activate the plugin
3. Go to the "Skit Optin" menu → Settings: pick a provider (MailerLite,
   Brevo, Webhook or local only) and enter credentials
4. Place `[skit_optin_form]` where the form should appear — the
   confirmation mail works with the default template right away

== Frequently Asked Questions ==

= Why not the provider's own double opt-in? =

Provider DOIs are unstyled, usually English and sometimes a paid feature.
Skit Optin sends your mail in your design and wording — the provider
only sees the confirmed contact.

= Do I need MailerLite or Brevo? =

No. With "store locally" confirmed addresses stay in the WP table and
export as CSV. For any other service there is the generic webhook.

= Is the consent proof GDPR-compliant? =

The plugin stores the shown consent text, timestamp, IP and user agent per
signup — the usual evidence basis under Art. 7 GDPR. That does not replace
legal advice on your wording.

= What happens to installs of sk-mailerlite-doi or diviskit-optin? =

Both migrate automatically — options, mail templates, subscriber table
and cron. Confirmation links already sent and the old shortcodes
(`[diviskit_optin_form]`, `[skml_doi_form]`) keep working as legacy
aliases.

= Do I need Divi? =

No. The form is a shortcode with its own styling and runs on any theme. On
Divi 5 sites it picks up the Global Colors automatically.

== Screenshots ==

1. Opt-in form (frontend, adapts to Divi Global Colors)
2. Settings — provider select with MailerLite, Brevo, generic webhook or local only
3. Mail template editor with live preview
4. Subscriber list with consent proof and CSV export

== Upgrade Notice ==

= 0.7.0 =
Rename diviskit-optin → skit-optin. Settings, templates, subscriber table,
cron and already-sent confirmation links migrate/keep working
automatically.

= 0.6.0 =
Interest checkboxes are now a fully editable list (add/remove rows in
Settings). Existing int_* settings migrate automatically.

= 0.5.0 =
New provider "Webhook (generic)": confirmed subscribers are sent as a JSON
POST to any URL — works with any service that has an HTTP endpoint
(Zapier, Make, n8n, Mailchimp bridge, custom APIs).

= 0.4.0 =
Rename from sk-mailerlite-doi → diviskit-optin. Settings, subscriber table
and cron migrate automatically. Existing `[skml_doi_form]` shortcodes and
`skml/v1` confirmation links keep working (legacy aliases).

== Changelog ==

= 0.7.0 =
* Rename: Diviskit Optin → Skit Optin (slug skit-optin) — new plugin
  identity for the WordPress.org directory
* Migration: options, templates option, subscriber table
  (wp_diviskit_optin_subscribers → wp_skit_optin_subscribers) and cron
  hook are carried over
* Legacy aliases kept: REST `diviskit-optin/v1` + `skml/v1`, shortcodes
  `[diviskit_optin_form]` + `[skml_doi_form]`
* Security: mail template HTML now requires the unfiltered_html
  capability; without it the markup is filtered through wp_kses with a
  mail-document allowlist

= 0.6.0 =
* Interest checkboxes: the fixed 3-slot registry is replaced by a free
  add/remove list in Settings — any number of interests with label +
  optional MailerLite group ID
* New rows derive their slug from the label; existing slugs stay stable
* Legacy int_* options migrate transparently on read (no data loss)

= 0.5.0 =
* New provider "Webhook (generic)": JSON POST { event, email, interests,
  confirmed_at, site } to a configurable URL — 2xx counts as success,
  failures are stored and retried daily like the API providers
* Optional secret signs the body via HMAC-SHA256
  (X-Skit-Signature header, GitHub style)
* Connectivity check pings the hook with an { event: "ping" } event

= 0.4.0 =
* Rename: sk-mailerlite-doi → Diviskit Optin (slug diviskit-optin)
* Admin redesign: wordmark header, tab navigation
  (Subscribers / Mail templates / Settings), submenu under the Diviskit
  Agent menu when that plugin is active
* Mail templates: create/edit/duplicate/activate multiple HTML templates
  in the admin, placeholder system, live preview, test send, factory reset
  for built-in templates
* Four built-in designs: Standard (Vision yellow), brand blue, Minimal,
  Dark
* Migration: options, subscriber table (wp_skml_subscribers →
  wp_diviskit_optin_subscribers) and cron hook are carried over
* Legacy aliases: REST `skml/v1` + shortcode `[skml_doi_form]`
* Redirect query arg is now `?optin=confirmed|error` (was `?skml=`)

= 0.3.0 =
* Optional interest checkboxes in the form (slug whitelist)
* New `interests` column (DB version 2), admin list + CSV export
* MailerLite: per-interest configurable group ID is set on sync in
  addition to the base group

= 0.2.0 =
* Provider abstraction: MailerLite + Brevo + "local only"
* reCAPTCHA v3 (replacing the v2 checkbox)
* Configurable thank-you/error redirects

= 0.1.0 =
* Initial release.
