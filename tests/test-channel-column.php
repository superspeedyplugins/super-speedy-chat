<?php
/**
 * Regression: ssc_conversations.channel column exists, defaults to 'website',
 * indexed, and create_tables() is idempotent.
 */
require __DIR__ . '/lib/bootstrap.php';

echo "=== conversations.channel column ===\n";

global $wpdb;
$table = $wpdb->prefix . 'ssc_conversations';

// Column exists with the right type.
$col = $wpdb->get_row( "SHOW COLUMNS FROM {$table} LIKE 'channel'" );
ssc_assert_true( $col !== null, 'channel column exists' );
if ( $col ) {
    ssc_assert_contains( 'varchar(32)', strtolower( $col->Type ), 'channel column type is varchar(32)' );
    ssc_assert_eq( 'website', $col->Default, 'channel column default = "website"' );
    ssc_assert_eq( 'NO', $col->Null, 'channel column is NOT NULL' );
}

// Index exists.
$idx = $wpdb->get_results( "SHOW INDEX FROM {$table} WHERE Key_name = 'idx_channel'" );
ssc_assert_true( ! empty( $idx ), 'idx_channel index exists' );

// DB_VERSION constant.
ssc_assert_eq( '4.0.0', SSC_DB::DB_VERSION, 'SSC_DB::DB_VERSION is 4.0.0' );

// Stored DB version option matches.
$stored = get_option( 'ssc_db_version', '0' );
ssc_assert_eq( '4.0.0', $stored, 'ssc_db_version option is 4.0.0' );

// create_tables() is idempotent — calling it again on a current install
// must not error and must not change DB_VERSION.
SSC_DB::create_tables();
$stored_after = get_option( 'ssc_db_version', '0' );
ssc_assert_eq( '4.0.0', $stored_after, 'create_tables() second call is idempotent' );

// New conversations default to channel='website'.
ssc_test_reset_tables();
$id = SSC_DB::create_conversation( array(
    'visitor_hash' => 'test_hash_' . bin2hex( random_bytes( 4 ) ),
    'visitor_name' => 'Test',
) );
ssc_assert_true( $id > 0, 'create_conversation returns an id' );
$conv = SSC_DB::get_conversation( $id );
ssc_assert_eq( 'website', $conv->channel, 'New conversation defaults to channel="website"' );

ssc_test_summary();
