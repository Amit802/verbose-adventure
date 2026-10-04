<?php
/**
 * Template Name: NAB AI Chatbot
 * Uses nab_head_open() / nab_open_body() / nab_portal_footer_js()
 * exactly like all other portal pages — sidebar CSS loads correctly.
 *
 * @package NAB_Member_Portal
 * @since   1.6.2
 */

if ( ! defined( 'ABSPATH' ) ) exit;
if ( ! is_user_logged_in() ) { wp_redirect( wp_login_url( get_permalink() ) ); exit; }

$user       = wp_get_current_user();
$uid        = $user->ID;
$first_name = get_user_meta( $uid, 'nab_first_name', true ) ?: $user->first_name ?: 'Member';
$last_name  = get_user_meta( $uid, 'nab_last_name',  true ) ?: $user->last_name  ?: '';
$initials   = strtoupper( substr($first_name,0,1).substr($last_name,0,1) ) ?: 'M';

$dash_pages = get_pages(['meta_key'=>'_wp_page_template','meta_value'=>'nab-dashboard','number'=>1]);
$dash_id    = !empty($dash_pages) ? $dash_pages[0]->ID : 0;

$openai_key = $dash_id ? get_field('nab_openai_key', $dash_id) : '';
$ai_model   = $dash_id ? (get_field('nab_ai_model',  $dash_id) ?: 'gpt-4o-mini') : 'gpt-4o-mini';
$ai_enabled = $dash_id ? get_field('nab_ai_enabled', $dash_id) : true;
$has_ai     = !empty($openai_key) && $ai_enabled;

$credit_score = (int) get_user_meta($uid,'nab_credit_score',true);
$credit_goal  = get_user_meta($uid,'nab_credit_goal',true);
$track        = get_user_meta($uid,'nab_track',true);
$tier         = function_exists('nab_get_member_tier') ? nab_get_member_tier($uid) : '';

$hour     = (int) current_time('G');
$greeting = $hour < 12 ? 'Good morning' : ($hour < 17 ? 'Good afternoon' : 'Good evening');

