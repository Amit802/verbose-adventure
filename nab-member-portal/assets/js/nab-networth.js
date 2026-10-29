/**
 * NAB Member Portal — Net Worth Calculator (v1.13.0)
 * Data: window.nabNetData (printed by templates/page-nab-networth.php).
 * Saves use the dashboard's own actions (nab_dash_account_save /
 * nab_dash_account_delete), which reply with the dashboard snapshot, so this
 * page and the dashboard's Net Worth card always agree.
 * All member-entered text is set with textContent.
 */
(function(){
'use strict';

var D = window.nabNetData;
if(!D) return;
var $ = function(id){ return document.getElementById(id); };
var TYPES = D.types || {}, TONES = D.tones || {};

/* ── Helpers ──────────────────────────────────────────── */
var cad = new Intl.NumberFormat('en-CA', { style:'currency', currency:'CAD', maximumFractionDigits:0 });
function money(v){ return cad.format(Math.round(+v || 0)); }
function signed(v){ return (v > 0 ? '+' : v < 0 ? '−' : '') + money(Math.abs(v)); }
function el(tag, cls, text){ var e = document.createElement(tag); if(cls) e.className = cls; if(text != null) e.textContent = text; return e; }
function setText(id, t){ var e = $(id); if(e) e.textContent = t; return e; }
function icon(n, s){ return typeof window.nabIcon === 'function' ? window.nabIcon(n, s) : ''; }
function tile(type, size){
  var t = TYPES[type] || {};
  var w = el('span');
  w.innerHTML = typeof window.nabTile === 'function' ? window.nabTile(t.icon || 'gem', t.tone || 'slate', size) : '';
  return w.firstChild || el('span');
}
function groupOf(type){ return (TYPES[type] || {}).group === 'debt' ? 'debt' : 'asset'; }
function fmtMonth(m, long){ var p = m.split('-'); return new Date(Date.UTC(+p[0], +p[1] - 1, 15)).toLocaleDateString('en-US', { month: long ? 'long' : 'short', year: long ? 'numeric' : undefined, timeZone:'UTC' }); }
function toneColor(type){ var c = TONES[(TYPES[type] || {}).tone]; return c ? 'linear-gradient(90deg,' + c[0] + ',' + c[1] + ')' : '#94A3B8'; }

/* ── Render ───────────────────────────────────────────── */
function render(){
  var acc = D.accounts || [], nw = D.networth || { assets:0, debts:0, net:0, history:[] };
  var has = acc.length > 0;

  // Hero
  var v = setText('nwValue', (nw.net < 0 ? '−' : '') + money(Math.abs(nw.net)));
  v.style.color = nw.net < 0 ? '#FFB4B7' : '';
  setText('nwAssets', money(nw.assets));
  setText('nwDebts', money(nw.debts));
  setText('nwRatio', nw.assets > 0 ? Math.round(100 * nw.debts / nw.assets) + '%' : (nw.debts > 0 ? 'No assets yet' : '—'));
  var tot = nw.assets + nw.debts;
  $('nwSplitA').style.width = tot ? (100 * nw.assets / tot) + '%' : '0';
  $('nwSplitD').style.width = tot ? (100 * nw.debts / tot) + '%' : '0';

  var hist = nw.history || [], ch = $('nwChange');
  ch.className = 'nw-change';
  ch.textContent = '';
  if(!has){ ch.textContent = 'Add your accounts to start'; }
  else if(hist.length >= 2){
    var last = hist[hist.length - 1], prev = hist[hist.length - 2], diff = last.v - prev.v;
    ch.classList.add(diff >= 0 ? 'up' : 'down');
    ch.innerHTML = icon(diff >= 0 ? 'trending-up' : 'trending-down', 15);
    ch.appendChild(document.createTextNode(signed(diff) + ' since ' + fmtMonth(prev.m, true)));
  } else {
    ch.textContent = hist.length ? 'Tracking since ' + fmtMonth(hist[0].m, true) : 'Saved this month';
  }

  renderInsight(acc, nw);
  renderChart(hist, has);
  renderBreakdown(acc, nw);
  renderAccounts(acc, nw);
}

function renderInsight(acc, nw){
  var box = $('nwInsight'), msg = '', ic = 'info', tone = 'blue';
  var cards = acc.filter(function(a){ return a.type === 'credit_card'; }).reduce(function(t, a){ return t + (+a.bal || 0); }, 0);
  var cash = acc.filter(function(a){ return a.type === 'cash'; }).reduce(function(t, a){ return t + (+a.bal || 0); }, 0);
  if(!acc.length){ box.hidden = true; return; }
  if(cards > 0 && cash >= cards){ msg = 'You have enough in the bank to clear your credit card balance of ' + money(cards) + '. Paying it off saves interest and can lift your credit score.'; ic = 'circle-check'; tone = 'green'; }
  else if(cards > 0){ msg = 'Credit cards make up ' + money(cards) + ' of what you owe. They usually charge the most interest, so paying them down first raises your net worth fastest.'; ic = 'triangle-alert'; tone = 'orange'; }
  else if(nw.net < 0){ msg = 'You owe more than you own right now. That is common while paying off loans, and every payment moves this number up.'; ic = 'info'; tone = 'blue'; }
  else if(nw.debts === 0){ msg = 'No debts recorded. Great work. Keep adding to savings and investments to grow your net worth.'; ic = 'circle-check'; tone = 'green'; }
  else { msg = 'Your debts are ' + Math.round(100 * nw.debts / Math.max(1, nw.assets)) + '% of what you own. Below 50% is a healthy place to be.'; ic = nw.debts / Math.max(1, nw.assets) <= .5 ? 'circle-check' : 'info'; tone = ic === 'circle-check' ? 'green' : 'blue'; }
  box.hidden = false;
  box.innerHTML = typeof window.nabTile === 'function' ? window.nabTile(ic, tone, 32) : '';
  box.appendChild(el('span', null, msg));
}

var chart = null;
function renderChart(hist, has){
  var ok = has && hist.length >= 2 && typeof window.Chart === 'function';
  $('nwChartBox').hidden = !ok;
  $('nwChartEmpty').hidden = ok || !has;
  if(!has) $('nwChartBox').parentNode.hidden = true; else $('nwChartBox').parentNode.hidden = false;
  if(!ok){ if(chart){ chart.destroy(); chart = null; } return; }
  var ctx = $('nwChart').getContext('2d');
  var grad = ctx.createLinearGradient(0, 0, 0, 240);
  grad.addColorStop(0, 'rgba(79,70,229,.28)'); grad.addColorStop(1, 'rgba(79,70,229,0)');
  var cfg = {
    type:'line',
    data:{ labels: hist.map(function(p){ return fmtMonth(p.m); }), datasets:[{ data: hist.map(function(p){ return p.v; }), borderColor:'#4F46E5', borderWidth:2.5, tension:.35, fill:true, backgroundColor:grad, pointRadius:3, pointHoverRadius:6, pointBackgroundColor:'#4F46E5' }] },
    options:{
      responsive:true, maintainAspectRatio:false, animation:{ duration:400 },
      interaction:{ mode:'index', intersect:false },
      plugins:{ legend:{ display:false }, tooltip:{ callbacks:{ title:function(i){ return fmtMonth(hist[i[0].dataIndex].m, true); }, label:function(i){ return ' Net worth: ' + money(i.raw); } } } },
      scales:{ x:{ grid:{ display:false }, ticks:{ color:'#9AA4B2' } }, y:{ grace:'10%', grid:{ color:'#F1F3F6' }, border:{ display:false }, ticks:{ color:'#9AA4B2', callback:function(v){ return money(v); }, maxTicksLimit:5 } } }
    }
  };
  if(chart){ chart.data = cfg.data; chart.update(); } else chart = new window.Chart(ctx, cfg);
}

function renderBreakdown(acc, nw){
  [ ['asset', 'nwOwn', 'nwOwnSub', nw.assets], ['debt', 'nwOwe', 'nwOweSub', nw.debts] ].forEach(function(g){
    var list = $(g[1]), byType = {};
    list.textContent = '';
    acc.filter(function(a){ return groupOf(a.type) === g[0]; }).forEach(function(a){ byType[a.type] = (byType[a.type] || 0) + (+a.bal || 0); });
    var keys = Object.keys(byType).sort(function(a, b){ return byType[b] - byType[a]; });
    setText(g[2], keys.length ? money(g[3]) + ' in total' : (g[0] === 'asset' ? 'Bank accounts, investments, property' : 'Cards, loans, mortgage'));
    if(!keys.length){ list.appendChild(el('li', 'nw-none', g[0] === 'asset' ? 'Nothing added yet.' : 'No debts added. If you have none, great!')); return; }
    keys.forEach(function(k){
      var li = el('li', 'nw-row');
      li.appendChild(tile(k, 36));
      li.appendChild(el('span', 'nw-name', (TYPES[k] || {}).label || k));
      li.appendChild(el('span', 'nw-amt', money(byType[k])));
      var tr = el('div', 'nw-track'), f = el('span');
      f.style.width = (g[3] ? Math.max(2, 100 * byType[k] / g[3]) : 0) + '%';
      f.style.background = toneColor(k);
      tr.appendChild(f);
      li.appendChild(tr);
      list.appendChild(li);
    });
  });
}

function renderAccounts(acc, nw){
  var box = $('nwAccounts');
  box.textContent = '';
  $('nwEmpty').hidden = acc.length > 0;
  setText('nwAccSub', acc.length ? acc.length + (acc.length === 1 ? ' account' : ' accounts') + ' · update balances once a month' : 'Everything you own and owe, in one list');
  [ ['asset', 'What you own', nw.assets], ['debt', 'What you owe', nw.debts] ].forEach(function(g){
    var items = acc.filter(function(a){ return groupOf(a.type) === g[0]; }).sort(function(a, b){ return b.bal - a.bal; });
    if(!items.length) return;
    var h = el('div', 'nw-group'); h.appendChild(el('span', null, g[1])); h.appendChild(el('span', null, money(g[2])));
    box.appendChild(h);
    items.forEach(function(a){
      var row = el('div', 'nw-acc');
      row.appendChild(tile(a.type, 38));
      var mid = el('div');
      mid.appendChild(el('div', 'nw-name', a.name));
      mid.appendChild(el('div', 'nw-meta', (TYPES[a.type] || {}).label || ''));
      row.appendChild(mid);
      row.appendChild(el('div', 'nw-amt' + (g[0] === 'debt' ? ' nw-debt' : ''), (g[0] === 'debt' ? '−' : '') + money(a.bal)));
      var b = el('button', 'nw-edit');
      b.type = 'button';
      b.innerHTML = icon('pencil', 14);
      b.appendChild(document.createTextNode('Update'));
      b.setAttribute('data-nw-edit', a.id);
      b.setAttribute('aria-label', 'Update ' + a.name);
      row.appendChild(b);
      box.appendChild(row);
    });
  });
}

/* ── Server ───────────────────────────────────────────── */
function post(params){
  if(typeof nabPortal === 'undefined') return Promise.resolve({ success:false, data:{ message:'Session error. Please refresh the page.' } });
  params.append('nonce', nabPortal.nonce);
  return fetch(nabPortal.ajax, { method:'POST', credentials:'same-origin', body:params })
    .then(function(r){ return r.text(); })
    .then(function(t){
      try { return JSON.parse(t); }
      catch(e){ return { success:false, data:{ message: t.trim() === '-1' ? 'Your session expired. Please refresh the page.' : 'Something went wrong. Please try again.' } }; }
    })
    .catch(function(){ return { success:false, data:{ message:'Connection error. Please check your internet and try again.' } }; });
}
function apply(dash){ if(dash){ D.accounts = dash.accounts || []; D.networth = dash.networth || D.networth; } }

/* ── Form ─────────────────────────────────────────────── */
var card = $('nwFormCard'), form = $('nwForm'), closeTimer = null;
function F(n){ return form.elements[n]; }
function msg(t, ok){ var m = form.querySelector('.nw-msg'); m.textContent = t || ''; m.className = 'nw-msg' + (t ? (ok ? ' ok' : ' err') : ''); }
function hint(){
  setText('nwTypeHint', groupOf(F('type').value) === 'debt' ? 'Enter how much you still owe. It counts against your net worth.' : 'Enter what it is worth today. It adds to your net worth.');
}
F('type').addEventListener('change', hint);

function openForm(id, preset){
  clearTimeout(closeTimer);
  var a = (D.accounts || []).filter(function(x){ return x.id === id; })[0] || null;
  msg('');
  F('id').value = a ? a.id : '';
  F('name').value = a ? a.name : (preset && preset.name) || '';
  F('type').value = a ? a.type : (preset && preset.type) || 'cash';
  F('balance').value = a && +a.bal ? a.bal : '';
  setText('nwFormTitle', a ? 'Update ' + a.name : 'Add an account');
  form.querySelector('[data-nw-delete]').hidden = !a;
  hint();
  card.hidden = false;
  try { (a || preset ? F('balance') : F('name')).focus({ preventScroll:true }); } catch(e){}
  card.scrollIntoView({ behavior:'smooth', block:'start' });
}
function closeForm(scrollId){ card.hidden = true; if(scrollId) $(scrollId).scrollIntoView({ behavior:'smooth', block:'start' }); }

form.addEventListener('submit', function(e){
  e.preventDefault();
  if(!F('name').value.trim()){ msg('Please give the account a name.', false); F('name').focus(); return; }
  if(F('balance').value === '' || +F('balance').value < 0){ msg('Please enter the balance (0 or more).', false); F('balance').focus(); return; }
  var btn = form.querySelector('button[type=submit]'), label = btn.textContent;
  btn.disabled = true; btn.textContent = 'Saving…';
  var p = new URLSearchParams();
  p.append('action', 'nab_dash_account_save');
  p.append('id', F('id').value);
  p.append('name', F('name').value.trim());
  p.append('type', F('type').value);
  p.append('balance', F('balance').value);
  post(p).then(function(d){
    btn.disabled = false; btn.textContent = label;
    if(d && d.success){
      apply(d.data.dash); render();
      msg('✓ ' + (d.data.message || 'Saved.'), true);
      clearTimeout(closeTimer);
      closeTimer = setTimeout(function(){ closeForm('nwAccountsCard'); }, 700);
    } else msg((d && d.data && d.data.message) || 'Could not save. Please try again.', false);
  });
});

function del(){
  var id = F('id').value;
  if(!id || !window.confirm('Delete this account?')) return;
  var btn = form.querySelector('[data-nw-delete]');
  btn.disabled = true;
  var p = new URLSearchParams();
  p.append('action', 'nab_dash_account_delete');
  p.append('id', id);
  post(p).then(function(d){
    btn.disabled = false;
    if(d && d.success){ apply(d.data.dash); render(); closeForm('nwAccountsCard'); }
    else msg((d && d.data && d.data.message) || 'Could not delete. Please try again.', false);
  });
}

document.addEventListener('click', function(e){
  var t = e.target;
  var ed = t.closest('[data-nw-edit]');
  if(ed){ openForm(ed.getAttribute('data-nw-edit')); return; }
  var st = t.closest('[data-nw-start]');
  if(st){ openForm(null, { type: st.getAttribute('data-nw-start'), name: st.getAttribute('data-nw-name') }); return; }
  if(t.closest('[data-nw-add]')){ openForm(null); return; }
  if(t.closest('[data-nw-close]')){ closeForm(); return; }
  if(t.closest('[data-nw-delete]')){ del(); }
});

render();
})();
