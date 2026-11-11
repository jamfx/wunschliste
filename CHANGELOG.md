# Changelog

All notable changes to this project are documented in this file.

The format follows [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/).
User-facing release announcements (in German) are published as
[GitHub Releases](../../releases); this file is the durable, in-repo
technical record — useful when diffing between versions or preparing an
upgrade.

## [Unreleased]

## [1.3.0] - 2026-11-11

### Added

- Suggestions: a guest can tick "I will bring this too". On approval the
  suggested quantity becomes a claim of min(pledge, final quantity) — the
  organiser can still raise or lower the quantity. The organiser sees the
  pledge in the suggestion list; no "item claimed" mail is sent for it (they
  approved it themselves); the decision mail to the suggester states how many
  they bring. In gift mode a pledge takes one place. Requires
  `migrations/004_suggester_claim.sql` (apply via Admin → Database update).
- The organiser can remove a single claim on the management page (the ×
  next to a name), e.g. a pledge that the guest can no longer undo themselves.

### Changed

- All remaining hard-coded German texts moved into `lang/de.json` and
  `lang/en.json` (flash messages, account/2FA/admin pages, installer, error
  pages, PDF labels, JavaScript fallbacks). Dates follow the language's
  `format.date`.
- E-mails are now translated (DE/EN): organiser notifications and cron mails
  use the site default language; password-reset and invitation mails use the
  language of the person who triggered them; suggestion decisions use the
  suggester's language. Previously all of these were German only.

## [1.2.0] - 2026-11-11

### Security

- Rate limits no longer trust forgeable `X-Forwarded-For` /
  `CF-Connecting-IP` headers: `client_ip()` uses `REMOTE_ADDR`. Behind a
  reverse proxy, list it under `trusted_proxies` in `config/config.php`.

### Added

- Database migrations from the admin area: Admin → "Database update"
  (SuperAdmin only) applies pending `migrations/*.sql` files with one click,
  records them in a `migrations` table and shows a banner on the dashboard
  while something is pending. Existing installations are detected from their
  schema (no migration is run twice); fresh installs mark 001–003 as done.
  New schema changes go into new numbered files (see `migrations/README.md`).
- Optional proof-of-work captcha ([ALTCHA](https://altcha.org) v3, MIT,
  self-hosted, no cookies, no external requests) for "create list" and
  "suggest an item". Admin → Security: three levels (off / normal / high),
  default off. Accessible messages for missing JavaScript, unsupported
  browsers, expired or failed checks. Challenges are signed (HMAC) and
  single-use. New files: `assets/js/altcha/`, `vendor/altcha-org/altcha/`,
  `src/AltchaGuard.php`, `src/Controllers/AltchaController.php`,
  `templates/partials/altcha_widget.php`. No database migration needed.
- Spam protection for the public forms "create list" and "suggest an
  item": configurable minimum fill time (HMAC-signed timestamp, default
  5 s, 0 = off, admin → Security) and rejection of links/HTML in list
  titles, item names and guest names.

- Suggested items: guests can optionally add a quantity and an email
  address. If an address is given, the suggester is notified by email
  (in the language they used) when the organiser approves or rejects the
  suggestion, with an optional message from the organiser. The address is
  deleted once the decision is made. The organiser can adjust the quantity
  when approving. Requires `migrations/003_suggestion_notify.sql` on
  existing installations.

- Event descriptions support a restricted Markdown subset (bold, italic,
  links, lists, line breaks). Raw HTML, images and non-http(s) links are
  rendered as plain text. Applies to the guest view, the management page
  and the PDF export.

### Changed

- Wider public pages: content width 800 px → 960 px; form and reading pages
  (create/edit list, "list created", help, "My Security") share one
  800 px width (`.content-medium`); header and footer follow the wide
  admin layout on admin pages.
- The ALTCHA widget uses the site's primary colour and a higher-contrast frame.

## [1.1.0] - 2026-08-24

### Added

- User accounts: the SuperAdmin can invite registered users by email
  (24h single-use link to set a password)
- Forgot-password / reset-password flow for all accounts
- Two-factor authentication (TOTP, Passkeys) available to every account,
  not just the SuperAdmin — new "My Security" area at `/account/security`
- TOTP setup now requires confirming the recovery codes (with a fresh code
  from the authenticator app) before it's activated, so codes can't be
  silently skipped
- List creation can be restricted to registered users only (admin setting);
  lists created while logged in are now tied to that account as owner
- "My Lists" area (`/my/lists`) for any logged-in account
- After being redirected to log in (e.g. to manage an owned list, or to
  create a list in restricted mode), the user is sent back to the page
  they originally requested
- Version number shown in the public footer

### Changed

- Dashboard/user-list tables no longer require horizontal scrolling on
  wide screens (`.container--wide` now scales with the viewport)

### Fixed

- Fresh installs (`install.php`) failed outright with a SQL syntax error.
  The installer split `migrations/001_initial.sql` into individual
  statements with a naive `explode(';', ...)`, which breaks as soon as a
  statement contains a semicolon inside a quoted string or a `-- ...` line
  comment — which one of the `claims` table's column comments did. This
  had been latent since 1.0.0 and was never caught because upgrades so far
  went through phpMyAdmin/the `mysql` CLI, both of which parse SQL
  properly; only the installer's own naive splitter was affected. Replaced
  with `split_sql_statements()` (in `src/bootstrap.php`), a small parser
  that tracks quote/comment state so embedded semicolons aren't mistaken
  for statement terminators.
- Fresh installs no longer create the unused, empty `admin`/`admin_passkeys`
  tables. Those exist only to support upgrading pre-1.1.0 installations
  (see `migrations/002_multi_user.sql`) and were being created as dead
  weight on brand-new installs too. `migrations/001_initial.sql` now
  defines the current schema directly (`users`, `user_passkeys`,
  `user_invites`, `password_resets`, `events.user_id`), and `install.php`
  runs only that file for fresh installs. The upgrade path via
  `002_multi_user.sql` for existing pre-1.1.0 installations is unchanged.

Fully backward compatible: in the default "open" mode, an existing
installation behaves exactly as before. See [UPGRADE.md](UPGRADE.md) for
the required one-time database migration.

## [1.0.1] - 2026-08-03

- License file, demo link, badges (documentation-only changes)

## [1.0.0] - 2026-08-02

- Initial release
