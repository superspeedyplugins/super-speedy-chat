<?php
/**
 * Regression: SSC_Chat::external_inbound() + get_or_create_external_conversation().
 *
 * Public helpers that channel add-ons depend on. If these break, every
 * inbound-from-channel flow (WhatsApp, Telegram, Slack) breaks.
 */
require __DIR__ . '/lib/bootstrap.php';

echo "=== external_inbound + get_or_create_external_conversation ===\n";

ssc_test_reset_tables();

// --- get_or_create_external_conversation -------------------------------

$first = SSC_Chat::get_or_create_external_conversation( array(
    'channel'      => 'whatsapp',
    'external_id'  => '+447111111111',
    'display_name' => 'Test Visitor',
    'metadata'     => array( 'wa_id' => '447111111111' ),
) );
ssc_assert_true( is_object( $first ) && isset( $first->id ), 'First call creates a new conversation' );
ssc_assert_eq( 'whatsapp', $first->channel, 'New conversation has channel="whatsapp"' );
ssc_assert_eq( 'waiting', $first->status, 'New external conversation starts in "waiting"' );
ssc_assert_eq( 'Test Visitor', $first->visitor_name, 'visitor_name is set from display_name' );

// Second call with same channel + external_id must return the SAME row.
$second = SSC_Chat::get_or_create_external_conversation( array(
    'channel'      => 'whatsapp',
    'external_id'  => '+447111111111',
    'display_name' => 'Test Visitor (rename ignored)',
) );
ssc_assert_eq( (int) $first->id, (int) $second->id, 'Same channel+external_id returns the same conversation' );

// Different external_id → different conversation.
$other = SSC_Chat::get_or_create_external_conversation( array(
    'channel'      => 'whatsapp',
    'external_id'  => '+447222222222',
    'display_name' => 'Other Visitor',
) );
ssc_assert_true( (int) $other->id !== (int) $first->id, 'Different external_id creates a different conversation' );

// Missing required args → null.
$bad = SSC_Chat::get_or_create_external_conversation( array(
    'channel'     => '',
    'external_id' => '+447111111111',
) );
ssc_assert_eq( null, $bad, 'Empty channel returns null' );

// --- external_inbound ---------------------------------------------------

$GLOBALS['ssc_inbound_visitor'] = 0;
$GLOBALS['ssc_inbound_status_changed'] = 0;
add_action( 'ssc_visitor_message_sent', function() {
    $GLOBALS['ssc_inbound_visitor']++;
}, 10, 4 );
add_action( 'ssc_conversation_status_changed', function() {
    $GLOBALS['ssc_inbound_status_changed']++;
}, 10, 3 );

$msg_id = SSC_Chat::external_inbound( array(
    'conversation_id' => $first->id,
    'channel'         => 'whatsapp',
    'author_name'     => 'Test Visitor',
    'author_type'     => 'visitor',
    'message'         => 'Hello over WhatsApp',
) );
ssc_assert_true( is_numeric( $msg_id ) && $msg_id > 0, 'external_inbound returns a message_id for a valid call' );
ssc_assert_eq( 1, $GLOBALS['ssc_inbound_visitor'], 'external_inbound fires ssc_visitor_message_sent for author_type=visitor' );

// Verify the message landed in the DB with the right participant type.
global $wpdb;
$msg_row = $wpdb->get_row( $wpdb->prepare(
    "SELECT m.*, p.participant_type, p.display_name
     FROM {$wpdb->prefix}ssc_messages m
     INNER JOIN {$wpdb->prefix}ssc_participants p ON m.participant_id = p.id
     WHERE m.id = %d",
    $msg_id
) );
ssc_assert_true( $msg_row !== null, 'Message row exists in DB' );
if ( $msg_row ) {
    ssc_assert_eq( 'Hello over WhatsApp', $msg_row->message, 'Message text stored correctly' );
    ssc_assert_eq( 'visitor', $msg_row->participant_type, 'Participant type is "visitor"' );
    ssc_assert_eq( 'Test Visitor', $msg_row->display_name, 'Participant display name preserved' );
}

// admin author → fires admin hook, status flips to active.
$GLOBALS['ssc_inbound_admin'] = 0;
add_action( 'ssc_admin_reply_sent', function() {
    $GLOBALS['ssc_inbound_admin']++;
}, 10, 4 );

$admin_msg_id = SSC_Chat::external_inbound( array(
    'conversation_id' => $first->id,
    'channel'         => 'whatsapp',
    'author_name'     => 'Some Admin',
    'author_type'     => 'admin',
    'message'         => 'Replying from outside wp-admin',
) );
ssc_assert_true( is_numeric( $admin_msg_id ) && $admin_msg_id > 0, 'external_inbound returns id for admin-typed call' );
ssc_assert_eq( 1, $GLOBALS['ssc_inbound_admin'], 'external_inbound fires ssc_admin_reply_sent for author_type=admin' );

$conv_after = SSC_DB::get_conversation( $first->id );
ssc_assert_eq( 'active', $conv_after->status, 'Conversation flipped to "active" after admin inbound' );

// Empty message → null, no DB write.
$null = SSC_Chat::external_inbound( array(
    'conversation_id' => $first->id,
    'channel'         => 'whatsapp',
    'author_name'     => 'X',
    'author_type'     => 'visitor',
    'message'         => '',
) );
ssc_assert_eq( null, $null, 'Empty message returns null' );

ssc_test_summary();
