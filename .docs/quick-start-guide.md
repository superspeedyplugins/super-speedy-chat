# Quick Start Guide

Get Super Speedy Chat running on your WordPress site in under five minutes. This guide walks you through installation, sending your first test message, and knowing where to find the key settings.

---

## 1. Install and Activate

**Option A -- WordPress Plugin Directory:**

1. In wp-admin, go to **Plugins > Add New**.
2. Search for "Super Speedy Chat".
3. Click **Install Now**, then **Activate**.

**Option B -- Upload ZIP:**

1. In wp-admin, go to **Plugins > Add New > Upload Plugin**.
2. Choose the `.zip` file and click **Install Now**.
3. Click **Activate**.

On activation, two things happen automatically:

- The database tables for storing conversations and messages are created.
- An mu-plugin (`ssc-fast-ajax.php`) is installed to `wp-content/mu-plugins/`. This handles chat polling with minimal overhead -- bypassing the full WordPress stack -- so responses are ultra-fast. No configuration needed.

---

## 2. Verify the Chat Bubble

1. Open your site's front end in a browser (not wp-admin).
2. You should see a chat bubble in the bottom-right corner of the page.
3. If the bubble does not appear, go to **Super Speedy Plugins > Super Speedy Chat** in wp-admin, open the **General** tab, and confirm "Enable Chat" is checked.

---

## 3. Send a Test Message

1. Open your site in a **separate browser** or an **incognito/private window** (so you are not logged in as admin).
2. Click the chat bubble.
3. You will see the welcome message ("Hi! How can we help you today?" by default).
4. Type a test message and hit send.

---

## 4. View the Conversation in wp-admin

1. Go back to your wp-admin browser.
2. Navigate to **Super Speedy Plugins > Super Speedy Chat**. The **Chats** tab is shown by default.
3. Your test conversation should appear in the list. If you don't see it yet, wait a moment -- the admin panel polls for new conversations automatically.
4. Click **View** to open the conversation.

---

## 5. Reply from wp-admin

1. Inside the conversation view, type a reply in the message box and send it.
2. Switch back to the other browser (the incognito/visitor window).
3. The reply should appear in the chat bubble within a second or two.

That's it -- you have a working live chat. Visitor-to-admin, real-time, both directions.

---

## 6. Key Settings to Know About

All settings are under **Super Speedy Plugins > Super Speedy Chat** in wp-admin, organized into tabs:

| Tab | What it controls |
|---|---|
| **General** | Enable/disable the chat bubble, toggle Ultra Ajax (the mu-plugin), set the welcome message. |
| **Display Names** | Choose whether admins appear under their own name or a shared name like "Support". |
| **Behaviour** | Admin reply timeout and timeout action (e.g. show an email prompt or trigger an LLM auto-reply), poll intervals, notification sounds, max message length. |
| **Email** | Admin email notifications on new conversations, visitor email notifications when you reply while they are offline. |
| **Canned Responses** | Pre-written answers the LLM classifier can match against visitor questions. |
| **LLM Auto-Reply** | Connect an OpenAI or Anthropic API key so the plugin can auto-reply with the best-matching canned response when you are away. |
| **Discord** | Bidirectional Discord integration -- visitor messages appear as Discord threads, and Discord replies are relayed back to the chat. |
| **Status** | System info and diagnostics. |

For advanced setup, see the other guides in this directory:

- [Discord Integration Setup](discord-integration-setup.md) -- full walkthrough for connecting Discord.
