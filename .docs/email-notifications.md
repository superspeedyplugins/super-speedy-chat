# Email Notifications

Super Speedy Chat supports two types of email notifications:

- **Admin notifications** -- alerts sent to you when a visitor starts a new conversation.
- **Visitor notifications** -- alerts sent to visitors when you reply while they are away from the site.

Both are optional and configured independently under **Settings > Email**.

---

## Admin Notifications

When enabled, the configured admin email address receives an email each time a new conversation starts on your site.

### Settings (Email tab)

| Setting | Description |
|---------|-------------|
| Admin Email Notifications | Checkbox to enable or disable admin notifications. |
| Admin Email | The address that receives notifications. Defaults to the WordPress site admin email if left blank. |

---

## Visitor Notifications

When a visitor provides their email address and then leaves the site, they will receive an email whenever an admin replies to their conversation. The email contains the text of the admin's reply.

### Settings (Email tab)

| Setting | Description |
|---------|-------------|
| Visitor Email Notifications | Checkbox to enable or disable visitor notifications. |
| From Name | The sender name shown on outgoing emails. Defaults to the site name. |

---

## Email Collection from Visitors

The email collection prompt appears automatically when all of the following conditions are met:

1. The admin reply timeout fires (configurable; default is 30 seconds).
2. The timeout action is set to **"Show email prompt"** or **"Auto-reply with LLM, then show email prompt"** in the **Behaviour** tab.
3. The visitor has not already provided their email in the current conversation.

The prompt displays the message "Leave your email and we'll notify you when we reply" along with an email input field.

---

## Tips

- **Verify your site can send emails.** If notifications are not arriving, install a plugin like WP Mail SMTP to diagnose delivery issues.
- **Use the email prompt as a fallback.** When you cannot reply immediately, collecting the visitor's email ensures you can follow up later.
- **Compatible with any SMTP plugin.** Emails are sent using the built-in `wp_mail()` function, so any plugin that routes WordPress mail through an external SMTP server will work automatically.
