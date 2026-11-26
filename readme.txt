=== Search with Algolia Headless extension ===
Donate link: https://www.amazon.jp/hz/wishlist/ls/1UYH9PSDMB3FZ?ref_=wl_share
Tags: algolia,headless,search,wp-cli
Requires at least: 5.5
Tested up to: 7.2
Requires PHP: 7.4
Stable tag: 0.2.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Extension plugin for WP Search with Algolia. Customize the permalink of the indices for headless WordPress setups.

== Description ==

WP Search with Algolia can index your WordPress data into Algolia.
When using WordPress as a headless CMS, you need to replace the indices' permalinks from the WordPress domain to your headless frontend domain.

This plugin automatically replaces WordPress URLs with your headless site URLs in Algolia indices.

**Features:**

* **URL Preview** - See real-time preview of URL replacements in the settings page
* **Environment Variable Support** - Configure domain via `ALGOLIA_HEADLESS_DOMAIN` constant in wp-config.php
* **Debug Logging** - Track URL replacements when WP_DEBUG is enabled (logs stored securely in database)
* **Admin Notices** - Helpful warnings for missing configuration
* **Site Health Integration** - Check plugin status in WordPress Site Health
* **WP-CLI Support** - Manage settings via command line
* **Security Focused** - Proper escaping, validation, and secure logging

**WP-CLI Commands:**

* `wp algolia-headless status` - View configuration status
* `wp algolia-headless set-domain <url>` - Set headless domain
* `wp algolia-headless test <url>` - Test URL replacement
* `wp algolia-headless logs` - View debug logs (requires WP_DEBUG)
* `wp algolia-headless clear-logs` - Clear debug logs

== Installation ==

1. Install and activate the plugin
2. Activate WP Search with Algolia plugin (required)
3. Configure WP Search with Algolia plugin settings
4. Go to `Settings > Reading` page and enter your headless site domain
5. Create indices using WP Search with Algolia
6. The plugin will automatically replace WordPress URLs with your headless domain

**Alternative: Environment Variable Configuration**

Add this to your `wp-config.php`:

`define( 'ALGOLIA_HEADLESS_DOMAIN', 'https://your-headless-site.com' );`

This will override the option set in the admin panel.

== Frequently Asked Questions ==

= How can I replace permalinks for existing indices? =

You need to re-index your content using WP Search with Algolia plugin after configuring this plugin.

= Does this work without WP Search with Algolia? =

No, this is an extension plugin that requires WP Search with Algolia to be installed and active.

= How do I enable debug logging? =

Add `define( 'WP_DEBUG', true );` to your wp-config.php. Logs are stored securely in the database (not accessible via web).

= Can I use WP-CLI to manage settings? =

Yes! Use `wp algolia-headless --help` to see all available commands.

= Where are debug logs stored? =

Logs are stored in WordPress transients (database) when WP_DEBUG is enabled. They are not accessible from the web and expire after 7 days.

== Screenshots ==

1. Settings page with real-time URL preview
2. Admin notices for configuration issues
3. Site Health integration
4. WP-CLI command examples

== Changelog ==

= 0.2.0 =
Not released yet.

* Add: Real-time URL preview in settings page
* Add: Environment variable support (ALGOLIA_HEADLESS_DOMAIN constant)
* Add: Debug logging system (stored securely in database)
* Add: Admin notices for configuration issues
* Add: Site Health integration
* Add: WP-CLI commands (status, set-domain, test, logs, clear-logs)
* Fix: Security improvements (proper escaping, ReDoS prevention)
* Fix: Better error handling and validation
* Improve: Code documentation and WordPress coding standards
* Fix: A trailing slash in the headless domain no longer produces "//" in the indexed URLs
* Fix: A port in the headless domain (e.g. http://localhost:3000) is kept
* Fix: Only the host part of a URL is replaced, not the same text in its path or query string
* Fix: A domain changed during the same request (e.g. WP-CLI before re-indexing) is used right away
* Fix: `wp algolia-headless logs` now honours --lines and --level
* Fix: `wp algolia-headless set-domain` and `clear-logs` now exist under the documented names (they were registered as `set_domain` / `clear_logs`)
* Change: The text domain is now `search-with-algolia-headless-extention` (the WordPress.org slug) so translations from translate.wordpress.org can load
* Change: Requires PHP 7.4 (the minimum of current WordPress). Tested up to WordPress 7.2
* Dev: PHPUnit tests on wp-env, run on CircleCI

= 0.1.0 =
* Initial release

== Upgrade Notice ==

= 0.2.0 =
Major update with new features: URL preview, WP-CLI support, debug logging, and security improvements.

= 0.1.0 =
Initial release