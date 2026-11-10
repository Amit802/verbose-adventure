// End-to-end: upload a CSV on Member Access, check the preview, confirm, and check the result.
// Needs: php -S 127.0.0.1:8093 dev/harness/admin-router.php
const { chromium } = require('playwright');
const fs = require('fs');
const B = 'http://127.0.0.1:8093';
(async () => {
  fs.mkdirSync(__dirname + '/shots', { recursive: true });
  const csv = __dirname + '/shots/cancelled.csv';
  fs.writeFileSync(csv, 'Full name,Email,Last day\nMark Active,Mark@mail.com,\nPia Paused,pia@mail.com,12/31/2026\nNobody Here,nobody@mail.com,\n"Smith, John",john@mail.com,2026-11-20\nEve Ending,eve@mail.com,\nOlga Old,olga@mail.com,\nmember3,member3@mail.com,\n');
  const b = await chromium.launch(); const p = await b.newPage({ viewport: { width: 1280, height: 900 }, acceptDownloads: true });
  const errs = []; p.on('pageerror', e => errs.push(e.message)); p.on('dialog', d => d.accept());
  await p.goto(B + '/reset');
  // no file → error
  await p.evaluate(() => document.querySelector('#nab-acc-import input[type=file]').removeAttribute('required'));
  await p.click('#nab-acc-import .button-primary'); await p.waitForLoadState();
  console.log('no file:', (await p.textContent('.notice-error p')).trim());
  // wrong type
  fs.writeFileSync(__dirname + '/shots/list.xlsx', 'x');
  await p.setInputFiles('#nab-acc-import input[type=file]', __dirname + '/shots/list.xlsx');
  await p.click('#nab-acc-import .button-primary'); await p.waitForLoadState();
  console.log('xlsx:', (await p.textContent('.notice-error p')).trim());
  // real upload
  await p.setInputFiles('#nab-acc-import input[type=file]', csv);
  await p.click('#nab-acc-import .button-primary'); await p.waitForLoadState();
  console.log('preview:', (await p.textContent('#nab-acc-import .nab-acc-lead')).replace(/\s+/g, ' ').trim());
  console.log('rows:', await p.$$eval('#nab-acc-import-form tbody tr', r => r.map(x => (x.querySelector('input') ? (x.querySelector('input').checked ? '[x] ' : '[ ] ') : '    ') + x.innerText.replace(/\s+/g, ' ').trim())));
  await p.screenshot({ path: __dirname + '/shots/import-preview.png', fullPage: true });
  // download skipped rows
  const [dl] = await Promise.all([p.waitForEvent('download'), p.click('#nab-acc-import .nab-acc-lead a')]);
  await dl.saveAs(__dirname + '/shots/skipped.csv'); console.log('skipped file:', JSON.stringify(fs.readFileSync(__dirname + '/shots/skipped.csv', 'utf8')));
  // untick member3, confirm
  await p.uncheck('#nab-acc-import-form input[aria-label="Include line 8"]');
  await p.click('#nab-acc-import-form .button-primary'); await p.waitForLoadState();
  console.log('result:', (await p.textContent('.notice-success p')).trim());
  // check states
  await p.goto(B + '/admin?page=nab-member-access&s=mail.com');
  console.log('states:', await p.$$eval('.nab-acc-card:nth-of-type(n) table tbody tr', r => r.slice(0, 12).map(x => x.innerText.split('\n').slice(0, 1).concat([x.querySelector('.nab-acc-badge') ? x.querySelector('.nab-acc-badge').textContent : '']).join(' | '))));
  // the preview cannot be confirmed twice
  await p.goBack(); await p.goBack();
  // sample file
  await p.goto(B + '/admin');
  const [s] = await Promise.all([p.waitForEvent('download'), p.click('text=Download a sample file')]);
  await s.saveAs(__dirname + '/shots/sample.csv'); console.log('sample:', JSON.stringify(fs.readFileSync(__dirname + '/shots/sample.csv', 'utf8')));
  // bulk save of old members (packed field)
  await p.goto(B + '/admin');
  const old = await p.$('#nab-acc-old-form');
  if (old) { await p.click('#nab-acc-old-form .button-primary'); await p.waitForLoadState(); console.log('bulk:', (await p.textContent('.notice p')).trim()); }
  const m = await b.newPage({ viewport: { width: 390, height: 844 } });
  await m.goto(B + '/reset'); await m.setInputFiles('#nab-acc-import input[type=file]', csv); await m.click('#nab-acc-import .button-primary'); await m.waitForLoadState();
  console.log('mobile scrollWidth', await m.evaluate(() => document.documentElement.scrollWidth));
  await m.screenshot({ path: __dirname + '/shots/import-mobile.png', fullPage: true });
  console.log('ERRORS', errs); await b.close();
})();
