<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

if ( ! class_exists( 'SSC_MU_Installer' ) ) {

    class SSC_MU_Installer {

        const MU_FILENAME = 'ssc-fast-ajax.php';

        /**
         * Get the source path (shipped with the plugin).
         */
        private static function source_path() {
            return SSC_DIR . 'mu-plugins/' . self::MU_FILENAME;
        }

        /**
         * Get the destination path (wp-content/mu-plugins/).
         */
        private static function destination_path() {
            return WPMU_PLUGIN_DIR . '/' . self::MU_FILENAME;
        }

        /**
         * Install the mu-plugin by copying from source to destination.
         * Called on plugin activation.
         */
        public static function install() {
            $source      = self::source_path();
            $destination = self::destination_path();

            if ( ! file_exists( $source ) ) {
                return false;
            }

            if ( ! is_dir( WPMU_PLUGIN_DIR ) ) {
                if ( ! mkdir( WPMU_PLUGIN_DIR, 0755, true ) ) {
                    error_log( 'Super Speedy Chat: Failed to create mu-plugins directory.' );
                    return false;
                }
            }

            $result = copy( $source, $destination );
            if ( ! $result ) {
                error_log( 'Super Speedy Chat: Failed to copy mu-plugin. Error: ' . ( error_get_last()['message'] ?? 'unknown' ) );
            }

            return $result;
        }

        /**
         * Remove the mu-plugin.
         * Called on plugin deactivation.
         */
        public static function uninstall() {
            $destination = self::destination_path();

            if ( file_exists( $destination ) ) {
                unlink( $destination );
            }
        }

        /**
         * Check if source is newer than destination and update if so.
         * Called when the settings page is loaded.
         */
        public static function check_and_update() {
            $source      = self::source_path();
            $destination = self::destination_path();

            if ( ! file_exists( $source ) ) {
                return;
            }

            // Get version from source.
            $source_data    = get_file_data( $source, array( 'Version' => 'Version' ) );
            $source_version = $source_data['Version'];

            // Get version from destination (if exists).
            if ( file_exists( $destination ) ) {
                $dest_data    = get_file_data( $destination, array( 'Version' => 'Version' ) );
                $dest_version = $dest_data['Version'];
            } else {
                $dest_version = '0.0.0';
            }

            // Copy if source is newer.
            if ( version_compare( $source_version, $dest_version, '>' ) ) {
                self::install();
            }
        }

        /**
         * Check if the mu-plugin is installed.
         */
        public static function is_installed() {
            return file_exists( self::destination_path() );
        }

        /**
         * Get the installed version, or false if not installed.
         */
        public static function get_installed_version() {
            $destination = self::destination_path();

            if ( ! file_exists( $destination ) ) {
                return false;
            }

            $data = get_file_data( $destination, array( 'Version' => 'Version' ) );
            return ! empty( $data['Version'] ) ? $data['Version'] : false;
        }
    }

}
