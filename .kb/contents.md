# Super Speedy Chat — KB Contents & Coverage Map

An index of every knowledge-base guide for Super Speedy Chat, organised by the KB category **term** it belongs under, with the publication status of each article, plus a list of suggested articles still to write.

**Status audit last run: 2026-06-05** (local `.kb/` files were content-diffed against the live site via the REST API).

## Status legend

| Status | Meaning |
|---|---|
| **unwritten** | Idea only — no local file yet. |
| **local** | Written in `.kb/` but never uploaded to the site. |
| **draft** | Uploaded to superspeedyplugins.com as a draft — needs review + publish. |
| **live** | Published on the site and in sync with the local file. |
| ⚠ **changed** | Live copy and local file have drifted — see the note; reconcile before editing either side. |

## The four standard terms

KB articles are filed two levels deep: the plugin parent term (`super-speedy-chat`, id 4534) → a child **section** term. We standardise on **four section terms across all plugins**:

1. **Getting Started** (id 4535) — install, basic use, and configuring the core features.
2. **Features** (id 4621) — what the plugin can do (the capabilities, explained).
3. **Advanced** (id 4622) — performance, privacy, troubleshooting, and deeper operational topics.
4. **Developers** (id 4623) — architecture, hooks, REST API, and extending the plugin.

> ⚠ **Legacy terms still in use on the live site.** Three extra child terms exist with published articles in them: **Configuration** (4597), **Automation** (4598) and **Performance** (4599). The five articles filed there (marked ⚠ *re-file* below) should be moved into the standard four next time each is touched, and the legacy terms deleted once empty.

## Articles by term

### Getting Started

| Article | File | Status | Audience | Covers |
|---|---|---|---|---|
| What is Super Speedy Chat? | **no local file** | **live** (id 1692050) ⚠ | Site owner | Product overview. **Lives only on the site — there is no `.kb/` source file.** Pull it down into `.kb/what-is-super-speedy-chat.md` so it has a source of truth. |
| Quick Start Guide | `.kb/quick-start-guide.md` | **live** (id 1692233) ⚠ **changed** | Site owner | Install/activate, verify the bubble, test message, reply from wp-admin, settings overview. **Live copy was edited 2026-06-03 and is newer than the local file** — it correctly dropped the false "Option A: install from the WordPress Plugin Directory" path (paid plugin, not on wp.org). Update the local file from live before any local edit. |
| Managing Conversations | `.kb/managing-conversations.md` | **live** (id 1692234) | Site owner | The Chats inbox: filters, assignment, detail view, statuses, saving canned responses. |
| Customizing the Chat Widget Appearance | `.kb/customizing-appearance.md` | **live** (id 1692235) ⚠ *re-file: Configuration → Getting Started* | Site owner | Customizer: header image/title, colours, position, trigger icon. |
| Email Notifications | `.kb/email-notifications.md` | **live** (id 1692236) ⚠ *re-file: Configuration → Getting Started* | Site owner | Admin + visitor notifications, the email-collection prompt, SMTP tips. |
| Behaviour and Session Settings | `.kb/behaviour-and-session-settings.md` | **draft** (id 1692468) ⚠ *blocker, see note* | Site owner | Timeout/action, login prompt, max length, poll intervals, sounds, require-login. **Do not publish as-is:** it documents **Require Login** as a working feature, but the `ssc_require_login` setting is registered yet never enforced anywhere in the code (verified 2026-06-05). Either implement enforcement first or rewrite that section (only the login *prompt* after N messages actually works). |
| Display Names | `.kb/display-names.md` | **draft** (id 1692469) | Site owner | Shared vs individual mode, per-user chat name, how it appears (incl. Discord). |
| Discord Integration Setup Guide | `.kb/discord-integration-setup.md` | **live** (id 1691994) | Site owner | Connecting Discord: bot token, channel ID, companion bot. |

### Features

| Article | File | Status | Audience | Covers |
|---|---|---|---|---|
| Canned Responses | `.kb/canned-responses.md` | **live** (id 1692237) ⚠ *re-file: Automation → Features* | Site owner | Saving/managing canned responses and how they feed the classifier. |
| LLM Auto-Reply Setup Guide | `.kb/llm-auto-reply-setup.md` | **live** (id 1692238) ⚠ *re-file: Automation → Features* | Site owner | OpenAI/Anthropic config, timeout action, how it works, cost, troubleshooting. |
| Discord Integration: Chat With Visitors From Discord | `.kb/discord-integration.md` | **draft** (id 1692470) | Site owner | Feature overview — what the Discord bridge does and why to use it. |

### Advanced

| Article | File | Status | Audience | Covers |
|---|---|---|---|---|
| Ultra Ajax Performance Guide | `.kb/ultra-ajax-performance.md` | **live** (id 1692239) ⚠ *re-file: Performance → Advanced* | Site owner / advanced | The mu-plugin: install/auto-update, verifying, polling, rate limits, troubleshooting. |
| Privacy and Data Handling | `.kb/privacy-and-data-handling.md` | **draft** (id 1692471) | Site owner | What's stored, the cookie, what leaves your server, retention, uninstall, GDPR checklist. |
| Troubleshooting and FAQ | `.kb/troubleshooting-and-faq.md` | **draft** (id 1692472) ⚠ *blocker, see note* | Site owner | Consolidated first-stop fixes + frequently asked questions. **Do not publish as-is:** the "can I make visitors log in?" answer claims you can require login on the Behaviour tab — that setting is unenforced in code (same blocker as Behaviour and Session Settings). |

