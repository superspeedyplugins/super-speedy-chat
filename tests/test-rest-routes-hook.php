<?php
/**
 * Regression: ssc_register_rest_routes action fires, and add-ons can
 * register routes during it.
 *
 * Also smoke-tests that the existing Discord routes (now registered via
 * this same hook from class-ssc-discord.php) actually appear in the REST
 * route table.
 */
require __DIR__ . '/lib/bootstrap.php';

echo "=== ssc_register_rest_routes action + REST route registration ===\n";

// Register a test route during the hook.
$action_fired = false;
add_action( 'ssc_register_rest_routes', function( $rest ) use ( &$action_fired ) {
    $action_fired = true;
    register_rest_route( 'ssc/v1', '/test-addon/ping', array(
        'methods'             => 'GET',
        'callback'            => function() { return rest_ensure_response( array( 'pong' => true ) ); },
        'permission_callback' => '__return_true',
    ) );
} );

// Make sure rest routes are registered.
do_action( 'rest_api_init' );

ssc_assert_eq( true, $action_fired, 'ssc_register_rest_routes fired during rest_api_init' );

$server = rest_get_server();
$routes = $server->get_routes();

ssc_assert_true( isset( $routes['/ssc/v1/test-addon/ping'] ), 'Add-on REST route was registered' );

// Discord routes are now registered via the same hook — verify they're present.
ssc_assert_true( isset( $routes['/ssc/v1/discord/incoming'] ), 'Discord /discord/incoming route exists (from class-ssc-discord.php)' );
ssc_assert_true( isset( $routes['/ssc/v1/admin/discord/test'] ), 'Discord /admin/discord/test route exists' );

// Core routes should also still exist.
ssc_assert_true( isset( $routes['/ssc/v1/session'] ),                   'Core /session route still registered' );
ssc_assert_true( isset( $routes['/ssc/v1/send'] ),                      'Core /send route still registered' );
ssc_assert_true( isset( $routes['/ssc/v1/poll'] ),                      'Core /poll route still registered' );
ssc_assert_true( isset( $routes['/ssc/v1/admin/conversations'] ),       'Core /admin/conversations route still registered' );
ssc_assert_true( isset( $routes['/ssc/v1/admin/reply'] ),               'Core /admin/reply route still registered' );

ssc_test_summary();
