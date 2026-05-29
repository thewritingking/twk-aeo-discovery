# Changelog

All notable changes to TWK AEO Discovery are documented in this file. Format follows [Keep a Changelog](https://keepachangelog.com/en/1.1.0/) and the project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

### Standards (submission-prep fixes after v1.7.0 was first packaged)

- **Plugin Check clean**: addressed all 54 findings the WordPress.org `Plugin Check` tool surfaced across the General, Plugin Repo, Security, Performance, and Accessibility categories. Codebase now returns zero findings.
- Refactored `TWKD_Admin::tip()` to echo HTML directly instead of returning it, so call sites no longer trip `WordPress.Security.EscapeOutput.OutputNotEscaped` (33 call sites cleaned up). The escaped-attribute logic is unchanged; output is identical.
- Moved `esc_url()` from inside the `$tab_url` closure to the six call sites that echo it, so PHPCS can see the escape at the output boundary.
- Annotated `TWKD_Wizard::save_step()` `$_POST` reads with a `phpcs:disable`/`enable` block documenting that nonce verification happens in the calling `handle_save()` and that field values are sanitized per-type downstream by `sanitize_value()` and `save_identifiers()`.
- Added `phpcs:ignore` annotations on the two `$_FILES['twkd_import_file']['tmp_name']` reads in `TWKD_Admin::import_settings()`, since that value is a server-generated temp filename rather than user-controlled input.
- Added `wp_unslash()` to the `$_POST['twkd_clear']` keys before sanitization in `TWKD_Wizard::save_step()`.
- Renamed the local variables `$sites` and `$site_id` in `uninstall.php` to `$twkd_sites` and `$twkd_site_id` to satisfy the `NonPrefixedVariableFound` check.
- Removed the `Domain Path: /lang` plugin header — no `/lang` folder ships with the plugin, and translations are loaded automatically by WordPress 4.6+ for WP.org-hosted plugins.
- Removed the `load_plugin_textdomain()` call for the same reason (WordPress 4.6+ auto-loads translations from the WP.org translate platform, no manual call needed).

### Submission

- Changed `Plugin URI:` header from `https://thewritingking.com/` to `https://github.com/thewritingking/twk-aeo-discovery` so it differs from `Author URI:`, satisfying WordPress.org's submission-time validator.
- Submitted to WordPress.org plugin directory under slug `twk-aeo-discovery`. Awaiting human review.

## [1.7.0] — 2026-05-28

First public release of TWK AEO Discovery — the WordPress.org-distribution version of the private TWK Discovery plugin, with multi-SEO support and a guided setup flow added.

### Added

- **Multi-SEO entity enrichment** for five host plugins: Slim SEO, Yoast SEO, Rank Math, All in One SEO, and The SEO Framework. Single per-node enrichment engine shared across all five, with a recursive walker for hosts whose graph shape differs from the flat-array case.
- **Standalone fallback emission** when no host SEO plugin is active. Emits a minimal WebSite + Organization + Person graph via `wp_head` on every page so entity authority works on a bare site.
- **Branching setup wizard** with non-destructive save semantics: pre-fill from existing values on every re-run, skip writes nothing, blank fields preserve existing data, per-field Clear is the sole deletion path. First-activation transient redirect and dismissible setup banner.
- **Post-wizard setup report** with six sections: at-a-glance summary, graph-health validation with critical/warning/info levels, action items derived from missing identifiers, full per-identifier setup walkthroughs for 15 identifiers (ORCID, ISNI, Wikidata, Google Scholar, LinkedIn, Muck Rack, Amazon Author, Goodreads, Open Library, LinkedIn Company, Crunchbase, X, Facebook, YouTube), external verification links, and a copy-paste-ready current entity graph view.
- **Copy and download actions** on the report: copy full report, copy graph JSON, download as text.
- **Per-field help descriptions** throughout the wizard explaining what each field is, why it matters for AEO, and any specific requirements (logo and photo URLs hosted on-site for Google rich results, canonical `@id` URL conventions).

### Changed

- Standalone graph emission now runs on every page instead of front-page-only — entity authority appears on posts, archives, and any other URL when no host SEO plugin is active.
- `entity_suppress_front` semantics clarified: now consistently suppresses only the front page emission across all three code paths (host enrichment, standalone, build-graph).
- Plugin name, slug, text domain, and folder name renamed from the private `twk-discovery` build to `twk-aeo-discovery`.

### Performance

- Autoloaded `twkd_wizard_done` boolean flag short-circuits the setup-banner state lookup after the wizard is completed or dismissed, eliminating one database query per admin page load on already-configured installs. Lazy migration handles existing completed installs without an explicit migration routine.

### Security

- `maybe_first_run_redirect` consumes the activation transient *after* the capability check, so a non-admin user landing on `/wp-admin/` during the 60-second activation window cannot deny the activator's redirect to the wizard.

### Removed

- "I do not have one yet" checkbox on wizard identifier steps. Pending identifiers are now derived automatically from blank/unmatched URLs, eliminating an unnecessary click during the wizard.

### Standards

- Admin CSS extracted from inline `<style>` to `assets/admin.css` and enqueued via `wp_enqueue_style` on plugin admin pages only.
- Readme tags trimmed to the WordPress.org five-tag maximum (`sitemap, indexnow, schema, aeo, llms.txt`).
- Filesystem writes (llms.txt write and delete) converted from raw `file_put_contents`/`unlink` to `WP_Filesystem` API.
- Plugin header `Description:` field harmonized with `readme.txt` short description.

## Earlier private-build versions

Versions 1.0.0 through 1.6.6 were the private TWK Discovery site-specific build. They are not distributed via this repository or WordPress.org and are documented only in the maintainer reference. The 1.7.0 release represents the first version published outside of personal sites.

[Unreleased]: https://github.com/thewritingking/twk-aeo-discovery/compare/v1.7.0...HEAD
[1.7.0]: https://github.com/thewritingking/twk-aeo-discovery/releases/tag/v1.7.0
