# What is Super Speedy Chat?

Super Speedy Chat is the fastest live chat plugin for WordPress. It puts a lightweight chat bubble on your website's front end so visitors can message you in real time — and you can reply directly from your WordPress admin dashboard or from Discord.

Unlike bloated live chat services that slow your site down, Super Speedy Chat uses an ultra-fast AJAX mu-plugin to handle chat messages, keeping your Time to First Byte (TTFB) as low as possible and thus delivering an instant chat experience.

## Key Features

- **Instant chat bubble** — A chat widget appears on every page of your site. Visitors can open it, type a message, and reach you immediately.
- **Ultra Ajax (mu-plugin)** — Chat requests bypass the normal WordPress request stack via a mu-plugin, making the chat extremely fast and low-impact on server resources.
- **Discord integration** — Visitor messages appear instantly in a Discord thread. Replies you type in Discord are delivered back to the visitor in real time. Full bidirectional chat without staying logged in to WordPress.
- **Canned responses** — Save frequently used answers as canned responses. Organise them by category and search them by keyword for quick use during conversations.
- **Email fallback** — If you don't reply within a configurable timeout, the visitor is prompted to leave their email address so you can follow up. Admin and visitor email notifications are both supported.
- **Conversation management** — A full conversation list in wp-admin with filters (All, Active, Waiting, Closed), live stats, visitor info (name, email, IP, referrer, page URL), and pagination.
- **Display name control** — Choose a single shared display name for all admins, or let each admin set their own personal chat name.
- **Customiser appearance settings** — Control the chat window title, primary colour, header image, bubble position (bottom-right or bottom-left), and trigger button icon without touching code.
- **Sound notifications** — Optional audio alerts for new messages on both the visitor and admin side.
- **Login prompts** — Optionally require visitors to log in before chatting, or prompt them to log in after a configurable number of messages.

## Who Is It For?

Super Speedy Chat is ideal for:

- **WooCommerce store owners** who need a fast, low-overhead support chat without adding seconds to their page load times.
- **Teams already using Discord** who want to handle customer support conversations directly inside their Discord server.
- **Developers and site owners** who want full control over the chat widget's appearance and behaviour without relying on a third-party SaaS platform.
- **Anyone who has found other chat plugins too slow or too heavy or too expensive** for their high-traffic WordPress site.

## How It Works

When a visitor opens the chat widget and sends a message, the request is handled by the Ultra Ajax mu-plugin, which intercepts the AJAX call before the full WordPress stack loads. This keeps response times in the tens of milliseconds.

Messages are stored in custom database tables (`ssc_conversations`, `ssc_participants`, `ssc_messages`). The admin dashboard polls for new messages on a configurable interval, and the visitor's widget polls on a separate (optionally slower) idle interval.

If Discord integration is enabled, each new conversation creates a thread in your chosen Discord channel. A companion Node.js bot listens on the Discord Gateway and relays your Discord replies back to WordPress via a secured REST endpoint.
