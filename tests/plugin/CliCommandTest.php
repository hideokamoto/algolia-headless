<?php
/**
 * `wp algolia-headless ...` commands, run against a recording stand-in for WP_CLI
 * (tests/support/class-fake-wp-cli.php). The real WP-CLI registration is checked
 * by the CircleCI job with `wp-env run cli wp algolia-headless ...`.
 *
 * @package Algolia_Headless
 */

class CliCommandTest extends WP_UnitTestCase {

	private $cli;

	public function set_up() {
		parent::set_up();
		Algolia_Headless_Fake_WP_CLI::reset();
		$this->cli = new Algolia_Headless_CLI();
	}

	public function tear_down() {
		delete_option( 'algolia_headless_domain' );
		delete_transient( 'algolia_headless_debug_logs' );
		parent::tear_down();
	}

	private function run_command( $method, array $args = array(), array $assoc_args = array() ) {
		try {
			$this->cli->$method( $args, $assoc_args );
		} catch ( Algolia_Headless_Fake_WP_CLI_Error $e ) {
			return $e->getMessage();
		}
		return null;
	}

	public function test_set_domain_saves_the_option_and_shows_a_sample() {
		$this->assertNull( $this->run_command( 'set_domain', array( 'https://headless.example.com' ) ) );

		$this->assertSame( 'https://headless.example.com', get_option( 'algolia_headless_domain' ) );
		$this->assertContains( 'Headless domain set to: https://headless.example.com', Algolia_Headless_Fake_WP_CLI::messages( 'success' ) );
		$this->assertContains( 'After:  http://headless.example.com/sample-post/', Algolia_Headless_Fake_WP_CLI::messages( 'line' ) );
	}

	public function test_set_domain_warns_about_http() {
		$this->run_command( 'set_domain', array( 'http://headless.example.com' ) );

		$this->assertSame( 'http://headless.example.com', get_option( 'algolia_headless_domain' ) );
		$this->assertNotEmpty( Algolia_Headless_Fake_WP_CLI::messages( 'warning' ) );
	}

	public function test_set_domain_rejects_an_invalid_url_without_saving() {
		$this->assertSame( 'Invalid URL format: not-a-url', $this->run_command( 'set_domain', array( 'not-a-url' ) ) );
		$this->assertSame( '', get_option( 'algolia_headless_domain' ) );
	}

	public function test_set_domain_requires_an_argument() {
		$this->assertSame( 'Please provide a domain URL', $this->run_command( 'set_domain' ) );
	}

	public function test_test_command_requires_a_configured_domain() {
		$this->assertStringContainsString( 'not configured', $this->run_command( 'test', array( 'http://example.org/hello/' ) ) );
	}

	public function test_test_command_prints_the_replaced_url() {
		update_option( 'algolia_headless_domain', 'https://headless.example.com' );

		$this->assertNull( $this->run_command( 'test', array( 'http://example.org/hello/' ) ) );

		$this->assertContains( 'Replaced URL:  http://headless.example.com/hello/', Algolia_Headless_Fake_WP_CLI::messages( 'line' ) );
		$this->assertContains( 'URL replacement successful', Algolia_Headless_Fake_WP_CLI::messages( 'success' ) );
	}

	public function test_status_reports_missing_algolia_plugin_and_domain() {
		$this->assertNull( $this->run_command( 'status' ) );

		$warnings = Algolia_Headless_Fake_WP_CLI::messages( 'warning' );
		$this->assertContains( 'WP Search with Algolia plugin: Not Active', $warnings );
		$this->assertContains( 'Headless domain: Not configured', $warnings );
	}

	public function test_clear_logs_deletes_the_stored_logs() {
		set_transient( 'algolia_headless_debug_logs', array( array( 'level' => 'info', 'message' => 'x', 'timestamp' => 'now' ) ) );

		$this->run_command( 'clear_logs' );

		$this->assertFalse( get_transient( 'algolia_headless_debug_logs' ) );
	}
}
