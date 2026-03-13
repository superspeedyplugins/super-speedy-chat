/**
 * Super Speedy Chat - Discord Relay Bot
 *
 * Listens for messages in Discord threads and instantly relays them
 * to WordPress via the authenticated REST endpoint.
 *
 * Usage:
 *   1. Copy .env.example to .env and fill in your values
 *   2. npm install
 *   3. node discord-bot.js
 *
 * For production, use PM2 or systemd to keep the bot running:
 *   pm2 start discord-bot.js --name ssc-discord-bot
 */

const { Client, GatewayIntentBits, Events } = require('discord.js');
const { readFileSync } = require('fs');
const { resolve } = require('path');

// Load .env file manually (no dotenv dependency needed).
function loadEnv() {
    const envPath = resolve(__dirname, '.env');
    let content;
    try {
        content = readFileSync(envPath, 'utf8');
    } catch (e) {
        console.error('ERROR: .env file not found. Copy .env.example to .env and fill in your values.');
        process.exit(1);
    }

    const env = {};
    for (const line of content.split('\n')) {
        const trimmed = line.trim();
        if (!trimmed || trimmed.startsWith('#')) continue;
        const eqIndex = trimmed.indexOf('=');
        if (eqIndex === -1) continue;
        const key = trimmed.slice(0, eqIndex).trim();
        const value = trimmed.slice(eqIndex + 1).trim();
        env[key] = value;
    }
    return env;
}

const config = loadEnv();

// Validate required config.
const required = ['DISCORD_BOT_TOKEN', 'DISCORD_CHANNEL_ID', 'WP_ENDPOINT_URL', 'WP_WEBHOOK_SECRET'];
for (const key of required) {
    if (!config[key] || config[key].includes('your_')) {
        console.error(`ERROR: ${key} is not configured in .env`);
        process.exit(1);
    }
}

const client = new Client({
    intents: [
        GatewayIntentBits.Guilds,
        GatewayIntentBits.GuildMessages,
        GatewayIntentBits.MessageContent,
    ],
});

client.once(Events.ClientReady, (c) => {
    console.log(`[SSC Bot] Logged in as ${c.user.tag}`);
    console.log(`[SSC Bot] Monitoring channel: ${config.DISCORD_CHANNEL_ID}`);
    console.log(`[SSC Bot] Relaying to: ${config.WP_ENDPOINT_URL}`);
});

client.on(Events.MessageCreate, async (message) => {
    // Skip bot messages (including our own).
    if (message.author.bot) return;

    // Only handle messages in threads.
    if (!message.channel.isThread()) return;

    // Only handle threads whose parent channel matches our configured channel.
    if (message.channel.parentId !== config.DISCORD_CHANNEL_ID) return;

    // Skip empty messages (attachments only, etc.).
    if (!message.content || !message.content.trim()) return;

    const threadId = message.channel.id;
    const authorName = message.member?.displayName || message.author.globalName || message.author.username;
    const messageText = message.content.trim();

    console.log(`[SSC Bot] Relaying message from ${authorName} in thread ${threadId}`);

    try {
        const response = await fetch(config.WP_ENDPOINT_URL, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-SSC-Secret': config.WP_WEBHOOK_SECRET,
            },
            body: JSON.stringify({
                thread_id: threadId,
                author_name: authorName,
                message: messageText,
                discord_user_id: message.author.id,
            }),
        });

        if (!response.ok) {
            const body = await response.text();
            console.error(`[SSC Bot] WordPress returned ${response.status}: ${body}`);
        } else {
            const data = await response.json();
            console.log(`[SSC Bot] Message relayed successfully (WP message ID: ${data.message_id})`);
        }
    } catch (err) {
        console.error(`[SSC Bot] Failed to relay message:`, err.message);
    }
});

// Handle errors gracefully.
client.on(Events.Error, (error) => {
    console.error('[SSC Bot] Client error:', error.message);
});

process.on('unhandledRejection', (error) => {
    console.error('[SSC Bot] Unhandled rejection:', error.message);
});

// Connect to Discord.
client.login(config.DISCORD_BOT_TOKEN).catch((err) => {
    console.error('[SSC Bot] Failed to login:', err.message);
    console.error('[SSC Bot] Check your DISCORD_BOT_TOKEN in .env');
    process.exit(1);
});
