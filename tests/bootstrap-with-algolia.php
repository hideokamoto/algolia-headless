<?php
/**
 * PHPUnit bootstrap for the "with-algolia" suite.
 *
 * Simulates a production-like site: WP Search with Algolia is active (a stand-in
 * Algolia_Plugin class is defined before this plugin loads) and the headless
 * domain is set with the ALGOLIA_HEADLESS_DOMAIN constant in wp-config.php.
 * Both are process-wide and cannot be undone, so this is a separate PHPUnit run.
 *
 * @package Algolia_Headless
 */

define( 'ALGOLIA_HEADLESS_TESTS_CONSTANT_DOMAIN', 'https://from-constant.example.com' );

/**
 * Prepare the environment before the plugin loads (called from bootstrap.php).
 */
function algolia_headless_tests_before_plugin_load() {
	if ( ! class_exists( 'Algolia_Plugin' ) ) {
		require_once __DIR__ . '/support/class-algolia-plugin-stub.php';
	}
	define( 'ALGOLIA_HEADLESS_DOMAIN', ALGOLIA_HEADLESS_TESTS_CONSTANT_DOMAIN );
}

require __DIR__ . '/bootstrap.php';
