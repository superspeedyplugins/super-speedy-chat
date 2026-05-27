# Add-ons System — Architecture & Developer Guide

Status: **proposal / for review**. Not yet implemented.

## 1. Goal

Make Super Speedy Chat **extensible by separate plugins**, so we can sell the core (with Discord bundled) and then sell WhatsApp / Slack / Telegram / Microsoft Teams / etc. as paid add-on plugins. Each add-on must be able to:

1. Push outbound messages to its channel when a visitor/admin sends a message in WP.
2. Receive inbound messages from its channel and turn them into WP messages.
3. Add its own tab + settings to the chat admin page.
4. Add badges, columns and panels to the admin conversation list / detail view.
5. Add buttons/panels to the front-end chat bubble.
6. Have its own licence (using the existing `super-speedy-settings` submodule).

…without modifying core code after the extension API ships.

### Non-goals (v1)
- Multi-channel-per-conversation (e.g. same conversation routed to both Discord and Slack simultaneously) — supported by hooks, but no core UI for it
- Hot-swap of channel for an existing conversation
- Add-on-to-add-on dependencies (only add-on → core)

---

## 2. Commercial model

| Plugin | Channel | Bundle | Licence |
|---|---|---|---|
| `super-speedy-chat` | Website bubble + Discord | Core (paid base plugin) | Existing super-speedy-settings licence |
| `super-speedy-chat-whatsapp` | WhatsApp | Add-on | Own licence, same auth server |
| `super-speedy-chat-slack` | Slack | Add-on | Own |
| `super-speedy-chat-telegram` | Telegram | Add-on | Own |
| `super-speedy-chat-teams` | Microsoft Teams | Add-on | Own |

Each add-on is a normal WordPress plugin with `Requires Plugins: super-speedy-chat` in its header (WP 6.5+ feature — auto-deactivates the add-on if core is missing). It initialises its own `SuperSpeedySettings_1_0::init()` block, so existing licensing infrastructure just works — no core change needed for billing.

Discord stays in core because it's the proof-of-concept channel and the add-on API is designed to expose exactly the surface Discord currently uses. Once that refactor lands, Discord becomes the **reference implementation** — if it can be rebuilt as an add-on cleanly, the API is sufficient.

---

## 3. Architecture overview

```
                       ┌────────────────────────────┐
                       │      Super Speedy Chat     │
                       │           (core)           │
                       │                            │
                       │  - DB: conversations,      │
                       │    participants, messages  │
                       │  - REST: poll/send/reply   │
                       │  - Admin UI                │
                       │  - Bubble JS               │
                       │  - Hooks API ◄──────────┐  │
                       └────────────┬────────────┼──┘
                                    │            │
              ┌─────────────────────┼────────────┼────────────────────┐
              │                     │            │                    │
       PHP do_action /        wp.hooks.do      apply_filters     SSC_Chat::*
       apply_filters         (JS, both         (tabs, columns,    public API
       (10 hooks)            bubble + admin)    channels, etc.)
              │                     │            │                    │
              ▼                     ▼            ▼                    ▼
   ┌──────────────────┐  ┌──────────────────┐  ┌──────────────────────────┐
   │  Discord (core)  │  │ WhatsApp add-on  │  │ Slack add-on, Telegram   │
   │  - reference impl│  │ - sample build   │  │ add-on, ...              │
   └──────────────────┘  └──────────────────┘  └──────────────────────────┘
```

The core fires hooks; each add-on listens for the ones it cares about. The core never has any knowledge of which add-ons exist.

---

## 4. Required core changes

### 4.1 New PHP action/filter hooks

All hooks are prefixed `ssc_`. **All actions also receive the full `$conversation` object** so add-ons don't have to re-fetch from DB on every event.

#### Message lifecycle (actions)

Replaces the direct Discord calls currently in `class-ssc-chat.php:78-81` and `:147-150`.

