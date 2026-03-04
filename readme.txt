=== Snappy ===

Contributors: webguyio
Donate link: https://webguy.io/donate
Tags: cache, caching, page cache, speed optimization
Requires at least: 5.0
Tested up to: 6.9
Requires PHP: 7.4
Stable tag: 0.1
License: GPL
License URI: https://www.gnu.org/licenses/gpl.html

Caching for a snappier website.

== Description ==

[💬 Ask Question](https://github.com/webguyio/snappy/issues) | [📧 Email Me](mailto:webguywork@gmail.com)

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

= Advanced Version =

Snappy is fully capable with its core caching capability, but if you want or need additional optimization and control, advanced settings are available at [snappywp.me](https://snappywp.me/).

- HTML, CSS, and JavaScript minification
- GZIP compression
- Lazy loading for images and videos
- Video embed optimization (YouTube/Vimeo facades)
- Font preloading
- Database cleanup (spam, revisions, transients)
- Automatic weekly database optimization
- Cache preloading from sitemap
- Defer JavaScript with exclusions
- Critical CSS extraction and inlining
- WordPress Heartbeat control
- Resource hints (preload, prefetch, DNS-prefetch)
- Browser caching headers via .htaccess
- Security headers (X-Frame-Options, CSP, etc.)
- Cloudflare integration with optimized settings
- CDN integration with URL rewriting
- Settings import/export
- Self-hosted update system

== Changelog ==

= 0.1 =
* New