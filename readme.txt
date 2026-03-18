=== Super Speedy Chat ===
Contributors: dhilditch
Donate link: https://www.superspeedyplugins.com/
Tags: live chat, chat, customer support, discord, fast ajax
Requires at least: 4.7
Tested up to: 6.7
Stable tag: 1.05.2
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