// Uses nab_head_open() — loads all portal CSS including sidebar
nab_head_open( 'NAB AI Assistant — NAB Member Portal' );
?>
<style>
/* ── AI Chatbot specific styles ── */
.nab-chat-wrap{max-width:820px;margin:0 auto;flex:1;display:flex;flex-direction:column;gap:16px}
.nab-chat-hero{background:linear-gradient(135deg,#7c3aed,#6d28d9);border-radius:16px;padding:24px 28px;color:#fff;display:flex;align-items:center;gap:20px;flex-wrap:wrap}
.nab-chat-hero-icon{font-size:44px;flex-shrink:0}
.nab-chat-hero-title{font-size:20px;font-weight:800;margin:0 0 4px}
.nab-chat-hero-sub{font-size:13px;opacity:.85;margin:0;line-height:1.5}
.nab-chat-hero-badge{display:inline-block;background:rgba(255,255,255,.2);padding:3px 10px;border-radius:20px;font-size:11px;font-weight:600;margin-top:8px}
.nab-chat-prompts{display:grid;grid-template-columns:repeat(2,1fr);gap:10px}
.nab-chat-prompt-btn{background:#fff;border:1.5px solid #e2e8f0;border-radius:10px;padding:12px 14px;text-align:left;cursor:pointer;transition:.15s;font-size:13px;color:#374151;line-height:1.4}
.nab-chat-prompt-btn:hover{border-color:#7c3aed;background:#faf5ff;color:#7c3aed}
.nab-chat-prompt-icon{font-size:16px;margin-bottom:6px;display:block}
.nab-chat-prompt-text{font-weight:600;display:block;margin-bottom:2px}
.nab-chat-prompt-hint{font-size:11px;color:#94a3b8}
.nab-chat-window{background:#fff;border-radius:16px;box-shadow:0 2px 16px rgba(0,0,0,.07);flex:1;display:flex;flex-direction:column;min-height:400px}
.nab-chat-messages{flex:1;padding:20px;overflow-y:auto;display:flex;flex-direction:column;gap:14px;max-height:520px}
.nab-chat-msg{display:flex;gap:10px;align-items:flex-start}
.nab-chat-msg.user{flex-direction:row-reverse}
.nab-chat-msg-avatar{width:32px;height:32px;border-radius:50%;display:flex;align-items:center;justify-content:center;font-size:14px;flex-shrink:0;font-weight:700}
.nab-chat-msg.ai .nab-chat-msg-avatar{background:#ede9fe;color:#7c3aed}
.nab-chat-msg.user .nab-chat-msg-avatar{background:#0D5C9B;color:#fff;font-size:11px}
.nab-chat-bubble{max-width:80%;padding:12px 16px;border-radius:14px;font-size:13px;line-height:1.6}
.nab-chat-msg.ai .nab-chat-bubble{background:#f8fafc;color:#1e293b;border-radius:4px 14px 14px 14px}
.nab-chat-msg.user .nab-chat-bubble{background:#0D5C9B;color:#fff;border-radius:14px 4px 14px 14px}
.nab-typing-dots span{display:inline-block;width:6px;height:6px;background:#94a3b8;border-radius:50%;margin:0 2px;animation:nabDot 1.2s infinite}
.nab-typing-dots span:nth-child(2){animation-delay:.2s}
.nab-typing-dots span:nth-child(3){animation-delay:.4s}
@keyframes nabDot{0%,60%,100%{transform:translateY(0)}30%{transform:translateY(-6px)}}
.nab-chat-input-row{display:flex;gap:10px;padding:16px 20px;border-top:1px solid #f1f5f9;align-items:flex-end}
.nab-chat-input{flex:1;padding:11px 16px;border:1.5px solid #e2e8f0;border-radius:10px;font-size:13px;resize:none;font-family:inherit;transition:.15s;max-height:100px;line-height:1.5}
.nab-chat-input:focus{outline:none;border-color:#7c3aed}
.nab-chat-send{background:#7c3aed;color:#fff;border:none;border-radius:10px;padding:11px 20px;font-size:13px;font-weight:700;cursor:pointer;transition:.15s;flex-shrink:0;height:42px}
.nab-chat-send:hover{background:#6d28d9}
.nab-chat-send:disabled{background:#cbd5e1;cursor:not-allowed}
.nab-chat-clear{background:none;border:1.5px solid #e2e8f0;border-radius:10px;padding:11px 14px;font-size:12px;color:#94a3b8;cursor:pointer;height:42px;transition:.15s}
.nab-chat-clear:hover{border-color:#7c3aed;color:#7c3aed}
.nab-chat-offline-notice{background:#fff8e1;border-left:4px solid #f59e0b;border-radius:8px;padding:12px 16px;font-size:12px;color:#78350f;line-height:1.5}
.nab-chat-welcome{text-align:center;padding:24px 16px;color:#94a3b8}
.nab-chat-welcome-icon{font-size:40px;margin-bottom:10px}
.nab-chat-welcome-text{font-size:14px;font-weight:600;color:#64748b;margin-bottom:4px}
.nab-chat-welcome-hint{font-size:12px}
@media(max-width:640px){.nab-chat-prompts{grid-template-columns:1fr}.nab-chat-hero{flex-direction:column;text-align:center}.nab-chat-messages{max-height:360px}}
</style>
</head>
<body <?php body_class('nab-portal-body'); ?>>
<?php wp_body_open(); ?>

<div class="nab-portal-wrap">
  <?php nab_render_sidebar('chatbot'); ?>

  <main class="nab-main">
    <header class="nab-topbar">
      <div class="nab-topbar-title">🤖 NAB AI Assistant</div>
      <div class="nab-topbar-right">
        <?php if(function_exists('nab_render_notification_bell')) nab_render_notification_bell(); ?>
      </div>
    </header>

    <div class="nab-content">
      <div class="nab-chat-wrap">

        <!-- Hero -->
        <div class="nab-chat-hero">
          <div class="nab-chat-hero-icon">🤖</div>
          <div>
            <div class="nab-chat-hero-title"><?php echo esc_html($greeting.', '.$first_name); ?>! I'm your NAB Credit Assistant.</div>
            <p class="nab-chat-hero-sub">Ask me anything about your credit score, dispute strategies, Canadian credit cards, budgeting, or how to improve your financial profile.</p>
            <span class="nab-chat-hero-badge">
              <?php echo $has_ai ? '✨ AI-Powered by '.strtoupper($ai_model) : '📚 Smart Offline Mode — AI activates when OpenAI key is added'; ?>
            </span>
          </div>
        </div>

        <?php if(!$has_ai): ?>
        <div class="nab-chat-offline-notice">
          <strong>💡 Offline Mode:</strong> The AI assistant is currently using built-in smart responses. To activate full GPT-powered AI, go to <strong>WP Admin → Edit Dashboard page → OpenAI / AI Features tab</strong> and paste your OpenAI API key.
        </div>
        <?php endif; ?>

        <!-- Suggested prompts -->
        <div class="nab-chat-prompts">
          <button class="nab-chat-prompt-btn" onclick="nabSendPrompt(this.dataset.q)" data-q="How can I improve my credit score fast?">
            <span class="nab-chat-prompt-icon">📈</span>
            <span class="nab-chat-prompt-text">Improve my credit score</span>
            <span class="nab-chat-prompt-hint">Get a personalised action plan</span>
          </button>
          <button class="nab-chat-prompt-btn" onclick="nabSendPrompt(this.dataset.q)" data-q="How do I dispute an error on my credit report?">
            <span class="nab-chat-prompt-icon">🛡️</span>
            <span class="nab-chat-prompt-text">Dispute credit report errors</span>
            <span class="nab-chat-prompt-hint">Step-by-step dispute guidance</span>
          </button>
          <button class="nab-chat-prompt-btn" onclick="nabSendPrompt(this.dataset.q)" data-q="What credit card is best for someone rebuilding credit in Canada?">
            <span class="nab-chat-prompt-icon">💳</span>
            <span class="nab-chat-prompt-text">Best credit card for me</span>
            <span class="nab-chat-prompt-hint">Canadian card recommendations</span>
          </button>
          <button class="nab-chat-prompt-btn" onclick="nabSendPrompt(this.dataset.q)" data-q="How does credit utilization affect my score and what should I aim for?">
            <span class="nab-chat-prompt-icon">📊</span>
            <span class="nab-chat-prompt-text">Credit utilization explained</span>
            <span class="nab-chat-prompt-hint">Optimise your ratio</span>
          </button>
          <button class="nab-chat-prompt-btn" onclick="nabSendPrompt(this.dataset.q)" data-q="How long does a missed payment stay on my credit report in Canada?">
            <span class="nab-chat-prompt-icon">⏱️</span>
            <span class="nab-chat-prompt-text">How long do negatives last?</span>
            <span class="nab-chat-prompt-hint">Canadian credit bureau rules</span>
          </button>
          <button class="nab-chat-prompt-btn" onclick="nabSendPrompt(this.dataset.q)" data-q="What is the difference between Equifax and TransUnion in Canada?">
            <span class="nab-chat-prompt-icon">🏛️</span>
            <span class="nab-chat-prompt-text">Equifax vs TransUnion</span>
            <span class="nab-chat-prompt-hint">Understanding both bureaus</span>
          </button>
        </div>

        <!-- Chat window -->
        <div class="nab-chat-window">
          <div class="nab-chat-messages" id="nabChatMessages">
            <div class="nab-chat-welcome" id="nabChatWelcome">
              <div class="nab-chat-welcome-icon">💬</div>
              <div class="nab-chat-welcome-text">Ask me anything about your credit</div>
              <div class="nab-chat-welcome-hint">Use a suggested prompt above or type your own question below</div>
            </div>
          </div>
          <div class="nab-chat-input-row">
            <textarea class="nab-chat-input" id="nabChatInput" rows="1"
              placeholder="Ask about credit scores, disputes, Canadian cards, budgeting…"></textarea>
            <button class="nab-chat-clear" title="Clear chat" onclick="nabClearChat()" type="button">🗑️</button>
            <button class="nab-chat-send" id="nabChatSend" type="button">Send</button>
          </div>
        </div>

      </div>
    </div>
  </main>
</div>

<script>
(function(){
var HAS_AI  = <?php echo $has_ai ? 'true' : 'false'; ?>;
var AJAXURL = '<?php echo admin_url("admin-ajax.php"); ?>';
var NONCE   = '<?php echo wp_create_nonce("nab_ai_chat"); ?>';
var history = [];
var messages = document.getElementById('nabChatMessages');
var input    = document.getElementById('nabChatInput');
var sendBtn  = document.getElementById('nabChatSend');

function addMsg(text,role){
  var w=document.getElementById('nabChatWelcome');if(w)w.style.display='none';
  var d=document.createElement('div');d.className='nab-chat-msg '+role;
  var av=role==='ai'?'🤖':'<?php echo esc_js($initials); ?>';
  d.innerHTML='<div class="nab-chat-msg-avatar">'+av+'</div><div class="nab-chat-bubble">'+text+'</div>';
  messages.appendChild(d);messages.scrollTop=messages.scrollHeight;return d;
}
function addTyping(){
  var d=document.createElement('div');d.className='nab-chat-msg ai';d.id='nabTyping';
  d.innerHTML='<div class="nab-chat-msg-avatar">🤖</div><div class="nab-chat-bubble" style="color:#94a3b8"><div class="nab-typing-dots"><span></span><span></span><span></span></div></div>';
  messages.appendChild(d);messages.scrollTop=messages.scrollHeight;return d;
}
function offlineReply(q){
  q=q.toLowerCase();
  if(q.indexOf('utiliz')>-1||q.indexOf('utilis')>-1)return 'Credit utilization is the percentage of available credit you\'re using. Keep it <strong>below 30%</strong> on each card and overall. For the best impact, target <strong>below 10%</strong>. Paying before your statement date reduces what gets reported.';
  if(q.indexOf('dispute')>-1||q.indexOf('error')>-1)return 'To dispute an error: 1) Get your free report from <strong>Equifax.ca</strong> and <strong>TransUnion.ca</strong>. 2) Identify the error. 3) Submit a dispute online with supporting documents. 4) The bureau must investigate within 30 days. Use our <strong>Dispute Center</strong> for ready-made templates.';
  if(q.indexOf('improv')>-1||q.indexOf('boost')>-1||q.indexOf('increas')>-1)return '5 ways to improve your score: 1) <strong>Pay on time every month</strong>. 2) <strong>Keep utilization below 30%</strong>. 3) <strong>Don\'t close old accounts</strong>. 4) <strong>Limit hard inquiries</strong> — space applications 6+ months apart. 5) <strong>Mix credit types</strong> (card + installment loan).';
  if(q.indexOf('miss')>-1||q.indexOf('late')>-1||q.indexOf('how long')>-1)return 'In Canada, negative items stay on your report for <strong>6–7 years</strong> from first delinquency. This includes missed payments, collections, and consumer proposals. Bankruptcies stay for <strong>6–7 years</strong> after discharge. The impact fades significantly after 2–3 years of positive history.';
  if(q.indexOf('equifax')>-1||q.indexOf('transunion')>-1)return '<strong>Equifax</strong> is commonly used by banks and credit cards. <strong>TransUnion</strong> is common for auto loans. Your score may differ slightly between them. Get free annual reports at <strong>Equifax.ca</strong> and <strong>TransUnion.ca</strong>.';
  if(q.indexOf('secured')>-1||q.indexOf('deposit')>-1)return 'A secured card requires a refundable deposit ($200–$500 CAD) as your credit limit. After 12–18 months of on-time payments, most issuers upgrade you to unsecured. Top options: <strong>Neo Financial Secured Visa</strong> and <strong>Home Trust Secured Visa</strong>.';
  if(q.indexOf('card')>-1)return 'The best card depends on your score and goals. For rebuilding: <strong>Neo Financial Secured Visa</strong>. No-fee cashback: <strong>Tangerine Money-Back</strong>. Travel: <strong>CIBC Aventura Visa Infinite</strong>. Use our <strong>Credit Card Finder</strong> for a personalised match.';
  // Better generic fallback based on common keywords
  if(q.indexOf('hello')>-1||q.indexOf('hi')>-1||q.indexOf('hey')>-1||q.indexOf('how are')>-1)
    return 'Hi there! I\'m your NAB Credit Assistant. I can help you with credit score improvement, dispute strategies, Canadian credit card recommendations, and more. What would you like to know?';
  if(q.indexOf('score')>-1)
    return 'Your credit score is calculated based on 5 factors: payment history (35%), utilization (30%), length of history (15%), credit mix (10%), and new inquiries (10%). The biggest quick wins are paying on time and keeping utilization below 30%.';
  if(q.indexOf('budget')>-1||q.indexOf('money')>-1||q.indexOf('sav')>-1)
    return 'Good budgeting habits directly improve your credit. Pay all bills on time, keep credit card balances low (under 30% of the limit), and avoid opening too many new accounts at once. Would you like specific tips for your situation?';
  return 'I can help you with credit score improvement, dispute strategies, Canadian credit cards, utilization ratios, and understanding your credit report. What specific area would you like help with today?';
}
function sendMessage(q){
  if(!q.trim())return;
  addMsg(q,'user');history.push({role:'user',content:q});
  input.value='';input.style.height='auto';sendBtn.disabled=true;
  var typing=addTyping();
  if(!HAS_AI){
    setTimeout(function(){typing.remove();var r=offlineReply(q);addMsg(r,'ai');history.push({role:'assistant',content:r});sendBtn.disabled=false;},700);
    return;
  }
  fetch(AJAXURL,{method:'POST',headers:{'Content-Type':'application/x-www-form-urlencoded'},
    body:'action=nab_ai_chat&nonce='+NONCE+'&message='+encodeURIComponent(q)+'&history='+encodeURIComponent(JSON.stringify(history.slice(-10)))})
  .then(function(r){return r.json();})
  .then(function(data){
    typing.remove();
    var r;
    if(data.success && data.data && data.data.reply && data.data.reply.trim()){
      r = data.data.reply;
    } else if(!data.success && data.data && data.data.code === 'insufficient_quota'){
      r = '⚠️ The AI is temporarily unavailable — the OpenAI account needs billing credits added. In the meantime, here is my best answer: ' + offlineReply(q);
    } else {
      r = offlineReply(q);
    }
    addMsg(r,'ai');
    history.push({role:'assistant',content:r});
    sendBtn.disabled=false;
  })
  .catch(function(){typing.remove();addMsg(offlineReply(q),'ai');sendBtn.disabled=false;});
}
sendBtn.addEventListener('click',function(){sendMessage(input.value.trim());});
input.addEventListener('keydown',function(e){if(e.key==='Enter'&&!e.shiftKey){e.preventDefault();sendMessage(input.value.trim());}});
input.addEventListener('input',function(){this.style.height='auto';this.style.height=Math.min(this.scrollHeight,100)+'px';});
window.nabSendPrompt=function(q){sendMessage(q);};
window.nabClearChat=function(){
  history=[];
  messages.innerHTML='<div class="nab-chat-welcome" id="nabChatWelcome"><div class="nab-chat-welcome-icon">💬</div><div class="nab-chat-welcome-text">Ask me anything about your credit</div><div class="nab-chat-welcome-hint">Use a suggested prompt above or type your own question below</div></div>';
};
})();
</script>

<?php nab_portal_footer_js(); ?>
<?php wp_footer(); ?>
</body>
</html>
