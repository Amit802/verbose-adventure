const { chromium } = require('playwright');
const U = 'http://127.0.0.1:8092/page/nab-loan-calculator';
(async () => {
  const b = await chromium.launch();
  const p = await b.newPage({ viewport: { width: 1366, height: 900 } });
  const errs = []; p.on('pageerror', e => errs.push(e.message)); p.on('console', m => { if (m.type() === 'error' && !/Failed to load resource/.test(m.text())) errs.push(m.text()); });
  await p.goto(U, { waitUntil: 'networkidle' });
  const read = async () => p.evaluate(() => ({ label: lcPayLabel.textContent, pay: lcPay.textContent, int: lcInterest.textContent, total: lcTotal.textContent, count: lcCount.textContent, payoff: lcPayoff.textContent, err: lcErr.textContent, rows: document.querySelectorAll('#lcRows tr').length, ins: [...document.querySelectorAll('.lc-insight')].map(x => x.innerText.replace(/\s+/g, ' ')) }));
  console.log('car default', await read());
  await p.screenshot({ path: __dirname + '/shots/lc-desk.png', fullPage: true });
  // Known answer: 25,000 at 7.99% for 60 months = $506.91/month
  // try "Try it"
  const tryBtn = await p.$('.lc-insight button'); if (tryBtn) { await tryBtn.click(); await p.waitForTimeout(400); console.log('after try it', await read()); }
  await p.fill('[name=extra]', ''); await p.waitForTimeout(300);
  // mortgage
  await p.click('[data-lc-preset=mortgage]'); await p.waitForTimeout(400);
  console.log('mortgage', await read(), await p.textContent('#lcNote'));
  await p.selectOption('[name=freq]', 'accelerated'); await p.waitForTimeout(400);
  console.log('mortgage accel', await read());
  // personal + down payment, weekly
  await p.click('[data-lc-preset=personal]'); await p.fill('[name=down]', '2000'); await p.selectOption('[name=freq]', 'weekly'); await p.waitForTimeout(400);
  console.log('personal weekly', await read(), await p.textContent('#lcNote'));
  // 0% rate
  await p.fill('[name=rate]', '0'); await p.waitForTimeout(400); console.log('zero rate', await read());
  // errors
  await p.fill('[name=amount]', '50'); await p.waitForTimeout(400); console.log('bad amount', (await read()).err);
  await p.fill('[name=amount]', '10000'); await p.fill('[name=years]', '0'); await p.fill('[name=months]', '0'); await p.waitForTimeout(400); console.log('bad term', (await read()).err);
  await p.fill('[name=months]', '6'); await p.fill('[name=down]', '20000'); await p.waitForTimeout(400); console.log('bad down', (await read()).err);
  await p.fill('[name=down]', ''); await p.waitForTimeout(400); console.log('6 months', await read());
  // my loan chip
  await p.click('#lcMine button'); await p.waitForTimeout(400); console.log('my loan amount', await p.inputValue('[name=amount]'));
  // slider
  await p.fill('[name=amountRange]', '40000'); await p.waitForTimeout(300); console.log('slider ->', await p.inputValue('[name=amount]'));
  // sidebar active
  console.log('sidebar active:', await p.evaluate(() => { const a = document.querySelector('.nab-sidebar .active, .nab-nav-item.active, [aria-current="page"]'); return a ? a.textContent.trim() : 'none'; }));
  const m = await b.newPage({ viewport: { width: 390, height: 844 }, deviceScaleFactor: 2 });
  m.on('pageerror', e => errs.push('mobile: ' + e.message));
  await m.goto(U, { waitUntil: 'networkidle' });
  console.log('mobile scrollWidth', await m.evaluate(() => document.documentElement.scrollWidth));
  await m.screenshot({ path: __dirname + '/shots/lc-mob.png', fullPage: true });
  console.log('ERRORS', errs);
  await b.close();
})();
