# WhatsApp Integration — Build Plan

Status: **implemented in 1.11.0** (22 Jul 2026) — both Mode A and Mode B were
built (Dave wanted visitors to choose their channel AND instant forwarding to
his personal WhatsApp because Discord phone notifications lag ~10 minutes).
Implementation lives in `includes/class-ssc-whatsapp.php` on the channel
add-on API; user-facing setup guide: `whatsapp-integration-setup.md`.

This doc proposes how to add WhatsApp to Super Speedy Chat. It mirrors the existing Discord integration where the pattern transfers cleanly and calls out the places where WhatsApp forces a different design.

---

## 1. Why WhatsApp is not "just like Discord"

The Discord integration is essentially: visitor types in the bubble → message is pushed to a Discord thread → admin replies in Discord → relayed back. WhatsApp **cannot** be modelled the same way without major caveats:

1. **No threads.** WhatsApp 1:1 chats don't have threads. Every message between the business number and a given phone number is one continuous chat. We can't open a "thread per conversation" the way Discord does.
2. **24-hour customer-service window.** A WhatsApp Business number can only send free-form messages within 24 hours of the recipient's last inbound message. Outside that window, the business must send a **pre-approved template message** (and pay per send in many price tiers). This is enforced by Meta.
3. **Direction of initiation matters.** Meta strongly prefers that the **end user** initiates the conversation (via a `wa.me/` link, "Click to Chat" button, or QR code). Cold outbound to a number that has never messaged you is restricted to template messages and requires opt-in.
4. **No Gateway/WebSocket — webhooks instead.** The Cloud API delivers inbound messages by HTTPS POST to a webhook URL we control. **We do not need a Node.js companion bot** the way Discord does. This is a simplification.
5. **Verification is heavier.** A Meta Business account, a verified phone number, and a public HTTPS webhook are required. The setup guide will be noticeably longer than Discord's.

These constraints push us toward two distinct usage modes — described below — rather than one Discord-style "admin reply channel".

---

## 2. Two usage modes

### Mode A — WhatsApp as the **visitor entry channel** (recommended primary)

Visitor clicks a "Chat on WhatsApp" button on the site (or scans a QR), is dropped into a WhatsApp chat with the business number, and types there. Their message arrives in WordPress via webhook and shows up in the admin chat dashboard exactly as a website-bubble conversation would. The admin replies from wp-admin and the reply is pushed back to the visitor's WhatsApp via the Cloud API.

- Aligns with how WhatsApp Business is *meant* to be used (user-initiated)
- No 24-hour window problem for the first reply — the visitor already messaged us
- Visitor identity = phone number, persistent across sessions
- Works on mobile beautifully (most visitors will be on phones anyway)

### Mode B — WhatsApp as the **admin's notification + reply channel** (optional, parallels Discord)

Visitor types in the website bubble; the message is forwarded to the **admin's personal WhatsApp** (a number they've opted in to receive these). Admin replies from their phone; the reply is relayed back to the website bubble.

- Mirrors Discord behaviour
- But there are no threads, so all conversations land in one chat with the admin's number — we have to **prefix every message with a conversation tag** (e.g. `[#123 Jane]`) and require the admin to **quote-reply** so we can route the response back to the right conversation
- Admin must opt-in by messaging the business number first (so the 24h window is open), and re-open it periodically, otherwise we'd need a template message every time

**Recommendation:** Build Mode A first. Mode B is a nice-to-have but the UX is awkward without threads.

The rest of this doc assumes **Mode A as the primary build**, with a note at the end on what Mode B would additionally need.

---

## 3. Choice of WhatsApp API provider

| Option | Notes |
|---|---|
| **Meta Cloud API (direct)** | Official, free for up to 1,000 service conversations/month, then per-conversation pricing. Requires Meta Business Manager, app, and a verified phone number. **Recommended.** |
| **Twilio / 360dialog / Vonage / MessageBird (BSPs)** | Friendlier onboarding, but adds a paid middle-man and a second account. Useful as a v2 fallback if customers struggle with Meta onboarding. |
| **whatsapp-web.js / Baileys (unofficial WhatsApp Web automation)** | Violates WhatsApp ToS; accounts get banned. **Not an option.** |

We'll build against the **Cloud API** directly. The class can later be subclassed/swapped for Twilio if there's demand.

---

## 4. Architecture (mirrors Discord where possible)

```
super-speedy-chat/
├── includes/
│   ├── class-ssc-whatsapp.php          # NEW — mirrors class-ssc-discord.php
│   └── class-ssc-rest.php              # add /whatsapp/incoming + /whatsapp/test routes
├── includes/class-ssc-db.php           # add ssc_whatsapp_threads table, bump DB_VERSION
├── includes/class-ssc-admin.php        # add "WhatsApp" settings tab + register fields
├── includes/class-ssc-chat.php         # hook push_message() calls (parallel to Discord)
└── .docs/
    └── whatsapp-integration-setup.md   # NEW — user-facing setup guide (after build)
```

