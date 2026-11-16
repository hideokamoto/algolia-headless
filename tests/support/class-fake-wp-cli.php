<?php
/**
 * Minimal stand-in for the WP_CLI class, so that Algolia_Headless_CLI can be
 * exercised inside PHPUnit (WP-CLI itself is not loaded there).
 *
 * Every call is recorded in Algolia_Headless_Fake_WP_CLI::$calls.
 * WP_CLI::error() halts the command like the real one does, by throwing.
 *
 * @package Algolia_Headless
 */

/**
 * Thrown by the fake WP_CLI::error().
 */
class Algolia_Headless_Fake_WP_CLI_Error extends Exception {
}

/**
 * Recorder shared by the fake WP_CLI class.
 */
class Algolia_Headless_Fake_WP_CLI {

	/**
	 * Recorded calls: list of array( method, message ).
	 *
	 * @var array
	 */
	public static $calls = array();

	/**
	 * Reset recorded calls.
	 */
	public static function reset() {
		self::$calls = array();
	}

	/**
	 * Messages recorded for one method.
	 *
	 * @param string $method WP_CLI method name.
	 * @return string[]
	 */
	public static function messages( $method ) {
		$messages = array();
		foreach ( self::$calls as $call ) {
			if ( $method === $call[0] ) {
				$messages[] = $call[1];
			}
		}
		return $messages;
	}
}

if ( ! class_exists( 'WP_CLI' ) ) {
	// phpcs:ignore Generic.Files.OneObjectStructurePerFile.MultipleFound
	class WP_CLI {

		public static function line( $message = '' ) {
			Algolia_Headless_Fake_WP_CLI::$calls[] = array( 'line', $message );
		}

		public static function colorize( $message ) {
			return preg_replace( '/%[a-zA-Z]/', '', $message );
		}

		public static function success( $message ) {
			Algolia_Headless_Fake_WP_CLI::$calls[] = array( 'success', $message );
		}

		public static function warning( $message ) {
			Algolia_Headless_Fake_WP_CLI::$calls[] = array( 'warning', $message );
		}

		public static function confirm( $message ) {
			Algolia_Headless_Fake_WP_CLI::$calls[] = array( 'confirm', $message );
		}

		public static function error( $message ) {
			Algolia_Headless_Fake_WP_CLI::$calls[] = array( 'error', $message );
			throw new Algolia_Headless_Fake_WP_CLI_Error( $message );
		}
	}
}

require_once dirname( __DIR__, 2 ) . '/includes/class-wp-cli.php';
