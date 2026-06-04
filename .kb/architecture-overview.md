# Super Speedy Chat — Architecture Overview

This is a developer-facing tour of how Super Speedy Chat is put together: what each component does, the handful of design decisions that are genuinely unusual, and the internal architecture of each notable piece. If you're extending the plugin, building a channel add-on, or just trying to understand why a message takes the path it does, start here.

## Component summary

| Component | File(s) | Purpose |
|---|---|---|
| Plugin bootstrap | `super-speedy-chat.php` | Defines constants, loads classes, wires activation/REST/asset hooks, localises front-end config. |
| Data layer | `includes/class-ssc-db.php` | Owns the schema (`dbDelta`) and every query against the four core tables. |
| Session layer | `includes/class-ssc-session.php` | Issues and resolves the anonymous visitor identity (a cookie hash). |
| Chat service layer | `includes/class-ssc-chat.php` | The channel-agnostic core: send/receive logic, status transitions, lifecycle hooks. |
| REST API | `includes/class-ssc-rest.php` | Public visitor endpoints + capability-gated admin endpoints, plus the static "fast" handlers. |
| Ultra Ajax | `mu-plugins/ssc-fast-ajax.php`, `includes/class-ssc-mu-installer.php` | A must-use plugin that short-circuits visitor requests before full WordPress loads. |
| Front-end widget | `assets/chat-bubble.js`, `assets/chat-bubble.css` | The vanilla-JS chat bubble visitors interact with. |
| Admin UI | `includes/class-ssc-admin.php`, `admin/admin.js` | Settings tabs, conversation inbox, and the per-conversation reply view. |
| Settings accessor | `includes/class-ssc-settings.php`, `super-speedy-settings/` | Cached reader over the `ssc_options` array; the submodule provides the settings framework + update checker. |
| Canned responses | `includes/class-ssc-canned.php` | CRUD for saved reply snippets. |
| LLM auto-reply | `includes/class-ssc-llm.php` | Uses a cheap LLM as a *classifier* to pick a canned response when no human replies. |
| Email | `includes/class-ssc-email.php` | Admin "new chat" and visitor "you have a reply" notifications. |
| Discord integration | `includes/class-ssc-discord.php`, `bot/discord-bot.js` | Bidirectional bridge between conversations and Discord threads. |
| Add-on system | `includes/class-ssc-addons.php` | Registry + version gate for third-party channel add-ons. |
| Uninstall | `uninstall.php` | Drops tables, deletes options, removes the mu-plugin. |

## The interesting bits (read this first)

A few decisions shape everything else and aren't what you'd expect from a typical chat plugin:

