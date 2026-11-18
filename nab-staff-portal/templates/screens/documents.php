<?php
/** Documents screen. v0.1: design with sample data. */
if ( ! defined( 'ABSPATH' ) ) exit;
?>
<section class="screen" id="s-docs">
        <div class="head">
          <div class="eyebrow">Documents</div>
          <div class="title">Our policies and guides</div>
          <p class="sub">Upload our policies, procedures, FAQs and reply templates. The team can search them, and the reply assistant uses the ones switched on.</p>
        </div>
        <div class="card">
          <?php if ( nab_staff_can( 'manage' ) ) : ?>
          <div class="drop"><svg class="i" viewBox="0 0 24 24" style="width:26px;height:26px;color:var(--brand)"><path d="M12 16V4"/><path d="m7 9 5-5 5 5"/><path d="M20 16v3a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2v-3"/></svg><b>Drop a file here or click to upload</b><span>PDF or Word, up to 10 MB</span></div>
          <?php endif; ?>
          <div class="search" style="max-width:420px;margin-bottom:6px"><input class="in" placeholder="Search documents"></div>
          <table>
            <thead><tr><th>Document</th><th>Added by</th><th>Updated</th><th style="text-align:right">Used by the AI</th></tr></thead>
            <tbody>
              <tr><td><div class="file"><span class="ficon pdf">PDF</span>Cancellation and Refund Policy</div></td><td>Matt</td><td>Nov 12, 2026</td><td style="text-align:right"><span class="switch"></span></td></tr>
              <tr><td><div class="file"><span class="ficon doc">DOC</span>PAD and Billing Procedures</div></td><td>Matt</td><td>Nov 10, 2026</td><td style="text-align:right"><span class="switch"></span></td></tr>
              <tr><td><div class="file"><span class="ficon doc">DOC</span>Reply Templates</div></td><td>Jessica K.</td><td>Nov 15, 2026</td><td style="text-align:right"><span class="switch"></span></td></tr>
              <tr><td><div class="file"><span class="ficon pdf">PDF</span>Member FAQs</div></td><td>Matt</td><td>Nov 8, 2026</td><td style="text-align:right"><span class="switch"></span></td></tr>
              <tr><td><div class="file"><span class="ficon doc">DOC</span>Dispute Process Guide</div></td><td>Matt</td><td>Nov 5, 2026</td><td style="text-align:right"><span class="switch"></span></td></tr>
              <tr><td><div class="file"><span class="ficon pdf">PDF</span>Old Pricing 2025</div></td><td>Matt</td><td>Jan 14, 2026</td><td style="text-align:right"><span class="switch off"></span></td></tr>
            </tbody>
          </table>
          <?php if ( nab_staff_can( 'manage' ) ) : ?>
          <p class="note">When a policy changes, upload the new version and switch the old one off. The assistant starts using it from the next reply.</p>
          <?php else : ?>
          <p class="note">Only managers can upload or change documents. Ask your manager if something here is out of date.</p>
          <?php endif; ?>
        </div>
      </section>
