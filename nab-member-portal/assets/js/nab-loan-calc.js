/**
 * NAB Member Portal — Loan Calculator (v1.20.0)
 * Data: window.nabLoanData (printed by templates/page-nab-loan-calculator.php).
 * Everything is worked out in the browser; nothing is saved.
 *
 * Maths
 * - Payment: standard amortizing loan, P * i / (1 - (1 + i)^-N).
 * - Rate per payment: annual rate / payments per year. Mortgages compound
 *   twice a year (Canadian rule), so their rate is (1 + r/2)^(2/n) - 1.
 * - "Accelerated" every 2 weeks = half the monthly payment, 26 times a year.
 * - Extra payments go straight to the loan, so it ends sooner.
 * All member-entered text is set with textContent.
 */
(function(){
'use strict';

var D = window.nabLoanData;
if(!D) return;
var $ = function(id){ return document.getElementById(id); };
var form = $('lcForm');
if(!form) return;
var F = form.elements;

/* ── Helpers ──────────────────────────────────────────── */
var cad0 = new Intl.NumberFormat('en-CA', { style:'currency', currency:'CAD', maximumFractionDigits:0 });
var cad2 = new Intl.NumberFormat('en-CA', { style:'currency', currency:'CAD', minimumFractionDigits:2, maximumFractionDigits:2 });
function money(v){ return cad0.format(Math.round(+v || 0)); }
function money2(v){ return cad2.format(+v || 0); }
function num(v){ v = parseFloat(String(v == null ? '' : v).replace(/[^0-9.\-]/g, '')); return isFinite(v) ? v : NaN; }
function el(tag, cls, text){ var e = document.createElement(tag); if(cls) e.className = cls; if(text != null) e.textContent = text; return e; }
function setText(id, t){ var e = $(id); if(e) e.textContent = t; return e; }
function tile(name, tone, size){
  var w = el('span');
  w.innerHTML = typeof window.nabTile === 'function' ? window.nabTile(name, tone, size) : '';
  return w.firstChild || el('span');
}

var FREQ = {
  monthly:     { n:12, label:'Your monthly payment',            each:'month',           short:'a month' },
  semimonthly: { n:24, label:'Your payment twice a month',      each:'payment',         short:'twice a month' },
  biweekly:    { n:26, label:'Your payment every 2 weeks',      each:'payment',         short:'every 2 weeks' },
  accelerated: { n:26, label:'Your payment every 2 weeks',      each:'payment',         short:'every 2 weeks' },
  weekly:      { n:52, label:'Your weekly payment',             each:'week',            short:'a week' }
};
var MAX_PAYMENTS = 52 * 45;

function periodRate(annualPct, n, mortgage){
  var r = annualPct / 100;
  if(r <= 0) return 0;
  return mortgage ? Math.pow(1 + r / 2, 2 / n) - 1 : r / n;
}
function payment(P, i, N){
  if(N <= 0) return P;
  return i === 0 ? P / N : P * i / (1 - Math.pow(1 + i, -N));
}

/** Full schedule. Returns { pay, rows:[{bal, int, prin, paid}], interest, paid, count } */
function schedule(o){
  var f = FREQ[o.freq] || FREQ.monthly, n = f.n;
  var months = o.years * 12 + o.months;
  var i = periodRate(o.rate, n, o.mortgage), pay;
  if(o.freq === 'accelerated'){
    pay = payment(o.P, periodRate(o.rate, 12, o.mortgage), months) / 2;
  } else {
    pay = payment(o.P, i, Math.max(1, Math.round(months / 12 * n)));
  }
  var bal = o.P, rows = [], tInt = 0, tPaid = 0, extra = Math.max(0, o.extra || 0);
  for(var k = 0; k < MAX_PAYMENTS && bal > 0.005; k++){
    var it = bal * i;
    var due = Math.min(pay + extra, bal + it);
    if(due <= it && k > 0) break;            // payment can't cover the interest (shouldn't happen)
    var pr = due - it;
    bal = Math.max(0, bal - pr);
    tInt += it; tPaid += due;
    rows.push({ bal:bal, int:it, prin:pr, paid:due });
  }
  return { pay:pay, n:n, rows:rows, interest:tInt, paid:tPaid, count:rows.length, open: bal > 0.005 };
}

function dateAfter(count, freq){
  var d = new Date(); d.setHours(12, 0, 0, 0);
  if(freq === 'monthly'){ d.setMonth(d.getMonth() + count); }
  else if(freq === 'semimonthly'){ d.setMonth(d.getMonth() + Math.floor(count / 2)); if(count % 2) d.setDate(d.getDate() + 15); }
  else if(freq === 'weekly'){ d.setDate(d.getDate() + 7 * count); }
  else { d.setDate(d.getDate() + 14 * count); }
  return d;
}
function monthYear(d){ return d.toLocaleDateString('en-CA', { month:'long', year:'numeric' }); }
function span(count, n){
  var totalMonths = Math.round(count / n * 12), y = Math.floor(totalMonths / 12), m = totalMonths % 12;
  var parts = [];
  if(y) parts.push(y + (y === 1 ? ' year' : ' years'));
  if(m) parts.push(m + (m === 1 ? ' month' : ' months'));
  return parts.join(' ') || 'less than a month';
}
function niceExtra(pay, freq){
  var step = freq === 'monthly' ? 25 : 10;
  return Math.max(step, Math.round(pay * 0.1 / step) * step);
}

/* ── State ────────────────────────────────────────────── */
var state = { mortgage:false };

function readForm(){
  return {
    amount: num(F.amount.value), down: num(F.down.value) || 0, rate: num(F.rate.value),
    years: num(F.years.value) || 0, months: num(F.months.value) || 0,
    freq: F.freq.value, extra: num(F.extra.value) || 0, mortgage: state.mortgage
  };
}

function validate(o){
  var bad = [], msg = '';
  function flag(name, text){ bad.push(name); if(!msg) msg = text; }
  if(!(o.amount >= 100) || o.amount > 5000000) flag('amount', 'Enter a loan amount between $100 and $5,000,000.');
  if(!(o.rate >= 0) || o.rate > 60 || isNaN(o.rate)) flag('rate', 'Enter an interest rate between 0% and 60%.');
  var months = o.years * 12 + o.months;
  if(o.years < 0 || o.months < 0 || o.months > 11 || months < 1 || months > 480) flag(months < 1 ? 'years' : (o.months > 11 ? 'months' : 'years'), 'Enter a length between 1 month and 40 years.');
  if(o.down < 0 || (o.amount >= 100 && o.down >= o.amount)) flag('down', 'The down payment has to be less than the loan amount.');
  if(o.extra < 0) flag('extra', 'The extra amount can\'t be negative.');
  ['amount','rate','years','months','down','extra'].forEach(function(nm){ F[nm].setAttribute('aria-invalid', bad.indexOf(nm) >= 0 ? 'true' : 'false'); });
  setText('lcErr', msg);
  return !msg;
}

/* ── Render ───────────────────────────────────────────── */
var chart = null;

function render(){
  var o = readForm();
  syncRange();
  updateNote(o);
  if(!validate(o)){ return; }
  o.P = o.amount - o.down;
  var f = FREQ[o.freq] || FREQ.monthly;
  var s = schedule(o);
  var base = o.extra > 0 ? schedule(Object.assign({}, o, { extra:0 })) : s;

  // Hero
  setText('lcPayLabel', f.label);
  var pv = $('lcPay'); pv.textContent = money2(s.pay);
  setText('lcPrincipal', money(o.P));
  setText('lcInterest', money(s.interest));
  setText('lcTotal', money(s.paid));
  setText('lcCount', String(s.count));
  var tot = o.P + s.interest;
  $('lcSplitP').style.width = tot ? (100 * o.P / tot) + '%' : '0';
  $('lcSplitI').style.width = tot ? (100 * s.interest / tot) + '%' : '0';
  var pill = $('lcPayoff');
  pill.className = 'lc-pill' + (o.extra > 0 && base.count > s.count ? ' good' : '');
  pill.textContent = 'Paid off by ' + monthYear(dateAfter(s.count, o.freq)) + ' (' + span(s.count, s.n) + ')';
  var foot = $('lcHeroFoot');
  if(o.extra > 0){
    foot.textContent = 'With your extra ' + money2(o.extra) + ' you pay ' + money2(s.pay + o.extra) + ' each time. The last payment is smaller. This is an estimate; your lender\'s numbers can differ a little.';
  } else {
    foot.textContent = 'Interest adds ' + Math.round(100 * s.interest / o.P) + '% to what you borrow. This is an estimate; your lender\'s numbers can differ a little because of fees and how they count days.';
  }

  renderInsights(o, s, base);
  renderChart(o, s, base);
  renderTable(s);
}

function insight(iconName, tone, parts, action){
  var box = el('div', 'lc-insight');
  box.appendChild(tile(iconName, tone, 32));
  var p = el('div');
  parts.forEach(function(t){ p.appendChild(typeof t === 'string' ? document.createTextNode(t) : t); });
  if(action){ var b = el('button', null, action.label); b.type = 'button'; b.addEventListener('click', action.run); p.appendChild(document.createTextNode(' ')); p.appendChild(b); }
  box.appendChild(p);
  return box;
}

function renderInsights(o, s, base){
  var box = $('lcInsights'); box.textContent = '';
  var f = FREQ[o.freq];

  // 1. Extra payments
  if(o.extra > 0 && base.count > s.count){
    box.appendChild(insight('piggy-bank', 'green', [
      'Your extra ', el('b', null, money(o.extra) + ' ' + f.short), ' saves ', el('b', null, money(base.interest - s.interest)),
      ' in interest and pays the loan off ', el('b', null, span(base.count - s.count, s.n) + ' sooner'), '.'
    ]));
  } else if(o.extra <= 0 && s.count > 3 && o.rate > 0){
    var e = niceExtra(s.pay, o.freq);
    var t = schedule(Object.assign({}, o, { extra:e }));
    if(s.interest - t.interest >= 50){
      box.appendChild(insight('piggy-bank', 'green', [
        'Paying ', el('b', null, money(e) + ' more ' + f.short), ' would save ', el('b', null, money(s.interest - t.interest)),
        ' and finish ', el('b', null, span(s.count - t.count, s.n) + ' sooner'), '.'
      ], { label:'Try it', run:function(){ F.extra.value = e; render(); } }));
    }
  }

  // 2. A better rate (credit score)
  if(o.rate >= 3){
    var lower = Math.round((o.rate - 2) * 100) / 100;
    var r = schedule(Object.assign({}, o, { rate:lower }));
    if(s.interest - r.interest >= 50){
      box.appendChild(insight('gauge', 'indigo', [
        'With a stronger credit score you could qualify for a lower rate. At ', el('b', null, lower + '%'), ' instead of ', el('b', null, o.rate + '%'),
        ' you\'d save ', el('b', null, money(s.interest - r.interest)), ' in interest.'
      ]));
    }
  }

  // 3. Pay every 2 weeks (accelerated)
  if(o.freq === 'monthly' && o.extra <= 0 && s.count > 12 && o.rate > 0){
    var a = schedule(Object.assign({}, o, { freq:'accelerated' }));
    if(s.interest - a.interest >= 50 && s.count / 12 > a.count / 26){
      box.appendChild(insight('calendar', 'blue', [
        'Paying half of this every 2 weeks (', el('b', null, money2(a.pay)), ') adds up to one extra payment a year. You\'d save ',
        el('b', null, money(s.interest - a.interest)), ' and finish ', el('b', null, span(Math.round((s.count / 12 - a.count / 26) * 12), 12) + ' sooner'), '.'
      ], { label:'Show me', run:function(){ F.freq.value = 'accelerated'; render(); } }));
    }
  }
  box.hidden = !box.childNodes.length;
}

function yearly(s){
  var out = [], n = s.n;
  for(var k = 0; k < s.rows.length; k += n){
    var chunk = s.rows.slice(k, k + n), y = { paid:0, prin:0, int:0, bal:0 };
    chunk.forEach(function(r){ y.paid += r.paid; y.prin += r.prin; y.int += r.int; });
    y.bal = chunk[chunk.length - 1].bal;
    out.push(y);
  }
  return out;
}

function renderTable(s){
  var body = $('lcRows'); body.textContent = '';
  yearly(s).forEach(function(y, idx){
    var tr = el('tr');
    tr.appendChild(el('td', null, 'Year ' + (idx + 1)));
    tr.appendChild(el('td', null, money(y.paid)));
    tr.appendChild(el('td', null, money(y.prin)));
    tr.appendChild(el('td', 'int', money(y.int)));
    tr.appendChild(el('td', null, money(y.bal)));
    body.appendChild(tr);
  });
  setText('lcFootPaid', money(s.paid));
  setText('lcFootPrin', money(s.paid - s.interest));
  setText('lcFootInt', money(s.interest));
}

function renderChart(o, s, base){
  if(typeof window.Chart !== 'function'){ $('lcChartCard').hidden = true; return; }
  var ys = yearly(s), yb = yearly(base), years = Math.max(ys.length, yb.length);
  var labels = ['Now'], cur = [o.P], prev = [o.P];
  for(var k = 0; k < years; k++){
    labels.push('Year ' + (k + 1));
    cur.push(k < ys.length ? ys[k].bal : null);
    prev.push(k < yb.length ? yb[k].bal : null);
  }
  var withBase = o.extra > 0 && base.count > s.count;
  $('lcLegendBase').hidden = !withBase;
  var ctx = $('lcChart').getContext('2d');
  var grad = ctx.createLinearGradient(0, 0, 0, 260);
  grad.addColorStop(0, 'rgba(13,92,155,.18)'); grad.addColorStop(1, 'rgba(13,92,155,0)');
  var sets = [{ label:'Balance', data:cur, borderColor:'#0D5C9B', borderWidth:2.5, tension:.3, fill:true, backgroundColor:grad, pointRadius:years > 15 ? 0 : 3, pointHoverRadius:6, pointBackgroundColor:'#0D5C9B', spanGaps:false }];
  if(withBase) sets.push({ label:'Without extra payments', data:prev, borderColor:'#98A2B3', borderWidth:2, borderDash:[5, 4], tension:.3, fill:false, pointRadius:0, pointHoverRadius:4 });
  var cfg = {
    type:'line',
    data:{ labels:labels, datasets:sets },
    options:{
      responsive:true, maintainAspectRatio:false, animation:{ duration:350 },
      interaction:{ mode:'index', intersect:false },
      plugins:{ legend:{ display:false }, tooltip:{ callbacks:{ label:function(i){ return i.raw == null ? null : ' ' + i.dataset.label + ': ' + money(i.raw); } } } },
      scales:{ x:{ grid:{ display:false }, ticks:{ color:'#9AA4B2', autoSkip:true, maxTicksLimit:8 } }, y:{ beginAtZero:true, grid:{ color:'#F1F3F6' }, border:{ display:false }, ticks:{ color:'#9AA4B2', callback:function(v){ return money(v); }, maxTicksLimit:5 } } }
    }
  };
  if(chart){ chart.data = cfg.data; chart.options = cfg.options; chart.update(); } else chart = new window.Chart(ctx, cfg);
}

function updateNote(o){
  var notes = [];
  if(state.mortgage) notes.push('Canadian mortgages work out interest twice a year, so this uses that rule.');
  if(o.freq === 'accelerated') notes.push('Accelerated means half of the monthly payment every 2 weeks. That adds up to one extra monthly payment each year.');
  if(o.down > 0 && o.amount > o.down) notes.push('You borrow ' + money(o.amount - o.down) + ' after the down payment.');
  setText('lcNote', notes.join(' '));
}

/* ── Amount slider ────────────────────────────────────── */
function syncRange(){
  var a = num(F.amount.value), r = F.amountRange;
  if(!(a > 0)) return;
  var max = +r.max;
  if(a > max || (max > 100000 && a < max / 6)){
    max = Math.max(100000, Math.ceil(a * 2 / 50000) * 50000);
    r.max = max; r.step = max > 200000 ? 5000 : 500;
  }
  r.value = Math.min(a, max);
}
F.amountRange.addEventListener('input', function(){ F.amount.value = this.value; render(); });

/* ── Presets and "use my loan" ────────────────────────── */
function setPreset(key){
  var p = D.presets[key]; if(!p) return;
  state.mortgage = !!p.mortgage;
  F.amount.value = p.amount; F.rate.value = p.rate; F.years.value = p.years; F.months.value = 0;
  F.freq.value = p.freq; F.down.value = ''; F.extra.value = '';
  document.querySelectorAll('[data-lc-preset]').forEach(function(b){ b.setAttribute('aria-pressed', b.getAttribute('data-lc-preset') === key ? 'true' : 'false'); });
  render();
}
document.querySelectorAll('[data-lc-preset]').forEach(function(b){
  b.addEventListener('click', function(){ setPreset(b.getAttribute('data-lc-preset')); });
});

var mine = $('lcMine');
if(mine && D.myLoans){
  D.myLoans.forEach(function(l){
    var b = el('button', null, (l.name || ((D.types[l.type] || {}).label || 'Loan')) + ' ' + money(l.bal));
    b.type = 'button';
    b.addEventListener('click', function(){
      if(l.type === 'mortgage'){ setPreset('mortgage'); }
      F.amount.value = Math.round(l.bal); F.down.value = '';
      document.querySelectorAll('[data-lc-preset]').forEach(function(x){ if(l.type !== 'mortgage') x.setAttribute('aria-pressed', 'false'); });
      render();
      F.rate.focus();
    });
    mine.appendChild(b);
  });
}

/* ── Inputs ───────────────────────────────────────────── */
var timer = null;
form.addEventListener('input', function(e){
  if(e.target === F.amountRange) return;
  clearTimeout(timer); timer = setTimeout(render, 120);
});
form.addEventListener('change', render);
form.addEventListener('submit', function(e){ e.preventDefault(); render(); });

setPreset('car');
})();
