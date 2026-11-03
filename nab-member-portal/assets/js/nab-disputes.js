/**
 * NAB Member Portal — Dispute Letter Builder (v1.14.0)
 * Data: window.nabDisputeData (printed by templates/dispute-center-template.php).
 * Saves go to nab_dispute_save / nab_dispute_status / nab_dispute_delete
 * (inc/disputes.php); each reply carries the full, fresh page data. The
 * letter text itself is written in PHP (nab_dispute_letter_blocks), so the
 * preview here and the downloaded PDF always match.
 * All member-entered text is set with textContent.
 */
(function(){
'use strict';

var D = window.nabDisputeData;
if(!D) return;
var $ = function(id){ return document.getElementById(id); };
var editing = '';   // id of the letter being edited, '' for a new one
var shown = '';     // id of the letter in the preview

/* ── Small helpers ────────────────────────────────────── */
function el(tag, cls, text){
  var e = document.createElement(tag);
  if(cls) e.className = cls;
  if(text !== undefined) e.textContent = text;
  return e;
}
function fmtDate(s){
  if(!/^\d{4}-\d{2}-\d{2}$/.test(s || '')) return '';
  var p = s.split('-');
  return new Date(Date.UTC(+p[0], +p[1] - 1, +p[2], 12)).toLocaleDateString('en-US', { month:'short', day:'numeric', year:'numeric', timeZone:'UTC' });
}
function find(id){ for(var i = 0; i < D.letters.length; i++) if(D.letters[i].id === id) return D.letters[i]; return null; }
function typeOf(l){ return D.types[l.type] || { label:'Dispute', icon:'file-text', tone:'blue', enclose:[] }; }
function bureauOf(l){ return D.bureaus[l.bureau] || { name:'the bureau', url:'#' }; }
function pdfUrl(id){ return D.pdf + '&id=' + encodeURIComponent(id); }
function show(node, on){ node.hidden = !on; }
function scrollTo(node){ node.scrollIntoView({ behavior:'smooth', block:'start' }); }

var STATUS = {
  created:  { chip:'Ready to submit', cls:'dl-chip-created' },
  sent:     { chip:'Waiting for reply', cls:'dl-chip-sent' },
  response: { chip:'Answered', cls:'dl-chip-response' }
};

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
function apply(data){ if(data){ D.letters = data.letters || []; D.profile = data.profile || D.profile; } }

/* ── My letters ───────────────────────────────────────── */
function renderList(){
  var list = $('dlList');
  list.textContent = '';
  show($('dlEmpty'), !D.letters.length);
  D.letters.forEach(function(l){
    var t = typeOf(l), b = bureauOf(l), st = STATUS[l.status] || STATUS.created;
    var li = el('li', 'dl-row');
    var ico = el('span'); ico.innerHTML = window.nabTile(t.icon, t.tone, 40); // icon markup only, no member text
    li.appendChild(ico.firstChild);

    var mid = el('div');
    mid.style.minWidth = '0';
    mid.appendChild(el('div', 'dl-row-t', t.label + ' · ' + b.name));
    var sub = 'Created ' + fmtDate(l.created);
    if(l.status === 'sent' && l.sent) sub += ' · Submitted ' + fmtDate(l.sent);
    if(l.status === 'response' && l.response) sub += ' · Answered ' + fmtDate(l.response);
    var who = l.f && (l.f.creditor || (l.f.items && l.f.items[0] && (l.f.items[0].creditor || l.f.items[0].company)));
    if(who) sub = who + ' · ' + sub;
    mid.appendChild(el('div', 'dl-row-s', sub));
    li.appendChild(mid);

    var act = el('div', 'dl-row-a');
    act.appendChild(el('span', 'dl-chip ' + st.cls, st.chip));
    var view = el('button', 'dl-btn dl-btn-sm', 'View');
    view.type = 'button';
    view.addEventListener('click', function(){ openPreview(l.id, true); });
    act.appendChild(view);
    var dl = el('a', 'dl-btn dl-btn-sm');
    dl.href = pdfUrl(l.id);
    dl.innerHTML = window.nabIcon('download', 14);
    dl.appendChild(document.createTextNode(' PDF'));
    dl.setAttribute('aria-label', 'Download ' + t.label + ' letter as PDF');
    act.appendChild(dl);
    li.appendChild(act);
    list.appendChild(li);
  });
}

/* ── Builder ──────────────────────────────────────────── */
var form = $('dlForm');
function F(n){ return form.elements[n]; }
function checked(n){ var r = form.querySelector('input[name="' + n + '"]:checked'); return r ? r.value : ''; }
function setChecked(n, v){ form.querySelectorAll('input[name="' + n + '"]').forEach(function(r){ r.checked = r.value === v; }); }
function msg(text, ok){ var m = $('dlMsg'); m.textContent = text || ''; m.className = 'dl-msg' + (text ? (ok ? ' ok' : ' err') : ''); }

function fieldLabel(fd){
  var lab = el('label', fd.type === 'textarea' ? 'wide' : '');
  lab.appendChild(document.createTextNode(fd.label + ' '));
  if(fd.optional) lab.appendChild(el('span', 'opt', '(optional)'));
  return lab;
}
function input(fd, name, value){
  var i;
  if(fd.type === 'textarea'){ i = el('textarea'); i.maxLength = 600; }
  else { i = el('input'); i.type = fd.type === 'date' ? 'date' : 'text'; if(fd.type === 'date') i.max = D.today; else i.maxLength = fd.long ? 300 : 120; }
  i.name = name;
  if(fd.ph) i.placeholder = fd.ph;
  i.value = value || '';
  return i;
}

function addRow(fd, box, values){
  var sets = box.querySelectorAll('.dl-rowset');
  if(sets.length >= fd.max) return;
  var set = el('div', 'dl-rowset');
  var head = el('div', 'dl-rowset-h');
  head.appendChild(el('span', 'dl-rowset-n'));
  var x = el('button', 'dl-rowset-x');
  x.type = 'button';
  x.innerHTML = window.nabIcon('x', 16);
  x.setAttribute('aria-label', 'Remove this item');
  x.addEventListener('click', function(){ set.remove(); renumber(fd, box); });
  head.appendChild(x);
  set.appendChild(head);
  var grid = el('div', 'dl-grid');
  fd.cols.forEach(function(c){
    var lab = el('label', c.long ? 'wide' : '');
    lab.appendChild(document.createTextNode(c.label + ' '));
    lab.appendChild(input({ type:c.type || 'text', ph:c.ph, long:c.long }, 'col-' + c.key, values && values[c.key]));
    grid.appendChild(lab);
  });
  set.appendChild(grid);
  box.querySelector('.dl-rows').appendChild(set);
  renumber(fd, box);
}
function renumber(fd, box){
  var sets = box.querySelectorAll('.dl-rowset');
  sets.forEach(function(s, i){
    s.querySelector('.dl-rowset-n').textContent = 'Item ' + (i + 1);
    s.querySelector('.dl-rowset-x').hidden = sets.length < 2;
  });
  box.querySelector('[data-dl-addrow]').hidden = sets.length >= fd.max;
}

function renderFields(type, values){
  var t = D.types[type], wrap = $('dlFields');
  wrap.textContent = '';
  show($('dlTypeSection'), !!t);
  $('dlYouStep').textContent = t ? '4' : '3';
  if(!t) return;
  $('dlTypeHeading').textContent = 'About the ' + (type === 'inquiry' ? 'inquiries' : type === 'personal' ? 'error' : type === 'round1' ? 'items' : 'account');
  values = values || {};
  t.fields.forEach(function(fd){
    if(fd.type === 'rows'){
      var box = el('div', 'wide');
      box.dataset.rows = fd.key;
      box.appendChild(el('div', '', fd.label)).style.cssText = 'font-size:13px;font-weight:600;margin-bottom:8px';
      box.appendChild(el('div', 'dl-rows'));
      var more = el('button', 'dl-btn dl-btn-sm');
      more.type = 'button';
      more.dataset.dlAddrow = '1';
      more.innerHTML = window.nabIcon('plus', 14);
      more.appendChild(document.createTextNode(' Add another'));
      more.style.marginTop = '10px';
      more.addEventListener('click', function(){ addRow(fd, box); });
      box.appendChild(more);
      wrap.appendChild(box);
      var rows = values[fd.key] && values[fd.key].length ? values[fd.key] : [null];
      rows.forEach(function(r){ addRow(fd, box, r); });
      return;
    }
    var lab = fieldLabel(fd);
    var v = values[fd.key];
    if(v === undefined && fd.key === 'dob') v = D.profile.dob;
    lab.appendChild(input(fd, 'x-' + fd.key, v));
    if(fd.hint) lab.appendChild(el('span', 'dl-hint', fd.hint));
    wrap.appendChild(lab);
  });
}

function openBuilder(id, noScroll){
  var l = id ? find(id) : null;
  editing = l ? l.id : '';
  form.reset();
  msg('');
  $('dlFormTitle').textContent = l ? 'Edit your letter' : 'Create a dispute letter';
  $('dlSubmit').lastChild.textContent = l ? ' Update my letter' : ' Create my letter';
  setChecked('type', l ? l.type : '');
  setChecked('bureau', l ? l.bureau : '');
  var who = l ? l.f : D.profile;
  ['name', 'street', 'city', 'province', 'postal'].forEach(function(k){ F(k).value = (who && who[k]) || ''; });
  renderFields(l ? l.type : '', l ? l.f : null);
  show($('dlPreviewCard'), false);
  show($('dlBuilder'), true);
  if(!noScroll) scrollTo($('dlBuilder'));
}
function closeBuilder(){ show($('dlBuilder'), false); editing = ''; }

form.addEventListener('change', function(e){
  if(e.target.name === 'type' || e.target.name === 'bureau') msg('');
  if(e.target.name !== 'type') return;
  renderFields(e.target.value);
});

form.addEventListener('submit', function(e){
  e.preventDefault();
  var type = checked('type'), bureau = checked('bureau');
  if(!type){ msg('Please choose what you are disputing.'); return; }
  if(!bureau){ msg('Please choose Equifax or TransUnion.'); return; }
  var p = new URLSearchParams();
  p.append('action', 'nab_dispute_save');
  p.append('id', editing);
  p.append('type', type);
  p.append('bureau', bureau);
  ['name', 'street', 'city', 'province', 'postal'].forEach(function(k){ p.append('f[' + k + ']', F(k).value.trim()); });
  D.types[type].fields.forEach(function(fd){
    if(fd.type === 'rows'){
      form.querySelectorAll('[data-rows="' + fd.key + '"] .dl-rowset').forEach(function(set, i){
        fd.cols.forEach(function(c){ p.append('f[' + fd.key + '][' + i + '][' + c.key + ']', set.querySelector('[name="col-' + c.key + '"]').value.trim()); });
      });
      return;
    }
    var i = form.querySelector('[name="x-' + fd.key + '"]');
    if(i) p.append('f[' + fd.key + ']', i.value.trim());
  });
  var btn = $('dlSubmit');
  btn.disabled = true;
  msg('');
  post(p).then(function(r){
    btn.disabled = false;
    if(!r.success){ msg((r.data && r.data.message) || 'Something went wrong. Please try again.'); return; }
    apply(r.data.data);
    renderList();
    closeBuilder();
    openPreview(r.data.id, true, r.data.message);
  });
});

/* ── Preview ──────────────────────────────────────────── */
function renderPaper(blocks){
  var paper = $('dlPaper'), ul = null;
  paper.textContent = '';
  (blocks || []).forEach(function(b){
    if(b.t !== 'li') ul = null;
    if(b.t === 'lines'){
      var p = el('p');
      b.s.forEach(function(line, i){ if(i) p.appendChild(el('br')); p.appendChild(document.createTextNode(line)); });
      paper.appendChild(p);
    } else if(b.t === 'p' || b.t === 'b'){
      paper.appendChild(el('p', b.t === 'b' ? 'b' : '', b.s));
    } else if(b.t === 'li'){
      if(!ul){ ul = el('ul'); paper.appendChild(ul); }
      ul.appendChild(el('li', '', b.s));
    } else if(b.t === 'sign'){
      paper.appendChild(el('div', 'sign'));
    }
  });
}

function statusButton(label, status, primary){
  var b = el('button', 'dl-btn' + (primary ? ' dl-btn-primary' : ''), label);
  b.type = 'button';
  b.addEventListener('click', function(){ setStatus(status, b); });
  return b;
}
function renderStatus(l){
  var box = $('dlStatusBtns'), line = $('dlStatusLine'), b = bureauOf(l);
  box.textContent = '';
  if(l.status === 'sent'){
    line.textContent = 'Submitted to ' + b.name + ' on ' + fmtDate(l.sent) + '. We\'ll remind you if you haven\'t heard back in 30 days.';
    box.appendChild(statusButton('I got a reply', 'response', true));
    box.appendChild(statusButton('I haven\'t submitted it yet', 'created'));
  } else if(l.status === 'response'){
    line.textContent = b.name + ' replied on ' + fmtDate(l.response) + '. If the error wasn\'t fixed, you can create a new letter with more evidence.';
    box.appendChild(statusButton('Still waiting for a reply', 'sent'));
  } else {
    line.textContent = 'Not submitted yet.';
    box.appendChild(statusButton('I\'ve submitted it', 'sent', true));
  }
}

function openPreview(id, scroll, note){
  var l = find(id);
  if(!l) return;
  shown = id;
  var t = typeOf(l), b = bureauOf(l);
  $('dlPreviewTitle').textContent = note === 'Your letter is ready.' ? 'Your letter is ready' : t.label;
  $('dlPreviewSub').textContent = t.label + ' · ' + b.name + ' · Created ' + fmtDate(l.created);
  renderPaper(l.blocks);
  $('dlDownload').href = pdfUrl(l.id);
  $('dlBureauName').textContent = b.name;
  $('dlBureauLink').href = b.url;
  $('dlBureauLinkText').textContent = 'Go to ' + b.name;
  var att = $('dlAttach');
  att.textContent = '';
  (t.enclose || []).forEach(function(e){
    if(l.type === 'fraud' && !(l.f && l.f.police) && e.indexOf('Police') === 0) return;
    att.appendChild(el('li', '', e));
  });
  renderStatus(l);
  show($('dlBuilder'), false);
  show($('dlPreviewCard'), true);
  if(scroll) scrollTo($('dlPreviewCard'));
}

function setStatus(status, btn){
  var p = new URLSearchParams();
  p.append('action', 'nab_dispute_status');
  p.append('id', shown);
  p.append('status', status);
  btn.disabled = true;
  post(p).then(function(r){
    btn.disabled = false;
    if(!r.success){ window.alert((r.data && r.data.message) || 'Something went wrong. Please try again.'); return; }
    apply(r.data.data);
    renderList();
    openPreview(shown, false);
  });
}

/* ── Buttons ──────────────────────────────────────────── */
document.addEventListener('click', function(e){
  var t = e.target.closest('[data-dl-new],[data-dl-close],[data-dl-edit],[data-dl-delete]');
  if(!t) return;
  if(t.hasAttribute('data-dl-new')) openBuilder('');
  else if(t.hasAttribute('data-dl-edit')) openBuilder(shown);
  else if(t.hasAttribute('data-dl-close')){ closeBuilder(); show($('dlPreviewCard'), false); scrollTo($('dlLettersCard')); }
  else if(t.hasAttribute('data-dl-delete')){
    if(!shown || !window.confirm('Delete this letter? You can always create it again.')) return;
    var p = new URLSearchParams();
    p.append('action', 'nab_dispute_delete');
    p.append('id', shown);
    t.disabled = true;
    post(p).then(function(r){
      t.disabled = false;
      if(!r.success){ window.alert((r.data && r.data.message) || 'Something went wrong. Please try again.'); return; }
      apply(r.data.data);
      shown = '';
      renderList();
      show($('dlPreviewCard'), false);
      scrollTo($('dlLettersCard'));
    });
  }
});

renderList();
if(!D.letters.length) openBuilder('', true); // first visit: the form is open, no scrolling
})();