1. **Two request paths that share one brain.** Visitor traffic can be served either by the normal WordPress REST API *or* by an mu-plugin that responds before WordPress finishes booting. Both call the **same static handler methods** on `SSC_REST` (`fast_poll`/`fast_send`/`fast_session`/`fast_auto_reply` mirror `handle_poll`/`handle_send`/…). This is the "Ultra Ajax" performance feature and the single most unusual structural choice in the codebase. See [Ultra Ajax](#ultra-ajax-the-fast-path).

2. **A channel-agnostic participant model.** Conversations aren't "visitor ↔ admin." They're a conversation with N **participants**, each typed `visitor` / `admin` / `bot` / `system`, and N messages each pointing at a participant. Website chat, Discord, and any future channel (WhatsApp, Telegram) all reduce to the same three tables. See [Data model](#data-model).

3. **Lifecycle hooks instead of direct calls.** `SSC_Chat` never calls Discord (or any channel) directly. It fires actions — `ssc_visitor_message_sent`, `ssc_admin_reply_sent`, `ssc_bot_message_sent`, `ssc_conversation_status_changed`. The bundled Discord integration is wired entirely through these hooks and the add-on extension API, deliberately **dogfooding** the same surface a third-party add-on would use. See [Add-on system](#add-on-extension-system).

4. **The LLM is a classifier, not a chatbot.** When auto-reply fires, the visitor's question plus the numbered list of canned responses go to the model with `max_tokens = 10`, and the reply is `intval()`-parsed into a canned-response index. The model never free-writes to a visitor. This keeps cost near zero and output bounded. See [LLM auto-reply](#llm-auto-reply).

5. **Anonymous identity is a bearer token in a cookie.** There's no login requirement; a visitor is whoever holds the `ssc_visitor_hash` cookie (256-bit, CSPRNG, HttpOnly). External channels synthesise a deterministic hash instead. See [Session layer](#session-layer).

6. **A self-installing mu-plugin.** The plugin copies `mu-plugins/ssc-fast-ajax.php` into `wp-content/mu-plugins/` on activation and re-copies it when the bundled version is newer. The thing that makes requests fast lives outside the plugin directory and is managed by the plugin. See [Ultra Ajax](#ultra-ajax-the-fast-path).

---

## Bootstrap

`super-speedy-chat.php` is intentionally thin. In order it:

1. Loads the `super-speedy-settings` submodule (guarded by `function_exists('wp_next_scheduled')` so it's skipped in the stripped-down mu-plugin context) and defines `SSC_VERSION` from the plugin header.
2. Defines `SSC_DIR` / `SSC_URL` / `SSC_VERSION`.
3. `require_once`s every class in `includes/` (no autoloader — explicit includes).
4. Registers activation (`SSC_DB::create_tables()` + `SSC_MU_Installer::install()`) and deactivation (`SSC_MU_Installer::uninstall()`) hooks.
5. On `plugins_loaded` → `ssc_init_plugin()`: runs the DB upgrade check (`version_compare` against `SSC_DB::DB_VERSION`) and instantiates `SSC_Admin` when `is_admin()`.
6. Registers Customizer settings, REST routes (`rest_api_init`), and front-end assets (`wp_enqueue_scripts`).

The front-end enqueue (`ssc_enqueue_frontend_assets`) is where all server→client config is assembled into the `ssc_config` blob via `wp_localize_script`, including the REST URL, a `wp_rest` nonce, polling intervals, appearance/Customizer values, and the `ssc_frontend_config` filter that lets add-ons inject their own keys.

> **Note on the include style:** functions and classes are called directly with no `function_exists()`/`class_exists()` guards around *our own* code. A missing include is meant to fatal loudly rather than silently no-op. The `class_exists()` wrappers you see at the top of each class file are idempotency guards (the same files are `require_once`d again from the mu-plugin), not defensive call-site checks.

---

## Data model

Four core tables (plus one for Discord), created by `SSC_DB::create_tables()` with `dbDelta`. `SSC_DB::DB_VERSION` drives migrations: on load, if the stored `ssc_db_version` is older, `create_tables()` runs again (`dbDelta` is idempotent) and a version-gated backfill can run.

- **`ssc_conversations`** — one row per conversation. Carries `visitor_hash`, optional `user_id`, `visitor_name`/`visitor_email`, a `status` enum (`active` / `waiting` / `closed` / `archived`), a `channel` discriminator (default `website`), provenance fields (`ip_address`, `user_agent`, `referrer_url`, `last_page_url`), `assigned_to`, and a JSON `metadata` column.
- **`ssc_participants`** — one row per actor in a conversation, typed `visitor` / `admin` / `bot` / `system`. Holds either a `user_id` (admins) or a `visitor_hash` (visitors), plus a `display_name`.
- **`ssc_messages`** — one row per message, pointing at a `conversation_id` and a `participant_id`, with a `message_type` enum (`text` / `email_prompt` / `canned_response` / `auto_reply`).
- **`ssc_canned_responses`** — saved reply snippets (`question_summary`, `response_text`, `category`, `usage_count`).
- **`ssc_discord_threads`** — maps a `conversation_id` to a Discord `thread_id`/`channel_id` and tracks sync watermarks.

**Architecture notes:**

- The `channel` column is what makes the model multi-channel. A conversation "belongs to" website chat or Discord or a future add-on, and queries that resolve a visitor's active conversation key on `(visitor_hash, channel, status)`.
- The `status` enum is a small state machine: a visitor message moves a conversation to `waiting`; an admin/bot reply moves it back to `active`; the admin can `close` it. Every transition fires `ssc_conversation_status_changed`.
- **All** queries go through `$wpdb->prepare()`; dynamic `ORDER BY`/`LIMIT` use whitelists and `absint()`. The data layer is the security boundary for SQL, and it's consistent about it.
- `SSC_DB` is a thin static query class — no ORM, no model objects. Callers get raw `stdClass` rows from `$wpdb`.

---

## Session layer

`SSC_Session` answers one question: *who is this visitor?*

- `get_or_create_visitor_hash()` reads the `ssc_visitor_hash` cookie, or mints a new `bin2hex(random_bytes(32))` (256-bit) token and sets it with `HttpOnly`, `SameSite=Lax`, and `Secure` (when HTTPS). The hash is the visitor's identity — there is no account.
- `get_visitor_hash()` is the read-only variant used by endpoints that must not create identity (poll/send/email refuse with 403 when there's no cookie).
- `get_or_create_conversation()` / `get_or_create_participant()` lazily create the conversation and the visitor participant row on first message.
- `link_user()` upgrades an anonymous conversation to a logged-in one: if a WordPress user is logged in, their `user_id` and `display_name` are stitched onto the existing conversation/participant.

**Why this matters:** because identity is a bearer cookie, the security model leans on (a) the token being unguessable (256-bit CSPRNG), (b) `HttpOnly` keeping it out of JS, and (c) endpoints resolving the conversation *only* from the cookie — there is no visitor-facing endpoint that accepts a conversation ID, which is what prevents one visitor from reading another's chat. External channels can't use a browser cookie, so `SSC_Chat::get_or_create_external_conversation()` derives a deterministic `sha256('ext:' + channel + ':' + external_id)` hash instead.

---

## Chat service layer

`SSC_Chat` is the channel-agnostic core. Every way a message can enter the system funnels through it, and it owns the side effects.

- `send_visitor_message()` — sanitises and length-caps the text, ensures conversation + visitor participant exist, inserts the message, flips status to `waiting`, sends the admin "new conversation" email on the *first* message only, and fires `ssc_visitor_message_sent`.
- `send_admin_reply()` — ensures an admin participant exists (named per the display-name mode), inserts the reply, flips status to `active`, emails the visitor if they're offline with an email on file, and fires `ssc_admin_reply_sent`.
- `send_bot_message()` — inserts a `bot`-typed message (used by auto-reply) and fires `ssc_bot_message_sent`.
- `poll_messages()` — thin wrapper over `SSC_DB::get_messages($conversation_id, $since_id)`.
- `get_or_create_external_conversation()` / `external_inbound()` — the public entry points add-ons use to push messages from an external channel into core without re-implementing participant/message/status logic.
- `has_auto_reply()` / `get_visitor_message_count()` — guards used to bound the auto-reply feature (see [LLM auto-reply](#llm-auto-reply)).

**Architecture note — the hooks are the API.** `SSC_Chat` has no knowledge of Discord, email-as-a-channel, or anything downstream beyond the bundled `SSC_Email` calls. Channels subscribe to the lifecycle actions. This is what lets the Discord integration live in its own file and be, structurally, an add-on.

---

## REST API

`SSC_REST::register_routes()` registers everything under the `ssc/v1` namespace, in two tiers:

- **Visitor endpoints** — `/session`, `/send`, `/poll`, `/email`, `/auto-reply`. `permission_callback` is `__return_true` (anonymous by design). Protection comes from per-IP rate limiting (`check_rate_limit()` using transients), a honeypot field (`website_url`) on `/send`, and the fact that each resolves the conversation from the visitor cookie only.
- **Admin endpoints** — `/admin/conversations`, `/admin/conversation/{id}`, `/admin/reply`, `/admin/close/{id}`, `/admin/assign/{id}`, and canned-response CRUD. Every one is gated by `check_admin_permission()` → `current_user_can('manage_options')`, and CSRF-protected by the WordPress REST nonce.

At the end of registration it fires `do_action('ssc_register_rest_routes', $this)` so add-ons (and the bundled Discord class) can register their own routes — that's how `/discord/incoming` and `/admin/discord/test` come to exist.

**The static "fast" handlers.** The other half of this class is `fast_session()`, `fast_send()`, `fast_poll()`, and `fast_auto_reply()` — static methods that take a plain params array and return a plain array, with no dependency on `WP_REST_Request`. They duplicate the logic of the instance handlers because they're invoked from the mu-plugin, where the full REST infrastructure isn't loaded. Keeping them as static methods on the same class is what keeps the two paths from drifting apart.

---

## Ultra Ajax (the fast path)

This is the plugin's headline performance feature and its most unusual mechanism.

`mu-plugins/ssc-fast-ajax.php` is a **must-use plugin** that the main plugin installs into `wp-content/mu-plugins/`. Because mu-plugins load extremely early, this file can inspect the incoming request and respond *before* the full WordPress REST stack (and most of the plugin ecosystem) initialises. Its flow:

1. Bail unless the request path is under `/wp-json/ssc/v1/`.
2. Bail (fall through to normal REST) unless Ultra Ajax is enabled in options.
3. Parse the command from the path. Only `poll` / `send` / `session` / `auto-reply` are handled fast; **admin routes deliberately fall through** to full WordPress so they get real authentication.
4. Define `DOING_AJAX` / `DOING_SSC_FAST_AJAX`, stub `is_user_logged_in()` if needed, `require_once` just the handful of classes the handlers need.
5. Apply per-IP, per-action rate limiting via transients.
6. Dispatch to the matching `SSC_REST::fast_*` static method, echo JSON, and — crucially — call `fastcgi_finish_request()` so the visitor gets their response immediately while any after-work (e.g. pushing the message to Discord) runs after the connection closes.

`SSC_MU_Installer` manages the file's lifecycle: `install()` copies it on activation and on settings save; `check_and_update()` compares the bundled version header against the installed one and re-copies when newer; `uninstall()` removes it. The Status tab surfaces whether it's installed and at what version.

**Trade-off to be aware of:** the fast path re-implements request routing and rate limiting outside the normal WordPress request lifecycle. That's the cost of bypassing the bootstrap. The mitigation is the shared static handlers (logic isn't duplicated, only the plumbing around them) and the strict allow-list that keeps anything privileged on the normal, fully-authenticated path.

---

## Front-end widget

`assets/chat-bubble.js` is dependency-free vanilla JS (no jQuery). It self-initialises from the `ssc_config` blob and:

- Builds the bubble, window, message list, email-prompt, login-prompt, and a hidden honeypot input entirely in JS.
- Drives the conversation lifecycle: `startSession()` → `sendMessage()` → adaptive `poll()`.
- **Adaptive polling.** It polls fast while the visitor is active, then backs off through "idle" (after 30s) and "deep idle" (after ~2 min) intervals, resetting to fast on any activity or new message. Intervals tighten automatically when Ultra Ajax is active.
- **Timeout actions.** If no human replies within the configured window, it triggers the admin's chosen action: show the email prompt, request an LLM auto-reply, or both.
- **Output escaping.** All message text and display names are escaped (`escapeHtml` / `escapeAttr`) before insertion, so visitor- or admin-authored content can't inject markup.

**Interesting aspect — a mini hooks library.** The file ships a tiny `window.ssc.hooks` implementation (`addAction`/`doAction`/`addFilter`/`applyFilters`) that mirrors the `@wordpress/hooks` API surface *without* the dependency, to keep the bundle small. Add-ons can observe and modify bubble behaviour (`ssc.bubble.opened`, `ssc.bubble.messageReceived`, the `ssc.bubble.welcomeMessage` filter, etc.) the same way they'd hook PHP actions.

---

## Admin UI

`SSC_Admin` registers a submenu page under the shared "superspeedy" menu and renders two distinct views from one `render_page()`:

- **The inbox** — a tabbed settings page (Chats, General, Display Names, Behaviour, Email, Canned Responses, LLM Auto-Reply, Status, plus any add-on tabs). Tabs are an ordered, filterable (`ssc_settings_tabs`) list; the "Chats" tab hosts the conversation list, the rest are WordPress Settings API sections inside a single form.
- **The conversation detail** — shown when `?conversation_id=` is present; renders the visitor sidebar, message thread, reply box, and assignment dropdown.

`admin/admin.js` (jQuery-based) powers both: it fetches conversations and messages through the admin REST endpoints (sending the `X-WP-Nonce`), polls the open conversation every few seconds, handles replying/closing/assigning, and provides the canned-response management UI. Like the front-end, it escapes all server data before rendering.

**Architecture notes:**

- Settings are registered with the WordPress Settings API and saved through one `sanitize_options()` callback that whitelists keys by type (checkbox/text/textarea/select/number/sound-filename) and then fires `ssc_sanitize_options` so add-ons can sanitise their own keys in the shared `ssc_options` array.
- Per-user chat display names are stored in user meta, not `ssc_options`, so individual-mode names are per-admin.
- Appearance (colours, header image, icons, position) lives in the **Customizer**, not the settings page, with proper `sanitize_hex_color` / `esc_url_raw` / value-whitelist callbacks.

---

## Settings accessor

`SSC_Settings::get_option($key, $default)` is a tiny cached reader over the single `ssc_options` array — it loads the option once per request and serves keys from the in-memory cache. Nearly every component reads config through it rather than calling `get_option('ssc_options')` repeatedly.

The `super-speedy-settings/` submodule is the shared Super Speedy settings framework and bundles the Plugin Update Checker library (`plugin-update-checker/`) used for self-hosted updates. It's third-party/shared infrastructure and is treated as a black box by the rest of the plugin.

---

## Canned responses

`SSC_Canned` is straightforward CRUD over `ssc_canned_responses` (`add` / `get` / `get_all` / `update` / `delete` / `increment_usage` / `get_categories`), with prepared statements and `esc_like` search. Responses are created from the admin conversation view (star a good admin reply) or the Canned Responses tab. They serve two roles: a quick-insert library for admins, and the candidate set the LLM classifier chooses from.

---

## LLM auto-reply

`SSC_LLM` turns "no human replied in time" into "send the best-matching canned response," using an LLM purely as a **classifier**.

`classify_question()`:

1. Loads all canned responses and builds a numbered list.
2. Sends `{system prompt + visitor question + numbered list}` to the configured provider (OpenAI or Anthropic) with `max_tokens = 10`, `temperature = 0`.
3. Parses the model's reply with `intval()` into a canned-response index; index 0 / out-of-range means "no match → stay silent."
4. On a match, increments usage and returns the canned text, which `SSC_Chat::send_bot_message()` posts as a `canned_response` message.

**Architecture notes:**

- Both providers are called via `wp_remote_post`; the provider switch lives in `call_llm()`, with `call_openai()` / `call_anthropic()` handling the request shape. Default models are cheap (`gpt-4o-mini` / `claude-haiku-4-5`).
- **Cost controls.** Because the trigger endpoint is unauthenticated, the feature is bounded on several axes: per-IP rate limiting on `/auto-reply`, a requirement that the conversation already have a visitor message, a once-per-conversation cap (`SSC_Chat::has_auto_reply()`), and a site-wide daily ceiling (`SSC_LLM::DAILY_CALL_CAP`, overridable via the `ssc_llm_daily_cap` filter). The output cap (`max_tokens = 10` + `intval` parse) also means a prompt-injected response can't do anything except pick a (already-public) canned answer.

---

## Email

`SSC_Email` sends two plain-text notifications via `wp_mail`:

- `notify_admin_new_conversation()` — to the configured admin address when a conversation's first visitor message lands.
- `notify_visitor_reply()` — to the visitor's address (if provided) when an admin replies.

Both build a `From:` header from the configured from-name and the site admin email, and gate on their respective enable toggles. This is the "email fallback" that lets conversations continue when the visitor has closed the tab.

---

## Discord integration

`SSC_Discord` is a full bidirectional bridge and the **reference implementation** of the add-on extension API (it's bundled in core but written as if it were a separate add-on).

- **WordPress → Discord.** It listens on `ssc_visitor_message_sent` / `ssc_admin_reply_sent`. On a visitor's first message it lazily creates a Discord thread (`get_or_create_thread()` posts a starter message with visitor context, then spins a thread off it) and records the mapping in `ssc_discord_threads`. Subsequent messages are pushed into the thread. All message posts set `allowed_mentions: {parse: []}` so untrusted visitor text can't ping `@everyone`/roles.
- **Discord → WordPress.** A companion Node.js bot (`bot/discord-bot.js`) listens to the configured channel's threads and relays replies to the `/ssc/v1/discord/incoming` REST endpoint. That endpoint is public but authenticated by a shared secret compared with `hash_equals()` (timing-safe). `handle_incoming()` maps the thread back to a conversation and inserts the reply as an `admin`-typed participant.
- **Config + UI.** Everything Discord — its settings tab, fields, sanitiser, channel registration, and the webhook-secret display — is registered through the same hooks an add-on would use (`ssc_settings_tabs`, `ssc_register_settings`, `ssc_sanitize_options`, `ssc_channels`, `ssc_register_rest_routes`).

The companion bot is intentionally minimal: it loads its own `.env` (no dotenv dependency), filters to the configured channel's threads, skips bot messages, and `fetch`es the WordPress endpoint with the shared secret header.

---

## Add-on extension system

`SSC_Addons` is a small registry that third-party channel add-ons (WhatsApp, Telegram, …) call via `SSC_Addons::register()` during `plugins_loaded`. It records the add-on's slug/name/version/channel, enforces an API-version and core-version gate (`ADDON_API_VERSION`), and queues an admin notice if an add-on requires a newer API than core provides. The Status tab lists registered add-ons.

The extension surface an add-on builds against is the union of:

- **PHP actions/filters** — the lifecycle actions (`ssc_visitor_message_sent`, `ssc_admin_reply_sent`, `ssc_bot_message_sent`, `ssc_conversation_status_changed`), the registration hooks (`ssc_register_rest_routes`, `ssc_register_settings`, `ssc_settings_tabs`, `ssc_sanitize_options`, `ssc_channels`, `ssc_frontend_config`, `ssc_enqueue_frontend`), and the UI extension points (`ssc_conversation_sidebar`, `ssc_conversation_reply_footer`).
- **`SSC_Chat` helpers** — `get_or_create_external_conversation()` and `external_inbound()` for pushing inbound channel messages into core.
- **The front-end `window.ssc.hooks`** — for bubble behaviour.

That the bundled Discord integration is built entirely on this surface is the design check that keeps the surface real: if Discord can do it through hooks, so can an add-on. See `.docs/addons-system-plan.md` for the full plan and `.docs/whatsapp-integration-plan.md` for a worked second channel.

---

## Lifecycle: a message end to end

To tie it together, here's a visitor message on a site with Ultra Ajax and Discord both enabled:

1. The bubble POSTs to `/wp-json/ssc/v1/send`.
2. The mu-plugin intercepts it before full WordPress loads, rate-limits it, and calls `SSC_REST::fast_send()`.
3. `fast_send()` resolves the conversation from the cookie, inserts the message, sets status `waiting`, and returns JSON.
4. `fastcgi_finish_request()` flushes the response to the visitor immediately.
5. *After* the connection closes, the mu-plugin pushes the message to Discord.
6. The bubble's next poll (`/poll` → `fast_poll`) returns any admin/bot replies; if the admin replied from Discord, the companion bot already relayed it through `/discord/incoming` into the same conversation, so it simply appears.

The same message on a site *without* Ultra Ajax takes the normal REST path (`handle_send` → `SSC_Chat::send_visitor_message`), where the `ssc_visitor_message_sent` action drives the Discord push instead of the mu-plugin's after-work block — same outcome, different plumbing.
