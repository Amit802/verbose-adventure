const { chromium } = require('playwright');
(async () => {
  const b = await chromium.launch(); const p = await b.newPage({ viewport: { width: 1366, height: 900 } });
  const errs = []; p.on('pageerror', e => errs.push(e.message)); p.on('dialog', d => d.accept());
  await p.goto('http://127.0.0.1:8092/page/nab-dashboard', { waitUntil: 'networkidle' });
  await p.click('.nab-tab-btn[data-tab="profile"]'); await p.waitForTimeout(300);
  await p.click('[data-show="nabCancelForm"]'); await p.fill('#nabCancelReason', 'Testing');
  await p.click('[data-mp-action="cancel"]'); await p.waitForTimeout(800);
  console.log('msg:', await p.textContent('#nabMpMsg'));
  await p.reload({ waitUntil: 'networkidle' }); await p.click('.nab-tab-btn[data-tab="profile"]'); await p.waitForTimeout(300);
  console.log('after reload note:', await p.evaluate(() => { const t = document.body.innerText; const i = t.indexOf('Your last day'); return t.slice(i - 10, i + 60); }));
  console.log('cancel button still there:', !!(await p.$('[data-mp-action="cancel"]')));
  const card = await p.$('#nabTab-profile'); if (card) await card.screenshot({ path: __dirname + '/shots/cancelled-profile.png' });
  console.log('errors', errs); await b.close();
})();
