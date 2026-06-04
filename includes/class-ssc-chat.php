<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

if ( ! class_exists( 'SSC_Chat' ) ) {

    class SSC_Chat {

        /**
         * Handle a visitor sending a message.
         *
         * Creates session/conversation/participant as needed, inserts the message,
         * and returns the result for the REST response.
         *
         * @param string $message      The message text.
         * @param string $visitor_hash The visitor's cookie hash.
         * @param string $page_url     The page the visitor is on.
         * @return array|WP_Error
         */
        public static function send_visitor_message( $message, $visitor_hash, $page_url = '' ) {
            $message = sanitize_text_field( $message );

            if ( empty( $message ) ) {
                return new WP_Error( 'empty_message', __( 'Message cannot be empty.', 'super-speedy-chat' ), array( 'status' => 400 ) );
            }

            $max_length = absint( SSC_Settings::get_option( 'ssc_max_message_length', 500 ) );
            if ( $max_length > 0 && mb_strlen( $message ) > $max_length ) {
                $message = mb_substr( $message, 0, $max_length );
            }

            // Get or create conversation.
            $conversation = SSC_Session::get_or_create_conversation( $visitor_hash );
            if ( ! $conversation ) {
                return new WP_Error( 'conversation_error', __( 'Could not create conversation.', 'super-speedy-chat' ), array( 'status' => 500 ) );
            }

            // Update last page URL.
            if ( ! empty( $page_url ) ) {
                SSC_DB::update_conversation( $conversation->id, array(
                    'last_page_url' => esc_url_raw( $page_url ),
                ) );
            }

            // Get or create visitor participant.
            $user_id     = is_user_logged_in() ? get_current_user_id() : null;
            $participant = SSC_Session::get_or_create_participant( $conversation->id, $visitor_hash, $user_id );

            if ( ! $participant ) {
                return new WP_Error( 'participant_error', __( 'Could not create participant.', 'super-speedy-chat' ), array( 'status' => 500 ) );
            }

            // Insert the message.
            $message_id = SSC_DB::add_message( array(
                'conversation_id' => $conversation->id,
                'participant_id'  => $participant->id,
                'message'         => $message,
                'message_type'    => 'text',
            ) );

            if ( ! $message_id ) {
                return new WP_Error( 'message_error', __( 'Could not save message.', 'super-speedy-chat' ), array( 'status' => 500 ) );
            }

            // Set conversation to 'waiting' (waiting for admin reply).
            $old_status = $conversation->status;
            if ( $old_status !== 'waiting' ) {
                SSC_DB::update_conversation( $conversation->id, array(
                    'status' => 'waiting',
                ) );
                do_action( 'ssc_conversation_status_changed', $conversation->id, 'waiting', $old_status );
            }

            // Send admin email notification on first message.
            $msg_count = self::get_visitor_message_count( $conversation->id );
            if ( $msg_count === 1 ) {
                SSC_Email::notify_admin_new_conversation( $conversation );
            }

            // Fire lifecycle hook — channel add-ons (Discord, WhatsApp, …) listen.
            $fresh = SSC_DB::get_conversation( $conversation->id );
            do_action( 'ssc_visitor_message_sent', $message_id, $fresh, $message, $participant );

            return array(
                'message_id'      => $message_id,
                'conversation_id' => $conversation->id,
            );
        }

        /**
         * Handle an admin sending a reply.
         *
         * @param int    $conversation_id The conversation to reply to.
         * @param string $message         The reply text.
         * @param int    $admin_user_id   The WP user ID of the admin.
         * @return array|WP_Error
         */
        public static function send_admin_reply( $conversation_id, $message, $admin_user_id ) {
            $message = sanitize_text_field( $message );

            if ( empty( $message ) ) {
                return new WP_Error( 'empty_message', __( 'Message cannot be empty.', 'super-speedy-chat' ), array( 'status' => 400 ) );
            }

            $conversation = SSC_DB::get_conversation( $conversation_id );
            if ( ! $conversation ) {
                return new WP_Error( 'not_found', __( 'Conversation not found.', 'super-speedy-chat' ), array( 'status' => 404 ) );
            }

            // Get or create admin participant.
            $participant = SSC_DB::get_participant( $conversation_id, $admin_user_id, 'admin' );
            if ( ! $participant ) {
                $admin_name = SSC_Admin::get_admin_chat_name( $admin_user_id );

                $participant_id = SSC_DB::add_participant( array(
                    'conversation_id'  => $conversation_id,
                    'participant_type' => 'admin',
                    'user_id'          => $admin_user_id,
                    'display_name'     => $admin_name,
                ) );

                $participant = (object) array( 'id' => $participant_id );
            }

            // Insert the message.
            $message_id = SSC_DB::add_message( array(
                'conversation_id' => $conversation_id,
                'participant_id'  => $participant->id,
                'message'         => $message,
                'message_type'    => 'text',
            ) );

            if ( ! $message_id ) {
                return new WP_Error( 'message_error', __( 'Could not save message.', 'super-speedy-chat' ), array( 'status' => 500 ) );
            }

            // Set conversation back to 'active'.
            $old_status = $conversation->status;
            if ( $old_status !== 'active' ) {
                SSC_DB::update_conversation( $conversation_id, array(
                    'status' => 'active',
                ) );
                do_action( 'ssc_conversation_status_changed', $conversation_id, 'active', $old_status );
            }

            // Email the visitor if they have an email and are offline.
            if ( ! empty( $conversation->visitor_email ) ) {
                SSC_Email::notify_visitor_reply( $conversation, $message );
            }

            // Fire lifecycle hook — channel add-ons relay this to their channel.
            $fresh = SSC_DB::get_conversation( $conversation_id );
            do_action( 'ssc_admin_reply_sent', $message_id, $fresh, $message, $admin_user_id );

            return array(
                'message_id'      => $message_id,
                'conversation_id' => $conversation_id,
            );
        }

        /**
         * Send a bot/auto-reply message to a conversation.
         *
         * @param int    $conversation_id Conversation ID.
         * @param string $message         Message text.
         * @param string $message_type    Message type (default 'auto_reply').
         * @return array|WP_Error
         */
        public static function send_bot_message( $conversation_id, $message, $message_type = 'auto_reply' ) {
            $message = sanitize_text_field( $message );
            if ( empty( $message ) ) {
                return new WP_Error( 'empty_message', __( 'Message cannot be empty.', 'super-speedy-chat' ), array( 'status' => 400 ) );
            }

            $conversation = SSC_DB::get_conversation( $conversation_id );
            if ( ! $conversation ) {
                return new WP_Error( 'not_found', __( 'Conversation not found.', 'super-speedy-chat' ), array( 'status' => 404 ) );
            }

            // Get or create bot participant.
            $participant = SSC_DB::get_participant_by_type( $conversation_id, 'bot' );
            if ( ! $participant ) {
                $bot_name = SSC_Settings::get_option( 'ssc_shared_display_name', get_bloginfo( 'name' ) );

                $participant_id = SSC_DB::add_participant( array(
                    'conversation_id'  => $conversation_id,
                    'participant_type' => 'bot',
                    'display_name'     => $bot_name,
                ) );

                $participant = (object) array( 'id' => $participant_id );
            }

            $message_id = SSC_DB::add_message( array(
                'conversation_id' => $conversation_id,
                'participant_id'  => $participant->id,
                'message'         => $message,
                'message_type'    => $message_type,
            ) );

            if ( ! $message_id ) {
                return new WP_Error( 'message_error', __( 'Could not save message.', 'super-speedy-chat' ), array( 'status' => 500 ) );
            }

            do_action( 'ssc_bot_message_sent', $message_id, $conversation, $message, $message_type );

            return array(
                'message_id'      => $message_id,
                'conversation_id' => $conversation_id,
            );
        }

        /**
         * Poll for new messages in a conversation.
         *
         * @param int $conversation_id Conversation ID.
         * @param int $since_id        Return messages after this ID.
         * @return array
         */
        public static function poll_messages( $conversation_id, $since_id = 0 ) {
            $messages = SSC_DB::get_messages( $conversation_id, $since_id, 50 );

            return array(
                'messages'        => $messages ? $messages : array(),
                'conversation_id' => $conversation_id,
            );
        }

        /**
         * Save a visitor's email address on their conversation.
         *
         * @param int    $conversation_id Conversation ID.
         * @param string $email           Email address.
         * @return bool|WP_Error
         */
        public static function save_visitor_email( $conversation_id, $email ) {
            $email = sanitize_email( $email );
            if ( ! is_email( $email ) ) {
                return new WP_Error( 'invalid_email', __( 'Invalid email address.', 'super-speedy-chat' ), array( 'status' => 400 ) );
            }

            SSC_DB::update_conversation( $conversation_id, array(
                'visitor_email' => $email,
            ) );

            // Notify the admin with the visitor's address as Reply-To, so they can
            // reply by email and have it reach the visitor directly.
            $conversation = SSC_DB::get_conversation( $conversation_id );
            if ( $conversation ) {
                SSC_Email::notify_admin_visitor_email( $conversation );
            }

            return true;
        }

        /**
         * Count visitor messages in a conversation.
         *
         * @param int $conversation_id Conversation ID.
         * @return int
         */
        public static function get_visitor_message_count( $conversation_id ) {
            global $wpdb;
            $messages_table     = $wpdb->prefix . 'ssc_messages';
            $participants_table = $wpdb->prefix . 'ssc_participants';

            return (int) $wpdb->get_var(
                $wpdb->prepare(
                    "SELECT COUNT(*) FROM {$messages_table} m
                     INNER JOIN {$participants_table} p ON m.participant_id = p.id
                     WHERE m.conversation_id = %d AND p.participant_type = 'visitor'",
                    $conversation_id
                )
            );
        }

        /**
         * Whether a conversation has already received an LLM/canned auto-reply.
         *
         * Used to cap paid LLM classification at one call per conversation, so the
         * unauthenticated auto-reply endpoint can't be looped for cost abuse.
         *
         * @param int $conversation_id Conversation ID.
         * @return bool
         */
        public static function has_auto_reply( $conversation_id ) {
            global $wpdb;
            $messages_table = $wpdb->prefix . 'ssc_messages';

            $count = (int) $wpdb->get_var(
                $wpdb->prepare(
                    "SELECT COUNT(*) FROM {$messages_table}
                     WHERE conversation_id = %d AND message_type IN ('auto_reply','canned_response')",
                    $conversation_id
                )
            );

            return $count > 0;
        }

        /**
         * Get the last message preview for a conversation.
         *
         * @param int $conversation_id Conversation ID.
         * @return string
         */
        public static function get_last_message_preview( $conversation_id ) {
            global $wpdb;
            $table = $wpdb->prefix . 'ssc_messages';

            $message = $wpdb->get_var(
                $wpdb->prepare(
                    "SELECT message FROM {$table} WHERE conversation_id = %d ORDER BY id DESC LIMIT 1",
                    $conversation_id
                )
            );

            if ( ! $message ) {
                return '';
            }

            return mb_strlen( $message ) > 80 ? mb_substr( $message, 0, 80 ) . '...' : $message;
        }

        // -------------------------------------------------------------------
        // Add-on extension helpers
        //
        // These are public so channel add-ons (WhatsApp, Telegram, etc.) can
        // route inbound messages into core without duplicating participant /
        // message logic. See .docs/addons-system-plan.md §4.2.
        // -------------------------------------------------------------------

        /**
         * Get or create a conversation owned by an external channel.
         *
         * Used when an external channel (WhatsApp, Telegram, etc.) initiates a
         * new conversation — there's no browser-cookie visitor_hash to bind to,
         * so the channel + external_id pair becomes the identity.
         *
         * Fires `ssc_conversation_created` when a new row is created.
         */
        public static function get_or_create_external_conversation( $args ) {
            global $wpdb;
            $defaults = array(
                'channel'      => '',
                'external_id'  => '',
                'display_name' => 'Visitor',
                'metadata'     => array(),
            );
            $args = wp_parse_args( $args, $defaults );

            if ( empty( $args['channel'] ) || empty( $args['external_id'] ) ) {
                return null;
            }

            $channel      = sanitize_key( $args['channel'] );
            $conv_table   = $wpdb->prefix . 'ssc_conversations';
            // visitor_hash is VARCHAR(64). sha256 hex is 64 chars; the conversation's
            // `channel` column carries the "external vs cookie-based" discriminator.
            $visitor_hash = hash( 'sha256', 'ext:' . $channel . ':' . $args['external_id'] );

            $existing = $wpdb->get_row( $wpdb->prepare(
                "SELECT * FROM {$conv_table}
                 WHERE visitor_hash = %s AND channel = %s AND status IN ('active','waiting')
                 ORDER BY last_message_at DESC LIMIT 1",
                $visitor_hash,
                $channel
            ) );

            if ( $existing ) {
                return $existing;
            }

            $now      = current_time( 'mysql' );
            $metadata = is_array( $args['metadata'] ) && ! empty( $args['metadata'] )
                ? wp_json_encode( $args['metadata'] )
                : null;

            $conversation_id = SSC_DB::create_conversation( array(
                'visitor_hash'    => $visitor_hash,
                'visitor_name'    => sanitize_text_field( $args['display_name'] ),
                'channel'         => $channel,
                'status'          => 'waiting',
                'started_at'      => $now,
                'last_message_at' => $now,
                'metadata'        => $metadata,
            ) );

            $conversation = SSC_DB::get_conversation( $conversation_id );
            if ( $conversation ) {
                do_action( 'ssc_conversation_created', $conversation );
            }

            return $conversation;
        }

        /**
         * Append an inbound message from an external channel.
         *
         * Handles participant creation, message insert, and conversation status
         * update, then fires the corresponding lifecycle hooks.
         *
         * @param array $args {
         *     @type int    $conversation_id Required.
         *     @type string $channel         Channel slug (informational; conversation already has it).
         *     @type string $author_name     Display name shown in WP chat.
         *     @type string $author_type     'visitor' (default), 'admin', 'bot', or 'system'.
         *     @type string $message         Required.
         *     @type string $external_msg_id Optional — for dedup / sync tracking by the add-on.
         * }
         * @return int|null Message ID, or null on failure.
         */
        public static function external_inbound( $args ) {
            $defaults = array(
                'conversation_id' => 0,
                'channel'         => '',
                'author_name'     => 'External',
                'author_type'     => 'visitor',
                'message'         => '',
                'external_msg_id' => '',
            );
            $args = wp_parse_args( $args, $defaults );

            $conversation_id = absint( $args['conversation_id'] );
            $message_text    = sanitize_text_field( $args['message'] );

            if ( ! $conversation_id || empty( $message_text ) ) {
                return null;
            }

            $conversation = SSC_DB::get_conversation( $conversation_id );
            if ( ! $conversation ) {
                return null;
            }

            $author_type = in_array( $args['author_type'], array( 'visitor', 'admin', 'bot', 'system' ), true )
                ? $args['author_type']
                : 'visitor';

            // Look up an existing participant of this type with this display name.
            global $wpdb;
            $part_table  = $wpdb->prefix . 'ssc_participants';
            $author_name = sanitize_text_field( $args['author_name'] );
            $participant = $wpdb->get_row( $wpdb->prepare(
                "SELECT * FROM {$part_table}
                 WHERE conversation_id = %d AND participant_type = %s AND display_name = %s
                 LIMIT 1",
                $conversation_id,
                $author_type,
                $author_name
            ) );

            if ( ! $participant ) {
                $participant_id = SSC_DB::add_participant( array(
                    'conversation_id'  => $conversation_id,
                    'participant_type' => $author_type,
                    'display_name'     => $author_name,
                ) );
                $participant = (object) array(
                    'id'           => $participant_id,
                    'display_name' => $author_name,
                );
            }

            $message_id = SSC_DB::add_message( array(
                'conversation_id' => $conversation_id,
                'participant_id'  => $participant->id,
                'message'         => $message_text,
                'message_type'    => 'text',
            ) );

            if ( ! $message_id ) {
                return null;
            }

            // Visitor messages park the conversation as 'waiting'; admin/bot replies set it 'active'.
            $new_status = ( $author_type === 'admin' || $author_type === 'bot' ) ? 'active' : 'waiting';
            $old_status = $conversation->status;
            if ( $old_status !== $new_status ) {
                SSC_DB::update_conversation( $conversation_id, array( 'status' => $new_status ) );
                do_action( 'ssc_conversation_status_changed', $conversation_id, $new_status, $old_status );
            }

            // Fire the appropriate inbound lifecycle hook so other listeners
            // (analytics, notifications, mirroring add-ons) can react.
            $conversation = SSC_DB::get_conversation( $conversation_id );
            if ( $author_type === 'visitor' ) {
                do_action( 'ssc_visitor_message_sent', $message_id, $conversation, $message_text, $participant );
            } elseif ( $author_type === 'admin' ) {
                do_action( 'ssc_admin_reply_sent', $message_id, $conversation, $message_text, null );
            } else {
                do_action( 'ssc_bot_message_sent', $message_id, $conversation, $message_text, 'text' );
            }

            return $message_id;
        }
    }

}
