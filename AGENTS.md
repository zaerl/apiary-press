# AGENTS.md

## Project Overview

This repository contains a WordPress plugin powered by WpApp
(`akirk/wp-app`).

The plugin is used to keep track of beehives. Users can save hive records and
record visits, treatments, feedings, and harvests they make on those hives.

- Plugin slug / text domain: `apiary-press`
- Namespace: `ApiaryPress\` (PSR-4, but classes live in `class-<kebab>.php`
  files per WordPress convention; the autoloader in `apiary-press.php`
  bridges the two)
- Requires PHP 7.4+, WordPress 6.0+
- Base app URL path: `/apiary-press/`

## Domain Notes

- An **apiary** is a list of hives, usually positioned in the same
  geographic area.
- A **hive** represents a beehive being tracked by the beekeeper. Hives
  carry latitude/longitude and a structured "queen" record (year, color,
  marked/clipped flags, origin, installed date). When no color is set
  manually, it is derived from the queen's birth year using the
  international beekeeping color cycle (blue/white/yellow/red/green).
- A **visit** represents an inspection or activity performed on a hive. A
  visit can store a weather snapshot fetched from the MET Weather API and
  can carry attached media (photos).
- A **treatment** and a **feeding** share the same shape (date, product,
  quantity, unit, optional end date) and are stored under a single post
  type with a `kind` discriminator (`treatment` | `feeding`).
- A **harvest** records a honey harvest entry on a hive (quantity in kg,
  honey type, frames extracted, extraction method).
- Changes should preserve the plugin's WordPress integration and
  WpApp-based structure.

## Architecture

- `apiary-press.php` — plugin bootstrap: vendor autoload, custom
  class-file autoloader, `plugins_loaded` / activation / deactivation
  hooks.
- `src/class-app.php` — `App extends WpApp\BaseApp`. Constructs the
  `WpApp` instance, requires login + `edit_posts` capability, registers
  post types and meta, enqueues the shared stylesheet on app requests,
  declares routes in `setup_routes()`, declares menu items in
  `setup_menu()`.
- `src/class-{apiary,hive,visit,treatment,harvest,weather}.php` — one
  class per domain entity. Each entity class owns its `register_post_types()`,
  `register_meta()`, sanitization callbacks, and read helpers.
- `templates/` — one PHP template per WpApp route (e.g. `hive.php`,
  `hive-form.php`, `hive-qr.php`, `visit.php`, `treatment.php`,
  `harvest.php`, `apiary.php`, `apiary-form.php`, `index.php`).
- `assets/` — shared stylesheet plus MET Weather SVG icons (one per
  `symbol_code`).

### Routes

Registered in `App::setup_routes()` (`apiary-press/` is the base path):

- `apiary/new`, `apiary/{id}`, `apiary/{id}/edit`
- `apiary/{apiary_id}/hive/new`
- `apiary/{apiary_id}/hive/{id}`, `apiary/{apiary_id}/hive/{id}/edit`,
  `apiary/{apiary_id}/hive/{id}/qr`
- `apiary/{apiary_id}/hive/{id}/visit/{hive_visit}`
- `apiary/{apiary_id}/hive/{id}/treatment/{hive_treatment}`
- `apiary/{apiary_id}/hive/{id}/harvest/{hive_harvest}`

Use `ApiaryPress\App::get_url( $path )` to build in-app URLs and
`App::get_asset_url( $relative )` for assets (adds a filemtime cache
buster).

### Storage model

No custom tables. Everything is stored as WordPress posts + post meta:

- `ap_apiary` — top-level apiary
- `ap_hive` — hive, child of an apiary via `post_parent`
- `ap_hive_visit` — visit, child of a hive via `post_parent`
- `ap_hive_treatment` — treatment/feeding, child of a hive via
  `post_parent`
- `ap_hive_harvest` — harvest, child of a hive via `post_parent`

All meta is registered with `show_in_rest => true` and per-post
`auth_callback`s that check `edit_post` for the target post. Visit media
is stored as standard WordPress attachments parented to the visit post.

## External Services

- **MET Weather API** (`https://api.met.no/weatherapi/locationforecast/2.0/compact`)
  — fetched in `Weather::fetch_met_weather_snapshot()` for the hive's
  coordinates when a visit is saved. SVG icons for each `symbol_code`
  live under `assets/`. User-agent identifies the plugin per MET's
  terms of service.

## Style

- `#E7AE43` is the app's main color.

## Tooling

- WP-CLI is available; invoke it as `wp`.
- Composer scripts:
  - `composer lint` — PHPCS against `phpcs.xml.dist` (WordPress Coding
    Standards over `apiary-press.php`, `src/`, `templates/`).
  - `composer format` — PHPCBF autofix.
  - `composer make-pot` / `update-po` / `make-mo` / `i18n` — gettext
    workflow over `languages/`. English source POT, with an `it_IT`
    translation.
- PHPCS prefixes whitelisted for `WordPress.NamingConventions.PrefixAllGlobals`:
  `appr`, `apiary_press`, `ApiaryPress`.
- Dependencies via Composer: `akirk/wp-app` (the app framework),
  `chillerlan/php-qrcode` (used by `hive-qr.php`).
