<?php
/** Documents: our policies and templates. Managers upload and choose what the assistant reads. */
if ( ! defined( 'ABSPATH' ) ) exit;
$manage = nab_staff_can( 'manage' );
$docs   = array_map( 'nab_staff_doc_view', nab_staff_docs() );
?>
<section class="screen" id="s-documents">
  <div class="head">
    <div class="eyebrow">Documents</div>
    <div class="title">Our policies and guides</div>
    <p class="sub">Our policies, procedures, FAQs and reply templates. The Reply Assistant uses the ones switched on.</p>
  </div>
  <div class="card">
    <?php if ( $manage ) : ?>
    <form id="docUpload" class="upload" enctype="multipart/form-data">
      <label class="drop" for="docFile"><svg class="i" viewBox="0 0 24 24" style="width:26px;height:26px;color:var(--brand)"><path d="M12 16V4"/><path d="m7 9 5-5 5 5"/><path d="M20 16v3a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2v-3"/></svg><b id="dropLabel">Click to choose a file</b><span>PDF, Word (.docx) or text, up to 10 MB</span></label>
      <input type="file" id="docFile" name="file" accept=".pdf,.docx,.txt" hidden>
      <div class="row upload-row">
        <input class="in" name="title" id="docTitle" placeholder="Document name (optional, taken from the file)">
        <button class="btn primary" type="submit" id="docSave">Upload</button>
        <button class="btn" type="button" id="docPasteBtn">Or paste text</button>
      </div>
      <div id="docPasteBox" hidden><textarea class="in" name="text" style="min-height:140px;margin-top:10px" placeholder="Paste the policy or template text here"></textarea></div>
      <p class="form-msg" id="docMsg" role="status"></p>
    </form>
    <?php endif; ?>
    <div class="search" style="max-width:420px;margin-bottom:6px"><input class="in" id="docSearch" placeholder="Search documents"></div>
    <table id="docTable">
      <thead><tr><th>Document</th><th>Added by</th><th>Updated</th><th style="text-align:right">Used by the assistant</th></tr></thead>
      <tbody>
      <?php foreach ( $docs as $d ) : ?>
        <tr data-id="<?php echo (int) $d['id']; ?>" data-title="<?php echo esc_attr( strtolower( $d['title'] ) ); ?>">
          <td>
            <div class="file"><span class="ficon <?php echo $d['ext'] === 'PDF' ? 'pdf' : 'doc'; ?>"><?php echo esc_html( $d['ext'] === 'DOCX' ? 'DOC' : $d['ext'] ); ?></span><div><span class="doc-title"><?php echo esc_html( $d['title'] ); ?></span>
              <div class="doc-actions"><button type="button" class="linkbtn" data-act="view">View text</button><?php if ( $d['hasFile'] ) : ?> · <a class="linkbtn" href="<?php echo esc_url( nab_staff_doc_file_url( $d['id'] ) ); ?>">Download</a><?php endif; ?><?php if ( $manage ) : ?> · <button type="button" class="linkbtn" data-act="edit">Edit text</button> · <button type="button" class="linkbtn danger" data-act="delete">Delete</button><?php endif; ?><?php if ( ! $d['words'] ) : ?> <span class="badge warn">No text</span><?php endif; ?></div>
            </div></div>
          </td>
          <td><?php echo esc_html( $d['by'] ); ?></td>
          <td><?php echo esc_html( $d['updated'] ); ?></td>
          <td style="text-align:right"><button type="button" class="switch<?php echo $d['ai'] ? '' : ' off'; ?>" data-act="toggle" aria-label="Used by the assistant" <?php echo $manage ? '' : 'disabled'; ?>></button></td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
    <p class="empty-note" id="docEmpty" <?php echo $docs ? 'hidden' : ''; ?>><?php echo $manage ? 'No documents yet. Upload our cancellation and refund policy, billing procedures, FAQs and reply templates to get started.' : 'No documents yet. Your manager will add them here.'; ?></p>
    <p class="note"><?php echo $manage ? 'When a policy changes, upload the new version and switch the old one off. Check the text of PDFs with "Edit text", as some don\'t come out cleanly. The assistant uses changes from the next reply.' : 'Only managers can upload or change documents. Ask your manager if something here is out of date.'; ?></p>
  </div>

  <div class="modal" id="docModal" hidden>
    <div class="modal-card">
      <div class="card-head"><h2 id="dmTitle"></h2><button class="btn small" type="button" id="dmClose">Close</button></div>
      <input class="in" id="dmName" hidden>
      <textarea class="in" id="dmText" style="min-height:360px" readonly></textarea>
      <div class="row" style="margin-top:12px" id="dmActions" hidden><button class="btn primary" type="button" id="dmSave">Save text</button><span class="form-msg" id="dmMsg"></span></div>
    </div>
  </div>
</section>
