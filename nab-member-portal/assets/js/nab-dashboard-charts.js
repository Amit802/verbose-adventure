/**
 * NAB Member Portal — Visual Dashboard (v1.9.0)
 * Charts (Chart.js, bundled in assets/js/vendor) + manual entry sheets.
 * Data comes from window.nabDashData (printed by page-nab-dashboard.php)
 * and every save returns a fresh copy, so the page always re-renders
 * from one consistent snapshot. All user text goes in via textContent.
 */
(function(){
'use strict';

var D = window.nabDashData;
if(!D) return;

var hasChart = typeof window.Chart !== 'undefined';
var $ = function(id){ return document.getElementById(id); };
var C = { blue:'#0D5C9B', orange:'#F97316', green:'#22A06B', red:'#E5484D', text:'#0F1B2D', muted:'#6B7685', faint:'#9AA4B2', grid:'#EEF1F5' };
var charts = {};
var scoreRange = 12;

/* ── Formatting ─────────────────────────────────────────── */
var cad = new Intl.NumberFormat('en-CA', { style:'currency', currency:'CAD', maximumFractionDigits:0 });
function money(v){ return cad.format(Math.round(v || 0)); }
function moneyShort(v){
  var a = Math.abs(v), s = v < 0 ? '-' : '';
  if(a >= 1e6) return s + '$' + (a/1e6).toFixed(1).replace(/\.0$/, '') + 'M';
  if(a >= 1e4) return s + '$' + Math.round(a/1e3) + 'k';
  return money(v);
}
function signed(v){ return (v > 0 ? '+' : v < 0 ? '−' : '') + money(Math.abs(v)); }
function parseYmd(s){ var p = s.split('-'); return new Date(+p[0], +p[1]-1, +(p[2] || 1)); }
function ymd(d){ return d.getFullYear() + '-' + ('0'+(d.getMonth()+1)).slice(-2) + '-' + ('0'+d.getDate()).slice(-2); }
function fmtDate(s, withYear){ return parseYmd(s).toLocaleDateString('en-US', withYear === false ? { month:'short', day:'numeric' } : { month:'short', day:'numeric', year:'numeric' }); }
function fmtMonth(m, long){ return parseYmd(m).toLocaleDateString('en-US', long ? { month:'long', year:'numeric' } : { month:'short' }); }
function scoreBand(s){
  if(s < 580) return 'Poor'; if(s < 670) return 'Fair'; if(s < 740) return 'Good'; if(s < 800) return 'Very good'; return 'Excellent';
}
function setText(id, t){ var el = $(id); if(el) el.textContent = t; return el; }
function setTone(el, v){ if(!el) return; el.classList.remove('nd-up','nd-down'); if(v > 0) el.classList.add('nd-up'); else if(v < 0) el.classList.add('nd-down'); }
function el(tag, cls, text){ var e = document.createElement(tag); if(cls) e.className = cls; if(text != null) e.textContent = text; return e; }
function catOf(k){ return (D.cats && D.cats[k]) || { label:k, color:'#94A3B8' }; }
function sumExp(row){ var t = 0; Object.keys(row.exp || {}).forEach(function(k){ t += +row.exp[k] || 0; }); return t; }

/* ── Chart.js theme (Monarch-like: quiet grid, white tooltips) ── */
if(hasChart){
  var f = Chart.defaults;
  f.font.family = getComputedStyle(document.body).fontFamily || 'system-ui, sans-serif';
  f.font.size = 12;
  f.color = C.muted;
  f.maintainAspectRatio = false;
  f.plugins.legend.display = false;
  var tt = f.plugins.tooltip;
  tt.backgroundColor = '#fff'; tt.titleColor = C.text; tt.bodyColor = C.text;
  tt.borderColor = '#E8EBF0'; tt.borderWidth = 1; tt.padding = 10; tt.cornerRadius = 10;
  tt.boxPadding = 4; tt.usePointStyle = true; tt.titleFont = { weight:'600' };
  tt.caretSize = 0;
}
function draw(key, canvasId, config){
  if(charts[key]){ charts[key].destroy(); delete charts[key]; }
  var cv = $(canvasId);
  if(!hasChart || !cv || !config) return;
  charts[key] = new Chart(cv, config);
}
function fadeFill(rgb){
  return function(ctx){
    var a = ctx.chart.chartArea;
    if(!a) return 'rgba(' + rgb + ',.12)';
    var g = ctx.chart.ctx.createLinearGradient(0, a.top, 0, a.bottom);
    g.addColorStop(0, 'rgba(' + rgb + ',.24)');
    g.addColorStop(1, 'rgba(' + rgb + ',0)');
    return g;
  };
}

/* ═══ Credit score ═══════════════════════════════════════ */
function renderScore(){
  var h = D.score || [];
  var pts = h;
  if(scoreRange && h.length){
    var cut = parseYmd(D.today); cut.setMonth(cut.getMonth() - scoreRange);
    var cs = ymd(cut);
    pts = h.filter(function(e){ return e.d >= cs; });
    if(!pts.length) pts = [ h[h.length - 1] ];
  }
  var empty = $('ndScoreEmpty');
  if(empty) empty.hidden = h.length > 0;

  // Subtitle + KPI
  var sub = $('ndScoreSub');
  var kpi = setText('ndKpiScore', h.length ? h[h.length-1].s : '—');
  var kpiSub = $('ndKpiScoreSub');
  if(h.length){
    var last = h[h.length-1];
    if(h.length > 1){
      var prev = h[h.length-2], d = last.s - prev.s;
      kpiSub.textContent = (d > 0 ? '▲ ' + d : d < 0 ? '▼ ' + Math.abs(d) : 'No change') + ' since ' + fmtDate(prev.d, false);
      setTone(kpiSub, d);
    } else { kpiSub.textContent = scoreBand(last.s) + ' · ' + fmtDate(last.d); setTone(kpiSub, 0); }
    if(sub){
      if(pts.length > 1){
        var ch = pts[pts.length-1].s - pts[0].s;
        sub.textContent = (ch > 0 ? 'Up ' + ch + ' points' : ch < 0 ? 'Down ' + Math.abs(ch) + ' points' : 'Holding steady') + ' since ' + fmtDate(pts[0].d);
        setTone(sub, ch);
      } else { sub.textContent = scoreBand(last.s) + ' · last updated ' + fmtDate(last.d); setTone(sub, 0); }
    }
  } else {
    kpiSub.textContent = 'Log your score'; setTone(kpiSub, 0);
    if(sub){ sub.textContent = 'Track your score over time'; setTone(sub, 0); }
  }
  if(kpi && h.length) kpi.title = scoreBand(h[h.length-1].s);

  // Keep the gauge card in step with the latest entry
  if(typeof window.nabRenderScoreGauge === 'function') window.nabRenderScoreGauge(h.length ? h[h.length-1].s : 0);

  var vals = pts.map(function(e){ return e.s; });
  var lo = Math.min.apply(null, vals.concat([900])), hi = Math.max.apply(null, vals.concat([300]));
  draw('score', 'ndScoreChart', {
    type: 'line',
    data: { labels: pts.map(function(e){ return fmtDate(e.d, false); }), datasets: [{
      data: vals, borderColor: C.blue, borderWidth: 2.5, tension: .35, fill: true,
      backgroundColor: fadeFill('13,92,155'),
      pointRadius: pts.length <= 14 ? 3.5 : 0, pointHoverRadius: 6,
      pointBackgroundColor: '#fff', pointBorderColor: C.blue, pointBorderWidth: 2
    }] },
    options: {
      interaction: { mode:'index', intersect:false },
      scales: {
        x: { grid: { display:false }, border: { display:false }, ticks: { maxTicksLimit: 6, maxRotation: 0 } },
        y: { suggestedMin: Math.max(300, lo - 40), suggestedMax: Math.min(900, hi + 40), grid: { color: C.grid }, border: { display:false }, ticks: { maxTicksLimit: 5, precision: 0 } }
      },
      plugins: { tooltip: { callbacks: {
        title: function(i){ return fmtDate(pts[i[0].dataIndex].d); },
        label: function(i){ return ' ' + i.raw + ' · ' + scoreBand(i.raw); }
      } } }
    }
  });
}

/* ═══ Cash flow + spending ═══════════════════════════════ */
function lastMonths(n){
  var out = [], d = parseYmd(D.month + '-01');
  for(var i = 0; i < n; i++){ out.unshift(ymd(d).slice(0, 7)); d.setMonth(d.getMonth() - 1); }
  return out;
}
function cfMap(){ var m = {}; (D.cashflow || []).forEach(function(r){ m[r.m] = r; }); return m; }

function renderCashflow(){
  var rows = D.cashflow || [];
  var map = cfMap();
  var months = lastMonths(6);
  // If all data is older than 6 months, still show the most recent 6 that exist
  var recent = months.some(function(m){ return map[m]; });
  if(!recent && rows.length) months = rows.slice(-6).map(function(r){ return r.m; });

  var empty = $('ndCashflowEmpty');
  if(empty) empty.hidden = rows.length > 0;

  var latest = rows.length ? rows[rows.length - 1] : null;
  var stats = $('ndCfStats');
  if(stats){
    stats.textContent = '';
    if(latest){
      var exp = sumExp(latest), net = latest.inc - exp;
      [ ['Income', C.green, money(latest.inc), 0], ['Expenses', C.red, money(exp), 0],
        [ net >= 0 ? 'Saved' : 'Overspent', null, money(Math.abs(net)) + (latest.inc > 0 ? ' · ' + Math.round(100 * net / latest.inc) + '%' : ''), net ] ]
      .forEach(function(s){
        var w = el('div'), l = el('div', 'nd-stat-l');
        if(s[1]){ var i = el('i'); i.style.background = s[1]; l.appendChild(i); }
        l.appendChild(document.createTextNode(s[0] + ' · ' + fmtMonth(latest.m)));
        var v = el('div', 'nd-stat-v', s[2]); setTone(v, s[3]);
        w.appendChild(l); w.appendChild(v); stats.appendChild(w);
      });
    }
  }

  // KPI
  var kv = $('ndKpiCf'), ks = $('ndKpiCfSub');
  if(latest){
    var e2 = sumExp(latest), n2 = latest.inc - e2;
    setText('ndKpiCfLabel', 'Cash flow · ' + fmtMonth(latest.m));
    kv.textContent = signed(n2); setTone(kv, n2);
    ks.textContent = latest.inc > 0 ? (n2 >= 0 ? 'Saved ' : 'Overspent ') + Math.abs(Math.round(100 * n2 / latest.inc)) + '% of income' : 'Expenses ' + money(e2);
  } else {
    setText('ndKpiCfLabel', 'Cash flow'); kv.textContent = '—'; setTone(kv, 0); ks.textContent = 'Add this month';
  }

  draw('cashflow', 'ndCashflowChart', {
    type: 'bar',
    data: { labels: months.map(function(m){ return fmtMonth(m); }), datasets: [
      { label:'Income',   data: months.map(function(m){ return map[m] ? map[m].inc : null; }), backgroundColor: C.green, borderRadius: 6, maxBarThickness: 26 },
      { label:'Expenses', data: months.map(function(m){ return map[m] ? sumExp(map[m]) : null; }), backgroundColor: C.red, borderRadius: 6, maxBarThickness: 26 }
    ] },
    options: {
      datasets: { bar: { categoryPercentage: .62, barPercentage: .9 } },
      interaction: { mode:'index', intersect:false },
      scales: {
        x: { grid: { display:false }, border: { display:false } },
        y: { beginAtZero: true, grid: { color: C.grid }, border: { display:false }, ticks: { maxTicksLimit: 5, callback: function(v){ return moneyShort(v); } } }
      },
      plugins: { tooltip: { callbacks: {
        title: function(i){ return fmtMonth(months[i[0].dataIndex], true); },
        label: function(i){ return ' ' + i.dataset.label + ': ' + money(i.raw); },
        footer: function(i){ var m = map[months[i[0].dataIndex]]; return m ? 'Net: ' + signed(m.inc - sumExp(m)) : ''; }
      } } }
    }
  });

  renderSpending();
}

function renderSpending(){
  var rows = (D.cashflow || []).filter(function(r){ return sumExp(r) > 0; });
  var row = rows.length ? rows[rows.length - 1] : null;
  var wrap = document.querySelector('.nd-donut-wrap');
  var empty = $('ndSpendEmpty');
  if(wrap) wrap.hidden = !row;
  if(empty) empty.hidden = !!row;
  setText('ndSpendSub', row ? fmtMonth(row.m, true) + ' · by category' : 'By category');
  var legend = $('ndSpendLegend');
  if(legend) legend.textContent = '';
  if(!row){ draw('spend', 'ndSpendChart', null); return; }

  var total = sumExp(row);
  var keys = Object.keys(row.exp).filter(function(k){ return +row.exp[k] > 0; }).sort(function(a, b){ return row.exp[b] - row.exp[a]; });
  setText('ndSpendTotal', moneyShort(total));
  keys.forEach(function(k){
    var li = el('li'), dot = el('span', 'dot'); dot.style.background = catOf(k).color;
    li.appendChild(dot);
    li.appendChild(el('span', 'nm', catOf(k).label));
    li.appendChild(el('span', 'amt', money(row.exp[k])));
    li.appendChild(el('span', 'pc', Math.round(100 * row.exp[k] / total) + '%'));
    legend.appendChild(li);
  });
  draw('spend', 'ndSpendChart', {
    type: 'doughnut',
    data: { labels: keys.map(function(k){ return catOf(k).label; }), datasets: [{
      data: keys.map(function(k){ return row.exp[k]; }), backgroundColor: keys.map(function(k){ return catOf(k).color; }),
      borderColor: '#fff', borderWidth: 3, hoverOffset: 6, borderRadius: 4
    }] },
    options: { cutout: '72%', plugins: { tooltip: { callbacks: { label: function(i){ return ' ' + i.label + ': ' + money(i.raw) + ' (' + Math.round(100 * i.raw / total) + '%)'; } } } } }
  });
}

/* ═══ Net worth ══════════════════════════════════════════ */
var ACC_ICON = { cash:'🏦', investment:'📈', property:'🏠', vehicle:'🚗', other_asset:'💼', credit_card:'💳', loan:'🧾', mortgage:'🏡', other_debt:'📄' };
function groupOf(t){ return (D.types && D.types[t] && D.types[t].group) || 'asset'; }

function renderNetworth(){
  var nw = D.networth || { assets:0, debts:0, net:0, history:[] };
  var acc = D.accounts || [];
  var has = acc.length > 0;
  var top = document.querySelector('.nd-nw-top'), chartBox = $('ndNetChart') && $('ndNetChart').parentNode;
  if(top) top.hidden = !has;
  var empty = $('ndNwEmpty'); if(empty) empty.hidden = has;

  setText('ndNwValue', money(nw.net));
  setText('ndNwAssets', money(nw.assets));
  setText('ndNwDebts', money(nw.debts));
  var tot = nw.assets + nw.debts, bar = $('ndNwBar');
  if(bar){
    bar.querySelector('.a').style.width = (tot ? 100 * nw.assets / tot : 0) + '%';
    bar.querySelector('.d').style.width = (tot ? 100 * nw.debts / tot : 0) + '%';
  }

  var kv = $('ndKpiNet'), ks = $('ndKpiNetSub');
  if(has){
    kv.textContent = moneyShort(nw.net);
    var hh = nw.history || [];
    if(hh.length > 1){
      var d = hh[hh.length-1].v - hh[hh.length-2].v;
      ks.textContent = (d >= 0 ? '▲ ' : '▼ ') + money(Math.abs(d)) + ' since ' + fmtMonth(hh[hh.length-2].m);
      setTone(ks, d);
    } else { ks.textContent = acc.length + (acc.length === 1 ? ' account' : ' accounts'); setTone(ks, 0); }
  } else { kv.textContent = '—'; ks.textContent = 'Add your accounts'; setTone(ks, 0); }

  // Account list, grouped like Monarch
  var list = $('ndAccountList');
  if(list){
    list.textContent = '';
    [ ['asset', 'Assets', nw.assets], ['debt', 'Debts', nw.debts] ].forEach(function(g){
      var items = acc.filter(function(a){ return groupOf(a.type) === g[0]; }).sort(function(a, b){ return b.bal - a.bal; });
      if(!items.length) return;
      var head = el('li', 'nd-acc-group'); head.appendChild(el('span', null, g[1])); head.appendChild(el('span', null, money(g[2])));
      list.appendChild(head);
      items.forEach(function(a){
        var li = el('li', 'nd-acc');
        li.appendChild(el('span', 'nd-acc-ico', ACC_ICON[a.type] || '💼'));
        var nm = el('span', 'nd-acc-nm'); nm.appendChild(el('b', null, a.name)); nm.appendChild(el('small', null, (D.types[a.type] || {}).label || ''));
        li.appendChild(nm);
        var bal = el('span', 'nd-acc-bal', (g[0] === 'debt' ? '−' : '') + money(a.bal));
        if(g[0] === 'debt') bal.style.color = C.red;
        li.appendChild(bal);
        list.appendChild(li);
      });
    });
  }

  var hist = (nw.history || []);
  if(chartBox) chartBox.hidden = !has || hist.length < 2;
  if(!has || hist.length < 2){ draw('net', 'ndNetChart', null); return; }
  draw('net', 'ndNetChart', {
    type: 'line',
    data: { labels: hist.map(function(p){ return fmtMonth(p.m); }), datasets: [{
      data: hist.map(function(p){ return p.v; }), borderColor: C.green, borderWidth: 2.5, tension: .35, fill: true,
      backgroundColor: fadeFill('34,160,107'), pointRadius: 0, pointHoverRadius: 5, pointBackgroundColor: C.green
    }] },
    options: {
      interaction: { mode:'index', intersect:false },
      scales: { x: { display: false }, y: { display: false, grace: '10%' } },
      plugins: { tooltip: { callbacks: { title: function(i){ return fmtMonth(hist[i[0].dataIndex].m, true); }, label: function(i){ return ' Net worth: ' + money(i.raw); } } } }
    }
  });
}

/* ═══ Utilization (read from Utilization Checker) ═══════ */
function utilTone(p){ return p < 10 ? ['Excellent','good',C.green] : p < 30 ? ['Good','good',C.green] : p < 50 ? ['Fair','ok','#E4A11B'] : ['High','bad',C.red]; }
function renderUtil(){
  var body = $('ndUtilBody'); if(!body) return;
  var cards = (D.util && D.util.cards) || [];
  body.textContent = '';
  var bal = 0, lim = 0;
  cards.forEach(function(c){ bal += c.bal; lim += c.lim; });
  if(!lim){
    var e = el('div', 'nd-empty nd-empty-inline');
    e.appendChild(el('div', 'nd-empty-txt', 'Save your cards in the Utilization Checker and your usage will show here.'));
    body.appendChild(e);
    setText('ndKpiUtil', '—'); setText('ndKpiUtilSub', 'Use the Utilization Checker');
    return;
  }
  var pct = Math.round(100 * bal / lim), tone = utilTone(pct);
  var top = el('div', 'nd-util-top');
  top.appendChild(el('div', 'nd-util-pct', pct + '%'));
  top.appendChild(el('span', 'nd-badge ' + tone[1], tone[0]));
  var used = el('div', 'nd-card-sub', money(bal) + ' of ' + money(lim) + ' used'); used.style.marginLeft = 'auto';
  top.appendChild(used);
  body.appendChild(top);
  cards.forEach(function(c){
    var p = Math.round(100 * c.bal / c.lim), t = utilTone(p);
    var row = el('div', 'nd-util-row'), tx = el('div', 't');
    tx.appendChild(el('span', null, c.name)); tx.appendChild(el('span', null, p + '% · ' + money(c.bal) + ' / ' + money(c.lim)));
    var tr = el('div', 'nd-track'), fill = el('span'); fill.style.width = Math.min(100, p) + '%'; fill.style.background = t[2];
    tr.appendChild(fill); row.appendChild(tx); row.appendChild(tr); body.appendChild(row);
  });
  var kv = setText('ndKpiUtil', pct + '%');
  kv.classList.remove('nd-up','nd-down'); if(pct >= 50) kv.classList.add('nd-down');
  setText('ndKpiUtilSub', tone[0] + ' · ' + cards.length + (cards.length === 1 ? ' card' : ' cards'));
}

/* ═══ Goals: emergency fund ring ═════════════════════════ */
function renderGoals(){
  var ef = D.ef || {}, ring = $('ndEfRing');
  var pct = ef.goal > 0 ? Math.min(100, Math.round(100 * ef.saved / ef.goal)) : 0;
  if(ring){ var c = 2 * Math.PI * 26; ring.setAttribute('stroke-dashoffset', (c * (1 - pct / 100)).toFixed(1)); }
  setText('ndEfPct', pct + '%');
  setText('ndEfSub', ef.goal > 0 ? money(ef.saved) + ' of ' + moneyShort(ef.goal) : 'Set a goal');
}

function renderAll(){
  renderScore(); renderCashflow(); renderNetworth(); renderUtil(); renderGoals();
  renderLists();
}

/* ═══ Entry sheets ═══════════════════════════════════════ */
var modal = $('ndModal'), lastFocus = null;
function pane(name){ return modal && modal.querySelector('[data-pane="' + name + '"]'); }
function setMsg(form, text, ok){ var m = form.querySelector('.nd-form-msg'); if(m){ m.textContent = text || ''; m.className = 'nd-form-msg' + (text ? (ok ? ' ok' : ' err') : ''); } }

function fillCashflowForm(form, month){
  var row = cfMap()[month];
  form.elements['month'].value = month;
  form.elements['income'].value = row && row.inc ? row.inc : '';
  Object.keys(D.cats || {}).forEach(function(k){
    var inp = form.elements['exp_' + k]; if(inp) inp.value = row && row.exp && row.exp[k] ? row.exp[k] : '';
  });
}
function resetAccountForm(form){
  form.reset(); form.elements['id'].value = '';
  var cancel = form.querySelector('[data-nd-reset]'); if(cancel) cancel.hidden = true;
  $('ndSheetTitle').textContent = form.getAttribute('data-title');
}
function editAccount(id){
  var a = (D.accounts || []).filter(function(x){ return x.id === id; })[0];
  var form = pane('account'); if(!a || !form) return;
  form.elements['id'].value = a.id; form.elements['name'].value = a.name; form.elements['type'].value = a.type; form.elements['balance'].value = a.bal;
  var cancel = form.querySelector('[data-nd-reset]'); if(cancel) cancel.hidden = false;
  $('ndSheetTitle').textContent = 'Edit account';
  form.elements['name'].focus();
}

function openSheet(name, opts){
  var p = pane(name);
  if(!p) return;
  opts = opts || {};
  if(modal.hidden) lastFocus = document.activeElement;
  modal.querySelectorAll('.nd-pane').forEach(function(x){ x.hidden = x !== p; });
  $('ndSheetTitle').textContent = p.getAttribute('data-title');
  if(p.tagName === 'FORM'){
    setMsg(p, '');
    if(name === 'score'){ p.elements['score'].value = ''; p.elements['date'].value = D.today; }
    if(name === 'cashflow') fillCashflowForm(p, opts.month || D.month);
    if(name === 'account'){ resetAccountForm(p); if(opts.account) editAccount(opts.account); }
  }
  renderLists();
  modal.hidden = false;
  document.documentElement.style.overflow = 'hidden';
  modal.querySelector('.nd-sheet-body').scrollTop = 0;
  // Only auto-focus where there is a real keyboard — on phones it would pop the keyboard over the sheet
  if(window.matchMedia && window.matchMedia('(hover: hover)').matches){
    var first = p.querySelector('input:not([type=hidden]),select,button');
    if(first) first.focus();
  }
}
function closeSheet(){
  if(!modal || modal.hidden) return;
  modal.hidden = true;
  document.documentElement.style.overflow = '';
  if(lastFocus && lastFocus.focus) lastFocus.focus();
}

function row(main, sub, actions){
  var li = el('li'), m = el('span', 'main');
  m.appendChild(el('b', null, main)); m.appendChild(el('small', null, sub));
  li.appendChild(m);
  var act = el('span', 'act');
  actions.forEach(function(a){ var b = el('button', a[2] || '', a[0]); b.type = 'button'; b.setAttribute(a[1][0], a[1][1]); act.appendChild(b); });
  li.appendChild(act);
  return li;
}
function renderLists(){
  if(!modal) return;
  var ls = modal.querySelector('[data-list="score"]');
  if(ls){
    ls.textContent = '';
    var h = (D.score || []).slice().reverse();
    if(!h.length) ls.appendChild(el('li', 'none', 'No scores yet.'));
    h.forEach(function(e){ ls.appendChild(row(e.s + ' · ' + scoreBand(e.s), fmtDate(e.d), [['Delete', ['data-del-score', e.d], 'del']])); });
  }
  var lc = modal.querySelector('[data-list="cashflow"]');
  if(lc){
    lc.textContent = '';
    var cf = (D.cashflow || []).slice().reverse();
    if(!cf.length) lc.appendChild(el('li', 'none', 'No months yet.'));
    cf.forEach(function(r){ lc.appendChild(row(fmtMonth(r.m, true), 'Income ' + money(r.inc) + ' · Expenses ' + money(sumExp(r)), [['Edit', ['data-edit-month', r.m]], ['Delete', ['data-del-month', r.m], 'del']])); });
  }
  var la = modal.querySelector('[data-list="account"]');
  if(la){
    la.textContent = '';
    var acc = D.accounts || [];
    if(!acc.length) la.appendChild(el('li', 'none', 'No accounts yet.'));
    acc.forEach(function(a){
      var debt = groupOf(a.type) === 'debt';
      la.appendChild(row(a.name, ((D.types[a.type] || {}).label || '') + ' · ' + (debt ? '−' : '') + money(a.bal), [['Edit', ['data-edit-account', a.id]], ['Delete', ['data-del-account', a.id], 'del']]));
    });
  }
}

/* ── Server calls ───────────────────────────────────────── */
function post(params){
  if(typeof nabPortal === 'undefined') return Promise.resolve({ success:false, data:{ message:'Session error. Please refresh the page.' } });
  params.append('nonce', nabPortal.nonce);
  return fetch(nabPortal.ajax, { method:'POST', credentials:'same-origin', body: params })
    .then(function(r){ return r.text(); })
    .then(function(t){
      try { return JSON.parse(t); }
      catch(e){ return { success:false, data:{ message: t.trim() === '-1' ? 'Your session expired — please refresh the page.' : 'Something went wrong. Please try again.' } }; }
    })
    .catch(function(){ return { success:false, data:{ message:'Connection error. Please check your internet and try again.' } }; });
}
function applyResult(d){
  if(d && d.success && d.data && d.data.dash){ D = d.data.dash; window.nabDashData = D; renderAll(); return true; }
  return false;
}

function validate(form){
  var a = form.getAttribute('data-action');
  if(a === 'nab_dash_score_add'){
    var s = parseInt(form.elements['score'].value, 10);
    if(!s || s < 300 || s > 900) return 'Please enter a score between 300 and 900.';
    if(!form.elements['date'].value || form.elements['date'].value > D.today) return 'Please choose a date that is not in the future.';
  }
  if(a === 'nab_dash_cashflow_save'){
    var any = +form.elements['income'].value > 0;
    Object.keys(D.cats || {}).forEach(function(k){ if(form.elements['exp_' + k] && +form.elements['exp_' + k].value > 0) any = true; });
    if(!any) return 'Enter your income or at least one expense.';
  }
  if(a === 'nab_dash_account_save'){
    if(!form.elements['name'].value.trim()) return 'Please give the account a name.';
    if(form.elements['balance'].value === '' || +form.elements['balance'].value < 0) return 'Please enter the balance (0 or more).';
  }
  return '';
}

if(modal){
  modal.addEventListener('submit', function(e){
    var form = e.target.closest('form.nd-form');
    if(!form) return;
    e.preventDefault();
    var problem = validate(form);
    if(problem){ setMsg(form, problem, false); return; }
    var btn = form.querySelector('button[type=submit]'), label = btn.textContent;
    btn.disabled = true; btn.textContent = 'Saving…';
    var params = new URLSearchParams(new FormData(form));
    params.append('action', form.getAttribute('data-action'));
    post(params).then(function(d){
      btn.disabled = false; btn.textContent = label;
      if(applyResult(d)){
        setMsg(form, '✓ ' + (d.data.message || 'Saved.'), true);
        if(form.elements['score']) form.elements['score'].value = '';
        if(form.getAttribute('data-pane') === 'account') resetAccountForm(form);
      } else setMsg(form, (d && d.data && d.data.message) || 'Could not save. Please try again.', false);
    });
  });

  modal.addEventListener('change', function(e){
    if(e.target.name === 'month' && e.target.closest('[data-pane="cashflow"]')) fillCashflowForm(e.target.form, e.target.value);
  });

  modal.addEventListener('click', function(e){
    if(e.target.closest('[data-nd-close]')){ closeSheet(); return; }
    var t;
    if((t = e.target.closest('[data-nd-reset]'))){ resetAccountForm(t.form); return; }
    if((t = e.target.closest('[data-edit-month]'))){ var f = pane('cashflow'); fillCashflowForm(f, t.getAttribute('data-edit-month')); setMsg(f, ''); modal.querySelector('.nd-sheet-body').scrollTop = 0; return; }
    if((t = e.target.closest('[data-edit-account]'))){ setMsg(pane('account'), ''); editAccount(t.getAttribute('data-edit-account')); modal.querySelector('.nd-sheet-body').scrollTop = 0; return; }
    var del = [ ['data-del-score', 'nab_dash_score_delete', 'date', 'Delete this score?'],
                ['data-del-month', 'nab_dash_cashflow_delete', 'month', 'Delete this month?'],
                ['data-del-account', 'nab_dash_account_delete', 'id', 'Delete this account?'] ];
    for(var i = 0; i < del.length; i++){
      t = e.target.closest('[' + del[i][0] + ']');
      if(!t) continue;
      if(!window.confirm(del[i][3])) return;
      var form = t.closest('form'), p = new URLSearchParams();
      p.append('action', del[i][1]); p.append(del[i][2], t.getAttribute(del[i][0]));
      t.disabled = true;
      post(p).then(function(d){
        if(applyResult(d)) setMsg(form, '✓ ' + (d.data.message || 'Removed.'), true);
        else { t.disabled = false; setMsg(form, (d && d.data && d.data.message) || 'Could not delete. Please try again.', false); }
      });
      return;
    }
  });
}

/* ── Page-level interactions ────────────────────────────── */
document.addEventListener('click', function(e){
  var t = e.target.closest('[data-nd-open]');
  if(t){ e.preventDefault(); openSheet(t.getAttribute('data-nd-open')); return; }

  var seg = e.target.closest('[data-nd-range] button');
  if(seg){
    seg.parentNode.querySelectorAll('button').forEach(function(b){ b.classList.toggle('on', b === seg); });
    scoreRange = parseInt(seg.getAttribute('data-range'), 10) || 0;
    renderScore(); return;
  }

  if(e.target.closest('[data-nd-menu]')){ var ham = $('nabHamburger'); if(ham) ham.click(); return; }

  if(e.target.closest('[data-nd-home]')){
    if(typeof window.nabOpenTab === 'function') window.nabOpenTab('overview');
    window.scrollTo({ top: 0, behavior: 'smooth' }); return;
  }

  // Tab links outside the tab bar (hero "Profile", bottom nav): bring the tab into view
  var tab = e.target.closest('[data-tab-open]');
  if(tab && !tab.closest('#nabTabBar')){
    var bar = $('nabTabBar');
    if(bar) setTimeout(function(){ bar.scrollIntoView({ behavior:'smooth', block:'start' }); }, 30);
  }
});
document.addEventListener('keydown', function(e){ if(e.key === 'Escape') closeSheet(); });

// The original score checker (nab-dashboard.js) saves through its own
// endpoint and hands us the fresh data here.
window.addEventListener('nab:dash-data', function(e){ if(e.detail){ D = e.detail; window.nabDashData = D; renderAll(); } });

renderAll();
})();
