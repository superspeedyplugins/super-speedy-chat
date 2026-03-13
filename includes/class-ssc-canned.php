<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

if ( ! class_exists( 'SSC_Canned' ) ) {

    class SSC_Canned {

        private static function table() {
            global $wpdb;
            return $wpdb->prefix . 'ssc_canned_responses';
        }

        public static function add( $data ) {
            global $wpdb;
            $now = current_time( 'mysql' );

            $defaults = array(
                'question_summary'  => '',
                'response_text'     => '',
                'category'          => '',
                'usage_count'       => 0,
                'source_message_id' => null,
                'created_by'        => get_current_user_id(),
                'created_at'        => $now,
            );

            $data = wp_parse_args( $data, $defaults );

            $wpdb->insert( self::table(), $data );
            return $wpdb->insert_id;
        }

        public static function get( $id ) {
            global $wpdb;
            return $wpdb->get_row(
                $wpdb->prepare( "SELECT * FROM " . self::table() . " WHERE id = %d", $id )
            );
        }

        public static function get_all( $args = array() ) {
            global $wpdb;
            $table = self::table();

            $defaults = array(
                'search'   => '',
                'category' => '',
                'per_page' => 50,
                'page'     => 1,
            );
            $args = wp_parse_args( $args, $defaults );

            $where  = array();
            $values = array();

            if ( ! empty( $args['search'] ) ) {
                $like     = '%' . $wpdb->esc_like( $args['search'] ) . '%';
                $where[]  = '(question_summary LIKE %s OR response_text LIKE %s)';
                $values[] = $like;
                $values[] = $like;
            }

            if ( ! empty( $args['category'] ) ) {
                $where[]  = 'category = %s';
                $values[] = $args['category'];
            }

            $where_sql = ! empty( $where ) ? 'WHERE ' . implode( ' AND ', $where ) : '';

            $per_page = absint( $args['per_page'] ) ?: 50;
            $page     = absint( $args['page'] ) ?: 1;
            $offset   = ( $page - 1 ) * $per_page;

            if ( ! empty( $values ) ) {
                $total = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$table} {$where_sql}", ...$values ) );
                $items = $wpdb->get_results( $wpdb->prepare(
                    "SELECT * FROM {$table} {$where_sql} ORDER BY created_at DESC LIMIT %d OFFSET %d",
                    ...array_merge( $values, array( $per_page, $offset ) )
                ) );
            } else {
                $total = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$table}" );
                $items = $wpdb->get_results( $wpdb->prepare(
                    "SELECT * FROM {$table} ORDER BY created_at DESC LIMIT %d OFFSET %d",
                    $per_page, $offset
                ) );
            }

            return array(
                'items'       => $items,
                'total'       => $total,
                'per_page'    => $per_page,
                'page'        => $page,
                'total_pages' => (int) ceil( $total / max( $per_page, 1 ) ),
            );
        }

        public static function update( $id, $data ) {
            global $wpdb;
            return $wpdb->update( self::table(), $data, array( 'id' => $id ), null, array( '%d' ) );
        }

        public static function delete( $id ) {
            global $wpdb;
            return $wpdb->delete( self::table(), array( 'id' => $id ), array( '%d' ) );
        }

        public static function increment_usage( $id ) {
            global $wpdb;
            $table = self::table();
            return $wpdb->query( $wpdb->prepare(
                "UPDATE {$table} SET usage_count = usage_count + 1 WHERE id = %d", $id
            ) );
        }

        public static function get_categories() {
            global $wpdb;
            $table = self::table();
            return $wpdb->get_col(
                "SELECT DISTINCT category FROM {$table} WHERE category != '' ORDER BY category ASC"
            );
        }
    }

}
