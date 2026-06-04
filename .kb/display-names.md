# Display Names

The **Display Names** tab controls what visitors see your support agents called in the chat. You can present a single shared name (like "Support") or let each admin use their own name.

## Display Name Mode

Two modes, chosen with a radio button:

- **All admins share one display name** *(default)* — every admin reply appears under the same name, regardless of which admin actually sent it. Good for a unified, branded support voice ("Support", "The Acme Team").
- **Each admin chooses their own display name** — replies appear under each admin's individual chat name, so visitors see who they're talking to.

## Shared Display Name

Used when the mode is **shared**. This is the single name shown to visitors for all admin replies. Default is **"Support"**. Set it to whatever fits your brand — "Support", "Acme Help", "Concierge", etc.

This name is also used as the sender name for **bot / auto-reply** messages, so it's the name visitors associate with the chat overall.

## Your Chat Display Name

Used when the mode is **individual**. Each admin sets their **own** chat name here, and it's saved per-user (in user meta) — so every admin configures their own. If an admin hasn't set one, their WordPress profile display name is used as a fallback.

Because it's per-user, each admin should open the Display Names tab themselves and set the name they want visitors to see.

## How it appears to visitors

In the chat window, each non-visitor message shows a small sender label above the text:

- **Shared mode** → always the Shared Display Name.
- **Individual mode** → the replying admin's chat name (or their profile display name as fallback).

Visitors never see WordPress usernames or email addresses — only the display name you configure here.

## Interaction with Discord

If you reply from **Discord** (via the companion bot) rather than wp-admin, the name shown to the visitor comes from your **Discord** display name, not the Display Names setting — because the reply originates in Discord. The Display Names modes above govern replies sent from the WordPress admin. Keep that in mind if you want a consistent agent identity across both: align your Discord nickname with your chosen chat name.

## Quick recommendations

- Running a brand-first support desk? Use **shared** mode with a clean name like "Support".
- Want a personal touch or a multi-agent team where visitors should know who's helping? Use **individual** mode and have each agent set their name.
- Either way, the **Shared Display Name** still matters because it names your auto-reply/bot messages.
