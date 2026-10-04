(function(){
'use strict';



function nabOpenTab(tab){
  document.querySelectorAll('.nab-tab-panel').forEach(function(p){p.classList.remove('active');});
  document.querySelectorAll('.nab-tab-btn').forEach(function(b){b.classList.toggle('active',b.dataset.tab===tab);});
  var panel=document.getElementById('nabTab-'+tab);
  if(panel)panel.classList.add('active');
}

var nabCurrentModule=0;
var nabQuizState={moduleId:0,qIndex:0,score:0,answered:false};

function nabStartModule(mid){
  if(!window.nabEduData)return;
  var mod=nabEduData.find(function(m){return m.id===mid;});
  if(!mod)return;
  nabCurrentModule=mid;
  nabOpenTab('education');
  var grid=document.getElementById('nabEduGrid');
  var player=document.getElementById('nabEduPlayer');
  var quiz=document.getElementById('nabEduQuiz');
  if(grid)grid.style.display='none';
  if(player)player.style.display='block';
  if(quiz)quiz.style.display='none';
  var titleEl=document.getElementById('nabEduPlayerTitle');
  if(titleEl)titleEl.textContent=mod.title;
  var descEl=document.getElementById('nabEduDesc');
  if(descEl){
    descEl.innerHTML='';
    var ov=document.createElement('div');ov.style.marginBottom='14px';
    var ovL=document.createElement('span');ovL.style.cssText='font-size:10px;font-weight:700;color:#0D5C9B;text-transform:uppercase';ovL.textContent='Overview';
    var ovP=document.createElement('p');ovP.style.cssText='margin:8px 0 0;font-size:13px;color:#374151;line-height:1.7';ovP.textContent=mod.overview||'';
    ov.appendChild(ovL);ov.appendChild(ovP);descEl.appendChild(ov);
    if(mod.concepts&&mod.concepts.length){
      var kc=document.createElement('div');kc.style.marginBottom='14px';
      var kcL=document.createElement('span');kcL.style.cssText='font-size:10px;font-weight:700;color:#0D5C9B;text-transform:uppercase';kcL.textContent='Key Concepts';
      var ul=document.createElement('ul');ul.style.cssText='margin:8px 0 0;padding-left:18px';
      mod.concepts.forEach(function(c){var li=document.createElement('li');li.style.cssText='font-size:13px;color:#374151;line-height:1.6;margin-bottom:4px';li.textContent=c;ul.appendChild(li);});
      kc.appendChild(kcL);kc.appendChild(ul);descEl.appendChild(kc);
    }
    if(mod.steps&&mod.steps.length){
      var st=document.createElement('div');
      var stL=document.createElement('span');stL.style.cssText='font-size:10px;font-weight:700;color:#0D5C9B;text-transform:uppercase';stL.textContent='Actionable Steps';
      var ol=document.createElement('ol');ol.style.cssText='margin:8px 0 0;padding-left:18px';
      mod.steps.forEach(function(s){var li=document.createElement('li');li.style.cssText='font-size:13px;color:#374151;line-height:1.6;margin-bottom:4px';li.textContent=s;ol.appendChild(li);});
      st.appendChild(stL);st.appendChild(ol);descEl.appendChild(st);
    }
  }
  var pw=document.getElementById('nabEduPlayerWrap');
  if(pw){
    if(mod.url){
      pw.style.display='';
      pw.setAttribute('data-video-url',mod.url);
      var ytId=mod.url.replace(/.*embed\//,'').replace(/[?&].*/,'');
      var img=pw.querySelector('img');
      if(img)img.src=mod.thumb||('https://img.youtube.com/vi/'+ytId+'/maxresdefault.jpg');
    }else{pw.style.display='none';}
  }
  var qBtn=document.getElementById('nabStartQuizBtn');
  if(qBtn)qBtn.setAttribute('data-mid',mid);
  if(player)window.scrollTo({top:player.offsetTop-20,behavior:'smooth'});
}

function nabEduBack(){
  var pw=document.getElementById('nabEduPlayerWrap');
  if(pw){var ifrEl=pw.querySelector('iframe');if(ifrEl)ifrEl.src='';}
  var grid=document.getElementById('nabEduGrid');
  var player=document.getElementById('nabEduPlayer');
  var quiz=document.getElementById('nabEduQuiz');
  if(grid)grid.style.display='';
  if(player)player.style.display='none';
  if(quiz)quiz.style.display='none';
}

function nabEduShowPlayer(){
  var player=document.getElementById('nabEduPlayer');
  var quiz=document.getElementById('nabEduQuiz');
  if(player)player.style.display='block';
  if(quiz)quiz.style.display='none';
}

function nabStartQuiz(mid){
  var qData=window.nabQuizData&&nabQuizData[mid];
  if(!qData){alert('Quiz coming soon!');return;}
  nabQuizState={moduleId:mid,qIndex:0,score:0,answered:false};
  var player=document.getElementById('nabEduPlayer');
  var quiz=document.getElementById('nabEduQuiz');
  var modTitle=document.getElementById('nabQuizModTitle');
  if(player)player.style.display='none';
  if(quiz)quiz.style.display='block';
  if(modTitle)modTitle.textContent='Quiz: '+qData.title;
  nabRenderQuestion();
}

function nabRenderQuestion(){
  var qData=nabQuizData[nabQuizState.moduleId];
  var q=qData.questions[nabQuizState.qIndex];
  var total=qData.questions.length;
  var pct=Math.round((nabQuizState.qIndex/total)*100);
  var letters=['A','B','C','D'];
  var wrap=document.getElementById('nabQuizWrap');
  if(!wrap)return;
  wrap.innerHTML='';
  var hdr=document.createElement('div');hdr.style.marginBottom='14px';
  hdr.innerHTML='<div style="display:flex;justify-content:space-between;margin-bottom:6px"><span style="font-size:12px;color:#64748b">Question '+(nabQuizState.qIndex+1)+' of '+total+'</span><span style="font-size:12px;color:#64748b">'+nabQuizState.score+' correct</span></div><div class="nab-quiz-bar"><div class="nab-quiz-bar-fill" style="width:'+pct+'%"></div></div>';
  wrap.appendChild(hdr);
  var qEl=document.createElement('div');qEl.className='nab-quiz-q';qEl.textContent=q.q;wrap.appendChild(qEl);
  var hintEl=document.createElement('div');hintEl.className='nab-quiz-hint';hintEl.textContent='Hint: '+q.hint;wrap.appendChild(hintEl);
  var optsDiv=document.createElement('div');optsDiv.className='nab-quiz-opts';
  q.opts.forEach(function(opt,i){
    var btn=document.createElement('button');btn.className='nab-quiz-opt';btn.setAttribute('data-idx',i);btn.type='button';
    var letter=document.createElement('span');letter.className='nab-quiz-opt-letter';letter.textContent=letters[i];
    btn.appendChild(letter);btn.appendChild(document.createTextNode(opt));optsDiv.appendChild(btn);
  });
  wrap.appendChild(optsDiv);
  var fb=document.createElement('div');fb.className='nab-quiz-feedback';fb.id='nabQuizFeedback';fb.style.display='none';wrap.appendChild(fb);
  var nav=document.createElement('div');nav.className='nab-quiz-nav';
  var nextBtn=document.createElement('button');nextBtn.className='nab-quiz-btn';nextBtn.id='nabQuizNext';nextBtn.type='button';nextBtn.disabled=true;
  nextBtn.textContent=nabQuizState.qIndex<total-1?'Next Question':'See Results';
  nav.appendChild(document.createElement('span'));nav.appendChild(nextBtn);wrap.appendChild(nav);
  nabQuizState.answered=false;
}

function nabSelectAnswer(idx){
  if(nabQuizState.answered)return;
  nabQuizState.answered=true;
  var q=nabQuizData[nabQuizState.moduleId].questions[nabQuizState.qIndex];
  var correct=(idx===q.answer);
  if(correct)nabQuizState.score++;
  document.querySelectorAll('.nab-quiz-opt').forEach(function(btn,i){
    if(i===q.answer)btn.classList.add('correct');
    else if(i===idx&&!correct)btn.classList.add('wrong');
    btn.disabled=true;
    var letter=btn.querySelector('.nab-quiz-opt-letter');
    if(letter){if(i===q.answer){letter.style.background='#16a34a';letter.style.color='#fff';}else if(i===idx&&!correct){letter.style.background='#dc2626';letter.style.color='#fff';}}
  });
  var fb=document.getElementById('nabQuizFeedback');
  if(fb){fb.className='nab-quiz-feedback '+(correct?'correct':'wrong');fb.textContent=correct?'Correct! Well done.':'Not quite. The correct answer is highlighted above.';fb.style.display='block';}
  var nextBtn=document.getElementById('nabQuizNext');
  if(nextBtn)nextBtn.disabled=false;
}

function nabNextQuestion(){
  nabQuizState.qIndex++;
  if(nabQuizState.qIndex>=nabQuizData[nabQuizState.moduleId].questions.length)nabShowQuizResult();
  else nabRenderQuestion();
}

function nabShowQuizResult(){
  var total=nabQuizData[nabQuizState.moduleId].questions.length;
  var score=nabQuizState.score;
  var pct=Math.round((score/total)*100);
  var passed=pct>=60;
  var wrap=document.getElementById('nabQuizWrap');
  if(!wrap)return;
  wrap.innerHTML='';
  var res=document.createElement('div');res.className='nab-quiz-result';
  var icon=document.createElement('div');icon.className='nab-quiz-result-icon';icon.textContent=pct===100?'Trophy':passed?'Star':'Book';res.appendChild(icon);
  var scoreEl=document.createElement('div');scoreEl.className='nab-quiz-result-score';scoreEl.textContent=score+' / '+total;res.appendChild(scoreEl);
  var lbl=document.createElement('div');lbl.className='nab-quiz-result-label';lbl.textContent=pct+'% - '+(pct===100?'Perfect!':passed?'Passed':'Keep Learning');res.appendChild(lbl);
  var msg=document.createElement('div');msg.style.cssText='font-size:13px;color:#374151;margin-bottom:20px';msg.textContent=passed?'Great work! You have completed this module.':'Review the video and try again.';res.appendChild(msg);
  if(passed){var cb=document.createElement('button');cb.className='nab-quiz-btn';cb.setAttribute('data-action','complete');cb.setAttribute('data-mid',nabQuizState.moduleId);cb.type='button';cb.style.marginRight='8px';cb.textContent='Mark as Complete & Earn Points';res.appendChild(cb);}
  var rb=document.createElement('button');rb.className='nab-quiz-btn';rb.setAttribute('data-action','retake');rb.setAttribute('data-mid',nabQuizState.moduleId);rb.type='button';rb.style.cssText='background:#f1f5f9;color:#374151;margin-left:8px';rb.textContent='Retake Quiz';res.appendChild(rb);
  wrap.appendChild(res);
}

function nabMarkModuleComplete(mid){
  if(typeof nabPortal==='undefined')return;
  var btn=document.querySelector('[data-action="complete"][data-mid="'+mid+'"]');
  if(btn){btn.disabled=true;btn.textContent='Saving...';}
  fetch(nabPortal.ajax,{method:'POST',headers:{'Content-Type':'application/x-www-form-urlencoded'},body:'action=nab_complete_module&nonce='+nabPortal.nonce+'&module_id='+mid})
  .then(function(r){return r.json();}).then(function(d){
    if(d.success){
      var completed=d.data.completed||0,total=d.data.total||7,pct=Math.round((completed/total)*100);
      document.querySelectorAll('.nab-progress-pct').forEach(function(el){el.textContent=pct+'%';});
      var circ=2*3.14159*28;
      document.querySelectorAll('circle[stroke="#0D5C9B"]').forEach(function(el){el.setAttribute('stroke-dashoffset',Math.round(circ*(1-pct/100)));});
      var pf=document.querySelector('.nab-featured-progress-fill');if(pf)pf.style.width=pct+'%';
      var card=document.querySelector('.nab-edu-card[data-mid="'+mid+'"]');
      if(card){card.classList.add('completed');var badge=card.querySelector('button');if(badge){badge.textContent='Completed';badge.style.background='#dcfce7';badge.style.color='#166534';}}
      var wrap=document.getElementById('nabQuizWrap');
      if(wrap){wrap.innerHTML='';var res=document.createElement('div');res.className='nab-quiz-result';
        res.innerHTML='<div class="nab-quiz-result-score" style="font-size:20px">Module Complete!</div><div class="nab-quiz-result-label">+10 Points Earned</div>';
        var bb=document.createElement('button');bb.className='nab-quiz-btn';bb.setAttribute('data-action','back');bb.type='button';bb.textContent='Back to Modules';res.appendChild(bb);wrap.appendChild(res);}
    }else{if(btn){btn.disabled=false;btn.textContent='Mark as Complete & Earn Points';}}
  }).catch(function(){if(btn){btn.disabled=false;btn.textContent='Mark as Complete & Earn Points';}});
}

document.addEventListener('click',function(e){
  var tabEl=e.target.closest('[data-tab-open]');if(tabEl){nabOpenTab(tabEl.getAttribute('data-tab-open'));return;}
  var card=e.target.closest('.nab-edu-card[data-mid]');if(card){nabStartModule(parseInt(card.getAttribute('data-mid')));return;}
  var vidWrap=e.target.closest('[data-action="play-video"]');
  if(vidWrap){var url=vidWrap.getAttribute('data-video-url');if(url){var ifr=document.createElement('iframe');ifr.src=url+'?autoplay=1&rel=0';ifr.setAttribute('frameborder','0');ifr.setAttribute('allow','accelerometer;autoplay;clipboard-write;encrypted-media;gyroscope;picture-in-picture;web-share');ifr.setAttribute('allowfullscreen','');ifr.style.cssText='width:100%;height:100%;border:0;display:block';vidWrap.innerHTML='';vidWrap.appendChild(ifr);}return;}
  var opt=e.target.closest('.nab-quiz-opt[data-idx]');if(opt){nabSelectAnswer(parseInt(opt.getAttribute('data-idx')));return;}
  var nextBtn=e.target.closest('#nabQuizNext');if(nextBtn&&!nextBtn.disabled){nabNextQuestion();return;}
  var completeBtn=e.target.closest('[data-action="complete"]');if(completeBtn){nabMarkModuleComplete(parseInt(completeBtn.getAttribute('data-mid')));return;}
  var retakeBtn=e.target.closest('[data-action="retake"]');if(retakeBtn){nabStartQuiz(parseInt(retakeBtn.getAttribute('data-mid')));return;}
  var backBtn=e.target.closest('[data-action="back"]');if(backBtn){nabEduBack();return;}
  var quizBtn=e.target.closest('[data-action="quiz"]');if(quizBtn){nabStartQuiz(parseInt(quizBtn.getAttribute('data-mid')||nabCurrentModule));return;}
  var backVid=e.target.closest('[data-action="backToVideo"]');if(backVid){nabEduShowPlayer();return;}
  var mpBtn=e.target.closest('[data-mp-action]');if(mpBtn){var act=mpBtn.getAttribute('data-mp-action');var plan=mpBtn.getAttribute('data-plan');if(act==='pause'&&typeof nabMpPause==='function')nabMpPause(plan);else if(act==='cancel'&&typeof nabMpCancel==='function')nabMpCancel();return;}
  var showEl=e.target.closest('[data-show]');if(showEl){var el=document.getElementById(showEl.getAttribute('data-show'));if(el){el.style.display='block';showEl.style.display='none';}return;}
  var hideEl=e.target.closest('[data-hide]');if(hideEl){var el2=document.getElementById(hideEl.getAttribute('data-hide'));if(el2)el2.style.display='none';return;}
  var postBtn=e.target.closest('[data-load-post]');if(postBtn&&typeof nabLoadPost==='function'){nabLoadPost(postBtn.getAttribute('data-load-post'),postBtn.getAttribute('data-load-tab'));return;}
});

window.nabOpenTab=nabOpenTab;
window.nabStartModule=nabStartModule;
window.nabStartQuiz=nabStartQuiz;
window.nabEduBack=nabEduBack;
window.nabEduShowPlayer=nabEduShowPlayer;
window.nabSelectAnswer=nabSelectAnswer;
window.nabNextQuestion=nabNextQuestion;
window.nabMarkModuleComplete=nabMarkModuleComplete;

})();

// ── AJAX Search ───────────────────────────────────────────
(function(){
  var searchInput = document.getElementById('nabSearchInput');
  var searchResults = document.getElementById('nabSearchResults');
  if(!searchInput || !searchResults) return;

  // All searchable items
  var searchItems = [
    // Credit Tools
    {icon:'📄', title:'Credit Report Access', desc:'Access your Equifax, TransUnion, Borrowell or Credit Karma report', tab:null, url:null, key:'credit report equifax transunion borrowell'},
    {icon:'📊', title:'Utilization Checker', desc:'Visualize your credit utilization', tab:null, url:'utilization', key:'utilization checker credit usage'},
    {icon:'📈', title:'Score Simulator', desc:'Simulate what-if scenarios for your credit score', tab:null, url:'simulator', key:'score simulator credit score'},
    {icon:'🛡', title:'Dispute Center', desc:'Submit a credit dispute and track your case', tab:null, url:'dispute', key:'dispute center credit error'},
    // Services
    {icon:'📅', title:'Book Specialist', desc:'Schedule a 1-on-1 session with a credit advisor', tab:null, url:'booking', key:'book specialist appointment advisor'},
    {icon:'🤖', title:'NAB AI Chatbot', desc:'Ask our intelligent credit chatbot anything', tab:null, url:'chatbot', key:'ai chatbot assistant help'},
    {icon:'🎫', title:'Support Center', desc:'Submit a support ticket or track existing tickets', tab:null, url:'support', key:'support ticket help contact'},
    // Loan Tools
    {icon:'🚗', title:'Auto Loan Matcher', desc:'Find auto loan offers from Canadian lenders', tab:null, url:'loan', key:'auto loan car vehicle lender'},
    {icon:'💳', title:'Credit Card Matcher', desc:'Answer 4 questions and get matched to the best credit card', tab:null, url:'card-match', key:'credit card matcher best card'},
    // Resources
    {icon:'🎓', title:'Learning Center', desc:'7-part credit education video series', tab:null, url:'learning', key:'learning center education videos modules'},
    {icon:'📚', title:'Education Blog', desc:'Browse credit articles and tips', tab:'blog', url:null, key:'education blog articles credit tips'},
    {icon:'✅', title:'DIY Repair Guide', desc:'Step-by-step credit repair guides', tab:'diy', url:null, key:'diy repair guide credit fix'},
    {icon:'📄', title:'PAD Agreement', desc:'View your Pre-Authorized Debit Agreement', tab:null, url:'pad', key:'pad agreement debit authorization'},
    // Modules
    {icon:'🎥', title:'What is Credit, Really?', desc:'Module 1 — Credit Basics', tab:'education', url:null, key:'what is credit really module 1'},
    {icon:'🎥', title:'The Trust Recipe', desc:'Module 2 — How Credit Scores Are Calculated', tab:'education', url:null, key:'trust recipe credit score calculated module 2'},
    {icon:'🎥', title:'The Escalating Cost of Bad Advice', desc:'Module 3 — Credit Education', tab:'education', url:null, key:'escalating cost bad advice module 3'},
    {icon:'🎥', title:'Free Canadian Credit Reports', desc:'Module 4 — The Complete Guide', tab:'education', url:null, key:'free canadian credit reports equifax transunion module 4'},
    {icon:'🎥', title:'Securing Your Free Credit Reports', desc:'Module 5 — Step by step guide', tab:'education', url:null, key:'securing free credit reports module 5'},
    {icon:'🎥', title:'Credit Inquiry Guide', desc:'Module 6 — Hard and Soft Pulls', tab:'education', url:null, key:'credit inquiry hard soft pulls module 6'},
    {icon:'🎥', title:'Building Better Financial Habits', desc:'Module 7 — Financial Habits', tab:'education', url:null, key:'building financial habits module 7'},
    // Journey
    {icon:'🗺', title:'Financial Roadmap', desc:'Personalized credit action plan — coming soon', tab:null, url:null, key:'financial roadmap plan goals'},
    {icon:'🎯', title:'Goal Tracker', desc:'Track your home, car, business funding goals — coming soon', tab:null, url:null, key:'goal tracker home car business'},
  ];

  var debounceTimer;
  searchInput.addEventListener('input', function(){
    clearTimeout(debounceTimer);
    debounceTimer = setTimeout(function(){
      var q = searchInput.value.trim().toLowerCase();
      if(!q || q.length < 2){ searchResults.classList.remove('visible'); return; }
      var matches = searchItems.filter(function(item){
        return item.title.toLowerCase().includes(q) ||
               item.desc.toLowerCase().includes(q) ||
               item.key.toLowerCase().includes(q);
      }).slice(0, 6);

      if(!matches.length){
        searchResults.innerHTML = '<div class="nab-search-no-results">No results found for "' + q + '"</div>';
      } else {
        searchResults.innerHTML = matches.map(function(item){
          return '<div class="nab-search-result-item" data-tab="'+(item.tab||'')+'" data-url="'+(item.url||'')+'">' +
            '<div class="nab-search-result-icon">'+item.icon+'</div>' +
            '<div><div class="nab-search-result-title">'+item.title+'</div>' +
            '<div class="nab-search-result-desc">'+item.desc+'</div></div></div>';
        }).join('');
      }
      searchResults.classList.add('visible');
    }, 200);
  });

  // Handle result click
  searchResults.addEventListener('click', function(e){
    var item = e.target.closest('.nab-search-result-item');
    if(!item) return;
    var tab = item.getAttribute('data-tab');
    var url = item.getAttribute('data-url');
    searchInput.value = '';
    searchResults.classList.remove('visible');

    if(tab && typeof nabOpenTab === 'function'){
      nabOpenTab(tab);
    } else if(url && typeof nabPortal !== 'undefined'){
      // Navigate to portal page by URL key
      var urlMap = {
        'utilization': '/utilization-checker/',
        'simulator': '/score-simulator/',
        'dispute': '/credit-dispute-center/',
        'booking': '/book-specialist/',
        'chatbot': '/nab-ai-chatbot/',
        'support': '/support-center/',
        'loan': '/auto-loan-matcher/',
        'card-match': '/credit-card-matcher/',
        'learning': '/learning-center/',
        'pad': '/pad-agreement/',
      };
      var path = urlMap[url];
      if(path) window.location.href = window.location.origin + path;
    }
  });

  // Close on outside click
  document.addEventListener('click', function(e){
    if(!e.target.closest('#nabSearchWrap')){
      searchResults.classList.remove('visible');
    }
  });

  // Close on Escape
  searchInput.addEventListener('keydown', function(e){
    if(e.key === 'Escape') searchResults.classList.remove('visible');
  });
})();

// ── Credit Score Checker ──────────────────────────────────
(function(){
  var btn   = document.getElementById('nabScoreBtn');
  var input = document.getElementById('nabScoreInput');
  var err   = document.getElementById('nabScoreError');
  var disp  = document.getElementById('nabScoreDisplay');
  var num   = document.getElementById('nabScoreNum');
  var arc   = document.getElementById('nabGaugeArc');
  var lbl   = document.getElementById('nabScoreLabel');
  var msg   = document.getElementById('nabScoreMsg');

  function getScoreColor(s){
    if(s<580) return '#ef4444';
    if(s<670) return '#f97316';
    if(s<740) return '#3b82f6';
    if(s<800) return '#22c55e';
    return '#16a34a';
  }
  function getScoreLabel(s){
    if(s<580) return {label:'Poor',    msg:'Your score needs attention. Focus on on-time payments.'};
    if(s<670) return {label:'Fair',    msg:'You are making progress. Keep reducing utilization.'};
    if(s<740) return {label:'Good',    msg:'Good score! You qualify for most loans at decent rates.'};
    if(s<800) return {label:'V.Good',  msg:'Very good! You are getting great rates from lenders.'};
    return           {label:'Excellent',msg:'Outstanding! You qualify for the best rates available.'};
  }

  // Draw the gauge for a score (no saving). v1.9.0: also used on page load
  // so a saved score shows its arc/label, and by the charts after edits.
  function renderGauge(s){
    s = parseInt(s);
    if(!s || s < 300 || s > 900){ if(disp) disp.style.display='none'; return; }
    var info  = getScoreLabel(s);
    var color = getScoreColor(s);
    var total = 220; // arc length (matches stroke-dasharray in the template)
    var pct   = (s - 300) / 600;
    var offset = Math.round(total * (1 - pct));

    if(num)  num.textContent = s;
    if(arc){ arc.style.stroke = color; arc.setAttribute('stroke-dashoffset', offset); }
    if(lbl){ lbl.textContent = info.label; lbl.style.color = color; }
    if(msg)  msg.textContent = info.msg;
    if(disp) disp.style.display = 'block';

    // Update needle
    var needle = document.getElementById('nabGaugeNeedle');
    if(needle){
      var angle = -90 + (pct * 180);
      needle.style.transform = 'rotate(' + angle + 'deg)';
    }

  }
  window.nabRenderScoreGauge = renderGauge;
  if(num && num.textContent.trim()) renderGauge(num.textContent.trim());

  if(!btn || !input) return;

  function checkScore(){
    var s = parseInt(input.value);
    if(!s || s < 300 || s > 900){
      if(err){ err.textContent='Please enter a score between 300 and 900.'; err.style.display='block'; }
      return;
    }
    if(err) err.style.display='none';
    renderGauge(s);

    // Save via AJAX — v1.9.0: the response carries fresh chart data
    if(typeof nabPortal !== 'undefined'){
      fetch(nabPortal.ajax, {
        method:'POST',
        headers:{'Content-Type':'application/x-www-form-urlencoded'},
        body:'action=nab_save_credit_score&nonce='+nabPortal.nonce+'&score='+s+'&source=self'
      }).then(function(r){ return r.json(); }).then(function(d){
        if(d && d.success && d.data && d.data.dash){
          window.dispatchEvent(new CustomEvent('nab:dash-data', { detail: d.data.dash }));
        } else if(err){
          err.textContent = (d && d.data && d.data.message) || 'Could not save your score. Please refresh and try again.';
          err.style.display = 'block';
        }
      }).catch(function(){
        if(err){ err.textContent='Connection error — your score was not saved.'; err.style.display='block'; }
      });
    }
  }

  btn.addEventListener('click', checkScore);
  input.addEventListener('keypress', function(e){
    if(e.key === 'Enter') checkScore();
  });
})();

// Auto-open tab
if(window._nabAutoTab){nabOpenTab(window._nabAutoTab);window._nabAutoTab=null;}
