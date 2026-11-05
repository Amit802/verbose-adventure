/**
 * NAB Member Portal — Dispute Letter Builder (v1.14.0, reworked in v1.18.0)
 * Data: window.nabDisputeData (printed by templates/dispute-center-template.php).
 * Saves go to nab_dispute_save / _status / _notes / _delete / _upload /
 * _file_delete (inc/disputes.php); each reply carries the full, fresh page
 * data. The letter text itself is written in PHP (nab_dispute_letter_blocks),
 * so the preview here and the downloaded PDF always match.
 * All member-entered text is set with textContent.
 */
(function(){
'use strict';

var D = window.nabDisputeData;
if(!D) return;
var $ = function(id){ return document.getElementById(id); };
var editing = '';   // id of the letter being edited, '' for a new one
var shown = '';     // id of the letter in the preview
var picked = [];    // files chosen in the builder, uploaded after the letter is saved

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
function fmtSize(n){ return n > 1048576 ? (n / 1048576).toFixed(1) + ' MB' : Math.max(1, Math.round(n / 1024)) + ' KB'; }
function find(id){ for(var i = 0; i < D.letters.length; i++) if(D.letters[i].id === id) return D.letters[i]; return null; }
function typeOf(l){ return D.types[l.type] || D.types.general; }
function bureauOf(l){ return D.bureaus[l.bureau] || { name:'the bureau', url:'#' }; }
function pdfUrl(id){ return D.pdf + '&id=' + encodeURIComponent(id); }
function fileUrl(id, f){ return D.fileUrl + '&id=' + encodeURIComponent(id) + '&file=' + encodeURIComponent(f); }
function show(node, on){ node.hidden = !on; }
function scrollTo(node){ node.scrollIntoView({ behavior:'smooth', block:'start' }); }
function icon(n, s){ return window.nabIcon ? window.nabIcon(n, s) : ''; }
function setMsg(node, text, ok){ node.textContent = text || ''; node.className = node.className.replace(/\s*\b(ok|err)\b/g, '') + (text ? (ok ? ' ok' : ' err') : ''); }

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
function fail(r){ return (r && r.data && r.data.message) || 'Something went wrong. Please try again.'; }

/* ── My letters ───────────────────────────────────────── */
function renderList(){
  var list = $('dlList');
  list.textContent = '';
  show($('dlEmpty'), !D.letters.length);
  D.letters.forEach(function(l){
    var t = typeOf(l), b = bureauOf(l), st = D.statuses[l.status] || D.statuses.draft;
    var li = el('li', 'dl-row');
    var ico = el('span'); ico.innerHTML = window.nabTile(t.icon, t.tone, 40); // icon markup only, no member text
    li.appendChild(ico.firstChild);

    var mid = el('div');
    mid.style.minWidth = '0';
    mid.appendChild(el('div', 'dl-row-t', t.label + ' · ' + b.name));
    var items = (l.f && l.f.items) || [];
    var first = items[0] && (items[0].creditor || items[0].company || '');
    var bits = [];
    if(first) bits.push(first + (items.length > 1 ? ' +' + (items.length - 1) + ' more' : ''));
    bits.push('Created ' + fmtDate(l.created));
    if(l.sent) bits.push('Sent ' + fmtDate(l.sent));
    if(l.response) bits.push('Response ' + fmtDate(l.response));
    mid.appendChild(el('div', 'dl-row-s', bits.join(' · ')));
    li.appendChild(mid);

    var act = el('div', 'dl-row-a');
    act.appendChild(el('span', 'dl-chip dl-chip-' + l.status, st.label));
    var view = el('button', 'dl-btn dl-btn-sm', 'Open');
    view.type = 'button';
    view.addEventListener('click', function(){ openPreview(l.id, true); });
    act.appendChild(view);
    var dl = el('a', 'dl-btn dl-btn-sm');
    dl.href = pdfUrl(l.id);
    dl.innerHTML = icon('download', 14);
    dl.appendChild(document.createTextNode(' PDF'));
    dl.setAttribute('aria-label', 'Download ' + t.label + ' letter as PDF');
    dl.addEventListener('click', function(){ afterDownload(l.id); });
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
function msg(text, ok){ setMsg($('dlMsg'), text, ok); }

function control(fd, value, ph){
  var i;
  if(fd.long){ i = el('textarea'); i.maxLength = 500; i.rows = 2; }
  else { i = el('input'); i.type = fd.type === 'date' ? 'date' : 'text'; if(fd.type === 'date') i.max = D.today; else i.maxLength = 120; }
  i.dataset.key = fd.key;
  if(ph) i.placeholder = ph;
  i.value = value || '';
  return i;
}
function fieldLabel(fd, value, ph){
  var lab = el('label', fd.long ? 'wide' : '');
  lab.appendChild(document.createTextNode(fd.label + ' '));
  if(!fd.req) lab.appendChild(el('span', 'opt', '(optional)'));
  lab.appendChild(control(fd, value, ph));
  if(fd.hint) lab.appendChild(el('span', 'dl-hint', fd.hint));
  return lab;
}

var NOUN = {
  accounts:  ['account', 'Account details', 'Add each account or creditor you are disputing. You can add as many as you need, up to 10.'],
  inquiries: ['inquiry', 'Inquiry details', 'Add each inquiry you are disputing. You can add as many as you need, up to 10.'],
  personal:  ['detail', 'What is incorrect', 'Add each personal detail that is wrong on your report.']
};

function addItem(type, values){
  var t = D.types[type], box = $('dlItems');
  if(box.children.length >= D.maxItems) return null;
  var card = el('div', 'dl-item');
  var head = el('div', 'dl-item-h');
  head.appendChild(el('span', 'dl-item-n'));
  var x = el('button', 'dl-item-x');
  x.type = 'button';
  x.innerHTML = icon('x', 16);
  x.setAttribute('aria-label', 'Remove');
  x.addEventListener('click', function(){ card.remove(); renumber(type); });
  head.appendChild(x);
  card.appendChild(head);
  var grid = el('div', 'dl-grid');
  t.fields.forEach(function(fd){ grid.appendChild(fieldLabel(fd, values && values[fd.key], (t.ph || {})[fd.key])); });
  card.appendChild(grid);
  box.appendChild(card);
  renumber(type);
  return card;
}
function renumber(type){
  var t = D.types[type], cards = $('dlItems').children, noun = NOUN[t.mode][0];
  Array.prototype.forEach.call(cards, function(c, i){
    c.querySelector('.dl-item-n').textContent = noun.charAt(0).toUpperCase() + noun.slice(1) + ' ' + (i + 1);
    c.querySelector('.dl-item-x').hidden = cards.length < 2;
  });
  $('dlAddItem').hidden = cards.length >= D.maxItems;
}

function renderFields(type, f){
  var t = D.types[type];
  $('dlItems').textContent = '';
  $('dlExtras').textContent = '';
  show($('dlTypeSection'), !!t);
  $('dlYouStep').textContent = t ? '4' : '3';
  $('dlDocsStep').textContent = t ? '5' : '4';
  if(!t) return;
  var noun = NOUN[t.mode];
  $('dlItemsHeading').textContent = noun[1];
  $('dlItemsLead').textContent = noun[2];
  $('dlAddItem').lastChild.textContent = 'Add another ' + noun[0];
  var items = f && f.items && f.items.length ? f.items : [null];
  items.forEach(function(it){ addItem(type, it); });
  (t.extras || []).forEach(function(fd){ $('dlExtras').appendChild(fieldLabel(fd, f && f[fd.key], '')); });
}

function renderPicked(){
  var ul = $('dlPicked');
  ul.textContent = '';
  picked.forEach(function(file, i){
    var li = el('li');
    li.innerHTML = icon('file-text', 14);
    li.appendChild(document.createTextNode(' ' + file.name + ' (' + fmtSize(file.size) + ') '));
    var x = el('button', 'dl-item-x');
    x.type = 'button';
    x.innerHTML = icon('x', 14);
    x.setAttribute('aria-label', 'Remove ' + file.name);
    x.addEventListener('click', function(){ picked.splice(i, 1); renderPicked(); });
    li.appendChild(x);
    ul.appendChild(li);
  });
}
$('dlDocs').addEventListener('change', function(){
  Array.prototype.forEach.call(this.files, function(file){
    if(picked.length >= D.maxFiles) return;
    if(file.size > D.maxFileMb * 1048576){ msg(file.name + ' is larger than ' + D.maxFileMb + ' MB.'); return; }
    picked.push(file);
  });
  this.value = '';
  renderPicked();
});

/** Opens the form: empty, to edit a letter (id), or prefilled from another letter (copyFrom). */
function openBuilder(id, noScroll, copyFrom){
  var l = id ? find(id) : null, src = l || (copyFrom ? find(copyFrom) : null);
  editing = l ? l.id : '';
  form.reset();
  picked = [];
  renderPicked();
  msg('');
  $('dlFormTitle').textContent = l ? 'Edit your letter' : (src ? 'Create a follow-up letter' : 'Create a dispute letter');
  $('dlSubmit').lastChild.textContent = l ? 'Save and review' : 'Review my letter';
  setChecked('type', src ? src.type : '');
  setChecked('bureau', src ? src.bureau : '');
  var who = src ? src.f : D.profile;
  ['name', 'street', 'city', 'province', 'postal'].forEach(function(k){ F(k).value = (who && who[k]) || ''; });
  renderFields(src ? src.type : '', src ? src.f : null);
  show($('dlPreviewCard'), false);
  show($('dlBuilder'), true);
  if(!noScroll) scrollTo($('dlBuilder'));
}
function closeBuilder(){ show($('dlBuilder'), false); editing = ''; }

function collectItems(){
  var out = [];
  Array.prototype.forEach.call($('dlItems').children, function(card){
    var o = {};
    card.querySelectorAll('[data-key]').forEach(function(i){ o[i.dataset.key] = i.value.trim(); });
    out.push(o);
  });
  return out;
}

form.addEventListener('change', function(e){
  if(e.target.name === 'type' || e.target.name === 'bureau') msg('');
  if(e.target.name !== 'type') return;
  // keep what was typed when switching to a type that uses the same fields
  var before = collectItems().filter(function(it){ return Object.keys(it).some(function(k){ return it[k]; }); });
  renderFields(e.target.value, { items: before });
});
$('dlAddItem').addEventListener('click', function(){
  var type = checked('type');
  if(!type) return;
  var card = addItem(type);
  if(card) card.querySelector('input,textarea').focus();
});

function uploadAll(id, files, kind){
  // one request per file keeps each under the server's upload limit
  var errors = [];
  return files.reduce(function(chain, file){
    return chain.then(function(){
      var p = new FormData();
      p.append('action', 'nab_dispute_upload');
      p.append('id', id);
      p.append('kind', kind);
      p.append('file', file, file.name);
      return post(p).then(function(r){ if(r.success) apply(r.data.data); else errors.push(file.name + ': ' + fail(r)); });
    });
  }, Promise.resolve()).then(function(){ return errors; });
}

form.addEventListener('submit', function(e){
  e.preventDefault();
  var type = checked('type'), bureau = checked('bureau');
  if(!type){ msg('Please choose what you are disputing.'); return; }
  if(!bureau){ msg('Please choose one bureau: Equifax or TransUnion.'); return; }
  var p = new URLSearchParams();
  p.append('action', 'nab_dispute_save');
  p.append('id', editing);
  p.append('type', type);
  p.append('bureau', bureau);
  ['name', 'street', 'city', 'province', 'postal'].forEach(function(k){ p.append('f[' + k + ']', F(k).value.trim()); });
  collectItems().forEach(function(it, i){
    Object.keys(it).forEach(function(k){ p.append('f[items][' + i + '][' + k + ']', it[k]); });
  });
  $('dlExtras').querySelectorAll('[data-key]').forEach(function(i){ p.append('f[' + i.dataset.key + ']', i.value.trim()); });
  var btn = $('dlSubmit');
  btn.disabled = true;
  msg('');
  post(p).then(function(r){
    if(!r.success){ btn.disabled = false; msg(fail(r)); return null; }
    apply(r.data.data);
    var id = r.data.id;
    if(!picked.length) return [id, []];
    msg('Saving your documents…', true);
    return uploadAll(id, picked.slice(), 'evidence').then(function(errs){ return [id, errs]; });
  }).then(function(res){
    if(!res) return;
    btn.disabled = false;
    picked = [];
    renderList();
    closeBuilder();
    openPreview(res[0], true);
    if(res[1].length) setMsg($('dlFilesMsg'), res[1].join(' '), false);
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
    } else if(b.t === 'item'){
      var box = el('div', 'item');
      box.appendChild(el('div', 'item-t', b.n + '. ' + b.title));
      b.rows.forEach(function(r){ box.appendChild(el('div', 'item-r', r[0] + ': ' + r[1])); });
      paper.appendChild(box);
    } else if(b.t === 'sign'){
      paper.appendChild(el('div', 'sign'));
    }
  });
}

function renderChecks(l){
  var ul = $('dlChecks'), list = l.checks || [];
  ul.textContent = '';
  ul.className = 'dl-checks' + (list.length ? '' : ' ok');
  if(!list.length){
    var ok = el('li'); ok.innerHTML = icon('circle-check', 15); ok.appendChild(document.createTextNode(' Everything needed is filled in.'));
    ul.appendChild(ok);
    return;
  }
  list.forEach(function(c){
    var li = el('li'); li.innerHTML = icon('triangle-alert', 15); li.appendChild(document.createTextNode(' ' + c));
    ul.appendChild(li);
  });
  var fix = el('li', '', 'You can still download it, or click Edit to add the missing details.');
  fix.style.color = 'var(--dl-muted)';
  ul.appendChild(fix);
}

function renderTimeline(l){
  var tl = $('dlTimeline'), idx = { draft:0, ready:1, sent:2, response:3, resolved:4, unresolved:4 }[l.status];
  var steps = [
    ['Draft', l.created ? 'Created ' + fmtDate(l.created) : ''],
    ['Ready to send', l.ready ? 'Downloaded ' + fmtDate(l.ready) : 'Download the PDF'],
    ['Sent', l.sent ? 'Sent ' + fmtDate(l.sent) : 'Submit it to the bureau'],
    ['Response received', l.response ? 'Received ' + fmtDate(l.response) : 'Usually within about 30 days'],
    [l.status === 'unresolved' ? 'Not resolved' : 'Resolved', l.closed ? fmtDate(l.closed) : 'Was the error corrected?']
  ];
  tl.textContent = '';
  steps.forEach(function(s, i){
    var cls = i < idx ? 'done' : (i > idx ? 'todo' : (i === 4 ? (l.status === 'unresolved' ? 'now bad' : 'now good') : 'now'));
    var li = el('li', 'dl-tl ' + cls);
    var dot = el('span', 'dl-tl-dot');
    if(cls === 'done' || cls === 'now good') dot.innerHTML = icon('check', 12);
    if(cls === 'now bad') dot.innerHTML = icon('x', 12);
    li.appendChild(dot);
    var txt = el('div');
    txt.appendChild(el('div', 'dl-tl-t', s[0]));
    if(s[1]) txt.appendChild(el('div', 'dl-tl-d', s[1]));
    li.appendChild(txt);
    tl.appendChild(li);
  });

  var next = $('dlNext');
  next.textContent = '';
  var add = function(label, status, cls){
    var b = el('button', 'dl-btn dl-btn-block ' + (cls || ''), label);
    b.type = 'button';
    b.addEventListener('click', function(){ setStatus(status, b); });
    next.appendChild(b);
  };
  if(l.status === 'draft') add('I\'ve downloaded it', 'ready');
  if(l.status === 'ready') add('I\'ve sent it to the bureau', 'sent', 'dl-btn-primary');
  if(l.status === 'sent') add('I got a response', 'response', 'dl-btn-primary');
  if(l.status === 'response'){ add('The error was corrected', 'resolved', 'dl-btn-primary'); add('It was not corrected', 'unresolved'); }
  if(l.status === 'unresolved'){
    var f = el('button', 'dl-btn dl-btn-block dl-btn-accent', 'Create a follow-up letter');
    f.type = 'button';
    f.addEventListener('click', function(){ openBuilder('', false, l.id); });
    next.appendChild(f);
  }
  $('dlStatusSel').value = l.status;
}

function renderFiles(l){
  var ul = $('dlFiles');
  ul.textContent = '';
  if(!l.files.length){ var none = el('li', 'dl-file', 'No files yet.'); none.style.color = 'var(--dl-faint)'; ul.appendChild(none); return; }
  l.files.forEach(function(file){
    var li = el('li', 'dl-file');
    li.innerHTML = icon('file-text', 15);
    var a = el('a', '', file.name);
    a.href = fileUrl(l.id, file.id);
    li.appendChild(a);
    li.appendChild(el('span', 'dl-file-tag', file.kind === 'response' ? 'Response' : 'Proof'));
    li.appendChild(el('small', '', fmtSize(file.size)));
    var x = el('button', 'dl-item-x');
    x.type = 'button';
    x.innerHTML = icon('trash-2', 14);
    x.setAttribute('aria-label', 'Delete ' + file.name);
    x.addEventListener('click', function(){
      if(!window.confirm('Delete ' + file.name + '?')) return;
      var p = new URLSearchParams();
      p.append('action', 'nab_dispute_file_delete');
      p.append('id', l.id);
      p.append('file', file.id);
      post(p).then(function(r){
        if(!r.success){ setMsg($('dlFilesMsg'), fail(r), false); return; }
        apply(r.data.data); refresh(); setMsg($('dlFilesMsg'), r.data.message, true);
      });
    });
    li.appendChild(x);
    ul.appendChild(li);
  });
}

function openPreview(id, scroll){
  var l = find(id);
  if(!l) return;
  var fresh = shown !== id;
  shown = id;
  var t = typeOf(l), b = bureauOf(l), n = (l.f.items || []).length;
  $('dlPreviewTitle').textContent = l.status === 'draft' ? 'Review your letter' : t.label;
  $('dlPreviewSub').textContent = t.label + ' · ' + b.name + ' · ' + n + ' ' + (n === 1 ? 'item' : 'items') + ' · Created ' + fmtDate(l.created);
  renderPaper(l.blocks);
  renderChecks(l);
  $('dlDownload').href = pdfUrl(l.id);
  $('dlBureauName').textContent = b.name;
  $('dlBureauLink').href = b.url;
  $('dlBureauLinkText').textContent = 'Go to ' + b.name;
  $('dlAttach').textContent = (t.enclose || []).filter(function(e){ return !(l.type === 'fraud' && !(l.f && l.f.police) && e.indexOf('Police') === 0); }).join('. ') + '.';
  renderTimeline(l);
  if(fresh){
    $('dlNotes').value = l.notes || '';
    ['dlNotesMsg', 'dlStatusMsg', 'dlFilesMsg'].forEach(function(m){ setMsg($(m), ''); });
  }
  renderFiles(l);
  show($('dlBuilder'), false);
  show($('dlPreviewCard'), true);
  if(scroll) scrollTo($('dlPreviewCard'));
}
function refresh(){ renderList(); if(shown) openPreview(shown, false); }

function setStatus(status, btn){
  var p = new URLSearchParams();
  p.append('action', 'nab_dispute_status');
  p.append('id', shown);
  p.append('status', status);
  if(btn) btn.disabled = true;
  post(p).then(function(r){
    if(btn) btn.disabled = false;
    if(!r.success){ setMsg($('dlStatusMsg'), fail(r), false); return; }
    apply(r.data.data);
    refresh();
    setMsg($('dlStatusMsg'), r.data.message, true);
  });
}
$('dlStatusSel').addEventListener('change', function(){ setStatus(this.value); });

// Downloading a draft moves it to "Ready to send" on the server; show that once the download starts
function afterDownload(id){
  var l = find(id);
  if(!l || l.status !== 'draft') return;
  setTimeout(function(){ l.status = 'ready'; l.ready = D.today; refresh(); }, 600);
}
$('dlDownload').addEventListener('click', function(){ afterDownload(shown); });

$('dlNotesSave').addEventListener('click', function(){
  var btn = this, p = new URLSearchParams();
  p.append('action', 'nab_dispute_notes');
  p.append('id', shown);
  p.append('notes', $('dlNotes').value);
  btn.disabled = true;
  post(p).then(function(r){
    btn.disabled = false;
    if(!r.success){ setMsg($('dlNotesMsg'), fail(r), false); return; }
    apply(r.data.data);
    renderList();
    setMsg($('dlNotesMsg'), r.data.message, true);
  });
});

$('dlFileIn').addEventListener('change', function(){
  var all = Array.prototype.slice.call(this.files), kind = $('dlFileKind').value, id = shown;
  this.value = '';
  var files = all.filter(function(f){ return f.size <= D.maxFileMb * 1048576; });
  var big = all.length - files.length;
  if(!files.length){ if(big) setMsg($('dlFilesMsg'), 'Files can be up to ' + D.maxFileMb + ' MB.', false); return; }
  setMsg($('dlFilesMsg'), 'Uploading…', true);
  uploadAll(id, files, kind).then(function(errs){
    if(big) errs.push('Files can be up to ' + D.maxFileMb + ' MB.');
    refresh();
    setMsg($('dlFilesMsg'), errs.length ? errs.join(' ') : (files.length === 1 ? 'File saved.' : files.length + ' files saved.'), !errs.length);
  });
});

/* ── Buttons ──────────────────────────────────────────── */
document.addEventListener('click', function(e){
  var t = e.target.closest('[data-dl-new],[data-dl-close],[data-dl-edit],[data-dl-delete]');
  if(!t) return;
  if(t.hasAttribute('data-dl-new')) openBuilder('');
  else if(t.hasAttribute('data-dl-edit')) openBuilder(shown);
  else if(t.hasAttribute('data-dl-close')){ closeBuilder(); show($('dlPreviewCard'), false); shown = ''; scrollTo($('dlLettersCard')); }
  else if(t.hasAttribute('data-dl-delete')){
    if(!shown || !window.confirm('Delete this letter and its saved files? This can\'t be undone.')) return;
    var p = new URLSearchParams();
    p.append('action', 'nab_dispute_delete');
    p.append('id', shown);
    t.disabled = true;
    post(p).then(function(r){
      t.disabled = false;
      if(!r.success){ window.alert(fail(r)); return; }
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
