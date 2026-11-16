<?php
/**
 * PHPUnit bootstrap for the wp-env tests-cli container.
 *
 * wp-env mounts the WordPress core test library at /wordpress-phpunit and sets
 * WP_TESTS_DIR / WP_PHPUNIT__TESTS_CONFIG for it. The test library installs a
 * fresh database for every run, so plugins activated in wp-env are not loaded
 * here; the plugin is loaded by hand on muplugins_loaded instead.
 *
 * In this run WP Search with Algolia is NOT loaded and ALGOLIA_HEADLESS_DOMAIN is
 * not defined (see bootstrap-with-algolia.php for the other case).
 *
 * @package Algolia_Headless
 */

$algolia_headless_tests_dir = getenv( 'WP_TESTS_DIR' );
if ( ! $algolia_headless_tests_dir ) {
	$algolia_headless_tests_dir = '/wordpress-phpunit';
}

if ( ! file_exists( $algolia_headless_tests_dir . '/includes/functions.php' ) ) {
	fwrite( STDERR, "WordPress test library not found in {$algolia_headless_tests_dir}. Run the tests inside wp-env (npm run test).\n" );
	exit( 1 );
}

// The WordPress test library requires the PHPUnit Polyfills (1.1 or later).
define( 'WP_TESTS_PHPUNIT_POLYFILLS_PATH', dirname( __DIR__ ) . '/vendor/yoast/phpunit-polyfills' );

require_once $algolia_headless_tests_dir . '/includes/functions.php';

if ( ! function_exists( 'algolia_headless_tests_before_plugin_load' ) ) {
	/**
	 * Hook for bootstrap-with-algolia.php to prepare the environment before the plugin loads.
	 */
	function algolia_headless_tests_before_plugin_load() {
	}
}

tests_add_filter(
	'muplugins_loaded',
	static function () {
		algolia_headless_tests_before_plugin_load();
		require dirname( __DIR__ ) . '/algolia-headless.php';
	}
);

require $algolia_headless_tests_dir . '/includes/bootstrap.php';

require_once __DIR__ . '/support/class-fake-wp-cli.php';
