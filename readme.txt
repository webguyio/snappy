=== Snappy ===

Contributors: webguyio
Donate link: https://webguy.io/donate
Tags: cache, caching, page cache, speed optimization
Requires at least: 5.0
Tested up to: 6.8
Requires PHP: 7.4
Stable tag: 0.1
License: GPL
License URI: https://www.gnu.org/licenses/gpl.html

Caching for a snappier website.

== Description ==

Caching for a snappier website.

When a WordPress page loads normally, it goes through:

1. PHP execution
2. Database queries (often 20-50+ queries)
3. Theme processing
4. Plugin execution
5. HTML generation

With Snappy file-based caching, it skips all that and just serves a static HTML file.

Estimates for performance improvement:

- 2x faster is conservative and achievable for most sites
- 5x faster is realistic for database-heavy sites
- 10x faster is possible for poorly optimized sites with many plugins

== Changelog ==

= 0.1 =
* New