### Developers

| Article | File | Status | Audience | Covers |
|---|---|---|---|---|
| Architecture Overview | `.kb/architecture-overview.md` | **draft** (id 1692473) | Developer | Component map, design decisions, per-component internals, message lifecycle. |
| Developer Guide: Hooks and Building Channel Add-ons | `.kb/developer-hooks-and-add-ons.md` | **draft** (id 1692474) | Developer | Lifecycle actions, registration hooks, inbound helpers, the add-on registry. |
| REST API Reference | `.kb/rest-api-reference.md` | **draft** (id 1692475) | Developer | `ssc/v1` endpoints, auth model, rate limits, request/response shapes. |
| Discord Companion Bot: Running It in Production | `.kb/discord-companion-bot-operations.md` | **draft** (id 1692476) | Developer / admin | Deploying the Node relay bot (PM2/systemd), updating, secret rotation, troubleshooting. |

## Publish checklist (state as of 2026-06-05)

- **8 live** and in sync: managing-conversations, customizing-appearance, email-notifications, canned-responses, llm-auto-reply-setup, ultra-ajax-performance, discord-integration-setup, what-is-super-speedy-chat (live-only — needs a local source file).
- **1 live but drifted**: quick-start-guide — live is newer; sync local from live.
- **9 drafts awaiting publish**: behaviour-and-session-settings ⚠, display-names, discord-integration, privacy-and-data-handling, troubleshooting-and-faq ⚠, architecture-overview, developer-hooks-and-add-ons, rest-api-reference, discord-companion-bot-operations. The two ⚠ ones must have their Require Login sections fixed (or the feature implemented) first.
- **0 local-only** articles — everything written has been uploaded at least as a draft.
- **Re-file on next touch**: the five live articles sitting in legacy Configuration/Automation/Performance terms.

## Related files in `.docs/` (specs, not KB articles)

`.docs/` holds specs and internal docs for us to review and implement — not website content. These stay there:

| File | What it is |
|---|---|
| `.docs/addons-system-plan.md` | Internal architecture/build plan for the add-on system. |
| `.docs/whatsapp-integration-plan.md` | Internal build plan for a future WhatsApp channel. |
| `.docs/maybe-in-future.md` | Backlog of deferred features. |
| `.docs/2026-06-03-security-review.md` | Internal security review + remediation log. |
| `.docs/landing-page.md` | Marketing landing-page copy. *Website content, not a spec — may belong in `.kb/` under the new workflow; left in `.docs/` pending a decision.* |
| `.docs/product-page-content.html` + `.docs/product-page-rebuild.md` | Product-page (WooCommerce id 1692164) source + rebuild notes. Fact-checked & republished 2026-06-05. |

## Pending / suggested articles (all **unwritten**)

Candidates worth adding, with their intended term. The first block came out of a customer/developer gap review (2026-06-05) — things a paying customer or integrating developer will look for and not find.

### Gaps found 2026-06-05 — customer perspective

| Suggested article | Term | Why |
|---|---|---|
| **Installing Your License, Activation & Updates** | Getting Started | **Biggest gap for a paid plugin.** Nothing documents the post-purchase flow: downloading the zip, entering the licence key on the Super Speedy Settings page, the "Recheck Licenses" button after a renewal/upgrade, what happens when a licence expires, and how plugin updates are delivered. Every buyer hits this before anything else in the KB. |
| **Team Roles & Permissions** | Getting Started | Replying, viewing the inbox and assignment all require the `manage_options` capability (admin-only) — verified in `class-ssc-rest.php`. Customers with support staff will ask "how do I let my agent reply without giving them full admin?" Document the current constraint honestly (and revisit if a chat-agent capability/role is added). |
| **Showing or Hiding the Chat on Specific Pages** | Advanced | There is no built-in per-page display setting. A very common ask ("hide it on checkout", "only show on the shop"). Document the supported approaches (conditional dequeue of the widget assets / `ssc_frontend_config` filter) with copy-paste snippets. |
| **Backing Up & Migrating Chat Data** | Advanced | Conversations live in custom `ssc_*` tables. Site-migration and backup tools that only handle core tables will silently drop chat history; uninstall drops the tables entirely (`uninstall.php`). Customers should know this *before* it bites them. |

### Gaps found 2026-06-05 — developer perspective

| Suggested article | Term | Why |
|---|---|---|
| **Widget Styling Reference (Custom CSS)** | Developers | The Customizer covers colours/position/icon only. Developers wanting deeper restyling need a reference of the widget's stable CSS classes (`ssc-*`) and guidance on safe overrides vs. things that may change between versions. |

### Previously identified (still valid)

| Suggested article | Term | Why |
|---|---|---|
| Spam & Abuse Prevention | Advanced | Pull together the honeypot, per-IP rate limits, login prompt, and LLM daily cap into one "harden your chat" guide. *(Drop "require-login" from the outline unless the setting gets enforced.)* |
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
