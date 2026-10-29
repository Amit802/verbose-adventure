/**
 * NAB Member Portal — Monthly Budget Planner (v1.10.0)
 * Data: window.nabBudgetData (printed by templates/page-nab-budget.php).
 * Budget saves go to nab_budget_save; spending saves go to the dashboard's
 * nab_dash_cashflow_save so both pages share the same monthly numbers.
 * All member-entered text is set with textContent.
 */
(function(){
'use strict';

var D = window.nabBudgetData;
if(!D) return;
var $ = function(id){ return document.getElementById(id); };
var C = { green:'#22A06B', amber:'#E4A11B', red:'#E5484D', blue:'#0D5C9B' };
var CATS = Object.keys(D.cats || {});
var sel = D.month;

/* ── Formatting ───────────────────────────────────────── */
var cad = new Intl.NumberFormat('en-CA', { style:'currency', currency:'CAD', maximumFractionDigits:0 });
function money(v){ return cad.format(Math.round(v || 0)); }
function parseYm(m){ var p = m.split('-'); return new Date(+p[0], +p[1] - 1, 1); }
function ym(d){ return d.getFullYear() + '-' + ('0' + (d.getMonth() + 1)).slice(-2); }
function fmtMonth(m, long){ return parseYm(m).toLocaleDateString('en-US', long ? { month:'long', year:'numeric' } : { month:'long' }); }
function sum(o){ var t = 0; Object.keys(o || {}).forEach(function(k){ t += +o[k] || 0; }); return t; }
function setText(id, t){ var e = $(id); if(e) e.textContent = t; return e; }
function tone(el, v){ if(!el) return; el.classList.remove('bp-up','bp-down'); if(v > 0) el.classList.add('bp-up'); else if(v < 0) el.classList.add('bp-down'); }
function el(tag, cls, text){ var e = document.createElement(tag); if(cls) e.className = cls; if(text != null) e.textContent = text; return e; }
function cfRow(m){ return (D.cashflow || []).filter(function(r){ return r.m === m; })[0] || null; }
function barColor(p){ return p > 100 ? C.red : p >= 85 ? C.amber : C.green; }
function icon(n, s){ return typeof window.nabIcon === 'function' ? window.nabIcon(n, s) : ''; }

/* ── Month picker: last 12 months ─────────────────────── */
var months = [];
(function(){ var d = parseYm(D.month); for(var i = 0; i < 12; i++){ months.push(ym(d)); d.setMonth(d.getMonth() - 1); } })();
var picker = $('bpMonth');
months.forEach(function(m){ var o = el('option', null, fmtMonth(m, true)); o.value = m; picker.appendChild(o); });
picker.value = sel;
picker.addEventListener('change', function(){ sel = picker.value; render(); });
document.querySelectorAll('[data-bp-step]').forEach(function(b){
  b.addEventListener('click', function(){
    var i = months.indexOf(sel) - parseInt(b.getAttribute('data-bp-step'), 10); // list is newest first
    if(i >= 0 && i < months.length){ sel = months[i]; picker.value = sel; render(); }
  });
});

/* ── Render ───────────────────────────────────────────── */
function render(){
  var row = cfRow(sel);
  var spentBy = row ? (row.exp || {}) : {};
  var income  = row ? (+row.inc || 0) : 0;
  var budget  = D.budget || {};
  var totB = sum(budget), totS = sum(spentBy), left = totB - totS;
  var isNow = sel === D.month;

  // Arrows
  var idx = months.indexOf(sel);
  document.querySelector('[data-bp-step="1"]').disabled = idx <= 0;
  document.querySelector('[data-bp-step="-1"]').disabled = idx >= months.length - 1;

  // Summary tiles
  setText('bpBudget', money(totB));
  setText('bpBudgetSub', totB ? Object.keys(budget).length + (Object.keys(budget).length === 1 ? ' category' : ' categories') : 'Not set yet');
  setText('bpSpent', money(totS));
  setText('bpSpentSub', row ? 'in ' + fmtMonth(sel) : 'No spending entered');
  setText('bpLeftLabel', left < 0 ? 'Over budget' : 'Left to spend');
  var lv = setText('bpLeft', money(Math.abs(left)));
  tone(lv, totB ? (left < 0 ? -1 : 1) : 0);
  setText('bpLeftSub', totB ? (left < 0 ? 'Spent more than planned' : Math.round(100 * totS / totB) + '% used') : 'Set a budget first');
  setText('bpIncome', money(income));
  var unbud = income - totB, is = $('bpIncomeSub');
  is.textContent = !income ? 'Add your income' : !totB ? 'Set a budget to compare' : unbud >= 0 ? money(unbud) + ' not budgeted' : money(-unbud) + ' more than income';
  tone(is, income && totB ? (unbud >= 0 ? 0 : -1) : 0);

  // Overall progress
  var pct = totB ? Math.round(100 * totS / totB) : 0;
  var bar = $('bpOverallBar');
  bar.style.width = Math.min(100, pct) + '%';
  bar.style.background = barColor(pct);
  setText('bpOverallSub', fmtMonth(sel, true));
  setText('bpOverallPct', totB ? money(totS) + ' of ' + money(totB) + ' (' + pct + '%)' : 'Set a budget to track your progress');
  var perDay = '';
  if(isNow && totB && left > 0){
    var today = new Date(D.today.replace(/-/g, '/'));
    var last = new Date(today.getFullYear(), today.getMonth() + 1, 0).getDate();
    var daysLeft = last - today.getDate() + 1;
    perDay = 'About ' + money(left / daysLeft) + ' a day for the rest of the month';
  }
  setText('bpPerDay', perDay || ' ');

  // Insight
  var over = CATS.filter(function(k){ return budget[k] && (+spentBy[k] || 0) > budget[k]; })
    .sort(function(a, b){ return (spentBy[b] - budget[b]) - (spentBy[a] - budget[a]); });
  var ins = $('bpInsight'), msg = '', cls = '';
  if(totB && !row) { msg = 'No spending entered for ' + fmtMonth(sel) + ' yet. Use Update spending to add it.'; }
  else if(over.length){
    cls = 'bad';
    msg = 'You are over budget on ' + D.cats[over[0]].label + ' by ' + money(spentBy[over[0]] - budget[over[0]]) + (over.length > 1 ? ' and on ' + (over.length - 1) + ' other ' + (over.length > 2 ? 'categories' : 'category') : '') + '.';
  } else if(totB && pct >= 85){ msg = 'You have used ' + pct + '% of your budget for ' + fmtMonth(sel) + '.'; }
  else if(totB && row && !isNow){ cls = 'good'; msg = 'Nice work. You stayed within your budget in ' + fmtMonth(sel) + '.'; }
  ins.hidden = !msg;
  ins.className = 'bp-insight' + (cls ? ' ' + cls : '');
  ins.innerHTML = msg ? icon(cls === 'good' ? 'circle-check' : cls === 'bad' ? 'triangle-alert' : 'info', 18) : '';
  if(msg) ins.appendChild(el('span', null, msg));

  // Categories
  $('bpEmpty').hidden = !!totB;
  var list = $('bpList');
  list.textContent = '';
  CATS.filter(function(k){ return budget[k] || +spentBy[k]; }).forEach(function(k){
    var b = +budget[k] || 0, s = +spentBy[k] || 0, cat = D.cats[k];
    var li = el('li', 'bp-row');
    var ic = el('span', 'bp-ico', cat.label.charAt(0)); ic.style.background = cat.color; ic.setAttribute('aria-hidden', 'true');
    if(cat.tone && typeof window.nabTile === 'function'){ ic.textContent = ''; ic.style.background = ''; ic.classList.add('is-tile'); ic.innerHTML = window.nabTile(cat.icon, cat.tone, 36); }
    li.appendChild(ic);
    li.appendChild(el('span', 'bp-name', cat.label));
    var amt = el('span', 'bp-amt', money(s));
    if(b) amt.appendChild(el('small', null, ' of ' + money(b)));
    li.appendChild(amt);
    var tr = el('div', 'bp-track'), fill = el('span');
    var p = b ? Math.round(100 * s / b) : (s ? 100 : 0);
    fill.style.width = Math.min(100, p) + '%';
    fill.style.background = b ? barColor(p) : '#C3CAD5';
    tr.appendChild(fill);
    li.appendChild(tr);
    var st = el('span', 'bp-status', !b ? 'Not in budget' : s > b ? money(s - b) + ' over' : money(b - s) + ' left');
    if(b && s > b) st.classList.add('bp-down');
    li.appendChild(st);
    list.appendChild(li);
  });
}

/* ── Forms ────────────────────────────────────────────── */
var budgetCard = $('bpBudgetCard'), spendCard = $('bpSpendCard');
var bForm = $('bpBudgetForm'), sForm = $('bpSpendForm');
function msg(form, text, ok){ var m = form.querySelector('.bp-msg'); m.textContent = text || ''; m.className = 'bp-msg' + (text ? (ok ? ' ok' : ' err') : ''); }
function field(form, n){ return form.elements[n]; }

function budgetTotal(){
  var t = 0; CATS.forEach(function(k){ t += +field(bForm, 'b_' + k).value || 0; });
  var row = cfRow(sel) || latestWith('inc');
  var inc = row ? +row.inc || 0 : 0;
  var box = $('bpBudgetTotal');
  box.textContent = '';
  box.appendChild(el('span', null, 'Total budget: '));
  box.appendChild(el('b', null, money(t)));
  if(inc){
    var d = inc - t;
    box.appendChild(el('span', null, ' · Income in ' + fmtMonth(row.m) + ': ' + money(inc) + ' · '));
    var r = el('b', null, d >= 0 ? money(d) + ' left for savings' : money(-d) + ' more than your income');
    r.style.color = d >= 0 ? '#157F4F' : '#B42328';
    box.appendChild(r);
  }
}
bForm.addEventListener('input', budgetTotal);

// Most recent month (selected one first) that has expenses / income
function latestWith(what){
  var rows = (D.cashflow || []).slice().reverse();
  var cur = cfRow(sel);
  if(cur) rows.unshift(cur);
  return rows.filter(function(r){ return what === 'inc' ? +r.inc > 0 : sum(r.exp) > 0; })[0] || null;
}
var GUIDE = { housing:.25, transport:.10, food:.10, utilities:.03, health:.02, debt:.10, shopping:.15, other:.15 }; // 90%; the last 10% is savings
function fill(mode){
  if(mode === 'spending'){
    var r = latestWith('exp');
    if(!r){ msg(bForm, 'Add your spending for a month first (Update spending), then try again.', false); return; }
    CATS.forEach(function(k){ var v = +r.exp[k] || 0; field(bForm, 'b_' + k).value = v ? Math.ceil(v / 10) * 10 : ''; });
    msg(bForm, 'Filled from your ' + fmtMonth(r.m, true) + ' spending, rounded up. Adjust as you like.', true);
  }
  if(mode === 'guide'){
    var ri = latestWith('inc');
    if(!ri){ msg(bForm, 'Add your monthly income first (Update spending), then try again.', false); return; }
    CATS.forEach(function(k){ field(bForm, 'b_' + k).value = GUIDE[k] ? Math.round(ri.inc * GUIDE[k] / 10) * 10 : ''; });
    msg(bForm, 'Based on ' + money(ri.inc) + ' income: 50% needs, 30% wants, 20% savings and debt. Adjust as you like.', true);
  }
  budgetTotal();
}

var closeTimer = null;
function open(which, mode){
  clearTimeout(closeTimer);
  var card = which === 'budget' ? budgetCard : spendCard;
  (which === 'budget' ? spendCard : budgetCard).hidden = true;
  if(which === 'budget'){
    msg(bForm, '');
    CATS.forEach(function(k){ field(bForm, 'b_' + k).value = D.budget[k] || ''; });
    if(mode) fill(mode); else budgetTotal();
  } else {
    msg(sForm, '');
    var r = cfRow(sel);
    field(sForm, 'month').value = sel;
    field(sForm, 'income').value = r && r.inc ? r.inc : '';
    CATS.forEach(function(k){ field(sForm, 'exp_' + k).value = r && r.exp && r.exp[k] ? r.exp[k] : ''; });
    setText('bpSpendTitle', 'Spending for ' + fmtMonth(sel, true));
  }
  card.hidden = false;
  card.scrollIntoView({ behavior:'smooth', block:'start' });
}
document.addEventListener('click', function(e){
  var o = e.target.closest('[data-bp-open]');
  if(o){ open(o.getAttribute('data-bp-open'), o.getAttribute('data-bp-fill')); return; }
  var f = e.target.closest('#bpBudgetCard [data-bp-fill]');
  if(f){ fill(f.getAttribute('data-bp-fill')); return; }
  if(e.target.closest('[data-bp-close]')){ budgetCard.hidden = spendCard.hidden = true; }
});

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
function submit(form, action, onDone){
  var btn = form.querySelector('button[type=submit]'), label = btn.textContent;
  btn.disabled = true; btn.textContent = 'Saving…';
  var params = new URLSearchParams(new FormData(form));
  params.append('action', action);
  post(params).then(function(d){
    btn.disabled = false; btn.textContent = label;
    if(d && d.success){
      onDone(d.data); render();
      msg(form, '✓ ' + (d.data.message || 'Saved.'), true);
      // Close the form and bring the updated categories into view
      clearTimeout(closeTimer);
      closeTimer = setTimeout(function(){
        form.closest('.bp-card').hidden = true;
        $('bpList').closest('.bp-card').scrollIntoView({ behavior:'smooth', block:'start' });
      }, 700);
    }
    else msg(form, (d && d.data && d.data.message) || 'Could not save. Please try again.', false);
  });
}
bForm.addEventListener('submit', function(e){
  e.preventDefault();
  submit(bForm, 'nab_budget_save', function(r){ D.budget = r.data.budget; D.cashflow = r.data.cashflow; });
});
sForm.addEventListener('submit', function(e){
  e.preventDefault();
  var any = +field(sForm, 'income').value > 0;
  CATS.forEach(function(k){ if(+field(sForm, 'exp_' + k).value > 0) any = true; });
  if(!any){ msg(sForm, 'Enter your income or at least one expense.', false); return; }
  submit(sForm, 'nab_dash_cashflow_save', function(r){ D.cashflow = r.dash.cashflow; });
});

render();
})();
