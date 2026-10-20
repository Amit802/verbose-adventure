/**
 * NAB Member Portal — Payment Calendar (v1.11.0)
 * Data: window.nabPayData (printed by templates/page-nab-payments.php).
 * Saves go to nab_cal_bill_save / nab_cal_bill_delete / nab_cal_toggle_paid
 * (inc/payments.php); each reply carries the full, fresh page data.
 * nabPayDates() must follow the same rules as nab_cal_bill_dates() in PHP.
 * All member-entered text is set with textContent.
 */
(function(){
'use strict';

var D = window.nabPayData;
if(!D) return;
var $ = function(id){ return document.getElementById(id); };
var TODAY = D.today;
var view = TODAY.slice(0, 7);   // month on the calendar, 'Y-m'
var selDay = TODAY;             // day shown under the calendar
var NAV_BACK = 12, NAV_AHEAD = 24; // months the calendar can move

/* ── Dates (plain Y-m-d strings, no time zones) ───────── */
function pad(n){ return ('0' + n).slice(-2); }
function ymd(y, m, d){ return y + '-' + pad(m) + '-' + pad(d); }
function parts(s){ var p = s.split('-'); return [+p[0], +p[1], +(p[2] || 1)]; }
function utc(s){ var p = parts(s); return Date.UTC(p[0], p[1] - 1, p[2]); }
function fromUtc(t){ var d = new Date(t); return ymd(d.getUTCFullYear(), d.getUTCMonth() + 1, d.getUTCDate()); }
function addDays(s, n){ return fromUtc(utc(s) + n * 864e5); }
function daysBetween(a, b){ return Math.round((utc(b) - utc(a)) / 864e5); }
function daysIn(y, m){ return new Date(Date.UTC(y, m, 0)).getUTCDate(); }
function addMonths(ym, n){ var p = parts(ym), t = p[0] * 12 + (p[1] - 1) + n; return Math.floor(t / 12) + '-' + pad(t % 12 + 1); }
function monthsBetween(a, b){ var x = parts(a), y = parts(b); return (y[0] - x[0]) * 12 + (y[1] - x[1]); }
function asDate(s){ var p = parts(s); return new Date(Date.UTC(p[0], p[1] - 1, p[2], 12)); }
function fmt(s, o){ o.timeZone = 'UTC'; return asDate(s).toLocaleDateString('en-US', o); }
function fmtShort(s){ return fmt(s, { month:'short', day:'numeric' }); }
function fmtLong(s){ return fmt(s, { weekday:'long', month:'long', day:'numeric' }); }
function fmtMonth(ym){ return fmt(ym + '-01', { month:'long', year:'numeric' }); }
function ordinal(n){ var s = ['th','st','nd','rd'], v = n % 100; return n + (s[(v - 20) % 10] || s[v] || s[0]); }

var DATE_RE = /^\d{4}-\d{2}-\d{2}$/;
/**
 * Due dates of one bill between from and to (inclusive). Mirrors nab_cal_bill_dates():
 * the start date always counts; other dates only from the day the bill was added.
 */
function nabPayDates(b, from, to){
  var s = b.start, a = b.added;
  if(!DATE_RE.test(s || '')) return [];
  var dates = repeatDates(b, s, from, to);
  if(!DATE_RE.test(a || '') || a <= s) return dates;
  return dates.filter(function(d){ return d === s || d >= a; });
}
function repeatDates(b, s, from, to){
  var out = [], f = b.freq || 'monthly';
  if(f === 'once'){ if(s >= from && s <= to) out.push(s); return out; }
  if(f === 'weekly' || f === 'biweekly'){
    var step = f === 'weekly' ? 7 : 14, d = s;
    if(s < from) d = addDays(s, Math.floor(daysBetween(s, from) / step) * step);
    for(var i = 0; i < 400 && d <= to; i++, d = addDays(d, step)) if(d >= from) out.push(d);
    return out;
  }
  var p = parts(s), y = p[0], m = p[1], day = p[2], stepM = f === 'yearly' ? 12 : 1;
  var fp = parts(from), gap = (fp[0] - y) * 12 + (fp[1] - m);
  if(gap > 0){ m += Math.floor(gap / stepM) * stepM; while(m > 12){ m -= 12; y++; } }
  for(var j = 0; j < 400; j++){
    var ds = ymd(y, m, Math.min(day, daysIn(y, m)));
    if(ds > to) break;
    if(ds >= from && ds >= s) out.push(ds);
    m += stepM; while(m > 12){ m -= 12; y++; }
  }
  return out;
}
window.nabPayDates = nabPayDates;

/* ── Status of one payment ────────────────────────────── */
function isPaid(b, d){ return !!(D.paid && D.paid[b.id + '@' + d]); }
function status(b, d){
  if(isPaid(b, d)) return 'paid';
  if(b.autopay) return 'auto';
  if(d < TODAY) return 'overdue';
  if(daysBetween(TODAY, d) <= 3) return 'soon';
  return 'upcoming';
}
// Paid, or autopay whose date has come
function settled(o){ return o.st === 'paid' || (o.st === 'auto' && o.date <= TODAY); }
function statusText(o){
  var n = daysBetween(TODAY, o.date);
  switch(o.st){
    case 'paid':    return 'Paid';
    case 'auto':    return o.date <= TODAY ? 'Paid by autopay' : 'Autopay';
    case 'overdue': return -n === 1 ? '1 day overdue' : -n + ' days overdue';
    case 'soon':    return n === 0 ? 'Due today' : n === 1 ? 'Due tomorrow' : 'Due in ' + n + ' days';
    default:        return 'Upcoming';
  }
}
function occurrences(from, to){
  var list = [];
  (D.bills || []).forEach(function(b){
    nabPayDates(b, from, to).forEach(function(d){ list.push({ bill:b, date:d, st:status(b, d) }); });
  });
  return list.sort(function(a, b){ return a.date < b.date ? -1 : a.date > b.date ? 1 : a.bill.name.localeCompare(b.bill.name); });
}
function total(list){ var t = 0; list.forEach(function(o){ t += +o.bill.amount || 0; }); return t; }

/* ── Small helpers ────────────────────────────────────── */
var cad = new Intl.NumberFormat('en-CA', { style:'currency', currency:'CAD', minimumFractionDigits:0, maximumFractionDigits:2 });
function money(v){ v = +v || 0; return cad.format(v); }
function el(tag, cls, text){ var e = document.createElement(tag); if(cls) e.className = cls; if(text != null) e.textContent = text; return e; }
function setText(id, t){ var e = $(id); if(e) e.textContent = t; return e; }
function icon(n, s){ return typeof window.nabIcon === 'function' ? window.nabIcon(n, s) : ''; }
function catOf(b){ return (D.cats && D.cats[b.cat]) || null; }
function freqText(b){
  var p = parts(b.start);
  switch(b.freq){
    case 'monthly':  return 'Every month on the ' + ordinal(p[2]);
    case 'biweekly': return 'Every 2 weeks';
    case 'weekly':   return 'Every week on ' + fmt(b.start, { weekday:'long' });
    case 'yearly':   return 'Every year on ' + fmtShort(b.start);
    default:         return 'One time';
  }
}
function nextDue(b){
  var d = nabPayDates(b, TODAY, addDays(TODAY, 400));
  for(var i = 0; i < d.length; i++) if(!isPaid(b, d[i])) return d[i];
  return '';
}

/* ── One payment row (day panel + coming up) ──────────── */
function payRow(o, showDate){
  var b = o.bill, cat = catOf(b);
  var li = el('li', 'pc-row');
  var ic = el('span', 'pc-ico', b.name.charAt(0).toUpperCase());
  if(cat) ic.style.background = cat.color;
  ic.setAttribute('aria-hidden', 'true');
  li.appendChild(ic);

  var mid = el('div');
  mid.appendChild(el('div', 'pc-name', b.name));
  var meta = [];
  if(showDate) meta.push(o.date === TODAY ? 'Today' : fmt(o.date, { weekday:'short', month:'short', day:'numeric' }));
  if(cat) meta.push(cat.label);
  meta.push(freqText(b).replace(/ on .*/, ''));
  mid.appendChild(el('div', 'pc-meta', meta.join(' · ')));
  li.appendChild(mid);

  var right = el('div', 'pc-amt', money(b.amount));
  right.appendChild(document.createElement('br'));
  right.appendChild(el('span', 'pc-pill s-' + o.st, statusText(o)));
  li.appendChild(right);

  var act = el('div', 'pc-act');
  if(!b.autopay){
    var paid = o.st === 'paid';
    var btn = el('button', 'pc-tick' + (paid ? ' is-paid' : ''));
    btn.type = 'button';
    btn.innerHTML = icon('check', 15);
    btn.appendChild(document.createTextNode(paid ? 'Paid' : 'Mark paid'));
    btn.setAttribute('aria-pressed', paid ? 'true' : 'false');
    btn.setAttribute('aria-label', (paid ? 'Mark as not paid: ' : 'Mark as paid: ') + b.name + ', ' + fmtShort(o.date));
    btn.setAttribute('data-pc-pay', b.id);
    btn.setAttribute('data-pc-date', o.date);
    act.appendChild(btn);
  }
  li.appendChild(act);
  return li;
}

/* ── Render ───────────────────────────────────────────── */
function render(){
  renderSummary();
  renderAlert();
  renderCalendar();
  renderDay();
  renderUpcoming();
  renderBills();
}

function renderSummary(){
  var p = parts(view), first = view + '-01', last = ymd(p[0], p[1], daysIn(p[0], p[1]));
  var list = occurrences(first, last);
  var done = list.filter(settled), open = list.filter(function(o){ return !settled(o); });
  var late = open.filter(function(o){ return o.st === 'overdue'; });
  var isNow = view === TODAY.slice(0, 7);

  setText('pcDueLabel', isNow ? 'Due this month' : 'Due in ' + fmt(first, { month:'long' }));
  setText('pcDue', money(total(list)));
  setText('pcDueSub', list.length ? list.length + (list.length === 1 ? ' payment' : ' payments') : 'Nothing due');
  setText('pcPaid', money(total(done))).classList.toggle('pc-green', done.length > 0 && !open.length);
  setText('pcPaidSub', list.length ? done.length + ' of ' + list.length + ' paid' : ' ');
  setText('pcLeft', money(total(open))).classList.toggle('pc-red', late.length > 0);
  setText('pcLeftSub', late.length ? money(total(late)) + ' overdue' : list.length && !open.length ? 'All paid' : open.length ? open.length + ' to go' : ' ');

  var up = occurrences(TODAY, addDays(TODAY, 400)).filter(function(o){ return o.st !== 'paid'; })[0];
  setText('pcNext', up ? up.bill.name : 'None');
  setText('pcNextSub', up ? (up.date === TODAY ? 'Today' : fmtShort(up.date)) + ' · ' + money(up.bill.amount) : (D.bills || []).length ? 'Nothing coming up' : 'Add a payment');
}

function renderAlert(){
  var late = occurrences(addDays(TODAY, -60), addDays(TODAY, -1)).filter(function(o){ return o.st === 'overdue'; });
  var soon = occurrences(TODAY, addDays(TODAY, 3)).filter(function(o){ return o.st === 'soon'; });
  var box = $('pcAlert'), msg = '', cls = '';
  if(late.length){
    cls = 'bad';
    msg = late.length === 1
      ? late[0].bill.name + ' (' + money(late[0].bill.amount) + ') was due on ' + fmtShort(late[0].date) + '. If you have paid it, mark it as paid.'
      : late.length + ' payments are overdue, ' + money(total(late)) + ' in total. If you have paid them, mark them as paid.';
  } else if(soon.length){
    cls = 'warn';
    msg = soon.length === 1
      ? soon[0].bill.name + ' (' + money(soon[0].bill.amount) + ') is ' + statusText(soon[0]).toLowerCase() + '.'
      : soon.length + ' payments are due in the next 3 days, ' + money(total(soon)) + ' in total.';
  }
  box.hidden = !msg;
  box.className = 'pc-alert' + (cls ? ' ' + cls : '');
  box.innerHTML = msg ? icon(cls === 'bad' ? 'triangle-alert' : 'clock', 18) : '';
  if(msg) box.appendChild(el('span', null, msg));
}

function renderCalendar(){
  var p = parts(view), y = p[0], m = p[1], n = daysIn(y, m);
  var first = view + '-01', last = ymd(y, m, n);
  var byDay = {};
  occurrences(first, last).forEach(function(o){ (byDay[o.date] = byDay[o.date] || []).push(o); });

  setText('pcMonthLabel', fmtMonth(view));
  var off = monthsBetween(TODAY.slice(0, 7), view);
  document.querySelector('[data-pc-step="-1"]').disabled = off <= -NAV_BACK;
  document.querySelector('[data-pc-step="1"]').disabled = off >= NAV_AHEAD;
  document.querySelector('[data-pc-today]').hidden = off === 0;

  var grid = $('pcGrid');
  grid.textContent = '';
  ['Sun','Mon','Tue','Wed','Thu','Fri','Sat'].forEach(function(w){ var h = el('div', 'pc-wd', w); h.setAttribute('role', 'columnheader'); grid.appendChild(h); });
  var lead = asDate(first).getUTCDay();
  for(var i = 0; i < lead; i++) grid.appendChild(el('div', 'pc-day pc-out'));
  for(var d = 1; d <= n; d++){
    var ds = ymd(y, m, d), items = byDay[ds] || [];
    var cell = el('button', 'pc-day' + (ds === TODAY ? ' is-today' : '') + (ds === selDay ? ' is-sel' : ''));
    cell.type = 'button';
    cell.setAttribute('data-pc-day', ds);
    cell.setAttribute('role', 'gridcell');
    cell.setAttribute('aria-label', fmtLong(ds) + (items.length ? ', ' + items.length + (items.length === 1 ? ' payment' : ' payments') : ''));
    if(ds === selDay) cell.setAttribute('aria-selected', 'true');
    cell.appendChild(el('span', 'pc-num', String(d)));
    if(items.length){
      var chips = el('span', 'pc-chips'), dots = el('span', 'pc-dots');
      items.slice(0, items.length > 3 ? 2 : 3).forEach(function(o){
        chips.appendChild(el('span', 'pc-chip s-' + o.st, o.bill.name + ' ' + money(o.bill.amount)));
      });
      if(items.length > 3) chips.appendChild(el('span', 'pc-more', '+' + (items.length - 2) + ' more'));
      items.slice(0, 4).forEach(function(o){ dots.appendChild(el('i', 's-' + o.st)); });
      cell.appendChild(chips);
      cell.appendChild(dots);
    }
    grid.appendChild(cell);
  }
  var tail = (7 - (lead + n) % 7) % 7;
  for(var t = 0; t < tail; t++) grid.appendChild(el('div', 'pc-day pc-out'));
}

function renderDay(){
  var items = occurrences(selDay, selDay);
  setText('pcDayTitle', (selDay === TODAY ? 'Today, ' : '') + fmtLong(selDay));
  var list = $('pcDayList');
  list.textContent = '';
  if(!items.length){ list.appendChild(el('li', 'pc-none', 'No payments on this day.')); return; }
  items.forEach(function(o){ list.appendChild(payRow(o, false)); });
}

function renderUpcoming(){
  var box = $('pcUpcoming');
  box.textContent = '';
  var late = occurrences(addDays(TODAY, -60), addDays(TODAY, -1)).filter(function(o){ return o.st === 'overdue'; });
  var next = occurrences(TODAY, addDays(TODAY, 30));
  if(!late.length && !next.length){
    box.appendChild(el('p', 'pc-none', (D.bills || []).length ? 'Nothing due in the next 30 days.' : 'Your upcoming payments will show here once you add them.'));
    return;
  }
  function group(title, list){
    if(!list.length) return;
    box.appendChild(el('div', 'pc-group', title));
    var ul = el('ul', 'pc-list');
    list.forEach(function(o){ ul.appendChild(payRow(o, true)); });
    box.appendChild(ul);
  }
  group('Overdue', late);
  group('Next 30 days', next);
}

function renderBills(){
  var bills = (D.bills || []).slice().sort(function(a, b){ return a.name.localeCompare(b.name); });
  $('pcEmpty').hidden = bills.length > 0;
  var monthly = 0;
  bills.forEach(function(b){
    var a = +b.amount || 0;
    monthly += b.freq === 'monthly' ? a : b.freq === 'biweekly' ? a * 26 / 12 : b.freq === 'weekly' ? a * 52 / 12 : b.freq === 'yearly' ? a / 12 : 0;
  });
  setText('pcBillsSub', bills.length
    ? bills.length + (bills.length === 1 ? ' payment' : ' payments') + (monthly ? ' · about ' + money(Math.round(monthly)) + ' a month' : '')
    : 'Bills and payments you want to keep track of');
  var list = $('pcBills');
  list.textContent = '';
  bills.forEach(function(b){
    var cat = catOf(b), li = el('li', 'pc-row');
    var ic = el('span', 'pc-ico', b.name.charAt(0).toUpperCase());
    if(cat) ic.style.background = cat.color;
    ic.setAttribute('aria-hidden', 'true');
    li.appendChild(ic);
    var mid = el('div'), nm = el('div', 'pc-name', b.name);
    if(b.autopay) nm.appendChild(el('span', 'pc-auto-tag', 'Autopay'));
    mid.appendChild(nm);
    var nd = nextDue(b);
    mid.appendChild(el('div', 'pc-meta', freqText(b) + (nd ? ' · next ' + (nd === TODAY ? 'today' : fmtShort(nd)) : b.freq === 'once' ? ' · ' + fmtShort(b.start) : '')));
    li.appendChild(mid);
    li.appendChild(el('div', 'pc-amt', money(b.amount)));
    var act = el('div', 'pc-act'), eb = el('button', 'pc-tick');
    eb.type = 'button';
    eb.innerHTML = icon('pencil', 14);
    eb.appendChild(document.createTextNode('Edit'));
    eb.setAttribute('aria-label', 'Edit ' + b.name);
    eb.setAttribute('data-pc-edit', b.id);
    act.appendChild(eb);
    li.appendChild(act);
    list.appendChild(li);
  });
}

/* ── Server calls ─────────────────────────────────────── */
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
function apply(data){ if(data){ D.bills = data.bills || []; D.paid = data.paid || {}; } }

/* ── Form ─────────────────────────────────────────────── */
var card = $('pcFormCard'), form = $('pcForm'), closeTimer = null;
function F(n){ return form.elements[n]; }
function msg(text, ok){ var m = form.querySelector('.pc-msg'); m.textContent = text || ''; m.className = 'pc-msg' + (text ? (ok ? ' ok' : ' err') : ''); }
function hint(){
  var s = F('start').value;
  setText('pcRepeatHint', /^\d{4}-\d{2}-\d{2}$/.test(s) ? freqText({ start:s, freq:F('freq').value }) + (F('freq').value === 'once' ? ', on ' + fmtLong(s) : '') + '.' : ' ');
}
form.addEventListener('input', hint);
form.addEventListener('change', hint);

function openForm(id){
  clearTimeout(closeTimer);
  var b = (D.bills || []).filter(function(x){ return x.id === id; })[0] || null;
  msg('');
  F('id').value = b ? b.id : '';
  F('name').value = b ? b.name : '';
  F('amount').value = b && +b.amount ? b.amount : '';
  F('freq').value = b ? b.freq : 'monthly';
  F('start').value = b ? b.start : (selDay >= TODAY ? selDay : TODAY);
  F('cat').value = b ? (b.cat || '') : '';
  F('autopay').checked = !!(b && b.autopay);
  setText('pcFormTitle', b ? 'Edit ' + b.name : 'Add a payment');
  setText('pcStartLabel', b ? 'First due date' : 'Next due date');
  form.querySelector('[data-pc-delete]').hidden = !b;
  hint();
  card.hidden = false;
  if(!b){ try { F('name').focus({ preventScroll:true }); } catch(e){} }
  card.scrollIntoView({ behavior:'smooth', block:'start' });
}
function closeForm(scrollTo){
  card.hidden = true;
  if(scrollTo) $(scrollTo).scrollIntoView({ behavior:'smooth', block:'start' });
}

form.addEventListener('submit', function(e){
  e.preventDefault();
  if(!F('name').value.trim()){ msg('Please give the payment a name.', false); F('name').focus(); return; }
  if(!/^\d{4}-\d{2}-\d{2}$/.test(F('start').value)){ msg('Please choose the due date.', false); F('start').focus(); return; }
  var btn = form.querySelector('button[type=submit]'), label = btn.textContent;
  btn.disabled = true; btn.textContent = 'Saving…';
  var params = new URLSearchParams(new FormData(form));
  params.append('action', 'nab_cal_bill_save');
  post(params).then(function(d){
    btn.disabled = false; btn.textContent = label;
    if(d && d.success){
      apply(d.data.data);
      // show the month of the date just saved
      var id = F('id').value;
      var saved = id ? D.bills.filter(function(x){ return x.id === id; })[0] : D.bills[D.bills.length - 1];
      selDay = saved ? (nextDue(saved) || saved.start) : TODAY;
      if(Math.abs(monthsBetween(TODAY.slice(0, 7), selDay.slice(0, 7))) <= NAV_AHEAD) view = selDay.slice(0, 7);
      render();
      msg('✓ ' + (d.data.message || 'Saved.'), true);
      clearTimeout(closeTimer);
      closeTimer = setTimeout(function(){ closeForm('pcCalCard'); }, 700);
    } else msg((d && d.data && d.data.message) || 'Could not save. Please try again.', false);
  });
});

function del(){
  var id = F('id').value;
  if(!id || !window.confirm('Delete this payment and its history?')) return;
  var btn = form.querySelector('[data-pc-delete]');
  btn.disabled = true;
  var params = new URLSearchParams();
  params.append('action', 'nab_cal_bill_delete');
  params.append('id', id);
  post(params).then(function(d){
    btn.disabled = false;
    if(d && d.success){ apply(d.data.data); render(); closeForm('pcBillsCard'); }
    else msg((d && d.data && d.data.message) || 'Could not delete. Please try again.', false);
  });
}

function togglePaid(btn){
  var id = btn.getAttribute('data-pc-pay'), date = btn.getAttribute('data-pc-date');
  var b = (D.bills || []).filter(function(x){ return x.id === id; })[0];
  if(!b) return;
  var nowPaid = !isPaid(b, date);
  btn.disabled = true;
  var params = new URLSearchParams();
  params.append('action', 'nab_cal_toggle_paid');
  params.append('id', id);
  params.append('date', date);
  params.append('paid', nowPaid ? '1' : '');
  post(params).then(function(d){
    if(d && d.success){ apply(d.data.data); render(); }
    else { btn.disabled = false; window.alert((d && d.data && d.data.message) || 'Could not update. Please try again.'); }
  });
}

/* ── Clicks ───────────────────────────────────────────── */
document.addEventListener('click', function(e){
  var t = e.target;
  var day = t.closest('[data-pc-day]');
  if(day){ selDay = day.getAttribute('data-pc-day'); renderCalendar(); renderDay(); return; }
  var pay = t.closest('[data-pc-pay]');
  if(pay){ togglePaid(pay); return; }
  var ed = t.closest('[data-pc-edit]');
  if(ed){ openForm(ed.getAttribute('data-pc-edit')); return; }
  if(t.closest('[data-pc-add]')){ openForm(null); return; }
  if(t.closest('[data-pc-close]')){ closeForm(); return; }
  if(t.closest('[data-pc-delete]')){ del(); return; }
  var st = t.closest('[data-pc-step]');
  if(st && !st.disabled){
    view = addMonths(view, parseInt(st.getAttribute('data-pc-step'), 10));
    selDay = view === TODAY.slice(0, 7) ? TODAY : view + '-01';
    var firstWith = occurrences(view + '-01', addDays(addMonths(view, 1) + '-01', -1))[0];
    if(view !== TODAY.slice(0, 7) && firstWith) selDay = firstWith.date;
    renderSummary(); renderCalendar(); renderDay();
    return;
  }
  if(t.closest('[data-pc-today]')){ view = TODAY.slice(0, 7); selDay = TODAY; renderSummary(); renderCalendar(); renderDay(); }
});

render();
})();
