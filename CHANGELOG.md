# Changelog

All notable changes to this project are documented in this file.

The format follows [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/).
User-facing release announcements (in German) are published as
[GitHub Releases](../../releases); this file is the durable, in-repo
technical record — useful when diffing between versions or preparing an
upgrade.

## [Unreleased]

### Added

- Event descriptions support a restricted Markdown subset (bold, italic,
  links, lists, line breaks). Raw HTML, images and non-http(s) links are
  rendered as plain text. Applies to the guest view, the management page
  and the PDF export.

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
