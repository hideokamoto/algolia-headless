<?php
/**
 * Admin notices, with WP Search with Algolia absent (this suite never loads it).
 *
 * @package Algolia_Headless
 */

class AdminNoticesTest extends WP_UnitTestCase {

	private $user_id;

	public function set_up() {
		parent::set_up();
		$this->user_id = self::factory()->user->create( array( 'role' => 'administrator' ) );
		wp_set_current_user( $this->user_id );
		set_current_screen( 'dashboard' );
	}

	public function tear_down() {
		delete_option( 'algolia_headless_domain' );
		set_current_screen( 'front' );
		parent::tear_down();
	}

	private function render_notices() {
		$notices = new Algolia_Headless_Admin_Notices();
		ob_start();
		$notices->display_notices();
		return ob_get_clean();
	}

	public function test_wp_search_with_algolia_is_reported_as_inactive() {
		$this->assertFalse( Algolia_Headless_Helper::is_algolia_plugin_active() );
	}

	public function test_warns_that_wp_search_with_algolia_is_required() {
		$html = $this->render_notices();

		$this->assertStringContainsString( 'notice-warning', $html );
		$this->assertStringContainsString( 'data-notice-id="algolia-plugin-missing"', $html );
		$this->assertStringContainsString( '<strong>WP Search with Algolia</strong>', $html );
	}

	public function test_asks_to_configure_the_domain_when_it_is_empty() {
		$html = $this->render_notices();

		$this->assertStringContainsString( 'data-notice-id="algolia-domain-not-configured"', $html );
		$this->assertStringContainsString( 'options-reading.php#algolia_headless_domain', $html );
	}

	public function test_no_domain_notice_once_a_https_domain_is_saved() {
		update_option( 'algolia_headless_domain', 'https://headless.example.com' );

		$html = $this->render_notices();

		$this->assertStringNotContainsString( 'algolia-domain-not-configured', $html );
		$this->assertStringNotContainsString( 'algolia-http-warning', $html );
		$this->assertStringNotContainsString( 'algolia-invalid-domain', $html );
	}

	public function test_http_domain_gets_a_dismissible_warning() {
		update_option( 'algolia_headless_domain', 'http://headless.example.com' );

		$html = $this->render_notices();

		$this->assertStringContainsString( 'is-dismissible', $html );
		$this->assertStringContainsString( 'data-notice-id="algolia-http-warning"', $html );
	}

	public function test_dismissed_http_warning_is_not_shown_again() {
		update_option( 'algolia_headless_domain', 'http://headless.example.com' );
		update_user_meta( $this->user_id, 'algolia_headless_dismissed_algolia-http-warning', true );

		$this->assertStringNotContainsString( 'algolia-http-warning', $this->render_notices() );
	}

	public function test_invalid_domain_gets_an_escaped_error() {
		add_filter(
			'pre_option_algolia_headless_domain',
			static function () {
				return '<b>not a url</b>';
			}
		);

		$html = $this->render_notices();

		$this->assertStringContainsString( 'notice-error', $html );
		$this->assertStringContainsString( '&lt;b&gt;not a url&lt;/b&gt;', $html );
	}

	public function test_nothing_is_printed_outside_the_admin() {
		set_current_screen( 'front' );

		$this->assertSame( '', $this->render_notices() );
	}
}
