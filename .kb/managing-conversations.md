# Managing Conversations

This guide covers the admin conversation management interface in Super Speedy Chat, accessible from the WordPress admin dashboard.

## The Chats Tab

The Chats tab is the default view when you open Super Speedy Chat in wp-admin.

### Stats Bar

At the top of the page, a stats bar displays three counts:

- **Active** -- Conversations where an admin has replied and the conversation is ongoing.
- **Waiting for Reply** -- Conversations where a visitor is waiting for an admin response.
- **Total** -- All conversations across every status.

### Filtering Conversations

Use the filter buttons to narrow the conversation list:

- **All** -- Shows every conversation regardless of status.
- **Active** -- Shows only ongoing conversations with admin replies.
- **Waiting** -- Shows only conversations awaiting an admin reply.
- **Closed** -- Shows only conversations that have been manually closed.

An **Assignee filter dropdown** lets you further refine the list:

- **All Assignees** -- No assignee filtering applied.
- **Unassigned** -- Shows conversations not yet assigned to any admin.
- **Assigned to Me** -- Shows only conversations assigned to the current logged-in admin.

A **search box** allows searching by visitor name or email address.

### Conversation Table

Each row in the table displays:

| Column | Description |
|---|---|
| Visitor | Visitor name and email address |
| Last Message | A preview of the most recent message |
| Status | A badge showing Active, Waiting, or Closed |
| Started | The date and time the conversation began |
| Last Activity | The date and time of the most recent message |
| Assigned | The admin user assigned to the conversation |
| Actions | A "View" button to open the full conversation |

### Auto-Refresh and Notifications

The conversation list auto-refreshes every 10 seconds. When new conversations appear with a "Waiting" status, a sound notification plays to alert admins.

---

## Conversation Detail View

Click the **View** button on any conversation row to open the full detail view.

### Message Thread

The main panel displays the full message history. Each message shows:

- Timestamp
- Sender name
- A message type badge indicating the source: **visitor**, **admin**, **bot**, or **system**

### Replying

A reply textarea sits at the bottom of the message thread. Type your response and either click **Send** or press **Ctrl+Enter** (or **Cmd+Enter** on macOS) to submit it.

### Visitor Info Sidebar

The right sidebar shows details about the visitor:

- **Name**
- **Email**
- **IP Address**
- **Referrer** -- The page or site the visitor came from
- **User Agent** -- The visitor's browser and OS
- **Page URL** -- The page where the chat was initiated
- **Started At** -- When the conversation began

### Assignment

Use the **Assigned To** dropdown in the sidebar to assign the conversation to any admin user registered on the site.

### Closing a Conversation

Click the **Close Conversation** button in the sidebar to end the conversation and set its status to Closed.

### Auto-Refresh and Notifications

The detail view polls for new messages every 3 seconds. A sound notification plays when a new visitor message arrives.

---

## Assigning Conversations

There are two ways to manage assignment:

1. **From the conversation detail view** -- Use the "Assigned To" dropdown in the sidebar to assign the conversation to a specific admin.
2. **From the conversation list** -- Use the "Assignee" filter dropdown to show only conversations assigned to you, unassigned conversations, or all conversations. This helps admins focus on their own workload or pick up unassigned chats.

---

## Conversation Statuses

| Status | Meaning |
|---|---|
| **Active** | An admin has replied and the conversation is ongoing. |
| **Waiting** | A visitor has sent a message but no admin has replied yet. |
| **Closed** | The conversation was manually closed by an admin. |
| **Archived** | Reserved for future use. |

---

## Saving Canned Responses

While viewing a conversation, you can save any admin message as a canned response for future LLM auto-replies.

To save a canned response:

1. Open a conversation detail view.
2. Find an admin message you want to reuse.
3. Click the **star icon** on that message.

The message content is saved as a canned response that the LLM can draw from when generating automatic replies to visitors.
