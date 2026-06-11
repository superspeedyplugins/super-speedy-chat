# Localhost: chat textarea shows a flashing cursor but won't accept keystrokes

## Symptom

Fresh install of the plugin on localhost. Clicking the bubble opens the
dialog, the caret blinks inside the textarea, but typing produces nothing.
No JavaScript errors in the console. Same plugin version works on the live
site.

## Root cause

`sanitize_options()` in `includes/class-ssc-admin.php` was rebuilding the
entire saved `ssc_options` array from scratch on every write, forcing every
recognised key to a hard-coded default when it was missing from `$input`.
Numeric keys defaulted to `0`. That callback fires on **every**
`update_option('ssc_options', …)`, not just on Settings-form submissions —
so any partial programmatic write would wipe everything else.

The trigger on first install is `SSC_Discord::get_webhook_secret()` in
`includes/class-ssc-discord.php`. It lazily generates a webhook secret the
first time it's needed (rendered on the Discord settings tab, also fetched
on the incoming-webhook endpoint) and writes it back:

```php
$options['ssc_discord_webhook_secret'] = $secret;
update_option( 'ssc_options', $options );
```

On a fresh install `$options` starts as `array()`, so `update_option` is
called with effectively just the new secret. WordPress fires
`sanitize_option_ssc_options` → `SSC_Admin::sanitize_options($input)` →
`$input` contains no number keys → every numeric field is written to the
DB as `0`. After that point, `SSC_Settings::get_option('ssc_max_message_length', 500)`
returns `0` (because the key now exists), and the front-end ships:

```html
<textarea id="ssc-input" rows="2" maxlength="0"></textarea>
```

`maxlength="0"` is a perfectly valid attribute — the browser silently
refuses every keystroke while still painting the caret. No JS error
because nothing in the JS side fails; the textarea is just doing exactly
what the HTML told it to.

The live site doesn't hit this because either the Discord tab was visited
*after* a Settings-form save populated proper values, or its options were
seeded before the bug existed.

## Fix

Two changes in `SSC_Admin::sanitize_options()` (class-ssc-admin.php:704):

1. **Start the `$sanitized` array from the existing saved options**, not
   from `array()`. Partial updates now preserve every untouched key.
2. **Only overwrite a key when it's present in `$input`.** The previous
   `isset( $input[$k] ) ? sanitize(...) : <hardcoded default>` ternaries
   are gone for the text, textarea, select, sound, and number groups.

Checkboxes are the only group that needs a quirk: unchecked checkboxes
don't appear in `$_POST` at all, so on a real Settings-form submission we
must coerce missing checkbox keys to `false`. We detect that by checking
`$_POST['option_page'] === 'ssc_option_group'` (set by `settings_fields()`
inside the form, absent on programmatic `update_option` calls).

## Why the JS fallback isn't the right place to fix this

`chat-bubble.js:135` uses `(config.max_message_length || 500)` to fall back
to 500 if the value is falsy. `wp_localize_script()` casts scalars to
strings before serialising, so PHP integer `0` arrives as JS string `"0"`,
which is truthy — `"0" || 500 === "0"` — and the fallback never fires.
Adding `parseInt(…, 10) || 500` would mask the symptom but leave every
other `0`-valued setting (`ssc_poll_interval`, `ssc_admin_timeout`, etc.)
broken in its own way. Fixing the server-side cause keeps every setting
honest and removes the need for per-field guards in JS.

## How to recover an already-broken install

The options row is already poisoned, so the fix doesn't retroactively
unbreak existing localhost installs — it only prevents the wipe on future
saves. Either:

- Visit wp-admin → Super Speedy Chat → settings, hit "Save Changes"
  once — the form now submits proper values and they stick; or
- Delete the `ssc_options` row via `wp_options` so the defaults are
  rebuilt from scratch on the next read.
