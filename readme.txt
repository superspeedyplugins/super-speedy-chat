=== Super Speedy Chat ===
Contributors: dhilditch
Donate link: https://www.superspeedyplugins.com/
Tags: live chat, chat, customer support, discord, fast ajax
Requires at least: 4.7
Tested up to: 6.7
Stable tag: 1.10
Requires PHP: 7.0
License: GPLv3 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

The fastest live chat plugin for WordPress. Ultra-fast AJAX via mu-plugin, visitor-to-admin chat with email fallback and Discord integration.

== Description ==

Super Speedy Chat is a live chat system for WordPress where visitors chat with site admins. It uses the Super Speedy ultra-fast mu-plugin AJAX pattern for sub-100ms response times, providing a modern chat bubble on the front-end and a full admin interface for managing conversations.

**Key Features:**

* Ultra-fast AJAX via mu-plugin — chat polling bypasses full WordPress load
* Front-end chat bubble with CSS animations and sound effects
* Admin conversation list and reply interface in wp-admin
* Cookie-based anonymous visitor sessions with email collection fallback
* Instant bidirectional Discord integration — reply to visitors from Discord
* Canned responses system for quick admin replies
* Email notifications for new conversations and offline replies
* Rate limiting, nonce verification, and honeypot anti-spam
* WordPress Customizer integration for appearance settings
* Mobile-responsive chat bubble

== Frequently Asked Questions ==

= Is this PHP 8 compatible? =

Yes, and it's MySQL 8, MariaDB and Percona DB compatible too.

== Changelog ==

= 1.10 (11th June 2026) =
* Fixed a bug where settings fields could be saved as 0 instead of their correct defaults on a fresh install.

= 1.09 (5th June 2026) =
* The "Require login to chat" setting is now actually enforced. Previously the checkbox existed but had no effect; now, when enabled, all visitor endpoints (session/send/poll/email/auto-reply) return 401 for anonymous visitors, and the chat widget replaces the message input with a log in / create account invitation. Conversations from logged-in users are linked to their account as before.
* With Require Login enabled, chat requests route through the standard REST API instead of the Ultra Ajax fast path (mu-plugin updated to 1.2.0), since they need real WordPress authentication. Anonymous-friendly sites are unaffected.
* Added `SSC_Settings::flush_cache()` for re-reading options mid-request.
* The widget footer link no longer claims the plugin is free ("Powered by Super Speedy Chat").
* New regression test: tests/test-require-login.php.

= 1.08 (27th May 2026) =
* Introduced the add-on extension API (`SSC_ADDON_API_VERSION = 1.0`) — channel integrations can now ship as separate plugins (WhatsApp, Slack, Telegram, etc.) and plug into core via hooks instead of patching it
* Added `SSC_Addons` registry class — add-ons call `SSC_Addons::register()` to declare slug, version, channel, and minimum API version requirements
* Added PHP message-lifecycle hooks: `ssc_visitor_message_sent`, `ssc_admin_reply_sent`, `ssc_bot_message_sent`, `ssc_conversation_created`, `ssc_conversation_status_changed`
* Added `ssc_channels` filter for channel registry, `ssc_settings_tabs` + `ssc_register_settings` + `ssc_sanitize_options` for shared settings, `ssc_register_rest_routes` for REST extension, `ssc_conversation_sidebar` + `ssc_conversation_reply_footer` for admin UI panels, `ssc_frontend_config` + `ssc_enqueue_frontend` for bubble extension
* Added `SSC_Chat::external_inbound()` and `SSC_Chat::get_or_create_external_conversation()` public helpers so channel add-ons can route inbound messages into core without duplicating participant/message logic
* Added `channel` column to `ssc_conversations` (default `'website'`) with one-shot backfill from `ssc_discord_threads`
* Added front-end JS hook system at `window.ssc.hooks` (inline ~40-line lib, matches `wp.hooks` API surface) firing `ssc.bubble.opened/closed/rendered/messageSent/messageReceived` and a `ssc.bubble.welcomeMessage` filter
* Added admin JS hook events `ssc.admin.conversationsLoaded` and `ssc.admin.conversationRowRendered` via `wp.hooks` (admin.js now declares wp-hooks as a dependency)
* Refactored Discord integration to use the new extension API — moved tab/section/fields registration, REST routes, message-lifecycle listeners, and admin renderers from core classes into `class-ssc-discord.php`. Discord is now the reference implementation of the add-on API while remaining bundled with core.
* Converted `SSC_Admin::field_*` and `section_*` settings renderers to static so add-ons can reuse them
* Database schema bumped to v4.0.0
* Note: hook signatures may change without deprecation notices until the first commercial add-on ships. Once that happens the documented relaxed-deprecation policy applies (deprecations stay live for at least two minor versions).

