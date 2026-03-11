<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

if ( ! class_exists( 'SSC_Session' ) ) {

    class SSC_Session {

        const COOKIE_NAME = 'ssc_visitor_hash';
        const COOKIE_DAYS = 365;

        /**
         * Get existing visitor hash from cookie, or create and set a new one.
         */
        public static function get_or_create_visitor_hash() {
            if ( isset( $_COOKIE[ self::COOKIE_NAME ] ) && ! empty( $_COOKIE[ self::COOKIE_NAME ] ) ) {
                return sanitize_text_field( $_COOKIE[ self::COOKIE_NAME ] );
            }

            $hash = bin2hex( random_bytes( 32 ) );

            $expire = time() + ( self::COOKIE_DAYS * DAY_IN_SECONDS );
            $secure = is_ssl();

            setcookie(
                self::COOKIE_NAME,
                $hash,
                $expire,
                '/',
                '',
                $secure,
                true
            );

            // Make available for the current request.
            $_COOKIE[ self::COOKIE_NAME ] = $hash;

            return $hash;
        }

        /**
         * Get visitor hash from cookie without creating one.
         */
        public static function get_visitor_hash() {
            if ( isset( $_COOKIE[ self::COOKIE_NAME ] ) && ! empty( $_COOKIE[ self::COOKIE_NAME ] ) ) {
                return sanitize_text_field( $_COOKIE[ self::COOKIE_NAME ] );
            }

            return null;
        }

        /**
         * Get or create an active conversation for the given visitor hash.
         */
        public static function get_or_create_conversation( $visitor_hash ) {
            $conversation = SSC_DB::get_conversation_by_hash( $visitor_hash );

            if ( $conversation && ! in_array( $conversation->status, array( 'closed', 'archived' ), true ) ) {
                return $conversation;
            }

            $referrer = isset( $_SERVER['HTTP_REFERER'] ) ? esc_url_raw( $_SERVER['HTTP_REFERER'] ) : null;

            $data = array(
                'visitor_hash' => $visitor_hash,
                'ip_address'   => isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( $_SERVER['REMOTE_ADDR'] ) : null,
                'user_agent'   => isset( $_SERVER['HTTP_USER_AGENT'] ) ? substr( sanitize_text_field( $_SERVER['HTTP_USER_AGENT'] ), 0, 500 ) : null,
                'referrer_url' => $referrer,
            );

            $conversation_id = SSC_DB::create_conversation( $data );

            return SSC_DB::get_conversation( $conversation_id );
        }

        /**
         * Find or create a visitor participant in a conversation.
         */
        public static function get_or_create_participant( $conversation_id, $visitor_hash, $user_id = null ) {
            $participant = SSC_DB::get_participant( $conversation_id, $visitor_hash, 'visitor' );

            if ( $participant ) {
                return $participant;
            }

            $display_name = self::get_display_name( $user_id );

            $data = array(
                'conversation_id'  => $conversation_id,
                'participant_type' => 'visitor',
                'user_id'          => $user_id,
                'visitor_hash'     => $visitor_hash,
                'display_name'     => $display_name,
            );

            $participant_id = SSC_DB::add_participant( $data );

            return SSC_DB::get_participant( $conversation_id, $visitor_hash, 'visitor' );
        }

        /**
         * Link a WordPress user to an anonymous visitor's conversation and participant.
         */
        public static function link_user( $visitor_hash, $user_id ) {
            $conversation = SSC_DB::get_conversation_by_hash( $visitor_hash );

            if ( ! $conversation ) {
                return false;
            }

            SSC_DB::update_conversation( $conversation->id, array(
                'user_id' => $user_id,
            ) );

            $participant = SSC_DB::get_participant( $conversation->id, $visitor_hash, 'visitor' );

            if ( $participant ) {
                global $wpdb;
                $table = $wpdb->prefix . 'ssc_participants';

                $display_name = self::get_display_name( $user_id );

                $wpdb->update(
                    $table,
                    array(
                        'user_id'      => $user_id,
                        'display_name' => $display_name,
                    ),
                    array( 'id' => $participant->id ),
                    array( '%d', '%s' ),
                    array( '%d' )
                );
            }

            return true;
        }

        /**
         * Get display name for a user ID, or 'Visitor' if not available.
         */
        public static function get_display_name( $user_id = null ) {
            if ( $user_id ) {
                $user = get_userdata( $user_id );
                if ( $user ) {
                    return $user->display_name;
                }
            }

            return 'Visitor';
        }
    }

}
