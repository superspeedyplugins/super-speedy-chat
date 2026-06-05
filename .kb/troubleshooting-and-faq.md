# Troubleshooting and FAQ

A first-stop reference for the most common issues, plus quick answers to frequently asked questions. For feature-specific troubleshooting, the Ultra Ajax and LLM Auto-Reply guides have their own dedicated sections.

## Troubleshooting

### The chat bubble doesn't appear

1. **Check it's enabled.** Go to **Super Speedy Chat > General** and confirm "Enable Chat" is ticked.
2. **You're viewing wp-admin.** The bubble only loads on the front end of your site, never inside wp-admin. Open your actual site (ideally in an incognito window so you're not logged in).
3. **Caching.** If you use a page cache or CDN, purge it. A cached page may not include the chat assets or may carry a stale config.
4. **Theme support.** The widget is injected via standard WordPress hooks. A theme that doesn't call `wp_footer()` won't render it — rare, but possible with heavily customised themes.
5. **JavaScript errors.** Open your browser console; a conflicting plugin/theme script that throws early can stop the widget initialising.

### Messages won't send

- **Rate limited (HTTP 429).** Sending too fast trips the per-IP limit (15 sends/minute). Wait a moment.
- **REST API blocked.** Some security plugins or host rules block `/wp-json/`. Confirm `yoursite.com/wp-json/ssc/v1/` is reachable.
- **Cookies blocked.** The chat needs the `ssc_visitor_hash` cookie to maintain a session. If the browser blocks it, sends fail with "No active session."

### A reply I sent doesn't show up for the visitor

- The visitor's widget polls on an interval; give it a second or two.
- If a page/edge cache is caching the `/poll` response, exclude `/wp-json/ssc/` from caching.
- Confirm Ultra Ajax is healthy (see below) — a broken mu-plugin falls back to the slower path but shouldn't stop delivery.

### Email notifications aren't arriving

- WordPress email is unreliable on many hosts. Install an SMTP plugin (e.g. WP Mail SMTP) to route mail through a real mail service.
- Check spam folders and confirm the **From Name** / admin email on the **Email** tab are sensible.
- Verify the relevant toggle (admin or visitor notifications) is enabled.

### "I see a brand-new conversation every time I test"

You're testing in the same browser where you're logged in as admin, or with cookies cleared between attempts. Each distinct browser/cookie is a distinct visitor. Test the visitor side in a single incognito window and keep it open.

### Ultra Ajax shows as "Not installed"

- Open **Super Speedy Chat > Status** to confirm.
- The mu-plugin is written to `wp-content/mu-plugins/`. If your host blocks writes there, it can't install. Ensure the directory exists and is writable by the PHP user.
- Deactivating and reactivating the plugin forces a fresh install.

### LLM auto-reply never fires

- On the **Behaviour** tab, set **Timeout Action** to one of the LLM options.
- On the **LLM Auto-Reply** tab, confirm the provider and API key are set.
- You need **canned responses** saved for the classifier to choose from.
- A site-wide daily cap protects against runaway cost; if you've hit it, auto-reply pauses until the next day (the cap is adjustable via the `ssc_llm_daily_cap` filter).

### Discord isn't working

- Double-check the **bot token** and **channel ID**, and that the bot has been added to your server with the right permissions.
- Visitor → Discord push is automatic once configured. **Discord → WordPress replies require the companion Node bot** to be running (see the Discord companion-bot guide).
- Use the **Test Connection** button on the Discord tab to verify the token.

### A `_load_textdomain_just_in_time` notice in my logs

This is a harmless WordPress 6.7+ notice about translations loading early. It doesn't affect functionality.

### Chat is increasing server load

Lengthen the polling intervals on the **Behaviour** tab, and make sure Ultra Ajax is active (it makes each request far cheaper). See the Behaviour and Ultra Ajax guides.

## FAQ

**Do visitors have to log in to chat?**
No. Chat is anonymous by default. You can require login on the **Behaviour** tab if you prefer — logged-out visitors then see a log in / create account invitation instead of the message box.

**Does it need a third-party service?**
No. Core chat runs entirely on your server. OpenAI/Anthropic and Discord are optional add-on integrations.

**Will it slow down my site?**
The widget is lightweight and loads in the footer. With Ultra Ajax enabled, chat requests bypass the full WordPress load and complete in single-digit milliseconds.

**Does it work on mobile?**
Yes — the widget is responsive and adjusts to the on-screen keyboard.

**How much does the LLM auto-reply cost?**
Fractions of a cent per classification using the default budget models. See the LLM Auto-Reply guide for details, and note the built-in daily spend cap.

**Is my data sent anywhere?**
Not unless you enable LLM Auto-Reply or Discord. See the Privacy and Data Handling guide.

**Does it work on WordPress Multisite?**
The plugin runs per-site. The Ultra Ajax mu-plugin is shared across the network (mu-plugins are network-wide), but it only acts on requests for sites where chat is enabled.

**What happens to my data if I uninstall?**
A full uninstall drops all chat tables, deletes options, and removes the mu-plugin. Deactivation keeps your data.
