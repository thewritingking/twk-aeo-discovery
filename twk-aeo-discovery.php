<?php
/**
 * Plugin Name:       TWK AEO Discovery
 * Plugin URI:        https://thewritingking.com/
 * Description:       Entity-authority schema enrichment so AI answer engines cite you. Plus XML sitemap, IndexNow notifications, and llms.txt.
 * Version:           1.7.0
 * Requires at least: 5.6
 * Requires PHP:      7.4
 * Author:            Richard Lowe
 * Author URI:        https://thewritingking.com/
 * Text Domain:       twk-aeo-discovery
 * Domain Path:       /lang
 * License:           GPLv2 or later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 *
 * @package TWKDiscovery
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // No direct access.
}

define( 'TWKD_VERSION', '1.7.0' );
define( 'TWKD_NAME', 'TWK AEO Discovery' );
define( 'TWKD_HOME', 'https://thewritingking.com/' );
define( 'TWKD_FILE', __FILE__ );
define( 'TWKD_DIR', plugin_dir_path( __FILE__ ) );
define( 'TWKD_URL', plugin_dir_url( __FILE__ ) );
define( 'TWKD_OPTION', 'twkd_settings' );
define( 'TWKD_KEY_OPTION', 'twkd_indexnow_key' );

/**
 * Default settings. The single source of truth for option keys.
 *
 * @return array
 */
function twkd_default_settings() {
	return array(
		'enable_sitemap'      => 1,
		'post_types'          => array( 'post', 'page' ),
		'taxonomies'          => array( 'category', 'post_tag' ),
		'per_page'            => 2000,
		'robots_sitemap'      => 1,
		'disable_core_sitemap' => 1,
		'sitemap_stylesheet'  => 1,

		'enable_indexnow'     => 1,

		// Entity authority — enriches Slim SEO's Organization and Person schema
		// rather than emitting our own (which would duplicate Slim SEO).
		'enable_entity'         => 0,
		'entity_suppress_front' => 0,
		'org_name'              => '',
		'org_id'                => '',
		'org_logo'              => '',
		'org_sameas'            => '',
		'org_knowsabout'        => '',
		'org_contactpoints'     => '',
		'org_altname'           => '',
		'org_description'        => '',
		'org_areaserved'        => '',
		'author_name'           => '',
		'author_id'             => '',
		'author_bio'            => '',
		'author_jobtitle'       => '',
		'author_image'          => '',
		'author_sameas'         => '',
		'author_knowsabout'     => '',
		'author_altname'        => '',
		'author_url'            => '',
		'author_givenname'      => '',
		'author_familyname'     => '',

		'enable_llms'         => 1,
		'llms_mode'           => 'auto', // 'auto' generates from site content; 'custom' serves the editable field verbatim.
		'llms_custom'         => '',
		'llms_intro'          => '',
		'llms_key_pages'      => '',
		'ai_welcome'          => 1,
	);
}

/**
 * Get one setting with default fallback.
 *
 * @param string $key Setting key.
 * @return mixed
 */
function twkd_get_option( $key ) {
	$defaults = twkd_default_settings();
	$saved    = get_option( TWKD_OPTION, array() );
	if ( ! is_array( $saved ) ) {
		$saved = array();
	}
	$merged = array_merge( $defaults, $saved );
	return isset( $merged[ $key ] ) ? $merged[ $key ] : null;
}

/**
 * Get (or lazily create) the IndexNow API key.
 *
 * IndexNow keys must be 8-128 chars, hexadecimal/alphanumeric/dashes.
 *
 * @return string
 */
function twkd_get_indexnow_key() {
	$key = get_option( TWKD_KEY_OPTION );
	if ( ! $key ) {
		$key = md5( wp_generate_password( 32, false ) . microtime() );
		update_option( TWKD_KEY_OPTION, $key, false );
	}
	return $key;
}

/**
 * Record one of the plugin's own operational errors for the Diagnostics tab.
 *
 * Stores a capped ring buffer (newest first) in a non-autoloaded option. Only
 * the plugin's own failures are logged here — never general PHP errors. The
 * logger dedupes and rate-limits: if the same error was just recorded within
 * the last five minutes it bumps that entry's count and time instead of adding
 * a row, so a persistent error (e.g. a broken taxonomy hit repeatedly by
 * crawlers during sitemap generation) can neither flood the log nor hammer the
 * option on every request.
 *
 * @param string $context Short label, e.g. 'IndexNow', 'Sitemap', 'Import'.
 * @param string $message Human-readable error detail.
 */
