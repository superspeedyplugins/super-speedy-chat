# Super Speedy Chat — KB Contents & Coverage Map

An index of every knowledge-base guide for Super Speedy Chat, organised by the KB category **term** it belongs under, with the publication status of each article, plus a list of suggested articles still to write.

**Status audit last run: 2026-06-05** (local `.kb/` files were content-diffed against the live site via the REST API). Same day: the 5 gap articles were written and uploaded as drafts, the Require Login feature was implemented in v1.09 (clearing the two publish blockers), quick-start was reconciled both ways, and what-is got a local source file.

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
| What is Super Speedy Chat? | `.kb/what-is-super-speedy-chat.md` | **live** (id 1692050) | Site owner | Product overview: key features, who it's for, how it works. Local source created 2026-06-05 from the live copy. |
| Quick Start Guide | `.kb/quick-start-guide.md` | **live** (id 1692233) | Site owner | Install/activate (ZIP upload — paid plugin, not on wp.org), verify the bubble, test message, reply from wp-admin, settings overview. Reconciled 2026-06-05: local synced to live, and live got back its lost intro + "1. Install and Activate" heading plus a fixed further-reading link (was a broken relative `.md` href). |
| Installing Your License, Activation and Updates | `.kb/installing-your-license-activation-and-updates.md` | **live** (id 1692590) | Site owner | Entering the key on the Super Speedy settings page, Recheck Licenses, license statuses (active/expired/exceeded), how updates are delivered, expiry consequences. |
| Team Roles and Permissions | `.kb/team-roles-and-permissions.md` | **live** (id 1692591) | Site owner | wp-admin chat needs `manage_options` (Administrator); Discord as the no-WordPress-account route for support staff; display names; no dedicated chat-agent role yet. |
| Managing Conversations | `.kb/managing-conversations.md` | **live** (id 1692234) | Site owner | The Chats inbox: filters, assignment, detail view, statuses, saving canned responses. |
| Customizing the Chat Widget Appearance | `.kb/customizing-appearance.md` | **live** (id 1692235) ⚠ *re-file: Configuration → Getting Started* | Site owner | Customizer: header image/title, colours, position, trigger icon. |
| Email Notifications | `.kb/email-notifications.md` | **live** (id 1692236) ⚠ *re-file: Configuration → Getting Started* | Site owner | Admin + visitor notifications, the email-collection prompt, SMTP tips. |
| Behaviour and Session Settings | `.kb/behaviour-and-session-settings.md` | **live** (id 1692468) | Site owner | Timeout/action, login prompt, max length, poll intervals, sounds, require-login. *Former publish blocker cleared 2026-06-05: Require Login enforcement shipped in v1.09 and the article now documents the real behaviour (401s, login invitation in the widget, REST fall-through instead of Ultra Ajax).* |
| Display Names | `.kb/display-names.md` | **live** (id 1692469) | Site owner | Shared vs individual mode, per-user chat name, how it appears (incl. Discord). |
| Discord Integration Setup Guide | `.kb/discord-integration-setup.md` | **live** (id 1691994) | Site owner | Connecting Discord: bot token, channel ID, companion bot. |

### Features

| Article | File | Status | Audience | Covers |
|---|---|---|---|---|
| Canned Responses | `.kb/canned-responses.md` | **live** (id 1692237) ⚠ *re-file: Automation → Features* | Site owner | Saving/managing canned responses and how they feed the classifier. |
| LLM Auto-Reply Setup Guide | `.kb/llm-auto-reply-setup.md` | **live** (id 1692238) ⚠ *re-file: Automation → Features* | Site owner | OpenAI/Anthropic config, timeout action, how it works, cost, troubleshooting. |
| Discord Integration: Chat With Visitors From Discord | `.kb/discord-integration.md` | **live** (id 1692470) | Site owner | Feature overview — what the Discord bridge does and why to use it. |

### Advanced

| Article | File | Status | Audience | Covers |
|---|---|---|---|---|
| Ultra Ajax Performance Guide | `.kb/ultra-ajax-performance.md` | **live** (id 1692239) ⚠ *re-file: Performance → Advanced* | Site owner / advanced | The mu-plugin: install/auto-update, verifying, polling, rate limits, troubleshooting. |
| Privacy and Data Handling | `.kb/privacy-and-data-handling.md` | **live** (id 1692471) | Site owner | What's stored, the cookie, what leaves your server, retention, uninstall, GDPR checklist. |
| Troubleshooting and FAQ | `.kb/troubleshooting-and-faq.md` | **live** (id 1692472) | Site owner | Consolidated first-stop fixes + frequently asked questions. *Former publish blocker cleared 2026-06-05: Require Login is now enforced (v1.09) and the FAQ answer matches the real behaviour.* |
| Showing or Hiding the Chat on Specific Pages | `.kb/showing-or-hiding-the-chat-on-specific-pages.md` | **live** (id 1692592) | Site owner / advanced | Conditional dequeue snippets (checkout, by page, show-only-on), CSS-only fallback, global Enable Chat toggle. |
| Backing Up and Migrating Chat Data | `.kb/backing-up-and-migrating-chat-data.md` | **live** (id 1692593) | Site owner / advanced | The five `ssc_*` tables + options, what backup/migration tools do and don't carry, delete-plugin-drops-tables warning, targeted mysqldump, migration steps. |

### Developers

| Article | File | Status | Audience | Covers |
|---|---|---|---|---|
| Architecture Overview | `.kb/architecture-overview.md` | **live** (id 1692473) | Developer | Component map, design decisions, per-component internals, message lifecycle. |
| Developer Guide: Hooks and Building Channel Add-ons | `.kb/developer-hooks-and-add-ons.md` | **live** (id 1692474) | Developer | Lifecycle actions, registration hooks, inbound helpers, the add-on registry. |
| REST API Reference | `.kb/rest-api-reference.md` | **live** (id 1692475) | Developer | `ssc/v1` endpoints, auth model, rate limits, request/response shapes. |
| Discord Companion Bot: Running It in Production | `.kb/discord-companion-bot-operations.md` | **live** (id 1692476) | Developer / admin | Deploying the Node relay bot (PM2/systemd), updating, secret rotation, troubleshooting. |
| Widget Styling Reference (Custom CSS) | `.kb/widget-styling-reference.md` | **live** (id 1692594) | Developer | CSS custom properties, stable selector table, the inline-style colour gotcha (Customizer colours beat `:root` overrides), mobile note. |

## Publish checklist (state as of 2026-06-05, end of day)

- **All 23 articles are live** and in sync with their local source files. The remaining 14 drafts were published on 2026-06-05 after v1.09 (Require Login enforcement) was committed, so every article describes shipped behaviour.
- **0 drafts, 0 local-only, 0 unwritten gaps** from the customer/developer review.
- **Re-file on next touch**: the five live articles sitting in legacy Configuration/Automation/Performance terms (Customizing Appearance, Email Notifications → Getting Started; Canned Responses, LLM Auto-Reply → Features; Ultra Ajax Performance → Advanced).

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

Candidates worth adding, with their intended term.

*The five gap articles identified in the 2026-06-05 customer/developer review (License Activation & Updates, Team Roles & Permissions, Show/Hide on Specific Pages, Backing Up & Migrating Chat Data, Widget Styling Reference) have been written and uploaded as drafts — see the term tables above.*

### Previously identified (still valid)

| Suggested article | Term | Why |
|---|---|---|
| Spam & Abuse Prevention | Advanced | Pull together the honeypot, per-IP rate limits, login prompt, Require Login (enforced as of v1.09), and LLM daily cap into one "harden your chat" guide. |
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
