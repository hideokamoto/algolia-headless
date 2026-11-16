<?php
/**
 * URL replacement: the core job of the plugin.
 *
 * WP Search with Algolia passes each record through the filters this plugin hooks,
 * and the plugin swaps the WordPress host for the headless site.
 *
 * @package Algolia_Headless
 */

class ReplacerTest extends WP_UnitTestCase {

	public function tear_down() {
		delete_option( 'algolia_headless_domain' );
		parent::tear_down();
	}

	private function replace( $url ) {
		$replacer = new Algolia_Headless_Replacer();
		return $replacer->replace_algolia_permalink_to_public_site_domain( $url );
	}

	public function test_algolia_filters_are_hooked() {
		$this->assertTrue( (bool) has_filter( 'algolia_term_record' ) );
		$this->assertTrue( (bool) has_filter( 'algolia_post_shared_attributes' ) );
		$this->assertTrue( (bool) has_filter( 'algolia_searchable_post_shared_attributes' ) );
		$this->assertTrue( (bool) has_filter( 'algolia_user_record' ) );
	}

	public function test_url_is_unchanged_when_no_domain_is_configured() {
		$this->assertSame( 'http://example.org/hello-world/', $this->replace( 'http://example.org/hello-world/' ) );
	}

	public function test_host_is_replaced_with_the_headless_domain() {
		update_option( 'algolia_headless_domain', 'https://headless.example.com' );

		// The scheme of the original URL is kept; only the host part changes (behaviour since 0.1.0).
		$this->assertSame( 'http://headless.example.com/hello-world/', $this->replace( 'http://example.org/hello-world/' ) );
	}

	public function test_port_of_the_original_url_is_replaced_too() {
		update_option( 'algolia_headless_domain', 'https://headless.example.com' );

		$this->assertSame( 'http://headless.example.com/?p=1', $this->replace( 'http://localhost:8889/?p=1' ) );
	}

	public function test_path_of_the_headless_domain_is_kept() {
		update_option( 'algolia_headless_domain', 'https://example.com/blog' );

		$this->assertSame( 'http://example.com/blog/hello-world/', $this->replace( 'http://example.org/hello-world/' ) );
	}

	public function test_unparsable_original_url_is_returned_as_is() {
		update_option( 'algolia_headless_domain', 'https://headless.example.com' );

		$this->assertSame( '/relative/path/', $this->replace( '/relative/path/' ) );
		$this->assertSame( '', $this->replace( '' ) );
	}

	public function test_invalid_headless_domain_leaves_url_unchanged() {
		add_filter(
			'pre_option_algolia_headless_domain',
			static function () {
				return 'not a url';
			}
		);

		$this->assertSame( 'http://example.org/hello-world/', $this->replace( 'http://example.org/hello-world/' ) );
	}

	public function test_records_without_the_url_field_are_returned_untouched() {
		update_option( 'algolia_headless_domain', 'https://headless.example.com' );
		$replacer = new Algolia_Headless_Replacer();

		$this->assertSame( array( 'post_title' => 'Hello' ), $replacer->replace_algolia_permalink( array( 'post_title' => 'Hello' ) ) );
		$this->assertSame( array( 'display_name' => 'admin' ), $replacer->replace_algolia_posts_url( array( 'display_name' => 'admin' ) ) );
	}

	public function test_helper_test_url_replacement_uses_the_saved_option() {
		update_option( 'algolia_headless_domain', 'https://headless.example.com' );

		$this->assertSame( 'http://headless.example.com/sample-post/', Algolia_Headless_Helper::test_url_replacement( home_url( '/sample-post/' ) ) );
	}
}
