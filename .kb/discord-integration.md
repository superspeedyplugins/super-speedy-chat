# Discord Integration: Chat With Visitors From Discord

Super Speedy Chat can bridge your website chat into Discord, so your team can answer visitors from the place they already hang out — no extra dashboard to babysit. This is a feature overview: what it does and why you'd use it. For step-by-step connection instructions, see the Discord Integration Setup guide; for keeping the relay bot running, see the Discord Companion Bot guide.

## What it does

- **Every conversation becomes a Discord thread.** When a visitor starts chatting, the integration opens a thread in your chosen Discord channel, seeded with the visitor's context (name, and where available their page, email, and IP).
- **Visitor messages appear in Discord instantly.** Each message the visitor sends is pushed into that thread in real time.
- **Your Discord replies reach the visitor.** Reply in the thread and the visitor sees it in the chat widget within a second or two (this direction uses the companion bot).
- **It's bidirectional and persistent.** The whole conversation lives in both places — the WordPress inbox and the Discord thread stay in sync.

## Why use it

- **Answer from where your team already is.** If your team lives in Discord, you don't need to watch wp-admin. Notifications, mobile apps, and your existing workflow all just work.
- **Mobile support without an app.** The Discord mobile app effectively becomes your live-chat console.
- **Shared team visibility.** Everyone in the channel can see incoming chats and pitch in, with threads keeping each conversation tidy.
- **No lost conversations.** Replies sync back to WordPress, so the record (and any visitor email follow-up) stays intact.

## How the two directions work

| Direction | What's needed |
|---|---|
| **WordPress → Discord** (visitor messages appear as threads) | Automatic once you enter the bot token + channel ID and enable the integration. |
| **Discord → WordPress** (your replies reach visitors) | The small companion Node.js bot must be running on a server. |

Without the companion bot, visitor messages still flow to Discord instantly — you just reply from wp-admin instead of from Discord.

## Safety and security built in

- **No accidental pings.** Visitor text pushed to Discord has all mention parsing disabled, so a visitor can't make the bot ping `@everyone` or your roles.
- **Authenticated relay.** The companion bot authenticates to WordPress with a shared secret (timing-safe check), so only your bot can post replies into conversations.
- **Your data, disclosed.** Because conversation context is relayed to Discord, mention this in your privacy policy — see the Privacy and Data Handling guide.

## Get started

1. **Discord Integration Setup** — create the bot, get the token/channel ID, enter them, and enable the integration.
2. **Discord Companion Bot** — deploy and keep the relay bot running so replies flow back.

Together those two give you a full two-way bridge between your website chat and Discord.
