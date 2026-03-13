# Super Speedy Chat - Full Rewrite Plan

## Overview

Rewrite the old "Marketer Pro" plugin as **Super Speedy Chat** — a live chat system for WordPress where visitors chat with site admins. Uses the Super Speedy ultra-fast mu-plugin AJAX pattern for sub-100ms response times, the Super Speedy Settings submodule, and a modern admin interface.

---

## Architecture

### Core Principles
- **Ultra-fast AJAX via mu-plugin** — chat polling and message sending bypass full WP load
- **Cookie-based anonymous sessions** with encouragement to log in or provide email
- **Channel-per-visitor model** — each visitor gets a unique conversation channel
- **Admin reply system** — wp-admin interface for managing live and historical chats
- **Extensible** — hooks for LLM integration, messaging platforms, CRM data

### File Structure

```
super-speedy-chat/
├── super-speedy-chat.php              # Main plugin file (header, activation, hooks)
├── includes/
│   ├── class-ssc-db.php               # Database table creation and queries
│   ├── class-ssc-chat.php             # Core chat logic (send, receive, channels)
│   ├── class-ssc-session.php          # Visitor session management (cookies, user linking)
│   ├── class-ssc-admin.php            # WP Admin chat management page
│   ├── class-ssc-settings.php         # Settings page (extends Super Speedy Settings pattern)
│   ├── class-ssc-email.php            # Email notification system
│   ├── class-ssc-mu-installer.php     # mu-plugin install/update logic
│   ├── class-ssc-rest.php             # REST API route definitions (used by mu-plugin)
│   ├── class-ssc-llm.php             # LLM integration (v2)
│   └── class-ssc-integrations.php     # External messaging integrations (v2/v3)
├── mu-plugins/
│   └── ssc-fast-ajax.php              # Ultra-fast AJAX handler (copied to wp-content/mu-plugins/)
├── admin/
│   ├── admin.css                      # Admin chat interface styles
│   └── admin.js                       # Admin chat interface JS (live polling, reply UI)
├── assets/
│   ├── chat-bubble.css                # Front-end chat bubble styles + animations
│   ├── chat-bubble.js                 # Front-end chat JS (vanilla JS, no jQuery dependency)
│   ├── sounds/
│   │   ├── new-message.mp3
│   │   ├── chat-open.mp3
│   │   └── chat-close.mp3
│   └── images/
│       └── chat-icon.png
├── super-speedy-settings/             # Git submodule
├── languages/
│   └── super-speedy-chat.pot
├── readme.txt
├── uninstall.php
└── .ai/
    └── plan.md                        # This file
```

### Database Schema

**Table: `{prefix}ssc_conversations`**
| Column | Type | Description |
|--------|------|-------------|
| id | BIGINT UNSIGNED AUTO_INCREMENT | Primary key |
| visitor_hash | VARCHAR(64) | Cookie-based visitor identifier |
| user_id | BIGINT UNSIGNED NULL | WP user ID if logged in or later linked |
| visitor_name | VARCHAR(100) | Display name (from cookie or WP user) |
| visitor_email | VARCHAR(255) NULL | Email if provided |
| status | ENUM('active','waiting','closed','archived') | Conversation state |
| started_at | DATETIME | When conversation began |
| last_message_at | DATETIME | Last activity timestamp |
| last_page_url | TEXT | Last page the visitor was on |
| referrer_url | TEXT NULL | How they arrived at the site |
| ip_address | VARCHAR(45) | Visitor IP (for geo/spam) |
| user_agent | VARCHAR(500) | Browser info |
| metadata | JSON NULL | Extensible metadata (v3: woo orders, etc.) |

**Table: `{prefix}ssc_participants`**
| Column | Type | Description |
|--------|------|-------------|
| id | BIGINT UNSIGNED AUTO_INCREMENT | Primary key |
| conversation_id | BIGINT UNSIGNED | FK to ssc_conversations |
| participant_type | ENUM('visitor','admin','bot','system') | Role in conversation |
| user_id | BIGINT UNSIGNED NULL | WP user ID (for admins and logged-in visitors) |
| visitor_hash | VARCHAR(64) NULL | Cookie hash (for anonymous visitors) |
| display_name | VARCHAR(100) | Name shown in chat |
| joined_at | DATETIME | When they joined the conversation |
| last_seen_at | DATETIME NULL | Last activity (for typing/online indicators) |

