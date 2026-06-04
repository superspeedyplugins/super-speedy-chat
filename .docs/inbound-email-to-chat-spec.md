# Spec: Inbound Email → Chat (Email Reply Threading)

Let admin and visitor email replies flow **back into the conversation as chat messages**, so "Email fallback" becomes a true two-way channel rather than one-way notifications. This is the full version of the feature; an interim Reply-To shim (below, "Phase 0") already ships so the marketing copy holds today.

## Today's behaviour (baseline)

Outbound notifications only, no inbound handling:

- First visitor message → admin gets a "new chat" email (link to wp-admin).
- Admin replies **in wp-admin/Discord** → if the visitor left an email, the visitor gets an email with the reply text + a link back to the site.
- Nothing parses replies; emails carry no routing token.

## Phase 0 — Reply-To shim (shipped)

Make replies reach the *person* by email, without parsing anything back into the chat:

- Admin emails set **`Reply-To: <visitor>`** once the visitor's address is known; when the visitor submits their email, the admin gets a notification whose `Reply-To` is the visitor, so a plain "Reply" reaches them.
- Visitor reply emails set **`Reply-To: <admin>`** so the visitor's reply reaches the team.

Limitation: those replies are ordinary emails — they do **not** appear in the chat thread or wp-admin. Phases 1–2 fix that.

## Goal (Phases 1–2)

When someone replies to a chat email, the reply is appended to the correct conversation as a message (admin reply or visitor message) and shows up in wp-admin, the widget, and any connected channel (Discord, etc.) — reusing the existing lifecycle so nothing else has to change.

## Architecture

### Routing token (no spoofing)

Every outbound chat email embeds a **signed token** that identifies the conversation and the recipient role. Routing must rely on the token, **never** on the `From` address (trivially forged).

- Token payload: `conversation_id` + `role` (`admin` | `visitor`) + `participant_id` (optional).
- Signature: `HMAC-SHA256(payload, site_secret)` — `site_secret` is a dedicated option generated once (like the Discord webhook secret), not `AUTH_KEY`.
- Encoding: short, URL/email-safe (base64url of `payload.sig`, truncated sig to ~16–24 bytes).
- Carried in **both**:
  - the `Reply-To` local part (sub-addressing): `chat+<token>@reply.yourdomain.com`, and
  - a hidden marker in the body (`[ref:<token>]`) as a fallback when sub-addressing is stripped.

### Inbound transport (two supported methods)

1. **Inbound webhook (recommended).** Use a provider that POSTs inbound mail as JSON/multipart — Postmark "inbound", SendGrid Inbound Parse, Mailgun Routes, Cloudflare Email Workers. Point an MX/route at the provider; it calls a new REST endpoint.
   - Route: `POST /ssc/v1/email/incoming`, `permission_callback => __return_true`, authenticated by the **provider's signature** (e.g. Postmark Basic-Auth/token, SendGrid/Mailgun HMAC) — verified before processing.
2. **IMAP polling (fallback, self-hosted).** A WP-Cron job (e.g. every minute via a tightened schedule) connects to a mailbox over IMAP, reads unseen messages, processes, and marks them seen. Requires the `imap` PHP extension; degrade gracefully if absent.

Both converge on the same handler.

### Processing pipeline

1. **Authenticate** the inbound request (provider signature, or IMAP = trusted mailbox).
2. **Extract the token** from `To`/`Reply-To` sub-address, else the body `[ref:...]` marker. No valid token → drop (log + optional bounce).
3. **Verify HMAC** and parse `conversation_id` + `role`. Reject on mismatch.
4. **Load the conversation**; bail if missing/closed (configurable: reopen on inbound?).
5. **Extract the reply text** — strip quoted history and signatures (reply-above-the-line: cut at the first quote marker / `On … wrote:` / `-- ` signature delimiter). Prefer `text/plain`; convert `text/html` → text if that's all there is.
6. **Insert the message** by role:
   - `role = visitor` → `SSC_Chat::external_inbound([... author_type=visitor ...])` (or a dedicated path) → fires `ssc_visitor_message_sent`, status → `waiting`.
   - `role = admin` → resolve the WP user by the authenticated `From` if possible (else generic "Support") → `SSC_Chat::send_admin_reply()` (or `external_inbound` admin) → status → `active`.
7. **Dedup** on provider Message-ID (store last seen, or a small processed-ID table) so retried webhooks don't double-post.
8. **Attachments**: out of scope v1 — ignore, or store a note "(attachment omitted)".

### Loop & abuse prevention

- Don't email a notification back out for a message that *arrived* by email to the same party (avoid ping-pong) — tag email-originated messages (e.g. `metadata.source = 'email'`) and skip re-notifying that recipient.
- Ignore auto-responders/bounces: check `Auto-Submitted: auto-*`, `Precedence: bulk/auto_reply`, empty/`<>` envelope-from.
- Rate-limit inbound per conversation; cap message length; sanitize with `sanitize_textarea_field`.
- Reject tokens for `closed`/`archived` conversations unless "reopen on reply" is enabled.

## Settings (new "Email" tab fields)

- **Inbound replies**: Off (default) / Webhook / IMAP.
- Webhook: show the `POST /ssc/v1/email/incoming` URL + the signing secret; pick provider (for signature scheme).
- IMAP: host, port, encryption, username, password, mailbox, polling interval.
- **Reply-To domain / inbound address** (e.g. `reply.yourdomain.com` or `chat@…`).
- **Reopen closed conversations on email reply** (bool).
- Store the inbound `site_secret` in `ssc_options` (auto-generated, like the Discord secret).

## Data / schema

- No new tables required for routing (HMAC is stateless). Optional: a tiny `ssc_email_seen` table (or transient set) for Message-ID dedup.
- Optionally add `metadata.source` usage on messages to tag email-origin.

## Deliverability notes

- Send from a real domain with SPF + DKIM; never spoof the visitor's address as `From`.
- Sub-addressing (`chat+token@`) needs the receiving domain/provider to preserve the local-part tag — verify per provider.
- Provide the body `[ref:...]` fallback for clients/providers that strip tags.

## Reuse / touch-points in the current code

- Insertion: `SSC_Chat::external_inbound()` and `SSC_Chat::send_admin_reply()` already handle participant creation, status transitions, and lifecycle hooks — the inbound handler should call these, not write rows directly.
- Secret pattern: mirror `SSC_Discord::get_webhook_secret()` / `verify_secret()` (`hash_equals`).
- Route registration: hook `ssc_register_rest_routes` (same as Discord) to add `/email/incoming`.
- Email building: extend `SSC_Email` to embed the token in `Reply-To` + body for outbound (Phase 1 prerequisite), then add the inbound handler class (`SSC_Email_Inbound`).

## Phasing

- **Phase 0 (done):** Reply-To shim — replies reach the person by email (not into the chat).
- **Phase 1:** Embed signed tokens in outbound emails (`Reply-To` sub-address + body marker). No inbound yet, but every email becomes routable.
- **Phase 2:** Inbound webhook endpoint + processing pipeline → replies land in the conversation. (Recommended first real inbound method — no server extensions, provider verifies signature.)
- **Phase 3:** IMAP polling fallback for fully self-hosted setups.

## Testing

- Unit: token sign/verify round-trip; reject tampered/expired tokens; quoted-history stripping fixtures.
- Integration (WP-CLI, mirroring `tests/`): simulate an inbound payload → assert a message is appended to the right conversation with the right `participant_type`, status transition fires, and dedup prevents doubles.
- Security: forged `From` cannot post without a valid token; provider-signature failure is rejected; closed-conversation tokens rejected.