```php
do_action( 'ssc_visitor_message_sent', $message_id, $conversation, $message_text, $participant );
do_action( 'ssc_admin_reply_sent',     $message_id, $conversation, $message_text, $admin_user_id );
do_action( 'ssc_bot_message_sent',     $message_id, $conversation, $message_text, $message_type );
do_action( 'ssc_conversation_created', $conversation );
do_action( 'ssc_conversation_status_changed', $conversation_id, $new_status, $old_status );
do_action( 'ssc_conversation_closed',  $conversation );
do_action( 'ssc_conversation_assigned', $conversation_id, $new_assignee_id, $old_assignee_id );
```

#### Channel awareness (filters)

```php
// Registry — add-ons append: ['id' => 'whatsapp', 'label' => 'WhatsApp', 'icon' => 'dashicons-whatsapp']
apply_filters( 'ssc_channels', $channels );

// Override channel detection for a given conversation
// (core default: returns 'website', or 'discord' / 'whatsapp' / etc. if an add-on table row exists)
apply_filters( 'ssc_conversation_channel', $channel_id = 'website', $conversation_id );
```

#### Settings UI (filters)

```php
// Tab list — add-ons append: ['id' => 'whatsapp', 'label' => 'WhatsApp', 'order' => 50]
apply_filters( 'ssc_settings_tabs', $tabs );

// Allow add-ons to register their settings section/fields after registry is built
do_action( 'ssc_register_settings' );
```

#### Admin UI (filters/actions)

```php
// Conversation list columns
// Add-ons append: ['id' => 'channel', 'label' => 'Channel', 'order' => 25]
apply_filters( 'ssc_conversation_list_columns', $columns );

// Conversation detail sidebar panels — fires inside the sidebar wrapper
do_action( 'ssc_conversation_sidebar', $conversation );

// Conversation detail reply UI footer — for e.g. "Send WhatsApp template" buttons
do_action( 'ssc_conversation_reply_footer', $conversation );
```

#### REST (action)

```php
// Fires inside rest_api_init *after* core routes are registered, so add-ons
// can register their own /ssc/v1/{channel}/incoming endpoints
do_action( 'ssc_register_rest_routes', $rest_instance );
```

#### Front-end localised data (filter)

```php
// Lets add-ons add their own config blob to the front-end ssc_config object
apply_filters( 'ssc_frontend_config', $config );
// Lets add-ons enqueue their own scripts after the core bubble script
do_action( 'ssc_enqueue_frontend', $ultra_ajax_active );
```

### 4.2 New helper methods on `SSC_Chat`

These give add-ons a one-call way to create messages from outside the website bubble, without duplicating participant/conversation logic.

```php
// Create or fetch a conversation for an external visitor identified by channel + external_id
// (e.g. WhatsApp phone, Telegram user_id). Returns SSC_Conversation object.
SSC_Chat::get_or_create_external_conversation( array(
    'channel'      => 'whatsapp',
    'external_id'  => '+447123456789',
    'display_name' => 'Jane Doe',
    'metadata'     => array( 'wa_id' => '447123456789' ),
) );

// Append an inbound message from an external channel. Handles participant creation,
// message insert, conversation status update, and fires the inbound lifecycle hooks.
SSC_Chat::external_inbound( array(
    'conversation_id' => 123,
    'channel'         => 'whatsapp',
    'author_name'     => 'Jane Doe',
    'author_type'     => 'visitor',  // or 'admin' for Mode-B style relay
    'message'         => 'Hello',
    'external_msg_id' => 'wamid.xxx',  // optional, for dedup
) );
```

These wrap what `SSC_Discord::handle_incoming()` does today. The Discord refactor replaces its bespoke participant/message code with calls to these helpers.

### 4.3 DB schema changes

Add a **`channel` column** to `ssc_conversations` with a default of `'website'`. This makes "which channel does this conversation belong to" a single column lookup instead of joining N add-on tables.

```sql
ALTER TABLE {prefix}ssc_conversations
  ADD COLUMN channel VARCHAR(32) NOT NULL DEFAULT 'website' AFTER status,
  ADD INDEX idx_channel (channel);
```

