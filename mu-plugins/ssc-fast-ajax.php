<?php
/**
 * Plugin Name: Super Speedy Chat Fast Ajax
 * Description: Speeds up AJAX requests for Super Speedy Chat by bypassing full WordPress loading.
 * Author: Dave Hilditch
 * Version: 1.0.0
 * Author URI: https://www.superspeedyplugins.com
 */

// Simple status check.
if ( isset( $_GET['is_ssc_ultra_ajax_active'] ) ) {
    echo '<h2>Super Speedy Chat Ultra Ajax</h2>';
    echo '<p>Ultra Ajax is active, bypassing full loading of WordPress for chat AJAX requests.</p>';
    die();
}

// Build the REST route prefix for this site.
function ssc_get_full_rest_route( $route = '' ) {
    $rest_prefix    = rest_get_url_prefix();
    $site_url_path  = parse_url( get_site_url(), PHP_URL_PATH );
    $full_rest_route = ( ! empty( $site_url_path ) ? $site_url_path : '' ) . '/' . $rest_prefix . ( $route ? '/' . ltrim( $route, '/' ) : '' );
    return $full_rest_route;
}

$request_path = parse_url( $_SERVER['REQUEST_URI'], PHP_URL_PATH );

// Only intercept /wp-json/ssc/v1/ requests.
if ( strpos( $request_path, ssc_get_full_rest_route( 'ssc/v1/' ) ) !== 0 ) {
    return;
}

// Check if Ultra Ajax is enabled.
$ssc_mu_options = get_option( 'ssc_options', array() );
$ssc_mu_enabled = isset( $ssc_mu_options['ssc_mu_enabled'] ) ? $ssc_mu_options['ssc_mu_enabled'] : true;
if ( ! $ssc_mu_enabled ) {
    return; // Fall through to normal REST API.
}

// Parse the route to determine the command.
$ssc_rest_prefix = ssc_get_full_rest_route( 'ssc/v1/' );
$ssc_route_path  = substr( $request_path, strlen( $ssc_rest_prefix ) );
$ssc_route_parts = explode( '/', trim( $ssc_route_path, '/' ) );
$ssc_command     = $ssc_route_parts[0] ?? '';

// Only handle known visitor-facing routes in fast mode.
// Admin routes need full WP auth, so we let those fall through.
$ssc_fast_commands = array( 'poll', 'send', 'session' );
if ( ! in_array( $ssc_command, $ssc_fast_commands, true ) ) {
    return; // Let WordPress handle admin routes normally.
}

// Define AJAX constants.
if ( ! defined( 'DOING_AJAX' ) ) {
    define( 'DOING_AJAX', true );
}
if ( ! defined( 'DOING_SSC_FAST_AJAX' ) ) {
    define( 'DOING_SSC_FAST_AJAX', true );
}

// Create stub for is_user_logged_in if not available (we don't need auth for visitor routes).
if ( ! function_exists( 'is_user_logged_in' ) ) {
    function is_user_logged_in() { return false; }
}

// Load the plugin's REST handler class.
$ssc_plugin_dir = WP_PLUGIN_DIR . '/super-speedy-chat/';
$ssc_loader     = $ssc_plugin_dir . 'includes/class-ssc-rest.php';

if ( ! file_exists( $ssc_loader ) ) {
    return; // Plugin not found, fall through to normal WP.
}

// Load required class files.
require_once $ssc_plugin_dir . 'includes/class-ssc-db.php';
require_once $ssc_plugin_dir . 'includes/class-ssc-settings.php';
require_once $ssc_plugin_dir . 'includes/class-ssc-discord.php';
require_once $ssc_loader;

header( 'Content-Type: application/json; charset=utf-8' );
header( 'Cache-Control: no-cache, no-store, must-revalidate' );

// Rate limiting for send and session endpoints.
if ( in_array( $ssc_command, array( 'send', 'session' ), true ) ) {
    $ssc_rate_key  = 'ssc_rate_' . md5( isset( $_SERVER['REMOTE_ADDR'] ) ? $_SERVER['REMOTE_ADDR'] : 'unknown' );
    $ssc_rate_data = get_transient( $ssc_rate_key );

    if ( $ssc_rate_data === false ) {
        $ssc_rate_data = array( 'count' => 0, 'window_start' => time() );
    }

    $ssc_rate_window = 60; // 1 minute window
    $ssc_rate_limit  = ( $ssc_command === 'send' ) ? 15 : 10; // 15 messages/min, 10 sessions/min

    // Reset window if expired.
    if ( ( time() - $ssc_rate_data['window_start'] ) > $ssc_rate_window ) {
        $ssc_rate_data = array( 'count' => 0, 'window_start' => time() );
    }

    $ssc_rate_data['count']++;
    set_transient( $ssc_rate_key, $ssc_rate_data, $ssc_rate_window );

    if ( $ssc_rate_data['count'] > $ssc_rate_limit ) {
        http_response_code( 429 );
        echo json_encode( array( 'error' => 'Too many requests. Please wait a moment.' ) );
        die();
    }
}

$ssc_response = null;

switch ( $ssc_command ) {
    case 'poll':
        $ssc_response = SSC_REST::fast_poll( $_GET );
        break;

    case 'send':
        $ssc_input = json_decode( file_get_contents( 'php://input' ), true );
        if ( ! is_array( $ssc_input ) ) {
            $ssc_input = $_POST;
        }
        $ssc_response = SSC_REST::fast_send( $ssc_input );
        break;

    case 'session':
        $ssc_response = SSC_REST::fast_session();
        break;
}

if ( $ssc_response !== null ) {
    echo json_encode( $ssc_response );

    // Send response to client immediately, then handle Discord push.
    if ( function_exists( 'fastcgi_finish_request' ) ) {
        fastcgi_finish_request();
    }

    // Push visitor message to Discord instantly (after response is sent).
    if ( $ssc_command === 'send' && ! empty( $ssc_response['message_id'] ) && class_exists( 'SSC_Discord' ) && SSC_Discord::is_enabled() ) {
        $ssc_discord_msg = isset( $ssc_input['message'] ) ? sanitize_text_field( $ssc_input['message'] ) : '';
        if ( ! empty( $ssc_discord_msg ) ) {
            SSC_Discord::push_message( $ssc_response['conversation_id'], 'Visitor', $ssc_discord_msg, true );
        }
    }

    die();
}

// If we get here somehow, fall through to normal WordPress.
