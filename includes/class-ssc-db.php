<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

if ( ! class_exists( 'SSC_DB' ) ) {

    class SSC_DB {

        const DB_VERSION = '1.0.0';

        /**
         * Get the full table name with prefix.
         */
        private static function table( $name ) {
            global $wpdb;
            return $wpdb->prefix . $name;
        }

        /**
         * Create all database tables using dbDelta.
         */
        public static function create_tables() {
            global $wpdb;

            $charset_collate = $wpdb->get_charset_collate();

            $conversations_table = self::table( 'ssc_conversations' );
            $participants_table  = self::table( 'ssc_participants' );
            $messages_table      = self::table( 'ssc_messages' );

            $sql = "CREATE TABLE {$conversations_table} (
                id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                visitor_hash VARCHAR(64) NOT NULL,
                user_id BIGINT UNSIGNED NULL,
                visitor_name VARCHAR(100) NOT NULL DEFAULT 'Visitor',
                visitor_email VARCHAR(255) NULL,
                status ENUM('active','waiting','closed','archived') DEFAULT 'active',
                started_at DATETIME NOT NULL,
                last_message_at DATETIME NOT NULL,
                last_page_url TEXT NULL,
                referrer_url TEXT NULL,
                ip_address VARCHAR(45) NULL,
                user_agent VARCHAR(500) NULL,
                metadata JSON NULL,
                PRIMARY KEY  (id),
                INDEX idx_visitor_hash (visitor_hash),
                INDEX idx_status_last_message (status, last_message_at)
            ) {$charset_collate};

            CREATE TABLE {$participants_table} (
                id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                conversation_id BIGINT UNSIGNED NOT NULL,
                participant_type ENUM('visitor','admin','bot','system') NOT NULL,
                user_id BIGINT UNSIGNED NULL,
                visitor_hash VARCHAR(64) NULL,
                display_name VARCHAR(100) NOT NULL,
                joined_at DATETIME NOT NULL,
                last_seen_at DATETIME NULL,
                PRIMARY KEY  (id),
                INDEX idx_conv_type (conversation_id, participant_type),
                INDEX idx_user_id (user_id),
                INDEX idx_visitor_hash (visitor_hash)
            ) {$charset_collate};

            CREATE TABLE {$messages_table} (
                id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                conversation_id BIGINT UNSIGNED NOT NULL,
                participant_id BIGINT UNSIGNED NOT NULL,
                message TEXT NOT NULL,
                message_type ENUM('text','email_prompt','canned_response','auto_reply') DEFAULT 'text',
                created_at DATETIME NOT NULL,
                read_at DATETIME NULL,
                PRIMARY KEY  (id),
                INDEX idx_conv_id (conversation_id, id),
                INDEX idx_participant_id (participant_id),
                INDEX idx_created_at (created_at)
            ) {$charset_collate};";

            require_once ABSPATH . 'wp-admin/includes/upgrade.php';
            dbDelta( $sql );

            update_option( 'ssc_db_version', self::DB_VERSION );
        }

        /**
         * Get a single conversation by ID.
         */
        public static function get_conversation( $id ) {
            global $wpdb;
            $table = self::table( 'ssc_conversations' );

            return $wpdb->get_row(
                $wpdb->prepare( "SELECT * FROM {$table} WHERE id = %d", $id )
            );
        }

        /**
         * Get the active conversation for a visitor hash.
         */
        public static function get_conversation_by_hash( $visitor_hash ) {
            global $wpdb;
            $table = self::table( 'ssc_conversations' );

            return $wpdb->get_row(
                $wpdb->prepare(
                    "SELECT * FROM {$table} WHERE visitor_hash = %s AND status IN ('active','waiting') ORDER BY last_message_at DESC LIMIT 1",
                    $visitor_hash
                )
            );
        }

        /**
         * Create a new conversation and return the inserted ID.
         */
        public static function create_conversation( $data ) {
            global $wpdb;
            $table = self::table( 'ssc_conversations' );

            $now = current_time( 'mysql' );

            $defaults = array(
                'visitor_hash'    => '',
                'user_id'         => null,
                'visitor_name'    => 'Visitor',
                'visitor_email'   => null,
                'status'          => 'active',
                'started_at'      => $now,
                'last_message_at' => $now,
                'last_page_url'   => null,
                'referrer_url'    => null,
                'ip_address'      => null,
                'user_agent'      => null,
                'metadata'        => null,
            );

            $data = wp_parse_args( $data, $defaults );

            $wpdb->insert( $table, $data );

            return $wpdb->insert_id;
        }

        /**
         * Update conversation fields.
         */
        public static function update_conversation( $id, $data ) {
            global $wpdb;
            $table = self::table( 'ssc_conversations' );

            return $wpdb->update(
                $table,
                $data,
                array( 'id' => $id ),
                null,
                array( '%d' )
            );
        }

        /**
         * Find an existing participant in a conversation.
         */
        public static function get_participant( $conversation_id, $visitor_hash_or_user_id, $type ) {
            global $wpdb;
            $table = self::table( 'ssc_participants' );

            if ( in_array( $type, array( 'admin', 'bot', 'system' ), true ) && is_numeric( $visitor_hash_or_user_id ) ) {
                return $wpdb->get_row(
                    $wpdb->prepare(
                        "SELECT * FROM {$table} WHERE conversation_id = %d AND user_id = %d AND participant_type = %s LIMIT 1",
                        $conversation_id,
                        $visitor_hash_or_user_id,
                        $type
                    )
                );
            }

            return $wpdb->get_row(
                $wpdb->prepare(
                    "SELECT * FROM {$table} WHERE conversation_id = %d AND visitor_hash = %s AND participant_type = %s LIMIT 1",
                    $conversation_id,
                    $visitor_hash_or_user_id,
                    $type
                )
            );
        }

        /**
         * Add a participant and return the inserted ID.
         */
        public static function add_participant( $data ) {
            global $wpdb;
            $table = self::table( 'ssc_participants' );

            $now = current_time( 'mysql' );

            $defaults = array(
                'conversation_id'  => 0,
                'participant_type' => 'visitor',
                'user_id'          => null,
                'visitor_hash'     => null,
                'display_name'     => 'Visitor',
                'joined_at'        => $now,
                'last_seen_at'     => null,
            );

            $data = wp_parse_args( $data, $defaults );

            $wpdb->insert( $table, $data );

            return $wpdb->insert_id;
        }

        /**
         * Get messages for a conversation after a given ID, with participant info.
         */
        public static function get_messages( $conversation_id, $since_id = 0, $limit = 50 ) {
            global $wpdb;
            $messages_table     = self::table( 'ssc_messages' );
            $participants_table = self::table( 'ssc_participants' );

            $limit = absint( $limit );
            if ( $limit < 1 ) {
                $limit = 50;
            }

            return $wpdb->get_results(
                $wpdb->prepare(
                    "SELECT m.*, p.display_name, p.participant_type
                     FROM {$messages_table} AS m
                     INNER JOIN {$participants_table} AS p ON m.participant_id = p.id
                     WHERE m.conversation_id = %d AND m.id > %d
                     ORDER BY m.id ASC
                     LIMIT %d",
                    $conversation_id,
                    $since_id,
                    $limit
                )
            );
        }

        /**
         * Add a message, update conversation last_message_at, and return the message ID.
         */
        public static function add_message( $data ) {
            global $wpdb;
            $messages_table      = self::table( 'ssc_messages' );
            $conversations_table = self::table( 'ssc_conversations' );

            $now = current_time( 'mysql' );

            $defaults = array(
                'conversation_id' => 0,
                'participant_id'  => 0,
                'message'         => '',
                'message_type'    => 'text',
                'created_at'      => $now,
                'read_at'         => null,
            );

            $data = wp_parse_args( $data, $defaults );

            $wpdb->insert( $messages_table, $data );
            $message_id = $wpdb->insert_id;

            if ( $message_id ) {
                $wpdb->update(
                    $conversations_table,
                    array( 'last_message_at' => $now ),
                    array( 'id' => $data['conversation_id'] ),
                    array( '%s' ),
                    array( '%d' )
                );
            }

            return $message_id;
        }

        /**
         * List conversations with optional status filter, search, and pagination.
         */
        public static function get_conversations( $args = array() ) {
            global $wpdb;
            $table = self::table( 'ssc_conversations' );

            $defaults = array(
                'status'   => '',
                'search'   => '',
                'per_page' => 20,
                'page'     => 1,
                'orderby'  => 'last_message_at',
                'order'    => 'DESC',
            );

            $args = wp_parse_args( $args, $defaults );

            $where_clauses = array();
            $where_values  = array();

            if ( ! empty( $args['status'] ) ) {
                $where_clauses[] = 'status = %s';
                $where_values[]  = $args['status'];
            }

            if ( ! empty( $args['search'] ) ) {
                $like            = '%' . $wpdb->esc_like( $args['search'] ) . '%';
                $where_clauses[] = '(visitor_name LIKE %s OR visitor_email LIKE %s OR visitor_hash LIKE %s)';
                $where_values[]  = $like;
                $where_values[]  = $like;
                $where_values[]  = $like;
            }

            $where_sql = '';
            if ( ! empty( $where_clauses ) ) {
                $where_sql = 'WHERE ' . implode( ' AND ', $where_clauses );
            }

            $allowed_orderby = array( 'last_message_at', 'started_at', 'id', 'status', 'visitor_name' );
            $orderby = in_array( $args['orderby'], $allowed_orderby, true ) ? $args['orderby'] : 'last_message_at';
            $order   = strtoupper( $args['order'] ) === 'ASC' ? 'ASC' : 'DESC';

            $per_page = absint( $args['per_page'] );
            $page     = absint( $args['page'] );
            if ( $per_page < 1 ) {
                $per_page = 20;
            }
            if ( $page < 1 ) {
                $page = 1;
            }
            $offset = ( $page - 1 ) * $per_page;

            // Get total count.
            if ( ! empty( $where_values ) ) {
                $total = (int) $wpdb->get_var(
                    $wpdb->prepare(
                        "SELECT COUNT(*) FROM {$table} {$where_sql}",
                        ...$where_values
                    )
                );
            } else {
                $total = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$table} {$where_sql}" );
            }

            // Get rows.
            $query = "SELECT * FROM {$table} {$where_sql} ORDER BY {$orderby} {$order} LIMIT %d OFFSET %d";
            $query_values = array_merge( $where_values, array( $per_page, $offset ) );

            $rows = $wpdb->get_results(
                $wpdb->prepare( $query, ...$query_values )
            );

            return array(
                'items'      => $rows,
                'total'      => $total,
                'per_page'   => $per_page,
                'page'       => $page,
                'total_pages' => (int) ceil( $total / $per_page ),
            );
        }

        /**
         * Count conversations with status 'waiting'.
         */
        public static function get_unread_count() {
            global $wpdb;
            $table = self::table( 'ssc_conversations' );

            return (int) $wpdb->get_var(
                $wpdb->prepare(
                    "SELECT COUNT(*) FROM {$table} WHERE status = %s",
                    'waiting'
                )
            );
        }
    }

}
