<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

if ( ! class_exists( 'SSC_Whatsapp' ) ) {

    /**
     * WhatsApp integration via the Meta Cloud API — bundled in core, built on
     * the same add-on extension API as SSC_Discord (the reference channel).
     *
     * Two modes, both supported simultaneously:
     *
     *  Mode A — visitor entry channel: a visitor messages the business number
     *  (wa.me link / click-to-chat button); the message arrives by webhook and
     *  becomes a normal conversation in the admin dashboard. Admin replies from
     *  wp-admin (or Discord, or their own WhatsApp) are pushed back to the
     *  visitor's WhatsApp, subject to Meta's 24-hour customer-service window.
     *
     *  Mode B — admin notification/reply channel: every visitor message (any
     *  channel) is forwarded to the site admin's personal WhatsApp, tagged
     *  [#<conversation> <name>]. The admin quote-replies from their phone and
     *  the reply is routed back to the right conversation (and on to the
     *  visitor, wherever they are).
     *
     * Unlike Discord there is no companion bot — Meta calls our REST webhook
     * directly. See .docs/whatsapp-integration-plan.md.
     */
    class SSC_Whatsapp {

        const API_BASE = 'https://graph.facebook.com/v21.0';

        /** Meta error code for "outside the 24h customer service window". */
        const ERR_REENGAGEMENT = 131047;

        /** WhatsApp text message body limit. */
        const MAX_TEXT_LENGTH = 4096;

        // -------------------------------------------------------------------
        // Bootstrap
        // -------------------------------------------------------------------

        /**
         * Wire all hooks. Called once from the main plugin file at plugins_loaded.
         */
        public static function init() {
            // Channel registry.
            add_filter( 'ssc_channels', array( __CLASS__, 'register_channel' ) );

            // Settings UI.
            add_filter( 'ssc_settings_tabs',     array( __CLASS__, 'register_tab' ) );
            add_action( 'ssc_register_settings', array( __CLASS__, 'register_settings_fields' ) );
            add_filter( 'ssc_sanitize_options',  array( __CLASS__, 'sanitize_options' ), 10, 2 );

            // REST routes.
            add_action( 'ssc_register_rest_routes', array( __CLASS__, 'register_rest_routes' ) );

            // Message lifecycle listeners.
            add_action( 'ssc_visitor_message_sent', array( __CLASS__, 'on_visitor_message_sent' ), 10, 5 );
            add_action( 'ssc_admin_reply_sent',     array( __CLASS__, 'on_admin_reply_sent' ),     10, 5 );

            // Admin conversation view extras.
            add_action( 'ssc_conversation_sidebar',     array( __CLASS__, 'render_sidebar_panel' ) );
            add_action( 'ssc_conversation_reply_footer', array( __CLASS__, 'render_reply_footer' ) );

            // Front-end click-to-chat button.
            add_filter( 'ssc_frontend_config',   array( __CLASS__, 'frontend_config' ) );
            add_action( 'ssc_enqueue_frontend',  array( __CLASS__, 'enqueue_frontend' ) );

            // Self-register with the add-on system so the Status tab shows us.
            if ( class_exists( 'SSC_Addons' ) ) {
                SSC_Addons::register( array(
                    'slug'        => 'super-speedy-chat-whatsapp',
                    'name'        => __( 'WhatsApp (bundled)', 'super-speedy-chat' ),
                    'version'     => defined( 'SSC_VERSION' ) ? SSC_VERSION : '0',
                    'channel'     => 'whatsapp',
                    'plugin_file' => __FILE__,
                ) );
            }
        }

        // -------------------------------------------------------------------
        // Configuration accessors
        // -------------------------------------------------------------------

        public static function is_configured() {
            return self::get_access_token() !== '' && self::get_phone_number_id() !== '';
        }

        public static function is_enabled() {
            return (bool) SSC_Settings::get_option( 'ssc_whatsapp_enabled', false ) && self::is_configured();
        }

        private static function get_access_token() {
            return (string) SSC_Settings::get_option( 'ssc_whatsapp_access_token', '' );
        }

        private static function get_phone_number_id() {
            return (string) SSC_Settings::get_option( 'ssc_whatsapp_phone_number_id', '' );
        }

        private static function get_app_secret() {
            return (string) SSC_Settings::get_option( 'ssc_whatsapp_app_secret', '' );
        }

        /**
         * The public business number (E.164-ish) used for wa.me links.
         */
        private static function get_business_number() {
            return self::normalize_phone( SSC_Settings::get_option( 'ssc_whatsapp_business_number', '' ) );
        }

        /**
         * The admin's personal WhatsApp number for Mode B forwarding.
         */
        private static function get_admin_number() {
            return self::normalize_phone( SSC_Settings::get_option( 'ssc_whatsapp_admin_number', '' ) );
        }

        private static function admin_forward_enabled() {
            return (bool) SSC_Settings::get_option( 'ssc_whatsapp_admin_forward_enabled', false )
                && self::get_admin_number() !== '';
        }

        private static function get_template_name() {
            return (string) SSC_Settings::get_option( 'ssc_whatsapp_template_name', '' );
        }

        private static function get_template_lang() {
            $lang = (string) SSC_Settings::get_option( 'ssc_whatsapp_template_lang', 'en' );
            return $lang !== '' ? $lang : 'en';
        }

        /**
         * Get or generate the webhook verify token (Meta's GET handshake).
         */
        public static function get_verify_token() {
            $options = get_option( 'ssc_options', array() );
            if ( ! empty( $options['ssc_whatsapp_verify_token'] ) ) {
                return $options['ssc_whatsapp_verify_token'];
            }

            $token = wp_generate_password( 32, false );
            $options['ssc_whatsapp_verify_token'] = $token;
            update_option( 'ssc_options', $options );
            return $token;
        }

        /**
         * Reduce a phone number to bare digits — the form the Cloud API uses
         * for both `to` and `from` (E.164 without the plus).
         */
        public static function normalize_phone( $phone ) {
            return preg_replace( '/\D/', '', (string) $phone );
        }

        // -------------------------------------------------------------------
        // Cloud API
        // -------------------------------------------------------------------

        private static function api_request( $method, $endpoint, $body = null ) {
            $url  = self::API_BASE . $endpoint;
            $args = array(
                'method'  => $method,
                'headers' => array(
                    'Authorization' => 'Bearer ' . self::get_access_token(),
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
                return new WP_Error( 'whatsapp_rate_limited', 'WhatsApp Cloud API rate limited.' );
            }

            if ( $code >= 400 ) {
                $msg      = isset( $data['error']['message'] ) ? $data['error']['message'] : 'WhatsApp Cloud API error (HTTP ' . $code . ')';
                $meta_code = isset( $data['error']['code'] ) ? (int) $data['error']['code'] : 0;
                return new WP_Error( 'whatsapp_error', $msg, array( 'meta_code' => $meta_code ) );
            }

            return $data;
        }

        /**
         * Send a free-form text message. Returns the WhatsApp message id
         * (wamid) on success, WP_Error on failure.
         */
        private static function send_text( $to_phone, $body ) {
            if ( mb_strlen( $body ) > self::MAX_TEXT_LENGTH ) {
                $body = mb_substr( $body, 0, self::MAX_TEXT_LENGTH - 3 ) . '...';
            }

            $result = self::api_request( 'POST', '/' . self::get_phone_number_id() . '/messages', array(
                'messaging_product' => 'whatsapp',
                'to'                => $to_phone,
                'type'              => 'text',
                'text'              => array( 'body' => $body ),
            ) );

            if ( is_wp_error( $result ) ) {
                self::record_error( $result );
                return $result;
            }

            return isset( $result['messages'][0]['id'] ) ? $result['messages'][0]['id'] : '';
        }

        /**
         * Send a pre-approved template message (allowed outside the 24h window).
         */
        private static function send_template( $to_phone, $template_name ) {
            $result = self::api_request( 'POST', '/' . self::get_phone_number_id() . '/messages', array(
                'messaging_product' => 'whatsapp',
                'to'                => $to_phone,
                'type'              => 'template',
                'template'          => array(
                    'name'     => $template_name,
                    'language' => array( 'code' => self::get_template_lang() ),
                ),
            ) );

            if ( is_wp_error( $result ) ) {
                self::record_error( $result );
                return $result;
            }

            return isset( $result['messages'][0]['id'] ) ? $result['messages'][0]['id'] : '';
        }

        public static function test_connection() {
            return self::api_request( 'GET', '/' . self::get_phone_number_id() . '?fields=display_phone_number,verified_name,quality_rating' );
        }

        /**
         * Remember the most recent send failure so the settings tab can show it.
         */
        private static function record_error( $error ) {
            update_option( 'ssc_whatsapp_last_error', array(
                'message' => $error->get_error_message(),
                'time'    => current_time( 'mysql' ),
            ), false );
        }

        // -------------------------------------------------------------------
        // Thread + message-map storage
        // -------------------------------------------------------------------

        private static function get_thread_by_phone( $phone ) {
            global $wpdb;
            $table = $wpdb->prefix . 'ssc_whatsapp_threads';
            return $wpdb->get_row(
                $wpdb->prepare( "SELECT * FROM {$table} WHERE wa_phone_number = %s LIMIT 1", $phone )
            );
        }

        private static function get_thread_by_conversation( $conversation_id ) {
            global $wpdb;
            $table = $wpdb->prefix . 'ssc_whatsapp_threads';
            return $wpdb->get_row(
                $wpdb->prepare( "SELECT * FROM {$table} WHERE conversation_id = %d LIMIT 1", $conversation_id )
            );
        }

        private static function touch_thread( $thread_id, $column ) {
            global $wpdb;
            $table = $wpdb->prefix . 'ssc_whatsapp_threads';
            $wpdb->update(
                $table,
                array( $column => current_time( 'mysql' ) ),
                array( 'id' => $thread_id ),
                array( '%s' ),
                array( '%d' )
            );
        }

        /**
         * Record a wamid → conversation mapping. Used for quote-reply routing
         * (Mode B) and inbound webhook dedup (Meta redelivers on timeouts).
         *
         * Returns false if the wamid was already recorded.
         */
        private static function map_message( $wa_msg_id, $conversation_id, $direction ) {
            global $wpdb;
            if ( empty( $wa_msg_id ) ) {
                return true;
            }
            $table  = $wpdb->prefix . 'ssc_whatsapp_msg_map';
            $result = $wpdb->query( $wpdb->prepare(
                "INSERT IGNORE INTO {$table} (wa_msg_id, conversation_id, direction, created_at) VALUES (%s, %d, %s, %s)",
                $wa_msg_id,
                $conversation_id,
                $direction,
                current_time( 'mysql' )
            ) );
            return (bool) $result;
        }

        private static function lookup_conversation_by_wamid( $wa_msg_id ) {
            global $wpdb;
            $table = $wpdb->prefix . 'ssc_whatsapp_msg_map';
            return (int) $wpdb->get_var(
                $wpdb->prepare( "SELECT conversation_id FROM {$table} WHERE wa_msg_id = %s LIMIT 1", $wa_msg_id )
            );
        }

        /**
         * Whether the visitor's 24-hour customer-service window is open.
         */
        public static function is_window_open( $thread ) {
            if ( empty( $thread->last_inbound_at ) ) {
                return false;
            }
            return ( current_time( 'timestamp' ) - strtotime( $thread->last_inbound_at ) ) < DAY_IN_SECONDS;
        }

        // -------------------------------------------------------------------
        // Conversation linkage
        // -------------------------------------------------------------------

        /**
         * Get or create the conversation + thread row for a visitor phone.
         */
        public static function get_or_create_conversation_for_phone( $phone, $display_name ) {
            global $wpdb;

            if ( $display_name === '' ) {
                $display_name = 'WhatsApp +' . $phone;
            }

            $conversation = SSC_Chat::get_or_create_external_conversation( array(
                'channel'      => 'whatsapp',
                'external_id'  => $phone,
                'display_name' => $display_name,
                'metadata'     => array( 'phone' => '+' . $phone ),
            ) );

            if ( ! $conversation ) {
                return null;
            }

            $table  = $wpdb->prefix . 'ssc_whatsapp_threads';
            $thread = self::get_thread_by_phone( $phone );

            if ( ! $thread ) {
                $wpdb->insert( $table, array(
                    'conversation_id' => $conversation->id,
                    'wa_phone_number' => $phone,
                    'wa_profile_name' => $display_name,
                    'created_at'      => current_time( 'mysql' ),
                ) );
            } elseif ( (int) $thread->conversation_id !== (int) $conversation->id ) {
                // Previous conversation was closed/archived; re-point the
                // thread at the fresh conversation for this phone.
                $wpdb->update(
                    $table,
                    array( 'conversation_id' => $conversation->id, 'wa_profile_name' => $display_name ),
                    array( 'id' => $thread->id ),
                    array( '%d', '%s' ),
                    array( '%d' )
                );
            }

            return $conversation;
        }

        // -------------------------------------------------------------------
        // Lifecycle hook listeners
        // -------------------------------------------------------------------

        /**
         * Visitor message (any channel) → forward to the admin's personal
         * WhatsApp so they get an instant phone notification (Mode B).
         */
        public static function on_visitor_message_sent( $message_id, $conversation, $message_text, $participant, $source_channel = '' ) {
            if ( ! self::is_enabled() || ! self::admin_forward_enabled() ) {
                return;
            }

            $name = isset( $participant->display_name ) ? $participant->display_name : 'Visitor';
            self::forward_to_admin( $conversation->id, "\xF0\x9F\x92\xAC", $name, $message_text ); // 💬
        }

        /**
         * Admin reply (from wp-admin, Discord, or WhatsApp quote-reply) →
         * push to the visitor's WhatsApp when the conversation lives there
         * (Mode A), and copy to the admin's personal WhatsApp for context
         * (Mode B) unless the reply came from there in the first place.
         */
        public static function on_admin_reply_sent( $message_id, $conversation, $message_text, $admin_user_id, $source_channel = '' ) {
            if ( ! self::is_enabled() ) {
                return;
            }

            if ( $admin_user_id ) {
                $admin_name = class_exists( 'SSC_Admin' ) ? SSC_Admin::get_admin_chat_name( $admin_user_id ) : 'Admin';
            } elseif ( $source_channel !== '' ) {
                $admin_name = 'Admin (' . ucfirst( $source_channel ) . ')';
            } else {
                $admin_name = 'Admin';
            }

            // Mode A — deliver to the WhatsApp visitor. The admin's own phone
            // and the visitor's phone are different recipients, so replies
            // sourced from WhatsApp still get delivered (no echo risk).
            if ( $conversation->channel === 'whatsapp' ) {
                self::push_to_visitor( $conversation->id, $message_text );
            }

            // Mode B — mirror the reply to the admin's phone so the quote-reply
            // history stays complete. Skip when the reply came from WhatsApp
            // (the admin already has it on their phone).
            if ( self::admin_forward_enabled() && $source_channel !== 'whatsapp' ) {
                self::forward_to_admin( $conversation->id, "\xF0\x9F\x91\xA4", $admin_name, $message_text ); // 👤
            }
        }

        /**
         * Send an admin reply to the visitor's WhatsApp, respecting the 24h window.
         */
        private static function push_to_visitor( $conversation_id, $message_text ) {
            $thread = self::get_thread_by_conversation( $conversation_id );
            if ( ! $thread ) {
                return;
            }

            if ( ! self::is_window_open( $thread ) ) {
                // Outside the window free-form sends are rejected by Meta. If a
                // re-engagement template is configured, send that instead — the
                // WP-side copy of the actual reply is already saved.
                if ( self::get_template_name() !== '' ) {
                    self::send_template( $thread->wa_phone_number, self::get_template_name() );
                    self::touch_thread( $thread->id, 'last_outbound_at' );
                } else {
                    self::record_error( new WP_Error( 'whatsapp_window', sprintf(
                        'Reply to conversation #%d not delivered: 24-hour window expired and no re-engagement template is configured.',
                        $conversation_id
                    ) ) );
                }
                return;
            }

            $wamid = self::send_text( $thread->wa_phone_number, $message_text );
            if ( ! is_wp_error( $wamid ) ) {
                self::map_message( $wamid, $conversation_id, 'out' );
                self::touch_thread( $thread->id, 'last_outbound_at' );
            }
        }

        /**
         * Forward a tagged copy of a message to the admin's personal WhatsApp.
         * The [#id name] tag plus the wamid map is what makes quote-reply
         * routing work from the admin's phone.
         */
        private static function forward_to_admin( $conversation_id, $emoji, $sender_name, $message_text ) {
            $admin_number = self::get_admin_number();

            // Never forward a WhatsApp visitor's message back to themselves
            // (covers the edge where the admin number is used for testing).
            $thread = self::get_thread_by_conversation( $conversation_id );
            if ( $thread && $thread->wa_phone_number === $admin_number ) {
                return;
            }

            $body  = "{$emoji} [#{$conversation_id} {$sender_name}]\n{$message_text}";
            $wamid = self::send_text( $admin_number, $body );

            if ( is_wp_error( $wamid ) ) {
                // Outside the admin's own 24h window: try to re-open it with the
                // configured template (once per 12h so we don't spam Meta).
                $data = $wamid->get_error_data();
                if ( is_array( $data ) && isset( $data['meta_code'] ) && (int) $data['meta_code'] === self::ERR_REENGAGEMENT
                    && self::get_template_name() !== ''
                    && ! get_transient( 'ssc_whatsapp_admin_reopen' ) ) {
                    set_transient( 'ssc_whatsapp_admin_reopen', 1, 12 * HOUR_IN_SECONDS );
                    self::send_template( $admin_number, self::get_template_name() );
                }
                return;
            }

            self::map_message( $wamid, $conversation_id, 'out' );
        }

        // -------------------------------------------------------------------
        // Inbound webhook
        // -------------------------------------------------------------------

        /**
         * Verify the X-Hub-Signature-256 HMAC over the raw request body.
         */
        public static function verify_webhook_signature( $raw_body, $signature_header ) {
            $secret = self::get_app_secret();
            if ( $secret === '' || empty( $signature_header ) ) {
                return false;
            }
            $expected = 'sha256=' . hash_hmac( 'sha256', $raw_body, $secret );
            return hash_equals( $expected, $signature_header );
        }

        /**
         * Walk a Cloud API webhook payload and process each message.
         * Payload shape: entry[].changes[].value.{messages[],contacts[],statuses[]}.
         */
        public static function handle_incoming_webhook( $payload ) {
            $handled = 0;

            if ( empty( $payload['entry'] ) || ! is_array( $payload['entry'] ) ) {
                return $handled;
            }

            foreach ( $payload['entry'] as $entry ) {
                if ( empty( $entry['changes'] ) || ! is_array( $entry['changes'] ) ) {
                    continue;
                }
                foreach ( $entry['changes'] as $change ) {
                    $value = isset( $change['value'] ) ? $change['value'] : array();
                    if ( empty( $value['messages'] ) || ! is_array( $value['messages'] ) ) {
                        continue; // Status updates (delivered/read) — ignored in v1.
                    }

                    // Index contact profiles by wa_id for name lookup.
                    $contacts = array();
                    if ( ! empty( $value['contacts'] ) && is_array( $value['contacts'] ) ) {
                        foreach ( $value['contacts'] as $contact ) {
                            if ( isset( $contact['wa_id'] ) ) {
                                $contacts[ $contact['wa_id'] ] = isset( $contact['profile']['name'] ) ? $contact['profile']['name'] : '';
                            }
                        }
                    }

                    foreach ( $value['messages'] as $message ) {
                        if ( self::handle_message_event( $message, $contacts ) ) {
                            $handled++;
                        }
                    }
                }
            }

            return $handled;
        }

        /**
         * Process one inbound message: admin quote-replies get routed to their
         * conversation; anything else is a visitor message.
         */
        private static function handle_message_event( $message, $contacts ) {
            $from  = isset( $message['from'] ) ? self::normalize_phone( $message['from'] ) : '';
            $wamid = isset( $message['id'] ) ? $message['id'] : '';
            $text  = self::extract_message_text( $message );

            if ( $from === '' || $text === '' ) {
                return false;
            }

            $is_admin = self::admin_forward_enabled() && $from === self::get_admin_number();

            // Dedup — Meta redelivers webhooks that time out. Recording the
            // wamid up front also stores the conversation mapping for visitors.
            // Conversation id is filled in below for visitor messages, so admin
            // messages map to 0 here and that's fine (they're only deduped).
            if ( $is_admin ) {
                if ( ! self::map_message( $wamid, 0, 'in' ) ) {
                    return false;
                }
                update_option( 'ssc_whatsapp_admin_last_inbound', current_time( 'mysql' ), false );
                return self::handle_admin_reply( $message, $text );
            }

            $profile_name = isset( $contacts[ $message['from'] ] ) ? sanitize_text_field( $contacts[ $message['from'] ] ) : '';
            $conversation = self::get_or_create_conversation_for_phone( $from, $profile_name );
            if ( ! $conversation ) {
                return false;
            }

            if ( ! self::map_message( $wamid, $conversation->id, 'in' ) ) {
                return false; // Already processed.
            }

            $thread = self::get_thread_by_phone( $from );
            if ( $thread ) {
                self::touch_thread( $thread->id, 'last_inbound_at' );
            }

            $display_name = $profile_name !== '' ? $profile_name : 'WhatsApp +' . $from;

            $message_id = SSC_Chat::external_inbound( array(
                'conversation_id' => $conversation->id,
                'channel'         => 'whatsapp',
                'author_name'     => $display_name,
                'author_type'     => 'visitor',
                'message'         => $text,
                'external_msg_id' => $wamid,
            ) );

            return (bool) $message_id;
        }

        /**
         * Route a message from the admin's personal WhatsApp back to a
         * conversation. Routing order: quoted-message wamid (context.id),
         * then a manually typed [#123] tag. Unroutable replies get a help
         * message back so the admin knows to quote-reply.
         */
        private static function handle_admin_reply( $message, $text ) {
            $conversation_id = 0;

            if ( ! empty( $message['context']['id'] ) ) {
                $conversation_id = self::lookup_conversation_by_wamid( $message['context']['id'] );
            }

            if ( ! $conversation_id && preg_match( '/^\[?#(\d+)\]?\s*/', $text, $m ) ) {
                $conversation_id = (int) $m[1];
                $text            = preg_replace( '/^\[?#(\d+)\]?\s*/', '', $text );
            }

            if ( ! $conversation_id || ! SSC_DB::get_conversation( $conversation_id ) ) {
                self::send_text(
                    self::get_admin_number(),
                    "\xE2\x9A\xA0\xEF\xB8\x8F Couldn't route your reply. Quote-reply (swipe) the chat message you're answering, or start your message with #<conversation-number>." // ⚠️
                );
                return false;
            }

            $message_id = SSC_Chat::external_inbound( array(
                'conversation_id' => $conversation_id,
                'channel'         => 'whatsapp',
                'author_name'     => 'Admin (WhatsApp)',
                'author_type'     => 'admin',
                'message'         => $text,
                'external_msg_id' => isset( $message['id'] ) ? $message['id'] : '',
            ) );

            return (bool) $message_id;
        }

        /**
         * Pull displayable text out of the supported message types. Media
         * arrives as a placeholder — v1 is text-only (see the plan doc).
         */
        private static function extract_message_text( $message ) {
            $type = isset( $message['type'] ) ? $message['type'] : '';

            switch ( $type ) {
                case 'text':
                    return isset( $message['text']['body'] ) ? sanitize_text_field( $message['text']['body'] ) : '';
                case 'button':
                    return isset( $message['button']['text'] ) ? sanitize_text_field( $message['button']['text'] ) : '';
                case 'interactive':
                    if ( isset( $message['interactive']['button_reply']['title'] ) ) {
                        return sanitize_text_field( $message['interactive']['button_reply']['title'] );
                    }
                    if ( isset( $message['interactive']['list_reply']['title'] ) ) {
                        return sanitize_text_field( $message['interactive']['list_reply']['title'] );
                    }
                    return '';
                case 'image':
                case 'video':
                case 'audio':
                case 'document':
                case 'sticker':
                case 'location':
                case 'contacts':
                    return '[Received a ' . $type . ' — view it in WhatsApp]';
                default:
                    return '';
            }
        }

        // -------------------------------------------------------------------
        // REST routes
        // -------------------------------------------------------------------

        public static function register_rest_routes( $rest ) {
            register_rest_route( 'ssc/v1', '/whatsapp/incoming', array(
                array(
                    'methods'             => 'GET',
                    'callback'            => array( __CLASS__, 'rest_verify_handshake' ),
                    'permission_callback' => '__return_true',
                ),
                array(
                    'methods'             => 'POST',
                    'callback'            => array( __CLASS__, 'rest_handle_incoming' ),
                    'permission_callback' => '__return_true',
                ),
            ) );

            register_rest_route( 'ssc/v1', '/admin/whatsapp/test', array(
                'methods'             => 'POST',
                'callback'            => array( __CLASS__, 'rest_test_connection' ),
                'permission_callback' => array( $rest, 'check_admin_permission' ),
            ) );
        }

        /**
         * Meta's webhook verification handshake. PHP mangles the dotted
         * hub.* query keys to underscores, so read hub_verify_token etc.
         */
        public static function rest_verify_handshake( $request ) {
            $mode      = $request->get_param( 'hub_mode' );
            $token     = $request->get_param( 'hub_verify_token' );
            $challenge = $request->get_param( 'hub_challenge' );

            if ( $mode === 'subscribe' && $token && hash_equals( self::get_verify_token(), (string) $token ) ) {
                return rest_ensure_response( (int) $challenge );
            }

            return new WP_Error( 'forbidden', 'Verification failed.', array( 'status' => 403 ) );
        }

        public static function rest_handle_incoming( $request ) {
            $raw_body  = $request->get_body();
            $signature = $request->get_header( 'X-Hub-Signature-256' );

            if ( ! self::verify_webhook_signature( $raw_body, $signature ) ) {
                return new WP_Error( 'unauthorized', 'Invalid signature.', array( 'status' => 401 ) );
            }

            $payload = json_decode( $raw_body, true );
            if ( ! is_array( $payload ) ) {
                return new WP_Error( 'bad_request', 'Malformed payload.', array( 'status' => 400 ) );
            }

            $handled = self::handle_incoming_webhook( $payload );

            return rest_ensure_response( array( 'success' => true, 'handled' => $handled ) );
        }

        public static function rest_test_connection( $request ) {
            $result = self::test_connection();

            if ( is_wp_error( $result ) ) {
                return $result;
            }

            return rest_ensure_response( array(
                'success' => true,
                'phone'   => isset( $result['display_phone_number'] ) ? $result['display_phone_number'] : '',
                'name'    => isset( $result['verified_name'] ) ? $result['verified_name'] : '',
            ) );
        }

        // -------------------------------------------------------------------
        // Channel registry
        // -------------------------------------------------------------------

        public static function register_channel( $channels ) {
            $channels[] = array(
                'id'    => 'whatsapp',
                'label' => __( 'WhatsApp', 'super-speedy-chat' ),
                'icon'  => 'dashicons-whatsapp',
            );
            return $channels;
        }

        // -------------------------------------------------------------------
        // Admin conversation view extras
        // -------------------------------------------------------------------

        public static function render_sidebar_panel( $conversation ) {
            if ( $conversation->channel !== 'whatsapp' ) {
                return;
            }
            $thread = self::get_thread_by_conversation( $conversation->id );
            if ( ! $thread ) {
                return;
            }
            $window_open = self::is_window_open( $thread );
            ?>
            <h3><?php esc_html_e( 'WhatsApp', 'super-speedy-chat' ); ?></h3>
            <dl class="ssc-visitor-info">
                <dt><?php esc_html_e( 'Phone', 'super-speedy-chat' ); ?></dt>
                <dd><a href="<?php echo esc_url( 'https://wa.me/' . $thread->wa_phone_number ); ?>" target="_blank">+<?php echo esc_html( $thread->wa_phone_number ); ?></a></dd>
                <dt><?php esc_html_e( 'Last Inbound', 'super-speedy-chat' ); ?></dt>
                <dd><?php echo $thread->last_inbound_at ? esc_html( $thread->last_inbound_at ) : '&mdash;'; ?></dd>
                <dt><?php esc_html_e( 'Reply Window', 'super-speedy-chat' ); ?></dt>
                <dd>
                    <?php if ( $window_open ) : ?>
                        <span style="color:#1a7f37;"><?php esc_html_e( 'Open', 'super-speedy-chat' ); ?></span>
                    <?php else : ?>
                        <span style="color:#b32d2e;"><?php esc_html_e( 'Expired (24h)', 'super-speedy-chat' ); ?></span>
                    <?php endif; ?>
                </dd>
            </dl>
            <?php
        }

        public static function render_reply_footer( $conversation ) {
            if ( $conversation->channel !== 'whatsapp' ) {
                return;
            }
            $thread = self::get_thread_by_conversation( $conversation->id );
            if ( ! $thread || self::is_window_open( $thread ) ) {
                return;
            }
            ?>
            <div class="notice notice-warning inline" style="margin:8px 0 0;">
                <p>
                    <?php esc_html_e( 'This visitor\'s WhatsApp 24-hour window has expired. Your reply will be saved here but not delivered via WhatsApp until they message again.', 'super-speedy-chat' ); ?>
                    <?php if ( self::get_template_name() !== '' ) : ?>
                        <?php
                        printf(
                            /* translators: %s: template name */
                            esc_html__( 'The "%s" template will be sent instead to invite them back.', 'super-speedy-chat' ),
                            esc_html( self::get_template_name() )
                        );
                        ?>
                    <?php endif; ?>
                </p>
            </div>
            <?php
        }

        // -------------------------------------------------------------------
        // Front-end click-to-chat button
        // -------------------------------------------------------------------

        private static function click_to_chat_enabled() {
            return self::is_enabled()
                && (bool) SSC_Settings::get_option( 'ssc_whatsapp_click_to_chat', false )
                && self::get_business_number() !== '';
        }

        public static function frontend_config( $config ) {
            if ( self::click_to_chat_enabled() ) {
                $config['whatsapp_link'] = 'https://wa.me/' . self::get_business_number();
            }
            return $config;
        }

        /**
         * Inject the "Chat on WhatsApp" link into the bubble via the JS hooks
         * API — core bubble markup stays untouched.
         */
        public static function enqueue_frontend() {
            if ( ! self::click_to_chat_enabled() ) {
                return;
            }

            $js = <<<'JS'
(function(){
    if (!window.ssc || !window.ssc.hooks || !window.ssc_config || !window.ssc_config.whatsapp_link) return;
    window.ssc.hooks.addAction('ssc.bubble.rendered', 'ssc-whatsapp', function () {
        var widget = document.getElementById('ssc-widget');
        if (!widget || document.getElementById('ssc-whatsapp-link')) return;
        var bar = document.createElement('div');
        bar.id = 'ssc-whatsapp-link';
        bar.style.cssText = 'text-align:center;padding:6px 10px;border-top:1px solid #eee;background:#fff;';
        var a = document.createElement('a');
        a.href = window.ssc_config.whatsapp_link;
        a.target = '_blank';
        a.rel = 'noopener';
        a.style.cssText = 'color:#25D366;font-size:13px;text-decoration:none;font-weight:600;';
        a.innerHTML = '<svg viewBox="0 0 24 24" style="width:16px;height:16px;vertical-align:-3px;fill:#25D366;margin-right:4px;"><path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.297-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 01-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 01-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884 2.64 0 5.122 1.03 6.988 2.898a9.825 9.825 0 012.893 6.994c-.003 5.45-4.437 9.884-9.885 9.884m8.413-18.297A11.815 11.815 0 0012.05 0C5.495 0 .16 5.335.157 11.892c0 2.096.547 4.142 1.588 5.945L.057 24l6.305-1.654a11.882 11.882 0 005.683 1.448h.005c6.554 0 11.89-5.335 11.893-11.893a11.821 11.821 0 00-3.48-8.413z"/></svg>Chat on WhatsApp';
        bar.appendChild(a);
        var sponsor = widget.querySelector('.ssc-sponsor-link');
        if (sponsor) { widget.insertBefore(bar, sponsor); } else { widget.appendChild(bar); }
    });
})();
JS;
            wp_add_inline_script( 'ssc-chat-bubble', $js, 'after' );
        }

        // -------------------------------------------------------------------
        // Settings UI (tab + section + fields)
        // -------------------------------------------------------------------

        public static function register_tab( $tabs ) {
            $tabs['whatsapp'] = array(
                'label' => __( 'WhatsApp', 'super-speedy-chat' ),
                'order' => 81,
            );
            return $tabs;
        }

        public static function register_settings_fields() {
            add_settings_section(
                'ssc_section_whatsapp',
                '',
                array( __CLASS__, 'render_section_callback' ),
                'ssc',
                array(
                    'before_section' => '<div class="ssc_tab">',
                    'after_section'  => '</div>',
                )
            );

            add_settings_field( 'ssc_whatsapp_enabled', __( 'Enable WhatsApp', 'super-speedy-chat' ), array( 'SSC_Admin', 'field_checkbox' ), 'ssc', 'ssc_section_whatsapp', array(
                'key'     => 'ssc_whatsapp_enabled',
                'label'   => __( 'Enable WhatsApp integration', 'super-speedy-chat' ),
                'default' => false,
            ) );
            add_settings_field( 'ssc_whatsapp_access_token', __( 'Access Token', 'super-speedy-chat' ), array( 'SSC_Admin', 'field_password' ), 'ssc', 'ssc_section_whatsapp', array(
                'key'         => 'ssc_whatsapp_access_token',
                'default'     => '',
                'description' => __( 'Permanent System User access token from Meta Business Manager. Keep this secret.', 'super-speedy-chat' ),
            ) );
            add_settings_field( 'ssc_whatsapp_phone_number_id', __( 'Phone Number ID', 'super-speedy-chat' ), array( 'SSC_Admin', 'field_text' ), 'ssc', 'ssc_section_whatsapp', array(
                'key'         => 'ssc_whatsapp_phone_number_id',
                'default'     => '',
                'description' => __( 'Meta\'s numeric ID for your business phone (WhatsApp > API Setup) — not the phone number itself.', 'super-speedy-chat' ),
            ) );
            add_settings_field( 'ssc_whatsapp_app_secret', __( 'App Secret', 'super-speedy-chat' ), array( 'SSC_Admin', 'field_password' ), 'ssc', 'ssc_section_whatsapp', array(
                'key'         => 'ssc_whatsapp_app_secret',
                'default'     => '',
                'description' => __( 'Your Meta app secret (App Settings > Basic). Used to verify webhook signatures — inbound messages are rejected without it.', 'super-speedy-chat' ),
            ) );
            add_settings_field( 'ssc_whatsapp_business_number', __( 'Business Phone Number', 'super-speedy-chat' ), array( 'SSC_Admin', 'field_text' ), 'ssc', 'ssc_section_whatsapp', array(
                'key'         => 'ssc_whatsapp_business_number',
                'default'     => '',
                'description' => __( 'Your public WhatsApp business number in international format, e.g. +447123456789. Used for the visitor "Chat on WhatsApp" link.', 'super-speedy-chat' ),
            ) );
            add_settings_field( 'ssc_whatsapp_click_to_chat', __( 'Click to Chat', 'super-speedy-chat' ), array( 'SSC_Admin', 'field_checkbox' ), 'ssc', 'ssc_section_whatsapp', array(
                'key'     => 'ssc_whatsapp_click_to_chat',
                'label'   => __( 'Show a "Chat on WhatsApp" link in the chat bubble so visitors can switch to WhatsApp', 'super-speedy-chat' ),
                'default' => false,
            ) );
            add_settings_field( 'ssc_whatsapp_admin_forward_enabled', __( 'Forward to Your Phone', 'super-speedy-chat' ), array( 'SSC_Admin', 'field_checkbox' ), 'ssc', 'ssc_section_whatsapp', array(
                'key'     => 'ssc_whatsapp_admin_forward_enabled',
                'label'   => __( 'Forward every chat message to your personal WhatsApp; quote-reply (swipe) from your phone to answer', 'super-speedy-chat' ),
                'default' => false,
            ) );
            add_settings_field( 'ssc_whatsapp_admin_number', __( 'Your WhatsApp Number', 'super-speedy-chat' ), array( 'SSC_Admin', 'field_text' ), 'ssc', 'ssc_section_whatsapp', array(
                'key'         => 'ssc_whatsapp_admin_number',
                'default'     => '',
                'description' => __( 'Your personal WhatsApp number in international format. IMPORTANT: message the business number from this phone first (and at least once a day) so Meta\'s 24-hour window stays open for forwarding.', 'super-speedy-chat' ),
            ) );
            add_settings_field( 'ssc_whatsapp_template_name', __( 'Re-engagement Template', 'super-speedy-chat' ), array( 'SSC_Admin', 'field_text' ), 'ssc', 'ssc_section_whatsapp', array(
                'key'         => 'ssc_whatsapp_template_name',
                'default'     => '',
                'description' => __( 'Optional: name of a pre-approved Meta message template to send when the 24-hour window has expired (e.g. hello_world while testing).', 'super-speedy-chat' ),
            ) );
            add_settings_field( 'ssc_whatsapp_template_lang', __( 'Template Language', 'super-speedy-chat' ), array( 'SSC_Admin', 'field_text' ), 'ssc', 'ssc_section_whatsapp', array(
                'key'         => 'ssc_whatsapp_template_lang',
                'default'     => 'en',
                'description' => __( 'Language code of the template, e.g. en or en_US.', 'super-speedy-chat' ),
            ) );
            add_settings_field( 'ssc_whatsapp_webhook_info', __( 'Webhook Configuration', 'super-speedy-chat' ), array( __CLASS__, 'render_webhook_info_field' ), 'ssc', 'ssc_section_whatsapp' );
        }

        /**
         * Sanitise WhatsApp's own keys inside the shared ssc_options array.
         */
        public static function sanitize_options( $sanitized, $input ) {
            $sanitized['ssc_whatsapp_enabled']               = ! empty( $input['ssc_whatsapp_enabled'] );
            $sanitized['ssc_whatsapp_click_to_chat']         = ! empty( $input['ssc_whatsapp_click_to_chat'] );
            $sanitized['ssc_whatsapp_admin_forward_enabled'] = ! empty( $input['ssc_whatsapp_admin_forward_enabled'] );

            $text_keys = array(
                'ssc_whatsapp_access_token',
                'ssc_whatsapp_phone_number_id',
                'ssc_whatsapp_app_secret',
                'ssc_whatsapp_business_number',
                'ssc_whatsapp_admin_number',
                'ssc_whatsapp_template_name',
                'ssc_whatsapp_template_lang',
            );
            foreach ( $text_keys as $key ) {
                $sanitized[ $key ] = isset( $input[ $key ] ) ? sanitize_text_field( $input[ $key ] ) : '';
            }

            // Preserve the auto-generated verify token (same pattern as the
            // Discord webhook secret — not re-submitted as a form field).
            if ( ! empty( $input['ssc_whatsapp_verify_token'] ) ) {
                $sanitized['ssc_whatsapp_verify_token'] = sanitize_text_field( $input['ssc_whatsapp_verify_token'] );
            } else {
                $existing = get_option( 'ssc_options', array() );
                if ( ! empty( $existing['ssc_whatsapp_verify_token'] ) ) {
                    $sanitized['ssc_whatsapp_verify_token'] = $existing['ssc_whatsapp_verify_token'];
                }
            }

            return $sanitized;
        }

        // -------------------------------------------------------------------
        // Admin UI renderers
        // -------------------------------------------------------------------

        public static function render_section_callback() {
            $last_error = get_option( 'ssc_whatsapp_last_error', array() );
            ?>
            <div class="ssc-guide-box">
                <h3><?php esc_html_e( 'WhatsApp Integration — Meta Cloud API', 'super-speedy-chat' ); ?></h3>
                <p><?php esc_html_e( 'Let visitors chat with you on WhatsApp, and/or get every chat message forwarded to your own phone for instant replies. No companion bot needed — Meta delivers messages straight to your site.', 'super-speedy-chat' ); ?></p>

                <h4 style="margin-top:16px;"><?php esc_html_e( 'Step 1: Create a Meta App with WhatsApp', 'super-speedy-chat' ); ?></h4>
                <ol>
                    <li><?php
                        printf(
                            /* translators: %s: Meta for Developers URL */
                            __( 'Go to <a href="%s" target="_blank">Meta for Developers</a>, create an app (type: Business), and add the WhatsApp product.', 'super-speedy-chat' ),
                            'https://developers.facebook.com/apps/'
                        );
                    ?></li>
                    <li><?php esc_html_e( 'Under WhatsApp > API Setup, add and verify your business phone number, and copy the Phone Number ID.', 'super-speedy-chat' ); ?></li>
                    <li><?php esc_html_e( 'Create a permanent access token: Business Settings > Users > System Users > Add > generate a token with whatsapp_business_messaging and whatsapp_business_management permissions.', 'super-speedy-chat' ); ?></li>
                    <li><?php esc_html_e( 'Copy the App Secret from App Settings > Basic.', 'super-speedy-chat' ); ?></li>
                </ol>

                <h4 style="margin-top:16px;"><?php esc_html_e( 'Step 2: Configure the Webhook', 'super-speedy-chat' ); ?></h4>
                <ol>
                    <li><?php esc_html_e( 'Under WhatsApp > Configuration > Webhook, enter the Callback URL and Verify Token shown below, then click Verify and Save.', 'super-speedy-chat' ); ?></li>
                    <li><?php esc_html_e( 'Subscribe to the "messages" webhook field.', 'super-speedy-chat' ); ?></li>
                </ol>

                <h4 style="margin-top:16px;"><?php esc_html_e( 'Step 3: Fill in the Settings Below & Save', 'super-speedy-chat' ); ?></h4>
                <p><?php esc_html_e( 'To reply from your own phone, enable "Forward to Your Phone", enter your number, then send any WhatsApp message from your phone to the business number — that opens Meta\'s 24-hour delivery window. Answer forwarded messages by quote-replying (swipe right on the message).', 'super-speedy-chat' ); ?></p>
                <p><button type="button" class="button" id="ssc-whatsapp-test"><?php esc_html_e( 'Test Connection', 'super-speedy-chat' ); ?></button> <span id="ssc-whatsapp-test-result"></span></p>
                <?php if ( ! empty( $last_error['message'] ) ) : ?>
                    <p style="color:#b32d2e;">
                        <strong><?php esc_html_e( 'Last send error:', 'super-speedy-chat' ); ?></strong>
                        <?php echo esc_html( $last_error['message'] ); ?>
                        (<?php echo esc_html( $last_error['time'] ); ?>)
                    </p>
                <?php endif; ?>
            </div>
            <?php
        }

        /**
         * Display the webhook URL + verify token for Meta's webhook config screen.
         */
        public static function render_webhook_info_field() {
            $token    = self::get_verify_token();
            $rest_url = rest_url( 'ssc/v1/whatsapp/incoming' );
            ?>
            <div style="background:#f9f9f9; border:1px solid #e0e0e0; border-radius:4px; padding:12px; max-width:600px;">
                <p style="margin-top:0;">
                    <strong><?php esc_html_e( 'Callback URL:', 'super-speedy-chat' ); ?></strong><br>
                    <code id="ssc-whatsapp-endpoint" style="user-select:all; font-size:12px;"><?php echo esc_html( $rest_url ); ?></code>
                </p>
                <p style="margin-bottom:0;">
                    <strong><?php esc_html_e( 'Verify Token:', 'super-speedy-chat' ); ?></strong><br>
                    <code id="ssc-whatsapp-verify-token" style="user-select:all; font-size:12px;"><?php echo esc_html( $token ); ?></code>
                </p>
            </div>
            <p class="description"><?php esc_html_e( 'Copy these into Meta\'s WhatsApp > Configuration > Webhook screen.', 'super-speedy-chat' ); ?></p>
            <?php
        }
    }

    // Defer hook wiring to plugins_loaded, same as SSC_Discord (avoids early
    // textdomain loading on WP 6.7+).
    add_action( 'plugins_loaded', array( 'SSC_Whatsapp', 'init' ), 20 );
}
