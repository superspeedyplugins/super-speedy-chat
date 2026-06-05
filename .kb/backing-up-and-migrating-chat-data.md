# Backing Up and Migrating Chat Data

Your conversations live in custom database tables — not in WordPress posts. That's what makes the chat fast, but it has one consequence you should know about before you migrate, restore, or clean up your site: tools that only handle "content" will not carry your chat history with them.

## Where the data lives

Super Speedy Chat stores everything in five custom tables (with your site's table prefix, usually `wp_`):

| Table | Contains |
|---|---|
| `wp_ssc_conversations` | One row per conversation: status, visitor info (name, email, IP, user agent), assignment, timestamps. |
| `wp_ssc_participants` | Who's in each conversation (visitor, admins, bot) and their display names. |
| `wp_ssc_messages` | Every message. |
| `wp_ssc_canned_responses` | Your saved canned responses. |
| `wp_ssc_discord_threads` | The mapping between conversations and Discord threads. |

Settings live in three options in the standard `wp_options` table: `ssc_options` (all plugin settings), `ssc_customizer` (appearance), and `ssc_db_version`. Your license key is stored separately in `superspeedy_options` (shared by all Super Speedy plugins).

## What's safe and what isn't

**✅ Full-database backups carry everything.** Anything that dumps the whole database — your host's backup, `mysqldump`, or backup plugins in full-database mode — includes the `ssc_*` tables automatically, because they're ordinary tables in the same database.

**⚠️ Content exports do NOT include chat.** The built-in WordPress export (Tools > Export / WXR files) only covers posts, pages and other post types — chat history, canned responses and settings are not in it. The same goes for any migration plugin configured to copy "content only" or a hand-picked list of core tables.

**⚠️ Deleting the plugin erases everything.** *Deactivating* Super Speedy Chat keeps all data. But clicking **Delete** on the Plugins screen runs the uninstaller, which **drops all five tables and removes the settings**. If there's any chance you'll want the history later, back up first.

## Backing up just the chat tables

A targeted dump of the chat tables (adjust the prefix and credentials):

```bash
mysqldump -u DB_USER -p DB_NAME \
  wp_ssc_conversations wp_ssc_participants wp_ssc_messages \
  wp_ssc_canned_responses wp_ssc_discord_threads \
  > ssc-chat-backup.sql
```

Restore with:

```bash
mysql -u DB_USER -p DB_NAME < ssc-chat-backup.sql
```

## Migrating to a new site or host

1. **Use a full-database migration** (or include the five `ssc_*` tables explicitly if your tool lets you pick tables). All-in-one migration plugins that copy the entire database are fine.
2. **Copy the plugin** as part of your files, or reinstall and re-activate it on the new site — activation recreates the speed-boosting mu-plugin (`wp-content/mu-plugins/ssc-fast-ajax.php`) automatically, so you don't need to copy `mu-plugins` by hand.
3. **Re-enter or verify your license** on the new domain under the **Super Speedy** menu (licenses are checked per-domain — see Installing Your License, Activation and Updates).
4. **If you use Discord**, update the companion bot's configuration if your site URL changed, since it posts back to your site's REST endpoint.

Two small notes on visitor continuity: visitor identity is a cookie tied to your domain, so on the same domain returning visitors resume their conversations after a migration; if the domain changes, visitors start fresh conversations (history is still in your admin). Messages are stored as plain text, so search-replace tools used during migration won't corrupt them.

## Cleaning up old conversations

There's currently no built-in auto-purge. If you want to trim old closed conversations for GDPR or housekeeping reasons, see the retention section of the Privacy and Data Handling guide before deleting rows by hand — conversations, participants and messages reference each other by ID.
