=== TWK AEO Discovery ===
Contributors: richardlowe
Tags: sitemap, indexnow, schema, aeo, llms.txt
Requires at least: 5.6
Tested up to: 7.0
Requires PHP: 7.4
Stable tag: 1.7.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Entity-authority schema enrichment so AI answer engines cite you. Plus XML sitemap, IndexNow notifications, and llms.txt.

== Description ==

A modern sitemap plugin built for how search and answer engines work today. It does the core sitemap job correctly and adds what older plugins never did: IndexNow notifications for participating engines, entity-authority schema enrichment, and llms.txt for AI answer engines. No upsells, no telemetry, no dead Google-ping code.

What it does:

* Generates a dynamic XML sitemap index at /sitemap.xml with per-object sub-sitemaps (one per post type and taxonomy, paginated).
* Outputs only what modern crawlers use: clean <loc> and accurate <lastmod>. No priority/changefreq theater, which Google has ignored for years.
* Notifies IndexNow engines (Microsoft Bing, Yandex, Seznam.cz, Naver and others) automatically whenever you publish or update content.
* Enriches Slim SEO's Organization and Person schema with full entity-authority data (logo, sameAs incl. ORCID/ISNI/Wikidata, jobTitle, bio, knowsAbout) instead of emitting its own — no duplicate graphs.
* Publishes /llms.txt and /llms-full.txt for AI answer engines.
* Adds the sitemap line to the virtual robots.txt and leaves AI crawlers welcome by default.
* Replaces the built-in WordPress sitemap so you do not run two.

== Installation ==

1. Upload the `twk-discovery` folder to `/wp-content/plugins/`, or install the zip from Plugins > Add New > Upload Plugin.
2. Deactivate any other sitemap plugin (Yoast, Rank Math, the old XML Sitemap Generator) so you are not generating two sitemaps.
3. Activate the plugin. An IndexNow key is generated automatically and rewrite rules are flushed.
4. Open Settings > TWK AEO Discovery to adjust what is included.

== Frequently Asked Questions ==

= Does it conflict with Yoast, Rank Math, or AIOSEO? =

Not in this release. v1.6 only enriches Slim SEO's schema. If you run Yoast, Rank Math, AIOSEO, or The SEO Framework, the sitemap, IndexNow, and llms.txt features still work — but the Entity Authority enrichment will sit idle until v1.7 adds adapters for those plugins. You should deactivate any other plugin that generates an XML sitemap so you do not run two.

= What is IndexNow and do I need to do anything to use it? =

IndexNow is an open protocol for telling participating search engines (Microsoft Bing, Yandex, Seznam.cz, Naver, and others — not Google) when your content changes. The plugin generates an API key on activation, publishes it at /{key}.txt on your site root, and fires a notification on every publish or update. No external account or setup is required.

= Does it require Slim SEO? =

Only the Entity Authority enrichment feature requires Slim SEO. Sitemap, IndexNow, llms.txt, and robots.txt integration all work without Slim SEO installed. If you do not run Slim SEO, the Entity Authority tab simply has no effect — nothing breaks.

= What is llms.txt? =

A small text file at the root of your site that tells AI answer engines (ChatGPT, Claude, Perplexity, and similar) which pages on your site should be treated as authoritative. The plugin can auto-generate it from an intro and a list of key pages, or you can supply the full content yourself.

= Why does the plugin not ping Google? =

Google retired anonymous sitemap ping in 2023. The correct path now is to submit /sitemap.xml once in Google Search Console; Google rediscovers content via the sitemap and lastmod. Anything claiming to "ping Google" today is dead code.

= My sub-sitemap URL returns a 404. What is wrong? =

If the affected post type or taxonomy slug contains a hyphen (e.g. case-study, press-release), upgrade to 1.6.1 or later — earlier versions had a regex bug that mishandled hyphenated slugs. After upgrading, visit Settings > Permalinks and click Save to flush the rewrite rules.

= Will it work with my caching plugin? =

