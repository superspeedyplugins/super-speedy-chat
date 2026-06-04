<?php
/**
 * Regression: SSC_Addons registry — register/get/version-gate behaviour.
 */
require __DIR__ . '/lib/bootstrap.php';

echo "=== SSC_Addons registry ===\n";

ssc_assert_true( class_exists( 'SSC_Addons' ), 'SSC_Addons class is loaded' );
ssc_assert_eq( '1.0', SSC_Addons::ADDON_API_VERSION, 'API version constant is 1.0' );

// Discord self-registers from class-ssc-discord.php so the table starts non-empty.
$discord = SSC_Addons::get( 'super-speedy-chat-discord' );
ssc_assert_true( is_array( $discord ), 'Discord registered itself' );
if ( is_array( $discord ) ) {
    ssc_assert_eq( 'discord', $discord['channel'], 'Discord declares channel=discord' );
}

// register() with bare-minimum args.
$ok = SSC_Addons::register( array(
    'slug'    => 'ssc-test-addon-helloworld',
    'name'    => 'Hello World Test',
    'version' => '0.1.0',
    'channel' => 'helloworld',
) );
ssc_assert_eq( true, $ok, 'register() returns true for valid args' );
ssc_assert_true( SSC_Addons::is_active( 'ssc-test-addon-helloworld' ), 'is_active() reflects registration' );

// register() rejects missing slug/name.
$missing = SSC_Addons::register( array( 'slug' => '', 'name' => 'X' ) );
ssc_assert_eq( false, $missing, 'register() rejects empty slug' );

// register() rejects too-new requires_addon_api.
$too_new = SSC_Addons::register( array(
    'slug'               => 'ssc-test-addon-future',
    'name'               => 'Future',
    'version'            => '1.0.0',
    'requires_addon_api' => '99.0',
) );
ssc_assert_eq( false, $too_new, 'register() rejects add-on requiring future API version' );
ssc_assert_eq( false, SSC_Addons::is_active( 'ssc-test-addon-future' ), 'Rejected add-on is not active' );

// get_channels() returns at least website + discord.
$channels = SSC_Addons::get_channels();
$channel_ids = array_map( function( $c ) { return $c['id']; }, $channels );
ssc_assert_contains( 'website', $channel_ids, 'get_channels() includes "website"' );
ssc_assert_contains( 'discord', $channel_ids, 'get_channels() includes "discord" (Discord registered itself)' );

ssc_test_summary();
