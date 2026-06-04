<?php
/**
 * Functional + authorization: the admin REST endpoints.
 *
 * Proves (a) the permission_callback actually blocks non-admins — the most
 * load-bearing untested control — and (b) the reply / list / detail / close
 * endpoints work for an administrator.
 *
 * Covers: check_admin_permission gating, POST /admin/reply, GET
 * /admin/conversations, GET /admin/conversation/{id}, POST /admin/close/{id}.
 */
require __DIR__ . '/lib/bootstrap.php';

echo "=== Admin REST flow (permission gating + reply/list/detail/close) ===\n";

ssc_test_reset_tables();
rest_get_server();

$_SERVER['REMOTE_ADDR'] = '198.51.100.8';

// --- Seed a conversation with a visitor message (service layer) -------

$visitor_hash = bin2hex( random_bytes( 32 ) );
$_COOKIE['ssc_visitor_hash'] = $visitor_hash;
wp_set_current_user( 0 );

$seed = SSC_Chat::send_visitor_message( 'I need help with billing', $visitor_hash, 'http://example.com/account' );
$conversation_id = (int) $seed['conversation_id'];
ssc_assert_true( $conversation_id > 0, 'Seed conversation created' );

// --- Users for the authorization tests -------------------------------

$admin_user = get_user_by( 'login', 'ssc_test_admin' );
$admin_uid  = $admin_user ? $admin_user->ID : wp_insert_user( array(
    'user_login' => 'ssc_test_admin',
    'user_pass'  => wp_generate_password(),
    'role'       => 'administrator',
) );

$sub_user = get_user_by( 'login', 'ssc_test_subscriber' );
$sub_uid  = $sub_user ? $sub_user->ID : wp_insert_user( array(
    'user_login' => 'ssc_test_subscriber',
    'user_pass'  => wp_generate_password(),
    'role'       => 'subscriber',
) );

// --- AuthZ: logged-out visitor is blocked ----------------------------

wp_set_current_user( 0 );
$req = new WP_REST_Request( 'GET', '/ssc/v1/admin/conversations' );
$res = rest_do_request( $req );
ssc_assert_eq( 401, $res->get_status(), 'Anonymous user is blocked from /admin/conversations (401)' );

// --- AuthZ: subscriber is blocked ------------------------------------

wp_set_current_user( $sub_uid );
$req = new WP_REST_Request( 'GET', '/ssc/v1/admin/conversations' );
$res = rest_do_request( $req );
ssc_assert_eq( 403, $res->get_status(), 'Subscriber is blocked from /admin/conversations (403)' );

// --- AuthZ: subscriber cannot reply ----------------------------------

$req = new WP_REST_Request( 'POST', '/ssc/v1/admin/reply' );
$req->set_param( 'conversation_id', $conversation_id );
$req->set_param( 'message', 'Sneaky reply' );
$res = rest_do_request( $req );
ssc_assert_true( $res->get_status() >= 400, 'Subscriber is blocked from POST /admin/reply' );

// --- Admin: list conversations ---------------------------------------

wp_set_current_user( $admin_uid );
$req = new WP_REST_Request( 'GET', '/ssc/v1/admin/conversations' );
$res = rest_do_request( $req );
ssc_assert_eq( 200, $res->get_status(), 'Admin can GET /admin/conversations (200)' );

$list = $res->get_data();
ssc_assert_true( isset( $list['items'] ) && count( $list['items'] ) >= 1, '/admin/conversations returns our seeded conversation' );
ssc_assert_true( $list['total'] >= 1, '/admin/conversations reports a non-zero total' );

// --- Admin: reply -----------------------------------------------------

$req = new WP_REST_Request( 'POST', '/ssc/v1/admin/reply' );
$req->set_param( 'conversation_id', $conversation_id );
$req->set_param( 'message', 'Happy to help with billing!' );
$res = rest_do_request( $req );
ssc_assert_eq( 200, $res->get_status(), 'Admin POST /admin/reply returns 200' );

$reply = $res->get_data();
ssc_assert_true( ! empty( $reply['message_id'] ), '/admin/reply returns a message_id' );

// Replying flips the conversation back to "active".
$conv = SSC_DB::get_conversation( $conversation_id );
ssc_assert_eq( 'active', $conv->status, 'Conversation is "active" after admin reply' );

// --- Admin: reply missing conversation_id ----------------------------

$req = new WP_REST_Request( 'POST', '/ssc/v1/admin/reply' );
$req->set_param( 'message', 'No id here' );
$res = rest_do_request( $req );
ssc_assert_true( $res->is_error() || $res->get_status() >= 400, '/admin/reply rejects a missing conversation_id' );

// --- Admin: conversation detail shows both messages ------------------

$req = new WP_REST_Request( 'GET', '/ssc/v1/admin/conversation/' . $conversation_id );
$res = rest_do_request( $req );
ssc_assert_eq( 200, $res->get_status(), 'Admin GET /admin/conversation/{id} returns 200' );

$detail = $res->get_data();
ssc_assert_true( isset( $detail['conversation']->id ) && (int) $detail['conversation']->id === $conversation_id, '/admin/conversation/{id} returns the right conversation' );
ssc_assert_true( count( $detail['messages'] ) >= 2, '/admin/conversation/{id} returns visitor + admin messages' );

$texts = array_map( function ( $m ) {
    return is_object( $m ) ? $m->message : $m['message'];
}, $detail['messages'] );
ssc_assert_contains( 'I need help with billing', $texts, 'Detail includes the visitor message' );
ssc_assert_contains( 'Happy to help with billing!', $texts, 'Detail includes the admin reply' );

// --- Admin: 404 for a non-existent conversation ----------------------

$req = new WP_REST_Request( 'GET', '/ssc/v1/admin/conversation/99999999' );
$res = rest_do_request( $req );
ssc_assert_eq( 404, $res->get_status(), '/admin/conversation/{id} returns 404 for unknown id' );

// --- Admin: close -----------------------------------------------------

$req = new WP_REST_Request( 'POST', '/ssc/v1/admin/close/' . $conversation_id );
$res = rest_do_request( $req );
ssc_assert_eq( 200, $res->get_status(), 'Admin POST /admin/close/{id} returns 200' );

$conv = SSC_DB::get_conversation( $conversation_id );
ssc_assert_eq( 'closed', $conv->status, 'Conversation is "closed" after /admin/close' );

ssc_test_summary();
