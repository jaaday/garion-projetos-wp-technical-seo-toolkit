=== Garion Projetos Technical SEO Toolkit ===
Contributors: garionprojetos
Tags: seo, redirects, sitemap, structured-data, robots
Requires at least: 6.0
Tested up to: 7.0
Requires PHP: 8.0
Stable tag: 0.3.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Technical SEO tools: redirects, 404 monitor, XML sitemap, broken link detection, structured data, canonical control, robots and Open Graph/Twitter Card tags.

== Description ==

Technical SEO Toolkit adds a set of technical SEO tools to WordPress:

* Redirect management, with CSV import/export
* 404 monitor: logs real "page not found" hits and turns them into redirects with one click
* XML sitemap (index + paginated per-post-type sitemaps), linked automatically from robots.txt
* Broken link detection
* Structured data insertion (Schema.org)
* Canonical control
* robots.txt / meta robots configuration
* Open Graph and Twitter Card tags, with per-post overrides and a live social preview
* Basic page auditing, with search and pagination
* WordPress REST API integration

This plugin does not send data to external servers. All processing happens locally, on your own site.

== Installation ==

1. Upload the plugin folder to `/wp-content/plugins/`.
2. Activate the plugin from the "Plugins" screen.
3. Open the new "Technical SEO" menu in the admin sidebar to manage redirects, review 404s, run a broken link scan, view the page audit and configure settings.

== Frequently Asked Questions ==

= Does this plugin send data to external services? =

No. All SEO checks run locally. The broken link scanner does contact the URLs found in your own content to check whether they still respond, but this is a normal HTTP check (like a browser visiting a link), not a data submission to a third-party service.

= Where do I manage redirects? =

Under "Technical SEO > Redirects" in the admin sidebar. You can also import/export redirects as CSV.

= How does the 404 monitor work? =

Every time a visitor hits a real "page not found" on your site, the plugin logs the URL, referrer and hit count under "Technical SEO > 404 Monitor". Click "Create redirect" on any entry to pre-fill a new redirect with that URL.

= Where is my sitemap? =

At `/sitemap.xml`, and it's referenced automatically in your site's robots.txt. Posts/pages marked "Noindex" are excluded.

= How often does the broken link scan run? =

Automatically every 10 minutes, in small batches, until every published post and page has been checked. You can also trigger an immediate scan from "Technical SEO > Broken Links".

== Changelog ==

= 0.3.0 =
* Added a 404 monitor that logs real "not found" hits and lets you turn them into redirects with one click.
* Added an XML sitemap (index + paginated per-post-type sitemaps), linked automatically from robots.txt and excluding noindexed content.
* Added Open Graph and Twitter Card meta tags, with per-post title/description/image overrides, a media library picker and a live social preview in the post editor.
* Added search and pagination to the Redirects, 404 Monitor, Broken Links and Page Audit tables.
* Added CSV export/import for redirects.

= 0.2.0 =
* Implemented all planned features: redirect management, broken link scanner, structured data (JSON-LD), canonical URL override, meta robots + robots.txt, page audit and REST API endpoints.
* Added admin screen with Redirects, Broken Links, Page Audit and Settings tabs.
* Added post-editor metabox for meta description, canonical override and noindex/nofollow.

= 0.1.0 =
* Initial release.

== Upgrade Notice ==

= 0.3.0 =
Adds a 404 monitor, XML sitemap and Open Graph/Twitter Card tags, plus search/pagination and CSV import/export for redirects.
