// Clicks through the main features on the locally rendered portal and reports JS errors.
const { chromium } = require('playwright');
const B = 'http://127.0.0.1:8092/page/';
(async () => {
  const b = await chromium.launch();
  const ctx = await b.newContext({ viewport: { width: 1366, height: 900 } });
  const errs = [];
  const log = (...a) => console.log(...a);
  async function open(t) {
    const p = await ctx.newPage();
    p.on('pageerror', e => errs.push(t + ': ' + e.message));
    p.on('console', m => { if (m.type() === 'error' && !/Failed to load resource/.test(m.text())) errs.push(t + ': ' + m.text()); });
    p.on('dialog', d => d.accept());
    await p.goto(B + t, { waitUntil: 'networkidle' });
    await p.waitForTimeout(600);
    return p;
  }
  // Dashboard: search, tabs
  let p = await open('nab-dashboard');
  await p.keyboard.press('/');
  log('search focused by /:', await p.evaluate(() => document.activeElement && document.activeElement.id));
  await p.keyboard.type('budget');
  await p.waitForTimeout(300);
  log('search results:', await p.$$eval('.nab-search-result-item', r => r.length));
  await p.keyboard.press('Escape');
  for (const tab of ['profile', 'blog', 'diy', 'overview']) {
    const btn = await p.$(`.nab-tab-btn[data-tab="${tab}"]`);
    if (btn) { await btn.click(); await p.waitForTimeout(200); }
  }
  log('tabs ok');
  await p.screenshot({ path: __dirname + '/shots/flow-dash.png' });
  await p.close();

  // Dispute flow is covered by dispute2.js
  // Budget: open editor, fill guide, save
  p = await open('nab-budget');
  await p.click('.bp-card [data-bp-open="budget"]');
  await p.click('#bpBudgetCard [data-bp-fill="guide"]');
  await p.click('#bpBudgetForm button[type=submit]');
  await p.waitForTimeout(500);
  log('budget msg:', (await p.textContent('#bpBudgetForm .bp-msg')).trim() || '(closed)');
  await p.close();

  // Payments: add a bill
  p = await open('nab-payments');
  const add = await p.$('[data-pc-open], #pcAdd, .pc-btn-primary');
  if (add) { await add.click(); await p.waitForTimeout(200); }
  const f = await p.$('#pcForm');
  if (f) {
    await p.fill('#pcForm [name=name]', 'Phone');
    await p.fill('#pcForm [name=amount]', '60');
    await p.fill('#pcForm [name=start]', new Date().toISOString().slice(0, 10));
    await p.click('#pcForm button[type=submit]');
    await p.waitForTimeout(500);
    log('payments msg:', (await p.textContent('#pcForm .pc-msg')).trim() || '(closed)');
  }
  await p.close();

  // Simulator: run
  p = await open('nab-simulator');
  await p.fill('#simScore', '650');
  await p.selectOption('#simScenario', 'payoff').catch(() => {});
  await p.click('.sim-run-btn');
  await p.waitForTimeout(600);
  log('sim result shown:', await p.$eval('.sim-result-panel', e => getComputedStyle(e).display !== 'none').catch(() => 'n/a'));
  await p.screenshot({ path: __dirname + '/shots/flow-sim.png', fullPage: true });
  await p.close();

  // Utilization: calculate
  p = await open('nab-utilization');
  await p.click('.uc-calc-btn');
  await p.waitForTimeout(500);
  log('util result visible:', await p.evaluate(() => { const e = document.getElementById('ucPlaceholder'); return e ? getComputedStyle(e).display : 'n/a'; }));
  await p.close();

  // Card matcher: answer first question
  p = await open('nab-card-match');
  await p.click('.nab-cf-opt[data-val="650_699"]');
  await p.click('.nab-cf-next').catch(() => {});
  await p.waitForTimeout(300);
  log('card match step 2 active:', !!(await p.$('#nQ2.active')));
  await p.close();

  // Learning: open first lesson
  p = await open('nab-learning');
  const mod = await p.$('.nab-lc-mod-card, [data-lc-open]');
  if (mod) { await mod.click(); await p.waitForTimeout(500); }
  log('learning panel open:', await p.evaluate(() => { const e = document.querySelector('.nab-lc-fullpanel'); return e ? getComputedStyle(e).display : 'n/a'; }));
  await p.close();

  // Emergency fund: save
  p = await open('nab-emergency-fund');
  const efBtn = await p.$('.nab-ef-submit, #efSave, button[type=submit]');
  if (efBtn) { await efBtn.click(); await p.waitForTimeout(500); }
  log('ef ok');
  await p.close();

  // Nabby widget opens
  p = await open('nab-budget');
  const fab = await p.$('.nab-aiw-fab, #nabAiwFab, [data-aiw-open]');
  if (fab) { await fab.click(); await p.waitForTimeout(300); }
  log('nabby widget:', fab ? 'opened' : 'not found');
  await p.close();

  log('ERRORS:', errs);
  await b.close();
})();
