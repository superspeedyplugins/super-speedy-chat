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
            SSC_DB::update_conversation( $conversation->id, array(
                'status' => 'waiting',
            ) );

            // Send admin email notification on first message.
            $msg_count = self::get_visitor_message_count( $conversation->id );
            if ( $msg_count === 1 ) {
                SSC_Email::notify_admin_new_conversation( $conversation );
            }

            // Push to Discord instantly.
            if ( class_exists( 'SSC_Discord' ) && SSC_Discord::is_enabled() ) {
                $sender = isset( $participant->display_name ) ? $participant->display_name : 'Visitor';
                SSC_Discord::push_message( $conversation->id, $sender, $message, true );
            }

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
            SSC_DB::update_conversation( $conversation_id, array(
                'status' => 'active',
            ) );

            // Email the visitor if they have an email and are offline.
            if ( ! empty( $conversation->visitor_email ) ) {
                SSC_Email::notify_visitor_reply( $conversation, $message );
            }

            // Push admin reply to Discord instantly.
            if ( class_exists( 'SSC_Discord' ) && SSC_Discord::is_enabled() ) {
                $admin_name = SSC_Admin::get_admin_chat_name( $admin_user_id );
                SSC_Discord::push_message( $conversation_id, $admin_name, $message, false );
            }

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
    }

}
