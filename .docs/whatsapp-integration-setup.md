# WhatsApp Integration — Setup Guide

Applies to Super Speedy Chat 1.11.0+. The integration talks directly to the
Meta WhatsApp Cloud API — there is **no companion bot** to install (unlike
Discord). Meta delivers inbound messages straight to your site's REST webhook.

Two independent features, use either or both:

- **Visitor channel** — visitors message your WhatsApp business number (via a
  "Chat on WhatsApp" link in the bubble, a wa.me link, or a QR code) and the
  conversation appears in the admin dashboard like any other. Your replies (from
  wp-admin, Discord, or your own phone) are delivered back to their WhatsApp.
- **Forward to your phone** — every chat message (website bubble or WhatsApp)
  is forwarded to your personal WhatsApp, tagged `[#123 Jane]`. Quote-reply
  (swipe right) on a forwarded message to answer; the reply is routed back to
  that conversation and on to the visitor wherever they are.

---

## Part 1 — Meta setup (one-time, ~30–60 min plus verification waits)

You need: a Facebook account, a Meta Business portfolio, and a phone number
that is NOT already registered on regular WhatsApp (Meta requires a dedicated
number for the Cloud API — a cheap SIM or virtual number works).

1. Go to https://developers.facebook.com/apps/ and **Create App** → type
   **Business**. Attach (or create) your Meta Business portfolio.
2. On the app dashboard, **Add Product → WhatsApp**.
3. Under **WhatsApp → API Setup**:
   - Add your business phone number and verify it by SMS/voice.
   - Copy the **Phone Number ID** (the long numeric ID shown under the number —
     not the number itself).
4. Create a **permanent access token** (the API Setup page's temporary token
   expires in 24h):
   - business.facebook.com → **Business Settings → Users → System Users** →
     Add a system user (Admin role).
   - **Add Assets** → assign your app with full control.
   - **Generate New Token** → select your app → tick
     `whatsapp_business_messaging` and `whatsapp_business_management` →
     generate and copy the token.
5. Copy the **App Secret** from **App Settings → Basic** (click Show).

## Part 2 — Plugin settings

In wp-admin → Super Speedy Chat → Settings → **WhatsApp** tab:

1. Paste the **Access Token**, **Phone Number ID** and **App Secret**.
2. Enter your **Business Phone Number** in international format
   (e.g. `+447123456789`) — used for the visitor-facing wa.me link.
3. Tick **Enable WhatsApp** and Save.
4. Click **Test Connection** — you should see your verified name and number.

## Part 3 — Webhook (inbound messages)

1. The WhatsApp tab shows a **Callback URL** and **Verify Token** — copy both.
2. In the Meta app: **WhatsApp → Configuration → Webhook** → Edit → paste the
   Callback URL and Verify Token → **Verify and Save**. (Your site must be
   public HTTPS — Meta cannot reach a local dev site.)
3. Under **Webhook fields**, subscribe to **messages**.
4. Send a WhatsApp message from any phone to your business number — it should
   appear in the admin Conversations list within seconds, with a WhatsApp badge.

## Part 4 — Forward to your phone (optional but recommended)

1. Tick **Forward to Your Phone** and enter **Your WhatsApp Number**.
2. **From your phone, send any message to the business number** (e.g. "hi").
   This opens Meta's 24-hour customer-service window that allows the site to
   message you. The window re-opens every time you reply to a forwarded
   message, so in normal use it stays open by itself; after a quiet day it
   needs another nudge (or a re-engagement template, below).
3. Test: send a message from the website chat bubble — it should arrive on
   your phone as `💬 [#12 Visitor] hello`. Swipe right on it (quote-reply) and
   type an answer — it appears in the website bubble within a poll interval.
   If you reply without quoting, the plugin messages you back asking you to
   quote-reply (or you can start a message with `#12` to target a conversation
   manually).

## The 24-hour window (Meta's rule, not ours)

Free-form messages can only be SENT to a number that has messaged you within
the last 24 hours. This affects two things:

- **Replies to visitors** — fine in practice, you're replying to someone who
  just messaged. If a visitor's window has expired the reply is saved in
  WordPress but not delivered; the conversation sidebar shows the window
  status and a warning appears under the reply box.
- **Forwarding to your phone** — your own window must be open (see Part 4).

**Re-engagement template (optional):** create a template under Meta →
WhatsApp Manager → Message Templates (e.g. "You have a new reply — message us
back to continue"), wait for approval, then put its name and language code in
the plugin's **Re-engagement Template** fields. It is sent automatically when
a window has expired — both to visitors (instead of the undeliverable reply)
and to your own number (max once per 12h) to prompt you to re-open your
window. While testing you can use Meta's pre-approved `hello_world` template
(language `en_US`).

## Notes and limits (v1)

- Text messages only. Inbound media arrives as a placeholder
  ("[Received a image — view it in WhatsApp]") with a wa.me link to the
  visitor's chat in the sidebar.
- Discord and WhatsApp stay in sync: a Discord reply reaches a WhatsApp
  visitor, a phone quote-reply is mirrored into the Discord thread, and
  wp-admin replies go everywhere.
- Meta pricing: the first 1,000 service conversations per month are free;
  check Meta's WhatsApp pricing page for your country's rates beyond that.
- Webhook security: inbound requests are HMAC-verified against the App
  Secret. If the App Secret field is empty, ALL inbound messages are rejected.
- The admin's number and a visitor's number must be different phones — a
  message from the admin number is always treated as an admin reply.