Add-on tables (`ssc_discord_threads`, `ssc_whatsapp_threads`, etc.) remain owned by each add-on — they're created in the add-on's `register_activation_hook` and never touched by core. They store **only the channel-specific external IDs** (Discord thread ID, WhatsApp phone, Slack channel, etc.), keyed by `conversation_id`.

Bump `SSC_DB::DB_VERSION` to trigger the column addition via `dbDelta`.

### 4.4 JS hook system

WP already ships `@wordpress/hooks` as the `wp-hooks` script. Use it on both the admin side and the bubble side, with a `ssc.` namespace prefix so we don't clash with anything else.

**Admin side** (already loads wp-hooks easily): add `'wp-hooks'` to the `wp_enqueue_script` deps array in `SSC_Admin::enqueue_scripts()`. Add-ons call:

```js
wp.hooks.addFilter( 'ssc.admin.conversationColumns', 'whatsapp', cols => {
    cols.push({ id: 'channel', label: 'Channel', order: 25 });
    return cols;
});

wp.hooks.addAction( 'ssc.admin.conversationRowRendered', 'whatsapp', ($row, conv) => {
    // mutate the rendered row, e.g. add a channel badge
});
```

The events the admin JS must fire:
- `ssc.admin.conversationsLoaded` — when the conversation list refreshes
- `ssc.admin.conversationRowRendered` — per row, after DOM insert
- `ssc.admin.conversationOpened` — when a detail view is shown
- `ssc.admin.replyBeforeSend` — filter on the outgoing reply (lets add-ons cancel or transform)

