# Maybe In Future

Features considered but deferred — not currently planned for implementation.

## Typing Indicator

Show when admin is composing a reply.

**Why deferred:** Limited value given that admins may be replying from Discord or other external platforms where typing state can't be reliably captured. The complexity of tracking typing across multiple platforms outweighs the benefit.

## Admin Online/Offline Status Indicator

Show on the chat bubble whether an admin is currently available.

**Why deferred:** No clear mechanism to determine admin availability. Admins may be monitoring from Discord, email, or other platforms — WordPress session presence alone doesn't reflect actual availability. Would likely produce misleading status indicators.