Sitemap, llms.txt, and the IndexNow key file are served with no-store headers so they are not cached by page caches or browsers. If your cache plugin still serves a stale sitemap, exclude /sitemap.xml, /sitemap-*.xml, /llms.txt, /llms-full.txt, and /*.txt from its caching rules.

= What happens to my settings if I deactivate the plugin? =

Settings are kept in the WordPress options table and survive deactivation. If you uninstall the plugin (Plugins > Delete), the uninstall script removes all settings cleanly. The physical /llms.txt and /{key}.txt files written to your site root are NOT removed automatically; delete them manually if you no longer want them served.

= Can I move settings between sites? =

Yes. The Tools tab has Export and Import buttons that save and load a JSON file with all settings. The IndexNow key is not included (each site keeps its own key); after importing, review the site-specific fields (Organization details, canonical @ids, llms.txt content, sitemap post types) and save.

== Screenshots ==

1. The post-wizard setup report — at-a-glance summary, graph health validation, action items, and per-identifier setup walkthroughs all in one view.
2. The branching setup wizard starts by asking what your site represents — a person, an organization, or both.
3. Every wizard field has an inline help description explaining what it is and why it matters for AEO.
4. Per-identifier setup walkthroughs tell you how to acquire each pending identifier — full step-by-step instructions, not just a list.
5. The Entity Authority settings tab — detected SEO plugin banner, run-wizard button, enrichment toggles, and all the entity fields ready to edit anytime.
6. The current entity graph view — copyable JSON-LD output and direct links to Google Rich Results Test and the Schema.org validator.
7. The wizard's organization identifiers step — LinkedIn, Wikidata, Crunchbase, X, Facebook, YouTube. Blank fields are surfaced as pending in the report.
8. The wizard's author identifiers step — ORCID, ISNI, Wikidata, Google Scholar, LinkedIn, Muck Rack, Amazon Author, Goodreads, Open Library.
9. The Diagnostics tab — plugin health, sitemap status, and the plugin's own error log. Empty log is the healthy state.

== The settings screen ==

Found at Settings > TWK AEO Discovery. The page shows your live sitemap URL at the top.

Sitemap:

* Enable sitemap — turn dynamic generation on or off.
* Included post types / taxonomies — checkboxes for every public type on the site, including custom post types and custom taxonomies. Attachments are excluded.
* URLs per sitemap file — how many URLs before a sub-sitemap paginates (default 2000; protocol max is 50,000).
* robots.txt — add the sitemap line to the virtual robots.txt, and disable the built-in WordPress sitemap to avoid duplicates.

Search engine notifications (IndexNow):

* IndexNow — when on, every publish or update is pushed to participating engines.
* API key — your generated key and the public key-file URL ({key}.txt at the site root). Submissions show their last result here.

Entity authority (Slim SEO schema):

* Does not emit its own schema. Enriches the Organization and Person nodes Slim SEO already outputs, via the slim_seo_schema_graph filter.
* Adds the logo, sameAs (including ORCID/ISNI/Wikidata/LinkedIn), job title, bio, and knowsAbout that Slim SEO leaves thin — matching sameAs is what reconciles every post's author with your canonical entity.
* Optional: suppress Slim SEO schema on the front page so a hand-built homepage graph stands alone.
* Requires Slim SEO active; harmless if it is not. Deactivating this plugin returns Slim SEO's basic schema — nothing breaks.

AI answer engines (AEO):

* llms.txt — publish /llms.txt and /llms-full.txt.
* Content source — Auto-generate from site content, or Custom (an editable field served verbatim).
* If you already had a physical llms.txt in your site root, its contents are imported into the editable field on activation so nothing is lost.
* By default the file is served dynamically (nothing is written to disk). A "Write current content to llms.txt" button exports it to a physical file so the content survives even if the plugin is deleted; a "Remove" button deletes that file to go back to dynamic serving.
* Note: if a physical llms.txt exists, your web server serves it directly and the editable version is inactive until the file is removed.
* AI crawlers — add a welcome note to robots.txt (blocks nothing).

Below the form, two buttons let you submit the homepage or the 100 most recent URLs to IndexNow on demand.

== Submitting to search engines ==

Google no longer accepts anonymous sitemap pings (retired 2023). The correct path now:

* Google: submit /sitemap.xml once in Google Search Console. Google rediscovers changes via the sitemap and lastmod.
* Bing: add the site in Bing Webmaster Tools (you can import settings from Search Console). IndexNow then handles ongoing change notifications.
* Yandex, Seznam.cz, Naver and other IndexNow participants are notified automatically through the IndexNow key.

Verify the key file at https://yoursite/{key}.txt loads and returns the key as plain text.

== Notes for AEO ==

The plugin handles the technical groundwork. Content still does the rest:

* Lead each section with a direct answer to the question in its heading.
* Use concrete numbers and cite sources; unsupported claims rarely get cited.
* Keep pages fresh — lastmod should reflect real changes.
* Make sure you are not blocking AI crawlers (GPTBot, ClaudeBot, PerplexityBot, Google-Extended, OAI-SearchBot) in a physical robots.txt if you want to be cited.

== Upgrade Notice ==

= 1.6.4 =
Settings page now uses tabs (Sitemap, IndexNow, Entity Authority, AI Engines, Tools) for easier navigation. Every field now has an inline help icon. Translation domain now loads correctly. No breaking changes; settings carry over as-is.

= 1.6.1 =
Important bug fix for sub-sitemap 404s on hyphenated post type and taxonomy slugs (press-release, case-study, about-richard-lowe). Upgrade if you use any custom post type or taxonomy with a hyphen in its slug. Visit Settings > Permalinks and click Save after upgrading.

== Changelog ==

= 1.7.0 =
* Multi-SEO support: Slim SEO, Yoast SEO, Rank Math, All in One SEO, and The SEO Framework. The plugin detects which SEO plugin is active and enriches its Organization and Person schema directly via the host plugin's filter; reuses one per-node enrichment engine across all five, with a recursive walker for hosts whose graph shape differs.
* Standalone fallback: when no supported SEO plugin is active, the plugin emits its own minimal WebSite + Organization + Person graph on the front page so entity authority still works on a bare site.
* Setup wizard: a branching, non-destructive entity-authority interview. Pre-fills every field on re-run, only writes fields you filled, Skip writes nothing, and a per-field Clear is the only deletion path. Tracks "do not have yet" separately from data.
* Post-wizard setup report: at-a-glance summary, graph-health validation, action items, full per-identifier setup instructions for ORCID, ISNI, Wikidata, Google Scholar, LinkedIn, Muck Rack, Amazon Author, Goodreads, Open Library, LinkedIn (company), Crunchbase, X, Facebook, YouTube. Copy-to-clipboard and download-as-text.
* First-run redirect, dismissible setup banner, and an always-available Run setup wizard button on the Entity Authority tab.

= 1.6.4 =
* Settings screen reorganized into tabs across the top — Sitemap, IndexNow, Entity Authority, AI Engines, Tools — so each section is its own self-contained pane.
* Inline help icons on every settings field. Hover over the (?) next to a label for a one-line description and a generic example. Helps new users self-orient without leaving the screen.
* Saves on one tab no longer overwrite the other tabs' values. Each tab carries a hidden submission marker and the sanitize callback merges with current saved settings, so an edit to Sitemap leaves Entity Authority untouched and vice versa.
* Translation domain is now actually loaded (load_plugin_textdomain on plugins_loaded). The text-domain header was set since 1.0 but translations were not finding their files, so /lang/*.mo files now work.

= 1.6.3 =
* Plugin version number now shown next to the settings page heading, for quick confirmation after uploading an update.

= 1.6.1 =
* Fixed sitemap sub-files 404'ing for post types and taxonomies whose slugs contain a hyphen (e.g. press-release, case-study, about-richard-lowe, post-tag). The object-slug character class in the routing regex did not include hyphens, so any sitemap URL whose CPT or taxonomy slug contained a dash failed to resolve. Single-word slugs were unaffected. The same fix is applied to both routing paths (rewrite-rule and direct parse_request match).

= 1.6.0 =
* Added settings export and import (Move settings to another site), for setting up the plugin on a second site without re-entering everything. The IndexNow key stays per-site; imported sitemap post types are validated against the destination site.

= 1.5.1 =
* Fixed a fatal error on servers with short_open_tag enabled: the sitemap stylesheet's XML declaration is now emitted safely instead of as a literal that some PHP configs misread as code.

= 1.5.0 =
* Added a styled, clickable HTML view of the sitemap in the browser (served via sitemap.xsl). Toggle under Sitemap settings; crawlers ignore it, it is purely for human viewing.

= 1.4.3 =
* Sitemap, llms.txt, and IndexNow key responses now send no-store cache headers, so page caches and browsers do not fossilize them.

= 1.4.2 =
* robots.txt Sitemap line now runs last in the filter chain, so it is not overwritten by another plugin that rewrites robots.txt.

= 1.4.1 =
* Fixed /sitemap.xml (and llms.txt, IndexNow key file) falling through to the homepage. Routing now matches the request path on parse_request, so it no longer depends on rewrite rules being flushed into the database.

= 1.4.0 =
* Entity Authority can now rewrite Slim SEO's Person and Organization @id to your canonical ids and repoint all references, unifying the entity across the whole site (not just sameAs reconciliation).
* Added Person fields: alternateName, url, givenName, familyName. Added Organization fields: alternateName, description, areaServed.

= 1.3.0 =
* Added contact points to Entity Authority — define them once (contactType | url | description) and they ride on Slim SEO's Organization sitewide, replacing per-page ContactPage blocks.

= 1.2.0 =
* Replaced the self-emitted schema with Slim SEO entity enrichment via the slim_seo_schema_graph filter (no more duplicate Organization/WebSite/Article nodes).
* Added Entity Authority fields: organization logo/sameAs/knowsAbout and author name/bio/jobTitle/image/sameAs/knowsAbout.
* Added optional front-page schema suppression for sites with a hand-built homepage graph.

= 1.1.0 =
* llms.txt is now editable in the plugin, with Auto-generate and Custom (verbatim) modes.
* Imports an existing physical llms.txt on activation so it is not lost.
* Added "Write to file" / "Remove file" actions so the content can be made durable against plugin deletion.

= 1.0.0 =
* Initial release: dynamic sitemap index and sub-sitemaps, IndexNow, JSON-LD schema, llms.txt, robots.txt integration.
