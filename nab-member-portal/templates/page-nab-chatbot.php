<?php
/**
 * Template Name: NAB AI Chatbot
 * Full-page NAB AI Assistant. Same assistant and same conversation as the
 * chat widget in the corner of every other portal page (v1.12.0):
 * engine in assets/js/nab-ai.js, server side in inc/ai-assistant.php.
 *
 * @package NAB_Member_Portal
 * @since   1.6.2
 */

if ( ! defined( 'ABSPATH' ) ) exit;
if ( ! is_user_logged_in() ) { wp_redirect( wp_login_url( get_permalink() ) ); exit; }

$user       = wp_get_current_user();
$uid        = $user->ID;
$first_name = get_user_meta( $uid, 'nab_first_name', true ) ?: $user->first_name ?: 'Member';

$ai_cfg   = nab_portal_ai_config();
$has_ai   = $ai_cfg['live'];
$prompts  = nab_portal_ai_prompts();

$hour     = (int) current_time( 'G' );
$greeting = $hour < 12 ? 'Good morning' : ( $hour < 17 ? 'Good afternoon' : 'Good evening' );

nab_head_open( 'NAB AI Assistant — NAB Member Portal' );
nab_portal_ai_message_css();
?>
<style>
body.nab-chat-page{background:#F5F6F8!important}
.nab-chat-page .nab-topbar-title{display:flex;align-items:center;gap:8px}
.nab-chat-wrap{max-width:820px;margin:0 auto;flex:1;display:flex;flex-direction:column;gap:16px;width:100%}
.nab-chat-hero{display:flex;align-items:center;gap:16px;background:#fff;border:1px solid #E8EBF0;border-radius:16px;padding:20px 22px}
.nab-chat-hero-icon{width:52px;height:52px;border-radius:14px;background:#0D5C9B;color:#fff;display:flex;align-items:center;justify-content:center;flex:none}
.nab-chat-hero-title{font-size:19px;font-weight:750;letter-spacing:-.01em;color:#0F1B2D;margin:0 0 4px}
.nab-chat-hero-sub{font-size:13.5px;color:#6B7685;margin:0;line-height:1.5}
.nab-chat-hero-badge{display:inline-flex;align-items:center;gap:6px;background:#EEF5FB;color:#0D5C9B;padding:4px 10px;border-radius:999px;font-size:12px;font-weight:600;margin-top:10px}
.nab-chat-hero-badge i{width:7px;height:7px;border-radius:50%;background:#22A06B;display:block}
.nab-chat-hero-badge.off{background:#F1F3F6;color:#475467}.nab-chat-hero-badge.off i{background:#9AA4B2}
.nab-chat-offline-notice{background:#FFF8E6;border:1px solid #F5DFA6;border-radius:12px;padding:12px 16px;font-size:13px;color:#7A5200;line-height:1.5}
.nab-chat-prompts{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:10px}
.nab-chat-prompt-btn{display:flex;gap:10px;align-items:flex-start;background:#fff;border:1px solid #E8EBF0;border-radius:12px;padding:12px 14px;text-align:left;cursor:pointer;transition:border-color .15s,background .15s;font:inherit;color:#0F1B2D;line-height:1.35}
.nab-chat-prompt-btn:hover{border-color:#0D5C9B;background:#F7FAFD}
.nab-chat-prompt-icon{width:32px;height:32px;border-radius:9px;background:#EEF5FB;color:#0D5C9B;display:flex;align-items:center;justify-content:center;flex:none}
.nab-chat-prompt-icon.is-tile,.nab-chat-welcome-icon.is-nabi,.nab-chat-msg-avatar.is-nabi{background:none;width:auto;height:auto}
.nab-chat-hero .nab-nabi-word i,.nab-chat-hero-title i{color:#C9962B}
.nab-chat-prompt-text{font-weight:650;font-size:13.5px;display:block;margin-bottom:2px}
.nab-chat-prompt-hint{font-size:12px;color:#6B7685}
.nab-chat-window{background:#fff;border:1px solid #E8EBF0;border-radius:16px;display:flex;flex-direction:column;min-height:420px}
.nab-chat-messages{flex:1;padding:20px;overflow-y:auto;max-height:560px;background:#FAFBFC;border-radius:16px 16px 0 0}
.nab-chat-messages .nab-ai-msg{gap:10px;align-items:flex-start;justify-content:flex-start}
.nab-chat-messages .nab-ai-msg.user{flex-direction:row-reverse}
.nab-chat-messages .nab-ai-bubble{max-width:78%;font-size:14px}
.nab-chat-msg-avatar{width:32px;height:32px;border-radius:10px;display:flex;align-items:center;justify-content:center;font-size:13px;font-weight:800;flex:none;background:#0D5C9B;color:#fff}
.nab-ai-msg.user .nab-chat-msg-avatar{background:#E8EBF0;color:#0F1B2D;border-radius:50%;font-size:11.5px}
.nab-chat-welcome{text-align:center;padding:40px 16px;color:#6B7685}
.nab-chat-welcome-icon{width:48px;height:48px;border-radius:14px;background:#EEF5FB;color:#0D5C9B;display:flex;align-items:center;justify-content:center;margin:0 auto 12px}
.nab-chat-welcome-text{font-size:15px;font-weight:650;color:#0F1B2D;margin-bottom:4px}
.nab-chat-welcome-hint{font-size:13px}
.nab-chat-input-row{display:flex;gap:8px;padding:14px 16px;border-top:1px solid #E8EBF0;align-items:flex-end}
.nab-chat-input{flex:1;min-height:44px;padding:11px 14px;border:1px solid #D9DEE6;border-radius:12px;font:inherit;font-size:14px;resize:none;max-height:120px;line-height:1.45;color:#0F1B2D;box-sizing:border-box}
.nab-chat-input:focus{outline:none;border-color:#0D5C9B;box-shadow:0 0 0 3px rgba(13,92,155,.12)}
.nab-chat-send{display:flex;align-items:center;gap:6px;background:#0D5C9B;color:#fff;border:none;border-radius:12px;padding:0 18px;font:inherit;font-size:14px;font-weight:650;cursor:pointer;flex-shrink:0;height:44px}
.nab-chat-send:hover{background:#0A4A7C}
.nab-chat-send:disabled{background:#C3CAD5;cursor:default}
.nab-chat-clear{display:flex;align-items:center;justify-content:center;background:#fff;border:1px solid #E8EBF0;border-radius:12px;width:44px;height:44px;color:#6B7685;cursor:pointer;flex:none}
.nab-chat-clear:hover{border-color:#0D5C9B;color:#0D5C9B}
.nab-chat-note{font-size:12px;color:#9AA4B2;text-align:center;margin:0}
@media(max-width:860px){.nab-chat-prompts{grid-template-columns:repeat(2,minmax(0,1fr))}}
@media(max-width:640px){
  .nab-chat-prompts{grid-template-columns:1fr}
  .nab-chat-hero{align-items:flex-start;padding:16px}
  .nab-chat-hero-icon{width:44px;height:44px}
  .nab-chat-messages{max-height:none;min-height:340px;padding:14px}
  .nab-chat-messages .nab-ai-bubble{max-width:84%}
  .nab-chat-send span{display:none}
  .nab-chat-send{padding:0 14px}
  .nab-chat-input{font-size:16px} /* stops iOS zooming into the field */
}
</style>
</head>
<body <?php body_class( 'nab-portal-body nab-chat-page' ); ?>>
<?php wp_body_open(); ?>

<div class="nab-portal-wrap">
  <?php nab_render_sidebar( 'chatbot' ); ?>

  <main class="nab-main">
    <header class="nab-topbar">
      <div class="nab-topbar-title"><?php echo nab_portal_nabi_mark( 26 ); ?> NAB AI Assistant</div>
      <div class="nab-topbar-right">
        <?php if ( function_exists( 'nab_render_notification_bell' ) ) nab_render_notification_bell(); ?>
      </div>
    </header>

    <div class="nab-content">
      <div class="nab-chat-wrap">

        <div class="nab-chat-hero">
          <?php echo nab_portal_nabi_mark( 56, 'nab-chat-hero-mark' ); ?>
          <div>
            <div class="nab-chat-hero-title"><?php echo esc_html( $greeting . ', ' . $first_name ); ?>! I'm <span class="nab-nabi-word">NAB<i>i</i></span>, your NAB Credit Assistant.</div>
            <p class="nab-chat-hero-sub">Ask me anything about your credit score, dispute strategies, Canadian credit cards, budgeting, or how to improve your financial profile. I can see the numbers you've added in your portal, so my answers fit your situation.</p>
            <span class="nab-chat-hero-badge<?php echo $has_ai ? '' : ' off'; ?>"><i></i><?php echo $has_ai ? 'Online' : 'Quick answers mode'; ?></span>
          </div>
        </div>

        <?php if ( ! $has_ai && current_user_can( 'manage_options' ) ) : ?>
        <div class="nab-chat-offline-notice">
          <strong>Admin note:</strong> The assistant is using built-in answers. To switch on full AI, go to <strong>WP Admin → Edit Dashboard page → OpenAI / AI Features</strong> and paste the OpenAI API key.
        </div>
        <?php endif; ?>

        <div class="nab-chat-prompts">
          <?php foreach ( $prompts as $p ) : ?>
          <button type="button" class="nab-chat-prompt-btn" data-chat-q="<?php echo esc_attr( $p['q'] ); ?>">
            <span class="nab-chat-prompt-icon is-tile"><?php echo nab_portal_icon_tile( $p['icon'], $p['tone'] ?? 'blue', 34 ); ?></span>
            <span>
              <span class="nab-chat-prompt-text"><?php echo esc_html( $p['label'] ); ?></span>
              <span class="nab-chat-prompt-hint"><?php echo esc_html( $p['hint'] ); ?></span>
            </span>
          </button>
          <?php endforeach; ?>
        </div>

        <div class="nab-chat-window">
          <div class="nab-chat-messages" id="nabChatMessages" aria-live="polite">
            <div class="nab-chat-welcome" id="nabChatWelcome">
              <div class="nab-chat-welcome-icon is-nabi"><?php echo nab_portal_nabi_mark( 52 ); ?></div>
              <div class="nab-chat-welcome-text">Ask me anything about your credit</div>
              <div class="nab-chat-welcome-hint">Use a suggested question above or type your own below</div>
            </div>
          </div>
          <div class="nab-chat-input-row">
            <textarea class="nab-chat-input" id="nabChatInput" rows="1" maxlength="1000"
              placeholder="Ask about credit scores, disputes, Canadian cards, budgeting…" aria-label="Your question"></textarea>
            <button class="nab-chat-clear" title="Start a new chat" aria-label="Start a new chat" onclick="nabClearChat()" type="button"><?php echo nab_portal_icon( 'square-pen', 18 ); ?></button>
            <button class="nab-chat-send" id="nabChatSend" type="button"><span>Send</span><?php echo nab_portal_icon( 'arrow-up', 17 ); ?></button>
          </div>
        </div>
        <p class="nab-chat-note">General education only, not financial, legal or tax advice. For anything about your account, use the Support Center.</p>

      </div>
    </div>
  </main>
</div>

<?php nab_portal_footer_js(); ?>
<?php wp_footer(); ?>
</body>
</html>
