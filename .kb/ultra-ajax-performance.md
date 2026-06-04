# Ultra Ajax Performance Guide

## What is Ultra Ajax?

Ultra Ajax is a **mu-plugin** (must-use plugin) that intercepts chat API requests early in the WordPress boot process. Instead of loading the full WordPress stack -- themes, plugins, widgets, the works -- it loads only the handful of classes needed to handle the chat request, processes it, returns JSON, and exits.

The result: chat polling and message sending complete in single-digit milliseconds instead of the 200-500ms a full WordPress REST API request typically takes.

## How It Works

### Installation

On plugin activation, `ssc-fast-ajax.php` is copied from the plugin's `mu-plugins/` directory into `wp-content/mu-plugins/`. WordPress automatically loads all files in this directory before any regular plugins, which is what gives Ultra Ajax its speed advantage.

### Request Interception

When any request hits your server, `ssc-fast-ajax.php` runs before WordPress fully loads. It checks whether the request path matches one of the chat endpoints:

- `/wp-json/ssc/v1/poll` -- fetch new messages
- `/wp-json/ssc/v1/send` -- send a message
- `/wp-json/ssc/v1/session` -- start or resume a chat session
- `/wp-json/ssc/v1/auto-reply` -- trigger an automated reply

If the request does not match any of these routes, the mu-plugin returns immediately and WordPress continues loading as normal. Admin-facing routes are always handled by the full WordPress REST API so that authentication and permissions work correctly.

### Minimal Loading

For matched routes, the mu-plugin loads only the classes it needs:

- `SSC_DB` -- database access
- `SSC_Settings` -- plugin options
- `SSC_Discord` -- Discord integration
- `SSC_Canned` -- canned responses
- `SSC_LLM` -- AI auto-reply
- `SSC_Chat` -- core chat logic
- `SSC_REST` -- request handling

It skips the theme, all other plugins, widgets, sidebars, the admin bar, and everything else WordPress normally loads. The response is sent and `die()` is called before WordPress ever finishes booting.

### Graceful Fallback

If the mu-plugin is missing, disabled, or encounters an error, the chat works normally through the standard WordPress REST API. It will just be slower. There is no loss of functionality -- only a loss of speed.

### Auto-Update

When you visit the plugin settings page, the installed mu-plugin version is compared against the version shipped with the plugin. If a newer version exists, it is automatically copied into place. This is handled by `SSC_MU_Installer::check_and_update()`.

## Verifying Ultra Ajax Is Active

There are three ways to confirm Ultra Ajax is running:

1. **Settings > Status tab** -- The status tab shows whether the mu-plugin is installed and its version number.

2. **Direct URL check** -- Visit `yoursite.com/?is_ssc_ultra_ajax_active` in your browser. If active, you will see a confirmation message: "Ultra Ajax is active, bypassing full loading of WordPress for chat AJAX requests."

3. **Behaviour tab indicators** -- In the Behaviour settings tab, polling interval descriptions will indicate "Ultra Ajax is active" and show lower default values.

## Polling Intervals

With Ultra Ajax active, default polling intervals are more aggressive because each request is lightweight:

| Polling Phase | With Ultra Ajax | Without Ultra Ajax |
|---|---|---|
| Active (user engaged) | 1000ms | 2000ms |
| Idle (after ~30s inactivity) | 3000ms | 5000ms |
| Deep idle (after ~2 min) | 10000ms | 15000ms |

All intervals are configurable in the **Behaviour** settings tab.

Lower intervals mean faster message delivery at the cost of slightly more server load. Because Ultra Ajax makes each request extremely cheap (a few milliseconds of PHP execution with no theme or plugin overhead), the more aggressive defaults are safe for the vast majority of hosting environments.

## Enabling and Disabling

### Enable

The **"Enable Ultra Ajax"** checkbox is in the **General** settings tab. It is enabled by default.

### Disable

When you uncheck the setting, the mu-plugin file remains in `wp-content/mu-plugins/` but returns early on every request, letting WordPress handle chat routes through the normal REST API. This avoids file-permission issues that could prevent re-enabling later.

### Plugin Deactivation

When Super Speedy Chat is deactivated, `ssc-fast-ajax.php` is automatically deleted from `wp-content/mu-plugins/`. No cleanup required.

## Rate Limiting

Ultra Ajax includes built-in per-IP rate limiting to prevent abuse:

| Endpoint | Limit |
|---|---|
| `/send` | 15 requests/minute |
| `/session` | 10 requests/minute |
| `/auto-reply` | 3 requests/minute |

The `/poll` endpoint is not rate-limited since it is read-only and needs to fire frequently.

When a client exceeds the limit, the mu-plugin returns HTTP 429 (Too Many Requests) with an error message.

## Troubleshooting

### Chat feels slow

Check the **Status** tab to confirm the mu-plugin is installed and Ultra Ajax is enabled. If the status shows it is missing, try deactivating and reactivating the plugin, which triggers a fresh install.

### Mu-plugin fails to install

Some hosts restrict writing to `wp-content/mu-plugins/`. Verify that the directory exists and is writable by PHP (typically owned by `www-data` or your PHP-FPM user, with `0755` permissions). If the directory does not exist, the installer attempts to create it automatically.

### Mu-plugin causes issues

You can safely delete `wp-content/mu-plugins/ssc-fast-ajax.php` at any time. The chat will fall back to the standard WordPress REST API with no other side effects. If you need to re-enable Ultra Ajax later, visit the plugin settings page or deactivate/reactivate the plugin.

### Discord messages not pushing

Ultra Ajax uses `fastcgi_finish_request()` (when available) to send the HTTP response to the visitor immediately, then pushes the message to Discord after the response is sent. If your server does not support `fastcgi_finish_request()`, Discord pushes happen before the response is sent, adding a small delay. This is normal on non-FPM setups (e.g., Apache mod_php).
