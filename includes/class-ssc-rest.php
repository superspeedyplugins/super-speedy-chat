<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

if ( ! class_exists( 'SSC_REST' ) ) {

    class SSC_REST {

        const REST_NAMESPACE = 'ssc/v1';

        /**
         * Register all REST API routes.
         */
        public function register_routes() {
            // Visitor: create/resume session.
            register_rest_route( self::REST_NAMESPACE, '/session', array(
                'methods'             => 'POST',
                'callback'            => array( $this, 'handle_session' ),
                'permission_callback' => '__return_true',
            ) );

            // Visitor: send a message.
            register_rest_route( self::REST_NAMESPACE, '/send', array(
                'methods'             => 'POST',
                'callback'            => array( $this, 'handle_send' ),
                'permission_callback' => '__return_true',
            ) );

            // Visitor: poll for new messages.
            register_rest_route( self::REST_NAMESPACE, '/poll', array(
                'methods'             => 'GET',
                'callback'            => array( $this, 'handle_poll' ),
                'permission_callback' => '__return_true',
            ) );

            // Visitor: provide email.
            register_rest_route( self::REST_NAMESPACE, '/email', array(
                'methods'             => 'POST',
                'callback'            => array( $this, 'handle_email' ),
                'permission_callback' => '__return_true',
            ) );

            // Admin: list conversations.
            register_rest_route( self::REST_NAMESPACE, '/admin/conversations', array(
                'methods'             => 'GET',
                'callback'            => array( $this, 'handle_admin_conversations' ),
                'permission_callback' => array( $this, 'check_admin_permission' ),
            ) );

            // Admin: get single conversation with messages.
            register_rest_route( self::REST_NAMESPACE, '/admin/conversation/(?P<id>\d+)', array(
                'methods'             => 'GET',
                'callback'            => array( $this, 'handle_admin_conversation' ),
                'permission_callback' => array( $this, 'check_admin_permission' ),
            ) );

            // Admin: reply to conversation.
            register_rest_route( self::REST_NAMESPACE, '/admin/reply', array(
                'methods'             => 'POST',
                'callback'            => array( $this, 'handle_admin_reply' ),
                'permission_callback' => array( $this, 'check_admin_permission' ),
            ) );

            // Admin: close conversation.
            register_rest_route( self::REST_NAMESPACE, '/admin/close/(?P<id>\d+)', array(
                'methods'             => 'POST',
                'callback'            => array( $this, 'handle_admin_close' ),
                'permission_callback' => array( $this, 'check_admin_permission' ),
            ) );
        }

        /**
         * Permission check for admin endpoints.
         */
        public function check_admin_permission() {
            return current_user_can( 'manage_options' );
        }

        // -------------------------------------------------------------------
        // Rate Limiting
        // -------------------------------------------------------------------

        /**
         * Check rate limit for an action. Returns true if allowed, WP_Error if exceeded.
         *
         * @param string $action   Action name (e.g. 'send', 'session').
         * @param int    $limit    Max requests per window.
         * @param int    $window   Window in seconds.
         * @return true|WP_Error
         */
        private static function check_rate_limit( $action, $limit = 15, $window = 60 ) {
            $ip  = isset( $_SERVER['REMOTE_ADDR'] ) ? $_SERVER['REMOTE_ADDR'] : 'unknown';
            $key = 'ssc_rate_' . $action . '_' . md5( $ip );

            $data = get_transient( $key );
            if ( $data === false ) {
                $data = array( 'count' => 0, 'window_start' => time() );
            }

            if ( ( time() - $data['window_start'] ) > $window ) {
                $data = array( 'count' => 0, 'window_start' => time() );
            }

            $data['count']++;
            set_transient( $key, $data, $window );

            if ( $data['count'] > $limit ) {
                return new WP_Error( 'rate_limited', __( 'Too many requests. Please wait a moment.', 'super-speedy-chat' ), array( 'status' => 429 ) );
            }

            return true;
        }

        // -------------------------------------------------------------------
        // Visitor Handlers
        // -------------------------------------------------------------------

        /**
         * POST /ssc/v1/session
         * Creates or resumes a session. Returns conversation_id and visitor_hash.
         */
        public function handle_session( $request ) {
            $rate_check = self::check_rate_limit( 'session', 10, 60 );
            if ( is_wp_error( $rate_check ) ) {
                return $rate_check;
            }

            $visitor_hash = SSC_Session::get_or_create_visitor_hash();
            $conversation = SSC_Session::get_or_create_conversation( $visitor_hash );

            if ( ! $conversation ) {
                return new WP_Error( 'session_error', __( 'Could not create session.', 'super-speedy-chat' ), array( 'status' => 500 ) );
            }

            // If user is logged in, link user.
            if ( is_user_logged_in() ) {
                SSC_Session::link_user( $visitor_hash, get_current_user_id() );
            }

            // Get existing messages.
            $messages = SSC_DB::get_messages( $conversation->id, 0, 50 );

            return rest_ensure_response( array(
                'conversation_id' => $conversation->id,
                'visitor_hash'    => $visitor_hash,
                'messages'        => $messages ? $messages : array(),
                'status'          => $conversation->status,
            ) );
        }

        /**
         * POST /ssc/v1/send
         * Visitor sends a message.
         */
        public function handle_send( $request ) {
            $rate_check = self::check_rate_limit( 'send', 15, 60 );
            if ( is_wp_error( $rate_check ) ) {
                return $rate_check;
            }

            $visitor_hash = SSC_Session::get_visitor_hash();
            if ( ! $visitor_hash ) {
                return new WP_Error( 'no_session', __( 'No active session. Please refresh the page.', 'super-speedy-chat' ), array( 'status' => 403 ) );
            }

            $message  = $request->get_param( 'message' );
            $page_url = $request->get_param( 'page_url' );

            $result = SSC_Chat::send_visitor_message( $message, $visitor_hash, $page_url );

            if ( is_wp_error( $result ) ) {
                return $result;
            }

            return rest_ensure_response( $result );
        }

        /**
         * GET /ssc/v1/poll
         * Visitor polls for new messages.
         */
        public function handle_poll( $request ) {
            $visitor_hash = SSC_Session::get_visitor_hash();
            if ( ! $visitor_hash ) {
                return new WP_Error( 'no_session', __( 'No active session.', 'super-speedy-chat' ), array( 'status' => 403 ) );
            }

            $conversation = SSC_DB::get_conversation_by_hash( $visitor_hash );
            if ( ! $conversation ) {
                return rest_ensure_response( array(
                    'messages'        => array(),
                    'conversation_id' => 0,
                ) );
            }

            $since_id = absint( $request->get_param( 'since_id' ) );

            return rest_ensure_response( SSC_Chat::poll_messages( $conversation->id, $since_id ) );
        }

        /**
         * POST /ssc/v1/email
         * Visitor provides their email address.
         */
        public function handle_email( $request ) {
            $visitor_hash = SSC_Session::get_visitor_hash();
            if ( ! $visitor_hash ) {
                return new WP_Error( 'no_session', __( 'No active session.', 'super-speedy-chat' ), array( 'status' => 403 ) );
            }

            $conversation = SSC_DB::get_conversation_by_hash( $visitor_hash );
            if ( ! $conversation ) {
                return new WP_Error( 'not_found', __( 'Conversation not found.', 'super-speedy-chat' ), array( 'status' => 404 ) );
            }

            $email  = $request->get_param( 'email' );
            $result = SSC_Chat::save_visitor_email( $conversation->id, $email );

            if ( is_wp_error( $result ) ) {
                return $result;
            }

            return rest_ensure_response( array( 'success' => true ) );
        }

        // -------------------------------------------------------------------
        // Admin Handlers
        // -------------------------------------------------------------------

        /**
         * GET /ssc/v1/admin/conversations
         */
        public function handle_admin_conversations( $request ) {
            $args = array(
                'status'   => sanitize_text_field( $request->get_param( 'status' ) ),
                'search'   => sanitize_text_field( $request->get_param( 'search' ) ),
                'per_page' => absint( $request->get_param( 'per_page' ) ) ?: 20,
                'page'     => absint( $request->get_param( 'page' ) ) ?: 1,
            );

            $result = SSC_DB::get_conversations( $args );

            // Add last message preview to each item.
            foreach ( $result['items'] as &$item ) {
                $item->last_message_preview = SSC_Chat::get_last_message_preview( $item->id );
            }

            return rest_ensure_response( $result );
        }

        /**
         * GET /ssc/v1/admin/conversation/{id}
         */
        public function handle_admin_conversation( $request ) {
            $id           = absint( $request['id'] );
            $conversation = SSC_DB::get_conversation( $id );

            if ( ! $conversation ) {
                return new WP_Error( 'not_found', __( 'Conversation not found.', 'super-speedy-chat' ), array( 'status' => 404 ) );
            }

            $since_id = absint( $request->get_param( 'since_id' ) );
            $messages = SSC_DB::get_messages( $id, $since_id, 100 );

            return rest_ensure_response( array(
                'conversation' => $conversation,
                'messages'     => $messages ? $messages : array(),
            ) );
        }

        /**
         * POST /ssc/v1/admin/reply
         */
        public function handle_admin_reply( $request ) {
            $conversation_id = absint( $request->get_param( 'conversation_id' ) );
            $message         = $request->get_param( 'message' );

            if ( ! $conversation_id ) {
                return new WP_Error( 'missing_id', __( 'Conversation ID is required.', 'super-speedy-chat' ), array( 'status' => 400 ) );
            }

            $result = SSC_Chat::send_admin_reply( $conversation_id, $message, get_current_user_id() );

            if ( is_wp_error( $result ) ) {
                return $result;
            }

            return rest_ensure_response( $result );
        }

        /**
         * POST /ssc/v1/admin/close/{id}
         */
        public function handle_admin_close( $request ) {
            $id = absint( $request['id'] );

            $conversation = SSC_DB::get_conversation( $id );
            if ( ! $conversation ) {
                return new WP_Error( 'not_found', __( 'Conversation not found.', 'super-speedy-chat' ), array( 'status' => 404 ) );
            }

            SSC_DB::update_conversation( $id, array(
                'status' => 'closed',
            ) );

            return rest_ensure_response( array( 'success' => true ) );
        }

        // -------------------------------------------------------------------
        // Static handler methods (called by both REST API and mu-plugin)
        // -------------------------------------------------------------------

        /**
         * Handle a fast-ajax poll request directly.
         * Called from the mu-plugin to bypass full WP REST infrastructure.
         *
         * @param array $params Request parameters.
         * @return array
         */
        public static function fast_poll( $params ) {
            $visitor_hash = isset( $_COOKIE['ssc_visitor_hash'] ) ? sanitize_text_field( $_COOKIE['ssc_visitor_hash'] ) : '';
            if ( empty( $visitor_hash ) ) {
                return array( 'messages' => array(), 'conversation_id' => 0 );
            }

            global $wpdb;
            $conv_table = $wpdb->prefix . 'ssc_conversations';
            $conversation = $wpdb->get_row(
                $wpdb->prepare(
                    "SELECT * FROM {$conv_table} WHERE visitor_hash = %s AND status IN ('active','waiting') ORDER BY last_message_at DESC LIMIT 1",
                    $visitor_hash
                )
            );

            if ( ! $conversation ) {
                return array( 'messages' => array(), 'conversation_id' => 0 );
            }

            $since_id = isset( $params['since_id'] ) ? absint( $params['since_id'] ) : 0;

            $msg_table  = $wpdb->prefix . 'ssc_messages';
            $part_table = $wpdb->prefix . 'ssc_participants';

            $messages = $wpdb->get_results(
                $wpdb->prepare(
                    "SELECT m.*, p.display_name, p.participant_type
                     FROM {$msg_table} AS m
                     INNER JOIN {$part_table} AS p ON m.participant_id = p.id
                     WHERE m.conversation_id = %d AND m.id > %d
                     ORDER BY m.id ASC LIMIT 50",
                    $conversation->id,
                    $since_id
                )
            );

            return array(
                'messages'        => $messages ? $messages : array(),
                'conversation_id' => $conversation->id,
            );
        }

        /**
         * Handle a fast-ajax send request directly.
         * Called from the mu-plugin.
         *
         * @param array $params Request parameters.
         * @return array
         */
        public static function fast_send( $params ) {
            $visitor_hash = isset( $_COOKIE['ssc_visitor_hash'] ) ? sanitize_text_field( $_COOKIE['ssc_visitor_hash'] ) : '';
            if ( empty( $visitor_hash ) ) {
                return array( 'error' => 'No active session.' );
            }

            $message  = isset( $params['message'] ) ? sanitize_text_field( $params['message'] ) : '';
            $page_url = isset( $params['page_url'] ) ? esc_url_raw( $params['page_url'] ) : '';

            if ( empty( $message ) ) {
                return array( 'error' => 'Message cannot be empty.' );
            }

            // Truncate if needed.
            $max_length = absint( get_option( 'ssc_options', array() )['ssc_max_message_length'] ?? 500 );
            if ( $max_length > 0 && mb_strlen( $message ) > $max_length ) {
                $message = mb_substr( $message, 0, $max_length );
            }

            global $wpdb;
            $conv_table = $wpdb->prefix . 'ssc_conversations';
            $part_table = $wpdb->prefix . 'ssc_participants';
            $msg_table  = $wpdb->prefix . 'ssc_messages';
            $now        = current_time( 'mysql' );

            // Find or create conversation.
            $conversation = $wpdb->get_row(
                $wpdb->prepare(
                    "SELECT * FROM {$conv_table} WHERE visitor_hash = %s AND status IN ('active','waiting') ORDER BY last_message_at DESC LIMIT 1",
                    $visitor_hash
                )
            );

            if ( ! $conversation ) {
                $wpdb->insert( $conv_table, array(
                    'visitor_hash'    => $visitor_hash,
                    'status'          => 'waiting',
                    'started_at'      => $now,
                    'last_message_at' => $now,
                    'last_page_url'   => $page_url,
                    'ip_address'      => isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( $_SERVER['REMOTE_ADDR'] ) : null,
                    'user_agent'      => isset( $_SERVER['HTTP_USER_AGENT'] ) ? substr( sanitize_text_field( $_SERVER['HTTP_USER_AGENT'] ), 0, 500 ) : null,
                ) );
                $conversation_id = $wpdb->insert_id;
            } else {
                $conversation_id = $conversation->id;
            }

            // Find or create participant.
            $participant = $wpdb->get_row(
                $wpdb->prepare(
                    "SELECT * FROM {$part_table} WHERE conversation_id = %d AND visitor_hash = %s AND participant_type = 'visitor' LIMIT 1",
                    $conversation_id,
                    $visitor_hash
                )
            );

            if ( ! $participant ) {
                $wpdb->insert( $part_table, array(
                    'conversation_id'  => $conversation_id,
                    'participant_type' => 'visitor',
                    'visitor_hash'     => $visitor_hash,
                    'display_name'     => 'Visitor',
                    'joined_at'        => $now,
                ) );
                $participant_id = $wpdb->insert_id;
            } else {
                $participant_id = $participant->id;
            }

            // Insert message.
            $wpdb->insert( $msg_table, array(
                'conversation_id' => $conversation_id,
                'participant_id'  => $participant_id,
                'message'         => $message,
                'message_type'    => 'text',
                'created_at'      => $now,
            ) );
            $message_id = $wpdb->insert_id;

            // Update conversation.
            $wpdb->update(
                $conv_table,
                array( 'last_message_at' => $now, 'status' => 'waiting', 'last_page_url' => $page_url ),
                array( 'id' => $conversation_id )
            );

            return array(
                'message_id'      => $message_id,
                'conversation_id' => $conversation_id,
            );
        }

        /**
         * Handle a fast-ajax session request directly.
         *
         * @return array
         */
        public static function fast_session() {
            // Create visitor hash cookie if not present.
            $visitor_hash = isset( $_COOKIE['ssc_visitor_hash'] ) ? sanitize_text_field( $_COOKIE['ssc_visitor_hash'] ) : '';
            if ( empty( $visitor_hash ) ) {
                $visitor_hash = bin2hex( random_bytes( 32 ) );
                $expire       = time() + ( 365 * DAY_IN_SECONDS );
                setcookie( 'ssc_visitor_hash', $visitor_hash, $expire, '/', '', is_ssl(), true );
            }

            global $wpdb;
            $conv_table = $wpdb->prefix . 'ssc_conversations';
            $now        = current_time( 'mysql' );

            $conversation = $wpdb->get_row(
                $wpdb->prepare(
                    "SELECT * FROM {$conv_table} WHERE visitor_hash = %s AND status IN ('active','waiting') ORDER BY last_message_at DESC LIMIT 1",
                    $visitor_hash
                )
            );

            if ( ! $conversation ) {
                $wpdb->insert( $conv_table, array(
                    'visitor_hash'    => $visitor_hash,
                    'status'          => 'active',
                    'started_at'      => $now,
                    'last_message_at' => $now,
                    'ip_address'      => isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( $_SERVER['REMOTE_ADDR'] ) : null,
                    'user_agent'      => isset( $_SERVER['HTTP_USER_AGENT'] ) ? substr( sanitize_text_field( $_SERVER['HTTP_USER_AGENT'] ), 0, 500 ) : null,
                ) );
                $conversation_id = $wpdb->insert_id;
                $messages        = array();
                $status          = 'active';
            } else {
                $conversation_id = $conversation->id;
                $status          = $conversation->status;

                $msg_table  = $wpdb->prefix . 'ssc_messages';
                $part_table = $wpdb->prefix . 'ssc_participants';
                $messages   = $wpdb->get_results(
                    $wpdb->prepare(
                        "SELECT m.*, p.display_name, p.participant_type
                         FROM {$msg_table} AS m
                         INNER JOIN {$part_table} AS p ON m.participant_id = p.id
                         WHERE m.conversation_id = %d ORDER BY m.id ASC LIMIT 50",
                        $conversation_id
                    )
                );
            }

            return array(
                'conversation_id' => $conversation_id,
                'visitor_hash'    => $visitor_hash,
                'messages'        => $messages ? $messages : array(),
                'status'          => $status,
            );
        }
    }

}