**Front-end bubble**: chat-bubble.js currently does not depend on wp-hooks (it's vanilla JS, no jQuery, deliberately small). Two options:

- **(a) Add `wp-hooks` as a dep** — adds ~3KB gzipped to the bubble. Consistent API with admin.
- **(b) Bundle a tiny ~40-line hooks lib** in chat-bubble.js itself, exposed as `window.ssc.hooks`.

Recommendation: **(b)** to keep the bubble lean. Identical API surface (`addAction`/`doAction`/`addFilter`/`applyFilters`) so add-on code looks the same in both contexts.

Front-end events to fire:
- `ssc.bubble.opened` / `ssc.bubble.closed`
- `ssc.bubble.messageSent` (action, after POST resolves)
- `ssc.bubble.messageReceived` (action, after poll returns new messages)
- `ssc.bubble.actions` (filter on the bubble's action-bar buttons array — lets add-ons add e.g. a "Chat on WhatsApp" button)
- `ssc.bubble.composerPlaceholder` (filter on the textarea placeholder text)
- `ssc.bubble.welcomeMessage` (filter on the welcome message HTML)

---

## 5. Settings UI extension

Today, tabs are a hardcoded PHP array in `SSC_Admin::render_page()` (lines 229-239) and all `add_settings_section` / `add_settings_field` calls happen inside `SSC_Admin::register_settings()`.

**Refactor:**

```php
// In SSC_Admin::register_settings(), after all core sections are added:
do_action( 'ssc_register_settings' );

// In SSC_Admin::render_page():
$tabs = apply_filters( 'ssc_settings_tabs', array(
    'chats'         => array( 'label' => __( 'Chats' ),         'order' => 10 ),
    'general'       => array( 'label' => __( 'General' ),       'order' => 20 ),
    'display_names' => array( 'label' => __( 'Display Names' ), 'order' => 30 ),
    // ...
    'discord'       => array( 'label' => __( 'Discord' ),       'order' => 80, 'section' => 'ssc_section_discord' ),
    'status'        => array( 'label' => __( 'Status' ),        'order' => 100 ),
) );
uasort( $tabs, fn($a, $b) => $a['order'] <=> $b['order'] );
```

An add-on then does:

```php
add_filter( 'ssc_settings_tabs', function( $tabs ) {
    $tabs['whatsapp'] = array(
        'label'   => __( 'WhatsApp', 'sscw' ),
        'order'   => 85,
        'section' => 'sscw_section_whatsapp',
    );
    return $tabs;
} );

add_action( 'ssc_register_settings', function() {
    add_settings_section( 'sscw_section_whatsapp', '', '__return_null', 'ssc', array(
        'before_section' => '<div class="ssc_tab">', 'after_section' => '</div>',
    ) );
    add_settings_field( /* ... */ );
} );
```

Add-ons store their options inside the same `ssc_options` array (so one save button still saves everything). The `sanitize_options` callback fires `apply_filters( 'ssc_sanitize_options', $sanitized, $input )` to let add-ons sanitise their own keys.

---

## 6. Front-end bubble extension

Add-ons enqueue their own JS via `do_action('ssc_enqueue_frontend')` and tap into the bubble hooks:

```js
// Example: add a "Chat on WhatsApp" button to the bubble action bar
window.ssc.hooks.addFilter( 'ssc.bubble.actions', 'sscw', (actions) => {
    actions.push({
        id: 'whatsapp-jump',
        label: 'Continue on WhatsApp',
        icon: 'whatsapp',
        onClick: () => {
            window.open(`https://wa.me/${sscw_config.business_number}`, '_blank');
        },
    });
    return actions;
} );
```

To pass data to that JS, the add-on uses the localised config filter:

```php
add_filter( 'ssc_frontend_config', function( $config ) {
    $config['whatsapp'] = array(
        'business_number' => SSC_Settings::get_option( 'sscw_business_number', '' ),
    );
    return $config;
} );
```

…or enqueues + localises its own separate script (`wp_localize_script` on the add-on's own handle), which is cleaner for larger add-ons.

---

## 7. Admin UI extension

### Adding a column to the conversation list

The list table is rendered client-side by `admin.js` from `/admin/conversations` JSON. To add a column:

```js
// In the add-on's admin JS:
wp.hooks.addFilter( 'ssc.admin.conversationColumns', 'sscw', (cols) => {
    cols.push({ id: 'channel', label: 'Channel', order: 25, render: (conv) => {
        if (conv.channel === 'whatsapp') return '<span class="ssc-badge ssc-badge-wa">WhatsApp</span>';
        if (conv.channel === 'discord')  return '<span class="ssc-badge ssc-badge-discord">Discord</span>';
        return '<span class="ssc-badge ssc-badge-web">Web</span>';
    } });
    return cols;
} );
```

The core JS must (a) iterate the filter output to build both `<thead>` and `<tbody>` cells in the right order, and (b) expose `conversation.channel` in the JSON. The REST handler does `SELECT channel FROM ssc_conversations` (now that the column exists).

### Adding panels to the conversation detail sidebar

Server-rendered, so just hook the PHP action:

```php
add_action( 'ssc_conversation_sidebar', function( $conversation ) {
    if ( $conversation->channel !== 'whatsapp' ) return;
    global $wpdb;
    $thread = $wpdb->get_row( /* fetch ssc_whatsapp_threads row */ );
    echo '<div class="ssc-sidebar-panel">';
    echo '<h4>WhatsApp</h4>';
    echo '<p>Phone: ' . esc_html( $thread->wa_phone_number ) . '</p>';
    echo '<p>Last inbound: ' . esc_html( $thread->last_inbound_at ) . '</p>';
    echo '</div>';
} );
```

### Replacing built-in panels

Provide `apply_filters('ssc_conversation_sidebar_panels', $panels)` so add-ons can remove or reorder the core panels (e.g. hide IP address when GDPR-strict).

---

## 8. Add-on registry + dependency checks

A lightweight registry helps with diagnostics, upsell, and version compatibility.

```php
// In an add-on's bootstrap:
SSC_Addons::register( array(
    'slug'                 => 'super-speedy-chat-whatsapp',
    'name'                 => 'Super Speedy Chat — WhatsApp',
    'version'              => '1.0.0',
    'requires_core'        => '1.08',         // min core version
    'requires_addon_api'   => '1.0',          // min SSC_ADDON_API_VERSION
    'channel'              => 'whatsapp',     // optional — purely informational
    'plugin_file'          => __FILE__,
) );
```

`SSC_Addons` does:
- Bail with an admin notice if `SSC_ADDON_API_VERSION` is lower than the add-on requires
- Show a panel on the **Status** tab listing all registered add-ons with their version + licence status
- Show "Get more add-ons" with links to superspeedyplugins.com for known-good add-ons not currently installed

`SSC_ADDON_API_VERSION` is a `const` on the `SSC_Addons` class. Bump only on **breaking** changes to the hook signatures.

---

## 9. Licensing

Already solved by the `super-speedy-settings` submodule. Each add-on includes the submodule (as a git submodule, the same way core does) and initialises it:

```php
require_once plugin_dir_path( __FILE__ ) . 'super-speedy-settings/super-speedy-settings.php';
SuperSpeedySettings_1_0::init( array(
    'plugin_slug' => 'super-speedy-chat-whatsapp',
    'version'     => SSCW_VERSION,
    'file'        => __FILE__,
) );
```

The licence key field appears under **WP Admin → Super Speedy → Licences**, hits the same auth server as every other Super Speedy plugin, and respects the same 1-hour cache + "Recheck Licences" button shipped in 1.07.1. Zero core changes needed for billing.

**Decision:** should the chat plugin's add-on degrade gracefully if its licence is expired (still receives inbound messages, but blocks outbound) or hard-fail (auto-deactivate)? See open questions.

---

## 10. Refactor checklist (existing code)

To prove the API is complete, the existing Discord integration is rebuilt against it. None of the changes should be visible to end users — same behaviour, cleaner seams.

- [ ] `class-ssc-chat.php:78-81` — remove direct `SSC_Discord::push_message()`; fire `do_action('ssc_visitor_message_sent', …)` instead; Discord listens and pushes.
- [ ] `class-ssc-chat.php:147-150` — same for admin reply.
- [ ] `class-ssc-chat.php` — extract `external_inbound()` + `get_or_create_external_conversation()` helpers, used by both Discord and any future add-on.
- [ ] `class-ssc-discord.php::handle_incoming()` — rewrite to call the new helpers, deleting its bespoke participant/message code.
- [ ] `class-ssc-admin.php:229-239` — replace hardcoded `$tabs` array with `apply_filters('ssc_settings_tabs', ...)`.
- [ ] `class-ssc-admin.php::register_settings()` — fire `do_action('ssc_register_settings')` at the end; move the existing Discord section registration into a Discord-specific bootstrap that hooks this action.
- [ ] `class-ssc-rest.php` — fire `do_action('ssc_register_rest_routes', $this)` after core routes are registered.
- [ ] `class-ssc-db.php` — add `channel` column to conversations; bump `DB_VERSION`.
- [ ] `super-speedy-chat.php` — add the `ssc_enqueue_frontend` action and `ssc_frontend_config` filter at the end of `ssc_enqueue_frontend_assets()`.
- [ ] `admin/admin.js` — refactor list rendering to iterate a columns array (from `wp.hooks.applyFilters('ssc.admin.conversationColumns', defaults)`) instead of a fixed `<thead>`.
- [ ] `assets/chat-bubble.js` — add the inline hooks lib + fire the lifecycle events listed in §4.4.
- [ ] New file: `includes/class-ssc-addons.php` — registry + version checks + Status-tab panel.

Estimate: **~2-3 days** for the refactor (it's mostly mechanical), plus testing parity with current Discord behaviour.

---

## 11. Developer guide: building a hello-world add-on

Below is the minimum boilerplate an add-on needs. Aimed at a developer who already knows WordPress plugin development.

### 11.1 File layout

```
super-speedy-chat-helloworld/
├── super-speedy-chat-helloworld.php   # Plugin header + bootstrap
├── includes/
│   ├── class-sschw-channel.php        # API client (push outbound, handle inbound)
│   ├── class-sschw-settings.php       # Register tab + fields
│   └── class-sschw-rest.php           # /helloworld/incoming endpoint
├── assets/
│   ├── admin.js                       # Adds column + badge to list
│   └── bubble.js                      # Adds button to front-end bubble
├── super-speedy-settings/             # git submodule
└── readme.txt
```

### 11.2 Plugin header

```php
<?php
/*
Plugin Name: Super Speedy Chat — Hello World
Plugin URI: https://www.superspeedyplugins.com
Author: Super Speedy Plugins
Version: 1.0.0
Requires Plugins: super-speedy-chat
Description: Reference add-on demonstrating the SSC extension API.
*/
if ( ! defined( 'ABSPATH' ) ) exit;

