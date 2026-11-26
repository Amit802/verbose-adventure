<?php
/**
 * Template Name: NAB Card Rewards Checker
 * Members pick the credit cards they already have, search a store, enter an
 * amount, and see which card earns the most there, how it was worked out,
 * and other cards that would earn more at that store.
 *
 * Data:   inc/card-rewards.php (cards, stores, the member's saved cards).
 * Script: assets/js/nab-card-rewards.js (enqueued in inc/ajax-handlers.php)
 *
 * @package NAB_Member_Portal
 * @since   1.24.0
 */

if ( ! defined( 'ABSPATH' ) ) exit;
if ( ! is_user_logged_in() ) { wp_redirect( wp_login_url( get_permalink() ) ); exit; }
if ( ! headers_sent() ) {
    header( 'Cache-Control: no-store, no-cache, must-revalidate, max-age=0' );
}
do_action( 'litespeed_control_set_nocache', 'Card rewards shows the member\'s own cards' );

$uid   = get_current_user_id();
$cards = nab_rewards_cards();
$cats  = nab_rewards_categories();

// Only what the browser needs from each card.
$card_data = [];
foreach ( $cards as $id => $c ) {
    $card_data[ $id ] = [
        'name'    => $c['name'],
        'issuer'  => $c['issuer'],
        'network' => $c['network'],
        'fee'     => (float) $c['fee'],
        'kind'    => $c['kind'],
        'unit'    => $c['unit'] ?? '',
        'cpp'     => (float) ( $c['cpp'] ?? 1 ),
        'base'    => (float) $c['base'],
        'cats'    => (object) ( $c['cats'] ?? [] ),
        'brands'  => (object) ( $c['brands'] ?? [] ),
        'choose'  => $c['choose'] ?? null,
        'caps'    => (object) ( $c['caps'] ?? [] ),
        'note'    => $c['note'] ?? '',
        'url'     => esc_url_raw( $c['url'] ?? '' ),
        'apply'   => esc_url_raw( $c['apply_url'] ?? '' ),
    ];
}

$nav       = nab_get_nav_links();
$match_url = nab_resolve_url( $nav['card-match'] ?? '#' );
$checked   = nab_rewards_checked_date();

$data = [
    'cards'     => $card_data,
    'cats'      => array_map( function ( $c ) { return $c['label']; }, $cats ),
    'merchants' => array_values( nab_rewards_merchants() ),
    'mine'      => nab_rewards_get_member( $uid ),
    'matchUrl'  => $match_url !== '#' ? $match_url : '',
];

$networks = [ 'visa' => 'Visa', 'mastercard' => 'Mastercard', 'amex' => 'American Express' ];

nab_head_open( 'Card Rewards Checker | NAB Member Portal' );
?>
<style>
body.nab-rewards-page{
  --cr-bg:var(--nab-bg);--cr-card:var(--nab-surface);--cr-line:var(--nab-line);--cr-line2:var(--nab-line-soft);
  --cr-text:var(--nab-text);--cr-muted:var(--nab-text-2);--cr-faint:var(--nab-text-3);
  --cr-blue:var(--nab-brand);--cr-blue2:var(--nab-brand-strong);--cr-green:var(--nab-good);--cr-red:var(--nab-bad);
  background:var(--cr-bg)!important;color:var(--cr-text);-webkit-font-smoothing:antialiased
}
body.nab-rewards-page [hidden]{display:none!important}
.nab-rewards-page .nab-topbar-title{display:flex;align-items:center;gap:10px}
.cr-wrap{max-width:1040px;margin:0 auto;padding-bottom:40px}
.cr-head{margin-bottom:18px}
.cr-eyebrow{font-size:12px;font-weight:600;color:var(--cr-faint);text-transform:uppercase;letter-spacing:.08em;margin-bottom:4px}
.cr-title{font-size:28px;line-height:1.15;font-weight:750;letter-spacing:-.02em;margin:0 0 6px}
.cr-sub{font-size:14px;color:var(--cr-muted);margin:0;max-width:600px;line-height:1.5}

