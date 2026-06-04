# Discord Integration Setup Guide

Super Speedy Chat supports instant bidirectional Discord integration. Visitor messages appear in Discord threads immediately, and admin replies in Discord are relayed back to the WordPress chat in real-time.

## How It Works

- **WordPress to Discord**: When a visitor sends a message, it's pushed instantly to a Discord thread via the bot API. Each conversation gets its own thread in a designated channel.
- **Discord to WordPress**: A companion Node.js bot listens on the Discord Gateway. When someone replies in a thread, the bot relays the message to a WordPress REST endpoint, where it appears in the conversation.

Authentication between the bot and WordPress uses a shared webhook secret sent via the `X-SSC-Secret` header.

---

## Prerequisites

- A Discord account with permission to create bots
- A Discord server (guild) where you want chat threads to appear
- Node.js 16+ installed on the server that will run the companion bot (can be the same server as WordPress, or a separate one)
- Super Speedy Chat plugin activated on your WordPress site

---

## Step 1: Create a Discord Bot

1. Go to the [Discord Developer Portal](https://discord.com/developers/applications)
2. Click **New Application**, give it a name (e.g. "Site Chat"), and click **Create**
3. Go to the **Bot** section in the left sidebar
4. Click **Reset Token** and copy the bot token — you'll need this for both WordPress settings and the companion bot. **Store it securely; you won't be able to see it again.**
5. Under **Privileged Gateway Intents**, enable:
   - **Message Content Intent** (required so the bot can read message content in threads)
6. Click **Save Changes**

## Step 2: Invite the Bot to Your Server

1. In the Developer Portal, go to **OAuth2** > **URL Generator**
2. Under **Scopes**, select `bot`
3. Under **Bot Permissions**, select:
   - Send Messages
   - Send Messages in Threads
   - Create Public Threads
   - Read Message History
   - View Channels
4. Copy the generated URL and open it in your browser
5. Select your server from the dropdown and click **Authorize**

## Step 3: Get the Channel ID

1. In Discord, open **User Settings** > **Advanced** and enable **Developer Mode**
2. Right-click the channel where you want chat threads to appear
3. Click **Copy Channel ID**

## Step 4: Configure WordPress Settings

1. In wp-admin, go to **Super Speedy Chat** > **Settings** (or the chat admin page)
2. Click the **Discord** tab
3. Fill in:
   - **Enable Discord**: Check this box
   - **Bot Token**: Paste the token from Step 1
   - **Channel ID**: Paste the channel ID from Step 3
4. Click **Save Changes**
5. After saving, the page will show:
   - **Webhook Secret**: An auto-generated secret (you'll need this for the bot)
   - **Endpoint URL**: The REST endpoint URL (e.g. `https://yoursite.com/wp-json/ssc/v1/discord/incoming`)
6. Click **Test Discord Connection** to verify the bot token is valid and the bot can reach Discord

## Step 5: Set Up the Companion Bot

The companion bot is a small Node.js application included in the plugin at `bot/`.

> **Important: Copy the bot outside of your WordPress installation.**
>
> Do **not** run the bot directly from `wp-content/plugins/super-speedy-chat/bot/`. You should copy it to a separate location for two reasons:
>
> 1. **Security** — The bot's `.env` file contains your Discord bot token and webhook secret. Anything inside `wp-content` is potentially web-accessible (a misconfigured server, a directory listing vulnerability, or a path traversal bug could expose these credentials). Placing the bot outside the web root eliminates this risk entirely.
> 2. **Plugin updates** — When Super Speedy Chat is updated, the entire plugin directory is replaced. Any changes you made inside `bot/` (your `.env` file, `node_modules`, PM2/systemd config pointing there) would be deleted.
>
> A good location is a dedicated directory outside the web root, for example:
> ```
> /opt/ssc-discord-bot/
> ```
> Other common choices: `/home/youruser/ssc-discord-bot/` or `/srv/ssc-discord-bot/`.
>
> The key requirement is that the directory is **not** inside your web root (e.g. not under `/var/www/`, `public_html/`, or `htdocs/`).

1. Copy the bot to a location outside your web root:
   ```bash
   sudo mkdir -p /opt/ssc-discord-bot
   sudo cp -r /var/www/yoursite/wp-content/plugins/super-speedy-chat/bot/. /opt/ssc-discord-bot/
   sudo chown -R www-data:www-data /opt/ssc-discord-bot
   ```

2. Navigate to the bot directory:
   ```bash
   cd /opt/ssc-discord-bot
   ```

3. Install dependencies:
   ```bash
   npm install
   ```

4. Create the environment file:
   ```bash
   cp .env.example .env
   ```

5. Edit `.env` with the values from the previous steps:
   ```
   DISCORD_BOT_TOKEN=your-bot-token-from-step-1
   DISCORD_CHANNEL_ID=your-channel-id-from-step-3
   WP_ENDPOINT_URL=https://yoursite.com/wp-json/ssc/v1/discord/incoming
   WP_WEBHOOK_SECRET=the-secret-shown-in-wordpress-settings
   ```

6. Restrict permissions on the `.env` file so only the bot process owner can read it:
   ```bash
   chmod 600 /opt/ssc-discord-bot/.env
   ```

7. Start the bot:
   ```bash
   npm start
   ```

   You should see a message confirming the bot has connected to Discord.

## Step 6: Keep the Bot Running (Production)

For production use, you'll want the bot to run persistently. Here are some options:

### Using PM2 (recommended)

```bash
npm install -g pm2
cd /opt/ssc-discord-bot
pm2 start discord-bot.js --name ssc-discord
pm2 save
pm2 startup  # follow the instructions to auto-start on reboot
```

### Using systemd

Create `/etc/systemd/system/ssc-discord-bot.service`:

```ini
[Unit]
Description=Super Speedy Chat Discord Bot
After=network.target

[Service]
Type=simple
User=www-data
WorkingDirectory=/opt/ssc-discord-bot
ExecStart=/usr/bin/node discord-bot.js
Restart=on-failure
RestartSec=5
EnvironmentFile=/opt/ssc-discord-bot/.env

[Install]
WantedBy=multi-user.target
```

Then:
```bash
sudo systemctl enable ssc-discord-bot
sudo systemctl start ssc-discord-bot
```

---

## How Conversations Appear in Discord

- When a visitor starts a new conversation, a **thread** is created in your configured channel with the visitor's name and page URL
- Each visitor message appears in the thread immediately
- Any reply you type in the Discord thread is relayed back to WordPress and appears in the visitor's chat bubble
- Thread names include the visitor's display name for easy identification

## Troubleshooting

### "Test Discord Connection" fails
- Verify the bot token is correct (reset it in the Developer Portal if unsure)
- Check that the bot has been invited to your server (Step 2)

### Messages not appearing in Discord
- Confirm **Enable Discord** is checked in settings
- Check that the bot token and channel ID are saved correctly
- Verify the bot has permissions to send messages and create threads in the target channel

### Discord replies not reaching WordPress
- Make sure the companion bot is running (`pm2 status` or `systemctl status ssc-discord-bot`)
- Check the bot's console output for errors
- Verify the `WP_ENDPOINT_URL` in `.env` is correct and publicly accessible
- Verify the `WP_WEBHOOK_SECRET` in `.env` matches the secret shown in WordPress settings
- If your site is behind a firewall or basic auth, the bot won't be able to reach the endpoint

### Bot starts but doesn't relay messages
- Ensure **Message Content Intent** is enabled in the Discord Developer Portal (Step 1.5)
- Make sure the bot has **Read Message History** and **View Channels** permissions in the target channel
- Check that messages are being sent in threads within the correct channel (not in the channel itself)

### Thread not created for a conversation
- The thread is created when the first visitor message is sent after Discord is enabled
- Check the PHP error log for any Discord API errors
- Verify the bot has **Create Public Threads** permission in the channel