// Fail fast if core is missing (belt-and-braces — Requires Plugins handles it from WP 6.5+)
add_action( 'plugins_loaded', function() {
    if ( ! class_exists( 'SSC_Chat' ) ) {
        add_action( 'admin_notices', function() {
            echo '<div class="notice notice-error"><p>Super Speedy Chat — Hello World requires Super Speedy Chat to be active.</p></div>';
        });
        return;
    }

    // Register with the add-on system
    SSC_Addons::register( array(
        'slug'    => 'super-speedy-chat-helloworld',
        'name'    => 'Hello World',
        'version' => '1.0.0',
        'channel' => 'helloworld',
        'requires_addon_api' => '1.0',
        'plugin_file' => __FILE__,
    ) );

    // Init the licensing submodule
    require_once __DIR__ . '/super-speedy-settings/super-speedy-settings.php';
    SuperSpeedySettings_1_0::init( array(
        'plugin_slug' => 'super-speedy-chat-helloworld',
        'version'     => '1.0.0',
        'file'        => __FILE__,
    ) );

    // Wire the channel
    require_once __DIR__ . '/includes/class-sschw-channel.php';
    SSCHW_Channel::init();
}, 20 ); // priority 20 to ensure core has set up its constants
```

### 11.3 Pushing outbound messages

```php
// In class-sschw-channel.php
class SSCHW_Channel {
    public static function init() {
        add_action( 'ssc_visitor_message_sent', array( __CLASS__, 'on_visitor' ), 10, 4 );
        add_action( 'ssc_admin_reply_sent',     array( __CLASS__, 'on_admin' ),   10, 4 );
        add_filter( 'ssc_channels',             array( __CLASS__, 'register_channel' ) );
    }

