<?php
/**
 * Functional: the visitor REST endpoints actually work end-to-end.
 *
 * test-message-lifecycle-hooks.php exercises SSC_Chat::* directly; this drives
 * the real REST layer via rest_do_request() — the plumbing a browser hits —
 * and proves the send → poll round-trip returns the message back.
 *
 * Covers: POST /session, POST /send (+ honeypot + empty), GET /poll, POST /email.
 */
require __DIR__ . '/lib/bootstrap.php';

echo "=== Visitor REST flow (session / send / poll / email) ===\n";

ssc_test_reset_tables();

// Ensure all SSC routes are registered (fires rest_api_init).
rest_get_server();

// Deterministic, fresh rate-limit bucket for this run.
$_SERVER['REMOTE_ADDR'] = '198.51.100.7';
foreach ( array( 'session', 'send', 'auto_reply' ) as $a ) {
    delete_transient( 'ssc_rate_' . $a . '_' . md5( $_SERVER['REMOTE_ADDR'] ) );
}

// Anonymous visitor with a known cookie hash (avoids setcookie-after-output).
wp_set_current_user( 0 );
$visitor_hash = bin2hex( random_bytes( 32 ) );
$_COOKIE['ssc_visitor_hash'] = $visitor_hash;

// --- POST /session ----------------------------------------------------

$req = new WP_REST_Request( 'POST', '/ssc/v1/session' );
$res = rest_do_request( $req );
ssc_assert_eq( 200, $res->get_status(), 'POST /session returns 200' );

$data = $res->get_data();
ssc_assert_true( is_array( $data ) && ! empty( $data['conversation_id'] ), '/session returns a conversation_id' );
ssc_assert_eq( $visitor_hash, $data['visitor_hash'] ?? null, '/session echoes our visitor_hash' );

$conversation_id = (int) $data['conversation_id'];

// --- POST /send (valid) ----------------------------------------------

$req = new WP_REST_Request( 'POST', '/ssc/v1/send' );
$req->set_param( 'message', 'Hello over REST' );
$req->set_param( 'page_url', 'http://example.com/pricing' );
$res = rest_do_request( $req );
ssc_assert_eq( 200, $res->get_status(), 'POST /send returns 200' );

$send = $res->get_data();
ssc_assert_true( is_array( $send ) && ! empty( $send['message_id'] ), '/send returns a message_id' );
ssc_assert_eq( $conversation_id, (int) ( $send['conversation_id'] ?? 0 ), '/send reuses the visitor conversation' );

// --- POST /send (honeypot) -------------------------------------------

$req = new WP_REST_Request( 'POST', '/ssc/v1/send' );
$req->set_param( 'message', 'I am a bot' );
$req->set_param( 'website_url', 'http://spam.example' ); // honeypot filled
$res = rest_do_request( $req );
$hp  = $res->get_data();
ssc_assert_eq( 0, (int) ( $hp['message_id'] ?? -1 ), '/send silently drops honeypot submissions (message_id 0)' );

// --- POST /send (empty) ----------------------------------------------

$req = new WP_REST_Request( 'POST', '/ssc/v1/send' );
$req->set_param( 'message', '   ' );
$res = rest_do_request( $req );
ssc_assert_true( $res->is_error() || $res->get_status() >= 400, '/send rejects an empty message' );

// --- GET /poll (round-trip) ------------------------------------------

$req = new WP_REST_Request( 'GET', '/ssc/v1/poll' );
$req->set_param( 'since_id', 0 );
$res = rest_do_request( $req );
ssc_assert_eq( 200, $res->get_status(), 'GET /poll returns 200' );

$poll = $res->get_data();
ssc_assert_true( is_array( $poll['messages'] ) && count( $poll['messages'] ) >= 1, '/poll returns at least one message' );

$found = false;
foreach ( $poll['messages'] as $m ) {
    $text = is_object( $m ) ? $m->message : $m['message'];
    if ( $text === 'Hello over REST' ) {
        $found = true;
    }
}
ssc_assert_true( $found, '/poll round-trips the exact message the visitor sent' );

// --- GET /poll with since_id = last id returns nothing new -----------

$last_id = 0;
foreach ( $poll['messages'] as $m ) {
    $id = (int) ( is_object( $m ) ? $m->id : $m['id'] );
    if ( $id > $last_id ) {
        $last_id = $id;
    }
}
$req = new WP_REST_Request( 'GET', '/ssc/v1/poll' );
$req->set_param( 'since_id', $last_id );
$res  = rest_do_request( $req );
$poll = $res->get_data();
ssc_assert_eq( 0, count( $poll['messages'] ), '/poll with since_id=last returns no duplicate messages' );

// --- POST /email ------------------------------------------------------

$req = new WP_REST_Request( 'POST', '/ssc/v1/email' );
$req->set_param( 'email', 'visitor@example.com' );
$res = rest_do_request( $req );
ssc_assert_eq( 200, $res->get_status(), 'POST /email accepts a valid address' );

$conv = SSC_DB::get_conversation( $conversation_id );
ssc_assert_eq( 'visitor@example.com', $conv->visitor_email, '/email persists the address on the conversation' );

// --- POST /email (invalid) -------------------------------------------

$req = new WP_REST_Request( 'POST', '/ssc/v1/email' );
$req->set_param( 'email', 'not-an-email' );
$res = rest_do_request( $req );
ssc_assert_true( $res->is_error() || $res->get_status() >= 400, '/email rejects an invalid address' );

// --- A visitor with no session cookie is refused ---------------------

unset( $_COOKIE['ssc_visitor_hash'] );
$req = new WP_REST_Request( 'GET', '/ssc/v1/poll' );
$res = rest_do_request( $req );
ssc_assert_eq( 403, $res->get_status(), '/poll without a session cookie returns 403' );

// Restore cookie for any later use.
$_COOKIE['ssc_visitor_hash'] = $visitor_hash;

ssc_test_summary();
