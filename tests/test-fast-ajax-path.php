<?php
/**
 * Functional: the Ultra-Ajax fast path (SSC_REST::fast_*).
 *
 * This is the default production code path when the mu-plugin is active, and was
 * previously untested. The mu-plugin wrapper does routing/rate-limiting; the
 * data work lives in these static handlers, so we exercise them directly:
 * session → send → poll round-trip, plus honeypot / empty / no-session guards.
 *
 * Covers: SSC_REST::fast_session(), ::fast_send(), ::fast_poll().
 */
require __DIR__ . '/lib/bootstrap.php';

echo "=== Ultra-Ajax fast path (fast_session / fast_send / fast_poll) ===\n";

ssc_test_reset_tables();

$_SERVER['REMOTE_ADDR']     = '198.51.100.9';
$_SERVER['HTTP_USER_AGENT'] = 'SSC-Test/1.0';

// Known cookie hash so fast_session reuses it (no setcookie-after-output).
$visitor_hash = bin2hex( random_bytes( 32 ) );
$_COOKIE['ssc_visitor_hash'] = $visitor_hash;
wp_set_current_user( 0 );

// --- fast_session: creates a conversation ----------------------------

$session = SSC_REST::fast_session();
ssc_assert_true( is_array( $session ) && ! empty( $session['conversation_id'] ), 'fast_session returns a conversation_id' );
ssc_assert_eq( $visitor_hash, $session['visitor_hash'], 'fast_session echoes our visitor_hash' );
ssc_assert_eq( 'active', $session['status'], 'fast_session starts a new conversation as "active"' );
ssc_assert_eq( 0, count( $session['messages'] ), 'fast_session has no messages yet' );

$conversation_id = (int) $session['conversation_id'];

// --- fast_send: honeypot is silently dropped -------------------------

$hp = SSC_REST::fast_send( array( 'message' => 'spam', 'website_url' => 'http://spam.example' ) );
ssc_assert_eq( 0, (int) $hp['message_id'], 'fast_send drops honeypot submissions (message_id 0)' );
ssc_assert_eq( 0, (int) $hp['conversation_id'], 'fast_send honeypot returns conversation_id 0' );

// --- fast_send: empty message is rejected ----------------------------

$empty = SSC_REST::fast_send( array( 'message' => '   ' ) );
ssc_assert_true( isset( $empty['error'] ), 'fast_send rejects an empty message' );

// --- fast_send: valid message ----------------------------------------

$send = SSC_REST::fast_send( array( 'message' => 'Fast hello', 'page_url' => 'http://example.com/x' ) );
ssc_assert_true( ! empty( $send['message_id'] ), 'fast_send returns a message_id' );
ssc_assert_eq( $conversation_id, (int) $send['conversation_id'], 'fast_send reuses the session conversation' );

// Sending parks the conversation as "waiting".
$conv = SSC_DB::get_conversation( $conversation_id );
ssc_assert_eq( 'waiting', $conv->status, 'Conversation is "waiting" after fast_send' );

// --- fast_poll: round-trips the message ------------------------------

$poll = SSC_REST::fast_poll( array( 'since_id' => 0 ) );
ssc_assert_eq( $conversation_id, (int) $poll['conversation_id'], 'fast_poll resolves the right conversation' );
ssc_assert_true( count( $poll['messages'] ) >= 1, 'fast_poll returns at least one message' );

$found = false;
$last_id = 0;
foreach ( $poll['messages'] as $m ) {
    if ( $m->message === 'Fast hello' ) {
        $found = true;
    }
    if ( (int) $m->id > $last_id ) {
        $last_id = (int) $m->id;
    }
}
ssc_assert_true( $found, 'fast_poll round-trips the exact message that was sent' );

// --- fast_poll: since_id = last returns nothing new ------------------

$poll2 = SSC_REST::fast_poll( array( 'since_id' => $last_id ) );
ssc_assert_eq( 0, count( $poll2['messages'] ), 'fast_poll with since_id=last returns no duplicates' );

// --- No session cookie: send + poll degrade gracefully ---------------

unset( $_COOKIE['ssc_visitor_hash'] );

$no_sess = SSC_REST::fast_send( array( 'message' => 'orphan' ) );
ssc_assert_true( isset( $no_sess['error'] ), 'fast_send without a session cookie returns an error' );

$no_poll = SSC_REST::fast_poll( array( 'since_id' => 0 ) );
ssc_assert_eq( 0, (int) $no_poll['conversation_id'], 'fast_poll without a session cookie returns conversation_id 0' );
ssc_assert_eq( 0, count( $no_poll['messages'] ), 'fast_poll without a session cookie returns no messages' );

// Restore cookie.
$_COOKIE['ssc_visitor_hash'] = $visitor_hash;

ssc_test_summary();
