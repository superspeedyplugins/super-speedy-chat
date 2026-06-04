<?php
/**
 * Regression: ssc_sanitize_options filter lets add-ons sanitise their keys
 * inside the shared ssc_options array.
 *
 * Specifically also verifies that Discord's own keys survive a save (the
 * webhook secret + bot token round-trip) — that was a load-bearing detail
 * of the pre-1.08 sanitize_options() method.
 */
require __DIR__ . '/lib/bootstrap.php';

echo "=== ssc_sanitize_options filter ===\n";

// Snapshot existing options to restore at end.
$saved_options = get_option( 'ssc_options', array() );

// --- Custom add-on key round-trips through the filter -------------------

$listener_received_input = null;
add_filter( 'ssc_sanitize_options', function( $sanitized, $input ) use ( &$listener_received_input ) {
    $listener_received_input = $input;
    $sanitized['ssc_test_addon_token'] = isset( $input['ssc_test_addon_token'] )
        ? sanitize_text_field( $input['ssc_test_addon_token'] )
        : '';
    $sanitized['ssc_test_addon_enabled'] = ! empty( $input['ssc_test_addon_enabled'] );
    return $sanitized;
}, 10, 2 );

if ( ! class_exists( 'SSC_Admin' ) ) {
    ssc_test_fail( 'SSC_Admin loaded' );
    ssc_test_summary();
}

$admin = new SSC_Admin();

$input = array(
    'ssc_enabled'            => '1',
    'ssc_test_addon_enabled' => '1',
    'ssc_test_addon_token'   => '  secret123  ',
    'ssc_discord_bot_token'  => 'discord-token-xyz',
    'ssc_discord_channel_id' => '1234567890',
    'ssc_discord_enabled'    => '1',
);

$out = $admin->sanitize_options( $input );

ssc_assert_true( is_array( $listener_received_input ), 'Filter callback received $input' );
ssc_assert_eq( 'secret123', $out['ssc_test_addon_token'], 'Custom key is sanitised + trimmed by the listener' );
ssc_assert_eq( true, $out['ssc_test_addon_enabled'], 'Custom checkbox key resolves to true' );

// Discord listener (registered from class-ssc-discord.php) also picks up its own keys.
ssc_assert_eq( 'discord-token-xyz', $out['ssc_discord_bot_token'], 'Discord listener sanitised bot_token' );
ssc_assert_eq( '1234567890', $out['ssc_discord_channel_id'], 'Discord listener sanitised channel_id' );
ssc_assert_eq( true, $out['ssc_discord_enabled'], 'Discord listener sanitised enabled checkbox' );

// --- Webhook secret preservation ----------------------------------------

// Seed an existing secret, then save options without re-submitting it.
update_option( 'ssc_options', array_merge( $saved_options, array(
    'ssc_discord_webhook_secret' => 'preserved-secret-abc',
) ) );

$out2 = $admin->sanitize_options( array(
    'ssc_enabled' => '1',
    'ssc_discord_bot_token' => 'foo',
) );
ssc_assert_eq( 'preserved-secret-abc', $out2['ssc_discord_webhook_secret'], 'Webhook secret preserved when not re-submitted' );

// Restore options.
update_option( 'ssc_options', $saved_options );

ssc_test_summary();
