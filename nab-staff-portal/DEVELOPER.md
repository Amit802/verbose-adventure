# NAB Staff Portal

Private workspace for the NAB Customer Care team, at members.nabsolutions.ca/staff/.
Built by Amit. Runs next to the NAB Member Portal plugin on the same site.

## Status
v1.0.0: everything works with real data. v0.5 was the design only.

Screens: Home (shortcuts, recent documents, team topics), Reply Assistant (paste an email, get a reply based on our policies), Member Lookup, Documents, Team Discussions (topics with votes and comments).

Tickets stay in Freshdesk (decided with the client). The sidebar links to Freshdesk, and Member Lookup shows the member's Freshdesk tickets (read only, same API key as the member portal, cached 5 minutes). A ticket system inside this portal was designed in v0.3 to v0.4 and parked; see git history if it comes back.

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
| `inc/store.php` | Private post types `nab_staff_doc` and `nab_staff_topic`, the private upload folder, date and name helpers |
| `inc/documents.php` | Upload (PDF, .docx, .txt, max 10 MB, or pasted text), text extraction, edit text, switch on/off, delete, download |
| `inc/discussions.php` | Topics: create, vote, comment, status (author or manager), delete |
| `inc/members.php` | Member search, profile (reads member portal data), Freshdesk tickets, resend login email, short brief for the assistant |
| `inc/assistant.php` | Hides card/SIN/bank numbers, picks document text, calls OpenAI, cleans the reply (no em dashes or emoji), tones |
| `inc/ajax.php` | One endpoint `admin-ajax.php?action=nab_staff&do=<task>`: nonce, then permission per task (`nab_staff_ajax_tasks()`), then the handler |
| `templates/layout.php` | Sidebar, top bar, loads the current screen |
| `templates/screens/*.php` | One file per screen |
| `assets/css/staff.css`, `assets/js/staff.js` | Look and behaviour |

## Adding a screen
1. Add an entry to `nab_staff_screens()` in `inc/screens.php`.
2. Create `templates/screens/{file}.php`.
That's it: it shows in the sidebar, gets its own address and the permission check.

## How it works
- **Reply Assistant**: card, SIN and bank numbers are replaced before anything leaves the site; phone numbers are kept. All documents that are switched on are sent if they fit (about 24,000 characters), otherwise the parts that match the email best. OpenAI key and model come from the member portal (`nab_portal_ai_config()`, same as Nabby). Limit: 120 AI requests per person per hour. If no document covers the question, the reply says the team will check and a warning shows.
- **Member Lookup**: only members are found (admins and staff are left out). Reads `nab_access_state()`, score history, dispute letters, Collection Assistance requests and `nab_last_login` from the member portal. "Resend login email" uses the member portal's welcome email, once per 10 minutes per member. Managers also get a link to Member Access.
- **Documents**: files go to `uploads/nab-staff-docs/` with a random name and are blocked from direct access; downloads go through a nonce-checked AJAX link. The extracted text is what the assistant reads, so check PDFs with "Edit text".
- **Discussions**: the author votes for their own topic. An open topic becomes "Being discussed" when someone else comments.
- Every member portal function is checked with `function_exists`, so the staff portal still loads if the member portal is off (those parts then show empty).

## Testing
`bash dev/harness/run-tests.sh` runs the staff checks too (`dev/harness/staff/`: a stand-in WordPress with several users, fake OpenAI and Freshdesk, and `flow.js` which clicks through every screen as manager, staff, member and guest).

## Ideas for later
- Home: the most common questions of the week (needs saved replies history).
- Saved replies the team can reuse.
- Freshdesk: open a ticket's reply straight from Member Lookup.
