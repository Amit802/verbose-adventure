/* NAB Staff Portal, screen behaviour.
   v0.1 is a design preview: the reply is sample text and nothing is saved.
   When the real features are connected, replace the sample parts with AJAX calls. */
(function(){
'use strict';
var $=function(id){return document.getElementById(id);};
if($('suggest')){
var replies={
 standard:"Hi Sarah,\n\nThank you for reaching out, and I'm sorry to hear you'd like to leave. I've noted your request to cancel your membership.\n\nAs part of our subscription terms, cancellations need 30 days' notice, so your membership will stay active for the next 30 days and then end automatically. You'll keep full access to your member portal during that time.\n\nOur memberships are non refundable once access has been delivered, so we're not able to refund this week's payment. I know that may not be the answer you were hoping for.\n\nIf you'd prefer to pause instead of cancelling, just reply and we'll set that up for you.\n\nKind regards,\nJessica\nNAB Solutions Customer Care",
 short:"Hi Sarah,\n\nThanks for letting us know. I've noted your request to cancel. Your membership needs 30 days' notice, so it will stay active for the next 30 days and then end automatically, and you'll keep full portal access until then.\n\nWe're not able to refund this week's payment, as memberships are non refundable once access is delivered. If you'd rather pause instead, just reply and we'll set it up.\n\nKind regards,\nJessica",
 friendly:"Hi Sarah,\n\nThanks so much for getting in touch, and I'm really sorry to see you go. I've noted your cancellation request for you.\n\nJust so you know how it works: we need 30 days' notice, so your membership will stay active for the next 30 days and then end on its own. You can keep using everything in your portal until then, so please do!\n\nI completely understand wanting this week back. Unfortunately our memberships aren't refundable once access has been delivered, so we can't refund this week's payment.\n\nIf things are just busy right now, a pause might suit you better than cancelling. Reply anytime and I'll set that up.\n\nTake care,\nJessica\nNAB Solutions Customer Care",
 formal:"Dear Sarah,\n\nThank you for your email. We have received your request to cancel your NAB Solutions membership.\n\nIn accordance with our subscription terms, cancellations require 30 days' written notice. Your membership will therefore remain active, and continue to be billed, for the next 30 days, after which it will end automatically. Your member portal access will remain available during this period.\n\nAs outlined in our refund policy, membership payments are non refundable once access has been delivered, and we are therefore unable to refund this week's payment.\n\nShould you wish to pause your membership instead, please reply to this email and we will arrange it.\n\nKind regards,\nJessica K.\nCustomer Care, NAB Solutions"
};
var tone='standard';
function show(){$('replyText').textContent=replies[tone]}
$('suggest').addEventListener('click',function(){
  $('rEmpty').style.display='none';$('rOut').style.display='none';
  $('rLoad').style.display='flex';
  setTimeout(function(){$('rLoad').style.display='none';$('rOut').style.display='block';show()},1100);
});
$('again').addEventListener('click',function(){$('suggest').click()});
$('clear').addEventListener('click',function(){$('custMsg').value='';$('rOut').style.display='none';$('rEmpty').style.display='grid'});
document.querySelectorAll('[data-tone]').forEach(function(b){b.addEventListener('click',function(){
  tone=b.dataset.tone;document.querySelectorAll('[data-tone]').forEach(function(x){x.classList.toggle('on',x===b)});show();
})});
$('copy').addEventListener('click',function(){
  var t=$('replyText').innerText;
  if(navigator.clipboard)navigator.clipboard.writeText(t).catch(function(){});
  var c=$('copied');c.style.display='inline';setTimeout(function(){c.style.display='none'},2200);
});
}
document.querySelectorAll('.switch').forEach(function(s){s.addEventListener('click',function(){s.classList.toggle('off')})});

/* Ticket page */
if($('useAi')){
  var tr={
    standard:"Hi Sarah,\n\nThank you for reaching out, and I'm sorry to hear you'd like to leave. I've noted your request to cancel.\n\nCancellations need 30 days' notice, so your membership will stay active for the next 30 days and then end automatically, with full access to your portal until then. Our memberships are non refundable once access has been delivered, so we're not able to refund this week's payment.\n\nIf the weekly payment is the main worry right now, we can pause your membership for 2 or 4 weeks instead. Just reply and I'll set it up.\n\nKind regards,\nNAB Solutions Customer Care",
    short:"Hi Sarah,\n\nI've noted your request to cancel. With the 30 days' notice, your membership stays active for 30 days and then ends, and we can't refund this week's payment.\n\nIf you'd rather pause for 2 or 4 weeks instead, just reply and I'll set it up.\n\nKind regards,\nNAB Solutions Customer Care",
    friendly:"Hi Sarah,\n\nThanks for letting us know, and I'm really sorry to see you go. I've noted your cancellation.\n\nHow it works: we need 30 days' notice, so your membership stays active for the next 30 days and then ends on its own, and you can keep using your portal until then. I understand wanting this week back, but unfortunately memberships aren't refundable once access is delivered.\n\nIf the weekly payment is what's making things tight, a pause might help. Reply anytime and I'll sort it for you.\n\nTake care,\nNAB Solutions Customer Care",
    formal:"Dear Sarah,\n\nThank you for your email. We have received your request to cancel your membership.\n\nIn line with our subscription terms, cancellations require 30 days' notice. Your membership will remain active for the next 30 days and end automatically thereafter. As membership payments are non refundable once access has been delivered, we are unable to refund this week's payment.\n\nShould you prefer to pause your membership instead, please reply and we will arrange it.\n\nKind regards,\nCustomer Care, NAB Solutions"
  };
  var tone='standard';
  $('useAi').addEventListener('click',function(){ $('ticketReply').value=tr[tone]; $('ticketReply').focus(); });
  document.querySelectorAll('#aiBox [data-tone]').forEach(function(b){b.addEventListener('click',function(){
    tone=b.dataset.tone; document.querySelectorAll('#aiBox [data-tone]').forEach(function(x){x.classList.toggle('on',x===b)}); $('ticketReply').value=tr[tone];
  })});
}
/* Small toggles used on several screens */
document.querySelectorAll('.chips').forEach(function(g){ g.addEventListener('click',function(e){ var c=e.target.closest('.chip'); if(!c) return; g.querySelectorAll('.chip').forEach(function(x){x.classList.toggle('on',x===c)}); }); });
document.querySelectorAll('.tabs').forEach(function(g){ g.addEventListener('click',function(e){ var c=e.target.closest('.tab'); if(!c) return; g.querySelectorAll('.tab').forEach(function(x){x.classList.toggle('on',x===c)}); }); });
document.querySelectorAll('.vote').forEach(function(v){ v.addEventListener('click',function(){ var b=v.querySelector('b'), on=v.classList.toggle('on'); b.textContent=(+b.textContent)+(on?1:-1); }); });
if($('newTopic')){ $('newTopic').addEventListener('click',function(){ $('newTopicBox').hidden=false; $('newTopicBox').querySelector('input').focus(); }); $('cancelTopic').addEventListener('click',function(){ $('newTopicBox').hidden=true; }); }
})();