This table future-proofs the schema for v4 multi-visitor open chat (e.g. YouTube-style chat rooms where multiple visitors and admins participate in the same conversation). In v1, each conversation has exactly 2 participants: 1 visitor + 1 admin (or system/bot). In v4, a conversation can have N visitors + N admins.

**Table: `{prefix}ssc_messages`**
| Column | Type | Description |
|--------|------|-------------|
| id | BIGINT UNSIGNED AUTO_INCREMENT | Primary key |
| conversation_id | BIGINT UNSIGNED | FK to ssc_conversations |
| participant_id | BIGINT UNSIGNED | FK to ssc_participants (who sent this message) |
| message | TEXT | Message content |
| message_type | ENUM('text','email_prompt','canned_response','auto_reply') | Type of message |
| created_at | DATETIME | When sent |
| read_at | DATETIME NULL | When read by recipient(s) |

**Table: `{prefix}ssc_canned_responses`** (v2)
| Column | Type | Description |
|--------|------|-------------|
| id | INT UNSIGNED AUTO_INCREMENT | Primary key |
| title | VARCHAR(255) | Admin-facing label |
| content | TEXT | Response text |
| category | VARCHAR(100) NULL | Grouping/category |
| usage_count | INT DEFAULT 0 | How often used |
| created_by | BIGINT UNSIGNED | Admin who created it |

**Table: `{prefix}ssc_settings_meta`** (for per-conversation admin notes, v3)

### REST API Architecture

**Important: The REST API works with OR without the mu-plugin.** The endpoints are registered via standard `register_rest_route()` in `class-ssc-rest.php` and function normally through WordPress's built-in REST infrastructure. The mu-plugin is a **performance accelerator** — when present and enabled, it intercepts matching requests early and calls the same handler functions directly, bypassing the full WP stack. If the mu-plugin is missing, disabled, or deleted, the chat continues to work — just at normal WordPress REST speed instead of ultra-fast speed.

### MU-Plugin: Ultra-Fast AJAX (`ssc-fast-ajax.php`)

Following the SSS pattern exactly:

1. **Early detection** — checks if the request is an SSC REST API call by inspecting `$_SERVER['REQUEST_URI']` for `/wp-json/ssc/v1/` or a custom query param `?ssc_ajax=1`
2. **Minimal bootstrap** — defines `DOING_AJAX`, `DOING_SSC_FAST_AJAX` constants, loads only `wp-load.php` essentials (wpdb, options) without themes, most plugins, or admin
3. **Route handling** — dispatches to the same handler functions defined in `class-ssc-rest.php`:
   - `GET ssc/v1/poll` — check for new messages since last ID
   - `POST ssc/v1/send` — send a message from visitor
   - `POST ssc/v1/admin/reply` — send admin reply (requires auth)
   - `GET ssc/v1/admin/conversations` — list active conversations (requires auth)
4. **JSON response** — returns minimal JSON and exits
5. **Version header** — for auto-update comparison with source file
6. **Enable/disable option** — `ssc_mu_enabled` option controls whether fast AJAX is active
7. **Graceful fallback** — if any error occurs during fast-path handling, the mu-plugin returns early and lets WordPress handle the request normally

**Installation/Update Logic (class-ssc-mu-installer.php):**
- On plugin activation: copy mu-plugin to `WPMU_PLUGIN_DIR`
- On settings page load: compare versions, copy if source is newer
- On plugin deactivation: optionally remove mu-plugin (with admin confirmation)

### REST API Endpoints

| Endpoint | Method | Auth | Description |
|----------|--------|------|-------------|
| `ssc/v1/poll` | GET | Cookie/nonce | Get new messages for visitor's conversation |
| `ssc/v1/send` | POST | Cookie/nonce | Visitor sends a message |
| `ssc/v1/session` | POST | None | Initialize a session, get visitor_hash cookie |
| `ssc/v1/email` | POST | Cookie | Visitor provides their email address |
| `ssc/v1/admin/conversations` | GET | Admin | List conversations with filters |
| `ssc/v1/admin/conversation/{id}` | GET | Admin | Get full conversation history |
| `ssc/v1/admin/reply` | POST | Admin | Admin sends reply to conversation |
| `ssc/v1/admin/close/{id}` | POST | Admin | Close/archive a conversation |
| `ssc/v1/admin/canned` | GET/POST | Admin | Manage canned responses (v2) |
| `ssc/v1/admin/canned/{id}` | PUT/DELETE | Admin | Update/delete canned response (v2) |
| `ssc/v1/admin/discord/test` | POST | Admin | Test Discord bot connection (v2) |
| `ssc/v1/discord/incoming` | POST | Secret | Receive messages from Discord bot relay (v2) |

