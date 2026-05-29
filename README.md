# TWK AEO Discovery

[![License: GPL v2+](https://img.shields.io/badge/License-GPLv2%2B-blue.svg)](https://www.gnu.org/licenses/gpl-2.0)
[![WordPress 5.6+](https://img.shields.io/badge/WordPress-5.6%2B-green.svg)](https://wordpress.org/)
[![PHP 7.4+](https://img.shields.io/badge/PHP-7.4%2B-purple.svg)](https://php.net/)

A WordPress plugin for **entity-authority schema enrichment**, dynamic XML sitemap, IndexNow notifications, and llms.txt — built for how search and AI answer engines actually work today.

## What it does

Modern search has split. Classic search is Google and Bing, indexing-driven. **Answer engines** — ChatGPT, Claude, Perplexity, Google AI Overviews, Microsoft Copilot — don't rank pages; they synthesize answers from sources they trust. Getting cited requires **entity authority**: verifiable confirmation of who you are, expressed through schema.org `sameAs` profiles, ORCID/ISNI/Wikidata identifiers, `knowsAbout` topics, and stable canonical `@id` references.

Existing SEO plugins ship Organization and Person schema with bare-minimum fields. They don't ship the identifier-rich, knowsAbout-rich, sameAs-rich graphs that AEO requires. TWK AEO Discovery fills that gap.

## How it works

Rather than replace your existing SEO plugin, this plugin **enriches** what it already emits. It hooks the schema graph filter, merges your entity-authority data into the host's Organization and Person nodes, and emits the combined output. Host plugin per-page schema (Article, BlogPosting, WebPage, breadcrumbs) is preserved entirely.

**Supported SEO plugins:**

| Plugin | Filter hooked | Code path |
|--------|---------------|-----------|
| Slim SEO | `slim_seo_schema_graph` | Whole-graph enrich |
| Yoast SEO | `wpseo_schema_graph` | Whole-graph enrich |
| Rank Math | `rank_math/json_ld` | Recursive walker |
| All in One SEO | `aioseo_schema_output` | Recursive walker |
| The SEO Framework | `the_seo_framework_schema_graph_data` | Recursive walker |
| *(none — standalone)* | `wp_head` action | Direct emission |

When no host SEO plugin is active, the plugin emits its own WebSite + Organization + Person graph on every page.

## Features

- **Multi-SEO entity enrichment** — merges into Yoast/Rank Math/AIOSEO/TSF/Slim SEO via their filters
- **Standalone fallback** — own JSON-LD graph emission when no host is active
- **Setup wizard** — branching, non-destructive interview with per-field help and pre-fill
- **Post-wizard report** — at-a-glance summary, graph health validation, action items, per-identifier setup walkthroughs (ORCID, ISNI, Wikidata, Google Scholar, LinkedIn, Muck Rack, Amazon Author, Goodreads, Open Library, Crunchbase, X, Facebook, YouTube), copy/download
- **Dynamic XML sitemap** — sitemap index at `/sitemap.xml`, per-object-type sub-sitemaps, configurable post-type and taxonomy inclusion
- **IndexNow notifications** — automatic submission to Bing, Yandex, Seznam, Naver on post publish/update
- **llms.txt** — auto-generated `/llms.txt` and `/llms-full.txt` for AI crawler ingestion
- **robots.txt integration** — sitemap line + AI-crawler welcome note

## Requirements

- WordPress 5.6 or later
- PHP 7.4 or later (no PHP-8-only syntax)
- A supported SEO plugin (optional — works standalone too)

## Installation

### From WordPress.org (recommended once published)

1. WordPress admin → Plugins → Add New
2. Search for "TWK AEO Discovery"
3. Click Install Now, then Activate
4. The plugin redirects you to the setup wizard on first activation

### From GitHub (current path)

1. Download the latest release zip from [Releases](../../releases)
2. WordPress admin → Plugins → Add New → Upload Plugin
3. Choose the zip, install, activate

## Usage

After activation:

1. The plugin redirects you to its **setup wizard** — a branching interview that walks you through what your site represents (person, organization, or both), gathers Organization fields (name, logo, sameAs URLs, knowsAbout), Person fields (name, jobTitle, bio, ORCID, ISNI, Wikidata, etc.), and finishes by enabling enrichment.
2. The wizard is **non-destructive**: skipping any step writes nothing, blank fields are preserved, per-field Clear is the only deletion path.
3. The wizard ends with a **setup report** that shows what's configured, validates graph health, lists pending identifiers, and provides per-identifier setup walkthroughs.
4. Configure further at **Settings → TWK AEO Discovery** — six tabs: Sitemap, IndexNow, Entity Authority, AI Engines, Tools, Diagnostics.

## Architecture

Eight singleton classes, each with a single responsibility:

- `TWKD_Entity` — schema enrichment engine
- `TWKD_Wizard` — setup wizard state machine
- `TWKD_Report` — post-wizard diagnostic view
- `TWKD_Instructions` — per-identifier setup walkthroughs (15)
- `TWKD_Sitemap` — XML generation, rewrite rules, robots.txt
- `TWKD_IndexNow` — submission and key management
- `TWKD_LLMS` — llms.txt generation
- `TWKD_Admin` — settings UI

Admin-only classes are gated inside `is_admin()` so they don't register hooks on front-end requests. Total LOC: ~4,100.

For deeper technical detail see the maintainer documentation.

## Development

```bash
git clone https://github.com/thewritingking/twk-aeo-discovery.git
cd twk-aeo-discovery
# Edit, test, lint:
find . -name "*.php" -exec php -l {} \;
```

The plugin lints clean across PHP 7.4–8.x. No PHP-8-only syntax is used so the floor is firm at 7.4.

## Releasing

1. Update `TWKD_VERSION` in `twk-aeo-discovery.php`
2. Update `Version:` in the plugin header
3. Update `Stable tag:` in `readme.txt`
4. Add a changelog entry to `readme.txt` and `CHANGELOG.md`
5. Tag the release in Git: `git tag v1.7.1 && git push --tags`
6. Create a GitHub Release from the tag — the included GitHub Action syncs the release to WordPress.org SVN automatically

## License

GPLv2 or later. See [LICENSE](./LICENSE).

## Author

Built by [Richard Lowe](https://thewritingking.com) — professional ghostwriter, author, and the person who got tired of every SEO plugin pretending entity authority wasn't a thing.

- Website: [thewritingking.com](https://thewritingking.com)
- Books: [masterofworlds.com](https://masterofworlds.com)
- LinkedIn: [richardlowejr](https://www.linkedin.com/in/richardlowejr/)
- ORCID: [0009-0009-2039-4899](https://orcid.org/0009-0009-2039-4899)

## Issues and contributions

Bug reports and feature requests welcome via [GitHub Issues](../../issues). Pull requests considered but please open an issue first to discuss the change.
