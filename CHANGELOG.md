# Changelog

All notable changes to TWK AEO Discovery are documented in this file. Format follows [Keep a Changelog](https://keepachangelog.com/en/1.1.0/) and the project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

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
