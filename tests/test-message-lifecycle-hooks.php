<?php
/**
 * Regression: message-lifecycle hooks fire with the documented signatures.
 *
 * The whole point of the 1.08 refactor is that add-ons attach to these hooks
 * instead of being called directly from SSC_Chat. If the hook never fires —
 * or fires with the wrong args — every channel add-on silently breaks.
 */
require __DIR__ . '/lib/bootstrap.php';

echo "=== Message lifecycle hooks ===\n";

ssc_test_reset_tables();

// Stash hook invocations.
$GLOBALS['ssc_hook_calls'] = array();
$capture = function() {
    $args = func_get_args();
    $GLOBALS['ssc_hook_calls'][ current_action() ][] = $args;
};

foreach ( array(
    'ssc_visitor_message_sent',
    'ssc_admin_reply_sent',
    'ssc_bot_message_sent',
    'ssc_conversation_status_changed',
    'ssc_conversation_created',
) as $hook ) {
    add_action( $hook, $capture, 10, 4 );
}

// --- Visitor message --------------------------------------------------

// Need a valid visitor_hash; SSC_Session is what the REST layer uses.
$visitor_hash = bin2hex( random_bytes( 32 ) );

// Seed the cookie so SSC_Session can find it (the helper falls back to cookie).
$_COOKIE['ssc_visitor_hash'] = $visitor_hash;

$result = SSC_Chat::send_visitor_message( 'Hello from test', $visitor_hash, 'http://example.com/test' );
ssc_assert_true( is_array( $result ) && isset( $result['message_id'] ), 'send_visitor_message returns an array with message_id' );

$visitor_calls = $GLOBALS['ssc_hook_calls']['ssc_visitor_message_sent'] ?? array();
ssc_assert_eq( 1, count( $visitor_calls ), 'ssc_visitor_message_sent fired exactly once' );

if ( count( $visitor_calls ) >= 1 ) {
    list( $message_id, $conversation, $message_text, $participant ) = $visitor_calls[0];
    ssc_assert_eq( $result['message_id'], $message_id, 'Hook arg #1 = message_id' );
    ssc_assert_true( is_object( $conversation ) && isset( $conversation->id ), 'Hook arg #2 is the conversation object' );
    ssc_assert_eq( 'Hello from test', $message_text, 'Hook arg #3 = message text' );
    ssc_assert_true( is_object( $participant ) && isset( $participant->id ), 'Hook arg #4 is the participant object' );
    ssc_assert_eq( 'waiting', $conversation->status, 'Conversation status is "waiting" after visitor message' );
}

// status_changed should have fired (active → waiting).
$status_calls = $GLOBALS['ssc_hook_calls']['ssc_conversation_status_changed'] ?? array();
ssc_assert_true( count( $status_calls ) >= 1, 'ssc_conversation_status_changed fired on visitor message' );

// --- Admin reply ------------------------------------------------------

$GLOBALS['ssc_hook_calls']['ssc_admin_reply_sent'] = array();

// Need an admin user. Create a temporary one if none exists with manage_options.
$admin_user = get_user_by( 'login', 'ssc_test_admin' );
if ( ! $admin_user ) {
    $admin_uid  = wp_insert_user( array(
        'user_login' => 'ssc_test_admin',
        'user_pass'  => wp_generate_password(),
        'role'       => 'administrator',
    ) );
} else {
    $admin_uid = $admin_user->ID;
}

$conv_id = $result['conversation_id'];
$reply   = SSC_Chat::send_admin_reply( $conv_id, 'Thanks!', $admin_uid );
ssc_assert_true( is_array( $reply ) && isset( $reply['message_id'] ), 'send_admin_reply returns an array' );

$admin_calls = $GLOBALS['ssc_hook_calls']['ssc_admin_reply_sent'];
ssc_assert_eq( 1, count( $admin_calls ), 'ssc_admin_reply_sent fired exactly once' );

if ( count( $admin_calls ) >= 1 ) {
    list( $msg_id, $conv_obj, $reply_text, $reply_admin_uid ) = $admin_calls[0];
    ssc_assert_eq( $reply['message_id'], $msg_id, 'Admin hook arg #1 = message_id' );
    ssc_assert_true( is_object( $conv_obj ) && $conv_obj->id === $conv_id, 'Admin hook arg #2 = conversation' );
    ssc_assert_eq( 'Thanks!', $reply_text, 'Admin hook arg #3 = reply text' );
    ssc_assert_eq( (int) $admin_uid, (int) $reply_admin_uid, 'Admin hook arg #4 = admin user_id' );
    ssc_assert_eq( 'active', $conv_obj->status, 'Conversation status flipped back to "active"' );
}

// --- Bot message -------------------------------------------------------

$GLOBALS['ssc_hook_calls']['ssc_bot_message_sent'] = array();
$bot_result = SSC_Chat::send_bot_message( $conv_id, 'I am a bot.', 'canned_response' );
ssc_assert_true( is_array( $bot_result ), 'send_bot_message returns an array' );

$bot_calls = $GLOBALS['ssc_hook_calls']['ssc_bot_message_sent'];
ssc_assert_eq( 1, count( $bot_calls ), 'ssc_bot_message_sent fired exactly once' );

if ( count( $bot_calls ) >= 1 ) {
    ssc_assert_eq( 'canned_response', $bot_calls[0][3], 'Bot hook arg #4 = message_type ("canned_response")' );
}

ssc_test_summary();