    public static function register_channel( $channels ) {
        $channels[] = array( 'id' => 'helloworld', 'label' => 'Hello World', 'icon' => 'dashicons-megaphone' );
        return $channels;
    }

    public static function on_visitor( $message_id, $conversation, $message_text, $participant ) {
        // Only push if our channel is enabled and this conversation is on our channel
        if ( ! get_option( 'sschw_enabled' ) ) return;
        if ( $conversation->channel !== 'helloworld' && ! self::is_mirroring() ) return;

        self::send_to_external_api( $conversation, $participant->display_name, $message_text );
    }

    public static function on_admin( $message_id, $conversation, $message_text, $admin_user_id ) {
        // ...
    }

    private static function send_to_external_api( $conversation, $sender, $body ) {
        wp_remote_post( 'https://api.example.com/messages', array(
            'body' => wp_json_encode( array(
                'to'   => $conversation->id,
                'from' => $sender,
                'text' => $body,
            ) ),
            'headers' => array( 'Authorization' => 'Bearer ' . get_option( 'sschw_token' ) ),
            'timeout' => 10,
        ) );
    }
}
```

### 11.4 Receiving inbound messages

```php
add_action( 'ssc_register_rest_routes', function( $rest ) {
    register_rest_route( 'ssc/v1', '/helloworld/incoming', array(
        'methods'  => 'POST',
        'callback' => 'sschw_handle_incoming',
        'permission_callback' => function( $request ) {
            $secret = $request->get_header( 'X-SSCHW-Secret' );
            return hash_equals( get_option( 'sschw_webhook_secret' ), $secret );
        },
    ) );
} );

