# NAB Member Portal

WordPress plugin for NAB Solutions (nabsolutions.ca, member site members.nabsolutions.ca). Client: Matt. All plugin code is in `nab-member-portal/`; read `nab-member-portal/DEVELOPER.md` first (folder map, rules, add-a-tool checklist, roadmap and status).

## Working on a change
1. Make the change following DEVELOPER.md (tool registry in `inc/tools.php`, shared look in `inc/ui.php`, icons only via `nab_portal_icon()`).
2. Bump the version in **both** the plugin header and `NAB_VERSION` (`nab-member-portal.php`), and note it in DEVELOPER.md.
3. Test everything with one command: `bash dev/harness/run-tests.sh` (see `dev/harness/README.md`). Add a flow there for any new tool.
4. Commit, push, then build the zip the client uploads:
   `git archive --format=zip -o <scratchpad>/nab-member-portal-vX.Y.Z.zip HEAD nab-member-portal`
   The client installs it with Plugins > Add Plugin > Upload > "Replace current with uploaded" (no delete needed; member data is in user meta and survives).

## House rules
- Member-facing text: plain, human, short. No em dashes, no emoji. Don't change the left sidebar's look.
- The user writes in Hinglish; answer in Hinglish. Messages drafted for Matt or the boss: short, chat style, English, no lists, not AI-sounding.
- Never repeat or use member/test login details that appear in screenshots.

## Business facts (from nabsolutions.ca)
$20.45/week membership, paid by PAD only, no refunds. Main products: Lending Finder Program (20+ lenders) and credit education/coaching. Website policy: cancel with 30 days' written notice, access and billing continue 30 days.

## Open items
- Portal cancel gives access until the next Friday (matches the old cancel email), but the website says 30 days. Waiting for Matt to choose; plan is a "notice period" setting.
- Matt approved a CSV upload on Users > Member Access (email, name, optional last day) to cancel members who cancelled by email or phone, with a preview before closing accounts.
- Known security issues are listed at the end of DEVELOPER.md.
