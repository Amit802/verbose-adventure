// End-to-end check of the reworked Dispute Center on the local render.
const { chromium } = require('playwright');
const fs = require('fs');
const U = 'http://127.0.0.1:8092/page/nab-dispute-center';
(async () => {
  const b = await chromium.launch();
  const ctx = await b.newContext({ viewport: { width: 1366, height: 900 }, acceptDownloads: true });
  const p = await ctx.newPage();
  const errs = [];
  p.on('pageerror', e => errs.push(e.message));
  p.on('console', m => { if (m.type() === 'error' && !/Failed to load resource/.test(m.text())) errs.push(m.text()); });
  p.on('dialog', d => d.accept());
  const log = (...a) => console.log(...a);
  await p.goto(U, { waitUntil: 'networkidle' });

  // 1. old letters were migrated
  log('rows:', await p.$$eval('.dl-row', r => r.map(x => x.innerText.replace(/\s+/g, ' '))));

  // 2. new letter with two accounts and a document
  await p.click('[data-dl-new]');
  await p.click('label.dl-choice:has(input[value=late])');
  await p.click('label.dl-choice:has(input[value=equifax])');
  const card1 = (await p.$$('.dl-item'))[0];
  await (await card1.$('[data-key=creditor]')).fill('ABC Bank Visa');
  await (await card1.$('[data-key=account]')).fill('4521');
  await (await card1.$('[data-key=info]')).fill('My March 2026 payment shows as 30 days late');
  await (await card1.$('[data-key=reason]')).fill('I paid on March 10, before the due date.');
  await p.click('#dlAddItem');
  const card2 = (await p.$$('.dl-item'))[1];
  await (await card2.$('[data-key=creditor]')).fill('Rogers');
  await (await card2.$('[data-key=info]')).fill('June 2026 bill reported late');
  await p.fill('[name=street]', '12 Maple St'); await p.fill('[name=city]', 'Calgary');
  await p.selectOption('[name=province]', 'AB'); await p.fill('[name=postal]', 'T2P 1A1');
  // missing reason on purpose -> server error
  await p.setInputFiles('#dlDocs', [__dirname + '/proof.pdf', __dirname + '/proof.png']);
  log('picked:', await p.$$eval('#dlPicked li', l => l.length));
  await p.click('#dlSubmit');
  await p.waitForTimeout(500);
  log('missing reason msg:', await p.textContent('#dlMsg'));
  await (await card2.$('[data-key=reason]')).fill('I paid it on time with autopay.');
  await (await card2.$('[data-key=fix]')).fill('Show the June payment as on time');
  await p.click('#dlSubmit');
  await p.waitForSelector('#dlPreviewCard:not([hidden])', { timeout: 8000 });
  await p.waitForTimeout(400);
  log('preview:', await p.textContent('#dlPreviewTitle'), '|', await p.textContent('#dlPreviewSub'));
  log('checks:', await p.$$eval('#dlChecks li', l => l.map(x => x.textContent.trim())));
  log('files:', await p.$$eval('#dlFiles li', l => l.map(x => x.innerText.replace(/\s+/g, ' '))));
  log('timeline:', await p.$$eval('#dlTimeline li', l => l.map(x => x.className.replace('dl-tl ', '') + ':' + x.innerText.replace(/\s+/g, ' '))));
  await p.screenshot({ path: __dirname + '/shots/d2-preview.png', fullPage: true });

  // 3. download -> ready to send
  const [dl] = await Promise.all([p.waitForEvent('download'), p.click('#dlDownload')]);
  await dl.saveAs(__dirname + '/shots/d2.pdf');
  await p.waitForTimeout(900);
  log('after download status:', await p.$eval('#dlStatusSel', s => s.value));

  // 4. walk the tracker
  await p.click('#dlNext button'); await p.waitForTimeout(400); // sent
  log('status:', await p.$eval('#dlStatusSel', s => s.value), '|', await p.textContent('#dlStatusMsg'));
  await p.click('#dlNext button'); await p.waitForTimeout(400); // response
  await p.click('#dlNext button:nth-child(2)'); await p.waitForTimeout(400); // not resolved
  log('status:', await p.$eval('#dlStatusSel', s => s.value), '| next:', await p.$$eval('#dlNext button', x => x.map(b => b.textContent)));
  log('timeline:', await p.$$eval('#dlTimeline li', l => l.map(x => x.className.replace('dl-tl ', '') + ':' + x.innerText.replace(/\s+/g, ' '))));
  await p.selectOption('#dlStatusSel', 'sent'); await p.waitForTimeout(400);
  log('moved back to:', await p.$eval('#dlStatusSel', s => s.value));

  // 5. notes and a bureau response file
  await p.fill('#dlNotes', 'Called Equifax on Nov 5, ref 7788.');
  await p.click('#dlNotesSave'); await p.waitForTimeout(400);
  log('notes:', await p.textContent('#dlNotesMsg'));
  await p.selectOption('#dlFileKind', 'response');
  await p.setInputFiles('#dlFileIn', __dirname + '/proof.pdf'); await p.waitForTimeout(700);
  log('files now:', await p.$$eval('#dlFiles li', l => l.map(x => x.innerText.replace(/\s+/g, ' '))));
  const [f1] = await Promise.all([p.waitForEvent('download'), p.click('#dlFiles li a')]);
  log('file download:', f1.suggestedFilename());
  await p.click('#dlFiles li .dl-item-x'); await p.waitForTimeout(500);
  log('after delete:', await p.$$eval('#dlFiles li', l => l.length));

  // 6. reload keeps everything
  await p.reload({ waitUntil: 'networkidle' });
  await p.click('.dl-row button');
  await p.waitForTimeout(300);
  log('reload notes:', await p.inputValue('#dlNotes'), '| status:', await p.$eval('#dlStatusSel', s => s.value));

  // 7. old migrated letter opens and edits
  const rows = await p.$$('.dl-row');
  await (await rows[rows.length - 1].$('button')).click(); await p.waitForTimeout(300);
  log('old letter:', await p.textContent('#dlPreviewSub'), '| status', await p.$eval('#dlStatusSel', s => s.value));
  await p.click('[data-dl-edit]'); await p.waitForTimeout(300);
  log('edit type:', await p.$eval('input[name=type]:checked', i => i.value), 'items', (await p.$$('.dl-item')).length);

  // mobile
  const m = await ctx.newPage(); await m.setViewportSize({ width: 390, height: 844 });
  await m.goto(U, { waitUntil: 'networkidle' }); await m.click('.dl-row button'); await m.waitForTimeout(400);
  log('mobile width:', await m.evaluate(() => document.documentElement.scrollWidth));
  await m.screenshot({ path: __dirname + '/shots/d2-mobile.png', fullPage: true });
  await m.click('[data-dl-new]'); await m.waitForTimeout(300);
  await m.screenshot({ path: __dirname + '/shots/d2-mobile-form.png', fullPage: true });
  log('ERRORS:', errs);
  await b.close();
})();