= 1.07.1 (20th May 2026) =
* Updated the Super Speedy Settings page: added a "Recheck Licenses" button above the licence table so customers have a clear way to refresh licence status after a renewal or upgrade without scrolling down to the licence-key field. The button is disabled until a licence key is entered/saved and re-enables as you type.
* The Recheck flow now also force-bypasses the auth-server's own 1-hour licence cache (via a `force=1` flag on the `wpiapi/check_product_key` call), so renewals/upgrades that completed less than an hour before a recheck no longer show as expired/exceeded. Normal admin page loads continue to use both caches as before — only an explicit Recheck click bypasses them. (Lives in the `super-speedy-settings` submodule, so the change propagates to every Super Speedy plugin.)

= 1.07 (1st April 2026) =
* Added LLM auto-reply system — classifies visitor questions against canned responses using OpenAI or Anthropic and auto-sends the best match when admins are away
* Added LLM Auto-Reply admin settings tab with provider, API key, model, and system prompt configuration
* Added conversation assignment — assign conversations to specific admin users from the detail sidebar
* Added "Assigned To" column and assignee filter (All / Unassigned / Assigned to Me) to conversation list
* Added 3-tier adaptive polling: active, idle (30s), and deep idle (2min) with configurable intervals
* Added Ultra Ajax-aware polling defaults — faster intervals when mu-plugin is active (1s/3s/10s vs 2s/5s/15s)
* Added admin notification sounds — plays sound when new visitor messages arrive or waiting count increases
* Added sound customization settings — message sound selection, open/close sound selection, and volume slider with preview buttons
* Added honeypot anti-spam field to chat form with server-side validation in both REST and mu-plugin paths
* Added auto-reply REST endpoint with rate limiting (3/min) for both normal and fast-ajax paths
* Added bot message support (send_bot_message) for auto-reply and canned response delivery
* Added 7 KB documentation guides: Quick Start, Customizing Appearance, Canned Responses, LLM Auto-Reply, Managing Conversations, Email Notifications, Ultra Ajax & Performance
* Database schema updated to v3.0.0 (added assigned_to column on conversations table)
* Bumped mu-plugin to v1.1.0 with honeypot check, auto-reply route, and updated rate limiting

= 1.06 (23rd March 2026) =
* Improved mobile chat experience — widget now goes full-screen on phones so top messages are always visible
* Added close button (X) in chat header for easy exit on mobile
* Fixed background page scrolling when chat is open on mobile devices
* Added touch scroll containment so finger scrolling stays within the chat messages
* Fixed chat window overflowing above screen when virtual keyboard opens on mobile

= 1.05 (18th March 2026) =
* Added instant bidirectional Discord integration — visitor messages pushed to Discord threads, Discord replies relayed back to WordPress in real-time
* Added companion Node.js Discord bot (`bot/` directory) with Gateway connection
* Added Discord admin settings tab with step-by-step setup guide
* Added canned responses system with database table and admin CRUD interface
* Added "Save as Canned" button on admin messages in conversation detail
* Added canned responses admin tab with guide and management UI
* Added REST endpoints for canned response management and Discord incoming messages
* Added automatic database upgrade on version mismatch
* Improved admin chat interface with new styles and enhanced JS
* Version bumped to 1.05.2

= 1.03 (11th March 2026) =
* Added rate limiting on visitor endpoints to prevent chat spam
* Added rate limiting on session creation to prevent abuse

= 1.02 (11th March 2026) =
* Connected Customizer settings to front-end (colours, header image, window title, trigger icon)
* Fixed Customizer section not appearing by registering outside is_admin() context

= 1.01 (11th March 2026) =
* Merged settings into admin page with tabbed interface following SSS pattern
* Added display name control (shared name or per-admin individual names)
* Added WordPress Customizer section for appearance settings
* Stripped class-ssc-settings.php down to get_option() helper only

= 1.00 (11th March 2026) =
* Initial commit
