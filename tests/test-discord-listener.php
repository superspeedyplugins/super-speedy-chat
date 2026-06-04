<?php
/**
 * Regression: Discord's hook listeners are attached and short-circuit
 * cleanly when Discord is not configured.
 *
 * We don't want to actually hit the Discord API in tests, so we verify:
 *  - the listener method is hooked into ssc_visitor_message_sent
 *  - calling it with Discord disabled is a no-op (returns void without error)
 *  - the channel + tab + settings sanitiser are also attached
 */
require __DIR__ . '/lib/bootstrap.php';

echo "=== Discord listeners (no-network smoke test) ===\n";

ssc_assert_true( class_exists( 'SSC_Discord' ), 'SSC_Discord class loaded' );

// has_action returns priority if registered, or false.
$prio_visitor = has_action( 'ssc_visitor_message_sent', array( 'SSC_Discord', 'on_visitor_message_sent' ) );
$prio_admin   = has_action( 'ssc_admin_reply_sent',     array( 'SSC_Discord', 'on_admin_reply_sent' ) );
ssc_assert_eq( 10, $prio_visitor, 'SSC_Discord::on_visitor_message_sent is hooked at priority 10' );
ssc_assert_eq( 10, $prio_admin,   'SSC_Discord::on_admin_reply_sent is hooked at priority 10' );

$prio_channel  = has_filter( 'ssc_channels',           array( 'SSC_Discord', 'register_channel' ) );
$prio_tab      = has_filter( 'ssc_settings_tabs',      array( 'SSC_Discord', 'register_tab' ) );
$prio_register = has_action( 'ssc_register_settings',  array( 'SSC_Discord', 'register_settings_fields' ) );
$prio_sanitise = has_filter( 'ssc_sanitize_options',   array( 'SSC_Discord', 'sanitize_options' ) );
$prio_rest     = has_action( 'ssc_register_rest_routes', array( 'SSC_Discord', 'register_rest_routes' ) );

ssc_assert_eq( 10, $prio_channel,  'SSC_Discord::register_channel hooked into ssc_channels' );
ssc_assert_eq( 10, $prio_tab,      'SSC_Discord::register_tab hooked into ssc_settings_tabs' );
ssc_assert_eq( 10, $prio_register, 'SSC_Discord::register_settings_fields hooked into ssc_register_settings' );
ssc_assert_eq( 10, $prio_sanitise, 'SSC_Discord::sanitize_options hooked into ssc_sanitize_options' );
ssc_assert_eq( 10, $prio_rest,     'SSC_Discord::register_rest_routes hooked into ssc_register_rest_routes' );

// When Discord is not configured, is_enabled() is false and push_message is a no-op.
ssc_assert_eq( false, SSC_Discord::is_enabled(), 'is_enabled() is false when bot token / channel not configured' );

// Calling on_visitor_message_sent with Discord disabled should not throw.
$fake_conv = (object) array( 'id' => 99999, 'status' => 'waiting' );
$threw = false;
try {
    SSC_Discord::on_visitor_message_sent( 1, $fake_conv, 'hello', (object) array( 'display_name' => 'Test' ) );
} catch ( \Throwable $e ) {
    $threw = true;
}
ssc_assert_eq( false, $threw, 'on_visitor_message_sent() no-ops when disabled (no exception)' );

// register_channel filter returns array containing 'discord'.
$channels = SSC_Discord::register_channel( array() );
$ids = array_map( function( $c ) { return $c['id']; }, $channels );
ssc_assert_contains( 'discord', $ids, 'register_channel appends discord channel definition' );

// register_tab filter returns tabs array with 'discord' key.
$tabs = SSC_Discord::register_tab( array() );
ssc_assert_true( isset( $tabs['discord'] ), 'register_tab returns array with "discord" key' );
ssc_assert_eq( 80, $tabs['discord']['order'], 'Discord tab order = 80' );

ssc_test_summary();
