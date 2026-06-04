# Privacy and Data Handling

Super Speedy Chat stores conversation data on your own server and, depending on which features you enable, may send some of it to third parties (OpenAI/Anthropic or Discord). This guide explains exactly what is collected, where it lives, what leaves your server, and how to stay compliant with privacy regulations like GDPR.

> This is general guidance, not legal advice. If you operate in a regulated jurisdiction, have your privacy policy reviewed by someone qualified.

## What data is collected

When a visitor uses the chat, the plugin records the following in your WordPress database:

| Data | When | Why |
|---|---|---|
| Visitor hash (cookie) | First visit to a page with the chat | Identifies a returning visitor without requiring login. |
| Messages | Each message sent | The conversation itself. |
| IP address | Conversation start | Shown to admins for context / abuse handling. |
| User agent (browser/OS) | Conversation start | Shown to admins for context. |
| Referrer URL | Conversation start | Where the visitor came from. |
| Page URL | Each message | Which page the visitor was on. |
| Visitor email | Only if the visitor enters it | Offline reply notifications. |
| WordPress user ID + display name | Only if the visitor is logged in | Links the chat to their account. |

### The `ssc_visitor_hash` cookie

The chat sets one cookie, `ssc_visitor_hash`:

- It's a random 256-bit token (not derived from any personal data).
- It is **HttpOnly** (JavaScript can't read it), **SameSite=Lax**, and **Secure** when your site is served over HTTPS.
- It lasts up to **one year**, so a returning visitor sees their previous conversation.
- It is **functional, not advertising** — it isn't used to track visitors across other sites, and it carries no profiling data. It simply lets the server recognise "this is the same browser" so the right conversation loads.

Under most cookie-consent frameworks a strictly-functional cookie like this can be classed as essential, but you should still disclose it in your cookie/privacy notice. If you operate under strict consent rules, note that the cookie is only ever set once the visitor interacts with the chat.

## Where the data is stored

Everything lives in custom database tables on your own server:

- `ssc_conversations`, `ssc_participants`, `ssc_messages` — the conversations and messages.
- `ssc_canned_responses` — your saved replies (not visitor data).
- `ssc_discord_threads` — Discord thread mappings (only if Discord is used).

Admins see visitor details (name, email, IP, user agent, referrer, page URL) in the **conversation detail sidebar** in wp-admin.

## What leaves your server

By default, **nothing** — all chat data stays on your server. Two optional features change that:

### LLM Auto-Reply (if enabled)

When auto-reply fires, the visitor's question and your canned responses are sent to **OpenAI or Anthropic** (whichever you configured) to pick a matching response. If you enable this, your privacy policy should disclose that visitor messages may be processed by a third-party AI provider, and you should review that provider's data-handling terms. The plugin sends only the visitor's latest question plus your canned-response list — not IP, email, or history.

### Discord integration (if enabled)

When Discord is enabled, visitor messages are pushed to a Discord channel as threads. The **thread starter message includes the visitor's name and, when available, their page URL, email, and IP address**, so your support team has context. If you enable this, disclose that conversation data is relayed to Discord and is subject to Discord's data handling.

> Both integrations are **off by default**. If you don't configure them, no chat data ever leaves your server.

## Data retention

There is no automatic expiry — conversations are kept until you delete them or uninstall the plugin. To manage retention manually:

- **Close** conversations you've finished with (sets status to `closed`; the data is retained).
- To fully remove data, delete the rows at the database level, or uninstall the plugin (see below).

## Removing visitor data (data subject requests)

To find a specific visitor's data, use the search box on the **Chats** tab — it matches on visitor name and email. To honour a deletion request today, you currently need to delete the relevant rows directly in the database, as the plugin does not yet register with WordPress's built-in personal-data **export/erase** tools (Tools → Export/Erase Personal Data). If that integration matters to you, note it as a current limitation.

## Uninstalling removes everything

Deleting the plugin (not just deactivating) runs `uninstall.php`, which:

- Drops all `ssc_*` database tables (conversations, participants, messages, canned responses, Discord threads).
- Deletes the plugin's options (`ssc_options`, `ssc_db_version`, `ssc_customizer`).
- Removes the Ultra Ajax mu-plugin from `wp-content/mu-plugins/`.

Deactivation alone keeps your data; only a full uninstall wipes it.

## Checklist for your privacy policy

- [ ] Mention the functional `ssc_visitor_hash` cookie (purpose, ~1 year).
- [ ] State that chat messages and basic technical metadata (IP, browser, referrer) are stored.
- [ ] If LLM Auto-Reply is on, disclose the AI provider and link its terms.
- [ ] If Discord is on, disclose that conversations are relayed to Discord.
- [ ] Describe how visitors can request access or deletion of their chat data.
