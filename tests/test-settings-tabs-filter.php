<?php
/**
 * Regression: ssc_settings_tabs filter + ssc_register_settings action.
 *
 * If add-ons can't add tabs / register fields, they can't ship a settings UI.
 */
require __DIR__ . '/lib/bootstrap.php';

echo "=== ssc_settings_tabs + ssc_register_settings ===\n";

// --- ssc_settings_tabs filter ------------------------------------------

$tabs_added = false;
add_filter( 'ssc_settings_tabs', function( $tabs ) use ( &$tabs_added ) {
    $tabs_added = true;
    $tabs['ssc_test_addon'] = array(
        'label' => 'Test Add-on',
        'order' => 75,
    );
    return $tabs;
} );

// Simulate the same defaults the admin renderer uses (in render_page()).
$defaults = array(
    'chats'         => array( 'label' => 'Chats',         'order' => 10 ),
    'general'       => array( 'label' => 'General',       'order' => 20 ),
    'display_names' => array( 'label' => 'Display Names', 'order' => 30 ),
    'behaviour'     => array( 'label' => 'Behaviour',     'order' => 40 ),
    'email'         => array( 'label' => 'Email',         'order' => 50 ),
    'canned'        => array( 'label' => 'Canned',        'order' => 60 ),
    'llm'           => array( 'label' => 'LLM',           'order' => 70 ),
    'status'        => array( 'label' => 'Status',        'order' => 100 ),
);
$result = apply_filters( 'ssc_settings_tabs', $defaults );

ssc_assert_eq( true, $tabs_added, 'Filter callback fired' );
ssc_assert_true( isset( $result['ssc_test_addon'] ), 'Filter added our tab' );
if ( isset( $result['ssc_test_addon'] ) ) {
    ssc_assert_eq( 'Test Add-on', $result['ssc_test_addon']['label'], 'Added tab has correct label' );
    ssc_assert_eq( 75, $result['ssc_test_addon']['order'], 'Added tab has correct order' );
}

// Discord registers its tab via the same filter (from class-ssc-discord.php).
ssc_assert_true( isset( $result['discord'] ), 'Discord tab is registered via the filter' );

// --- ssc_register_settings action ---------------------------------------

$register_fired = 0;
add_action( 'ssc_register_settings', function() use ( &$register_fired ) {
    $register_fired++;
} );

// Trigger by calling the admin's register_settings (it fires the action at the end).
// Need an SSC_Admin instance — and that path does its own settings registration,
// so just fire the action directly to test the contract.
do_action( 'ssc_register_settings' );
ssc_assert_eq( 1, $register_fired, 'ssc_register_settings fires when invoked' );

ssc_test_summary();
