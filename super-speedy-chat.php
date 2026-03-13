<?php
/*
Plugin Name: Super Speedy Chat
Plugin URI: https://www.superspeedyplugins.com
Author: Dave Hilditch (Super Speedy Plugins)
Author URI: https://www.superspeedyplugins.com
Text Domain: super-speedy-chat
Version: 1.05.2
Description: The fastest live chat plugin for WordPress. Ultra-fast AJAX via mu-plugin, visitor-to-admin chat with email fallback.
*/

if ( ! defined( 'ABSPATH' ) ) {
    return;
}
https://maps.app.goo.gl/8v6XrjZNW2C29PZx5
// Load super-speedy-settings submodule (skip if loaded from multisite ultra ajax context)
if ( function_exists( 'wp_next_scheduled' ) ) {
    require_once( plugin_dir_path( __FILE__ ) . 'super-speedy-settings/super-speedy-settings.php' );
    $plugin_data = get_file_data( __FILE__, array( 'Version' => 'Version' ) );
    define( 'SSC_VERSION', $plugin_data['Version'] );
    SuperSpeedySettings_1_0::init( array(
        'plugin_slug' => 'super-speedy-chat',
        'version'     => SSC_VERSION,
        'file'        => __FILE__,
    ) );
}

// Define plugin constants.
if ( ! defined( 'SSC_DIR' ) ) {
    define( 'SSC_DIR', plugin_dir_path( __FILE__ ) );
}
if ( ! defined( 'SSC_URL' ) ) {
    define( 'SSC_URL', plugin_dir_url( __FILE__ ) );
}
if ( ! defined( 'SSC_VERSION' ) ) {
    define( 'SSC_VERSION', '1.00' );
}

// Include class files — these must only define classes/functions, no output
require_once SSC_DIR . 'includes/class-ssc-db.php';
require_once SSC_DIR . 'includes/class-ssc-chat.php';
require_once SSC_DIR . 'includes/class-ssc-session.php';
require_once SSC_DIR . 'includes/class-ssc-admin.php';
require_once SSC_DIR . 'includes/class-ssc-settings.php';
require_once SSC_DIR . 'includes/class-ssc-email.php';
require_once SSC_DIR . 'includes/class-ssc-mu-installer.php';
require_once SSC_DIR . 'includes/class-ssc-rest.php';
require_once SSC_DIR . 'includes/class-ssc-canned.php';
require_once SSC_DIR . 'includes/class-ssc-discord.php';

// Activation hook: create DB tables and install mu-plugin
register_activation_hook( __FILE__, function() {
    SSC_DB::create_tables();
    SSC_MU_Installer::install();
} );

// Deactivation hook: remove mu-plugin
register_deactivation_hook( __FILE__, function() {
    SSC_MU_Installer::uninstall();
} );

// Initialize components after all plugins are loaded
add_action( 'plugins_loaded', 'ssc_init_plugin' );

function ssc_init_plugin() {
    // Upgrade DB if needed.
    $current_db = get_option( 'ssc_db_version', '0' );
    if ( version_compare( $current_db, SSC_DB::DB_VERSION, '<' ) ) {
        SSC_DB::create_tables();
    }

    // Register admin menu.
    if ( is_admin() ) {
        $admin = new SSC_Admin();
        $admin->init();
    }

}


// Register Customizer settings (must be outside is_admin() for preview context)
add_action( 'customize_register', array( 'SSC_Admin', 'customizer_register' ) );

// Register REST API routes
add_action( 'rest_api_init', function() {
    $rest = new SSC_REST();
    $rest->register_routes();
} );

// Enqueue front-end assets
add_action( 'wp_enqueue_scripts', 'ssc_enqueue_frontend_assets' );