.cr-btn{display:inline-flex;align-items:center;justify-content:center;gap:6px;font:inherit;font-size:13px;font-weight:600;line-height:1;padding:10px 14px;border-radius:10px;border:1px solid var(--cr-line);background:#fff;color:var(--cr-text);cursor:pointer;text-decoration:none;white-space:nowrap}
.cr-btn:hover{background:#F8F9FB;border-color:#D9DEE6}
.cr-btn-primary{background:var(--cr-blue);border-color:var(--cr-blue);color:#fff}
.cr-btn-primary:hover{background:var(--cr-blue2);border-color:var(--cr-blue2);color:#fff}
.cr-btn-sm{padding:7px 10px;font-size:12.5px}
.cr-link{font:inherit;font-size:12.5px;font-weight:600;color:var(--cr-blue);background:none;border:0;padding:0;cursor:pointer;text-decoration:none}
.cr-link:hover{text-decoration:underline}

.cr-card{background:var(--cr-card);border:1px solid var(--cr-line);border-radius:16px;padding:20px 22px;margin-bottom:16px}
.cr-card-head{display:flex;justify-content:space-between;align-items:center;gap:12px;flex-wrap:wrap;margin-bottom:14px}
.cr-card-title{font-size:16px;font-weight:700;margin:0;display:flex;align-items:center;gap:8px}
.cr-step{display:inline-flex;align-items:center;justify-content:center;width:22px;height:22px;border-radius:999px;background:#EEF4FA;color:var(--cr-blue);font-size:12px;font-weight:700}
.cr-card-sub{font-size:12.5px;color:var(--cr-muted);margin-top:3px}

.cr-main{display:grid;grid-template-columns:minmax(0,1fr) minmax(0,1fr);gap:16px;align-items:start}
.cr-main>div>.cr-card:last-child{margin-bottom:0}
.cr-side{position:sticky;top:16px}

/* My cards */
.cr-mine{display:grid;gap:8px;margin-bottom:12px}
.cr-mine-empty{font-size:13px;color:var(--cr-muted);background:#F8FAFC;border:1px dashed #D5DCE5;border-radius:12px;padding:14px;line-height:1.5}
.cr-own{border:1px solid var(--cr-line);border-radius:12px;padding:10px 12px;background:#fff}
.cr-own-top{display:flex;align-items:center;gap:10px}
.cr-own-name{font-size:13.5px;font-weight:650;line-height:1.3}
.cr-own-meta{font-size:12px;color:var(--cr-muted);margin-top:1px}
.cr-own-txt{flex:1;min-width:0}
.cr-x{flex:0 0 auto;width:30px;height:30px;border-radius:8px;border:1px solid transparent;background:none;color:var(--cr-faint);cursor:pointer;display:inline-flex;align-items:center;justify-content:center}
.cr-x:hover{background:#FBEDEC;color:var(--cr-red)}
.cr-picks{margin-top:10px;padding-top:10px;border-top:1px solid var(--cr-line2)}
.cr-picks-l{font-size:12px;color:var(--cr-muted);margin-bottom:6px;line-height:1.4}
.cr-chips{display:flex;flex-wrap:wrap;gap:6px}
.cr-chip{font:inherit;font-size:12.5px;font-weight:600;padding:6px 10px;border-radius:999px;border:1px solid var(--cr-line);background:#fff;color:var(--cr-text);cursor:pointer}
.cr-chip:hover{border-color:#CFE0EF;background:#F7FAFD}
.cr-chip[aria-pressed="true"]{border-color:var(--cr-blue);background:#F2F7FC;color:var(--cr-blue)}
.cr-net{display:inline-block;font-size:10.5px;font-weight:700;letter-spacing:.03em;text-transform:uppercase;padding:2px 6px;border-radius:5px;background:#F1F3F6;color:#475467;margin-right:6px;vertical-align:1px}
.cr-saved{font-size:12px;color:var(--cr-faint);min-height:16px}

/* Card picker */
.cr-picker{border:1px solid var(--cr-line);border-radius:12px;margin-top:4px;overflow:hidden}
.cr-picker-search{padding:10px;border-bottom:1px solid var(--cr-line2);background:#FAFBFC}
.cr-list{max-height:300px;overflow:auto}
.cr-opt{display:flex;align-items:center;gap:10px;padding:10px 12px;border-bottom:1px solid var(--cr-line2)}
.cr-opt:last-child{border-bottom:0}
.cr-opt .cr-own-txt{font-size:13px}
.cr-opt-none{padding:14px;font-size:13px;color:var(--cr-muted)}
.cr-custom{padding:12px;border-top:1px solid var(--cr-line);background:#FAFBFC}
.cr-custom-grid{display:grid;grid-template-columns:1.4fr 1fr .8fr auto;gap:8px;align-items:end}

/* Form fields */
.cr-field{display:block;font-size:12.5px;font-weight:600;color:#475467;margin-bottom:14px;position:relative}
.cr-field small{font-weight:500;color:var(--cr-faint)}
.cr-input,.cr-select{display:block;width:100%;box-sizing:border-box;height:44px;margin-top:6px;padding:0 12px;border:1px solid #D9DEE6;border-radius:10px;font:inherit;font-size:15px;color:var(--cr-text);background:#fff;outline:none;-webkit-appearance:none;appearance:none}
.cr-select{background-image:url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='12' height='12' viewBox='0 0 24 24' fill='none' stroke='%236B7685' stroke-width='2.5' stroke-linecap='round' stroke-linejoin='round'%3E%3Cpath d='m6 9 6 6 6-6'/%3E%3C/svg%3E");background-repeat:no-repeat;background-position:right 12px center;padding-right:32px}
.cr-picker-search .cr-input,.cr-custom .cr-input,.cr-custom .cr-select{margin-top:4px;height:38px;font-size:14px}
.cr-input:focus,.cr-select:focus{border-color:var(--cr-blue);box-shadow:0 0 0 3px rgba(13,92,155,.12)}
.cr-unit{position:relative;display:block}
.cr-unit .cr-input{padding-left:26px}
.cr-unit::before{content:"$";position:absolute;left:12px;top:50%;transform:translateY(-50%);margin-top:3px;color:var(--cr-faint);font-weight:600;font-size:14px;pointer-events:none}
.cr-unit.pct .cr-input{padding-left:12px;padding-right:26px}
.cr-unit.pct::before{content:none}
.cr-unit.pct::after{content:"%";position:absolute;right:10px;top:50%;transform:translateY(-50%);margin-top:2px;color:var(--cr-faint);font-weight:600;font-size:14px;pointer-events:none}
.cr-err{font-size:12.5px;font-weight:600;color:#B42328;margin:6px 0 0}
.cr-err:empty{display:none}
.cr-grid2{display:grid;grid-template-columns:1.3fr 1fr;gap:0 12px}

/* Store suggestions */
.cr-suggest{position:absolute;left:0;right:0;top:100%;margin-top:4px;z-index:20;background:#fff;border:1px solid var(--cr-line);border-radius:12px;box-shadow:0 12px 28px rgba(16,24,40,.12);max-height:260px;overflow:auto;padding:4px}
.cr-suggest button{display:flex;width:100%;justify-content:space-between;gap:10px;align-items:center;font:inherit;font-size:14px;font-weight:500;text-align:left;padding:9px 10px;border:0;border-radius:8px;background:none;color:var(--cr-text);cursor:pointer}
.cr-suggest button span{font-size:12px;color:var(--cr-faint);font-weight:500}
.cr-suggest button:hover,.cr-suggest button.on{background:#F2F7FC}
.cr-popular{display:flex;flex-wrap:wrap;gap:6px;margin:-4px 0 14px}

/* Result */
.cr-hero{border:0;color:#fff;padding:22px 24px;background:var(--nab-brand-strong);box-shadow:var(--nab-shadow)}
.cr-hero-l{font-size:13px;font-weight:600;color:rgba(255,255,255,.72)}
.cr-hero-card{font-size:22px;font-weight:750;letter-spacing:-.01em;line-height:1.25;margin:4px 0 2px}
.cr-hero-v{font-size:38px;font-weight:800;letter-spacing:-.03em;line-height:1.1;margin:8px 0 4px;white-space:nowrap}
.cr-hero-v small{font-size:14px;font-weight:600;color:rgba(255,255,255,.72);letter-spacing:0;margin-left:4px}
.cr-hero-how{font-size:13px;color:rgba(255,255,255,.82);line-height:1.5;margin-top:6px}
.cr-hero-foot{margin-top:14px;padding-top:12px;border-top:1px solid rgba(255,255,255,.14);font-size:12.5px;color:rgba(255,255,255,.75);line-height:1.5}
.cr-hero-empty{font-size:14px;line-height:1.55;color:rgba(255,255,255,.85)}
.cr-tip{display:flex;gap:10px;align-items:flex-start;font-size:13px;line-height:1.5;background:#FFF8EC;border:1px solid #F6E1BD;color:#6B4A0E;border-radius:12px;padding:10px 12px;margin-top:12px}
.cr-tip svg{flex:0 0 auto;margin-top:2px}

.cr-rank{list-style:none;margin:0;padding:0;display:grid;gap:8px}
.cr-row{display:grid;grid-template-columns:28px minmax(0,1fr) auto;gap:10px;align-items:start;border:1px solid var(--cr-line);border-radius:12px;padding:12px}
.cr-row.best{border-color:#BFD8EE;background:#F7FBFE}
.cr-row.no{opacity:.65}
.cr-pos{width:26px;height:26px;border-radius:8px;background:#F1F3F6;color:#475467;font-size:12.5px;font-weight:700;display:flex;align-items:center;justify-content:center}
.cr-row.best .cr-pos{background:var(--cr-blue);color:#fff}
.cr-row-name{font-size:13.5px;font-weight:650;line-height:1.3}
.cr-row-how{font-size:12.5px;color:var(--cr-muted);margin-top:3px;line-height:1.45}
.cr-row-cap{font-size:12px;color:#8A6A1F;margin-top:4px;line-height:1.45}
.cr-row-v{font-size:16px;font-weight:750;text-align:right;white-space:nowrap}
.cr-row-v small{display:block;font-size:11.5px;font-weight:600;color:var(--cr-faint)}
.cr-row-v.more{color:var(--cr-green)}
.cr-row-act{display:flex;gap:10px;flex-wrap:wrap;align-items:center;margin-top:8px}
.cr-paid{font-size:11px;font-weight:700;letter-spacing:.03em;text-transform:uppercase;color:#946800;background:#FAF3DE;border-radius:5px;padding:2px 6px}
.cr-disclose{font-size:12px;color:var(--cr-muted);line-height:1.5;margin:12px 0 0}

.cr-tips{margin:0;padding-left:18px;font-size:13.5px;line-height:1.6;color:#344054}
.cr-tips li{margin-bottom:6px}
.cr-fine{font-size:12px;color:var(--cr-faint);line-height:1.55;margin:12px 0 0}

@media(max-width:900px){
  .cr-main{grid-template-columns:1fr}
  .cr-side{position:static}
}
@media(max-width:640px){
  .cr-title{font-size:24px}
  .cr-card{padding:16px}
  .cr-hero{padding:20px 18px}
  .cr-hero-v{font-size:32px}
  .cr-input,.cr-select{font-size:16px}
  .cr-custom-grid{grid-template-columns:1fr 1fr}
  .cr-custom-grid .cr-custom-name{grid-column:1/-1}
  .nab-rewards-page .nab-hamburger{position:absolute}
}
</style>
</head>
<body <?php body_class( 'nab-portal-body nab-rewards-page' ); ?>>
<?php wp_body_open(); ?>

<div class="nab-portal-wrap">
  <?php nab_render_sidebar( 'card-rewards' ); ?>

  <main class="nab-main">
    <header class="nab-topbar">
      <div class="nab-topbar-title"><?php echo nab_portal_icon_tile( 'gem', 'gold', 30 ); ?> Card Rewards Checker</div>
      <div class="nab-topbar-right">
        <?php if ( function_exists( 'nab_render_notification_bell' ) ) nab_render_notification_bell(); ?>
      </div>
    </header>

    <div class="nab-content">
      <div class="cr-wrap">

        <section class="cr-head">
          <div class="cr-eyebrow">Card rewards</div>
          <h1 class="cr-title">Which card should you use here?</h1>
          <p class="cr-sub">Add the credit cards you already have, then pick a store. We'll show which card gives you the most cash back or points there, and how we worked it out. We only need the card names, never your card numbers.</p>
        </section>

        <div class="cr-main">
          <div>
            <section class="cr-card" aria-labelledby="crMineTitle">
              <div class="cr-card-head">
                <div>
                  <h2 class="cr-card-title" id="crMineTitle"><span class="cr-step">1</span> Your cards</h2>
                  <div class="cr-card-sub">Saved to your account, so you only do this once.</div>
                </div>
                <button type="button" class="cr-btn cr-btn-sm" id="crAddBtn" aria-expanded="false" aria-controls="crPicker"><?php echo nab_portal_icon( 'plus', 14 ); ?> Add a card</button>
              </div>
              <div class="cr-mine" id="crMine"></div>
              <div class="cr-picker" id="crPicker" hidden>
                <div class="cr-picker-search">
                  <input type="search" class="cr-input" id="crCardSearch" placeholder="Search by card or bank, e.g. TD or Costco" aria-label="Search cards" autocomplete="off">
                </div>
                <div class="cr-list" id="crList" role="list"></div>
                <form class="cr-custom" id="crCustom" novalidate>
                  <div style="font-size:12.5px;font-weight:650;margin-bottom:8px">Card not on the list?</div>
                  <div class="cr-custom-grid">
                    <label class="cr-field cr-custom-name" style="margin:0">Card name<input class="cr-input" name="name" maxlength="60" placeholder="e.g. Home Trust Secured Visa" autocomplete="off"></label>
                    <label class="cr-field" style="margin:0">Type
                      <select class="cr-select" name="network">
                        <?php foreach ( $networks as $k => $label ) : ?><option value="<?php echo esc_attr( $k ); ?>"><?php echo esc_html( $label ); ?></option><?php endforeach; ?>
                      </select>
                    </label>
                    <label class="cr-field" style="margin:0">Cash back<span class="cr-unit pct"><input class="cr-input" name="rate" type="number" min="0" max="10" step="0.25" inputmode="decimal" placeholder="0"></span></label>
                    <button type="submit" class="cr-btn cr-btn-sm" style="height:38px">Add</button>
                  </div>
                  <p class="cr-err" id="crCustomErr" role="alert"></p>
                  <div style="font-size:12px;color:var(--cr-muted);margin-top:6px">Most secured cards earn no rewards. Leave cash back at 0 if yours doesn't.</div>
                </form>
              </div>
              <div class="cr-saved" id="crSaved" aria-live="polite"></div>
            </section>

            <section class="cr-card" aria-labelledby="crBuyTitle">
              <div class="cr-card-head">
                <div>
                  <h2 class="cr-card-title" id="crBuyTitle"><span class="cr-step">2</span> Where are you shopping?</h2>
                </div>
              </div>
              <form id="crForm" novalidate autocomplete="off">
                <label class="cr-field" id="crStoreField">Store
                  <input type="text" class="cr-input" id="crStore" name="store" placeholder="Search a store, e.g. Walmart" role="combobox" aria-autocomplete="list" aria-expanded="false" aria-controls="crSuggest">
                  <div class="cr-suggest" id="crSuggest" role="listbox" hidden></div>
                </label>
                <div class="cr-popular" id="crPopular" aria-label="Popular stores">
                  <?php foreach ( nab_rewards_popular() as $p ) : ?>
                  <button type="button" class="cr-chip" data-store="<?php echo esc_attr( $p ); ?>"><?php echo esc_html( $p ); ?></button>
                  <?php endforeach; ?>
                </div>
                <div class="cr-grid2">
                  <label class="cr-field">Type of store
                    <select class="cr-select" id="crCat" name="cat">
                      <?php foreach ( $cats as $k => $c ) : ?><option value="<?php echo esc_attr( $k ); ?>"><?php echo esc_html( $c['label'] ); ?></option><?php endforeach; ?>
                    </select>
                  </label>
                  <label class="cr-field">Amount
                    <span class="cr-unit"><input type="number" class="cr-input" id="crAmount" name="amount" min="1" max="100000" step="0.01" inputmode="decimal" value="100"></span>
                  </label>
                </div>
                <p class="cr-err" id="crErr" role="alert"></p>
              </form>
            </section>
          </div>

          <aside class="cr-side">
            <section class="cr-card cr-hero" id="crHero" aria-live="polite" aria-label="Best card"></section>
            <section class="cr-card" id="crRankCard" hidden>
              <div class="cr-card-head">
                <div>
                  <h2 class="cr-card-title">All your cards here</h2>
                  <div class="cr-card-sub" id="crRankSub"></div>
                </div>
              </div>
              <ol class="cr-rank" id="crRank"></ol>
            </section>
          </aside>
        </div>

        <section class="cr-card" id="crMoreCard" hidden>
          <div class="cr-card-head">
            <div>
              <h2 class="cr-card-title" id="crMoreTitle">Cards that would earn more here</h2>
              <div class="cr-card-sub" id="crMoreSub"></div>
            </div>
            <?php if ( $match_url !== '#' ) : ?>
            <a class="cr-btn cr-btn-sm" href="<?php echo esc_url( $match_url ); ?>"><?php echo nab_portal_icon( 'credit-card', 14 ); ?> Find a card for your credit</a>
            <?php endif; ?>
          </div>
          <ol class="cr-rank" id="crMore"></ol>
          <p class="cr-disclose" id="crDisclose" hidden>Cards are ranked only by what they earn at this store. Some Apply links are paid links: NAB may get paid if you're approved through them. That never changes the order.</p>
        </section>

        <section class="cr-card">
          <h2 class="cr-card-title" style="margin-bottom:12px">Good to know</h2>
          <ul class="cr-tips">
            <li>Rewards are only worth it if you pay your full balance every month. Card interest is around 20% a year, so carrying a balance costs far more than any cash back.</li>
            <li>Stores are grouped by the bank, not by what you buy. Most cards count Walmart and Costco as general stores, so grocery rates usually don't apply there.</li>
            <li>Some higher rates stop after a yearly or monthly limit. We show the limit under the card when there is one.</li>
            <li>A card with an annual fee only pays off if your rewards add up to more than the fee.</li>
          </ul>
          <p class="cr-fine">These are estimates. Rates were checked on each bank's website on <?php echo esc_html( wp_date( 'F j, Y', strtotime( $checked . ' 12:00:00' ) ) ); ?> and can change at any time. Welcome bonuses and limited-time offers aren't included. Check with your card issuer before you rely on a rate. This is general information, not financial advice.</p>
        </section>

      </div>
    </div>
  </main>
</div>

<?php nab_portal_print_icons_js( [ 'x', 'check', 'plus', 'info', 'triangle-alert', 'arrow-right' ] ); ?>
<script>
window.nabRewardsData = <?php echo wp_json_encode( $data, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP ); ?>;
</script>

<?php nab_footer_scripts(); ?>
<?php wp_footer(); ?>
</body>
</html>