function sschw_handle_incoming( $request ) {
    $conv = SSC_Chat::get_or_create_external_conversation( array(
        'channel'      => 'helloworld',
        'external_id'  => $request->get_param( 'user_id' ),
        'display_name' => $request->get_param( 'user_name' ),
    ) );

    SSC_Chat::external_inbound( array(
        'conversation_id' => $conv->id,
        'channel'         => 'helloworld',
        'author_name'     => $request->get_param( 'user_name' ),
        'author_type'     => 'visitor',
        'message'         => $request->get_param( 'text' ),
        'external_msg_id' => $request->get_param( 'msg_id' ),
    ) );

    return rest_ensure_response( array( 'success' => true ) );
}
```

### 11.5 Adding a settings tab + fields

```php
add_filter( 'ssc_settings_tabs', function( $tabs ) {
    $tabs['helloworld'] = array(
        'label' => __( 'Hello World', 'sschw' ),
        'order' => 90,
        'section' => 'sschw_section',
    );
    return $tabs;
} );

add_action( 'ssc_register_settings', function() {
    add_settings_section( 'sschw_section', '', '__return_null', 'ssc', array(
        'before_section' => '<div class="ssc_tab">',
        'after_section'  => '</div>',
    ) );
    add_settings_field( 'sschw_enabled', 'Enable', 'sschw_field_checkbox', 'ssc', 'sschw_section' );
    add_settings_field( 'sschw_token',   'API Token', 'sschw_field_password', 'ssc', 'sschw_section' );
} );

// Sanitise our own keys when the form saves
add_filter( 'ssc_sanitize_options', function( $sanitized, $input ) {
    $sanitized['sschw_enabled'] = ! empty( $input['sschw_enabled'] );
    $sanitized['sschw_token']   = sanitize_text_field( $input['sschw_token'] ?? '' );
    return $sanitized;
}, 10, 2 );
```

### 11.6 Adding a column to the conversation list

```php
// Enqueue our admin JS only on the SSC admin page
add_action( 'admin_enqueue_scripts', function( $hook ) {
    if ( strpos( $hook, '_page_ssc' ) === false ) return;
    wp_enqueue_script( 'sschw-admin', plugin_dir_url( __FILE__ ) . 'assets/admin.js', array( 'ssc-admin', 'wp-hooks' ), '1.0.0', true );
} );
```

```js
// assets/admin.js
wp.hooks.addFilter( 'ssc.admin.conversationColumns', 'sschw', (cols) => {
    cols.push({
        id: 'helloworld',
        label: 'Hello World',
        order: 25,
        render: (conv) => conv.channel === 'helloworld'
            ? '<span class="ssc-badge sschw-badge">HW</span>'
            : '',
    });
    return cols;
} );
```

### 11.7 Adding a button to the front-end bubble

```php
add_action( 'ssc_enqueue_frontend', function() {
    wp_enqueue_script( 'sschw-bubble', plugin_dir_url( __FILE__ ) . 'assets/bubble.js', array( 'ssc-chat-bubble' ), '1.0.0', true );
} );