**No companion bot directory.** Unlike Discord, the Cloud API webhook is a normal REST endpoint on the WP site — Meta calls it directly.

### 4.1 `class-ssc-whatsapp.php`

Public surface modelled on `SSC_Discord`:

```php
class SSC_Whatsapp {
    const API_BASE = 'https://graph.facebook.com/v21.0';

    public static function is_configured();      // token + phone_number_id + verify_token all set
    public static function is_enabled();         // enabled flag + is_configured()

    // Settings accessors
    private static function get_access_token();
    private static function get_phone_number_id();
    private static function get_business_account_id();
    private static function get_app_secret();        // for X-Hub-Signature-256 verification
    public  static function get_verify_token();      // generated like the Discord secret

    // Outbound (WP → WhatsApp)
    public static function push_message( $conversation_id, $sender_name, $message_text, $is_visitor );
    private static function send_text( $to_phone, $body );
    private static function send_template( $to_phone, $template_name, $params );  // for re-engagement

    // Inbound (WhatsApp → WP)
    public static function handle_incoming_webhook( $payload );  // dispatches by event type
    private static function handle_message_event( $message, $contact, $metadata );
    private static function verify_webhook_signature( $raw_body, $signature_header );

    // Conversation linkage
    public static function get_or_create_conversation_for_phone( $phone, $display_name );
    private static function get_or_create_whatsapp_participant( $conversation_id, $phone, $display_name );

    // Test
    public static function test_connection();    // GET /{phone_number_id}
}
```

### 4.2 Database — `ssc_whatsapp_threads`

Parallels `ssc_discord_threads`. Stores the visitor's phone number and the link to the conversation. Because WhatsApp has no thread concept, the "thread id" is just the visitor's E.164 phone number.

```sql
CREATE TABLE {prefix}ssc_whatsapp_threads (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    conversation_id BIGINT UNSIGNED NOT NULL,
    wa_phone_number VARCHAR(20) NOT NULL,           -- visitor, E.164 e.g. +447123456789
    wa_business_phone_number_id VARCHAR(64) NOT NULL,
    wa_contact_wa_id VARCHAR(20) NOT NULL,          -- Meta's "wa_id" (usually = phone)
    last_inbound_at DATETIME NULL,                  -- used to track 24h window
    last_outbound_at DATETIME NULL,
    last_synced_wa_msg_id VARCHAR(128) DEFAULT '',
    last_synced_wp_msg_id BIGINT UNSIGNED DEFAULT 0,
    created_at DATETIME NOT NULL,
    PRIMARY KEY  (id),
    UNIQUE INDEX idx_conv (conversation_id),
    UNIQUE INDEX idx_wa_phone (wa_phone_number),
    INDEX idx_business_phone (wa_business_phone_number_id)
) {$charset_collate};
```

Bump `SSC_DB::DB_VERSION` to trigger `dbDelta` on upgrade.

### 4.3 New settings tab

Tab name: **WhatsApp**, registered in `SSC_Admin` next to the Discord tab.

