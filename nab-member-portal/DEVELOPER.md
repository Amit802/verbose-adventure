# NAB Member Portal — Developer Guide

How the plugin is put together, the rules every file follows, and exactly where each upcoming tool will plug in. Read this before you add or change anything.

## Requirements

- WordPress 6.0+, PHP 7.4+
- **Advanced Custom Fields (ACF)** must be active. Without it, 8 of the 16 portal pages fail with a fatal error (`get_field()` is undefined).
- Non-admin members are sent to `/dashboard/` after login. A page with that slug must exist; otherwise WordPress redirects `/dashboard/` back to `/wp-admin/` and the member gets stuck in a redirect loop.

## Folder map

| Path | What lives there |
|---|---|
| `nab-member-portal.php` | Bootstrap: constants, `require`s, activation/deactivation, small admin tweaks. Bump **both** the header `Version` and `NAB_VERSION` on every release. A version change triggers the LiteSpeed purge-all automatically. |
| `inc/tools.php` | **Tool registry.** Every page template (`nab_tools()`) and every sidebar/search entry (`nab_nav_sections()`). |
| `inc/icons.php` | The only icon set (Lucide, ISC licence). `nab_icon( 'name', $size )`. No emoji in the UI. |
| `inc/helpers.php` | Shared layout (`nab_head_open`, `nab_render_sidebar`, shared CSS/JS), nav links, roadmap, templates. |
| `inc/template-loader.php` | Maps page templates to files. Driven by the registry, so you don't need to edit it. |
| `inc/ajax-handlers.php` | Loads `nabPortal` (ajax URL + nonce) on every registered tool, plus the AJAX for the existing tools. |
| `inc/dashboard-data.php` | Dashboard chart data + AJAX, and `nab_get_member_since()`. |
| `inc/memberpress*.php` | Membership pause/cancel/resume, welcome emails, signup fields. (`nab_mp_active()` is always `false` — MemberPress is not used.) |
| `inc/notifications.php` | Admin and automatic member notifications (`nab_add_auto_notification()`). |
| `inc/acf-fields.php` | ACF field groups (settings live on the Dashboard page). |
| `templates/` | One file per portal page. |
| `assets/js/` | `nab-dashboard.js` (tabs, quiz, search, score checker), `nab-dashboard-charts.js` (charts + entry sheets), `vendor/` (Chart.js 4.5.1, MIT, bundled — no CDN). |

## Rules for every file

1. **Prefix everything `nab_`.** Functions, options, user meta keys, AJAX actions, CSS classes (`nab-` / `nd-` on the dashboard).
2. **AJAX lives in `inc/`, never in templates.** Every handler must:
   - check a nonce (`check_ajax_referer( 'nab_portal_nonce', 'nonce' )`);
   - read `get_current_user_id()` and never accept a user ID from the request;
   - sanitize input (`sanitize_text_field`, `(int)`, range checks);
   - reply with `wp_send_json_success()` / `wp_send_json_error( [ 'message' => '…' ] )`.
3. **Escape all output.** In PHP use `esc_html` / `esc_attr` / `esc_url`. In JS, put user text in with `textContent` (or the `esc()` helper), never `innerHTML`.
4. **Member data goes in user meta**, as JSON for lists (see `nab_dash_json_meta()`). Keep existing meta keys stable — other tools read them (`nab_credit_score`, `nab_card_N_*`, `nab_ef_*`, `nab_points`…).
5. **Icons:** use `nab_icon()`. To add an icon, copy the inner markup of `lucide-static/icons/<name>.svg` into `nab_icon_paths()`.
6. **Dates shown to members:** use `wp_date()` in PHP (site timezone). The join date always comes from `nab_get_member_since()`.
7. **Credentials** go in `wp-config.php` constants or ACF settings, never in code (the existing `NAB_LC_*` pattern).
8. **Release:** run `php -l` on changed files, test on a staging site, then bump the version.

## Adding a new tool (checklist)

Example: a "Loan Calculator" page.

1. **Template file** `templates/page-nab-loan-calculator.php`. Copy the structure of an existing simple tool (e.g. `page-nab-emergency-fund.php`): `nab_head_open()` → page CSS → `nab_open_body( 'loan-calc' )` → content → `nab_portal_footer_js()` → `wp_footer()`.
2. **Register the page** in `nab_tools()`:
   ```php
   'nab-loan-calculator' => [ 'name' => 'NAB Loan Calculator', 'file' => 'page-nab-loan-calculator.php' ],
   ```
   That one line makes it selectable as a page template, routes it, loads `nabPortal` (no "Session error"), and excludes it from page cache.
