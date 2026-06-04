# Super Speedy Chat — Test Suite

Lightweight regression tests for the add-on extension API + core message flow.

Each test is a self-contained PHP script (`test-*.php`) executed via WP-CLI's
`wp eval-file`. No PHPUnit dependency, no database reset between runs — just
plain WordPress with the plugin active.

The pattern mirrors `super-speedy-imports/tests/security/` — short, focused
scripts that print `PASS:` / `FAIL:` lines and exit 0/1.

---

## Running

From any directory (uses `wp` from PATH):

```bash
bash tests/run-tests.sh                       # run every test-*.php
bash tests/run-tests.sh addon-registry        # run only tests matching substring
bash tests/run-tests.sh --verbose             # print full WP-CLI output per test
```

Exit code: `0` on full pass, `1` if anything failed.

The runner sanity-checks that `wp-cli` is on PATH and the plugin is active
(it will activate `super-speedy-chat` if not).

---

## What's covered (1.08)

| Test file | Surface |
|---|---|
| `test-addon-registry.php`         | `SSC_Addons::register`, `is_active`, `get_channels`, API version gate |
| `test-message-lifecycle-hooks.php` | `ssc_visitor_message_sent`, `ssc_admin_reply_sent`, `ssc_bot_message_sent`, `ssc_conversation_status_changed` signatures |
| `test-external-inbound.php`       | `SSC_Chat::external_inbound`, `SSC_Chat::get_or_create_external_conversation` |
| `test-channel-column.php`         | `ssc_conversations.channel` column type + default + index + DB_VERSION |
| `test-settings-tabs-filter.php`   | `ssc_settings_tabs` filter, `ssc_register_settings` action |
| `test-sanitize-options-filter.php`| `ssc_sanitize_options` filter (incl. Discord webhook secret preservation) |
| `test-rest-routes-hook.php`       | `ssc_register_rest_routes` action + every documented core/Discord route still present |
| `test-discord-listener.php`       | Discord's listeners are hooked into the new lifecycle, no-network smoke |
| `test-frontend-config-filter.php` | `ssc_frontend_config` filter + `ssc_enqueue_frontend` action |

If you add a new hook to the public API, add a test for its signature here.
If a hook is renamed or removed, update the test instead of deleting it — a
failing test surfaces the breakage cleanly.

---

## Writing a new test

```php
<?php
require __DIR__ . '/lib/bootstrap.php';

echo "=== Description of what this test covers ===\n";

ssc_test_reset_tables();          // optional — TRUNCATEs ssc_* tables

// ... your assertions ...
ssc_assert_eq( $expected, $actual, 'Label that describes the check' );
ssc_assert_true( $condition,       'Boolean assertion label' );
ssc_assert_not_empty( $value,      'Value should be non-empty' );
ssc_assert_contains( $needle, $haystack, 'Substring/array-membership check' );

ssc_test_summary();              // always end with this — exits 0/1
```

Helpers live in `tests/lib/bootstrap.php`:

| Helper | Purpose |
|---|---|
| `ssc_test_pass($label)`, `ssc_test_fail($label, $detail = '')` | Manual pass/fail |
| `ssc_assert_eq($expected, $actual, $label)`                    | Strict `===` equality |
| `ssc_assert_true($value, $label)`                              | Strict `=== true` |
| `ssc_assert_not_empty($value, $label)`                         | `! empty($value)` |
| `ssc_assert_contains($needle, $haystack, $label)`              | Substring or array membership |
| `ssc_test_reset_tables()`                                      | `TRUNCATE` every `wp_ssc_*` table |
| `ssc_test_summary()`                                           | Print totals, exit 0/1 |

Counters (`ssc_test_total`, `ssc_test_failed`, `ssc_test_failures`) live in
`$GLOBALS` because `wp eval-file` executes scripts inside a method scope.

---

## Design notes

- **No PHPUnit.** WP-CLI + `eval-file` is enough for the hook/contract surface we care about, and avoids a tooling dependency.
- **No mocked network.** Tests that touch external APIs (Discord, future WhatsApp) just verify that the listener is attached + that disabled-state is a clean no-op. Live API tests live elsewhere — they're not regression tests, they're integration smoke tests.
- **`ssc_test_reset_tables()` is opt-in.** Some tests want to inspect state across multiple calls without wiping in between.
- **Use `$GLOBALS` for hook-capture variables.** `wp eval-file` runs scripts in a method scope, so plain top-level `$foo` is not the same `$foo` your closures close over. `$GLOBALS['ssc_foo']` works.
- **Failure on first test file with a failure does not stop the runner.** Every test file runs; the runner exits 1 at the end if any failed.

---

## CI / pre-commit

Not wired up yet. When this lands in CI:

```yaml
- run: wp plugin activate super-speedy-chat
- run: bash wp-content/plugins/super-speedy-chat/tests/run-tests.sh
```

Locally you can run after every refactor with:

```bash
bash tests/run-tests.sh
```
