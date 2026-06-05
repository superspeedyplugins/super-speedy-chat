# Team Roles and Permissions

Who on your team can see and answer chats, and what your options are if you want support staff replying without handing out full admin accounts.

## The short version

Replying from wp-admin requires an **Administrator** account. Every part of the chat admin — the Chats inbox, the conversation view, replying, closing, assigning, canned responses — is gated on the `manage_options` capability, which only administrators have by default.

If you want team members to answer chats *without* making them WordPress admins, use the **Discord integration**: anyone in your Discord channel can reply to visitors from Discord with no WordPress account at all.

## What requires an administrator

| Action | Requirement |
|---|---|
| See the **Super Speedy Chat** menu and Chats inbox | `manage_options` (Administrator) |
| View a conversation and reply | `manage_options` |
| Close or assign conversations | `manage_options` |
| Manage canned responses | `manage_options` |
| Change plugin settings | `manage_options` |
| Reply from **Discord** | Discord channel access only — no WordPress account needed |

Assignment is also admin-scoped: the **Assign** dropdown in the conversation view lists users with the Administrator role.

## Setting up a support team

**Option 1 — Discord (recommended for non-admin staff).** Connect the Discord integration and invite your support staff to the Discord channel. Each new conversation opens its own thread; staff reply from Discord (desktop or phone) and replies are relayed to the visitor in real time. Visitors see the replier's name per your Display Names settings. See the Discord Integration Setup Guide.

**Option 2 — Administrator accounts.** If your support staff are trusted with full site admin, give them Administrator accounts and they get the complete wp-admin chat experience: shared inbox, filters, assignment, canned responses. Each admin can set a personal chat display name, or you can use one shared name like "Support" — see the Display Names guide.

**Not currently supported:** a dedicated "chat agent" role or capability that unlocks only the chat inbox. Granting `manage_options` to a custom role would work, but it is equivalent to making them an administrator — don't do that for staff you wouldn't trust with the whole site. If a dedicated chat-agent capability matters for your setup, tell us — it's on the radar for a future version.

## Display names

Whoever replies, you control what the visitor sees:

- **Shared mode** — every reply appears under one name, e.g. "Support".
- **Individual mode** — each admin sets their own chat name in their profile.

Discord replies show the Discord author's name. Full details in the Display Names guide.

## Visitors and login

By default visitors chat anonymously — no account needed. On the **Behaviour** tab you can prompt visitors to log in after a few messages (to save their chat history to their account), or tick **Require Login** so only logged-in users can chat at all. Logged-in visitors' conversations are linked to their account automatically. See Behaviour and Session Settings.