### Front-End Chat Bubble

**Keep from existing plugin:**
- Fixed-position bubble (bottom-right)
- `easeOutElastic` bounce-out animation on open
- Sound effects on new message / open / close
- Auto-scroll to latest message

**Rewrite/Improve:**
- Vanilla JS (no jQuery dependency) — keeps it lightweight for the front-end
- CSS-only animations where possible (CSS `@keyframes` for bounce, transitions for slide)
- Message bubbles with proper alignment (visitor right, admin left)
- Typing indicator when admin is composing
- "Admin is away" state after configurable timeout
- Email collection prompt (slides in after timeout or configurable message count)
- Login/register encouragement after N messages for anonymous visitors
- Unread message badge on collapsed bubble
- Mobile-responsive (full-width on small screens)
- Dark mode support via CSS custom properties

**Session Flow:**
1. Visitor lands on site → bubble icon appears (after configurable delay)
2. Visitor clicks bubble → opens chat, creates session via `ssc/v1/session`
3. Visitor types message → sent via `ssc/v1/send`
4. JS polls `ssc/v1/poll` every 2s (configurable), backs off to 5s after 30s idle, 15s after 2min idle
5. If admin replies within timeout → message appears in bubble
6. If no admin reply within timeout → system message: "We'll get back to you! Leave your email to be notified" with email input
7. After N messages (configurable, default 5), if anonymous → prompt: "Create an account to save your chat history"

### Admin Chat Interface (wp-admin)

**Conversation List View:**
- Table of active/waiting conversations
- Columns: Visitor name, last message preview, status, started, last activity
- Real-time badge counts (polling via admin AJAX)
- Filter by status: Active, Waiting for reply, Closed, All
- Search by visitor name/email

**Conversation Detail View:**
- Full message history with timestamps
- Reply textarea with send button
- Quick actions: Close conversation, Send canned response (v2)
- Visitor info sidebar: name, email, pages visited, referrer, IP/location
- v3: WooCommerce order history, MonsterInsights data, purchase suggestions

### Settings Page

Uses Super Speedy Settings submodule pattern:

```php
require_once(plugin_dir_path(__FILE__) . 'super-speedy-settings/super-speedy-settings.php');
$plugin_data = get_file_data(__FILE__, array('Version' => 'Version'));
define('SSC_VERSION', $plugin_data['Version']);
SuperSpeedySettings_1_0::init(array(
    'plugin_slug' => 'super-speedy-chat',
    'version' => SSC_VERSION,
    'file' => __FILE__
));
```

**Settings Tabs:**

**General Tab:**
- Enable/disable chat bubble on front-end
- Enable Ultra Ajax (mu-plugin)
- Default admin display name
- Welcome message text
- Chat bubble position (bottom-right, bottom-left)
- Bubble open delay (seconds)
- Bubble icon (upload or default)
- Color scheme (primary color, accent color, text color)

**Behaviour Tab:**
- Admin reply timeout before "away" message (default: 30s)
- Timeout action: Show email prompt / Show LLM response (v2) / Both
- Anonymous message limit before login prompt (default: 5)
- Max message length (default: 500 chars)
- Poll interval (ms, default: 2000)
- Idle poll backoff interval (ms, default: 5000)
- Play sounds (on/off)
- Require login to chat (on/off)
- Pages to show/hide chat on (URL patterns)

**Email Tab:**
- Enable email notifications to admin on new conversation
- Admin notification email address(es)
- Enable email reply to visitor when offline
- Email from name / from address
- Email template customization

**LLM Tab (v2):**
- Enable LLM auto-responses
- LLM mode: Full conversational / Canned response classifier
- API provider: OpenAI / Anthropic / Custom
- API key (encrypted storage)
- System prompt / instructions
- Max tokens per response
- Canned response management (add/edit/delete)

**Discord Tab (v2):**
- Enable/disable Discord integration
- Discord bot token
- Discord channel ID
- Auto-generated webhook secret + REST endpoint URL (for companion bot config)

