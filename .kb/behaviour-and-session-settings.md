# Behaviour and Session Settings

The **Behaviour** tab (under **Super Speedy Chat** in wp-admin) controls how the chat behaves at runtime: when it nudges visitors, how often it polls, sounds, message limits, and whether login is required. This guide explains each setting and when to change it.

## Admin Reply Timeout

How many seconds the widget waits for an admin reply before triggering the **Timeout Action**. Default is **30 seconds**.

Set this to roughly how long your team realistically takes to respond when online. Too short and visitors get nudged (email prompt / auto-reply) while you're still typing; too long and visitors who'd benefit from a fallback give up first.

## Timeout Action

What happens when the timeout fires with no admin reply:

| Option | Behaviour |
|---|---|
| **Show email prompt** | Asks the visitor to leave an email so you can follow up. |
| **Auto-reply with canned response (LLM)** | Sends the best-matching canned response (requires LLM Auto-Reply configured). |
| **Auto-reply with LLM, then show email prompt** | Tries an auto-reply, then also shows the email prompt. |
| **Do nothing** | No fallback. |

The two LLM options require an API key and saved canned responses — see the LLM Auto-Reply guide.

## Prompt Login After

If a visitor isn't logged in, the widget can suggest logging in (to save their chat history) after they've sent a set number of messages. Default is **5 messages**.

This only shows a gentle prompt — it does not block chatting. To actually require an account, use **Require Login** below. Leave it high (or rely on Require Login) if you don't want to push accounts at all.

## Max Message Length

The maximum characters a visitor can send in one message. Default is **500**. Messages are also truncated server-side to this length as a safeguard. Raise it if your visitors routinely need to paste longer details; keep it modest to discourage abuse.

## Polling intervals

The widget polls for new messages on an adaptive schedule that backs off while the visitor is inactive and speeds back up on activity or a new message:

| Phase | Default (Ultra Ajax on) | Default (off) |
|---|---|---|
| **Poll Interval** (active) | 1000 ms | 2000 ms |
| **Idle Poll Interval** (after ~30s) | 3000 ms | 5000 ms |
| **Deep Idle Poll Interval** (after ~2 min) | 10000 ms | 15000 ms |

Lower values deliver messages faster but make more requests. Because Ultra Ajax makes each request extremely cheap, the more aggressive defaults are safe on most hosting. If you're not running Ultra Ajax and see load issues, raise these. See the Ultra Ajax Performance guide.

## Sounds

- **Play Sounds** — master toggle for chat sounds (new message, open/close).
- **Message Sound** / **Open/Close Sound** — pick from the bundled sound files. Use the **Preview** button to hear them.
- **Sound Volume** — 0–100%. Default 30%.

Sounds play for both visitors (incoming replies) and admins (new waiting conversations).

## Require Login

When ticked, only logged-in WordPress users can chat. Off by default (chat is anonymous). Logged-out visitors see a **log in / create an account** invitation in place of the message box, and the server refuses any chat request that doesn't come from a logged-in user.

Turn this on for membership sites, internal/staff chat, or to cut spam. With login required, conversations are automatically linked to the user's account and their display name is used. Pair it sensibly with the **Prompt Login After** setting — if login is required, the soft prompt is redundant.

One technical note: authenticated requests need full WordPress to check who's logged in, so with Require Login on, chat requests go through the standard REST API instead of the Ultra Ajax fast path. Chat stays quick — it just doesn't get the extra mu-plugin speed-up while this setting is on.

## How these relate to sessions

A visitor's identity is the `ssc_visitor_hash` cookie, set on first interaction (256-bit, HttpOnly, SameSite=Lax, ~1 year). That cookie is what lets a returning visitor resume the same conversation and what the polling settings above operate against. For the privacy implications of that cookie and the stored session data, see the Privacy and Data Handling guide.
