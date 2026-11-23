// End to end check of the Staff Portal: node flow.js (server on :8096)
const { chromium } = require('playwright');
const B = 'http://127.0.0.1:8096', F = __dirname + '/fixtures/';
let fails = 0; const ok = (c, m) => { console.log((c ? 'PASS ' : 'FAIL ') + m); if (!c) fails++; };
(async () => {
  await fetch(B + '/reset');
  const br = await chromium.launch();
  const as = async (id, to = '') => { const p = await (await br.newContext()).newPage(); p.errs = []; p.on('pageerror', e => p.errs.push(e.message)); p.on('dialog', d => d.accept()); await p.goto(`${B}/login?as=${id}&to=${to}`); return p; };

  // Manager: documents
  let m = await as(3, 'documents');
  ok(await m.locator('#docEmpty').isVisible(), 'empty documents note');
  for (const f of ['policy.txt', 'billing.docx', 'faq.pdf']) {
    await m.setInputFiles('#docFile', F + f); await m.click('#docSave');
    await m.waitForSelector(`#docTable tr[data-title*="${f.split('.')[0].slice(0,4)}"]`, { timeout: 5000 }).catch(() => {});
    await m.waitForTimeout(400);
  }
  await m.reload();
  ok(await m.locator('#docTable tbody tr').count() === 3, 'three documents uploaded');
  await m.click('#docPasteBtn'); await m.fill('#docTitle', 'Reply template'); await m.fill('#docPasteBox textarea', 'Sign off with Kind regards, NAB Solutions Customer Care.'); await m.click('#docSave'); await m.waitForTimeout(600); await m.reload();
  ok(await m.locator('#docTable tbody tr').count() === 4, 'pasted text document added');
  const row = t => m.locator(`#docTable tr[data-title*="${t}"]`);
  await row('billing').locator('[data-act=view]').click(); await m.waitForTimeout(400);
  ok((await m.inputValue('#dmText')).includes('PAD payments come out every Friday'), 'docx text extracted');
  await m.click('#dmClose');
  await row('faq').locator('[data-act=view]').click(); await m.waitForTimeout(400);
  ok((await m.inputValue('#dmText')).includes('20+ lenders'), 'pdf text extracted');
  await m.click('#dmClose');
  await row('faq').locator('[data-act=edit]').click(); await m.waitForTimeout(400);
  await m.fill('#dmText', 'Lending Finder FAQ. We work with more than 20 lenders.'); await Promise.all([m.waitForNavigation(), m.click('#dmSave')]);
  await row('faq').locator('[data-act=view]').click(); await m.waitForTimeout(400);
  ok((await m.inputValue('#dmText')).includes('more than 20 lenders'), 'edited text saved'); await m.click('#dmClose');
  await row('billing').locator('[data-act=toggle]').click(); await m.waitForTimeout(400); await m.reload();
  ok(await row('billing').locator('button.switch.off').count() === 1, 'toggle off persists');
  await row('reply').locator('[data-act=delete]').click(); await m.waitForTimeout(500); await m.reload();
  ok(await m.locator('#docTable tbody tr').count() === 3, 'document deleted');
  await m.fill('#docSearch', 'polic'); ok(await m.locator('#docTable tbody tr:visible').count() === 1, 'document search');
  const dl = await m.request.get(B + (await row('polic').locator('a.linkbtn').getAttribute('href')).replace(/&amp;/g,'&'));
  ok(dl.status() === 200 && (await dl.text()).includes('30 days'), 'download works for manager');
  await m.screenshot({ path: __dirname + '/shots/documents.png', fullPage: true });
  ok(!m.errs.length, 'no JS errors on documents ' + m.errs.join(';'));

  // Staff: documents read only, cannot call manage tasks
  let s = await as(2, 'documents');
  ok(await s.locator('#docUpload').count() === 0 && await s.locator('[data-act=delete]').count() === 0, 'staff sees no upload or delete');
  const nonce = await s.evaluate(() => NABSTAFF.nonce);
  const r = await s.request.post(B + '/wp-admin/admin-ajax.php', { form: { action: 'nab_staff', do: 'doc_delete', nonce, id: '101' } });
  ok((await r.json()).success === false, 'staff blocked from doc_delete');
  await m.reload(); ok(await m.locator('#docTable tbody tr').count() === 3, 'document still there');

  // Staff: reply assistant
  await s.goto(B + '/staff/reply');
  await s.fill('#memberEmail', 'sarah.thompson@example.com'); await s.waitForTimeout(1200);
  ok((await s.textContent('#memberCard')).includes('NAB-1364'), 'member card shows on email');
  await s.fill('#custMsg', 'Hi, I want to cancel. My card is 4111 1111 1111 1111 and SIN 123-456-789. Can I get a refund for this week? Call me at 403 555 0148.');
  await s.click('#suggest'); await s.waitForSelector('#rOut:not([hidden])', { timeout: 8000 });
  const reply = await s.inputValue('#replyText');
  ok(reply.includes('Hi Sarah') && !reply.includes('—') && !/\p{Extended_Pictographic}/u.test(reply), 'reply shown without em dash or emoji');
  ok((await s.textContent('#rTags')).includes('Cancellation'), 'tags shown');
  ok((await s.textContent('#rSources')).includes('Policy'), 'sources listed');
  const calls = (await (await fetch(B + '/plug/../state')).text().catch(()=>''));
  await s.click('#tones [data-tone=short]'); await s.waitForTimeout(800);
  ok((await s.inputValue('#replyText')).startsWith('[rewritten short]'), 'tone change rewrites');
  await s.screenshot({ path: __dirname + '/shots/reply.png', fullPage: true });
  ok(!s.errs.length, 'no JS errors on reply ' + s.errs.join(';'));

  // Staff: member lookup
  await s.goto(B + '/staff/lookup');
  await s.fill('#lookupQ', 'sam'); await s.click('#lookupForm button'); await s.waitForTimeout(700);
  ok(await s.locator('#lkResults li').count() === 2, 'two results for sam');
  await s.fill('#lookupQ', 'NAB-1364'); await s.click('#lookupForm button'); await s.waitForTimeout(1200);
  ok(await s.locator('#lkProfile').isVisible() && (await s.textContent('#pName')).includes('Sarah Thompson'), 'single result opens profile');
  ok((await s.textContent('#pDisputes')).includes('Equifax'), 'disputes listed');
  ok((await s.textContent('#pCollections')).includes('Metcredit'), 'collections listed');
  ok((await s.textContent('#pTickets')).includes('Lending Finder'), 'freshdesk tickets listed');
  ok(await s.locator('#pAccessLink').isHidden(), 'staff gets no Member Access link');
  await s.click('#pResend'); await s.waitForTimeout(600);
  ok(await s.locator('#pMsg').isVisible(), 'resend confirmation shown');
  await s.click('#pResend'); await s.waitForTimeout(600);
  ok((await s.textContent('#pMsg') + await s.textContent('#lkErr')).toLowerCase().includes('minute'), 'resend limited');
  await s.fill('#lookupQ', 'jessica'); await s.click('#lookupForm button'); await s.waitForTimeout(600);
  ok(await s.locator('#lkResults li').count() === 0, 'staff accounts are not searchable');
  await s.goto(B + '/staff/lookup?q=NAB-1364'); await s.waitForTimeout(1200);
  ok(await s.locator('#lkProfile').isVisible(), 'lookup from ?q');
  await s.screenshot({ path: __dirname + '/shots/lookup.png', fullPage: true });
  ok(!s.errs.length, 'no JS errors on lookup ' + s.errs.join(';'));

  // Discussions
  await s.goto(B + '/staff/discussions');
  ok(await s.locator('#topicsEmpty').isVisible(), 'empty topics note');
  await s.click('#newTopic'); await s.fill('#newTopicBox [name=title]', 'Saved replies for refund questions'); await s.selectOption('#newTopicBox [name=type]', 'process'); await s.fill('#newTopicBox [name=body]', 'We get the same refund question a lot.');
  await s.click('#newTopicBox [type=submit]'); await s.waitForTimeout(700);
  ok(await s.locator('#topics .topic').count() === 1, 'topic created');
  m = await as(3, 'discussions');
  const t = m.locator('#topics .topic').first();
  await t.locator('.vote, [data-act=vote]').first().click(); await m.waitForTimeout(500);
  await m.reload();
  ok((await m.locator('#topics .topic').first().textContent()).includes('2'), 'vote counted');
  const tp = m.locator('#topics .topic').first();
  await tp.locator('.topic-meta button.linkbtn').first().click();
  await tp.locator('.comments input').fill('Good idea, I will add one to Documents.');
  await tp.locator('.comments button[type=submit]').click(); await m.waitForTimeout(600);
  await m.reload();
  const tt = await m.locator('#topics .topic').first().textContent();
  ok(tt.includes('Good idea'), 'comment saved');
  ok(/discussing/i.test(tt), 'status moved to discussing');
  await m.locator('#topics .topic').first().locator('select').selectOption('planned').catch(() => {}); await m.waitForTimeout(500); await m.reload();
  ok(/planned/i.test(await m.locator('#topics .topic').first().textContent()), 'manager sets status');
  await m.screenshot({ path: __dirname + '/shots/discussions.png', fullPage: true });
  ok(!m.errs.length, 'no JS errors on discussions ' + m.errs.join(';'));

  // Home
  await m.goto(B + '/staff/'); const home = await m.textContent('main, .main, body');
  ok(home.includes('Saved replies') && home.includes('Policy'), 'home shows real topics and docs');
  await m.screenshot({ path: __dirname + '/shots/home.png', fullPage: true });

  // People: manager adds, changes role, resends, removes
  await m.goto(B + '/staff/team');
  ok(await m.locator('#teamTable tbody tr').count() === 3, 'team lists admin, staff, manager');
  ok((await m.locator('#teamTable').textContent()).includes('Mark Taylor (you)'), 'own row marked');
  const add = async (first, email, role) => { await m.fill('#teamAdd [name=first]', first); await m.fill('#teamAdd [name=last]', 'Test'); await m.fill('#teamAdd [name=email]', email); await m.selectOption('#teamAdd [name=role]', role); await m.click('#teamAdd [type=submit]'); await m.waitForTimeout(600); return m.textContent('#teamMsg'); };
  ok((await add('Priya', 'priya@nab.ca', 'nab_staff')).includes('has been added'), 'person added');
  ok(await m.locator('#teamTable tbody tr').count() === 4, 'new person in list');
  ok((await add('Sarah', 'sarah.thompson@example.com', 'nab_staff')).includes('member account'), 'member email refused');
  ok((await add('Jess', 'jessica@nab.ca', 'nab_staff')).includes('already on the team'), 'duplicate refused');
  const state = () => JSON.parse(require('fs').readFileSync(__dirname + '/state.json', 'utf8'));
  let S1 = state(); const pid = Object.keys(S1.users).find(k => S1.users[k].user_email === 'priya@nab.ca');
  ok(S1.mail.some(x => x[0] === 'priya@nab.ca' && x[1].includes('Staff Portal')), 'staff invite email sent');
  ok(S1.meta[pid] && S1.meta[pid].nab_welcome_sent == 1, 'member welcome email blocked for staff');
  const prow = m.locator(`#teamTable tr[data-id="${pid}"]`);
  await prow.locator('select').selectOption('nab_staff_manager'); await m.waitForTimeout(500);
  ok(state().users[pid].roles[0] === 'nab_staff_manager', 'role changed');
  await prow.locator('button:has-text("Resend")').click(); await m.waitForTimeout(500);
  ok((await m.textContent('#teamMsg')).includes('wait 10 minutes'), 'resend limited');
  await m.locator(`#teamTable tr[data-id="${pid}"] button:has-text("Remove")`).click(); await m.waitForTimeout(500);
  ok(await m.locator('#teamTable tbody tr').count() === 3 && state().users[pid].roles.length === 0, 'person removed');
  ok((await add('Priya', 'priya@nab.ca', 'nab_staff')).includes('has been added') && state().users[pid].roles[0] === 'nab_staff', 'removed person can be added back');
  await m.locator('#teamTable tbody tr').first().screenshot().catch(()=>{});
  await m.screenshot({ path: __dirname + '/shots/team.png', fullPage: true });
  ok(!m.errs.length, 'no JS errors on people ' + m.errs.join(';'));
  const sn = await s.evaluate(() => NABSTAFF.nonce);
  const sr = await s.request.post(B + '/wp-admin/admin-ajax.php', { form: { action: 'nab_staff', do: 'team_add', nonce: sn, first: 'X', email: 'x@nab.ca', role: 'nab_staff_manager' } });
  ok((await sr.json()).success === false, 'staff cannot add people');
  await s.goto(B + '/staff/team'); ok(!(await s.content()).includes('id="teamAdd"'), 'staff cannot open People');
  const ar = await m.request.post(B + '/wp-admin/admin-ajax.php', { form: { action: 'nab_staff', do: 'team_remove', nonce: await m.evaluate(() => NABSTAFF.nonce), id: '1' } });
  ok((await ar.json()).success === false, 'admin cannot be removed');

  // Logo from Customize > Site Identity
  const setOpt = (k, v) => { const f = __dirname + '/state.json', st = JSON.parse(require('fs').readFileSync(f, 'utf8')); st.opt = st.opt || {}; st.opt[k] = v; require('fs').writeFileSync(f, JSON.stringify(st)); };
  await m.goto(B + '/staff/'); ok(await m.locator('.brand-mark').textContent() === 'NAB', 'no logo set: NAB mark');
  setOpt('site_icon_url', '/fx/logo.svg'); await m.reload(); ok(await m.locator('.brand-mark img').count() === 1, 'site icon used when no logo');
  setOpt('theme_custom_logo', 55); await m.reload();
  ok(await m.locator('.logo-chip img').evaluate(i => i.complete && i.naturalWidth > 0), 'customizer logo shown');
  await m.screenshot({ path: __dirname + '/shots/logo.png' });

  // Member and guest are kept out
  const mem = await as(10, ''); ok(!(await mem.content()).includes('Staff Portal</span>'), 'member cannot open staff portal');
  const memAjax = await mem.request.post(B + '/wp-admin/admin-ajax.php', { form: { action: 'nab_staff', do: 'member_search', nonce: 'n-nab_staff-10', q: 'sam' } });
  ok((await memAjax.json()).success === false, 'member blocked from ajax');
  const g = await fetch(B + '/staff/', { redirect: 'manual' }); ok(g.status === 302 && g.headers.get('location').includes('/login'), 'guest sent to login');

  await br.close(); console.log(fails ? `${fails} FAILED` : 'ALL PASSED'); process.exit(fails ? 1 : 0);
})();