add_filter( 'ssc_frontend_config', function( $config ) {
    $config['helloworld'] = array(
        'url' => get_option( 'sschw_external_url', '' ),
    );
    return $config;
} );
```

```js
// assets/bubble.js
window.ssc.hooks.addFilter( 'ssc.bubble.actions', 'sschw', (actions) => {
    if ( ! ssc_config.helloworld?.url ) return actions;
    actions.push({
        id: 'hw-jump',
        label: 'Open Hello World',
        onClick: () => window.open( ssc_config.helloworld.url, '_blank' ),
    });
    return actions;
} );
```

---

## 12. WhatsApp as the reference add-on

Mapping the WhatsApp plan in `whatsapp-integration-plan.md` onto this add-on system:

| WhatsApp need | Hook used |
|---|---|
| Push admin reply to WhatsApp | `add_action('ssc_admin_reply_sent', …)` |
| Receive inbound from Meta webhook | Hooks `ssc_register_rest_routes` to add `/whatsapp/incoming` |
| Settings tab | `add_filter('ssc_settings_tabs', …)` + `add_action('ssc_register_settings', …)` |
| "WhatsApp" badge in conversation list | `wp.hooks.addFilter('ssc.admin.conversationColumns', …)` |
| Phone number sidebar panel | `add_action('ssc_conversation_sidebar', …)` |
| 24-hour-window warning banner | `add_action('ssc_conversation_reply_footer', …)` |
| Chat-on-WhatsApp button on bubble | `window.ssc.hooks.addFilter('ssc.bubble.actions', …)` |
| Conversation channel = `whatsapp` | Sets `channel` column when calling `get_or_create_external_conversation` |
| `ssc_whatsapp_threads` table | Created in add-on's `register_activation_hook` |
| Licensing | Add-on includes super-speedy-settings submodule |

i.e. **every WhatsApp requirement maps to an extension point already in this plan** — confirming the hook set is sufficient for at least the first non-trivial add-on.

---

## 13. Open questions for you

Please review and let me know your preference on each:

1. **Discord — keep in core or extract to add-on?** Two options:
   - **Keep in core (free with the chat plugin)** — easier upsell story ("buy the chat, get Discord free, buy WhatsApp/Slack as extras")
   - **Extract Discord to its own add-on too** — cleaner separation, simpler core, more SKUs to sell. Means every customer needs at least 2 plugins.
   - My recommendation: **keep Discord in core** but refactor it to use the new hooks (so it's the reference implementation).

2. **Add-on API versioning policy** — How aggressively do we want to evolve the hook API? Strict semver (1.0 → 2.0 = breaking change requires all add-ons to update simultaneously) or relaxed (deprecate but don't remove)? My recommendation: **relaxed deprecation** — old hooks keep firing with `_deprecated_hook()` notices for at least 2 minor versions.

3. **JS hooks — wp-hooks for both contexts, or wp-hooks (admin) + inline lib (bubble)?** My recommendation: **inline lib on bubble** to keep the front-end JS small (~40 lines vs +3KB gzipped). Same API surface.

4. **One save button or per-tab saves?** Today the whole settings form is one giant `<form>` with one save button. With add-ons writing to `ssc_options`, that means saving WhatsApp settings re-validates Discord settings. Should we move to **per-tab forms** (one save per channel) or stick with **one big save**? Recommendation: **stick with one big save** for v1; revisit if customers complain.

5. **Add-on licence enforcement** — On licence expiry: degrade gracefully (still receive inbound, block outbound — so customers don't lose data) or hard-fail (auto-deactivate)? My recommendation: **degrade gracefully** + visible admin notice.

6. **DB migration for `channel` column** — Existing installs have conversations without a channel. Backfill default `'website'` on upgrade and detect Discord-originated conversations from `ssc_discord_threads` to backfill `'discord'`? Or leave them all as `'website'`? Recommendation: **backfill from `ssc_discord_threads`** during the DB upgrade — one-time `UPDATE … WHERE id IN (SELECT conversation_id FROM ssc_discord_threads)`.

7. **Pricing model** — Annual subscriptions per add-on (like core), or one-time purchase? Currently the rest of Super Speedy plugins are annual. Recommendation: **annual**, matching existing pattern.

8. **Bundle pricing** — Offer a "Chat + all channels" bundle for a discount, or sell strictly à la carte? Up to your commercial preference; the technical implementation doesn't care.

9. **Add-on naming convention** — `super-speedy-chat-whatsapp` (long but explicit) or `ssc-whatsapp` (short but cryptic)? Recommendation: **the long form** for the plugin slug + WordPress.org-style identity, with `sschw` / `sscs` / `ssct` as PHP class/function prefixes.

10. **Add-on directory bootstrap timing** — Add-ons run after core (`plugins_loaded` priority 20 in the example above). If two add-ons want to listen for `ssc_register_settings`, ordering can matter for tab order. Use explicit `order` keys (as in §5) rather than relying on priority. Agreed?

11. **Cross-channel mirroring** — Should we support the same conversation being mirrored to multiple channels (e.g. visitor on the website, admin gets it on both Discord and WhatsApp)? The hook signatures allow it but no core UI is planned. Recommendation: **defer**, decide later based on demand.
