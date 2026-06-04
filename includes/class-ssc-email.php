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

            // If we can resolve the visitor's email (one they provided, or their
            // account email when logged in), set Reply-To so a plain "Reply" reaches them.
            $reply_to = self::format_address( $conversation->visitor_name, self::resolve_visitor_email( $conversation ) );
            if ( $reply_to ) {
                $headers[] = 'Reply-To: ' . $reply_to;
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

            // Reply-To the support address so a visitor's email reply reaches the team.
            $admin_reply = SSC_Settings::get_option( 'ssc_admin_email', get_option( 'admin_email' ) );
            $reply_to    = self::format_address( $from_name, $admin_reply );
            if ( $reply_to ) {
                $headers[] = 'Reply-To: ' . $reply_to;
            }

            wp_mail( $conversation->visitor_email, $subject, $body, $headers );
        }

        /**
         * Notify the admin when a visitor leaves their email address.
         *
         * This is the email an admin can reply to directly: its Reply-To is the
         * visitor, so hitting "Reply" sends straight to them. Fired from
         * SSC_Chat::save_visitor_email() once an address is on file.
         *
         * @param object $conversation The conversation row (must include visitor_email).
         */
        public static function notify_admin_visitor_email( $conversation ) {
            if ( ! SSC_Settings::get_option( 'ssc_admin_email_enabled', true ) ) {
                return;
            }
            if ( empty( $conversation->visitor_email ) ) {
                return;
            }

            $admin_email = SSC_Settings::get_option( 'ssc_admin_email', get_option( 'admin_email' ) );
            if ( empty( $admin_email ) ) {
                return;
            }

            $site_name = get_bloginfo( 'name' );
            $name      = ! empty( $conversation->visitor_name ) ? $conversation->visitor_name : __( 'A visitor', 'super-speedy-chat' );
            $subject   = sprintf(
                /* translators: %s: site name */
                __( '[%s] A chat visitor left their email — you can reply directly', 'super-speedy-chat' ),
                $site_name
            );

            $admin_url = admin_url( 'admin.php?page=ssc&conversation_id=' . $conversation->id );

            $body  = sprintf( __( '%1$s left their email address in a chat on %2$s.', 'super-speedy-chat' ), $name, $site_name ) . "\n\n";
            $body .= sprintf( __( 'Email: %s', 'super-speedy-chat' ), $conversation->visitor_email ) . "\n\n";
            $body .= __( 'Reply directly to this email and it will go straight to the visitor, or continue the chat in your dashboard:', 'super-speedy-chat' ) . "\n";
            $body .= $admin_url . "\n";

            $from_name = SSC_Settings::get_option( 'ssc_email_from_name', $site_name );
            $headers   = array( 'Content-Type: text/plain; charset=UTF-8' );

            if ( $from_name ) {
                $headers[] = 'From: ' . $from_name . ' <' . get_option( 'admin_email' ) . '>';
            }

            $reply_to = self::format_address( $name, $conversation->visitor_email );
            if ( $reply_to ) {
                $headers[] = 'Reply-To: ' . $reply_to;
            }

            wp_mail( $admin_email, $subject, $body, $headers );
        }

        /**
         * Resolve the best contact email for a conversation's visitor.
         *
         * Prefers an address the visitor explicitly provided; falls back to their
         * WordPress account email when the conversation is linked to a logged-in user.
         *
         * Note: in Ultra Ajax mode the fast path does not link logged-in users
         * (user_id stays null), so the fallback only applies on the standard REST path.
         *
         * @param object $conversation The conversation row.
         * @return string Email address, or '' if none.
         */
        private static function resolve_visitor_email( $conversation ) {
            if ( ! empty( $conversation->visitor_email ) ) {
                return $conversation->visitor_email;
            }
            if ( ! empty( $conversation->user_id ) ) {
                $user = get_userdata( (int) $conversation->user_id );
                if ( $user && ! empty( $user->user_email ) ) {
                    return $user->user_email;
                }
            }
            return '';
        }

        /**
         * Build a safe "Name <email>" header value, or '' if the email is invalid.
         *
         * Strips CR/LF from the display name to prevent header injection.
         *
         * @param string $name  Display name (may be empty).
         * @param string $email Email address.
         * @return string
         */
        private static function format_address( $name, $email ) {
            $email = sanitize_email( (string) $email );
            if ( ! is_email( $email ) ) {
                return '';
            }
            $name = trim( preg_replace( '/[\r\n]+/', ' ', (string) $name ) );
            return $name !== '' ? sprintf( '%s <%s>', $name, $email ) : $email;
        }
    }

}
