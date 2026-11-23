/* NAB Staff Portal, screen behaviour.
   All requests go to admin-ajax.php (action=nab_staff, do=<task>) with the page nonce.
   Text from the server or from people is always set with textContent. */
(function () {
'use strict';
var CFG = window.NABSTAFF || {};
var $ = function (id) { return document.getElementById(id); };

function el(tag, cls, text) {
  var e = document.createElement(tag);
  if (cls) e.className = cls;
  if (text != null) e.textContent = text;
  return e;
}
function post(task, data) {
  var body = data instanceof FormData ? data : new FormData();
  if (!(data instanceof FormData) && data) Object.keys(data).forEach(function (k) { body.append(k, data[k]); });
  body.append('action', 'nab_staff');
  body.append('do', task);
  body.append('nonce', CFG.nonce);
  return fetch(CFG.ajax, { method: 'POST', credentials: 'same-origin', body: body })
    .then(function (r) { return r.json().catch(function () { return { success: false, data: { message: 'Something went wrong. Please try again.' } }; }); })
    .then(function (j) {
      if (j && j.success) return j.data;
      throw new Error((j && j.data && j.data.message) || 'Something went wrong. Please try again.');
    }, function () { throw new Error('Connection problem. Please check your internet and try again.'); });
}
function show(node, on) { if (node) node.hidden = !on; }
function badge(text, tone) { return el('span', 'badge ' + (tone || ''), text); }
function initials(name) { var p = String(name || '').trim().split(/\s+/); return ((p[0] || '').charAt(0) + (p[1] || '').charAt(0)).toUpperCase() || '?'; }

/* ── Reply Assistant ─────────────────────────────────────── */
if ($('replyForm')) {
  var tone = 'standard', lastAi = null;
  var memberTimer = null;

  function memberCard(m) {
    var box = $('memberCard'); box.textContent = '';
    if (!m) return;
    var card = el('div', 'mini');
    var av = el('div', 'av', initials(m.name)); av.style.cssText = 'width:32px;height:32px;font-size:12px;background:var(--brand-soft);color:var(--brand)';
    var mid = el('div'); mid.style.flex = '1';
    mid.appendChild(el('b', null, m.name));
    mid.appendChild(el('span', null, [m.memberId, 'Member since ' + m.since, 'Last login ' + m.lastLogin].filter(Boolean).join(' · ')));
    card.appendChild(av); card.appendChild(mid); card.appendChild(badge(m.membership, m.tone));
    box.appendChild(card);
  }
  $('memberEmail').addEventListener('input', function () {
    clearTimeout(memberTimer);
    var v = this.value.trim();
    if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(v)) { memberCard(null); return; }
    memberTimer = setTimeout(function () {
      post('member_brief', { email: v }).then(function (d) {
        if (d.member) memberCard(d.member);
        else { var b = $('memberCard'); b.textContent = ''; b.appendChild(el('p', 'hint', 'No member found with this email. The reply will still work, just without their details.')); }
      }).catch(function () {});
    }, 450);
  });

  function setBusy(on) {
    show($('rLoad'), on); $('rLoad').style.display = on ? 'flex' : 'none';
    $('suggest').disabled = on; $('again').disabled = on;
    document.querySelectorAll('#tones [data-tone]').forEach(function (b) { b.disabled = on; });
  }
  function renderReply(d) {
    lastAi = d;
    $('rSummary').textContent = d.summary || '';
    var tags = $('rTags'); tags.textContent = '';
    (d.tags || []).forEach(function (t) { tags.appendChild(el('span', 'tag', t)); });
    if (d.mood === 'unhappy' || d.mood === 'angry') tags.appendChild(el('span', 'tag warn', d.mood === 'angry' ? 'Upset' : 'Unhappy'));
    show($('rNotCovered'), d.covered === false);
    $('replyText').value = d.reply || '';
    var src = $('rSources'); src.textContent = '';
    if (d.sources && d.sources.length) {
      src.appendChild(document.createTextNode('Based on: '));
      d.sources.slice(0, 4).forEach(function (s) { src.appendChild(el('span', 'src', s)); });
    } else if (d.noDocs) {
      src.appendChild(document.createTextNode('No documents were used. Add our policies in Documents for better replies.'));
    }
    if (d.hidden) src.appendChild(el('span', 'muted', ' · ' + d.hidden + (d.hidden === 1 ? ' number was' : ' numbers were') + ' hidden'));
    if (d.member) memberCard(d.member);
    show($('rEmpty'), false); show($('rErr'), false); show($('rOut'), true);
    autoGrow($('replyText'));
  }
  function suggest() {
    var msg = $('custMsg').value.trim();
    if (msg.length < 5) { $('custMsg').focus(); return; }
    tone = 'standard';
    document.querySelectorAll('#tones [data-tone]').forEach(function (x) { x.classList.toggle('on', x.dataset.tone === 'standard'); });
    show($('rEmpty'), false); show($('rOut'), false); show($('rErr'), false); setBusy(true);
    post('reply', { message: msg, member: $('memberEmail').value.trim(), tone: tone })
      .then(renderReply)
      .catch(function (e) { $('rErr').textContent = e.message; show($('rErr'), true); show($('rEmpty'), !lastAi); })
      .then(function () { setBusy(false); });
  }
  $('replyForm').addEventListener('submit', function (e) { e.preventDefault(); suggest(); });
  $('again').addEventListener('click', suggest);
  $('clear').addEventListener('click', function () {
    $('custMsg').value = ''; $('memberEmail').value = ''; memberCard(null); lastAi = null;
    show($('rOut'), false); show($('rErr'), false); show($('rEmpty'), true); $('custMsg').focus();
  });
  document.querySelectorAll('#tones [data-tone]').forEach(function (b) {
    b.addEventListener('click', function () {
      if (b.dataset.tone === tone || !$('replyText').value.trim()) return;
      var prev = tone; tone = b.dataset.tone;
      document.querySelectorAll('#tones [data-tone]').forEach(function (x) { x.classList.toggle('on', x === b); });
      setBusy(true); show($('rErr'), false);
      post('retone', { reply: $('replyText').value, tone: tone })
        .then(function (d) { $('replyText').value = d.reply; autoGrow($('replyText')); })
        .catch(function (e) {
          tone = prev; document.querySelectorAll('#tones [data-tone]').forEach(function (x) { x.classList.toggle('on', x.dataset.tone === prev); });
          $('rErr').textContent = e.message; show($('rErr'), true);
        })
        .then(function () { setBusy(false); });
    });
  });
  $('copy').addEventListener('click', function () {
    var t = $('replyText').value, c = $('copied');
    var done = function () { c.style.display = 'inline'; setTimeout(function () { c.style.display = 'none'; }, 2200); };
    if (navigator.clipboard && window.isSecureContext) navigator.clipboard.writeText(t).then(done, function () { $('replyText').select(); document.execCommand('copy'); done(); });
    else { $('replyText').select(); document.execCommand('copy'); done(); }
  });
  $('replyText').addEventListener('input', function () { autoGrow(this); });
  // Coming from Member Lookup: fill the member's email
  var pre = new URLSearchParams(location.search).get('member');
  if (pre) { $('memberEmail').value = pre; $('memberEmail').dispatchEvent(new Event('input')); }
}
function autoGrow(t) { if (!t) return; t.style.height = 'auto'; t.style.height = Math.max(300, t.scrollHeight + 4) + 'px'; }

/* ── Member Lookup ───────────────────────────────────────── */
if ($('lookupForm')) {
  var current = null;
  function listInto(ul, rows, emptyText) {
    ul.textContent = '';
    if (!rows || !rows.length) { ul.appendChild(el('li', 'muted', emptyText)); return; }
    rows.forEach(function (r) {
      var li = el('li'), left = el('div');
      if (r.url) { var a = el('a', 'plain', r.title); a.href = r.url; a.target = '_blank'; a.rel = 'noopener'; left.appendChild(a); }
      else left.appendChild(document.createTextNode(r.title));
      left.appendChild(el('small', null, r.detail));
      li.appendChild(left); li.appendChild(badge(r.status, r.tone));
      ul.appendChild(li);
    });
  }
  function openProfile(id) {
    show($('lkErr'), false);
    post('member_profile', { id: id }).then(function (d) {
      var m = d.member; current = m;
      show($('lkResults'), false); show($('lkProfile'), true); show($('pMsg'), false);
      $('pInit').textContent = initials(m.name); $('pName').textContent = m.name; $('pEmail').textContent = m.email;
      $('pId').textContent = m.memberId || '-'; $('pPhone').textContent = m.phone || '-'; $('pSince').textContent = m.since || '-';
      $('pMembership').textContent = m.membership; $('pMembership').className = 'badge ' + m.tone;
      $('pAccess').textContent = m.access; $('pLogin').textContent = m.lastLogin; $('pScore').textContent = m.score;
      $('pReply').href = CFG.staffUrl + 'reply/?member=' + encodeURIComponent(m.email);
      if (m.accessUrl) { $('pAccessLink').href = m.accessUrl; show($('pAccessLink'), true); } else show($('pAccessLink'), false);
      listInto($('pDisputes'), m.disputes, 'No dispute letters.');
      listInto($('pCollections'), m.collections, 'No Collection Assistance requests.');
      $('pTickets').textContent = ''; $('pTickets').appendChild(el('li', 'muted', 'Loading…'));
      post('member_tickets', { id: m.id }).then(function (t) { listInto($('pTickets'), t.tickets, t.note || 'No Freshdesk tickets.'); })
        .catch(function (e) { listInto($('pTickets'), [], e.message); });
    }).catch(function (e) { $('lkErr').textContent = e.message; show($('lkErr'), true); });
  }
  function search(q) {
    show($('lkErr'), false);
    if (q.length < 2) { $('lkErr').textContent = 'Type at least 2 letters.'; show($('lkErr'), true); return; }
    post('member_search', { q: q }).then(function (d) {
      var rows = d.rows || [];
      if (rows.length === 1) { openProfile(rows[0].id); return; }
      show($('lkProfile'), false);
      var box = $('lkResults'); box.textContent = ''; show(box, true);
      if (!rows.length) { box.appendChild(el('p', 'empty-note', 'No member matches "' + q + '". Try their email address or Member ID.')); return; }
      var ul = el('ul', 'tlist');
      rows.forEach(function (r) {
        var li = el('li'), a = el('a'); a.href = '#';
        var av = el('span', 'av xs', initials(r.name));
        var mid = el('div'); mid.appendChild(el('b', null, r.name)); mid.appendChild(el('small', null, [r.email, r.memberId].filter(Boolean).join(' · ')));
        a.appendChild(av); a.appendChild(mid);
        a.addEventListener('click', function (e) { e.preventDefault(); openProfile(r.id); });
        li.appendChild(a); ul.appendChild(li);
      });
      box.appendChild(ul);
    }).catch(function (e) { $('lkErr').textContent = e.message; show($('lkErr'), true); });
  }
  $('lookupForm').addEventListener('submit', function (e) {
    e.preventDefault();
    var q = $('lookupQ').value.trim();
    history.replaceState(null, '', location.pathname + (q ? '?q=' + encodeURIComponent(q) : ''));
    search(q);
  });
  $('pResend').addEventListener('click', function () {
    if (!current || !confirm('Send ' + current.name + ' an email with a link to set a new password?')) return;
    var b = this; b.disabled = true;
    post('member_resend', { id: current.id }).then(function (d) { $('pMsg').textContent = d.message; $('pMsg').className = 'ok-note'; show($('pMsg'), true); })
      .catch(function (e) { $('pMsg').textContent = e.message; $('pMsg').className = 'ok-note bad'; show($('pMsg'), true); })
      .then(function () { b.disabled = false; });
  });
  if ($('lookupQ').value.trim()) search($('lookupQ').value.trim());
}

/* ── Documents ───────────────────────────────────────────── */
if ($('docTable')) {
  var file = $('docFile');
  if (file) {
    file.addEventListener('change', function () { $('dropLabel').textContent = this.files[0] ? this.files[0].name : 'Click to choose a file'; });
    $('docPasteBtn').addEventListener('click', function () { show($('docPasteBox'), $('docPasteBox').hidden); });
    $('docUpload').addEventListener('submit', function (e) {
      e.preventDefault();
      var fd = new FormData(this), msg = $('docMsg');
      if (!file.files[0] && !(fd.get('text') || '').trim()) { msg.textContent = 'Choose a file, or paste the text.'; msg.className = 'form-msg bad'; return; }
      if (!file.files[0]) fd.delete('file');
      $('docSave').disabled = true; msg.textContent = 'Uploading…'; msg.className = 'form-msg';
      post('doc_upload', fd).then(function (d) {
        msg.textContent = d.message; msg.className = 'form-msg ' + (d.noText ? 'bad' : 'ok');
        setTimeout(function () { location.reload(); }, d.noText ? 2600 : 900);
      }).catch(function (e) { msg.textContent = e.message; msg.className = 'form-msg bad'; })
        .then(function () { $('docSave').disabled = false; });
    });
  }
  $('docSearch').addEventListener('input', function () {
    var q = this.value.trim().toLowerCase();
    document.querySelectorAll('#docTable tbody tr').forEach(function (tr) { tr.hidden = q && tr.dataset.title.indexOf(q) === -1; });
  });
  var editing = 0;
  function modal(on) { show($('docModal'), on); document.body.style.overflow = on ? 'hidden' : ''; }
  $('dmClose').addEventListener('click', function () { modal(false); });
  $('docModal').addEventListener('click', function (e) { if (e.target === this) modal(false); });
  $('docTable').addEventListener('click', function (e) {
    var b = e.target.closest('[data-act]'); if (!b) return;
    var tr = b.closest('tr'), id = tr.dataset.id, act = b.dataset.act;
    if (act === 'toggle') {
      if (b.disabled) return;
      b.disabled = true;
      post('doc_toggle', { id: id }).then(function (d) { b.classList.toggle('off', !d.ai); }).catch(function (er) { alert(er.message); }).then(function () { b.disabled = false; });
    } else if (act === 'delete') {
      if (!confirm('Delete "' + tr.querySelector('.doc-title').textContent + '"? This can\'t be undone.')) return;
      post('doc_delete', { id: id }).then(function () { tr.remove(); if (!document.querySelector('#docTable tbody tr')) show($('docEmpty'), true); }).catch(function (er) { alert(er.message); });
    } else if (act === 'view' || act === 'edit') {
      editing = act === 'edit' ? id : 0;
      $('dmTitle').textContent = 'Loading…'; $('dmText').value = ''; $('dmMsg').textContent = '';
      $('dmText').readOnly = act !== 'edit'; show($('dmActions'), act === 'edit'); show($('dmName'), act === 'edit');
      modal(true);
      post('doc_text', { id: id }).then(function (d) {
        $('dmTitle').textContent = act === 'edit' ? 'Edit text' : d.title; $('dmName').value = d.title;
        $('dmText').value = d.text || (act === 'edit' ? '' : 'No text yet.');
        if (act === 'edit') $('dmText').focus();
      }).catch(function (er) { $('dmTitle').textContent = er.message; });
    }
  });
  if ($('dmSave')) $('dmSave').addEventListener('click', function () {
    if (!editing) return;
    var b = this; b.disabled = true; $('dmMsg').textContent = 'Saving…';
    post('doc_save', { id: editing, title: $('dmName').value, text: $('dmText').value })
      .then(function () { $('dmMsg').textContent = 'Saved.'; setTimeout(function () { location.reload(); }, 600); })
      .catch(function (er) { $('dmMsg').textContent = er.message; })
      .then(function () { b.disabled = false; });
  });
}

/* ── Team Discussions ────────────────────────────────────── */
if ($('topics') && window.NABSTAFF_TOPICS) {
  var T = window.NABSTAFF_TOPICS, filter = '';
  function comment(c) {
    var m = el('div', 'msg'); m.appendChild(el('div', 'av sm', c.init));
    var b = el('div', 'bubble'), meta = el('div', 'meta');
    meta.appendChild(el('b', null, c.name)); meta.appendChild(el('span', null, c.when));
    b.appendChild(meta); b.appendChild(document.createTextNode(c.text)); m.appendChild(b);
    return m;
  }
  function topicCard(t) {
    var card = el('div', 'card topic'); card.id = 'topic-' + t.id;
    var vote = el('button', 'vote' + (t.voted ? ' on' : '')); vote.type = 'button'; vote.setAttribute('aria-label', 'Vote');
    vote.innerHTML = '<svg class="i" width="16" height="16" viewBox="0 0 24 24" aria-hidden="true"><path d="m6 15 6-6 6 6"/></svg>';
    var count = el('b', null, String(t.votes)); vote.appendChild(count);
    vote.addEventListener('click', function () {
      vote.disabled = true;
      post('topic_vote', { id: t.id }).then(function (d) { t.votes = d.votes; t.voted = d.voted; count.textContent = d.votes; vote.classList.toggle('on', d.voted); })
        .catch(function (e) { alert(e.message); }).then(function () { vote.disabled = false; });
    });
    var body = el('div', 'topic-body');
    var head = el('div', 'row'); head.style.cssText = 'gap:8px;margin-bottom:4px';
    head.appendChild(el('b', 'topic-title', t.title)); head.appendChild(badge(t.statusLabel, t.statusTone));
    body.appendChild(head);
    if (t.body) body.appendChild(el('p', null, t.body));
    var meta = el('div', 'topic-meta');
    meta.appendChild(el('span', 'tag', t.typeLabel)); meta.appendChild(el('span', null, t.author)); meta.appendChild(el('span', null, t.when));
    var toggle = el('button', 'linkbtn', t.comments.length ? t.comments.length + (t.comments.length === 1 ? ' comment' : ' comments') : 'Comment'); toggle.type = 'button';
    meta.appendChild(toggle);
    if (t.canEdit) {
      var sel = el('select', 'in sm status-sel');
      Object.keys(T.statuses).forEach(function (k) { var o = el('option', null, T.statuses[k]); o.value = k; if (k === t.status) o.selected = true; sel.appendChild(o); });
      sel.addEventListener('change', function () { post('topic_status', { id: t.id, status: sel.value }).then(function (d) { replace(d.topic); }).catch(function (e) { alert(e.message); }); });
      meta.appendChild(sel);
      var del = el('button', 'linkbtn danger', 'Delete'); del.type = 'button';
      del.addEventListener('click', function () { if (confirm('Delete this topic and its comments?')) post('topic_delete', { id: t.id }).then(function () { T.topics = T.topics.filter(function (x) { return x.id !== t.id; }); render(); }).catch(function (e) { alert(e.message); }); });
      meta.appendChild(del);
    }
    body.appendChild(meta);
    var box = el('div', 'comments'); box.hidden = !t.comments.length || !t._open;
    t.comments.forEach(function (c) { box.appendChild(comment(c)); });
    var form = el('form', 'row'); var inp = el('input', 'in sm'); inp.placeholder = 'Write a comment'; inp.style.flex = '1'; inp.maxLength = 2000;
    var send = el('button', 'btn small', 'Comment'); send.type = 'submit';
    form.appendChild(inp); form.appendChild(send); box.appendChild(form);
    form.addEventListener('submit', function (e) {
      e.preventDefault(); if (!inp.value.trim()) return; send.disabled = true;
      post('topic_comment', { id: t.id, text: inp.value }).then(function (d) { d.topic._open = true; replace(d.topic); }).catch(function (er) { alert(er.message); send.disabled = false; });
    });
    toggle.addEventListener('click', function () { box.hidden = !box.hidden; t._open = !box.hidden; if (!box.hidden) inp.focus(); });
    body.appendChild(box);
    card.appendChild(vote); card.appendChild(body);
    return card;
  }
  function replace(t) { T.topics = T.topics.map(function (x) { return x.id === t.id ? t : x; }); render(); }
  function render() {
    var wrap = $('topics'); wrap.textContent = '';
    var list = T.topics.filter(function (t) { return !filter || t.type === filter; });
    list.forEach(function (t) { wrap.appendChild(topicCard(t)); });
    show($('topicsEmpty'), !list.length);
  }
  $('topicFilter').addEventListener('click', function (e) {
    var c = e.target.closest('.chip'); if (!c) return;
    filter = c.dataset.type; this.querySelectorAll('.chip').forEach(function (x) { x.classList.toggle('on', x === c); }); render();
  });
  $('newTopic').addEventListener('click', function () { show($('newTopicBox'), true); $('newTopicBox').elements.title.focus(); });
  $('cancelTopic').addEventListener('click', function () { show($('newTopicBox'), false); });
  $('newTopicBox').addEventListener('submit', function (e) {
    e.preventDefault(); var f = this, btn = f.querySelector('[type=submit]'); btn.disabled = true; $('topicMsg').textContent = '';
    post('topic_create', new FormData(f)).then(function (d) { T.topics.unshift(d.topic); f.reset(); show(f, false); render(); })
      .catch(function (er) { $('topicMsg').textContent = er.message; }).then(function () { btn.disabled = false; });
  });
  render();
  if (location.hash) { var target = document.querySelector(location.hash); if (target) target.scrollIntoView({ block: 'center' }); }
}

/* ── People (managers) ───────────────────────────────────── */
if ($('teamTable') && window.NABSTAFF_TEAM) {
  var TM = window.NABSTAFF_TEAM;
  function teamRow(p) {
    var tr = el('tr'); tr.dataset.id = p.id;
    var td = el('td'), who = el('div', 'file'), av = el('span', 'av xs', p.init), txt = el('div');
    txt.appendChild(el('b', null, p.name + (p.me ? ' (you)' : ''))); txt.appendChild(el('small', 'muted', p.email));
    who.appendChild(av); who.appendChild(txt); td.appendChild(who); tr.appendChild(td);
    var rt = el('td');
    if (p.admin || p.me) rt.appendChild(badge(p.roleLabel, p.admin ? 'blue' : ''));
    else {
      var sel = el('select', 'in sm status-sel'); sel.setAttribute('aria-label', 'Role for ' + p.name);
      Object.keys(TM.roles).forEach(function (k) { var o = el('option', null, TM.roles[k]); o.value = k; if (k === p.role) o.selected = true; sel.appendChild(o); });
      sel.addEventListener('change', function () {
        sel.disabled = true;
        post('team_role', { id: p.id, role: sel.value }).then(function (d) { replaceP(d.person); })
          .catch(function (e) { alert(e.message); sel.value = p.role; sel.disabled = false; });
      });
      rt.appendChild(sel);
    }
    tr.appendChild(rt);
    var lt = el('td'); if (p.lastLogin) lt.textContent = p.lastLogin; else lt.appendChild(badge(p.invited ? 'Invited ' + p.invited + ', not logged in yet' : 'Not logged in yet', 'warn')); tr.appendChild(lt);
    var at = el('td'); at.style.textAlign = 'right'; at.style.whiteSpace = 'nowrap';
    if (!p.admin && !p.me) {
      var rs = el('button', 'linkbtn', 'Resend invite'); rs.type = 'button';
      rs.addEventListener('click', function () {
        rs.disabled = true;
        post('team_resend', { id: p.id }).then(function (d) { replaceP(d.person); flash(d.message, true); })
          .catch(function (e) { flash(e.message, false); rs.disabled = false; });
      });
      var rm = el('button', 'linkbtn danger', 'Remove'); rm.type = 'button';
      rm.addEventListener('click', function () {
        if (!confirm('Remove ' + p.name + ' from the staff portal? They will be logged out and can\'t log in again.')) return;
        rm.disabled = true;
        post('team_remove', { id: p.id }).then(function () { TM.people = TM.people.filter(function (x) { return x.id !== p.id; }); renderTeam(); flash(p.name + ' has been removed.', true); })
          .catch(function (e) { flash(e.message, false); rm.disabled = false; });
      });
      at.appendChild(rs); at.appendChild(document.createTextNode(' · ')); at.appendChild(rm);
    }
    tr.appendChild(at);
    return tr;
  }
  function flash(text, good) { var m = $('teamMsg'); m.textContent = text; m.className = 'form-msg ' + (good ? 'good' : 'bad'); }
  function replaceP(p) { if (!p) return; TM.people = TM.people.map(function (x) { return x.id === p.id ? p : x; }); renderTeam(); }
  function renderTeam() { var b = $('teamTable').tBodies[0]; b.textContent = ''; TM.people.forEach(function (p) { b.appendChild(teamRow(p)); }); }
  renderTeam();
  $('teamAdd').addEventListener('submit', function (e) {
    e.preventDefault(); var f = this, btn = f.querySelector('[type=submit]'); btn.disabled = true; flash('Adding…', true);
    post('team_add', new FormData(f)).then(function (d) {
      if (d.person) { TM.people = TM.people.filter(function (x) { return x.id !== d.person.id; }); TM.people.push(d.person); }
      f.reset(); renderTeam(); flash(d.message, true);
    }).catch(function (er) { flash(er.message, false); }).then(function () { btn.disabled = false; });
  });
}


/* ── Portal Settings: logo ───────────────────────────────── */
if ($('logoForm')) {
  var FROM = { portal: 'Uploaded here', site: 'The website logo (Appearance > Customize > Site Identity)', icon: 'The site icon (no logo set)', none: 'No logo set, showing the NAB mark' };
  function logoMsg(t, good) { var m = $('logoMsg'); m.textContent = t; m.className = 'form-msg ' + (good ? 'good' : 'bad'); }
  function applyLogo(l) {
    $('logoFrom').textContent = FROM[l.source] || '';
    show($('logoReset'), l.source === 'portal');
    var pv = $('logoPreview'); pv.textContent = '';
    if (l.url) { var im = el('img'); im.src = l.url; im.alt = 'Current logo'; pv.appendChild(im); } else pv.appendChild(el('span', 'brand-mark', 'NAB'));
    setTimeout(function () { location.reload(); }, 900);
  }
  $('logoFile').addEventListener('change', function () {
    if (!this.files.length) return;
    var fd = new FormData(); fd.append('logo', this.files[0]); logoMsg('Uploading…', true);
    post('logo_upload', fd).then(function (d) { logoMsg(d.message, true); applyLogo(d.logo); }).catch(function (e) { logoMsg(e.message, false); });
    this.value = '';
  });
  $('logoReset').addEventListener('click', function () {
    if (!confirm('Go back to the website logo?')) return;
    post('logo_reset', {}).then(function (d) { logoMsg(d.message, true); applyLogo(d.logo); }).catch(function (e) { logoMsg(e.message, false); });
  });
}

})();
