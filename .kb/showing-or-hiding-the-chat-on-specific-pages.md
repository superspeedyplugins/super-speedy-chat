# Showing or Hiding the Chat on Specific Pages

The chat bubble appears on every front-end page by default. There's no per-page checkbox in the settings (yet), but hiding it on specific pages — or showing it only on specific pages — takes a few lines of code. This guide gives you copy-paste snippets for the common cases.

## How it works

Super Speedy Chat enqueues its widget assets (`ssc-chat-bubble` script and style) on the standard `wp_enqueue_scripts` hook. Dequeue them conditionally and the bubble never loads on that page — no script, no style, no chat requests. This is the clean approach: it removes the widget entirely rather than just hiding it.

Put the snippets below in your child theme's `functions.php` or a small custom plugin (a code-snippets plugin works too).

## Hide on specific pages

**Hide on the WooCommerce checkout** (the classic "don't distract them while they're paying"):

```php
add_action( 'wp_enqueue_scripts', function () {
    if ( function_exists( 'is_checkout' ) && is_checkout() ) {
        wp_dequeue_script( 'ssc-chat-bubble' );
        wp_dequeue_style( 'ssc-chat-bubble' );
    }
}, 20 );
```

**Hide on specific pages by slug or ID:**

```php
add_action( 'wp_enqueue_scripts', function () {
    if ( is_page( array( 'privacy-policy', 'terms', 42 ) ) ) {
        wp_dequeue_script( 'ssc-chat-bubble' );
        wp_dequeue_style( 'ssc-chat-bubble' );
    }
}, 20 );
```

**Hide everywhere except…** — flip the condition to show the chat only where you want it:

```php
add_action( 'wp_enqueue_scripts', function () {
    // Only show chat on the shop, product pages, and the contact page.
    $show = ( function_exists( 'is_shop' ) && is_shop() )
         || ( function_exists( 'is_product' ) && is_product() )
         || is_page( 'contact' );

    if ( ! $show ) {
        wp_dequeue_script( 'ssc-chat-bubble' );
        wp_dequeue_style( 'ssc-chat-bubble' );
    }
}, 20 );
```

The `20` priority matters: the plugin enqueues at the default priority `10`, so your dequeue must run after it.

You can use any WordPress conditional tag in these snippets — `is_front_page()`, `is_singular( 'post' )`, `is_user_logged_in()`, and so on.

## CSS-only alternative

If you can't add PHP, you can hide the bubble with CSS (Appearance > Customize > Additional CSS):

```css
/* Hide chat on page ID 42 */
.page-id-42 #ssc-wrapper {
    display: none;
}
```

Note the difference: with CSS the widget still loads and polls in the background — it's just invisible. Prefer the dequeue snippets when you can; use CSS only for quick cosmetic cases.

## Turning chat off everywhere

You don't need code for that — untick **Enable Chat** on the **General** tab under **Super Speedy Plugins > Super Speedy Chat**.

## Related

- Behaviour and Session Settings — polling intervals and widget behaviour.
- Widget Styling Reference — selectors and CSS variables for customising the bubble's look.
