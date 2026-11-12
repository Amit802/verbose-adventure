/**
 * NAB Member Portal — Collection Assistance (v1.22.0)
 * Data: window.nabCollectData (printed by templates/page-nab-collections.php).
 * Sends the form to nab_collection_submit, and refreshes request statuses from
 * nab_collection_list (Freshdesk). Member text is set with textContent.
 */
(function(){
'use strict';

var D = window.nabCollectData || { requests: [] };
var $ = function(id){ return document.getElementById(id); };
var form = $('clForm');
if(!form || typeof nabPortal === 'undefined') return;

var cad = new Intl.NumberFormat('en-CA', { style:'currency', currency:'CAD', maximumFractionDigits:2 });
function el(tag, cls, text){ var e = document.createElement(tag); if(cls) e.className = cls; if(text != null) e.textContent = text; return e; }
function icon(n, s){ return typeof window.nabIcon === 'function' ? window.nabIcon(n, s) : ''; }

/* ── Requests list ────────────────────────────────────── */
function renderRequests(list){
  var ul = $('clRequests'); ul.textContent = '';
  $('clEmpty').hidden = list.length > 0;
  list.forEach(function(r){
    var li = el('li'), top = el('div', 'cl-req-top');
    top.appendChild(el('span', 'cl-req-name', r.agency + (r.amount > 0 ? ' · ' + cad.format(r.amount) : '')));
    top.appendChild(el('span', 'cl-pill' + (r.done ? ' done' : ''), r.status));
    li.appendChild(top);
    li.appendChild(el('div', 'cl-req-meta', 'Sent ' + r.date + (r.ticket ? ' · Reference #' + r.ticket : '') + (r.help && r.help.length ? ' · ' + r.help.join(', ') : '')));
    ul.appendChild(li);
  });
}
renderRequests(D.requests || []);

function post(action, data){
  var body = new FormData();
  body.append('action', action); body.append('nonce', nabPortal.nonce);
  if(data) data.forEach(function(v, k){ body.append(k, v); });
  return fetch(nabPortal.ajax, { method:'POST', credentials:'same-origin', body: body }).then(function(r){ return r.json(); });
}

// Fresh statuses from Freshdesk (quietly; the saved list is already shown)
if((D.requests || []).some(function(r){ return r.ticket; })){
  post('nab_collection_list').then(function(res){ if(res && res.success) renderRequests(res.data.requests || []); }).catch(function(){});
}

/* ── Form ─────────────────────────────────────────────── */
var msg = $('clMsg');
function say(text, ok){ msg.className = 'cl-msg ' + (ok ? 'ok' : 'err'); msg.textContent = ''; if(ok) msg.insertAdjacentHTML('afterbegin', icon('circle-check', 16) + ' '); msg.appendChild(document.createTextNode(text)); }
function clearBad(){ form.querySelectorAll('.cl-bad').forEach(function(x){ x.classList.remove('cl-bad'); }); }
function markBad(field){
  if(!field) return;
  var input = form.elements[field];
  var group = form.querySelector('[data-group="' + field + '"]');
  if(group){ group.classList.add('cl-bad'); group.scrollIntoView({ behavior:'smooth', block:'center' }); }
  else if(input && input.classList){ input.classList.add('cl-bad'); input.focus(); }
}

function toggleNotMine(){
  var v = form.querySelector('input[name=mine]:checked');
  $('clNotMine').hidden = !(v && v.value === 'no');
}
form.addEventListener('change', function(e){
  if(e.target.name === 'mine') toggleNotMine();
  var g = e.target.closest('.cl-bad'); if(g) g.classList.remove('cl-bad');
});
form.addEventListener('input', function(e){ if(e.target.classList) e.target.classList.remove('cl-bad'); });

form.addEventListener('submit', function(e){
  e.preventDefault();
  clearBad();
  // Same checks as the server, so the member sees them at once
  var checks = [
    [ !form.elements.agency.value.trim(), 'agency', 'Please enter the name of the collection agency.' ],
    [ !form.querySelector('input[name=mine]:checked'), 'mine', 'Please tell us if this debt is yours.' ],
    [ !form.querySelector('input[name="help[]"]:checked'), 'help', 'Please pick at least one thing we can help with.' ],
    [ !form.elements.consent.checked, 'consent', 'Please tick the box to confirm you understand how we can help.' ]
  ];
  for(var i = 0; i < checks.length; i++){ if(checks[i][0]){ say(checks[i][2], false); markBad(checks[i][1]); return; } }

  var btn = form.querySelector('button[type=submit]');
  btn.disabled = true; say('Sending…', true);
  post('nab_collection_submit', new FormData(form)).then(function(res){
    btn.disabled = false;
    if(res && res.success){
      say(res.data.message, true);
      renderRequests(res.data.requests || []);
      var keep = { name: form.elements.name.value, phone: form.elements.phone.value, time: form.elements.time.value };
      form.reset(); toggleNotMine();
      form.elements.name.value = keep.name; form.elements.phone.value = keep.phone; form.elements.time.value = keep.time;
      $('clRequests').scrollIntoView({ behavior:'smooth', block:'nearest' });
    } else {
      var d = (res && res.data) || {};
      say(d.message || 'Something went wrong. Please try again.', false);
      markBad(d.field);
    }
  }).catch(function(){
    btn.disabled = false;
    say('Connection problem. Please check your internet and try again.', false);
  });
});
})();
