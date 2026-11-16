// Progressive unlock: a brand new member sees locks, opens tools step by step, and gets a bell note.
const { chromium } = require('playwright');
const fs = require('fs');
const B = 'http://127.0.0.1:8092/page/';
const META = __dirname + '/meta.json';
const seed = JSON.parse(fs.readFileSync(__dirname + '/seed-meta.json', 'utf8'));
const fresh = Object.assign({}, seed);
['nab_budget', 'nab_bills', 'nab_accounts', 'nab_networth_history', 'nab_modules_completed', 'nab_unlock_state', 'nab_auto_notifications'].forEach(k => delete fresh[k]);
const write = m => fs.writeFileSync(META, JSON.stringify(m));
const read = () => JSON.parse(fs.readFileSync(META, 'utf8'));
(async () => {
  const b = await chromium.launch();
  const p = await b.newPage({ viewport: { width: 1366, height: 900 } });
  const errs = []; p.on('pageerror', e => errs.push(e.message)); p.on('console', m => { if (m.type() === 'error' && !/Failed to load resource/.test(m.text())) errs.push(m.text()); });
  const locks = async () => p.$$eval('.nab-sidebar .nab-nav-locked', l => l.map(x => x.innerText.trim()));
  const groups = async () => p.$$eval('.nab-sidebar .nab-nav-label', l => l.map(x => x.innerText.trim()));

  write(fresh);
  await p.goto(B + 'nab-dashboard', { waitUntil: 'networkidle' });
  console.log('groups:', await groups());
  console.log('new member locks:', await locks());
  await p.goto(B + 'nab-payments', { waitUntil: 'networkidle' });
  console.log('payments page:', (await p.textContent('.lk-title')).trim(), '|', (await p.textContent('.lk-eyebrow')).trim());
  console.log('text:', (await p.textContent('.lk-text')).trim());
  console.log('journey:', await p.$$eval('.lk-steps li', l => l.map(x => x.innerText.replace(/\s+/g, ' ').trim())));
  await p.screenshot({ path: __dirname + '/shots/locked.png', fullPage: true });
  await p.goto(B + 'nab-budget', { waitUntil: 'networkidle' });
  console.log('budget open:', !(await p.$('.lk-title')));

  // Member saves a budget
  const m = read(); m.nab_budget = JSON.stringify ? seed.nab_budget : ''; write(m);
  await p.goto(B + 'nab-payments', { waitUntil: 'networkidle' });
  console.log('after budget, payments open:', !(await p.$('.lk-title')), '| locks:', await locks());
  console.log('bell note:', JSON.stringify(read().nab_auto_notifications || '').includes('Payment Calendar unlocked'));
  await p.goto(B + 'nab-networth', { waitUntil: 'networkidle' });
  console.log('networth page:', (await p.textContent('.lk-title').catch(() => 'OPEN')).trim());

  // Budget removed again: payments stays open
  const m2 = read(); delete m2.nab_budget; write(m2);
  await p.goto(B + 'nab-dashboard', { waitUntil: 'networkidle' });
  console.log('budget cleared, locks:', await locks());

  // Three lessons done: everything opens
  const m3 = read(); m3.nab_modules_completed = JSON.stringify([1, 2, 3]); write(m3);
  await p.goto(B + 'nab-loan-calculator', { waitUntil: 'networkidle' });
  console.log('3 lessons, loan calc open:', !(await p.$('.lk-title')), '| locks:', await locks());

  // Existing member with data: nothing locked
  write(seed);
  await p.goto(B + 'nab-dashboard', { waitUntil: 'networkidle' });
  console.log('existing member locks:', await locks());

  const mob = await b.newPage({ viewport: { width: 390, height: 844 } });
  write(fresh);
  await mob.goto(B + 'nab-networth', { waitUntil: 'networkidle' });
  console.log('mobile scrollWidth', await mob.evaluate(() => document.documentElement.scrollWidth));
  await mob.screenshot({ path: __dirname + '/shots/locked-mobile.png', fullPage: true });
  write(seed);
  console.log('ERRORS', errs); await b.close();
})();
