<?php
/** Portal settings (managers): the logo in the sidebar. */
if ( ! defined( 'ABSPATH' ) ) exit;
$logo = nab_staff_logo();
$from = [ 'portal' => 'Uploaded here', 'site' => 'The website logo (Appearance > Customize > Site Identity)', 'icon' => 'The site icon (no logo set)', 'none' => 'No logo set, showing the NAB mark' ][ $logo['source'] ];
?>
<section class="screen" id="s-settings">
  <div class="head">
    <div class="eyebrow">Portal settings</div>
    <div class="title">How the staff portal looks</div>
    <p class="sub">Change the logo in the top left of the staff portal. This doesn't change the main website or the member portal.</p>
  </div>
  <form class="card" id="logoForm" enctype="multipart/form-data">
    <h2>Logo</h2>
    <div class="logo-set">
      <div class="logo-preview" id="logoPreview"><?php if ( $logo['url'] ) : ?><img src="<?php echo esc_url( $logo['url'] ); ?>" alt="Current logo"><?php else : ?><span class="brand-mark">NAB</span><?php endif; ?></div>
      <div>
        <p class="hint" style="margin:0 0 4px">Showing now: <b id="logoFrom"><?php echo esc_html( $from ); ?></b></p>
        <p class="hint" style="margin:0 0 14px">PNG, JPG or WebP, up to 2 MB. A wide logo with a transparent background, at least 300 pixels wide, looks best.</p>
        <input type="file" id="logoFile" name="logo" accept=".png,.jpg,.jpeg,.webp,image/png,image/jpeg,image/webp" hidden>
        <div class="row">
          <label class="btn primary" for="logoFile">Upload a new logo</label>
          <button class="btn" type="button" id="logoReset" <?php echo $logo['source'] === 'portal' ? '' : 'hidden'; ?>>Use the website logo</button>
        </div>
        <p class="form-msg" id="logoMsg" role="status"></p>
      </div>
    </div>
  </form>
</section>