function ssc_enqueue_frontend_assets() {
    // Only load if chat is enabled.
    $enabled = SSC_Settings::get_option( 'ssc_enabled', true );
    if ( ! $enabled ) {
        return;
    }

    // Don't load for admins viewing wp-admin.
    if ( is_admin() ) {
        return;
    }

    wp_enqueue_style(
        'ssc-chat-bubble',
        SSC_URL . 'assets/chat-bubble.css',
        array(),
        SSC_VERSION
    );

    wp_enqueue_script(
        'ssc-chat-bubble',
        SSC_URL . 'assets/chat-bubble.js',
        array(),
        SSC_VERSION,
        true
    );

    // Gather settings for the front-end.
    $poll_interval      = SSC_Settings::get_option( 'ssc_poll_interval', 2000 );
    $idle_poll_interval = SSC_Settings::get_option( 'ssc_idle_poll_interval', 5000 );
    $max_message_length = SSC_Settings::get_option( 'ssc_max_message_length', 500 );
    $welcome_message    = SSC_Settings::get_option( 'ssc_welcome_message', __( 'Hi! How can we help you today?', 'super-speedy-chat' ) );
    $sounds_enabled     = SSC_Settings::get_option( 'ssc_play_sounds', true );
    $admin_timeout      = SSC_Settings::get_option( 'ssc_admin_timeout', 30 );
    $timeout_action     = SSC_Settings::get_option( 'ssc_timeout_action', 'show_email_prompt' );
    $login_prompt_after = SSC_Settings::get_option( 'ssc_login_prompt_after', 5 );
    $bubble_position    = SSC_Settings::get_option( 'ssc_bubble_position', 'bottom-right' );
    $primary_color      = SSC_Settings::get_option( 'ssc_primary_color', '#0073aa' );

    // Customizer appearance options.
    $customizer = get_option( 'ssc_customizer', array() );
    if ( ! is_array( $customizer ) ) {
        $customizer = array();
    }

    wp_localize_script( 'ssc-chat-bubble', 'ssc_config', array(
        'rest_url'           => esc_url_raw( rest_url( 'ssc/v1/' ) ),
        'nonce'              => wp_create_nonce( 'wp_rest' ),
        'poll_interval'      => absint( $poll_interval ),
        'idle_poll_interval' => absint( $idle_poll_interval ),
        'max_message_length' => absint( $max_message_length ),
        'welcome_message'    => esc_html( $welcome_message ),
        'sounds_enabled'     => (bool) $sounds_enabled,
        'sounds_url'         => SSC_URL . 'assets/sounds/',
        'images_url'         => SSC_URL . 'assets/images/',
        'admin_timeout'      => absint( $admin_timeout ),
        'timeout_action'     => $timeout_action,
        'login_prompt_after' => absint( $login_prompt_after ),
        'bubble_position'    => ! empty( $customizer['bubble_position'] ) ? $customizer['bubble_position'] : $bubble_position,
        'primary_color'      => ! empty( $customizer['primary_color'] ) ? sanitize_hex_color( $customizer['primary_color'] ) : sanitize_hex_color( $primary_color ),
        'header_bg_color'    => ! empty( $customizer['header_bg_color'] ) ? sanitize_hex_color( $customizer['header_bg_color'] ) : '',
        'visitor_msg_color'  => ! empty( $customizer['visitor_msg_color'] ) ? sanitize_hex_color( $customizer['visitor_msg_color'] ) : '',
        'header_image'       => ! empty( $customizer['header_image'] ) ? esc_url( $customizer['header_image'] ) : '',
        'window_title'       => ! empty( $customizer['window_title'] ) ? esc_html( $customizer['window_title'] ) : 'Chat',
        'trigger_icon'       => ! empty( $customizer['trigger_icon'] ) ? $customizer['trigger_icon'] : 'chat',
        'trigger_icon_image' => ! empty( $customizer['trigger_icon_image'] ) ? esc_url( $customizer['trigger_icon_image'] ) : '',
        'is_logged_in'       => is_user_logged_in(),
        'login_url'          => wp_login_url(),
        'register_url'       => wp_registration_url(),
    ) );
}

// Admin assets are enqueued by SSC_Admin::enqueue_scripts() which hooks into
// admin_enqueue_scripts and checks the hook suffix for the specific admin page.
