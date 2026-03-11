<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

if ( ! class_exists( 'SSC_Settings' ) ) {

    class SSC_Settings {

        /**
         * Cached options array.
         */
        private static $options_cache = null;

        /**
         * Get a single option value from the ssc_options array.
         *
         * @param string $key     Option key.
         * @param mixed  $default Default value if not set.
         * @return mixed
         */
        public static function get_option( $key, $default = null ) {
            if ( self::$options_cache === null ) {
                self::$options_cache = get_option( 'ssc_options', array() );
                if ( ! is_array( self::$options_cache ) ) {
                    self::$options_cache = array();
                }
            }

            if ( array_key_exists( $key, self::$options_cache ) ) {
                return self::$options_cache[ $key ];
            }

            return $default;
        }
    }

}
