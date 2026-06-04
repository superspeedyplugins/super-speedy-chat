<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

if ( ! class_exists( 'SSC_Addons' ) ) {

    /**
     * Registry for SSC channel add-on plugins.
     *
     * Add-ons call SSC_Addons::register() during plugins_loaded (priority >= 20)
     * to declare themselves, their required API version, and any channel they own.
     *
     * The version reported here is consumed by:
     *  - the Status tab "Active Add-ons" panel
     *  - the version-compatibility check on load
     *  - external diagnostics
     */
    class SSC_Addons {

        /**
         * Core's add-on extension API version.
         *
         * Bumped on breaking changes to hook signatures / public helpers.
         * Deprecation policy: relaxed (deprecate but keep firing for at least two
         * minor versions). See .docs/addons-system-plan.md §13.2.
         */
        const ADDON_API_VERSION = '1.0';

        /**
         * Registered add-ons, keyed by slug.
         *
         * @var array<string,array>
         */
        private static $addons = array();

        /**
         * Register an add-on with the core plugin.
         *
         * Required keys: slug, name, version, plugin_file.
         * Optional:      channel, requires_core, requires_addon_api.
         *
         * Returns true on success, false if rejected (e.g. API version mismatch).
         * On rejection, queues an admin notice explaining why.
         */
        public static function register( $args ) {
            $defaults = array(
                'slug'               => '',
                'name'               => '',
                'version'            => '0.0.0',
                'channel'            => '',
                'requires_core'      => '0',
                'requires_addon_api' => '1.0',
                'plugin_file'        => '',
            );

            $args = wp_parse_args( $args, $defaults );

            if ( empty( $args['slug'] ) || empty( $args['name'] ) ) {
                return false;
            }

            // API version gate.
            if ( version_compare( self::ADDON_API_VERSION, $args['requires_addon_api'], '<' ) ) {
                self::queue_incompatible_notice( $args, 'addon_api', self::ADDON_API_VERSION );
                return false;
            }

            // Core version gate.
            if ( defined( 'SSC_VERSION' ) && version_compare( SSC_VERSION, $args['requires_core'], '<' ) ) {
                self::queue_incompatible_notice( $args, 'core', SSC_VERSION );
                return false;
            }

            self::$addons[ $args['slug'] ] = $args;
            return true;
        }

        /**
         * Get all registered add-ons.
         */
        public static function all() {
            return self::$addons;
        }

        /**
         * Get a single add-on by slug.
         */
        public static function get( $slug ) {
            return isset( self::$addons[ $slug ] ) ? self::$addons[ $slug ] : null;
        }

        /**
         * Whether the named add-on is registered.
         */
        public static function is_active( $slug ) {
            return isset( self::$addons[ $slug ] );
        }

        /**
         * Get all known channels (core + add-on contributions).
         *
         * Add-ons hook `ssc_channels` to append e.g. ['id'=>'whatsapp','label'=>'WhatsApp',…].
         *
         * @return array<int,array{id:string,label:string,icon:string}>
         */
        public static function get_channels() {
            $channels = array(
                array(
                    'id'    => 'website',
                    'label' => __( 'Website', 'super-speedy-chat' ),
                    'icon'  => 'dashicons-format-chat',
                ),
            );

            return apply_filters( 'ssc_channels', $channels );
        }

        /**
         * Queue an admin notice for an incompatible add-on.
         */
        private static function queue_incompatible_notice( $args, $reason, $current ) {
            add_action( 'admin_notices', function() use ( $args, $reason, $current ) {
                $name = esc_html( $args['name'] );
                if ( $reason === 'addon_api' ) {
                    $msg = sprintf(
                        /* translators: 1: add-on name, 2: required API version, 3: current API version */
                        __( '%1$s requires Super Speedy Chat add-on API %2$s or newer (current: %3$s). Please update Super Speedy Chat.', 'super-speedy-chat' ),
                        $name,
                        esc_html( $args['requires_addon_api'] ),
                        esc_html( $current )
                    );
                } else {
                    $msg = sprintf(
                        /* translators: 1: add-on name, 2: required core version, 3: current core version */
                        __( '%1$s requires Super Speedy Chat %2$s or newer (current: %3$s).', 'super-speedy-chat' ),
                        $name,
                        esc_html( $args['requires_core'] ),
                        esc_html( $current )
                    );
                }
                echo '<div class="notice notice-error"><p>' . $msg . '</p></div>';
            } );
        }
    }

}
