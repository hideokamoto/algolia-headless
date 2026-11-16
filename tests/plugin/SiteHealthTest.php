<?php
/**
 * Tools > Site Health, with WP Search with Algolia absent.
 *
 * @package Algolia_Headless
 */

class SiteHealthTest extends WP_UnitTestCase {

	public function test_direct_test_is_registered() {
		$tests = apply_filters( 'site_status_tests', array( 'direct' => array(), 'async' => array() ) );

		$this->assertArrayHasKey( 'algolia_headless_plugin', $tests['direct'] );
		$this->assertIsCallable( $tests['direct']['algolia_headless_plugin']['test'] );
	}

	public function test_result_is_critical_when_wp_search_with_algolia_is_missing() {
		update_option( 'algolia_headless_domain', 'https://headless.example.com' );

		$result = ( new Algolia_Headless_Site_Health() )->test_configuration();

		$this->assertSame( 'critical', $result['status'] );
		$this->assertSame( 'algolia_headless_plugin', $result['test'] );
		$this->assertStringContainsString( 'not active', $result['label'] );
	}
}