**Integrations Tab (v3):**
- Slack webhook URL
- Telegram bot token
- WhatsApp Business API config
- Enable/disable per platform

**Status Tab:**
- MU-plugin installation status
- Database table status
- Active conversations count
- System health check

### Email System

**Admin Notifications:**
- New conversation started → email to configured admin address(es)
- Configurable: every new conversation, or only if no admin online

**Visitor Notifications:**
- When visitor left email + went offline + admin replies → email with reply
- Email contains: admin message, link back to site (re-opens chat with history)
- Unsubscribe link per conversation

---

## Version Roadmap

### Version 1.0 — MVP
**Goal:** Working live chat with ultra-fast AJAX, email fallback, admin reply interface

**Features:**
- [ ] Plugin skeleton with Super Speedy Settings submodule
- [ ] Database tables: `ssc_conversations`, `ssc_participants`, `ssc_messages`
- [ ] MU-plugin for ultra-fast AJAX (poll, send, session endpoints)
- [ ] MU-plugin installer/updater (activation + settings page check)
- [ ] Front-end chat bubble with CSS animations (bounce-out from existing plugin)
- [ ] Sound effects (reuse existing MP3/OGG files)
- [ ] Visitor session management (cookie-based `visitor_hash`)
- [ ] Message sending and polling (vanilla JS)
- [ ] Adaptive polling (fast when active, backoff when idle)
- [ ] Admin wp-admin page: conversation list + reply interface
- [ ] Admin real-time polling for new messages/conversations
- [ ] Admin reply timeout → email collection prompt to visitor
- [ ] Email notification to admin on new conversation
- [ ] Email reply to visitor when they leave email + go offline
- [ ] Anonymous → login/register prompt after N messages
- [ ] Link anonymous conversation to WP user on login
- [ ] Settings page: General, Behaviour, Email tabs
- [ ] Anti-spam: nonce verification, rate limiting, honeypot field
- [ ] readme.txt with changelog
- [ ] Uninstall cleanup (drop tables, delete options)
- [ ] Mobile-responsive chat bubble

**Activation Bug Fix:**
- The old plugin outputs HTML/text before headers during activation because `marketer-pro-form.php` and other includes produce output at include time. The new plugin will use classes loaded via autoloader or explicit requires that define classes/functions only — no output at include time.

**AJAX URL Bug Fix:**
- The old plugin references `ajaxurl` which is only defined in wp-admin. The new plugin uses the mu-plugin REST pattern with a known URL (`/wp-json/ssc/v1/...`) localized to JS via `wp_localize_script()`, completely eliminating this issue.

### Version 2.0 — Canned Responses + Discord (Instant)
**Goal:** Canned response gathering from live admin replies and instant bidirectional Discord chat

**Features:**
- [x] Canned responses table + admin CRUD interface
- [x] "Save as Canned" button on admin messages in conversation detail
- [x] Canned responses admin tab with guide and management UI
- [ ] LLM canned response classifier (future — cheap classifier picks best match for visitor questions)
- [x] Discord integration — **instant bidirectional**:
  - **WP → Discord (instant)**: Visitor messages pushed to Discord threads via bot API immediately on send
  - **Discord → WP (instant)**: Node.js companion bot connected to Discord Gateway, relays messages to WordPress REST endpoint immediately
  - Thread-per-conversation model (each visitor conversation = Discord thread)
  - Authenticated bot-to-WP communication via shared secret (X-SSC-Secret header)
  - `fastcgi_finish_request()` used in mu-plugin to avoid blocking visitor response
  - **No WP-Cron polling** — all message delivery is instant
- [x] Discord admin settings tab with step-by-step setup guide
- [x] Companion Node.js bot included in plugin (`bot/` directory)
- [ ] Typing indicator (show when admin is composing)
- [ ] Admin online/offline status indicator on bubble
- [ ] Conversation assignment (assign to specific admin)

**Architecture note (SaaS roadmap for v5+):**
The Discord → WP endpoint (`/ssc/v1/discord/incoming`) authenticates via a shared secret, not WordPress cookies. This means any authenticated source can push messages — in v5+, a hosted SaaS bot service could replace the self-hosted companion bot, removing the Node.js requirement for site owners.

### Version 3.0 — Messaging Platforms + CRM
**Goal:** Full messaging platform coverage and admin CRM-like interface

