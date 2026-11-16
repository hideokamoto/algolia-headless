<?php
/**
 * Plugin Name:     Search with Algolia Headless extension
 * Plugin URI:      https://wp-kyoto.net
 * Description:     Simply extension for WP Search with Algolia. Replace the indices domain from the WordPress to custom domain.
 * Author:          Hidetaka Okamoto
 * Author URI:      https://wp-kyoto.net/en
 * Text Domain:     search-with-algolia-headless-extention
 * Domain Path:     /languages
 * Version:         0.2.0
 * Requires at least: 5.5
 * Requires PHP:    7.4
 *
 * @package         Algolia_Headless
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Algolia Headless Helper Class
 *
 * Provides utility functions used across the plugin.
 */
class Algolia_Headless_Helper {

	/**
	 * Get headless domain from environment variable or option
	 *
	 * Priority: ALGOLIA_HEADLESS_DOMAIN constant > option
	 *
	 * @return string|false
	 */
	public static function get_headless_domain() {
		// Check for environment variable/constant first.
		if ( defined( 'ALGOLIA_HEADLESS_DOMAIN' ) && ALGOLIA_HEADLESS_DOMAIN ) {
			return ALGOLIA_HEADLESS_DOMAIN;
		}

		return get_option( 'algolia_headless_domain', false );
	}

	/**
	 * Check if WP Search with Algolia plugin is active
	 *
	 * @return bool
	 */
	public static function is_algolia_plugin_active() {
		// Check if Algolia class exists.
		return class_exists( 'Algolia_Plugin' ) || class_exists( 'Algolia_API' );
	}

	/**
	 * Log debug message
	 *
	 * Logs to PHP error log and stores recent logs in database (if WP_DEBUG enabled).
	 *
	 * @param string $message Log message.
	 * @param string $level Log level (info, warning, error).
	 */
	public static function log( $message, $level = 'info' ) {
		if ( ! defined( 'WP_DEBUG' ) || ! WP_DEBUG ) {
			return;
		}

		// Log to PHP error log.
		error_log( sprintf( '[Algolia Headless][%s] %s', strtoupper( $level ), $message ) );

		// Store in database (transient, expires in 7 days).
		$logs = get_transient( 'algolia_headless_debug_logs' );
		if ( ! is_array( $logs ) ) {
			$logs = array();
		}

		$logs[] = array(
			'timestamp' => current_time( 'mysql' ),
			'level'     => $level,
			'message'   => $message,
		);

		// Keep only last 100 logs.
		$logs = array_slice( $logs, -100 );

		set_transient( 'algolia_headless_debug_logs', $logs, 7 * DAY_IN_SECONDS );
	}

	/**
	 * Test URL replacement
	 *
	 * @param string $url URL to test.
	 * @return string Replaced URL.
	 */
	public static function test_url_replacement( $url ) {
		$replacer = new Algolia_Headless_Replacer();
		return $replacer->replace_algolia_permalink_to_public_site_domain( $url );
	}
}

/**
 * Algolia Headless Replacer Class
 *
 * Replaces WordPress permalinks with headless site domain in Algolia indices.
 */
class Algolia_Headless_Replacer {

	/**
	 * Constructor
	 */
	public function __construct() {
		add_filter( 'algolia_term_record', array( $this, 'replace_algolia_permalink' ), 10, 1 );
		add_filter( 'algolia_post_shared_attributes', array( $this, 'replace_algolia_permalink' ), 10, 1 );
		add_filter( 'algolia_searchable_post_shared_attributes', array( $this, 'replace_algolia_permalink' ), 10, 1 );
		add_filter( 'algolia_user_record', array( $this, 'replace_algolia_posts_url' ), 10, 1 );
	}

	/**
	 * Get headless domain from options
	 *
	 * Supports environment variable override via ALGOLIA_HEADLESS_DOMAIN constant.
	 * Not cached per instance: get_option() is already served from the object cache,
	 * and a domain changed during the request (e.g. by WP-CLI before a re-index)
	 * must be used by the filters right away.
	 *
	 * @return string|false
	 */
	private function get_headless_domain() {
		return Algolia_Headless_Helper::get_headless_domain();
	}

