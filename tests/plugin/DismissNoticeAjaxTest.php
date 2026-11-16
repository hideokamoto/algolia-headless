<?php
/**
 * The admin-ajax action that remembers a dismissed notice per user.
 *
 * @group ajax
 * @package Algolia_Headless
 */

class DismissNoticeAjaxTest extends WP_Ajax_UnitTestCase {

	private function dismiss( array $post ) {
		$_POST = $post;
		try {
			$this->_handleAjax( 'algolia_headless_dismiss_notice' );
		} catch ( WPAjaxDieContinueException $e ) {
			unset( $e );
		}
		return json_decode( $this->_last_response, true );
	}

	public function test_valid_request_stores_the_dismissal_for_the_current_user() {
		$this->_setRole( 'administrator' );

		$response = $this->dismiss(
			array(
				'nonce'     => wp_create_nonce( 'algolia_headless_dismiss_notice' ),
				'notice_id' => 'algolia-http-warning',
			)
		);

		$this->assertTrue( $response['success'] );
		$this->assertEquals( 1, get_user_meta( get_current_user_id(), 'algolia_headless_dismissed_algolia-http-warning', true ) );
	}

	public function test_request_with_a_bad_nonce_is_rejected() {
		$this->_setRole( 'administrator' );

		$response = $this->dismiss(
			array(
				'nonce'     => 'bad-nonce',
				'notice_id' => 'algolia-http-warning',
			)
		);

		$this->assertFalse( $response['success'] );
		$this->assertSame( '', get_user_meta( get_current_user_id(), 'algolia_headless_dismissed_algolia-http-warning', true ) );
	}

	public function test_request_without_a_notice_id_is_rejected() {
		$this->_setRole( 'administrator' );

		$response = $this->dismiss( array( 'nonce' => wp_create_nonce( 'algolia_headless_dismiss_notice' ) ) );

		$this->assertFalse( $response['success'] );
	}
}