**Features:**
- [ ] Slack integration (webhook + Slack app for bidirectional)
- [ ] Telegram bot integration (bidirectional via Telegram Bot API)
- [ ] WhatsApp Business API integration
- [ ] Generic webhook integration (for custom platforms)
- [ ] Admin conversation sidebar: WooCommerce data
  - Order history, total spend, last order date
  - Products viewed (if available)
  - Customer lifetime value
- [ ] Admin conversation sidebar: MonsterInsights data
  - Pages visited in session
  - Traffic source / campaign
  - Device / location info
- [ ] Purchase suggestion engine:
  - "Customers who bought X also bought Y" based on WooCommerce order data
  - Quick-send product recommendation buttons in admin reply UI
- [ ] Conversation tagging and categorization
- [ ] Conversation search and export
- [ ] Admin dashboard widget (recent conversations summary)
- [ ] Conversation analytics (response times, resolution rates, popular topics)
- [ ] Multi-admin presence (see which admins are online/viewing which conversations)

### Version 4.0 — Multi-Visitor Open Chat
**Goal:** YouTube-style open chat rooms where multiple visitors can chat together and with admins

**Features:**
- [ ] Open/public conversation type (in addition to private 1:1)
- [ ] Multiple visitors as participants in a single conversation (leverages `ssc_participants` table)
- [ ] Per-page or per-post chat rooms (shortcode or auto-attach)
- [ ] Configurable max participants per room
- [ ] Moderator tools (mute, kick, ban participant)
- [ ] Visitor-to-visitor messaging within rooms
- [ ] Live participant list / count display
- [ ] Chat room creation from admin or via shortcode attributes

---

## Technical Notes

### Existing Code to Preserve
- **CSS bounce animation**: The `easeOutElastic` jQuery animation and the fixed-position slide-in from `right: -560px`. Reimplement in pure CSS `@keyframes` for performance.
- **Sound effects**: Reuse the existing MP3/OGG audio files from `resources/`.
- **Chat bubble visual design**: The rounded-corner dark bubble with message triangles. Modernize but keep the feel.
- **Channel/conversation model**: The existing UUID-per-visitor approach maps directly to the new `ssc_conversations` table.

### Existing Code to Discard
All old Marketer Pro PHP, JS, and CSS files can be safely deleted. The only files to keep are the audio files from `resources/` (MP3/OGG sound effects) which will be moved to `assets/sounds/`. Specifically, delete:
- `marketer-pro.php`, `marketer-pro-core.php`, `marketer-pro-admin.php`, `marketer-pro-form.php`
- `resources/sac.php` (JS-via-PHP generator)
- `resources/sac.css`
- `resources/images/` (will create fresh icons)
- `languages/marketer-pro.pot`
- `uninstall.php` (will be rewritten)
- All jQuery dependencies
- The FAT (Fade Anything Technique) library
- HipChat integration (HipChat is dead)

**Keep:** `resources/*.mp3`, `resources/*.ogg` → move to `assets/sounds/`

### Security Considerations
- Nonce verification on all endpoints
- Rate limiting: max N messages per minute per visitor_hash (configurable, default 10)
- Input sanitization: `sanitize_text_field()` for messages, `sanitize_email()` for emails
- Output escaping: `esc_html()` in PHP, DOM text node creation in JS (no innerHTML with user content)
- CSRF protection via WordPress nonces
- IP-based rate limiting as secondary defense
- Honeypot field in chat form
- Encrypted storage for API keys (LLM, messaging platforms)
- Capability checks: all admin endpoints require `manage_options`
- Cookie security: HttpOnly, Secure (when HTTPS), SameSite=Lax

### Performance Targets
- MU-plugin AJAX response: < 50ms (poll with no new messages)
- MU-plugin AJAX response: < 100ms (poll with new messages)
- Front-end JS bundle: < 15KB gzipped
- CSS: < 5KB gzipped
- No blocking resources on page load (async/defer script loading)
- Database queries: indexed on `conversation_id`, `visitor_hash`, `created_at`

### Database Indexes
- `ssc_conversations`: INDEX on `visitor_hash`, INDEX on `status, last_message_at`
- `ssc_participants`: INDEX on `conversation_id, participant_type`, INDEX on `user_id`, INDEX on `visitor_hash`
- `ssc_messages`: INDEX on `conversation_id, id` (covering index for poll queries), INDEX on `participant_id`, INDEX on `created_at`
