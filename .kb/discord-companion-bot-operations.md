# Discord Companion Bot: Running It in Production

The Discord integration is bidirectional. WordPress → Discord works on its own once you've entered your bot token and channel. The other direction — **Discord replies reaching your visitors** — needs the small companion Node.js bot (`bot/discord-bot.js`) running on a server. This guide covers deploying, updating, and maintaining it.

> For the initial Discord connection (bot token, channel ID, permissions), see the Discord Integration Setup guide. This guide picks up at "now keep the relay bot running."

## What the bot does

It logs into Discord, watches the threads under your configured channel, and when a (non-bot) human posts a reply it `POST`s that message to your site's `/wp-json/ssc/v1/discord/incoming` endpoint, authenticated with a shared secret. WordPress maps the thread back to the conversation and inserts the reply as an admin message, which the visitor sees on their next poll.

## Requirements

- **Node.js 18+** on a machine that can reach your WordPress site over HTTPS.
- The `bot/` folder from the plugin (`discord-bot.js`, `package.json`, `.env.example`).
- Your bot token, channel ID, site endpoint URL, and webhook secret (all shown on the **Discord** settings tab under "Bot Connection Info").

## Setup

```bash
cp -r wp-content/plugins/super-speedy-chat/bot /opt/ssc-bot
cd /opt/ssc-bot
npm install
cp .env.example .env
# edit .env with your values
```

`.env` keys:

```
DISCORD_BOT_TOKEN=...     # the bot token
DISCORD_CHANNEL_ID=...    # the channel whose threads to watch
WP_ENDPOINT_URL=https://example.com/wp-json/ssc/v1/discord/incoming
WP_WEBHOOK_SECRET=...     # the secret shown on the Discord settings tab
```

The bot refuses to start if any value is missing or still contains a `your_…` placeholder.

## Keeping it running

Run it under a process manager so it restarts on crash/reboot.

### PM2

```bash
npm install -g pm2
pm2 start discord-bot.js --name ssc-discord-bot
pm2 save
pm2 startup        # follow the printed instruction to enable on boot
```

Logs: `pm2 logs ssc-discord-bot`.

### systemd

```ini
# /etc/systemd/system/ssc-discord-bot.service
[Unit]
Description=Super Speedy Chat Discord relay bot
After=network.target

[Service]
WorkingDirectory=/opt/ssc-bot
ExecStart=/usr/bin/node discord-bot.js
Restart=always
RestartSec=5
User=www-data

[Install]
WantedBy=multi-user.target
```

```bash
sudo systemctl daemon-reload
sudo systemctl enable --now ssc-discord-bot
journalctl -u ssc-discord-bot -f   # logs
```

## Updating the bot

When you update the plugin, the bot files may change. Re-copy `discord-bot.js` (your `.env` is separate and untouched), reinstall deps if `package.json` changed, and restart:

```bash
cp wp-content/plugins/super-speedy-chat/bot/discord-bot.js /opt/ssc-bot/
cd /opt/ssc-bot && npm install
pm2 restart ssc-discord-bot   # or: systemctl restart ssc-discord-bot
```

## Rotating the webhook secret

The shared secret authenticates the bot to WordPress. If it's ever exposed:

1. Change/regenerate it on the WordPress side (the **Discord** settings tab shows the current secret).
2. Update `WP_WEBHOOK_SECRET` in the bot's `.env`.
3. Restart the bot.

Until both sides match, inbound relays return **401** and Discord replies won't reach visitors.

## Troubleshooting

| Symptom | Likely cause |
|---|---|
| Bot logs `WordPress returned 401` | Secret mismatch — re-sync `.env` with the settings tab. |
| Bot logs `WordPress returned 404` | The Discord thread isn't mapped to a conversation (e.g. a thread not created by the integration). |
| Replies in Discord do nothing | Bot not running, watching the wrong `DISCORD_CHANNEL_ID`, or **Message Content Intent** not enabled on the bot. |
| Bot won't start | A missing/placeholder value in `.env`, or an invalid token. |
| Visitor → Discord works but not the reverse | That's expected without the bot — the bot powers only the Discord → WordPress direction. |

## Security notes

- Treat `.env` as a secret — it holds your bot token and webhook secret. Lock down file permissions and keep it out of version control.
- Run the bot over HTTPS to your site so the secret isn't sent in clear text.
- The bot needs no inbound ports; it makes outbound connections only (to Discord and to your site).
