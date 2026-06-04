# Super Speedy Chat — KB Contents & Coverage Map

An index of every knowledge-base guide for Super Speedy Chat, organised by the KB category **term** it belongs under, plus a list of suggested articles still to write.

## The four standard terms

KB articles are filed two levels deep: the plugin parent term (`super-speedy-chat`) → a child **section** term. We standardise on **four section terms across all plugins**:

1. **Getting Started** — install, basic use, and configuring the core features.
2. **Features** — what the plugin can do (the capabilities, explained).
3. **Advanced** — performance, privacy, troubleshooting, and deeper operational topics.
4. **Developers** — architecture, hooks, REST API, and extending the plugin.

> **Verify before publishing.** Child terms are resolved by *name* under the `super-speedy-chat` parent. Confirm these four exist as children of `super-speedy-chat` before uploading; create any missing ones (with confirmation) so every article has a home.

## Articles by term

### Getting Started

| Article | File | Audience | Covers |
|---|---|---|---|
| Quick Start Guide | `.kb/quick-start-guide.md` | Site owner | Install/activate, verify the bubble, test message, reply from wp-admin, settings overview. |
| Managing Conversations | `.kb/managing-conversations.md` | Site owner | The Chats inbox: filters, assignment, detail view, statuses, saving canned responses. |
| Customizing the Chat Widget Appearance | `.kb/customizing-appearance.md` | Site owner | Customizer: header image/title, colours, position, trigger icon. |
| Email Notifications | `.kb/email-notifications.md` | Site owner | Admin + visitor notifications, the email-collection prompt, SMTP tips. |
| Behaviour and Session Settings | `.kb/behaviour-and-session-settings.md` | Site owner | Timeout/action, login prompt, max length, poll intervals, sounds, require-login. |
| Display Names | `.kb/display-names.md` | Site owner | Shared vs individual mode, per-user chat name, how it appears (incl. Discord). |
| Discord Integration Setup Guide | `.kb/discord-integration-setup.md` | Site owner | Connecting Discord: bot token, channel ID, companion bot. |

### Features

| Article | File | Audience | Covers |
|---|---|---|---|
| Canned Responses | `.kb/canned-responses.md` | Site owner | Saving/managing canned responses and how they feed the classifier. |
| LLM Auto-Reply Setup Guide | `.kb/llm-auto-reply-setup.md` | Site owner | OpenAI/Anthropic config, timeout action, how it works, cost, troubleshooting. |
| Discord Integration: Chat With Visitors From Discord | `.kb/discord-integration.md` | Site owner | Feature overview — what the Discord bridge does and why to use it. |

### Advanced

| Article | File | Audience | Covers |
|---|---|---|---|
| Ultra Ajax Performance Guide | `.kb/ultra-ajax-performance.md` | Site owner / advanced | The mu-plugin: install/auto-update, verifying, polling, rate limits, troubleshooting. |
| Privacy and Data Handling | `.kb/privacy-and-data-handling.md` | Site owner | What's stored, the cookie, what leaves your server, retention, uninstall, GDPR checklist. |
| Troubleshooting and FAQ | `.kb/troubleshooting-and-faq.md` | Site owner | Consolidated first-stop fixes + frequently asked questions. |

### Developers

| Article | File | Audience | Covers |
|---|---|---|---|
| Architecture Overview | `.kb/architecture-overview.md` | Developer | Component map, design decisions, per-component internals, message lifecycle. |
| Developer Guide: Hooks and Building Channel Add-ons | `.kb/developer-hooks-and-add-ons.md` | Developer | Lifecycle actions, registration hooks, inbound helpers, the add-on registry. |
| REST API Reference | `.kb/rest-api-reference.md` | Developer | `ssc/v1` endpoints, auth model, rate limits, request/response shapes. |
| Discord Companion Bot: Running It in Production | `.kb/discord-companion-bot-operations.md` | Developer / admin | Deploying the Node relay bot (PM2/systemd), updating, secret rotation, troubleshooting. |

## Related files in `.docs/` (specs, not KB articles)

`.docs/` holds specs and internal docs for us to review and implement — not website content. These stay there:

| File | What it is |
|---|---|
| `.docs/addons-system-plan.md` | Internal architecture/build plan for the add-on system. |
| `.docs/whatsapp-integration-plan.md` | Internal build plan for a future WhatsApp channel. |
| `.docs/maybe-in-future.md` | Backlog of deferred features. |
| `.docs/2026-06-03-security-review.md` | Internal security review + remediation log. |
| `.docs/landing-page.md` | Marketing landing-page copy. *Website content, not a spec — may belong in `.kb/` under the new workflow; left in `.docs/` pending a decision.* |

## Pending / suggested articles

Not yet written — candidates worth adding, with their intended term.

| Suggested article | Term | Why |
|---|---|---|
| Spam & Abuse Prevention | Advanced | Pull together the honeypot, per-IP rate limits, require-login, and LLM daily cap into one "harden your chat" guide. |
| Caching & CDN Compatibility | Advanced | How to exclude `/wp-json/ssc/` from page/edge caches; why a cached config breaks the bubble. A very common support theme. |
| Multisite Setup | Advanced | Behaviour of the network-wide Ultra Ajax mu-plugin and per-site enablement. |
| Migrating From Another Chat Plugin | Getting Started | Switching from Tidio/Crisp/etc. — what carries over, what doesn't. |
| Translating Super Speedy Chat (i18n) | Developers | Text domain, translatable strings, front-end string overrides via the `ssc_frontend_config` filter / `window.ssc.hooks`. |
| GDPR Export & Erase Integration | Developers / Advanced | Currently the plugin does **not** register WordPress's personal-data exporters/erasers — document the manual process now, and revisit when/if that integration is built (also flagged in Privacy and Data Handling). |
| Conversation Export & Reporting | Features | If/when export or basic analytics are added; not currently a feature, so write only once it exists. |

## Folder convention

- **`.kb/`** — KB articles and other website-bound content. This is the source of truth for everything published to superspeedyplugins.com. **All KB articles now live here.**
- **`.docs/`** — specs and internal docs for us to review and implement (build plans, the security review, the deferred-features backlog).

The eight original guides were moved from `.docs/` into `.kb/` (as tracked git renames, history preserved). The one open question is `.docs/landing-page.md` — it's website content rather than a spec, so under this convention it arguably belongs in `.kb/`; it's left in `.docs/` pending your call.
