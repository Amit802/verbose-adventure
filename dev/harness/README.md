# Local test harness

Renders the plugin's real templates with stand-in WordPress functions (`wp.php`), so changes can be checked without a WordPress install. One fake member; their data is `seed-meta.json` (copied to `meta.json` on first run).

```bash
bash dev/harness/run-tests.sh
```

It lints every PHP/JS file, runs the member-access unit tests, renders all portal pages, and runs the Playwright flows (dashboard, budget, payments, simulator, loan calculator, member cancel, dispute center). Screenshots land in `dev/harness/shots/` (ignored by git).

Manual browsing: `php -S 127.0.0.1:8092 dev/harness/router.php`, then open `http://127.0.0.1:8092/page/<template-key>` (keys are in `nab_portal_tools()`).

When a new template or WordPress function is used, add a stub to `wp.php` and the page key to `run-tests.sh`.
