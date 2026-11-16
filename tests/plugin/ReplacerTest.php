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

	public function test_records_passed_through_the_algolia_filters_get_the_headless_url() {
		update_option( 'algolia_headless_domain', 'https://headless.example.com' );

		$post_attributes = apply_filters( 'algolia_post_shared_attributes', array( 'permalink' => 'http://example.org/hello-world/' ), null );
		$searchable      = apply_filters( 'algolia_searchable_post_shared_attributes', array( 'permalink' => 'http://example.org/hello-world/' ), null );
		$term            = apply_filters( 'algolia_term_record', array( 'permalink' => 'http://example.org/category/news/' ), null );
		$user            = apply_filters( 'algolia_user_record', array( 'posts_url' => 'http://example.org/author/admin/' ), null );

		$this->assertSame( 'http://headless.example.com/hello-world/', $post_attributes['permalink'] );
		$this->assertSame( 'http://headless.example.com/hello-world/', $searchable['permalink'] );
		$this->assertSame( 'http://headless.example.com/category/news/', $term['permalink'] );
		$this->assertSame( 'http://headless.example.com/author/admin/', $user['posts_url'] );
	}

	public function test_domain_change_is_picked_up_by_the_running_filters() {
		update_option( 'algolia_headless_domain', 'https://first.example.com' );
		$first = apply_filters( 'algolia_post_shared_attributes', array( 'permalink' => 'http://example.org/hello-world/' ), null );

		// e.g. `wp algolia-headless set-domain` followed by a re-index in the same request.
		update_option( 'algolia_headless_domain', 'https://second.example.com' );
		$second = apply_filters( 'algolia_post_shared_attributes', array( 'permalink' => 'http://example.org/hello-world/' ), null );

		$this->assertSame( 'http://first.example.com/hello-world/', $first['permalink'] );
		$this->assertSame( 'http://second.example.com/hello-world/', $second['permalink'] );
	}

	public function test_trailing_slash_of_the_headless_domain_does_not_double_the_slash() {
		// The settings field and esc_url_raw() keep a trailing slash as typed.
		update_option( 'algolia_headless_domain', 'https://headless.example.com/' );

		$this->assertSame( 'http://headless.example.com/hello-world/', $this->replace( 'http://example.org/hello-world/' ) );
	}

	public function test_port_of_the_headless_domain_is_kept() {
		update_option( 'algolia_headless_domain', 'http://localhost:3000' );

		$this->assertSame( 'http://localhost:3000/hello-world/', $this->replace( 'http://example.org/hello-world/' ) );
	}

	public function test_only_the_host_part_is_replaced() {
		update_option( 'algolia_headless_domain', 'https://headless.example.com' );

		$this->assertSame(
			'http://headless.example.com/?s=example.org&ref=http%3A%2F%2Fexample.org',
			$this->replace( 'http://example.org/?s=example.org&ref=http%3A%2F%2Fexample.org' )
		);
	}

	public function test_helper_test_url_replacement_uses_the_saved_option() {
		update_option( 'algolia_headless_domain', 'https://headless.example.com' );

		$this->assertSame( 'http://headless.example.com/sample-post/', Algolia_Headless_Helper::test_url_replacement( home_url( '/sample-post/' ) ) );
	}
}
