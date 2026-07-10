# Archived docs

Docs whose work has genuinely shipped. Moved here by the docs-cleanup procedure.
Newest first.

| Date archived | Original path | What shipped |
|---|---|---|
| 2026-07-10 | `.docs/2026-06-10-localhost-textarea-cannot-type.md` | Diagnosis + fix for the fresh-install bug where `sanitize_options()` rebuilt `ssc_options` from scratch and forced numeric settings to `0` (making the chat textarea `maxlength="0"`). Fix shipped in **1.10** ("settings fields could be saved as 0 instead of their correct defaults on a fresh install"); confirmed in `class-ssc-admin.php::sanitize_options()` which now seeds from `get_option('ssc_options')` and only coerces missing checkboxes on a real `ssc_option_group` form submit. |
| 2026-07-10 | `.docs/product-page-rebuild.md` | Full rebuild of the `/product/super-speedy-chat/` product page (ID 1692164): new intro, differentiators, 9-panel feature grid (AI auto-reply swapped in), doubt-answering sections, FAQ and improved short description. In-doc status records it LIVE and verified on the rendered page (published 2026-06-03), with the feature grid rebuilt as kses-safe native Gutenberg blocks. Marketing task, verified by the doc's first-hand completion record (not code). |
