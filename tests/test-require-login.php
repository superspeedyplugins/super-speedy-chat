<?php
/**
 * Functional: the Require Login setting actually gates the visitor endpoints.
 *
 * Regression for the gap where ssc_require_login was registered + sanitized
 * but never enforced anywhere: anonymous visitors must get 401s from every
 * visitor endpoint when the setting is on, logged-in users (any role) must
 * chat normally, and the mu-plugin must fall through to the normal REST API
 * (it can't authenticate, so fast-handling these routes would either block
 * logged-in users or let anonymous ones straight past the check).
 */
require __DIR__ . '/lib/bootstrap.php';

echo "=== Require Login enforcement ===\n";

ssc_test_reset_tables();

// Ensure all SSC routes are registered (fires rest_api_init).
rest_get_server();

// Deterministic, fresh rate-limit bucket for this run.
$_SERVER['REMOTE_ADDR'] = '198.51.100.8';
foreach ( array( 'session', 'send', 'auto_reply' ) as $a ) {
    delete_transient( 'ssc_rate_' . $a . '_' . md5( $_SERVER['REMOTE_ADDR'] ) );
}

// Snapshot options so the setting can be restored afterwards.
$ssc_old_options = get_option( 'ssc_options', array() );

function ssc_test_set_require_login( $on ) {
    $options = get_option( 'ssc_options', array() );
    if ( ! is_array( $options ) ) {
        $options = array();
    }
    $options['ssc_require_login'] = $on;
    update_option( 'ssc_options', $options );
    SSC_Settings::flush_cache();
}

ssc_test_set_require_login( true );

// --- Anonymous visitor: every visitor endpoint returns 401 -------------

wp_set_current_user( 0 );
$visitor_hash = bin2hex( random_bytes( 32 ) );
$_COOKIE['ssc_visitor_hash'] = $visitor_hash;

foreach ( array(
    array( 'POST', '/ssc/v1/session' ),
    array( 'POST', '/ssc/v1/send' ),
    array( 'GET',  '/ssc/v1/poll' ),
    array( 'POST', '/ssc/v1/email' ),
    array( 'POST', '/ssc/v1/auto-reply' ),
) as $route ) {
    $req = new WP_REST_Request( $route[0], $route[1] );
    if ( $route[1] === '/ssc/v1/send' ) {
        $req->set_param( 'message', 'anonymous message' );
    }
    $res = rest_do_request( $req );
    ssc_assert_eq( 401, $res->get_status(), $route[0] . ' ' . $route[1] . ' returns 401 for anonymous visitor' );
}

// No conversation should have been created by the refused requests.
global $wpdb;
$conv_count = (int) $wpdb->get_var(
    $wpdb->prepare( "SELECT COUNT(*) FROM {$wpdb->prefix}ssc_conversations WHERE visitor_hash = %s", $visitor_hash )
);
ssc_assert_eq( 0, $conv_count, 'refused anonymous requests create no conversation rows' );

// --- Logged-in user (lowest role): full session/send/poll round-trip ---

$ssc_test_user_id = wp_insert_user( array(
    'user_login' => 'ssc_test_subscriber_' . wp_rand( 1000, 9999 ),
    'user_pass'  => wp_generate_password(),
    'role'       => 'subscriber',
) );
ssc_assert_true( ! is_wp_error( $ssc_test_user_id ), 'test subscriber created' );

wp_set_current_user( $ssc_test_user_id );

$req = new WP_REST_Request( 'POST', '/ssc/v1/session' );
$res = rest_do_request( $req );
ssc_assert_eq( 200, $res->get_status(), 'POST /session returns 200 for a logged-in subscriber' );
$session = $res->get_data();

$req = new WP_REST_Request( 'POST', '/ssc/v1/send' );
$req->set_param( 'message', 'Logged-in hello' );
$res = rest_do_request( $req );
ssc_assert_eq( 200, $res->get_status(), 'POST /send returns 200 for a logged-in subscriber' );

$req = new WP_REST_Request( 'GET', '/ssc/v1/poll' );
$req->set_param( 'since_id', 0 );
$res = rest_do_request( $req );
ssc_assert_eq( 200, $res->get_status(), 'GET /poll returns 200 for a logged-in subscriber' );

// The conversation is linked to the user account (the doc's promise).
$conv = SSC_DB::get_conversation( (int) $session['conversation_id'] );
ssc_assert_eq( $ssc_test_user_id, (int) $conv->user_id, 'conversation is linked to the logged-in user' );

// --- Setting off: anonymous chat works again ----------------------------

ssc_test_set_require_login( false );
wp_set_current_user( 0 );
$_COOKIE['ssc_visitor_hash'] = bin2hex( random_bytes( 32 ) );

$req = new WP_REST_Request( 'POST', '/ssc/v1/session' );
$res = rest_do_request( $req );
ssc_assert_eq( 200, $res->get_status(), 'POST /session returns 200 for anonymous visitor when setting is off' );

// --- The mu-plugin falls through when Require Login is on --------------

$ssc_mu_source = file_get_contents( SSC_DIR . 'mu-plugins/ssc-fast-ajax.php' );
ssc_assert_true(
    strpos( $ssc_mu_source, "ssc_mu_options['ssc_require_login']" ) !== false,
    'mu-plugin source falls through to normal REST when ssc_require_login is set'
);

// The frontend config exposes the flag so the widget can gate its UI.
ssc_test_set_require_login( true );
ssc_assert_eq( true, (bool) SSC_Settings::get_option( 'ssc_require_login', false ), 'SSC_Settings reflects the enabled setting after flush_cache()' );

// --- Cleanup ------------------------------------------------------------

update_option( 'ssc_options', $ssc_old_options );
SSC_Settings::flush_cache();
if ( ! is_wp_error( $ssc_test_user_id ) ) {
    wp_delete_user( $ssc_test_user_id );
}

ssc_test_summary();
