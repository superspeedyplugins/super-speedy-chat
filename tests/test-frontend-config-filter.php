<?php
/**
 * Regression: ssc_frontend_config filter and ssc_enqueue_frontend action
 * fire from ssc_enqueue_frontend_assets() in super-speedy-chat.php.
 *
 * Add-ons that need to ship JS to the bubble (e.g. WhatsApp "Click to Chat"
 * button) depend on these.
 */
require __DIR__ . '/lib/bootstrap.php';

echo "=== ssc_frontend_config filter + ssc_enqueue_frontend action ===\n";

// Hook the filter + action.
$filter_received = null;
$enqueue_fired   = 0;
add_filter( 'ssc_frontend_config', function( $config ) use ( &$filter_received ) {
    $filter_received = $config;
    $config['test_addon'] = array( 'flag' => true );
    return $config;
} );
add_action( 'ssc_enqueue_frontend', function() use ( &$enqueue_fired ) {
    $enqueue_fired++;
} );

// Make sure chat is enabled (the enqueue function bails if it isn't).
$options = get_option( 'ssc_options', array() );
$options['ssc_enabled'] = true;
update_option( 'ssc_options', $options );

// Pretend we're on the front-end, not admin. WP-CLI normally runs with is_admin()=false
// but be explicit so the function doesn't bail.
if ( function_exists( 'ssc_enqueue_frontend_assets' ) ) {
    ssc_enqueue_frontend_assets();
}

ssc_assert_true( is_array( $filter_received ), 'ssc_frontend_config filter received the config array' );
if ( is_array( $filter_received ) ) {
    ssc_assert_true( isset( $filter_received['rest_url'] ), 'Config blob includes rest_url' );
    ssc_assert_true( isset( $filter_received['nonce'] ),    'Config blob includes nonce' );
    ssc_assert_true( isset( $filter_received['poll_interval'] ), 'Config blob includes poll_interval' );
}
ssc_assert_eq( 1, $enqueue_fired, 'ssc_enqueue_frontend action fired exactly once' );

ssc_test_summary();
