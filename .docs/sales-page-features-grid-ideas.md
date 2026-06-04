# Sales Page — Features Section Redesign Ideas

Ideas for turning the **Key features** section on the Super Speedy Chat sales page (`/get-super-speedy-chat/`, page ID 1692161) into a more visual **3×3 grid of icon panels**.

## What's there now

The section already contains exactly **9 features** laid out in three `wp:columns` rows (so the 3×3 structure exists), but it reads as plain text, not panels:

- The "icon" is an **emoji inside the H3 heading** (⚡ 💬 🔒 …), not a distinct visual element.
- Each description is **2–4 sentences** — heavier than a scannable card.
- There's **no panel styling** — no card background, border, spacing rhythm, icon badge, or hover state. It's three bare columns of running text.

**Goal:** keep the 9 features and the 3×3 grid, but render each as a self-contained **panel/card** with (1) a proper icon, (2) a short heading, (3) a single-sentence description — and make the whole section feel designed.

## Recommended panel content (icon + short heading + one sentence)

Tightened to one scannable sentence each. Icon column gives a [Lucide](https://lucide.dev) icon name (MIT-licensed, single-colour, crisp at any size) with the current emoji as a fallback.

| # | Icon (Lucide / emoji) | Heading | One-sentence description |
|---|---|---|---|
| 1 | `zap` / ⚡ | Lightning-fast AJAX | Chat requests skip the full WordPress load, so messages send and arrive in milliseconds. |
| 2 | `messages-square` / 💬 | Two-way Discord | Answer visitors straight from Discord threads — replies sync back to the chat instantly. |
| 3 | `shield-check` / 🔒 | 100% self-hosted | Every conversation lives in your own database — no third-party servers, no data leaving your site. |
| 4 | `clipboard-list` / 📋 | Canned responses | Save your best replies once and reuse them in a click — optionally auto-matched by AI. |
| 5 | `mail` / 📧 | Email fallback | Offline messages land in your inbox and your replies email the visitor back, so nothing slips. |
| 6 | `palette` / 🎨 | Customisable look | Colours, position, icon and welcome text — all live-previewed in the WordPress Customizer. |
| 7 | `inbox` / 👤 | Team inbox | Filter, search, assign and close conversations across your whole team from one dashboard. |
| 8 | `bell` / 🔔 | Sound alerts | Audible notifications mean you never miss a waiting visitor. |
| 9 | `log-in` / 🔐 | Login prompts | Gently invite visitors to sign in so their chat history follows their account. |

> **Content tweak worth considering:** "Sound alerts" and "Login prompts" are the two weakest selling points. **LLM auto-reply** is a stronger differentiator and is currently *not* surfaced as its own panel. Option: promote it to its own card (icon `sparkles` / 🤖 — "AI auto-reply — When you're away, an AI classifier answers common questions from your canned responses.") and fold "Login prompts" into the Team inbox or "Conversation management" card. Keeps it at 9.

## Visual treatment — two directions

### Style A — Clean cards (recommended; on-brand, low-risk)
- **Card:** white background, 1px `#e6e8eb` border, 14px radius, 24px padding, subtle shadow (`0 1px 3px rgba(0,0,0,.06)`).
- **Icon:** 44–48px rounded-square badge, brand colour at ~10% opacity background, brand-coloured glyph centred inside.
- **Heading:** 17–18px, semibold, 12px below the icon.
- **Description:** 14px, muted grey (`#5a6472`), 1.5 line-height.
- **Alignment:** left-aligned (best for reading short sentences).
- **Hover:** lift `translateY(-3px)`, shadow deepens, border switches to the brand colour. Smooth `transition: .15s ease`.
- **Equal height:** all cards stretch to the tallest in the row.

### Style B — Bold centred badges (punchier, more "designed")
- Centre-aligned content; larger **circular** icon badge (56px) filled with a brand gradient and a white glyph.
- Generous whitespace, heading centred under the badge, one-line description below.
- Optional: a faint number or top accent bar per card.
- Good if you want the section to feel like a hero moment; slightly more work to keep the one-liners from wrapping awkwardly.

### Shared layout / responsive
- **Desktop (>900px):** 3 columns. **Tablet (≤900px):** 2 columns. **Mobile (≤600px):** 1 column.
- Consistent gap (20–24px) horizontally and vertically.
- Add a short intro line under the **Key features** heading, e.g. *"Everything you need to chat with visitors — and nothing you'll pay monthly for."*
- **Accessibility:** icons are decorative → `aria-hidden="true"`; keep description contrast ≥ 4.5:1; don't rely on the icon alone to convey meaning (the heading already does).

## Implementation notes (Gutenberg)

The page already uses `wp:columns`, so the smallest change is to wrap each column's contents in a `wp:group` card and add CSS. Example for one Style-A panel (inline SVG icon so there's no icon-font dependency):

```html
<!-- wp:group {"className":"ssc-feature-card"} -->
<div class="wp-block-group ssc-feature-card">
  <span class="ssc-feature-icon" aria-hidden="true">
    <!-- paste the Lucide "zap" SVG here -->
  </span>
  <!-- wp:heading {"level":3} -->
  <h3 class="wp-block-heading">Lightning-fast AJAX</h3>
  <!-- /wp:heading -->
  <!-- wp:paragraph -->
  <p>Chat requests skip the full WordPress load, so messages send and arrive in milliseconds.</p>
  <!-- /wp:paragraph -->
</div>
<!-- /wp:group -->
```

CSS to drop in the theme's **Additional CSS** (or a Custom HTML block on the page):

```css
.wp-block-columns:has(.ssc-feature-card){ align-items: stretch; }
.ssc-feature-card{
  background:#fff; border:1px solid #e6e8eb; border-radius:14px;
  padding:24px; height:100%; transition:.15s ease;
}
.ssc-feature-card:hover{
  transform:translateY(-3px);
  box-shadow:0 8px 24px rgba(0,0,0,.08);
  border-color:var(--ssc-brand,#0073aa);
}
.ssc-feature-icon{
  display:inline-flex; align-items:center; justify-content:center;
  width:46px; height:46px; border-radius:12px; margin-bottom:14px;
  background:color-mix(in srgb, var(--ssc-brand,#0073aa) 12%, transparent);
  color:var(--ssc-brand,#0073aa);
}
.ssc-feature-icon svg{ width:24px; height:24px; }
.ssc-feature-card h3{ font-size:1.05rem; margin:0 0 8px; }
.ssc-feature-card p{ font-size:.9rem; color:#5a6472; margin:0; line-height:1.5; }
@media (max-width:600px){ .ssc-feature-card{ padding:20px; } }
```

Alternatives if you'd rather not hand-edit blocks:
- **Reusable block pattern:** build one card, save it as a pattern, drop it 9× — fastest to maintain.
- **Theme icon/"Icon List" block** if the active theme ships one (less control over the badge styling).
- **Stock SVG set:** Lucide or [Heroicons](https://heroicons.com) (both MIT) keep all nine icons visually consistent; avoid mixing emoji and SVG.

## Suggested next step

If you like Style A and the recommended 9 (with or without the LLM-auto-reply swap), I can generate the full Gutenberg markup for all nine cards + the CSS and update the page (as a draft revision or directly — your call).
