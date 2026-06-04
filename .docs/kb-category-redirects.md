# KB Category Move — URL Redirects

When the Super Speedy Chat KB taxonomy is consolidated onto the four standard terms (**Getting Started, Features, Advanced, Developers**), five already-published articles move to a different category. Because KB permalinks are `/kb/super-speedy-chat/<category>/<slug>/`, the category slug in the URL changes, so each needs a redirect from its old URL to its new one.

These five live posts are **not being re-published or re-created** — only their `kb_category` assignment changes (done in wp-admin) and these redirects added. The other published articles (`quick-start-guide`, `managing-conversations`, `discord-integration-setup-guide`, `what-is-super-speedy-chat`) stay in **Getting Started** and need no redirect.

## Redirects to create (301 permanent)

| # | Old URL | New URL |
|---|---|---|
| 1 | `https://www.superspeedyplugins.com/kb/super-speedy-chat/configuration/customizing-appearance/` | `https://www.superspeedyplugins.com/kb/super-speedy-chat/getting-started/customizing-appearance/` |
| 2 | `https://www.superspeedyplugins.com/kb/super-speedy-chat/configuration/email-notifications/` | `https://www.superspeedyplugins.com/kb/super-speedy-chat/getting-started/email-notifications/` |
| 3 | `https://www.superspeedyplugins.com/kb/super-speedy-chat/automation/canned-responses/` | `https://www.superspeedyplugins.com/kb/super-speedy-chat/features/canned-responses/` |
| 4 | `https://www.superspeedyplugins.com/kb/super-speedy-chat/automation/llm-auto-reply-setup/` | `https://www.superspeedyplugins.com/kb/super-speedy-chat/features/llm-auto-reply-setup/` |
| 5 | `https://www.superspeedyplugins.com/kb/super-speedy-chat/performance/ultra-ajax-performance/` | `https://www.superspeedyplugins.com/kb/super-speedy-chat/advanced/ultra-ajax-performance/` |

### As source → target path pairs (for a redirect plugin / server config)

```
/kb/super-speedy-chat/configuration/customizing-appearance/   /kb/super-speedy-chat/getting-started/customizing-appearance/
/kb/super-speedy-chat/configuration/email-notifications/       /kb/super-speedy-chat/getting-started/email-notifications/
/kb/super-speedy-chat/automation/canned-responses/            /kb/super-speedy-chat/features/canned-responses/
/kb/super-speedy-chat/automation/llm-auto-reply-setup/        /kb/super-speedy-chat/features/llm-auto-reply-setup/
/kb/super-speedy-chat/performance/ultra-ajax-performance/     /kb/super-speedy-chat/advanced/ultra-ajax-performance/
```

## Anchors / deep links

You do **not** need a redirect per in-page anchor. A page-level 301 preserves the `#fragment`, so a deep link like:

```
…/performance/ultra-ajax-performance/#plugin_deactivation
```

automatically resolves to:

```
…/advanced/ultra-ajax-performance/#plugin_deactivation
```

The article slugs and headings are unchanged by the move, so every existing anchor still exists on the destination page. For reference, the headings (anchors follow the site's `lower_case_with_underscores` convention, e.g. *Plugin Deactivation* → `#plugin_deactivation`):

- **customizing-appearance** — Available Settings; Chat Header Image; Chat Window Title; Primary Color; Header Background Color; Visitor Message Color; Bubble Position; Trigger Icon; Tips
- **email-notifications** — Admin Notifications; Visitor Notifications; Email Collection from Visitors; Tips
- **canned-responses** — What are canned responses?; Saving a canned response from a conversation; Managing canned responses; How canned responses are used; Tips
- **llm-auto-reply-setup** — Prerequisites; Step 1: Configure the LLM Provider; Step 2: Set the Timeout Action; How It Works in Practice; Cost Expectations; Troubleshooting
- **ultra-ajax-performance** — What is Ultra Ajax?; How It Works; Verifying Ultra Ajax Is Active; Polling Intervals; Enabling and Disabling; Plugin Deactivation; Rate Limiting; Troubleshooting

## Optional: old category archive pages

After the moves, three old category archive pages become empty:

```
/kb/super-speedy-chat/configuration/   → /kb/super-speedy-chat/getting-started/
/kb/super-speedy-chat/automation/      → /kb/super-speedy-chat/features/
/kb/super-speedy-chat/performance/     → /kb/super-speedy-chat/advanced/
```

If those archive URLs were ever linked or indexed, redirect them to the closest new section (above) or to the parent `/kb/super-speedy-chat/`. Once redirected and confirmed unused, the empty `Configuration`, `Automation`, and `Performance` terms can be deleted in wp-admin.
