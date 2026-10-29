/**
 * NAB Member Portal — NAB AI Assistant (v1.12.0)
 * Shared by the chat widget (bottom-right corner, printed by inc/ai-assistant.php)
 * and the full AI Assistant page (templates/page-nab-chatbot.php).
 * Settings: window.nabAiSettings. Server: nab_ai_chat (inc/ai-assistant.php).
 *
 * Replies are plain text with simple Markdown. They are escaped first and then
 * formatted here (md()), so nothing the model returns is trusted as HTML.
 * The conversation is kept in sessionStorage, so it carries on from page to
 * page (and from the widget to the full page) until the browser tab is closed.
 */
(function(){
'use strict';

var S = window.nabAiSettings;
if(!S) return;
var KEY = 'nabAiChat_' + S.user, MAX_KEEP = 30;

/* ── NABi mark (same markup as nab_portal_nabi_mark() in inc/icons.php) ── */
function mark(size){
  return '<span class="nab-nabi" style="--nabi:' + (size | 0) + 'px" aria-hidden="true"><svg viewBox="0 0 48 48" width="100%" height="100%" focusable="false">' +
    '<path class="nab-nabi-n" d="M15 33.5V15.5l15.5 18V15.5"/>' +
    '<path class="nab-nabi-spark" d="M37.5 6.5c.45 2.9 2.1 4.55 5 5-2.9.45-4.55 2.1-5 5-.45-2.9-2.1-4.55-5-5 2.9-.45 4.55-2.1 5-5z"/></svg></span>';
}

/* ── Conversation storage ─────────────────────────────── */
function load(){
  try { var h = JSON.parse(sessionStorage.getItem(KEY) || '[]'); return Array.isArray(h) ? h : []; }
  catch(e){ return []; }
}
function save(h){ try { sessionStorage.setItem(KEY, JSON.stringify(h.slice(-MAX_KEEP))); } catch(e){} }

/* ── Markdown → safe HTML ─────────────────────────────── */
function esc(s){ return String(s).replace(/[&<>"']/g, function(c){ return { '&':'&amp;', '<':'&lt;', '>':'&gt;', '"':'&quot;', "'":'&#39;' }[c]; }); }
function inline(s){
  return esc(s)
    .replace(/\[([^\]]+)\]\((https?:\/\/[^\s)]+|mailto:[^\s)]+)\)/g, function(m, t, u){ return '<a href="' + u + '" target="_blank" rel="noopener noreferrer">' + t + '</a>'; })
    .replace(/\*\*([^*]+)\*\*/g, '<strong>$1</strong>')
    .replace(/(^|[^*])\*([^*\s][^*]*)\*/g, '$1<em>$2</em>')
    .replace(/`([^`]+)`/g, '<code>$1</code>')
    .replace(/(^|[\s(])((?:https?:\/\/)[^\s<)]+)/g, function(m, pre, u){ return pre + '<a href="' + u + '" target="_blank" rel="noopener noreferrer">' + u + '</a>'; })
    .replace(/(^|[\s(])([\w.+-]+@[\w-]+\.[\w.-]+\w)/g, '$1<a href="mailto:$2">$2</a>');
}
function md(text){
  var out = [], list = null, para = [];
  function flushPara(){ if(para.length){ out.push('<p>' + para.map(inline).join('<br>') + '</p>'); para = []; } }
  function flushList(){ if(list){ out.push('<' + list.tag + '>' + list.items.map(function(i){ return '<li>' + inline(i) + '</li>'; }).join('') + '</' + list.tag + '>'); list = null; } }
  String(text || '').replace(/\r/g, '').split('\n').forEach(function(line){
    var t = line.trim(), m;
    if(!t){ flushPara(); flushList(); return; }
    if((m = t.match(/^#{1,6}\s+(.*)$/))){ flushPara(); flushList(); out.push('<h4>' + inline(m[1]) + '</h4>'); return; }
    if((m = t.match(/^[-*•]\s+(.*)$/)) || (m = t.match(/^(\d+)[.)]\s+(.*)$/))){
      var tag = /^\d/.test(t) ? 'ol' : 'ul', item = m[2] !== undefined && /^\d/.test(t) ? m[2] : m[1];
      flushPara();
      if(!list || list.tag !== tag){ flushList(); list = { tag:tag, items:[] }; }
      list.items.push(item);
      return;
    }
    if(list && /^\s{2,}/.test(line)){ list.items[list.items.length - 1] += ' ' + t; return; }
    flushList(); para.push(t);
  });
  flushPara(); flushList();
  return out.join('');
}

/* ── Built-in answers (offline mode, or when the AI can't be reached) ── */
function offline(q){
  q = q.toLowerCase();
  if(q.indexOf('utiliz')>-1||q.indexOf('utilis')>-1)return 'Credit utilization is the percentage of available credit you\'re using. Keep it **below 30%** on each card and overall. For the best impact, target **below 10%**. Paying before your statement date reduces what gets reported.';
  if(q.indexOf('dispute')>-1||q.indexOf('error')>-1)return 'To dispute an error: 1) Get your free report from **Equifax.ca** and **TransUnion.ca**. 2) Identify the error. 3) Submit a dispute online with supporting documents. 4) The bureau must investigate within 30 days. Use our **Dispute Center** for ready-made templates.';
  if(q.indexOf('improv')>-1||q.indexOf('boost')>-1||q.indexOf('increas')>-1)return '5 ways to improve your score: 1) **Pay on time every month**. 2) **Keep utilization below 30%**. 3) **Don\'t close old accounts**. 4) **Limit hard inquiries** — space applications 6+ months apart. 5) **Mix credit types** (card + installment loan).';
  if(q.indexOf('miss')>-1||q.indexOf('late')>-1||q.indexOf('how long')>-1)return 'In Canada, negative items stay on your report for **6–7 years** from first delinquency. This includes missed payments, collections, and consumer proposals. Bankruptcies stay for **6–7 years** after discharge. The impact fades significantly after 2–3 years of positive history.';
  if(q.indexOf('equifax')>-1||q.indexOf('transunion')>-1)return '**Equifax** is commonly used by banks and credit cards. **TransUnion** is common for auto loans. Your score may differ slightly between them. Get free annual reports at **Equifax.ca** and **TransUnion.ca**.';
  if(q.indexOf('secured')>-1||q.indexOf('deposit')>-1)return 'A secured card requires a refundable deposit ($200–$500 CAD) as your credit limit. After 12–18 months of on-time payments, most issuers upgrade you to unsecured. Top options: **Neo Financial Secured Visa** and **Home Trust Secured Visa**.';
  if(q.indexOf('card')>-1)return 'The best card depends on your score and goals. For rebuilding: **Neo Financial Secured Visa**. No-fee cashback: **Tangerine Money-Back**. Travel: **CIBC Aventura Visa Infinite**. Use our **Credit Card Finder** for a personalised match.';
  if(q.indexOf('hello')>-1||q.indexOf('hi')>-1||q.indexOf('hey')>-1||q.indexOf('how are')>-1)
    return 'Hi there! I\'m your NAB Credit Assistant. I can help you with credit score improvement, dispute strategies, Canadian credit card recommendations, and more. What would you like to know?';
  if(q.indexOf('score')>-1)
    return 'Your credit score is calculated based on 5 factors: payment history (35%), utilization (30%), length of history (15%), credit mix (10%), and new inquiries (10%). The biggest quick wins are paying on time and keeping utilization below 30%.';
  if(q.indexOf('budget')>-1||q.indexOf('money')>-1||q.indexOf('sav')>-1)
    return 'Good budgeting habits directly improve your credit. Pay all bills on time, keep credit card balances low (under 30% of the limit), and avoid opening too many new accounts at once. Would you like specific tips for your situation?';
  return 'I can help you with credit score improvement, dispute strategies, Canadian credit cards, utilization ratios, and understanding your credit report. What specific area would you like help with today?';
}

/* ── Ask the server ───────────────────────────────────── */
function ask(q, history){
  if(!S.live) return new Promise(function(res){ setTimeout(function(){ res({ text:offline(q) }); }, 600); });
  var body = new URLSearchParams();
  body.append('action', 'nab_ai_chat');
  body.append('nonce', S.nonce);
  body.append('message', q);
  body.append('page', S.page || '');
  body.append('history', JSON.stringify(history.slice(-10)));
  return fetch(S.ajax, { method:'POST', credentials:'same-origin', body:body })
    .then(function(r){ return r.json(); })
    .then(function(d){
      if(d && d.success && d.data && String(d.data.reply || '').trim()) return { text:d.data.reply };
      var code = d && d.data && d.data.code;
      if(code === 'insufficient_quota') return { text:'The AI is temporarily unavailable because the OpenAI account needs billing credits. In the meantime, here is my best answer:\n\n' + offline(q), notice:true };
      if(code === 'rate_limited') return { text:'You\'ve asked a lot of questions in the last hour, so I need a short break. Please try again a little later, or open a ticket in the **Support Center** and a person will help.', notice:true };
      if(code === 'session_expired') return { text:'Your session has expired. Please refresh the page and ask again.', notice:true };
      return { text:offline(q) };
    })
    .catch(function(){ return { text:offline(q) }; });
}

/* ── One chat view (used by the widget and by the full page) ── */
function Chat(o){
  var self = this, busy = false;
  this.o = o;
  this.history = load();

  function bubble(role, html, cls){
    var row = document.createElement('div');
    row.className = 'nab-ai-msg ' + role;
    if(o.avatar) row.appendChild(o.avatar(role));
    var b = document.createElement('div');
    b.className = 'nab-ai-bubble' + (cls ? ' ' + cls : '');
    if(role === 'user') b.textContent = html; else b.innerHTML = html;
    row.appendChild(b);
    o.list.appendChild(row);
    return row;
  }
  function scroll(){ var s = o.scroller || o.list; s.scrollTop = s.scrollHeight; }
  this.renderAll = function(){
    o.list.textContent = '';
    self.history.forEach(function(m){ bubble(m.role === 'user' ? 'user' : 'ai', m.role === 'user' ? m.content : md(m.content)); });
    if(o.intro) o.intro.hidden = self.history.length > 0;
    scroll();
  };
  this.send = function(q){
    q = String(q || '').trim();
    if(!q || busy) return;
    busy = true;
    if(o.intro) o.intro.hidden = true;
    self.history.push({ role:'user', content:q });
    save(self.history);
    bubble('user', q);
    o.input.value = '';
    autosize();
    o.sendBtn.disabled = true;
    var typing = bubble('ai', '<span class="nab-ai-typing" aria-label="Typing"><span></span><span></span><span></span></span>');
    scroll();
    ask(q, self.history).then(function(r){
      typing.remove();
      self.history.push({ role:'assistant', content:r.text });
      save(self.history);
      bubble('ai', md(r.text), r.notice ? 'is-error' : '');
      scroll();
      busy = false;
      o.sendBtn.disabled = !o.input.value.trim();
      if(o.focusAfter) o.input.focus({ preventScroll:true });
    });
  };
  this.reset = function(){ self.history = []; save([]); self.renderAll(); o.input.value = ''; autosize(); };

  function autosize(){ o.input.style.height = 'auto'; o.input.style.height = Math.min(o.input.scrollHeight, 120) + 'px'; o.sendBtn.disabled = busy || !o.input.value.trim(); }
  o.input.addEventListener('input', autosize);
  o.input.addEventListener('keydown', function(e){
    if(e.key === 'Enter' && !e.shiftKey && !e.isComposing){ e.preventDefault(); self.send(o.input.value); }
  });
  if(o.form) o.form.addEventListener('submit', function(e){ e.preventDefault(); self.send(o.input.value); });
  else o.sendBtn.addEventListener('click', function(){ self.send(o.input.value); });
  this.renderAll();
  autosize();
}

window.NabAI = { md:md, offline:offline, ask:ask, Chat:Chat, mark:mark };

/* ── Widget ───────────────────────────────────────────── */
function initWidget(){
  var root = document.getElementById('nabAiw');
  if(!root) return;
  var panel = document.getElementById('nabAiwPanel'), launch = document.getElementById('nabAiwLaunch');
  var input = document.getElementById('nabAiwInput');
  var chat = new Chat({
    list: document.getElementById('nabAiwMsgs'),
    scroller: document.getElementById('nabAiwBody'),
    intro: document.getElementById('nabAiwIntro'),
    input: input,
    form: document.getElementById('nabAiwForm'),
    sendBtn: root.querySelector('.nab-aiw-send'),
    focusAfter: window.matchMedia('(min-width:641px)').matches
  });
  var lastFocus = null, mobile = window.matchMedia('(max-width:640px)');
  function open(){
    lastFocus = document.activeElement;
    root.setAttribute('data-state', 'open');
    panel.hidden = false;
    launch.setAttribute('aria-expanded', 'true');
    if(mobile.matches) document.documentElement.style.overflow = 'hidden';
    chat.renderAll();
    if(!mobile.matches) input.focus({ preventScroll:true });
    try { sessionStorage.setItem(KEY + '_open', '1'); } catch(e){}
  }
  function close(){
    root.setAttribute('data-state', 'closed');
    panel.hidden = true;
    launch.setAttribute('aria-expanded', 'false');
    document.documentElement.style.overflow = '';
    try { sessionStorage.removeItem(KEY + '_open'); } catch(e){}
    if(lastFocus && lastFocus.focus) lastFocus.focus({ preventScroll:true });
  }
  launch.addEventListener('click', open);
  root.addEventListener('click', function(e){
    var c = e.target.closest('[data-aiw-q]');
    if(c){ chat.send(c.getAttribute('data-aiw-q')); return; }
    if(e.target.closest('[data-aiw-close]')){ close(); return; }
    if(e.target.closest('[data-aiw-new]')){ chat.reset(); input.focus({ preventScroll:true }); }
  });
  document.addEventListener('keydown', function(e){ if(e.key === 'Escape' && !panel.hidden) close(); });
  // Anything else on the page can open the chat with data-aiw-open (e.g. the dashboard's mobile "Ask AI" button)
  document.addEventListener('click', function(e){
    var t = e.target.closest('[data-aiw-open]');
    if(t){ e.preventDefault(); open(); }
  });
  if(document.querySelector('.nd-bottomnav')) document.body.classList.add('nab-has-bottomnav');
  // Desktop: keep the chat open while the member moves between pages
  var wasOpen = false;
  try { wasOpen = sessionStorage.getItem(KEY + '_open') === '1'; } catch(e){}
  if(wasOpen && !mobile.matches) open();
}

/* ── Full AI Assistant page ───────────────────────────── */
function initPage(){
  var list = document.getElementById('nabChatMessages');
  if(!list) return;
  var welcome = document.getElementById('nabChatWelcome');
  var thread = document.createElement('div');
  list.appendChild(thread);
  var chat = new Chat({
    list: thread,
    scroller: list,
    intro: welcome,
    input: document.getElementById('nabChatInput'),
    sendBtn: document.getElementById('nabChatSend'),
    focusAfter: true,
    avatar: function(role){
      var a = document.createElement('div');
      a.className = 'nab-chat-msg-avatar' + (role === 'user' ? '' : ' is-nabi');
      if(role === 'user') a.textContent = S.initials || 'M'; else a.innerHTML = mark(32);
      return a;
    }
  });
  window.nabSendPrompt = function(q){ chat.send(q); };
  window.nabClearChat = function(){ chat.reset(); };
  document.addEventListener('click', function(e){
    var p = e.target.closest('[data-chat-q]');
    if(p){ chat.send(p.getAttribute('data-chat-q')); list.scrollIntoView({ behavior:'smooth', block:'nearest' }); }
  });
}

if(document.readyState === 'loading') document.addEventListener('DOMContentLoaded', function(){ initWidget(); initPage(); });
else { initWidget(); initPage(); }
})();
