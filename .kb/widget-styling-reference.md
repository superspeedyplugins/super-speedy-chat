# Widget Styling Reference (Custom CSS)

The Customizer covers the common appearance options — colours, position, icon, header image, window title. When you want to go further, the widget is plain HTML and CSS you can target directly. This reference lists the CSS custom properties and stable selectors, and explains the one gotcha with overriding colours.

Put custom CSS in **Appearance > Customize > Additional CSS** (or your child theme's stylesheet).

## Start with the Customizer

Before writing CSS, check **Appearance > Customize > Super Speedy Chat** — primary colour, header background, visitor message colour, bubble position (bottom-right/bottom-left), trigger icon and header image are all settings. Use CSS for what the Customizer doesn't cover.

## CSS custom properties

The stylesheet defines its design tokens as variables on `:root`:

```css
:root {
    --ssc-primary: #0073aa;        /* brand colour: trigger bubble, send button   */
    --ssc-primary-dark: #005a87;   /* hover shade of the brand colour             */
    --ssc-bg: #ffffff;             /* widget background                           */
    --ssc-text: #333333;           /* main text                                   */
    --ssc-text-light: #666666;     /* secondary text (timestamps etc.)            */
    --ssc-border: #e0e0e0;         /* borders and dividers                        */
    --ssc-header-bg: var(--ssc-primary);  /* header bar background                */
    --ssc-visitor-bg: var(--ssc-primary); /* visitor message bubble               */
    --ssc-visitor-text: #ffffff;   /* visitor message text                        */
    --ssc-admin-bg: #f0f0f0;       /* admin/bot message bubble                    */
    --ssc-admin-text: #333333;     /* admin/bot message text                      */
    --ssc-system-bg: #fff3cd;      /* system message (welcome etc.) background    */
    --ssc-system-text: #856404;    /* system message text                         */
    --ssc-bubble-size: 60px;       /* trigger bubble diameter                     */
    --ssc-widget-width: 370px;     /* chat window width (desktop)                 */
    --ssc-widget-height: 500px;    /* chat window height (desktop)                */
}
```

Example — a bigger bubble and a wider window:

```css
:root {
    --ssc-bubble-size: 72px;
    --ssc-widget-width: 420px;
}
```

## The colour gotcha

If you've set **Primary Color**, **Header Background** or **Visitor Message Color** in the Customizer, the widget's JavaScript applies them as inline custom properties on the `<html>` element — and inline styles beat anything you set on `:root` in a stylesheet.

So, for those three colours: **change them in the Customizer**, that's what it's for. Only override `--ssc-primary`, `--ssc-header-bg` or `--ssc-visitor-bg` in CSS if you've left the Customizer colour settings empty. All the other variables (`--ssc-admin-bg`, `--ssc-system-bg`, sizes, etc.) are never set by JavaScript and can be overridden freely.

## Selector reference

| Selector | What it is |
|---|---|
| `#ssc-wrapper` | Outer container anchored to the viewport corner. Gets the class `ssc-position-bottom-left` when the bubble is positioned bottom-left. |
| `#ssc-trigger` | The round trigger bubble. `.ssc-open` is added while the chat is open. |
| `#ssc-unread-badge` | Unread-count badge on the trigger (`.ssc-has-unread` when > 0). |
| `#ssc-widget` | The chat window. `.ssc-visible` while open. |
| `.ssc-header` | Header bar. Contains `.ssc-header-image`, `.ssc-header-title`, `.ssc-header-close`. |
| `#ssc-messages` | Scrollable message list. |
| `.ssc-msg` | A message. Combined with one of `.ssc-msg-visitor`, `.ssc-msg-admin`, `.ssc-msg-bot`, `.ssc-msg-system`. |
| `.ssc-msg-sender` / `.ssc-msg-text` / `.ssc-msg-time` | Sender name, message text and timestamp inside a message. |
| `.ssc-input-area` | The message box row: contains the `#ssc-input` textarea and `.ssc-send-btn`. |
| `.ssc-email-prompt` | The "leave your email" prompt (`.ssc-visible` when shown). |
| `.ssc-login-prompt` | The soft "log in to save your history" prompt. |
| `.ssc-login-required` | The log in / create account panel shown instead of the input area when Require Login is on. |
| `.ssc-sponsor-link` | The "Powered by" footer link. |

Example — square message bubbles and a subtler footer:

```css
.ssc-msg-visitor,
.ssc-msg-admin,
.ssc-msg-bot {
    border-radius: 4px;
}

.ssc-sponsor-link {
    opacity: 0.5;
}
```

## Mobile

Below 480px wide, the widget switches to fullscreen and adapts to the on-screen keyboard. If you adjust widget dimensions, test on a phone — the desktop `--ssc-widget-width`/`--ssc-widget-height` variables don't constrain the fullscreen mobile layout.

## A note on stability

The variables above are the most stable styling surface — prefer them over deep selector overrides. Element IDs and the classes in the table are part of the widget's markup and rarely change, but very specific selectors (nth-childs, structural assumptions) may break in future versions. Keep overrides shallow.