	/**
	 * Replace WordPress domain with headless site domain
	 *
	 * @param string $url URL to replace.
	 * @return string Modified URL.
	 */
	public function replace_algolia_permalink_to_public_site_domain( $url ) {
		$replaced_url = $this->get_headless_domain();
		if ( ! $replaced_url || ! is_string( $replaced_url ) ) {
			Algolia_Headless_Helper::log( 'Headless domain not configured', 'warning' );
			return $url;
		}

		// Parse headless domain.
		$target = wp_parse_url( $replaced_url );
		if ( ! $target || ! isset( $target['host'] ) ) {
			Algolia_Headless_Helper::log( "Failed to parse headless domain: {$replaced_url}", 'error' );
			return $url;
		}

		$replaced_domain = $target['host'];
		if ( isset( $target['port'] ) && $target['port'] ) {
			$replaced_domain .= ':' . $target['port'];
		}
		// A trailing slash ("https://example.com/") would otherwise produce "//" before the path.
		if ( isset( $target['path'] ) ) {
			$replaced_domain .= untrailingslashit( $target['path'] );
		}

		if ( empty( $replaced_domain ) ) {
			Algolia_Headless_Helper::log( 'Replaced domain is empty after parsing', 'error' );
			return $url;
		}

		// Parse original URL.
		$parsed_url = wp_parse_url( $url );
		if ( ! $parsed_url || ! isset( $parsed_url['host'] ) ) {
			Algolia_Headless_Helper::log( "Failed to parse original URL: {$url}", 'error' );
			return $url;
		}

		$replace_target = $parsed_url['host'];
		if ( isset( $parsed_url['port'] ) && $parsed_url['port'] ) {
			$replace_target .= ':' . $parsed_url['port'];
		}

		// Replace only "host[:port]" inside the authority ("//[userinfo@]host[:port]"), not other
		// occurrences of the host in the path or query string. Plain string functions avoid ReDoS.
		$authority_start = strpos( $url, '//' );
		if ( false === $authority_start ) {
			Algolia_Headless_Helper::log( "Host not found in URL: {$url}", 'error' );
			return $url;
		}
		$authority_start += 2;
		$authority_length = strcspn( $url, '/?#', $authority_start );
		$authority        = substr( $url, $authority_start, $authority_length );
		$at               = strrpos( $authority, '@' );
		$host_offset      = ( false === $at ) ? 0 : $at + 1;
		if ( 0 !== strcasecmp( substr( $authority, $host_offset ), $replace_target ) ) {
			Algolia_Headless_Helper::log( "Host not found in URL: {$url}", 'error' );
			return $url;
		}
		$result = substr_replace( $url, $replaced_domain, $authority_start + $host_offset, $authority_length - $host_offset );

		Algolia_Headless_Helper::log( "URL replaced: {$url} -> {$result}", 'info' );

		return $result;
	}

	/**
	 * Replace permalink in shared attributes
	 *
	 * @param array $shared_attributes Shared attributes.
	 * @return array Modified attributes.
	 */
	public function replace_algolia_permalink( $shared_attributes ) {
		if ( isset( $shared_attributes['permalink'] ) ) {
			$shared_attributes['permalink'] = $this->replace_algolia_permalink_to_public_site_domain( $shared_attributes['permalink'] );
		}
		return $shared_attributes;
	}

	/**
	 * Replace posts_url in user record
	 *
	 * @param array $user User record.
	 * @return array Modified user record.
	 */
	public function replace_algolia_posts_url( $user ) {
		if ( isset( $user['posts_url'] ) ) {
			$user['posts_url'] = $this->replace_algolia_permalink_to_public_site_domain( $user['posts_url'] );
		}
		return $user;
	}
}


/**
 * Algolia Headless Settings Class
 *
 * Manages plugin settings in WordPress admin.
 */
class Algolia_Headless_Settings {

