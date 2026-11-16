<?php
/**
 * A production-like site: WP Search with Algolia is active and the headless domain
 * comes from the ALGOLIA_HEADLESS_DOMAIN constant (see tests/bootstrap-with-algolia.php).
 *
 * @package Algolia_Headless
 */

class ConfiguredSiteTest extends WP_UnitTestCase {

	public function set_up() {
		parent::set_up();
		Algolia_Headless_Fake_WP_CLI::reset();
	}

	public function tear_down() {
		delete_option( 'algolia_headless_domain' );
		set_current_screen( 'front' );
		parent::tear_down();
	}

	public function test_wp_search_with_algolia_is_detected() {
		$this->assertTrue( Algolia_Headless_Helper::is_algolia_plugin_active() );
	}

	public function test_constant_wins_over_the_saved_option() {
		update_option( 'algolia_headless_domain', 'https://from-option.example.com' );

		$this->assertSame( ALGOLIA_HEADLESS_TESTS_CONSTANT_DOMAIN, Algolia_Headless_Helper::get_headless_domain() );

		$replacer = new Algolia_Headless_Replacer();
		$this->assertSame( 'http://from-constant.example.com/hello-world/', $replacer->replace_algolia_permalink_to_public_site_domain( 'http://example.org/hello-world/' ) );
	}

	public function test_no_admin_notice_is_shown() {
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'administrator' ) ) );
		set_current_screen( 'dashboard' );

		ob_start();
		( new Algolia_Headless_Admin_Notices() )->display_notices();
		$this->assertSame( '', ob_get_clean() );
	}

	public function test_site_health_is_good_and_mentions_the_constant() {
		$result = ( new Algolia_Headless_Site_Health() )->test_configuration();

		$this->assertSame( 'good', $result['status'] );
		$this->assertStringContainsString( 'ALGOLIA_HEADLESS_DOMAIN', $result['description'] );
		$this->assertStringContainsString( ALGOLIA_HEADLESS_TESTS_CONSTANT_DOMAIN, $result['description'] );
	}

	public function test_cli_status_reports_the_constant() {
		( new Algolia_Headless_CLI() )->status();

		$this->assertContains( 'WP Search with Algolia plugin: Active', Algolia_Headless_Fake_WP_CLI::messages( 'success' ) );
		$this->assertContains( 'Headless domain: ' . ALGOLIA_HEADLESS_TESTS_CONSTANT_DOMAIN . ' (via constant)', Algolia_Headless_Fake_WP_CLI::messages( 'line' ) );
	}

	public function test_cli_set_domain_asks_for_confirmation_because_the_constant_overrides_it() {
		( new Algolia_Headless_CLI() )->set_domain( array( 'https://from-option.example.com' ) );

		$this->assertNotEmpty( Algolia_Headless_Fake_WP_CLI::messages( 'confirm' ) );
		$this->assertSame( 'https://from-option.example.com', get_option( 'algolia_headless_domain' ) );
	}
}