Fields:
- `ssc_whatsapp_enabled` (checkbox)
- `ssc_whatsapp_access_token` (password-style text, stored in `ssc_options`)
- `ssc_whatsapp_phone_number_id` (text — Meta's numeric ID for the business phone)
- `ssc_whatsapp_business_account_id` (text — WABA ID, optional but useful for diagnostics)
- `ssc_whatsapp_app_secret` (password — used to verify `X-Hub-Signature-256`)
- `ssc_whatsapp_verify_token` (read-only display, auto-generated like `ssc_discord_webhook_secret`)
- `ssc_whatsapp_webhook_url` (read-only display: `https://yoursite.com/wp-json/ssc/v1/whatsapp/incoming`)
- `ssc_whatsapp_welcome_template_name` (text, optional — name of a pre-approved template to send if a phone messages us for the first time outside business hours / for auto-reply)
- **"Test WhatsApp Connection"** button → calls `/admin/whatsapp/test`
- Step-by-step setup callouts (Meta Business → App → WhatsApp product → phone verify → token → webhook config)

### 4.4 REST endpoints

| Endpoint | Method | Auth | Purpose |
|---|---|---|---|
| `ssc/v1/whatsapp/incoming` | GET | `hub.verify_token` query param | Meta webhook verification challenge handshake — return `hub.challenge` if token matches |
| `ssc/v1/whatsapp/incoming` | POST | `X-Hub-Signature-256` HMAC | Meta delivers messages, status updates here |
| `ssc/v1/admin/whatsapp/test` | POST | Admin (`manage_options`) | Sanity-check the access token by hitting `GET /{phone_number_id}` |

All three go in `class-ssc-rest.php` next to the existing Discord routes.

### 4.5 Message flow (Mode A end-to-end)

**Inbound — visitor on WhatsApp → WordPress:**
1. Visitor sends a WhatsApp message to the business number.
2. Meta POSTs the message payload to `/wp-json/ssc/v1/whatsapp/incoming`.
3. Endpoint verifies `X-Hub-Signature-256` against `ssc_whatsapp_app_secret` using HMAC-SHA256 over the **raw** request body. (Critical: WP normalizes JSON; we have to grab `php://input` ourselves before WP parses it.)
4. `SSC_Whatsapp::handle_incoming_webhook()` walks the Meta payload (which is nested: `entry[].changes[].value.messages[]`), pulls out each message + matching contact, and for each one:
   - Looks up `ssc_whatsapp_threads` by `wa_phone_number`.
   - If missing → creates a new `ssc_conversations` row (visitor_name = WhatsApp profile name, visitor_hash = `wa_` + sha256(phone), metadata includes `{channel: "whatsapp", phone: "+44…"}`) **and** a `ssc_whatsapp_threads` row.
   - Adds the message via `SSC_DB::add_message()` with a `whatsapp` participant.
5. Conversation status set to `active`. Admin dashboard picks it up on next poll, plays the notification sound, etc.

**Outbound — admin reply in wp-admin → visitor on WhatsApp:**
1. Admin types reply in `SSC_Admin` conversation view → `POST /ssc/v1/admin/reply` (existing flow).
2. `SSC_Chat::admin_reply()` writes the message to the DB (existing).
3. New hook in `SSC_Chat::admin_reply()` (parallel to the existing Discord call at `class-ssc-chat.php:149`):
   ```php
   if ( SSC_Whatsapp::is_enabled() ) {
       SSC_Whatsapp::push_message( $conversation_id, $admin_name, $message, false );
   }
   ```
4. `push_message()` checks the conversation's channel:
   - If the conversation originated from WhatsApp (has a row in `ssc_whatsapp_threads`) → check the 24h window. If within window → send free-form text. If outside → either skip (and surface a notice in admin UI) or send a configured template if one is set.
   - If the conversation originated from the website bubble (no WhatsApp thread row) → do nothing. (Mode B would change this.)
5. Cloud API call: `POST /{phone_number_id}/messages` with `{ messaging_product: "whatsapp", to, type: "text", text: { body } }`.

### 4.6 Channel awareness

Conversations now have a notion of "where did this start". Options:

- **Cheap path:** infer from the existence of an `ssc_whatsapp_threads` / `ssc_discord_threads` row.
- **Cleaner path:** add a `channel ENUM('website','whatsapp','discord','email')` column to `ssc_conversations` with default `'website'`. Migration is a one-time `ALTER TABLE`.

I'd lean **clean path** because the admin UI will want to show a "WhatsApp" badge on the conversation list, and the cheap path means an extra JOIN every render.

### 4.7 Security

- Webhook signature verification (`X-Hub-Signature-256`, HMAC-SHA256 of raw body using app secret) — **must compare against raw `php://input`** before any framework parsing.
- Verify token for the GET handshake — generated with `wp_generate_password(32, false)` like the Discord secret, stored in `ssc_options`.
- Access token stored in `ssc_options` (same pattern as Discord bot token). Note: WordPress options are not encrypted; this is consistent with how the rest of the plugin handles secrets but worth flagging.
- Rate limiting on inbound webhook is **not** needed — Meta is the only caller and they self-throttle. We should still validate `Content-Type` and bail fast on garbage.
- Outbound rate limits: Cloud API caps depend on tier. Handle 429 the same way `SSC_Discord::api_request()` does (return `WP_Error`, don't retry in-request).

### 4.8 24-hour window handling

Track `last_inbound_at` on `ssc_whatsapp_threads` (updated on every inbound message). Before sending an outbound free-form message, check `last_inbound_at >= NOW() - 24 hours`. If not:

- v1: surface an admin-facing warning in the reply UI ("This visitor's WhatsApp window has expired. Send a template message to re-engage.") and **don't send**. The reply still saves to WP so it's not lost.
- v2: optional auto-send of a configured template (`ssc_whatsapp_welcome_template_name`) to re-open the window before retrying.

---

## 5. Build phases

### Phase 1 — Foundations (no UI yet)
- [ ] Add `channel` column to `ssc_conversations` (migration via `dbDelta` + version bump)
- [ ] Create `ssc_whatsapp_threads` table
- [ ] Stub `class-ssc-whatsapp.php` with `is_enabled()`, settings accessors, `get_verify_token()`
- [ ] Add `WhatsApp` settings tab (fields only, no test button yet)

### Phase 2 — Outbound (WP → WhatsApp)
- [ ] Implement `api_request()` helper
- [ ] Implement `send_text()` and `push_message()`
- [ ] Wire `push_message()` into `SSC_Chat::admin_reply()` (parallel to Discord)
- [ ] Implement `test_connection()` + admin "Test WhatsApp Connection" button + REST route
- [ ] Manual test: from a phone that has already messaged the business number, trigger an admin reply and confirm it arrives

### Phase 3 — Inbound (Webhook)
- [ ] Register `GET /ssc/v1/whatsapp/incoming` for verify handshake
- [ ] Register `POST /ssc/v1/whatsapp/incoming` for message delivery
- [ ] Capture raw body before WP parses (custom permission callback or early hook)
- [ ] Implement `verify_webhook_signature()`
- [ ] Implement `handle_incoming_webhook()` payload walker
- [ ] Implement `get_or_create_conversation_for_phone()`
- [ ] Manual test with Meta's webhook test tool, then real phone

### Phase 4 — Polish
- [ ] Channel badge in admin conversation list ("WhatsApp" / "Discord" / "Web")
- [ ] 24h-window warning in admin reply UI
- [ ] Status webhook handling (delivered / read receipts) — purely informational, updates `read_at`
- [ ] Setup guide doc `.docs/whatsapp-integration-setup.md`
- [ ] KB article (via the `kb-article` skill)
- [ ] readme.txt changelog entry, version bump

### Out of scope for v1
- Media messages (images, voice notes, documents) — text only for now
- Mode B (admin notification channel) — see section 2
- Template message management UI — admin enters template name as a string
- Multi-number / multi-WABA support
- Twilio/360dialog/other BSP backends

---

## 6. Effort estimate

Rough guess, assuming the existing Discord code is a reasonable reference:

- Phase 1: 0.5 day
- Phase 2: 1 day
- Phase 3: 1.5 days (webhook verification + payload walking is the fiddly bit)
- Phase 4: 1 day (mostly docs + admin UI polish)
- **Total: ~4 days** of focused work

Meta onboarding (creating the Business account, app, verifying a phone number, getting permanent access token) is **another 1–3 days of calendar time** but not coding time — it's clicking through Meta's UI and waiting on verifications.

---

## 7. Open questions for you

Please review and let me know your preference on each:

1. **Mode A vs Mode B priority** — Build only Mode A (visitor uses WhatsApp, admin replies from wp-admin) for v1, or do you also want Mode B (admin gets notified on their personal WhatsApp and replies from there, Discord-style)? My recommendation: A only, defer B.

2. **Provider choice** — Build against Meta Cloud API directly (free tier, more setup), or against a BSP like Twilio (paid, easier onboarding)? My recommendation: Cloud API direct. Twilio later if customers complain.

3. **Channel column on conversations** — Add a clean `channel` column to `ssc_conversations` (one-time migration), or infer from the existence of thread-table rows? My recommendation: add the column.

4. **24h window UX** — When admin tries to reply outside the 24h window: silent skip with a warning, or block the send entirely until they confirm? My recommendation: save the message in WP but don't send to WhatsApp, surface a clear banner with a "Send re-engagement template" option.

5. **Identity merging** — If the same person uses both the website bubble (cookie hash) and WhatsApp (phone number), should we try to merge their conversations? My recommendation: no merging in v1; treat them as separate conversations. A future "link by email" feature could bridge them.

6. **"Click to Chat" button on the front-end bubble** — Do you want a button next to the chat input that opens `wa.me/{business_number}` so visitors can choose WhatsApp? My recommendation: yes, but as a Phase 4 polish item, opt-in via a setting.

7. **Media messages** — Out of scope for v1, agreed? (Adds non-trivial work: media download from Meta CDN, storage decisions, message rendering changes in both visitor bubble and admin UI.)

8. **Setup-guide audience** — Aim the setup doc at "WordPress admin who has never touched Meta Business" (very long, hand-holding) or at "developer comfortable with Meta tooling" (terse)? My recommendation: the former, matching the depth of `discord-integration-setup.md`.

9. **Pricing / billing visibility** — Meta charges per "service conversation" past the free tier. Do we surface usage / costs anywhere in the admin UI, or leave that entirely to the Meta dashboard? My recommendation: leave it to Meta in v1; revisit if customers request it.

10. **Version target** — Ship as part of `1.08` (focused WhatsApp release) or roll into a larger `3.0` "Messaging Platforms" release that also adds Slack + Telegram? The plan file currently has WhatsApp scheduled in v3.0 alongside other platforms.
