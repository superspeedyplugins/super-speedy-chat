# Developer Guide: Hooks and Building Channel Add-ons

Super Speedy Chat is built so that new chat **channels** (WhatsApp, Telegram, SMS, …) can be added as separate plugins without touching core. The bundled Discord integration is itself written entirely against this extension surface — it's the reference implementation. This guide documents the hooks, helpers, and registry you build against.

> For the big-picture component map and the message lifecycle, read the Architecture Overview first. This guide is the practical hook reference.

## The model in one paragraph

A conversation has many **participants** (typed `visitor` / `admin` / `bot` / `system`) and many **messages**, and carries a `channel` discriminator. Core never calls a channel directly — it fires **lifecycle actions**, and channels listen. To push messages *in* from an external channel, you call two `SSC_Chat` helpers. To appear in settings/REST/UI, you hook the **registration** filters/actions. That's the whole API.

## Lifecycle actions (core fires, you listen)

| Action | Args | Fires when |
|---|---|---|
| `ssc_visitor_message_sent` | `$message_id, $conversation, $message_text, $participant` | A visitor sends a message. |
| `ssc_admin_reply_sent` | `$message_id, $conversation, $message_text, $admin_user_id` | An admin replies (from wp-admin; `$admin_user_id` is `null` for replies that originated in an external channel). |
| `ssc_bot_message_sent` | `$message_id, $conversation, $message_text, $message_type` | An auto/bot message is sent. |
| `ssc_conversation_status_changed` | `$conversation_id, $new_status, $old_status` | Status transitions (`active`/`waiting`/`closed`/`archived`). |
| `ssc_conversation_created` | `$conversation` | A new conversation row is created. |

Example — relay every visitor message to your channel:

```php
add_action( 'ssc_visitor_message_sent', function ( $message_id, $conversation, $text, $participant ) {
    if ( $conversation->channel !== 'mychannel' ) {
        return; // not ours
    }
    My_Channel::push( $conversation, $text );
}, 10, 4 );
```

## Inbound helpers (you call core)

When a message arrives *from* your channel, don't write to the tables yourself — use these so participant creation, status transitions, and lifecycle hooks all happen correctly:

- `SSC_Chat::get_or_create_external_conversation( $args )` — resolves (or creates) a conversation owned by an external channel. There's no browser cookie, so identity is derived as `sha256('ext:' . $channel . ':' . $external_id)`.
  ```php
  $conv = SSC_Chat::get_or_create_external_conversation( array(
      'channel'      => 'mychannel',
      'external_id'  => $sender_phone_or_id,   // stable per remote user
      'display_name' => $sender_name,
      'metadata'     => array( 'phone' => $sender_phone ),
  ) );
  ```
- `SSC_Chat::external_inbound( $args )` — appends an inbound message and fires the right lifecycle hook.
  ```php
  SSC_Chat::external_inbound( array(
      'conversation_id' => $conv->id,
      'channel'         => 'mychannel',
      'author_name'     => $sender_name,
      'author_type'     => 'visitor', // or 'admin' / 'bot' / 'system'
      'message'         => $text,
      'external_msg_id' => $remote_id, // optional, for your dedup
  ) );
  ```

## Registration hooks (appear in settings, REST, UI)

| Hook | Type | Use it to |
|---|---|---|
| `ssc_register_rest_routes` | action (`$rest`) | Register your own REST routes (e.g. an inbound webhook). |
| `ssc_register_settings` | action | Add Settings API sections/fields. |
| `ssc_settings_tabs` | filter (`$tabs`) | Add a settings tab (`['label'=>…, 'order'=>…]`). |
| `ssc_sanitize_options` | filter (`$sanitized, $input`) | Sanitise your own keys in the shared `ssc_options` array. |
| `ssc_channels` | filter (`$channels`) | Declare your channel (`['id'=>…, 'label'=>…, 'icon'=>…]`). |
| `ssc_frontend_config` | filter (`$config`) | Add keys to the front-end `ssc_config` blob. |
| `ssc_enqueue_frontend` | action (`$ultra_ajax`) | Enqueue your own bubble JS after core's. |
| `ssc_conversation_sidebar` | action (`$conversation`) | Render an extra panel in the admin conversation sidebar. |
| `ssc_conversation_reply_footer` | action (`$conversation`) | Render notices below the admin reply box. |

## Filters worth knowing

- `ssc_llm_daily_cap` — integer site-wide daily ceiling on LLM classification calls (return `0` to disable the cap).
- Front-end `window.ssc.hooks` — a tiny `@wordpress/hooks`-style API in the bubble:
  - actions: `ssc.bubble.rendered`, `ssc.bubble.opened`, `ssc.bubble.closed`, `ssc.bubble.messageSent`, `ssc.bubble.messageReceived`
  - filter: `ssc.bubble.welcomeMessage`
  ```js
  window.ssc.hooks.addFilter('ssc.bubble.welcomeMessage', 'mychannel', function (msg) {
      return msg || 'Hi there!';
  });
  ```

## Registering your add-on

Call `SSC_Addons::register()` on `plugins_loaded` (priority ≥ 20) so core can list you on the Status tab and gate on version compatibility:

```php
add_action( 'plugins_loaded', function () {
    SSC_Addons::register( array(
        'slug'               => 'ssc-mychannel',
        'name'               => 'My Channel for Super Speedy Chat',
        'version'            => '1.0.0',
        'channel'            => 'mychannel',
        'requires_core'      => '1.08',
        'requires_addon_api' => '1.0',
        'plugin_file'        => __FILE__,
    ) );
}, 20 );
```

If your `requires_addon_api` is newer than core's `SSC_Addons::ADDON_API_VERSION`, registration is refused and an admin notice is queued.

## A channel add-on, end to end

1. On `plugins_loaded` (≥20): `SSC_Addons::register()` and add your `ssc_channels` entry.
2. Add settings via `ssc_settings_tabs` + `ssc_register_settings` + `ssc_sanitize_options`.
3. Register an inbound webhook via `ssc_register_rest_routes`; in its handler, call `get_or_create_external_conversation()` then `external_inbound()`.
4. Listen on `ssc_admin_reply_sent` (and `ssc_visitor_message_sent` if you mirror both ways) to push outbound messages to your channel.
5. Optionally add sidebar/footer panels and front-end behaviour.

Read `includes/class-ssc-discord.php` as the worked example — every step above is visible there.
