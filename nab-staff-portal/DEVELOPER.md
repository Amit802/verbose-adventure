# NAB Staff Portal

Private workspace for the NAB Customer Care team, at members.nabsolutions.ca/staff/.
Built by Amit. Runs next to the NAB Member Portal plugin on the same site.

## Status
v0.5.0 is the design with sample data. Nothing is connected yet.

Screens: Home (shortcuts, recent documents, team topics), Reply Assistant (paste an email, get a reply based on our policies), Member Lookup, Documents, Team Discussions (topics with votes and comments).

Tickets stay in Freshdesk (decided with the client). The sidebar links to Freshdesk, and Member Lookup will show the member's Freshdesk tickets. A ticket system inside this portal was designed in v0.3 to v0.4 and parked; see git history if it comes back.

## Roles
| Role | Can do |
|---|---|
| NAB Staff | Open the staff portal, use the reply assistant and member lookup, read documents |
| NAB Staff Manager | All of the above, plus upload and switch documents on or off, and the Member Access shortcut |
| Administrator | Everything |

Screens check permissions (`nab_staff_portal`, `nab_staff_manage_docs`), never role names. See `inc/roles.php`.
Staff (not admins) are sent to /staff/ after logging in, and if they open the home page or any member portal page (the member portal sends logged-in people to its dashboard). Members and logged out visitors can't open it.

## Files
| Path | What it does |
|---|---|
| `nab-staff-portal.php` | Plugin header, constants, loads `inc/` |
| `inc/roles.php` | Roles, permissions, `nab_staff_can()`, login redirect |
| `inc/screens.php` | The list of screens (`nab_staff_screens()`), page addresses, icons |
| `inc/router.php` | `/staff/` and `/staff/{slug}/`, access checks, no cache, admin menu link |
| `templates/layout.php` | Sidebar, top bar, loads the current screen |
| `templates/screens/*.php` | One file per screen |
| `assets/css/staff.css`, `assets/js/staff.js` | Look and behaviour |

## Adding a screen
1. Add an entry to `nab_staff_screens()` in `inc/screens.php`.
2. Create `templates/screens/{file}.php`.
That's it: it shows in the sidebar, gets its own address and the permission check.

## Next steps (planned)
1. Reply assistant: AJAX endpoint that hides bank, card and SIN numbers, adds the member's details and the switched on documents, and asks OpenAI (same key and model as Nabby in the member portal) for a summary, tags and a reply in the chosen tone.
2. Member lookup: read the member portal's data (membership status and access dates from `inc/member-access.php`, dispute letters, collection requests, last login).
3. Documents: managers upload PDF or Word files into a private folder; the text is extracted and stored for the assistant; each document can be switched on or off.
4. Team discussions: topics, comments and votes.
5. Home: real counts and the weekly top questions.
