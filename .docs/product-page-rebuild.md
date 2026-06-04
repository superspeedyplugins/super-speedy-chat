# Product Page Rebuild — Super Speedy Chat

A complete rebuild of the **product** sales page (`/product/super-speedy-chat/`, product ID **1692164**) — distinct from the advert/email-capture landing page (`/get-super-speedy-chat/`, page 1692161) edited earlier.

> **Status: LIVE (published 2026-06-03).** After Dave gave `daveclaw` the `shop_manager` role, the rebuild was pushed to `/product/super-speedy-chat/` (content + improved short description) and verified on the rendered page. `product-page-content.html` holds the published markup.
>
> **kses note:** `shop_manager` lacks `unfiltered_html`, so the first push had its inline `<style>`/`<svg>` grid stripped. The feature grid was rebuilt with **native Gutenberg blocks** (`wp:columns` → `wp:group` cards, block inline styles, emoji icons) which survive kses. Use that approach for products; inline SVG/CSS only survives on `page`/`kb` content (editor role).

## Improved short description (product excerpt)

> Real-time live chat for WordPress — without the SaaS price tag, the page-speed hit, or your customer data on someone else's servers. A lightweight bubble lets visitors message you instantly; reply from wp-admin or straight from Discord, with optional AI auto-reply and email fallback so you never miss a lead. Fully self-hosted, blazing fast, and free — no per-seat fees, no monthly bill, no bloat.

## Conventions applied (per request)

- **9 feature panels** as a 3×3 grid (responsive 3 → 2 → 1), each with a **centered** icon, title and one-sentence description. Icons are inline Lucide SVGs in a tinted badge; cards have a subtle border, radius, and a hover lift.
- **AI auto-reply swapped in** for "Login prompts" (the weakest panel), matching the landing page.
- **No `<hr>` / horizontal separators anywhere** on the page.
- **All section H2s are left-aligned** (WordPress default), not centered.
- Self-contained: the grid ships as one `wp:html` block with scoped CSS + inline SVGs, so it needs no theme changes.

## Page structure (in order)

1. **Intro** — two benefit-led paragraphs: the "you pay three times for SaaS chat (bill, speed, data)" hook → the WordPress-native alternative.
2. **Why site owners switch** — the three differentiators as pain-answers: won't slow your site, your data stays yours, won't cost a fortune.
3. **Key features** — the 9 centered panels.
4. **Never miss a conversation — even when you're offline** — addresses the #1 doubt (coverage when away): email fallback, AI auto-reply, reply-from-Discord-on-mobile.
5. **Set up in minutes, no developer required** — kills the setup-friction doubt (install → customise → chat).
6. **How it works** — the mu-plugin speed story, local storage, the Discord bot — accessible but concrete.
7. **Who it's for** — WooCommerce/high-traffic owners, Discord teams, devs, the chat-plugin-disappointed.
8. **Free, with optional add-ons when you grow** — answers "what's the catch / will you gouge me later" honestly.
9. **Requirements** — compatibility reassurance (theme/builder/caching/WooCommerce; Discord optional Node).
10. **Frequently asked questions** — see list below.
11. **Ready to talk to your visitors?** — closing nudge + Quick Start link (no `<hr>`; the WooCommerce add-to-cart UI sits above this content).
12. **Further reading** — KB links updated to the current `/kb/super-speedy-chat/getting-started/…` URLs.

## Customer doubts the page now answers

Worked through from a prospective buyer's perspective; each doubt is met somewhere on the page and/or in the FAQ:

- *Will it slow my site?* → "Why site owners switch" + FAQ.
- *Free — what's the catch / will it stay free?* → "Free, with optional add-ons" + FAQ.
- *What if I'm not online when someone messages?* → dedicated "Never miss a conversation" section + FAQ.
- *Is it hard to set up / do I need a dev?* → "Set up in minutes" + FAQ.
- *Will it work with my theme / builder / caching / WooCommerce?* → Requirements + FAQ.
- *Where does my data go / GDPR?* → "Why site owners switch" + FAQ.
- *Can my team use it?* → FAQ (assignment, shared/individual names).
- *Discord — does it really work both ways, and is it hard?* → "How it works" + FAQ.
- *Mobile? Spam/bots? AI cost? Support?* → FAQ.

### FAQ questions included

1. Will it slow down my website?
2. Is it really free? What's the catch?
3. What happens when a visitor messages and I'm not online?
4. Do I need to be a developer to set it up?
5. Will it work with my theme, page builder and caching plugin?
6. Where is my chat data stored? Is it GDPR-friendly?
7. Can my whole team use it?
8. How does the Discord integration work, and is it hard to set up?
9. Does the chat work on mobile?
10. How do you stop spam and bots?
11. Does the AI auto-reply cost anything?
12. What if I get stuck?

## Publishing (pick one)

The full Gutenberg markup for the long description is in **`product-page-content.html`** (same folder). The short description is above.

- **Option A — fix the credential (recommended, fastest).** Confirm `daveclaw` has the `shop_manager` (or admin) role and generate a fresh Application Password. Then I can `POST /wp/v2/product/1692164` with `content` (the markup) and `excerpt` (the short description) in one call.
- **Option B — WooCommerce REST keys.** Provide a WooCommerce consumer key/secret (WooCommerce → Settings → Advanced → REST API, Read/Write). I'll `PUT /wc/v3/products/1692164` with `description` (= long content) and `short_description` (= excerpt).
- **Option C — paste manually.** In the product editor, switch the description to the Code Editor and paste `product-page-content.html`; paste the short description into the Product short description box. (The `daveclaw` user has `unfiltered_html`, so the inline `<style>`/SVG survive — confirmed on the landing page.)

## Related follow-up

The earlier landing page (`/get-super-speedy-chat/`, 1692161) still uses `<hr>` separators between sections and centered section H2s (both pre-existing on that page). If you'd like it brought in line with these conventions (drop the `<hr>`s, left-align the H2s), say the word and I'll update it — that page *is* editable with the current credential.