function twkd_log_error( $context, $message ) {
	$context = sanitize_text_field( $context );
	$message = sanitize_text_field( $message );
	$now     = time();

	$log = get_option( 'twkd_error_log', array() );
	if ( ! is_array( $log ) ) {
		$log = array();
	}

	if ( ! empty( $log ) && isset( $log[0]['context'], $log[0]['message'] )
		&& $log[0]['context'] === $context && $log[0]['message'] === $message ) {
		// Same as the most recent entry.
		if ( ( $now - (int) $log[0]['time'] ) < 300 ) {
			return; // Too soon — skip the write entirely.
		}
		$log[0]['time']  = $now;
		$log[0]['count'] = isset( $log[0]['count'] ) ? (int) $log[0]['count'] + 1 : 2;
		update_option( 'twkd_error_log', $log, false );
		return;
	}

	array_unshift(
		$log,
		array(
			'time'    => $now,
			'context' => $context,
			'message' => $message,
			'count'   => 1,
		)
	);
	$log = array_slice( $log, 0, 30 );
	update_option( 'twkd_error_log', $log, false );
}

// Load classes.
require_once TWKD_DIR . 'includes/class-twkd-llms.php';
require_once TWKD_DIR . 'includes/class-twkd-sitemap.php';
require_once TWKD_DIR . 'includes/class-twkd-indexnow.php';
require_once TWKD_DIR . 'includes/class-twkd-entity.php';
require_once TWKD_DIR . 'includes/class-twkd-admin.php';
require_once TWKD_DIR . 'includes/class-twkd-wizard.php';
require_once TWKD_DIR . 'includes/class-twkd-instructions.php';
require_once TWKD_DIR . 'includes/class-twkd-report.php';

/**
 * Boot the plugin.
 */
function twkd_boot() {
	load_plugin_textdomain( 'twk-aeo-discovery', false, dirname( plugin_basename( TWKD_FILE ) ) . '/lang' );
	TWKD_Sitemap::instance()->hooks();
	TWKD_IndexNow::instance()->hooks();
	TWKD_Entity::instance()->hooks();
	if ( is_admin() ) {
		TWKD_Admin::instance()->hooks();
		TWKD_Wizard::instance()->hooks();
		TWKD_Report::instance()->hooks();
	}
}
add_action( 'plugins_loaded', 'twkd_boot' );

/**
 * Output a small generator comment in the page head, crediting the plugin that
 * produced the sitemap and schema enhancements. This is the conventional
 * "made with" breadcrumb (compare Yoast SEO and Rank Math): a non-semantic HTML
 * comment only, so it makes no structured-data claims and asserts nothing about
 * who authored the site's content. The display name and credit URL come from
 * the TWKD_NAME / TWKD_HOME constants so this code is identical across builds.
 */
function twkd_generator_comment() {
	echo "\n<!-- " . esc_html( TWKD_NAME . ' ' . TWKD_VERSION ) . ' ' . esc_url( TWKD_HOME ) . " -->\n";
}
add_action( 'wp_head', 'twkd_generator_comment', 99 );

/**
 * Activation: generate the IndexNow key and register rewrite rules, then flush.
 */
function twkd_activate() {
	twkd_get_indexnow_key();
	twkd_maybe_import_llms();
	TWKD_Sitemap::instance()->register_rewrites();
	flush_rewrite_rules();
	set_transient( 'twkd_activation_redirect', 1, 60 );
}
register_activation_hook( __FILE__, 'twkd_activate' );

/**
 * If a physical llms.txt already exists in the site root, read it into the
 * editable field once so it is preserved, and switch to custom mode. Never
 * clobbers content that is already stored.
 */
function twkd_maybe_import_llms() {
	$existing = ABSPATH . 'llms.txt';
	if ( ! file_exists( $existing ) || ! is_readable( $existing ) ) {
		return;
	}
	$settings = get_option( TWKD_OPTION, array() );
	if ( ! is_array( $settings ) ) {
		$settings = array();
	}
	if ( ! empty( $settings['llms_custom'] ) ) {
		return;
	}
	$content = file_get_contents( $existing ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- local file.
	if ( false === $content || '' === trim( $content ) ) {
		return;
	}
	$settings['llms_custom'] = $content;
	$settings['llms_mode']   = 'custom';
	update_option( TWKD_OPTION, array_merge( twkd_default_settings(), $settings ) );
}

/**
 * Deactivation: clean up rewrite rules and any pending IndexNow cron events.
 */
function twkd_deactivate() {
	wp_clear_scheduled_hook( 'twkd_indexnow_event' );
	flush_rewrite_rules();
}
register_deactivation_hook( __FILE__, 'twkd_deactivate' );
