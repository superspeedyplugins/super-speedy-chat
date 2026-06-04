<?php
/**
 * Shared test bootstrap — assertion helpers + cleanup utilities.
 *
 * Designed to be loaded at the top of every test-*.php via:
 *   require __DIR__ . '/lib/bootstrap.php';
 *
 * Tests must be executed with `wp eval-file` so WP-CLI loads WordPress
 * (and the plugin) before the script runs.
 *
 * Style mirrors super-speedy-imports/tests/security/test-access-controls.php:
 * pass/fail counters live in $GLOBALS because `wp eval-file` runs scripts
 * inside a method scope.
 */

if ( ! defined( 'WP_CLI' ) || ! WP_CLI ) {
    fwrite( STDERR, "Tests must be run via WP-CLI: wp eval-file tests/test-*.php\n" );
    exit( 2 );
}

if ( ! function_exists( 'ssc_test_pass' ) ) {

    $GLOBALS['ssc_test_total']    = 0;
    $GLOBALS['ssc_test_failed']   = 0;
    $GLOBALS['ssc_test_failures'] = array();

    function ssc_test_pass( $label ) {
        $GLOBALS['ssc_test_total']++;
        echo "PASS: $label\n";
    }

    function ssc_test_fail( $label, $detail = '' ) {
        $GLOBALS['ssc_test_total']++;
        $GLOBALS['ssc_test_failed']++;
        $GLOBALS['ssc_test_failures'][] = $label . ( $detail !== '' ? ' - ' . $detail : '' );
        echo 'FAIL: ' . $label . ( $detail !== '' ? ' - ' . $detail : '' ) . "\n";
    }

    function ssc_assert_true( $value, $label ) {
        if ( $value === true ) {
            ssc_test_pass( $label );
        } else {
            ssc_test_fail( $label, 'expected true, got ' . var_export( $value, true ) );
        }
    }

    function ssc_assert_eq( $expected, $actual, $label ) {
        if ( $expected === $actual ) {
            ssc_test_pass( $label );
        } else {
            ssc_test_fail( $label, 'expected ' . var_export( $expected, true ) . ' got ' . var_export( $actual, true ) );
        }
    }

    function ssc_assert_not_empty( $value, $label ) {
        if ( ! empty( $value ) ) {
            ssc_test_pass( $label );
        } else {
            ssc_test_fail( $label, 'expected non-empty, got ' . var_export( $value, true ) );
        }
    }

    function ssc_assert_contains( $needle, $haystack, $label ) {
        if ( is_array( $haystack ) && in_array( $needle, $haystack, true ) ) {
            ssc_test_pass( $label );
        } elseif ( is_string( $haystack ) && strpos( $haystack, $needle ) !== false ) {
            ssc_test_pass( $label );
        } else {
            ssc_test_fail( $label, 'needle ' . var_export( $needle, true ) . ' not found in ' . var_export( $haystack, true ) );
        }
    }

    /**
     * Print summary and exit with 0/1. Call at end of every test file.
     */
    function ssc_test_summary() {
        $total  = $GLOBALS['ssc_test_total'];
        $failed = $GLOBALS['ssc_test_failed'];
        echo "\n---\n";
        echo "Total: $total, Failed: $failed\n";
        if ( $failed > 0 ) {
            echo "Failures:\n";
            foreach ( $GLOBALS['ssc_test_failures'] as $f ) {
                echo "  - $f\n";
            }
            exit( 1 );
        }
        exit( 0 );
    }

    /**
     * TRUNCATE the SSC tables so each test starts from a known empty state.
     * The plugin's own tables only — WordPress core tables are untouched.
     */
    function ssc_test_reset_tables() {
        global $wpdb;
        $tables = array(
            $wpdb->prefix . 'ssc_messages',
            $wpdb->prefix . 'ssc_participants',
            $wpdb->prefix . 'ssc_discord_threads',
            $wpdb->prefix . 'ssc_conversations',
        );
        foreach ( $tables as $t ) {
            // Suppress errors if table doesn't exist (fresh activation).
            $wpdb->query( "TRUNCATE TABLE {$t}" );
        }
    }
}