3. **Add it to the sidebar and search** in `nab_nav_sections()`:
   ```php
   [ 'key' => 'loan-calc', 'label' => 'Loan Calculator', 'icon' => 'circle-dollar-sign',
     'template' => 'nab-loan-calculator', 'desc' => 'Estimate payments and interest', 'keywords' => 'loan calculator payment interest' ],
   ```
   `template` links the item to the page that uses that template, so no new ACF link field is needed. `key` must match the value passed to `nab_open_body()` so the item highlights.
4. **Data + AJAX** (only if the tool saves anything): create `inc/loan-calculator.php` following the rules above, then `require_once` it in `nab-member-portal.php`.
5. **Optional:** show it on the dashboard (a card in `page-nab-dashboard.php`, data added to `nab_dash_get_chart_data()`), or add a Roadmap step (`nab_get_roadmap_steps()`).
6. **Test:** create a page with the template; open it as a normal member (not an admin) on desktop and on a phone; save something, reload, and confirm it's still there.

## Roadmap — where each planned tool plugs in

**Phase 2 — New member tools**

| # | Tool | Plan |
|---|---|---|
| 3 | Monthly Budget Planner | New tool. Reuse the expense categories from `nab_dash_expense_categories()` and the actual spending in `nab_cashflow`; store targets in `nab_budget` (`{ category: amount }`). The dashboard Spending card can then show budget vs actual. |
| 4 | Payment Calendar | New tool. `nab_bills` (name, amount, due day, repeat). Feeds #13 and #16. |
| 5 | Net Worth Calculator | **Data already exists:** `nab_accounts` + `nab_networth_history` (dashboard Net Worth card). Build a full-page view with the same AJAX actions (`nab_dash_account_*`). |
| 6 | Loan Calculator | New tool. Calculations in the browser; saving is optional. |
| 7 | Collection Assistance page | New page. Reuse the Freshdesk ticket AJAX (`nab_freshdesk_submit`) with its own ticket category. |

**Phase 3 — Structure & gamification**

| # | Item | Plan |
|---|---|---|
| 8 | Sidebar categories | Edit `nab_nav_sections()` only — rename/regroup into Loan Tools, Credit Tools, Money Management, Learning Hub, Member Support, My Progress. No template changes needed. |
| 9 | Progressive unlock | Add an `unlock` rule to nav items/tools (e.g. `[ 'level' => 'silver' ]`). Add `nab_tool_is_unlocked( $uid, $key )` in `tools.php`; the sidebar shows a lock icon, and the template redirects when the tool is locked. |
| 10 | Badges, XP, levels | **Partly exists:** `nab_points`, `nab_level`, `nab_streak` (updated in `nab_complete_module`). Move that into `inc/gamification.php` with one `nab_award_xp( $uid, $action )`, and call it from the existing saves (score logged, month added, module done). |

**Phase 4 — Additional tools**

| # | Tool | Plan |
|---|---|---|
| 11 | Debt Snowball vs Avalanche | Reuse the debts in `nab_accounts`; add interest rate and minimum payment fields to the account form. |
| 12 | RRSP/TFSA Contribution Tracker | New tool. `nab_contributions` per year; can link to `investment` accounts. |
| 13 | Grace Period Reminder | Statement/due dates on `credit_card` accounts. A daily cron sends reminders through `nab_add_auto_notification()` (and SMS once #16 exists). Note: `nab_mp_daily_check` is already scheduled daily but nothing is attached to it yet, so the reminder handler can hook onto it. |
| 14 | Credit Bureau Comparison | Content page (Equifax vs TransUnion). No data. |

**Phase 5 — Integrations**

| # | Item | Plan |
|---|---|---|
| 15 | Statistics Canada benchmark | `inc/integrations/statcan.php`. Fetch on the server, cache in a transient (daily), then compare with the member's `nab_cashflow` categories. |
| 16 | Twilio SMS | `inc/integrations/twilio.php`. Credentials as `NAB_TWILIO_*` constants in `wp-config.php`; opt-in and phone number stored per member (`nab_sms_optin`, existing `nab_phone`). Used by #4 and #13. |

**Phase 6 — Branding**

| # | Item | Plan |
|---|---|---|
| 17 | Portal-wide colours | The dashboard already uses CSS variables (`--nd-*`). Move the shared CSS in `nab_portal_head_css()` to the same variables, so the landing-page palette is a one-place change. |

## Known issues (not yet fixed — need a decision)

- The REST endpoint secret (`nab_portal_secret_2024`) is written in `inc/ajax-handlers.php`. Move it to a `wp-config.php` constant, and update the caller at the same time.
- `nab_rest_create_member` stores the new member's password in plain text (`nab_temp_password`).
- If MemberPress is ever installed, members get two welcome emails (`memberpress-account.php` and `memberpress-setup.php`).
- Large templates (dashboard, simulator, loan) still carry their own inline CSS/JS. Split them into `assets/` gradually as each tool is touched.
