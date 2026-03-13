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
    $wpdb->prefix . 'ssc_canned_responses',
    $wpdb->prefix . 'ssc_discord_threads',
);

foreach ( $tables as $table ) {
    $wpdb->query( "DROP TABLE IF EXISTS {$table}" );
}

// Delete plugin options.
delete_option( 'ssc_options' );
delete_option( 'ssc_db_version' );
delete_option( 'ssc_customizer' );

// Clear cron.
wp_clear_scheduled_hook( 'ssc_discord_sync' );

// Remove mu-plugin.
$mu_file = WPMU_PLUGIN_DIR . '/ssc-fast-ajax.php';
if ( file_exists( $mu_file ) ) {
    unlink( $mu_file );
}
