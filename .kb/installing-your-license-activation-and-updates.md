# Installing Your License, Activation and Updates

After buying Super Speedy Chat you get a license key and a plugin `.zip`. This guide covers entering the key, confirming it's active, and how plugin updates are delivered. If you haven't installed the plugin yet, start with the Quick Start Guide.

## Where the license lives

All Super Speedy plugins share one license screen: **Super Speedy** in the wp-admin sidebar (the top-level menu with the Super Speedy logo). One license key covers all your Super Speedy plugin purchases on that site — the key is checked per-plugin against your account, so it unlocks whichever plugins your purchases include.

## Entering your key

1. In wp-admin, go to **Super Speedy** (the top-level menu, not the Super Speedy Chat submenu).
2. Scroll to the **License Key** field, paste your key, and click **Save Settings**.
3. The license table above the field lists each installed Super Speedy plugin with its license status.

Your key is in your purchase confirmation email and in your account area on superspeedyplugins.com.

## Rechecking after a renewal or upgrade

License status is cached for up to an hour. If you've just renewed, upgraded, or added sites, click the **Recheck Licenses** button at the top of the license table (there's also a smaller **Recheck now** button beside the key field). Recheck forces a fresh check against the license server, bypassing the cache — so a renewal shows as active immediately instead of up to an hour later.

## License statuses you might see

| Status | Meaning | What to do |
|---|---|---|
| **Active** | Key is valid for this plugin and domain. | Nothing — updates are enabled. |
| **License expired** | Your license term has ended. | Purchase a license extension to re-enable updates. The plugin keeps working — you just stop receiving updates. |
| **Website limit exceeded** | The key is registered on more sites than it covers. | Purchase additional licenses, or deactivate the license on a site you no longer use, then Recheck. |

## How updates work

Updates come from superspeedyplugins.com, not the WordPress.org repository:

- With a valid license, new versions appear on the normal **Dashboard > Updates** and **Plugins** screens, exactly like any other plugin — click **Update** as usual.
- The **Super Speedy** menu shows an update-count bubble when any Super Speedy plugin has a new version, and the settings page lists the changelog entries since your installed version.
- Without a valid key, the update notice will tell you to enter a license key — the new version is visible but the download requires a valid license.

## What happens when a license expires

Nothing breaks. The plugin (and your chat history) keeps working indefinitely. An expired license means no further updates — including compatibility and security updates — and no support, so renewing is recommended, but your site does not lose chat.

## Staging and development sites

The license check is per-domain. If your staging site exceeds your site allowance, you'll see **Website limit exceeded** — deactivate the license on a site you no longer need, or extend your license.

## Troubleshooting

- **Saved the key but the table still shows expired/inactive** — click **Recheck Licenses**; you were likely seeing the cached status.
- **Update button asks for a license** — confirm the key is saved under **Super Speedy** settings on *this* site (keys are entered per-site) and the domain matches the one you registered.
- **No Super Speedy menu** — the menu is created by any active Super Speedy plugin; make sure Super Speedy Chat is activated and you're logged in as an administrator.
