<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

if ( ! class_exists( 'SSC_Discord' ) ) {

    /**
     * Discord integration — bundled in core, but written to the add-on extension
     * API as the reference implementation. Mirror this class when building
     * a third-party channel add-on (WhatsApp, Telegram, etc.).
     *
     * See .docs/addons-system-plan.md.
     */
    class SSC_Discord {

        const API_BASE = 'https://discord.com/api/v10';

        // -------------------------------------------------------------------
        // Bootstrap — wires all the hooks Discord listens for / contributes to
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
            add_action( 'ssc_visitor_message_sent', array( __CLASS__, 'on_visitor_message_sent' ), 10, 4 );
            add_action( 'ssc_admin_reply_sent',     array( __CLASS__, 'on_admin_reply_sent' ),     10, 5 );

            // Self-register with the add-on system so the Status tab shows us.
            if ( class_exists( 'SSC_Addons' ) ) {
                SSC_Addons::register( array(
                    'slug'        => 'super-speedy-chat-discord',
                    'name'        => __( 'Discord (bundled)', 'super-speedy-chat' ),
                    'version'     => defined( 'SSC_VERSION' ) ? SSC_VERSION : '0',
                    'channel'     => 'discord',
                    'plugin_file' => __FILE__,
                ) );
            }
        }

        // -------------------------------------------------------------------
        // Configuration accessors
        // -------------------------------------------------------------------

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
            // allowed_mentions disables ALL mention parsing — the visitor name /
            // page URL / email baked into $parts are untrusted and must not be
            // able to ping @everyone, roles, or users.
            $starter = self::api_request( 'POST', '/channels/' . $channel_id . '/messages', array(
                'content'         => implode( "\n", $parts ),
                'allowed_mentions' => array( 'parse' => array() ),
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

            // allowed_mentions disables ALL mention parsing. $message_text comes
            // from unauthenticated visitors, so without this an anonymous visitor
            // could ping @everyone / @here / roles in the support server.
            self::api_request( 'POST', '/channels/' . $thread->discord_thread_id . '/messages', array(
                'content'         => $content,
                'allowed_mentions' => array( 'parse' => array() ),
            ) );
        }

        // -------------------------------------------------------------------
        // Lifecycle hook listeners — replace the direct push_message calls
        // that used to live in SSC_Chat.
        // -------------------------------------------------------------------

        public static function on_visitor_message_sent( $message_id, $conversation, $message_text, $participant ) {
            if ( ! self::is_enabled() ) {
                return;
            }
            $sender = isset( $participant->display_name ) ? $participant->display_name : 'Visitor';
            self::push_message( $conversation->id, $sender, $message_text, true );
        }

        public static function on_admin_reply_sent( $message_id, $conversation, $message_text, $admin_user_id, $source_channel = '' ) {
            if ( ! self::is_enabled() ) {
                return;
            }
            // Skip replies that originated from Discord itself (would echo back
            // to the same thread). Replies relayed by another channel add-on
            // (e.g. WhatsApp) carry that channel's slug and DO get mirrored so
            // the Discord thread keeps the full history. The empty-source +
            // null-admin fallback covers add-ons that don't pass a channel.
            if ( $source_channel === 'discord' ) {
                return;
            }
            if ( empty( $source_channel ) && empty( $admin_user_id ) ) {
                return;
            }
            if ( $admin_user_id ) {
                $admin_name = class_exists( 'SSC_Admin' ) ? SSC_Admin::get_admin_chat_name( $admin_user_id ) : 'Admin';
            } else {
                $admin_name = 'Admin (' . ucfirst( $source_channel ) . ')';
            }
            self::push_message( $conversation->id, $admin_name, $message_text, false );
        }

        // -------------------------------------------------------------------
        // Instant Receive: Discord → WordPress
        // -------------------------------------------------------------------

        /**
         * Handle an incoming message from the Discord bot relay.
         *
         * Routed through SSC_Chat::external_inbound so the ssc_admin_reply_sent
         * hook fires (with source channel 'discord') and other channel add-ons
         * can relay the reply onwards (e.g. to a WhatsApp visitor).
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

            return SSC_Chat::external_inbound( array(
                'conversation_id' => $thread->conversation_id,
                'channel'         => 'discord',
                'author_name'     => $author_name,
                'author_type'     => 'admin',
                'message'         => $message_text,
            ) );
        }

        // -------------------------------------------------------------------
        // Test Connection
        // -------------------------------------------------------------------

        public static function test_connection() {
            return self::api_request( 'GET', '/users/@me' );
        }

        // -------------------------------------------------------------------
        // Channel registry
        // -------------------------------------------------------------------

        public static function register_channel( $channels ) {
            $channels[] = array(
                'id'    => 'discord',
                'label' => __( 'Discord', 'super-speedy-chat' ),
                'icon'  => 'dashicons-format-chat',
            );
            return $channels;
        }

        // -------------------------------------------------------------------
        // Settings UI (tab + section + fields)
        // -------------------------------------------------------------------

        public static function register_tab( $tabs ) {
            $tabs['discord'] = array(
                'label' => __( 'Discord', 'super-speedy-chat' ),
                'order' => 80,
            );
            return $tabs;
        }

        public static function register_settings_fields() {
            add_settings_section(
                'ssc_section_discord',
                '',
                array( __CLASS__, 'render_section_callback' ),
                'ssc',
                array(
                    'before_section' => '<div class="ssc_tab">',
                    'after_section'  => '</div>',
                )
            );

            add_settings_field( 'ssc_discord_enabled', __( 'Enable Discord', 'super-speedy-chat' ), array( 'SSC_Admin', 'field_checkbox' ), 'ssc', 'ssc_section_discord', array(
                'key'     => 'ssc_discord_enabled',
                'label'   => __( 'Enable Discord integration', 'super-speedy-chat' ),
                'default' => false,
            ) );
            add_settings_field( 'ssc_discord_bot_token', __( 'Bot Token', 'super-speedy-chat' ), array( 'SSC_Admin', 'field_password' ), 'ssc', 'ssc_section_discord', array(
                'key'         => 'ssc_discord_bot_token',
                'default'     => '',
                'description' => __( 'Your Discord bot token. Keep this secret.', 'super-speedy-chat' ),
            ) );
            add_settings_field( 'ssc_discord_channel_id', __( 'Channel ID', 'super-speedy-chat' ), array( 'SSC_Admin', 'field_text' ), 'ssc', 'ssc_section_discord', array(
                'key'         => 'ssc_discord_channel_id',
                'default'     => '',
                'description' => __( 'The Discord channel where chat threads will be created.', 'super-speedy-chat' ),
            ) );
            add_settings_field( 'ssc_discord_bot_info', __( 'Bot Connection Info', 'super-speedy-chat' ), array( __CLASS__, 'render_bot_info_field' ), 'ssc', 'ssc_section_discord' );
        }

        /**
         * Sanitise Discord's own keys inside the shared ssc_options array.
         */
        public static function sanitize_options( $sanitized, $input ) {
            $sanitized['ssc_discord_enabled']    = ! empty( $input['ssc_discord_enabled'] );
            $sanitized['ssc_discord_bot_token']  = isset( $input['ssc_discord_bot_token'] ) ? sanitize_text_field( $input['ssc_discord_bot_token'] ) : '';
            $sanitized['ssc_discord_channel_id'] = isset( $input['ssc_discord_channel_id'] ) ? sanitize_text_field( $input['ssc_discord_channel_id'] ) : '';

            // Preserve the auto-generated webhook secret. Check $input first (covers
            // direct update_option calls from get_webhook_secret), then fall back to
            // the existing DB value (covers form submissions where the secret isn't
            // re-submitted as a field).
            if ( ! empty( $input['ssc_discord_webhook_secret'] ) ) {
                $sanitized['ssc_discord_webhook_secret'] = sanitize_text_field( $input['ssc_discord_webhook_secret'] );
            } else {
                $existing = get_option( 'ssc_options', array() );
                if ( ! empty( $existing['ssc_discord_webhook_secret'] ) ) {
                    $sanitized['ssc_discord_webhook_secret'] = $existing['ssc_discord_webhook_secret'];
                }
            }

            return $sanitized;
        }

        // -------------------------------------------------------------------
        // REST routes
        // -------------------------------------------------------------------

        public static function register_rest_routes( $rest ) {
            register_rest_route( 'ssc/v1', '/admin/discord/test', array(
                'methods'             => 'POST',
                'callback'            => array( __CLASS__, 'rest_test_connection' ),
                'permission_callback' => array( $rest, 'check_admin_permission' ),
            ) );

            register_rest_route( 'ssc/v1', '/discord/incoming', array(
                'methods'             => 'POST',
                'callback'            => array( __CLASS__, 'rest_handle_incoming' ),
                'permission_callback' => '__return_true',
            ) );
        }

        public static function rest_handle_incoming( $request ) {
            $secret = $request->get_header( 'X-SSC-Secret' );
            if ( ! self::verify_secret( $secret ) ) {
                return new WP_Error( 'unauthorized', __( 'Invalid or missing secret.', 'super-speedy-chat' ), array( 'status' => 401 ) );
            }

            $thread_id   = sanitize_text_field( $request->get_param( 'thread_id' ) );
            $author_name = sanitize_text_field( $request->get_param( 'author_name' ) );
            $message     = sanitize_text_field( $request->get_param( 'message' ) );

            if ( empty( $thread_id ) || empty( $message ) ) {
                return new WP_Error( 'missing_params', __( 'Missing required parameters.', 'super-speedy-chat' ), array( 'status' => 400 ) );
            }

            if ( empty( $author_name ) ) {
                $author_name = 'Discord Admin';
            }

            $message_id = self::handle_incoming( $thread_id, $author_name, $message );

            if ( ! $message_id ) {
                return new WP_Error( 'not_found', __( 'Thread not found or message could not be created.', 'super-speedy-chat' ), array( 'status' => 404 ) );
            }

            return rest_ensure_response( array( 'success' => true, 'message_id' => $message_id ) );
        }

        public static function rest_test_connection( $request ) {
            $result = self::test_connection();

            if ( is_wp_error( $result ) ) {
                return $result;
            }

            return rest_ensure_response( array(
                'success'  => true,
                'bot_name' => isset( $result['username'] ) ? $result['username'] : 'Unknown',
            ) );
        }

        // -------------------------------------------------------------------
        // Admin UI renderers (moved here from class-ssc-admin.php)
        // -------------------------------------------------------------------

        public static function render_section_callback() {
            ?>
            <div class="ssc-guide-box">
                <h3><?php esc_html_e( 'Discord Integration — Instant Bidirectional Chat', 'super-speedy-chat' ); ?></h3>
                <p><?php esc_html_e( 'Chat with your visitors in real-time directly from Discord. Visitor messages appear instantly in Discord threads, and your Discord replies are delivered to visitors instantly.', 'super-speedy-chat' ); ?></p>

                <h4 style="margin-top:16px;"><?php esc_html_e( 'Step 1: Create a Discord Bot', 'super-speedy-chat' ); ?></h4>
                <ol>
                    <li><?php
                        printf(
                            /* translators: %s: Discord Developer Portal URL */
                            __( 'Go to the <a href="%s" target="_blank">Discord Developer Portal</a> and create a New Application.', 'super-speedy-chat' ),
                            'https://discord.com/developers/applications'
                        );
                    ?></li>
                    <li><?php esc_html_e( 'Go to the Bot tab and click "Reset Token" to get your bot token. Copy it.', 'super-speedy-chat' ); ?></li>
                    <li><?php esc_html_e( 'Under Privileged Gateway Intents, enable MESSAGE CONTENT INTENT.', 'super-speedy-chat' ); ?></li>
                    <li><?php esc_html_e( 'Go to OAuth2 > URL Generator. Select the "bot" scope.', 'super-speedy-chat' ); ?></li>
                    <li><?php esc_html_e( 'Select permissions: Send Messages, Create Public Threads, Send Messages in Threads, Read Message History.', 'super-speedy-chat' ); ?></li>
                    <li><?php esc_html_e( 'Copy the generated URL, open it, and add the bot to your server.', 'super-speedy-chat' ); ?></li>
                    <li><?php esc_html_e( 'Enable Developer Mode in Discord (User Settings > Advanced), right-click your channel, Copy Channel ID.', 'super-speedy-chat' ); ?></li>
                </ol>

                <h4 style="margin-top:16px;"><?php esc_html_e( 'Step 2: Configure Settings Below & Save', 'super-speedy-chat' ); ?></h4>
                <p><?php esc_html_e( 'Enter your bot token and channel ID below, enable the integration, and click Save. Visitor messages will start appearing in Discord immediately.', 'super-speedy-chat' ); ?></p>

                <h4 style="margin-top:16px;"><?php esc_html_e( 'Step 3: Install the Companion Bot (for Discord → WordPress replies)', 'super-speedy-chat' ); ?></h4>
                <p><?php esc_html_e( 'To receive your Discord replies back in WordPress instantly, install the companion Node.js bot on your server:', 'super-speedy-chat' ); ?></p>
                <ol>
                    <li><?php esc_html_e( 'Requires Node.js 18+ on your server.', 'super-speedy-chat' ); ?></li>
                    <li><?php
                        printf(
                            /* translators: %s: path to the bot/ folder shipped with this plugin */
                            __( 'Copy the <code>bot/</code> folder from <code>%s</code> to a location on your server.', 'super-speedy-chat' ),
                            esc_html( SSC_DIR . 'bot/' )
                        );
                    ?></li>
                    <li><?php esc_html_e( 'Run: npm install', 'super-speedy-chat' ); ?></li>
                    <li><?php esc_html_e( 'Copy .env.example to .env and fill in the values from "Bot Connection Info" below.', 'super-speedy-chat' ); ?></li>
                    <li><?php esc_html_e( 'Run: node discord-bot.js (or use PM2/systemd to keep it running).', 'super-speedy-chat' ); ?></li>
                </ol>
                <p><em><?php esc_html_e( 'Without the companion bot, visitor messages still go to Discord instantly, but your Discord replies won\'t reach visitors until you reply from wp-admin.', 'super-speedy-chat' ); ?></em></p>
                <p><button type="button" class="button" id="ssc-discord-test"><?php esc_html_e( 'Test Connection', 'super-speedy-chat' ); ?></button> <span id="ssc-discord-test-result"></span></p>
            </div>
            <?php
        }

        /**
         * Display the webhook secret and site URL for the Discord bot config.
         */
        public static function render_bot_info_field() {
            $secret   = self::get_webhook_secret();
            $rest_url = rest_url( 'ssc/v1/discord/incoming' );
            ?>
            <div style="background:#f9f9f9; border:1px solid #e0e0e0; border-radius:4px; padding:12px; max-width:600px;">
                <p style="margin-top:0;">
                    <strong><?php esc_html_e( 'Webhook Secret:', 'super-speedy-chat' ); ?></strong><br>
                    <code id="ssc-discord-secret" style="user-select:all; font-size:12px;"><?php echo esc_html( $secret ); ?></code>
                </p>
                <p style="margin-bottom:0;">
                    <strong><?php esc_html_e( 'WordPress Endpoint URL:', 'super-speedy-chat' ); ?></strong><br>
                    <code id="ssc-discord-endpoint" style="user-select:all; font-size:12px;"><?php echo esc_html( $rest_url ); ?></code>
                </p>
            </div>
            <p class="description"><?php esc_html_e( 'Copy these values into your bot\'s .env file.', 'super-speedy-chat' ); ?></p>
            <?php
        }
    }

    // Defer hook wiring to plugins_loaded so __()/translation loading isn't
    // triggered at file-load time (would emit a "_load_textdomain_just_in_time
    // called too early" notice on WP 6.7+). plugins_loaded fires before any
    // hook Discord listens for / contributes to.
    add_action( 'plugins_loaded', array( 'SSC_Discord', 'init' ), 20 );
}
