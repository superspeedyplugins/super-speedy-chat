<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

if ( ! class_exists( 'SSC_Discord' ) ) {

    class SSC_Discord {

        const API_BASE = 'https://discord.com/api/v10';

        public static function is_configured() {
            $token   = self::get_token();
            $channel = self::get_channel_id();
            return ! empty( $token ) && ! empty( $channel );
        }

        public static function is_enabled() {
            return (bool) SSC_Settings::get_option( 'ssc_discord_enabled', false ) && self::is_configured();
        }

        private static function get_token() {
            return SSC_Settings::get_option( 'ssc_discord_bot_token', '' );
        }

        private static function get_channel_id() {
            return SSC_Settings::get_option( 'ssc_discord_channel_id', '' );
        }

        // -------------------------------------------------------------------
        // Webhook Secret (for bot → WordPress authentication)
        // -------------------------------------------------------------------

        /**
         * Get or generate the shared secret for bot authentication.
         */
        public static function get_webhook_secret() {
            $options = get_option( 'ssc_options', array() );
            if ( ! empty( $options['ssc_discord_webhook_secret'] ) ) {
                return $options['ssc_discord_webhook_secret'];
            }

            $secret = wp_generate_password( 32, false );
            $options['ssc_discord_webhook_secret'] = $secret;
            update_option( 'ssc_options', $options );
            return $secret;
        }

        /**
         * Verify a provided secret against the stored one.
         */
        public static function verify_secret( $provided ) {
            if ( empty( $provided ) ) {
                return false;
            }
            return hash_equals( self::get_webhook_secret(), $provided );
        }

        // -------------------------------------------------------------------
        // Discord API
        // -------------------------------------------------------------------

        private static function api_request( $method, $endpoint, $body = null ) {
            $url  = self::API_BASE . $endpoint;
            $args = array(
                'method'  => $method,
                'headers' => array(
                    'Authorization' => 'Bot ' . self::get_token(),
                    'Content-Type'  => 'application/json',
                ),
                'timeout' => 15,
            );

            if ( $body !== null ) {
                $args['body'] = wp_json_encode( $body );
            }

            $response = wp_remote_request( $url, $args );

            if ( is_wp_error( $response ) ) {
                return $response;
            }

            $code = wp_remote_retrieve_response_code( $response );
            $data = json_decode( wp_remote_retrieve_body( $response ), true );

            if ( $code === 429 ) {
                return new WP_Error( 'discord_rate_limited', 'Discord API rate limited.' );
            }

            if ( $code >= 400 ) {
                $msg = isset( $data['message'] ) ? $data['message'] : 'Discord API error (HTTP ' . $code . ')';
                return new WP_Error( 'discord_error', $msg );
            }

            return $data;
        }

        // -------------------------------------------------------------------
        // Thread Management
        // -------------------------------------------------------------------

        /**
         * Get or create a Discord thread for a conversation.
         */
        public static function get_or_create_thread( $conversation_id ) {
            global $wpdb;
            $table = $wpdb->prefix . 'ssc_discord_threads';

            $existing = $wpdb->get_row(
                $wpdb->prepare( "SELECT * FROM {$table} WHERE conversation_id = %d LIMIT 1", $conversation_id )
            );

            if ( $existing ) {
                return $existing;
            }

            $conversation = SSC_DB::get_conversation( $conversation_id );
            if ( ! $conversation ) {
                return null;
            }

            $name        = ! empty( $conversation->visitor_name ) ? $conversation->visitor_name : 'Visitor';
            $thread_name = substr( 'Chat: ' . $name . ' #' . $conversation_id, 0, 100 );
            $channel_id  = self::get_channel_id();

            // Build starter message with visitor info.
            $parts = array( "**New chat from {$name}**" );
            if ( ! empty( $conversation->last_page_url ) ) {
                $parts[] = 'Page: ' . $conversation->last_page_url;
            }
            if ( ! empty( $conversation->visitor_email ) ) {
                $parts[] = 'Email: ' . $conversation->visitor_email;
            }
            if ( ! empty( $conversation->ip_address ) ) {
                $parts[] = 'IP: ' . $conversation->ip_address;
            }

            // Step 1: Post a starter message to the channel.
            $starter = self::api_request( 'POST', '/channels/' . $channel_id . '/messages', array(
                'content' => implode( "\n", $parts ),
            ) );

            if ( is_wp_error( $starter ) || ! isset( $starter['id'] ) ) {
                return null;
            }

            // Step 2: Create a thread from that message.
            $thread_result = self::api_request(
                'POST',
                '/channels/' . $channel_id . '/messages/' . $starter['id'] . '/threads',
                array(
                    'name'                  => $thread_name,
                    'auto_archive_duration' => 1440,
                )
            );

            if ( is_wp_error( $thread_result ) || ! isset( $thread_result['id'] ) ) {
                return null;
            }

            $wpdb->insert( $table, array(
                'conversation_id'           => $conversation_id,
                'discord_thread_id'         => $thread_result['id'],
                'discord_channel_id'        => $channel_id,
                'last_synced_discord_msg_id' => '0',
                'last_synced_wp_msg_id'     => 0,
                'created_at'                => current_time( 'mysql' ),
            ) );

            return $wpdb->get_row(
                $wpdb->prepare( "SELECT * FROM {$table} WHERE conversation_id = %d LIMIT 1", $conversation_id )
            );
        }

        // -------------------------------------------------------------------
        // Instant Push: WordPress → Discord
        // -------------------------------------------------------------------

        /**
         * Push a message to the Discord thread for a conversation.
         * Called immediately when a visitor sends a message or admin replies from WP.
         *
         * @param int    $conversation_id Conversation ID.
         * @param string $sender_name     Display name of the sender.
         * @param string $message_text    The message content.
         * @param bool   $is_visitor      Whether the sender is a visitor.
         */
        public static function push_message( $conversation_id, $sender_name, $message_text, $is_visitor = true ) {
            if ( ! self::is_enabled() ) {
                return;
            }

            $thread = self::get_or_create_thread( $conversation_id );
            if ( ! $thread ) {
                return;
            }

            $emoji   = $is_visitor ? "\xF0\x9F\x92\xAC" : "\xF0\x9F\x91\xA4"; // 💬 or 👤
            $content = "**{$emoji} {$sender_name}:** {$message_text}";

            if ( strlen( $content ) > 2000 ) {
                $content = substr( $content, 0, 1997 ) . '...';
            }

            self::api_request( 'POST', '/channels/' . $thread->discord_thread_id . '/messages', array(
                'content' => $content,
            ) );
        }

        // -------------------------------------------------------------------
        // Instant Receive: Discord → WordPress
        // -------------------------------------------------------------------

        /**
         * Handle an incoming message from the Discord bot relay.
         * Creates the message in WordPress and updates conversation status.
         *
         * @param string $discord_thread_id The Discord thread ID.
         * @param string $author_name       The Discord user's display name.
         * @param string $message_text      The message content.
         * @return int|null The WordPress message ID, or null on failure.
         */
        public static function handle_incoming( $discord_thread_id, $author_name, $message_text ) {
            global $wpdb;
            $table = $wpdb->prefix . 'ssc_discord_threads';

            $thread = $wpdb->get_row(
                $wpdb->prepare( "SELECT * FROM {$table} WHERE discord_thread_id = %s LIMIT 1", $discord_thread_id )
            );

            if ( ! $thread ) {
                return null;
            }

            $participant = self::get_or_create_discord_participant(
                $thread->conversation_id,
                $author_name
            );

            if ( ! $participant ) {
                return null;
            }

            $message_id = SSC_DB::add_message( array(
                'conversation_id' => $thread->conversation_id,
                'participant_id'  => $participant->id,
                'message'         => sanitize_text_field( $message_text ),
                'message_type'    => 'text',
            ) );

            // Set conversation back to 'active' since admin replied.
            SSC_DB::update_conversation( $thread->conversation_id, array(
                'status' => 'active',
            ) );

            return $message_id;
        }

        // -------------------------------------------------------------------
        // Participant Management
        // -------------------------------------------------------------------

        /**
         * Find or create an admin participant for a Discord user in a conversation.
         */
        private static function get_or_create_discord_participant( $conversation_id, $display_name ) {
            global $wpdb;
            $table = $wpdb->prefix . 'ssc_participants';

            // Look for an existing admin participant with this display name.
            $existing = $wpdb->get_row( $wpdb->prepare(
                "SELECT * FROM {$table} WHERE conversation_id = %d AND participant_type = 'admin' AND display_name = %s LIMIT 1",
                $conversation_id,
                $display_name
            ) );

            if ( $existing ) {
                return $existing;
            }

            $now = current_time( 'mysql' );
            $wpdb->insert( $table, array(
                'conversation_id'  => $conversation_id,
                'participant_type' => 'admin',
                'user_id'          => null,
                'visitor_hash'     => null,
                'display_name'     => $display_name,
                'joined_at'        => $now,
            ) );

            return $wpdb->get_row( $wpdb->prepare(
                "SELECT * FROM {$table} WHERE id = %d", $wpdb->insert_id
            ) );
        }

        // -------------------------------------------------------------------
        // Test Connection
        // -------------------------------------------------------------------

        /**
         * Test the Discord configuration by fetching bot info.
         *
         * @return array|WP_Error Bot info or error.
         */
        public static function test_connection() {
            return self::api_request( 'GET', '/users/@me' );
        }
    }

}
