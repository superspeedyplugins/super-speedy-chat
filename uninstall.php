<?php
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
    exit;
}

global $wpdb;

// Drop database tables.
$tables = array(
    $wpdb->prefix . 'ssc_messages',
    $wpdb->prefix . 'ssc_participants',
    $wpdb->prefix . 'ssc_conversations',
);

foreach ( $tables as $table ) {
    $wpdb->query( "DROP TABLE IF EXISTS {$table}" );
}

// Delete plugin options.
delete_option( 'ssc_options' );
delete_option( 'ssc_db_version' );

// Remove mu-plugin.
$mu_file = WPMU_PLUGIN_DIR . '/ssc-fast-ajax.php';
if ( file_exists( $mu_file ) ) {
    unlink( $mu_file );
}
