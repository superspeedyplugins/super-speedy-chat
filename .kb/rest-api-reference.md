# REST API Reference

All endpoints live under the `ssc/v1` namespace (`/wp-json/ssc/v1/…`). There are two tiers: **visitor** endpoints (public, used by the chat widget) and **admin** endpoints (require the `manage_options` capability).

> When Ultra Ajax is enabled, the visitor endpoints (`/session`, `/send`, `/poll`, `/auto-reply`) are served by the mu-plugin *before* full WordPress loads, but the request/response shapes are identical to the REST routes documented here. Admin routes always go through the full REST API.

## Authentication model

| Tier | Auth |
|---|---|
| Visitor endpoints | Public (`permission_callback` is open). Identity is the `ssc_visitor_hash` cookie; each request resolves the caller's own conversation from that cookie only. Protected by per-IP rate limiting + a honeypot field. |
| Admin endpoints | `current_user_can('manage_options')` + the standard WordPress REST nonce (`X-WP-Nonce`). |
| Discord inbound | Shared secret in the `X-SSC-Secret` header, compared with `hash_equals()`. |

## Rate limits (per IP)

| Endpoint | Limit |
|---|---|
| `/send` | 15 / minute |
| `/session` | 10 / minute |
| `/auto-reply` | 3 / minute |
| `/poll` | not limited (read-only, frequent) |

Exceeding a limit returns HTTP 429.

## Visitor endpoints

### POST `/session`
Create or resume the caller's conversation.
**Returns:** `{ conversation_id, visitor_hash, messages[], status }`

### POST `/send`
Send a visitor message.
**Params:** `message` (string), `page_url` (string, optional), `website_url` (honeypot — leave empty; if filled, the request is silently dropped).
**Returns:** `{ message_id, conversation_id }`. Requires a session cookie (else 403). Empty messages are rejected (400).

### GET `/poll`
Fetch messages newer than a given id.
**Params:** `since_id` (int).
**Returns:** `{ messages[], conversation_id }`. Requires a session cookie (else 403).

### POST `/email`
Attach the visitor's email to their conversation (for offline reply notifications).
**Params:** `email` (string).
**Returns:** `{ success: true }`. Invalid addresses are rejected (400).

### POST `/auto-reply`
Request an LLM-classified canned reply when no admin has responded.
**Params:** `question` (string).
**Returns:** `{ auto_replied: bool, reason?, message_id?, canned_id? }`. No-ops (with a `reason`) when the LLM isn't configured, there's no visitor message yet, the conversation was already auto-replied to, or the daily cap is reached.

## Admin endpoints

All require `manage_options` + nonce.

| Method & route | Purpose | Key params |
|---|---|---|
| GET `/admin/conversations` | List conversations | `status`, `search`, `assigned_to`, `per_page`, `page` |
| GET `/admin/conversation/{id}` | One conversation + messages | `since_id` (optional) |
| POST `/admin/reply` | Reply to a conversation | `conversation_id`, `message` |
| POST `/admin/close/{id}` | Close a conversation | — |
| POST `/admin/assign/{id}` | Assign/unassign | `assigned_to` (user id, or `0`/empty to unassign) |
| GET `/admin/canned` | List canned responses | `search`, `category`, `per_page`, `page` |
| POST `/admin/canned` | Create a canned response | `question`, `response`, `category`, `source_message_id` |
| PUT/PATCH `/admin/canned/{id}` | Update a canned response | `question`, `response`, `category` |
| DELETE `/admin/canned/{id}` | Delete a canned response | — |
| POST `/admin/discord/test` | Test the Discord bot connection | — |

Typical responses are `{ success: true }`, an object with an `id`/`message_id`, or a list payload `{ items[], total, per_page, page, total_pages }`. Unknown ids return 404; missing required params return 400.

## Discord inbound (add-on route)

### POST `/discord/incoming`
Used by the companion Discord bot to relay a Discord reply into a conversation.
**Auth:** `X-SSC-Secret` header (shared secret).
**Params:** `thread_id`, `author_name`, `message`.
**Returns:** `{ success: true, message_id }`, or 401 (bad secret) / 404 (unknown thread).

## Example calls

Visitor send (browser, with nonce optional on public routes):

```bash
curl -X POST https://example.com/wp-json/ssc/v1/send \
  -H 'Content-Type: application/json' \
  -b 'ssc_visitor_hash=<cookie>' \
  -d '{"message":"Hi there","page_url":"https://example.com/pricing"}'
```

Admin list (must be authenticated as an admin with a valid nonce):

```bash
curl https://example.com/wp-json/ssc/v1/admin/conversations?status=waiting \
  -H 'X-WP-Nonce: <wp_rest nonce>' \
  --cookie-jar/--cookie <wp-auth-cookies>
```

> The `tests/test-rest-visitor-flow.php`, `tests/test-rest-admin-flow.php`, and `tests/test-fast-ajax-path.php` files exercise these endpoints and serve as an executable spec for the exact shapes and status codes.