	/**
	 * Constructor
	 */
	public function __construct() {
		add_action( 'admin_init', array( $this, 'init_options' ) );
		add_action( 'init', array( $this, 'register_option' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_scripts' ) );
	}

	/**
	 * Enqueue admin scripts
	 *
	 * @param string $hook Current admin page hook.
	 */
	public function enqueue_scripts( $hook ) {
		// Only load on reading settings page.
		if ( 'options-reading.php' !== $hook ) {
			return;
		}

		wp_add_inline_script(
			'jquery',
			$this->get_preview_script(),
			'after'
		);

		wp_add_inline_style(
			'wp-admin',
			$this->get_preview_styles()
		);
	}

	/**
	 * Get preview JavaScript
	 *
	 * @return string JavaScript code.
	 */
	private function get_preview_script() {
		$wp_url = home_url();
		return "
		jQuery(document).ready(function($) {
			var domainInput = $('#algolia_headless_domain');
			var previewContainer = $('<div id=\"algolia-headless-preview\" style=\"margin-top:10px;\"></div>');

			domainInput.after(previewContainer);

			function updatePreview() {
				var headlessDomain = domainInput.val().trim();
				var wpUrl = '" . esc_js( $wp_url ) . "';

				if (!headlessDomain) {
					previewContainer.html('<em style=\"color:#666;\">" . esc_js( __( 'Enter a domain to see preview', 'search-with-algolia-headless-extention' ) ) . "</em>');
					return;
				}

				// Simple replacement logic (matches PHP logic)
				var wpParsed = new URL(wpUrl);
				var headlessParsed;
				try {
					headlessParsed = new URL(headlessDomain);
				} catch(e) {
					previewContainer.html('<span style=\"color:#d63638;\">" . esc_js( __( 'Invalid URL format', 'search-with-algolia-headless-extention' ) ) . "</span>');
					return;
				}

				var samplePath = '/sample-post/';
				var beforeUrl = wpUrl + samplePath;
				var afterUrl = headlessDomain.replace(/\/$/, '') + samplePath;

				previewContainer.html(
					'<div style=\"background:#f0f0f1;padding:12px;border-left:4px solid #2271b1;\">' +
					'<strong>" . esc_js( __( 'URL Preview:', 'search-with-algolia-headless-extention' ) ) . "</strong><br>' +
					'<span style=\"color:#666;\">" . esc_js( __( 'Before:', 'search-with-algolia-headless-extention' ) ) . "</span> <code>' + beforeUrl + '</code><br>' +
					'<span style=\"color:#666;\">" . esc_js( __( 'After:', 'search-with-algolia-headless-extention' ) ) . "</span> <code style=\"color:#2271b1;\">' + afterUrl + '</code>' +
					'</div>'
				);
			}

			domainInput.on('input', updatePreview);
			updatePreview();
		});
		";
	}

	/**
	 * Get preview CSS styles
	 *
	 * @return string CSS code.
	 */
	private function get_preview_styles() {
		return "
		#algolia-headless-preview {
			margin-top: 10px;
		}
		#algolia-headless-preview code {
			background: #fff;
			padding: 2px 6px;
			border-radius: 3px;
		}
		";
	}

	/**
	 * Register plugin option
	 */
	public function register_option() {
		register_setting(
			'reading',
			'algolia_headless_domain',
			array(
				'type'              => 'string',
				'sanitize_callback' => 'esc_url_raw',
				'show_in_rest'      => true,
				'default'           => '',
			)
		);
	}

	/**
	 * Initialize plugin options in admin
	 */
	public function init_options() {
		add_settings_section(
			'algolia_headless_settings',
			__( 'Algolia Headless extension', 'search-with-algolia-headless-extention' ),
			array( $this, 'algolia_setting_description' ),
			'reading'
		);
		add_settings_field(
			'algolia_headless_domain',
			__( 'Public site domain', 'search-with-algolia-headless-extention' ),
			array( $this, 'algolia_public_site_domain' ),
			'reading',
			'algolia_headless_settings'
		);
	}

	/**
	 * Display settings section description
	 */
	public function algolia_setting_description() {
		esc_html_e( 'You can replace the post domain from the WordPress to your public site.', 'search-with-algolia-headless-extention' );
	}

	/**
	 * Display public site domain input field
	 */
	public function algolia_public_site_domain() {
		$value = get_option( 'algolia_headless_domain', '' );
		?>
		<input
			id="algolia_headless_domain"
			name="algolia_headless_domain"
			class="regular-text"
			type="url"
			value="<?php echo esc_attr( $value ); ?>"
			placeholder="https://example.com"
		/>
		<p class="description">
			<?php esc_html_e( 'Enter the full URL of your headless site (e.g., https://example.com)', 'search-with-algolia-headless-extention' ); ?>
		</p>
		<?php
	}
}

/**
 * Algolia Headless Admin Notices Class
 *
 * Displays admin notices for configuration issues.
 */
class Algolia_Headless_Admin_Notices {

