/**
 * NAB Member Portal — Card Rewards Checker (v1.24.0)
 * Data: window.nabRewardsData (printed by templates/page-nab-card-rewards.php).
 * The member's cards are saved with the nab_rewards_save AJAX action; all
 * maths runs here.
 *
 * How a card's rate is found for a purchase (first match wins):
 *   1. the store's group (brand), e.g. PC cards at Shoppers Drug Mart
 *   2. a category the member chose (Tangerine)
 *   3. the store's category, e.g. groceries
 *   4. the card's base rate
 * Cash cards: amount x rate%. Points cards: amount x points per $1, each
 * point worth cpp cents. A store that only takes some networks (Costco:
 * Mastercard) marks the other cards as not accepted.
 * All text from data or the member is set with textContent.
 */
(function(){
'use strict';

var D = window.nabRewardsData;
if(!D) return;
var $ = function(id){ return document.getElementById(id); };
if(!$('crForm')) return;

var CARDS = D.cards || {};
var CATS = D.cats || {};
var STORES = D.merchants || [];
var mine = { cards: (D.mine && D.mine.cards) || [], custom: (D.mine && D.mine.custom) || [] };
var NET = { visa:'Visa', mastercard:'Mastercard', amex:'Amex' };

/* ── Helpers ──────────────────────────────────────────── */
var cad2 = new Intl.NumberFormat('en-CA', { style:'currency', currency:'CAD', minimumFractionDigits:2, maximumFractionDigits:2 });
var int0 = new Intl.NumberFormat('en-CA', { maximumFractionDigits:0 });
function money(v){ return cad2.format(+v || 0); }
function pct(v){ return (Math.round(v * 100) / 100) + '%'; }
function el(tag, cls, text){ var e = document.createElement(tag); if(cls) e.className = cls; if(text != null) e.textContent = text; return e; }
function icon(name, size){ var s = el('span'); s.style.display = 'inline-flex'; s.innerHTML = typeof window.nabIcon === 'function' ? window.nabIcon(name, size || 16) : ''; return s; }
function lc(s){ return String(s || '').toLowerCase(); }
function catLabel(k){ return CATS[k] || CATS.other || 'Everything else'; }
function fee(v){ return v > 0 ? money(v).replace('.00', '') + ' a year' : 'No annual fee'; }

/* ── Cards ────────────────────────────────────────────── */
function ownedIds(){ return mine.cards.map(function(c){ return c.id; }); }
function cardFor(own){
  if(own.custom){
    return { id: own.id, name: own.name, issuer: 'Your card', network: own.network, fee: 0, kind:'cash', base: +own.rate || 0, cats:{}, brands:{}, caps:{}, note:'', custom:true };
  }
  var c = CARDS[own.id]; if(!c) return null;
  var o = {}; for(var k in c) o[k] = c[k];
  o.id = own.id; o.picks = own.picks || [];
  return o;
}
function myCards(){
  var out = [];
  mine.cards.forEach(function(c){ var x = cardFor(c); if(x) out.push(x); });
  mine.custom.forEach(function(c){ out.push(cardFor({ custom:true, id:c.id, name:c.name, network:c.network, rate:c.rate })); });
  return out;
}

/** What a card earns on this purchase. */
function earn(card, store, amount){
  var r = { card: card, ok: true, value: 0, how: '', cap: '' };
  if(store.only && store.only.indexOf(card.network) < 0){
    r.ok = false;
    r.how = store.name + ' takes ' + store.only.map(function(n){ return NET[n] || n; }).join(' and ') + ' only.';
    return r;
  }
  var rate = card.base, where = 'everything else', key = '';
  var brands = card.brands || {}, cats = card.cats || {};
  var ch = card.choose, picks = card.picks || [];
  if(store.brand && brands[store.brand] != null){
    rate = +brands[store.brand]; where = 'purchases at ' + store.name; key = store.brand;
  } else if(ch && picks.indexOf(store.cat) > -1){
    rate = +ch.rate; where = lc(catLabel(store.cat)) + (card.preview ? ' (if you choose it as a 2% category)' : ' (a category you chose)'); key = store.cat;
  } else if(cats[store.cat] != null){
    rate = +cats[store.cat]; where = lc(catLabel(store.cat)); key = store.cat;
  }
  if(key && card.caps && card.caps[key]) r.cap = card.caps[key];
  if(card.kind === 'points'){
    var pts = amount * rate, cpp = +card.cpp || 1;
    r.value = pts * cpp / 100;
    var worth = cpp === 1 ? '1 point = 1 cent' : int0.format(Math.round(100 / cpp)) + ' points = $1';
    r.how = rate + ' ' + (card.unit || 'points') + ' per $1 on ' + where + ': ' + int0.format(Math.round(pts)) + ' points, worth about ' + money(r.value) + ' (' + worth + ').';
  } else {
    r.value = amount * rate / 100;
    r.how = rate > 0 ? pct(rate) + ' cash back on ' + where + ': ' + money(amount) + ' x ' + pct(rate) + ' = ' + money(r.value) + '.' : 'This card earns no rewards.';
  }
  if(ch && !picks.length && !card.preview) r.how += ' Choose your 2% categories above to include them.';
  return r;
}
function byValue(a, b){ if(a.ok !== b.ok) return a.ok ? -1 : 1; return (b.value - a.value) || ((a.card.fee || 0) - (b.card.fee || 0)); }

/* ── Saving ───────────────────────────────────────────── */
var saveTimer = null;
function save(){
  clearTimeout(saveTimer);
  $('crSaved').textContent = 'Saving...';
  saveTimer = setTimeout(function(){
    if(typeof nabPortal === 'undefined'){ $('crSaved').textContent = 'Session error. Please refresh the page.'; return; }
    var body = new FormData();
    body.append('action', 'nab_rewards_save'); body.append('nonce', nabPortal.nonce);
    body.append('data', JSON.stringify({ cards: mine.cards, custom: mine.custom.map(function(c){ return { name:c.name, network:c.network, rate:c.rate }; }) }));
    fetch(nabPortal.ajax, { method:'POST', credentials:'same-origin', body: body }).then(function(r){ return r.json(); }).then(function(res){
      if(res && res.success){
        if(res.data && res.data.saved){ mine.custom = res.data.saved.custom; }
        $('crSaved').textContent = 'Saved to your account.';
      } else {
        $('crSaved').textContent = (res && res.data && res.data.message) || 'Your cards could not be saved. Please try again.';
      }
    }).catch(function(){ $('crSaved').textContent = 'Your cards could not be saved. Check your connection and try again.'; });
  }, 400);
}

/* ── Your cards ───────────────────────────────────────── */
function renderMine(){
  var box = $('crMine'); box.textContent = '';
  var list = myCards();
  if(!list.length){
    box.appendChild(el('div', 'cr-mine-empty', 'You haven\'t added any cards yet. Tap "Add a card" and pick the cards in your wallet.'));
    return;
  }
  list.forEach(function(card){
    var row = el('div', 'cr-own');
    var top = el('div', 'cr-own-top');
    var txt = el('div', 'cr-own-txt');
    txt.appendChild(el('div', 'cr-own-name', card.name));
    var meta = el('div', 'cr-own-meta');
    meta.appendChild(el('span', 'cr-net', NET[card.network] || card.network));
    meta.appendChild(document.createTextNode(card.custom ? (card.base > 0 ? pct(card.base) + ' cash back on everything' : 'No rewards') : fee(card.fee)));
    txt.appendChild(meta);
    var x = el('button', 'cr-x'); x.type = 'button'; x.setAttribute('aria-label', 'Remove ' + card.name); x.appendChild(icon('x', 16));
    x.addEventListener('click', function(){
      if(card.custom) mine.custom = mine.custom.filter(function(c){ return c.id !== card.id; });
      else mine.cards = mine.cards.filter(function(c){ return c.id !== card.id; });
      renderAll(); save();
    });
    top.appendChild(txt); top.appendChild(x); row.appendChild(top);
    if(card.choose){
      var ch = card.choose, wrap = el('div', 'cr-picks');
      wrap.appendChild(el('div', 'cr-picks-l', ch.note || ('Pick your ' + ch.n + ' categories.')));
      var chips = el('div', 'cr-chips'); chips.setAttribute('role', 'group'); chips.setAttribute('aria-label', 'Your ' + pct(ch.rate) + ' categories');
      ch.options.forEach(function(opt){
        var on = card.picks.indexOf(opt) > -1;
        var b = el('button', 'cr-chip', catLabel(opt)); b.type = 'button'; b.setAttribute('aria-pressed', on ? 'true' : 'false');
        b.addEventListener('click', function(){
          var own = mine.cards.filter(function(c){ return c.id === card.id; })[0]; if(!own) return;
          own.picks = own.picks || [];
          var i = own.picks.indexOf(opt);
          if(i > -1) own.picks.splice(i, 1);
          else { if(own.picks.length >= (ch.max || ch.n)) own.picks.shift(); own.picks.push(opt); }
          renderAll(); save();
        });
        chips.appendChild(b);
      });
      wrap.appendChild(chips); row.appendChild(wrap);
    }
    box.appendChild(row);
  });
}

/* ── Card picker ──────────────────────────────────────── */
function renderList(){
  var q = lc($('crCardSearch').value).trim(), have = ownedIds(), box = $('crList');
  box.textContent = '';
  var ids = Object.keys(CARDS).filter(function(id){
    var c = CARDS[id];
    return !q || lc(c.name + ' ' + c.issuer).indexOf(q) > -1;
  });
  if(!ids.length){ box.appendChild(el('div', 'cr-opt-none', 'No card matches that. You can add it below.')); return; }
  ids.forEach(function(id){
    var c = CARDS[id], owned = have.indexOf(id) > -1;
    var row = el('div', 'cr-opt'); row.setAttribute('role', 'listitem');
    var txt = el('div', 'cr-own-txt');
    txt.appendChild(el('div', 'cr-own-name', c.name));
    var meta = el('div', 'cr-own-meta'); meta.appendChild(el('span', 'cr-net', NET[c.network] || c.network)); meta.appendChild(document.createTextNode(c.issuer + ' · ' + fee(c.fee)));
    txt.appendChild(meta);
    var b = el('button', 'cr-btn cr-btn-sm', owned ? 'Added' : 'Add'); b.type = 'button'; b.disabled = owned;
    if(owned) b.insertBefore(icon('check', 14), b.firstChild);
    b.setAttribute('aria-label', (owned ? 'Already added: ' : 'Add ') + c.name);
    b.addEventListener('click', function(){
      if(ownedIds().indexOf(id) > -1) return;
      mine.cards.push({ id:id, picks:[] });
      renderAll(); save();
    });
    row.appendChild(txt); row.appendChild(b); box.appendChild(row);
  });
}
$('crAddBtn').addEventListener('click', function(){
  var p = $('crPicker'), open = p.hidden;
  p.hidden = !open; this.setAttribute('aria-expanded', open ? 'true' : 'false');
  if(open){ renderList(); $('crCardSearch').focus(); }
});
$('crCardSearch').addEventListener('input', renderList);
$('crCustom').addEventListener('submit', function(e){
  e.preventDefault();
  var f = this.elements, name = f.name.value.trim(), rate = parseFloat(f.rate.value || '0');
  var err = $('crCustomErr'); err.textContent = '';
  if(!name){ err.textContent = 'Enter the card\'s name.'; f.name.focus(); return; }
  if(!isFinite(rate) || rate < 0 || rate > 10){ err.textContent = 'Cash back should be between 0% and 10%.'; f.rate.focus(); return; }
  if(mine.custom.length >= 5){ err.textContent = 'You can add up to 5 cards that aren\'t on the list.'; return; }
  mine.custom.push({ id:'custom-new-' + Date.now(), name:name.slice(0, 60), network:f.network.value, rate:Math.round(rate * 100) / 100 });
  f.name.value = ''; f.rate.value = '';
  renderAll(); save();
});

/* ── Store search ─────────────────────────────────────── */
var storeIn = $('crStore'), sug = $('crSuggest'), catSel = $('crCat'), amountIn = $('crAmount');
var active = -1, shown = [];
function findStore(name){ var n = lc(name).trim(); if(!n) return null; for(var i = 0; i < STORES.length; i++){ if(lc(STORES[i].name) === n) return STORES[i]; } return null; }
function closeSug(){ sug.hidden = true; storeIn.setAttribute('aria-expanded', 'false'); active = -1; }
function pickStore(s){ storeIn.value = s.name; catSel.value = s.cat; closeSug(); update(); }
function renderSug(){
  var q = lc(storeIn.value).trim();
  sug.textContent = ''; active = -1;
  if(!q){ closeSug(); return; }
  var starts = [], has = [];
  STORES.forEach(function(s){ var n = lc(s.name); if(n.indexOf(q) === 0) starts.push(s); else if(n.indexOf(q) > -1) has.push(s); });
  shown = starts.concat(has).slice(0, 8);
  if(!shown.length){ closeSug(); return; }
  shown.forEach(function(s, i){
    var b = el('button'); b.type = 'button'; b.setAttribute('role', 'option'); b.id = 'crSug' + i;
    b.appendChild(document.createTextNode(s.name)); b.appendChild(el('span', '', catLabel(s.cat)));
    b.addEventListener('mousedown', function(e){ e.preventDefault(); pickStore(s); });
    sug.appendChild(b);
  });
  sug.hidden = false; storeIn.setAttribute('aria-expanded', 'true');
}
function moveSug(d){
  if(sug.hidden || !shown.length) return;
  active = (active + d + shown.length) % shown.length;
  [].forEach.call(sug.children, function(b, i){ b.classList.toggle('on', i === active); });
  storeIn.setAttribute('aria-activedescendant', 'crSug' + active);
}
storeIn.addEventListener('input', function(){ renderSug(); var s = findStore(storeIn.value); if(s) catSel.value = s.cat; update(); });
storeIn.addEventListener('keydown', function(e){
  if(e.key === 'ArrowDown'){ e.preventDefault(); moveSug(1); }
  else if(e.key === 'ArrowUp'){ e.preventDefault(); moveSug(-1); }
  else if(e.key === 'Enter'){ e.preventDefault(); if(active > -1) pickStore(shown[active]); else closeSug(); }
  else if(e.key === 'Escape'){ closeSug(); }
});
storeIn.addEventListener('blur', function(){ setTimeout(closeSug, 120); });
catSel.addEventListener('change', update);
amountIn.addEventListener('input', update);
document.querySelectorAll('#crPopular [data-store]').forEach(function(b){
  b.addEventListener('click', function(){ var s = findStore(b.getAttribute('data-store')); if(s) pickStore(s); });
});
$('crForm').addEventListener('submit', function(e){ e.preventDefault(); });

/* ── Results ──────────────────────────────────────────── */
function currentStore(){
  var s = findStore(storeIn.value), cat = catSel.value;
  if(s){
    // The member may change the type of store; the store's own group rules still apply.
    return { name:s.name, cat:cat, brand:s.brand || '', only:s.only || null, tip:s.tip || '' };
  }
  var typed = storeIn.value.trim();
  return { name: typed || catLabel(cat), cat:cat, brand:'', only:null, tip: cat === 'recurring' ? 'Bill rates only count when the bill is charged to your card automatically every month.' : '' };
}
function rankRow(r, pos, opts){
  var li = el('li', 'cr-row' + (opts.best ? ' best' : '') + (r.ok ? '' : ' no'));
  li.appendChild(el('span', 'cr-pos', r.ok ? String(pos) : '-'));
  var mid = el('div');
  mid.appendChild(el('div', 'cr-row-name', r.card.name));
  if(opts.meta) mid.appendChild(el('div', 'cr-own-meta', opts.meta));
  mid.appendChild(el('div', 'cr-row-how', r.how));
  if(r.cap) mid.appendChild(el('div', 'cr-row-cap', r.cap));
  if(opts.actions){ mid.appendChild(opts.actions); }
  li.appendChild(mid);
  var v = el('div', 'cr-row-v' + (opts.more ? ' more' : ''));
  if(r.ok){ v.textContent = money(r.value); if(opts.vsub) v.appendChild(el('small', '', opts.vsub)); }
  else { v.textContent = 'Not taken'; }
  li.appendChild(v);
  return li;
}
function tipBox(text){
  var t = el('div', 'cr-tip'); t.appendChild(icon('info', 16)); t.appendChild(el('span', '', text)); return t;
}

function update(){
  var err = $('crErr'); err.textContent = '';
  var amount = parseFloat(amountIn.value);
  var hero = $('crHero'); hero.textContent = '';
  var rankCard = $('crRankCard'), moreCard = $('crMoreCard');
  if(!isFinite(amount) || amount < 1 || amount > 100000){
    err.textContent = 'Enter an amount between $1 and $100,000.';
    hero.appendChild(el('div', 'cr-hero-empty', 'Enter how much you plan to spend to see your best card.'));
    rankCard.hidden = true; moreCard.hidden = true;
    return;
  }
  amount = Math.round(amount * 100) / 100;
  var store = currentStore();
  var ranked = myCards().map(function(c){ return earn(c, store, amount); }).sort(byValue);
  var best = ranked.filter(function(r){ return r.ok; })[0] || null;

  // Hero
  if(!ranked.length){
    hero.appendChild(el('div', 'cr-hero-l', 'Your best card at ' + store.name));
    hero.appendChild(el('div', 'cr-hero-card', 'Add your cards first'));
    hero.appendChild(el('div', 'cr-hero-empty', 'Add the credit cards you have in step 1. Then we\'ll show which one to use for ' + money(amount) + ' at ' + store.name + '.'));
  } else if(!best){
    hero.appendChild(el('div', 'cr-hero-l', 'Your best card at ' + store.name));
    hero.appendChild(el('div', 'cr-hero-card', 'None of your cards are taken here'));
    hero.appendChild(el('div', 'cr-hero-empty', ranked[0].how + ' You could pay with debit, or see the cards below.'));
  } else {
    hero.appendChild(el('div', 'cr-hero-l', 'Use this card at ' + store.name));
    hero.appendChild(el('div', 'cr-hero-card', best.card.name));
    var v = el('div', 'cr-hero-v', money(best.value)); v.appendChild(el('small', '', 'back on ' + money(amount)));
    hero.appendChild(v);
    hero.appendChild(el('div', 'cr-hero-how', best.how));
    if(best.cap) hero.appendChild(el('div', 'cr-hero-how', best.cap));
    var okCount = ranked.filter(function(r){ return r.ok; }).length;
    if(okCount > 1){
      var second = ranked.filter(function(r){ return r.ok; })[1];
      var diff = best.value - second.value;
      hero.appendChild(el('div', 'cr-hero-foot', diff > 0.004 ? 'That\'s ' + money(diff) + ' more than your next best card, the ' + second.card.name + '.' : 'Your ' + second.card.name + ' earns the same here.'));
    }
  }
  if(store.tip) hero.appendChild(tipBox(store.tip));

  // All my cards
  var list = $('crRank'); list.textContent = '';
  if(ranked.length > 1 || (ranked.length === 1 && !best)){
    rankCard.hidden = false;
    $('crRankSub').textContent = money(amount) + ' at ' + store.name + ', best first';
    var pos = 0;
    ranked.forEach(function(r, i){ if(r.ok) pos++; list.appendChild(rankRow(r, pos, { best: i === 0 && r.ok })); });
  } else {
    rankCard.hidden = true;
  }

  // Cards that would earn more
  var have = ownedIds(), floor = best ? best.value + 0.005 : 0;
  var others = Object.keys(CARDS).filter(function(id){ return have.indexOf(id) < 0; })
    .map(function(id){
      // A card the member doesn't have: if they could choose this store's category, assume they would.
      var ch = CARDS[id].choose;
      var c = cardFor({ id:id, picks: ch && ch.options.indexOf(store.cat) > -1 ? [store.cat] : [] });
      c.preview = true;
      return earn(c, store, amount);
    })
    .filter(function(r){ return r.ok && r.value > 0 && r.value >= floor; })
    .sort(byValue).slice(0, 3);
  var box = $('crMore'); box.textContent = '';
  var anyPaid = false;
  if(!ranked.length){
    $('crMoreTitle').textContent = 'Cards that earn the most at ' + store.name;
    $('crMoreSub').textContent = 'From the cards on our list, for ' + money(amount);
  } else {
    $('crMoreTitle').textContent = 'Cards that would earn more here';
    $('crMoreSub').textContent = best ? 'Compared with your ' + best.card.name + ' for ' + money(amount) + ' at ' + store.name : 'Cards on our list that are taken at ' + store.name;
  }
  if(!others.length){
    box.appendChild(el('li', 'cr-mine-empty', best ? 'Your ' + best.card.name + ' is already one of the best cards on our list for this purchase.' : 'No card on our list earns rewards here.'));
  }
  others.forEach(function(r, i){
    var acts = el('div', 'cr-row-act');
    if(r.card.url){ var a = el('a', 'cr-link', 'See card details'); a.href = r.card.url; a.target = '_blank'; a.rel = 'noopener noreferrer'; acts.appendChild(a); }
    if(r.card.apply){
      anyPaid = true;
      var ap = el('a', 'cr-btn cr-btn-sm cr-btn-primary', 'Apply'); ap.href = r.card.apply; ap.target = '_blank'; ap.rel = 'noopener noreferrer sponsored';
      acts.appendChild(ap); acts.appendChild(el('span', 'cr-paid', 'Paid link'));
    }
    var meta = r.card.issuer + ' · ' + fee(r.card.fee);
    var vsub = best ? '+' + money(r.value - best.value) + ' more' : 'back';
    box.appendChild(rankRow(r, i + 1, { meta: meta, actions: acts, more: !!best, vsub: vsub }));
  });
  $('crDisclose').hidden = !anyPaid;
  moreCard.hidden = false;
}

function renderAll(){
  renderMine();
  if(!$('crPicker').hidden) renderList();
  update();
}
renderAll();
})();
