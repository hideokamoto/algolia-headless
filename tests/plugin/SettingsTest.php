<?php
/**
 * Settings > Reading: the "Public site domain" option.
 *
 * @package Algolia_Headless
 */

class SettingsTest extends WP_UnitTestCase {

	public function tear_down() {
		delete_option( 'algolia_headless_domain' );
		parent::tear_down();
	}

	public function test_option_is_registered_in_the_reading_group() {
		$settings = get_registered_settings();

		$this->assertArrayHasKey( 'algolia_headless_domain', $settings );
		$this->assertSame( 'reading', $settings['algolia_headless_domain']['group'] );
		$this->assertSame( 'string', $settings['algolia_headless_domain']['type'] );
		$this->assertSame( '', $settings['algolia_headless_domain']['default'] );
		$this->assertTrue( (bool) $settings['algolia_headless_domain']['show_in_rest'] );
	}

	public function test_default_value_is_an_empty_string() {
		$this->assertSame( '', get_option( 'algolia_headless_domain' ) );
	}

	public function test_saved_value_is_sanitized_as_a_url() {
		update_option( 'algolia_headless_domain', 'https://headless.example.com/' );
		$this->assertSame( 'https://headless.example.com/', get_option( 'algolia_headless_domain' ) );

		update_option( 'algolia_headless_domain', 'javascript:alert(1)' );
		$this->assertSame( '', get_option( 'algolia_headless_domain' ) );
	}

	public function test_option_is_exposed_in_the_rest_settings_endpoint_for_admins() {
		update_option( 'algolia_headless_domain', 'https://headless.example.com' );
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'administrator' ) ) );

		$response = rest_do_request( new WP_REST_Request( 'GET', '/wp/v2/settings' ) );

		$this->assertSame( 200, $response->get_status() );
		$this->assertSame( 'https://headless.example.com', $response->get_data()['algolia_headless_domain'] );
	}

	public function test_settings_section_and_field_are_added_to_the_reading_page() {
		global $wp_settings_sections, $wp_settings_fields;
		require_once ABSPATH . 'wp-admin/includes/template.php';

		( new Algolia_Headless_Settings() )->init_options();

		$this->assertArrayHasKey( 'algolia_headless_settings', $wp_settings_sections['reading'] );
		$this->assertArrayHasKey( 'algolia_headless_domain', $wp_settings_fields['reading']['algolia_headless_settings'] );
	}

	public function test_field_renders_the_saved_value_escaped() {
		add_filter(
			'pre_option_algolia_headless_domain',
			static function () {
				return 'https://example.com/"><script>alert(1)</script>';
			}
		);
		$settings = new Algolia_Headless_Settings();

		ob_start();
		$settings->algolia_public_site_domain();
		$html = ob_get_clean();

		$this->assertStringContainsString( 'id="algolia_headless_domain"', $html );
		$this->assertStringNotContainsString( '<script>', $html );
	}

	public function test_preview_script_is_only_added_on_the_reading_settings_page() {
		$settings = new Algolia_Headless_Settings();
		wp_scripts()->registered['jquery']->extra = array();

		$settings->enqueue_scripts( 'index.php' );
		$this->assertEmpty( wp_scripts()->get_data( 'jquery', 'after' ) );

		$settings->enqueue_scripts( 'options-reading.php' );
		$this->assertStringContainsString( 'algolia-headless-preview', implode( "\n", (array) wp_scripts()->get_data( 'jquery', 'after' ) ) );
	}
}
