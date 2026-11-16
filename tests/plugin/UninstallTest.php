<?php
/**
 * uninstall.php removes the plugin's option.
 *
 * @package Algolia_Headless
 */

class UninstallTest extends WP_UnitTestCase {

	public function test_uninstall_deletes_the_domain_option() {
		update_option( 'algolia_headless_domain', 'https://headless.example.com' );

		if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
			define( 'WP_UNINSTALL_PLUGIN', 'algolia-headless/algolia-headless.php' );
		}
		require dirname( __DIR__, 2 ) . '/uninstall.php';

		$this->assertFalse( get_option( 'algolia_headless_domain', false ) );
	}
}