	/**
	 * Constructor
	 */
	public function __construct() {
		add_action( 'admin_notices', array( $this, 'display_notices' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_dismiss_script' ) );
		add_action( 'wp_ajax_algolia_headless_dismiss_notice', array( $this, 'ajax_dismiss_notice' ) );
	}

	/**
	 * Enqueue dismiss notice script
	 */
	public function enqueue_dismiss_script() {
		wp_add_inline_script(
			'jquery',
			$this->get_dismiss_script(),
			'after'
		);
	}

	/**
	 * Get dismiss notice JavaScript
	 *
	 * @return string JavaScript code.
	 */
	private function get_dismiss_script() {
		$ajax_url = admin_url( 'admin-ajax.php' );
		$nonce    = wp_create_nonce( 'algolia_headless_dismiss_notice' );

		return "
		jQuery(document).ready(function($) {
			$(document).on('click', '.notice.is-dismissible[data-notice-id]', function() {
				var noticeId = $(this).data('notice-id');
				if (!noticeId) return;

				$.post('" . esc_js( $ajax_url ) . "', {
					action: 'algolia_headless_dismiss_notice',
					notice_id: noticeId,
					nonce: '" . esc_js( $nonce ) . "'
				});
			});
		});
		";
	}

	/**
	 * AJAX handler for dismissing notices
	 */
	public function ajax_dismiss_notice() {
		// Verify nonce.
		if ( ! isset( $_POST['nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['nonce'] ) ), 'algolia_headless_dismiss_notice' ) ) {
			wp_send_json_error( 'Invalid nonce' );
		}

		// Get notice ID.
		if ( ! isset( $_POST['notice_id'] ) ) {
			wp_send_json_error( 'Missing notice ID' );
		}

		$notice_id = sanitize_key( $_POST['notice_id'] );

		// Save dismissal.
		update_user_meta(
			get_current_user_id(),
			"algolia_headless_dismissed_{$notice_id}",
			true
		);

		wp_send_json_success();
	}

	/**
	 * Display admin notices
	 */
	public function display_notices() {
		// Only show on admin pages.
		if ( ! is_admin() ) {
			return;
		}

		// Check if Algolia plugin is active.
		if ( ! Algolia_Headless_Helper::is_algolia_plugin_active() ) {
			$this->show_notice(
				__( 'Algolia Headless extension requires the <strong>WP Search with Algolia</strong> plugin to be installed and activated.', 'search-with-algolia-headless-extention' ),
				'warning',
				'algolia-plugin-missing'
			);
		}

		// Check if headless domain is configured.
		$headless_domain = Algolia_Headless_Helper::get_headless_domain();
		if ( empty( $headless_domain ) ) {
			// Check if we're using constant.
			$using_constant = defined( 'ALGOLIA_HEADLESS_DOMAIN' );

			if ( ! $using_constant ) {
				$settings_url = admin_url( 'options-reading.php#algolia_headless_domain' );
				$this->show_notice(
					sprintf(
						/* translators: %s: Settings page URL */
						__( 'Algolia Headless domain is not configured. Please <a href="%s">configure it in Settings &raquo; Reading</a>.', 'search-with-algolia-headless-extention' ),
						esc_url( $settings_url )
					),
					'info',
					'algolia-domain-not-configured'
				);
			}
		}

		// Validate domain format if configured.
		if ( ! empty( $headless_domain ) ) {
			$parsed = wp_parse_url( $headless_domain );
			if ( ! $parsed || ! isset( $parsed['host'] ) ) {
				$this->show_notice(
					sprintf(
						/* translators: %s: Invalid domain URL */
						__( 'Invalid headless domain format: <code>%s</code>. Please use a full URL (e.g., https://example.com).', 'search-with-algolia-headless-extention' ),
						esc_html( $headless_domain )
					),
					'error',
					'algolia-invalid-domain'
				);
			} elseif ( isset( $parsed['scheme'] ) && 'http' === $parsed['scheme'] ) {
				// Warn about HTTP (not HTTPS).
				$this->show_notice(
					__( 'Your headless domain uses HTTP instead of HTTPS. Consider using HTTPS for better security.', 'search-with-algolia-headless-extention' ),
					'warning',
					'algolia-http-warning',
					true
				);
			}
		}
	}

	/**
	 * Show admin notice
	 *
	 * @param string $message Notice message (HTML allowed).
	 * @param string $type Notice type (info, warning, error, success).
	 * @param string $id Unique notice ID for dismissible notices.
	 * @param bool   $dismissible Whether notice is dismissible.
	 */
	private function show_notice( $message, $type = 'info', $id = '', $dismissible = false ) {
		// Check if notice was dismissed.
		if ( $dismissible && $id && get_user_meta( get_current_user_id(), "algolia_headless_dismissed_{$id}", true ) ) {
			return;
		}

		$classes = array( 'notice', "notice-{$type}" );
		if ( $dismissible ) {
			$classes[] = 'is-dismissible';
		}

		printf(
			'<div class="%s" data-notice-id="%s"><p>%s</p></div>',
			esc_attr( implode( ' ', $classes ) ),
			esc_attr( $id ),
			wp_kses_post( $message )
		);
	}
}


/**
 * Algolia Headless Site Health Class
 *
 * Integrates with WordPress Site Health.
 */
class Algolia_Headless_Site_Health {

	/**
	 * Constructor
	 */
	public function __construct() {
		add_filter( 'site_status_tests', array( $this, 'add_tests' ) );
	}

	/**
	 * Add custom tests to Site Health
	 *
	 * @param array $tests Existing tests.
	 * @return array Modified tests.
	 */
	public function add_tests( $tests ) {
		$tests['direct']['algolia_headless_plugin'] = array(
			'label' => __( 'Algolia Headless Configuration', 'search-with-algolia-headless-extention' ),
			'test'  => array( $this, 'test_configuration' ),
		);

		return $tests;
	}

	/**
	 * Test Algolia Headless configuration
	 *
	 * @return array Test result.
	 */
	public function test_configuration() {
		$result = array(
			'label'       => __( 'Algolia Headless is configured correctly', 'search-with-algolia-headless-extention' ),
			'status'      => 'good',
			'badge'       => array(
				'label' => __( 'Performance', 'search-with-algolia-headless-extention' ),
				'color' => 'blue',
			),
			'description' => '',
			'actions'     => '',
			'test'        => 'algolia_headless_plugin',
		);

		// Check if Algolia plugin is active.
		if ( ! Algolia_Headless_Helper::is_algolia_plugin_active() ) {
			$result['status']      = 'critical';
			$result['label']       = __( 'WP Search with Algolia plugin is not active', 'search-with-algolia-headless-extention' );
			$result['description'] = sprintf(
				'<p>%s</p>',
				__( 'Algolia Headless extension requires the WP Search with Algolia plugin to function.', 'search-with-algolia-headless-extention' )
			);
			return $result;
		}

		// Check if domain is configured.
		$headless_domain = Algolia_Headless_Helper::get_headless_domain();
		if ( empty( $headless_domain ) ) {
			$result['status']      = 'recommended';
			$result['label']       = __( 'Headless domain is not configured', 'search-with-algolia-headless-extention' );
			$result['description'] = sprintf(
				'<p>%s</p>',
				__( 'Please configure the headless site domain in Settings &raquo; Reading.', 'search-with-algolia-headless-extention' )
			);
			$result['actions']     = sprintf(
				'<a href="%s">%s</a>',
				admin_url( 'options-reading.php#algolia_headless_domain' ),
				__( 'Configure Domain', 'search-with-algolia-headless-extention' )
			);
			return $result;
		}

		// Validate domain format.
		$parsed = wp_parse_url( $headless_domain );
		if ( ! $parsed || ! isset( $parsed['host'] ) ) {
			$result['status']      = 'critical';
			$result['label']       = __( 'Invalid headless domain format', 'search-with-algolia-headless-extention' );
			$result['description'] = sprintf(
				'<p>%s</p>',
				sprintf(
					/* translators: %s: Invalid domain */
					__( 'The configured domain <code>%s</code> is not a valid URL.', 'search-with-algolia-headless-extention' ),
					esc_html( $headless_domain )
				)
			);
			return $result;
		}

		// Check HTTPS.
		if ( isset( $parsed['scheme'] ) && 'http' === $parsed['scheme'] ) {
			$result['status']      = 'recommended';
			$result['label']       = __( 'Headless domain uses HTTP instead of HTTPS', 'search-with-algolia-headless-extention' );
			$result['description'] = sprintf(
				'<p>%s</p>',
				__( 'For better security, consider using HTTPS for your headless domain.', 'search-with-algolia-headless-extention' )
			);
		}

		// Check if using constant.
		if ( defined( 'ALGOLIA_HEADLESS_DOMAIN' ) && ALGOLIA_HEADLESS_DOMAIN ) {
			$result['description'] = sprintf(
				'<p>%s</p>',
				sprintf(
					/* translators: %s: Domain configured via constant */
					__( 'Domain is configured via ALGOLIA_HEADLESS_DOMAIN constant: <code>%s</code>', 'search-with-algolia-headless-extention' ),
					esc_html( ALGOLIA_HEADLESS_DOMAIN )
				)
			);
		} else {
			$result['description'] = sprintf(
				'<p>%s</p>',
				sprintf(
					/* translators: %s: Configured domain */
					__( 'Headless domain: <code>%s</code>', 'search-with-algolia-headless-extention' ),
					esc_html( $headless_domain )
				)
			);
		}

		return $result;
	}
}


// Initialize plugin classes.
new Algolia_Headless_Admin_Notices();
new Algolia_Headless_Site_Health();
new Algolia_Headless_Settings();
new Algolia_Headless_Replacer();

// Register WP-CLI commands.
if ( defined( 'WP_CLI' ) && WP_CLI ) {
	require_once dirname( __FILE__ ) . '/includes/class-wp-cli.php';
	WP_CLI::add_command( 'algolia-headless', 'Algolia_Headless_CLI' );
}