<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

if ( ! class_exists( 'SSC_Email' ) ) {

    class SSC_Email {

        /**
         * Send email notification to admin when a new conversation starts.
         *
         * @param object $conversation The conversation row.
         */
        public static function notify_admin_new_conversation( $conversation ) {
            $enabled = SSC_Settings::get_option( 'ssc_admin_email_enabled', true );
            if ( ! $enabled ) {
                return;
            }

            $admin_email = SSC_Settings::get_option( 'ssc_admin_email', get_option( 'admin_email' ) );
            if ( empty( $admin_email ) ) {
                return;
            }

            $site_name = get_bloginfo( 'name' );
            $subject   = sprintf(
                /* translators: %s: site name */
                __( '[%s] New chat conversation started', 'super-speedy-chat' ),
                $site_name
            );

            $admin_url = admin_url( 'admin.php?page=ssc&conversation_id=' . $conversation->id );

            $body  = sprintf( __( 'A new chat conversation has started on %s.', 'super-speedy-chat' ), $site_name ) . "\n\n";
            $body .= sprintf( __( 'Visitor: %s', 'super-speedy-chat' ), $conversation->visitor_name ) . "\n";

            if ( ! empty( $conversation->last_page_url ) ) {
                $body .= sprintf( __( 'Page: %s', 'super-speedy-chat' ), $conversation->last_page_url ) . "\n";
            }

            $body .= "\n" . sprintf( __( 'Reply here: %s', 'super-speedy-chat' ), $admin_url ) . "\n";

            $from_name = SSC_Settings::get_option( 'ssc_email_from_name', $site_name );
            $headers   = array( 'Content-Type: text/plain; charset=UTF-8' );

            if ( $from_name ) {
                $from_email = get_option( 'admin_email' );
                $headers[]  = 'From: ' . $from_name . ' <' . $from_email . '>';
            }

            wp_mail( $admin_email, $subject, $body, $headers );
        }

        /**
         * Send email to visitor when admin replies and visitor is offline.
         *
         * @param object $conversation The conversation row.
         * @param string $reply_text   The admin's reply text.
         */
        public static function notify_visitor_reply( $conversation, $reply_text ) {
            $enabled = SSC_Settings::get_option( 'ssc_visitor_email_enabled', true );
            if ( ! $enabled ) {
                return;
            }

            if ( empty( $conversation->visitor_email ) ) {
                return;
            }

            $site_name = get_bloginfo( 'name' );
            $subject   = sprintf(
                /* translators: %s: site name */
                __( '[%s] You have a new reply', 'super-speedy-chat' ),
                $site_name
            );

            $site_url = home_url( '/' );

            $body  = sprintf( __( 'Hi %s,', 'super-speedy-chat' ), $conversation->visitor_name ) . "\n\n";
            $body .= __( 'You have a new reply to your chat:', 'super-speedy-chat' ) . "\n\n";
            $body .= '> ' . $reply_text . "\n\n";
            $body .= sprintf( __( 'Visit %s to continue the conversation.', 'super-speedy-chat' ), $site_url ) . "\n";

            $from_name = SSC_Settings::get_option( 'ssc_email_from_name', $site_name );
            $headers   = array( 'Content-Type: text/plain; charset=UTF-8' );

            if ( $from_name ) {
                $from_email = get_option( 'admin_email' );
                $headers[]  = 'From: ' . $from_name . ' <' . $from_email . '>';
            }

            wp_mail( $conversation->visitor_email, $subject, $body, $headers );
        }
    }

}
