// Card Rewards Checker: add cards, pick stores, check the maths, save and reload.
const { chromium } = require('playwright');
const U = 'http://127.0.0.1:8092/page/nab-card-rewards';
(async () => {
  const b = await chromium.launch();
  const p = await b.newPage({ viewport: { width: 1366, height: 900 } });
  const errs = []; p.on('pageerror', e => errs.push(e.message)); p.on('console', m => { if (m.type() === 'error' && !/Failed to load resource/.test(m.text())) errs.push(m.text()); });
  await p.goto(U, { waitUntil: 'networkidle' });
  const read = async () => p.evaluate(() => ({
    hero: crHero.innerText.replace(/\s+/g, ' ').trim(),
    rank: [...document.querySelectorAll('#crRank .cr-row')].map(r => r.querySelector('.cr-row-name').textContent + ' ' + r.querySelector('.cr-row-v').textContent),
    moreTitle: crMoreTitle.textContent,
    more: [...document.querySelectorAll('#crMore > li')].map(r => r.innerText.replace(/\s+/g, ' ').slice(0, 110)),
    err: crErr.textContent,
  }));
  const store = async (name, amount) => { await p.fill('#crStore', ''); await p.click(`#crPopular [data-store="${name}"]`).catch(async () => { await p.fill('#crStore', name); await p.keyboard.press('ArrowDown'); await p.keyboard.press('Enter'); }); if (amount) await p.fill('#crAmount', String(amount)); await p.waitForTimeout(250); };
  console.log('empty', await read());

  await p.click('#crAddBtn');
  for (const q of ['TD Cash Back Visa', 'Cobalt', 'PC World Elite', 'Tangerine']) {
    await p.fill('#crCardSearch', q); await p.waitForTimeout(100);
    await p.click('#crList .cr-opt >> nth=0 >> button');
  }
  // Tangerine: choose groceries as a 2% category
  await p.click('.cr-own:has-text("Tangerine") .cr-chip:has-text("Groceries")');
  // A card that is not on the list
  await p.fill('#crCustom [name=name]', 'Home Trust Secured Visa'); await p.fill('#crCustom [name=rate]', '0'); await p.click('#crCustom button[type=submit]');
  await p.waitForTimeout(900);
  console.log('saved:', await p.textContent('#crSaved'), '| my cards:', await p.$$eval('.cr-own .cr-own-name', a => a.map(x => x.textContent)));

  await p.fill('#crStore', 'loblaws'); await p.waitForTimeout(150);
  console.log('suggest', await p.$$eval('#crSuggest button', a => a.map(x => x.textContent)));
  await p.keyboard.press('ArrowDown'); await p.keyboard.press('Enter'); await p.fill('#crAmount', '100'); await p.waitForTimeout(250);
  console.log('Loblaws $100', await read());
  await p.screenshot({ path: __dirname + '/shots/cr-desk.png', fullPage: true });

  await store('Costco', 100); console.log('Costco $100', await read());
  await p.fill('#crStore', 'Sobeys'); await p.waitForTimeout(250); console.log('Sobeys $100', await read());
  await store('Shoppers Drug Mart', 50); console.log('Shoppers $50', await read());
  await store('Walmart', 80); console.log('Walmart $80', await read());
  await p.fill('#crStore', 'My corner bakery'); await p.selectOption('#crCat', 'dining'); await p.waitForTimeout(250); console.log('unlisted dining', (await read()).hero);
  await p.fill('#crAmount', '0'); await p.waitForTimeout(200); console.log('bad amount:', (await read()).err);
  await p.fill('#crAmount', '100');

  // Reload: cards and the Tangerine choice come back from the account
  await p.goto(U, { waitUntil: 'networkidle' });
  console.log('after reload', await p.$$eval('.cr-own .cr-own-name', a => a.map(x => x.textContent)), '| tangerine picks', await p.$$eval('.cr-own:has-text("Tangerine") .cr-chip[aria-pressed=true]', a => a.map(x => x.textContent)));
  await p.click('.cr-own:has-text("Cobalt") .cr-x'); await p.waitForTimeout(900);
  await p.goto(U, { waitUntil: 'networkidle' });
  console.log('after remove', await p.$$eval('.cr-own .cr-own-name', a => a.map(x => x.textContent)));
  console.log('sidebar active:', await p.evaluate(() => { const a = document.querySelector('.nab-sidebar .active, .nab-nav-item.active, [aria-current="page"]'); return a ? a.textContent.trim() : 'none'; }));

  const m = await b.newPage({ viewport: { width: 390, height: 844 }, deviceScaleFactor: 2 });
  m.on('pageerror', e => errs.push('mobile: ' + e.message));
  await m.goto(U, { waitUntil: 'networkidle' });
  await m.click('#crPopular [data-store="Loblaws"]'); await m.waitForTimeout(250);
  console.log('mobile scrollWidth', await m.evaluate(() => document.documentElement.scrollWidth));
  await m.screenshot({ path: __dirname + '/shots/cr-mob.png', fullPage: true });
  console.log('ERRORS', errs);
  await b.close();
})();
