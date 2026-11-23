<?php
/**
 * Team documents: policies, procedures, FAQs and reply templates.
 *
 * Managers upload PDF, Word (.docx) or text files. The text is pulled out of the
 * file and saved, and managers can check and correct it (PDFs don't always come
 * out clean). Documents switched on are what the Reply Assistant reads.
 * Everyone on the team can read and download them.
 *
 * Meta: _file (stored name), _orig (original name), _ext, _size, _ai (1/0).
 * The text the assistant reads is the post content.
 */
if ( ! defined( 'ABSPATH' ) ) exit;

const NAB_STAFF_DOC_MAX_BYTES = 10 * 1024 * 1024;

function nab_staff_docs( $only_ai = false ) {
    $q = [ 'post_type' => 'nab_staff_doc', 'post_status' => 'publish', 'numberposts' => 200, 'orderby' => 'modified', 'order' => 'DESC' ];
    if ( $only_ai ) $q['meta_query'] = [ [ 'key' => '_ai', 'value' => '1' ] ];
    return get_posts( $q );
}

function nab_staff_doc_view( $p ) {
    $text = (string) $p->post_content;
    return [
        'id'      => (int) $p->ID,
        'title'   => get_the_title( $p ),
        'ext'     => strtoupper( (string) get_post_meta( $p->ID, '_ext', true ) ?: 'TXT' ),
        'by'      => nab_staff_person( $p->post_author )['name'],
        'updated' => wp_date( 'M j, Y', strtotime( $p->post_modified_gmt . ' UTC' ) ),
        'ai'      => get_post_meta( $p->ID, '_ai', true ) === '1',
        'words'   => str_word_count( $text ),
        'hasFile' => (bool) get_post_meta( $p->ID, '_file', true ),
    ];
}

/* ── Text extraction ─────────────────────────────────────── */

function nab_staff_extract_text( $path, $ext ) {
    $text = '';
    if ( $ext === 'txt' ) {
        $text = (string) file_get_contents( $path );
        if ( ! preg_match( '//u', $text ) ) $text = mb_convert_encoding( $text, 'UTF-8', 'Windows-1252' );
    } elseif ( $ext === 'docx' ) {
        $text = nab_staff_docx_text( $path );
    } elseif ( $ext === 'pdf' ) {
        $text = nab_staff_pdf_text( $path );
    }
    $text = preg_replace( "/[ \t]+/", ' ', str_replace( "\r", '', $text ) );
    $text = preg_replace( "/\n{3,}/", "\n\n", $text );
    return mb_substr( trim( $text ), 0, 200000 );
}

function nab_staff_docx_text( $path ) {
    if ( ! class_exists( 'ZipArchive' ) ) return '';
    $zip = new ZipArchive();
    if ( $zip->open( $path ) !== true ) return '';
    $xml = (string) $zip->getFromName( 'word/document.xml' );
    $zip->close();
    if ( $xml === '' ) return '';
    $xml = preg_replace( '#<w:tab/>#', "\t", $xml );
    $xml = preg_replace( '#<w:br[^>]*/>#', "\n", $xml );
    $xml = preg_replace( '#</w:p>#', "\n", $xml );
    $xml = preg_replace( '#</w:tc>#', ' | ', $xml );
    return html_entity_decode( wp_strip_all_tags( $xml ), ENT_QUOTES | ENT_XML1, 'UTF-8' );
}

/**
 * Plain PDF text, good enough for typed policy documents. Scanned PDFs (images)
 * have no text: the manager pastes it in instead.
 */
function nab_staff_pdf_text( $path ) {
    $raw = (string) file_get_contents( $path );
    if ( $raw === '' || strpos( $raw, '%PDF' ) !== 0 ) return '';
    $out = '';
    if ( ! preg_match_all( '#stream\r?\n(.*?)\r?\nendstream#s', $raw, $m ) ) return '';
    foreach ( $m[1] as $data ) {
        $plain = @gzuncompress( $data );
        if ( $plain === false ) $plain = @gzinflate( $data );
        if ( $plain === false ) $plain = $data;
        if ( strpos( $plain, 'BT' ) === false ) continue;
        if ( ! preg_match_all( '#BT(.*?)ET#s', $plain, $blocks ) ) continue;
        foreach ( $blocks[1] as $b ) {
            $line = '';
            // Tokens in order: (strings), [arrays] with TJ, and line moves.
            preg_match_all( '#\((?:\\\\.|[^\\\\)])*\)|\[(?:[^\]]*)\]\s*TJ|T\*|Td|TD|\'|"#s', $b, $toks );
            foreach ( $toks[0] as $t ) {
                if ( $t === 'T*' || $t === 'Td' || $t === 'TD' || $t === "'" || $t === '"' ) { $line .= "\n"; continue; }
                if ( $t[0] === '[' ) {
                    preg_match_all( '#\((?:\\\\.|[^\\\\)])*\)|-?\d+(?:\.\d+)?#', $t, $parts );
                    foreach ( $parts[0] as $pp ) {
                        if ( $pp[0] === '(' ) $line .= nab_staff_pdf_unescape( substr( $pp, 1, -1 ) );
                        elseif ( (float) $pp < -200 ) $line .= ' '; // a wide gap is a space
                    }
                    continue;
                }
                $line .= nab_staff_pdf_unescape( substr( $t, 1, -1 ) );
            }
            $out .= $line . "\n";
        }
    }
    $out = preg_replace( "/\n\s*\n+/", "\n", $out );
    // Drop text that is mostly unreadable (custom font encodings).
    $readable = preg_match_all( '/[A-Za-z0-9 .,]/', $out );
    if ( $out === '' || $readable < 0.6 * strlen( $out ) ) return '';
    return $out;
}

function nab_staff_pdf_unescape( $s ) {
    $s = preg_replace_callback( '/\\\\([0-7]{1,3})/', function ( $m ) { return chr( octdec( $m[1] ) ); }, $s );
    $s = strtr( $s, [ '\\n' => "\n", '\\r' => '', '\\t' => ' ', '\\(' => '(', '\\)' => ')', '\\\\' => '\\' ] );
    return mb_convert_encoding( $s, 'UTF-8', 'Windows-1252' );
}

/* ── Actions (called from inc/ajax.php) ──────────────────── */

function nab_staff_doc_upload() {
    $title = sanitize_text_field( wp_unslash( $_POST['title'] ?? '' ) );
    $file  = $_FILES['file'] ?? null;
    $pasted = isset( $_POST['text'] ) ? sanitize_textarea_field( wp_unslash( $_POST['text'] ) ) : '';

    $ext = ''; $stored = ''; $size = 0; $orig = ''; $text = '';
    if ( $file && empty( $file['error'] ) && ! empty( $file['tmp_name'] ) && is_uploaded_file( $file['tmp_name'] ) ) {
        $orig = sanitize_file_name( (string) $file['name'] );
        $ext  = strtolower( pathinfo( $orig, PATHINFO_EXTENSION ) );
        if ( ! in_array( $ext, [ 'pdf', 'docx', 'txt' ], true ) ) return [ 'error' => 'Please upload a PDF, Word (.docx) or text file. For an older .doc file, open it in Word and save it as .docx first.' ];
        $size = (int) $file['size'];
        if ( $size > NAB_STAFF_DOC_MAX_BYTES ) return [ 'error' => 'The file is bigger than 10 MB.' ];
        $stored = wp_generate_password( 24, false, false ) . '.' . $ext;
        if ( ! @move_uploaded_file( $file['tmp_name'], nab_staff_docs_dir() . '/' . $stored ) ) return [ 'error' => 'The file could not be saved. Please try again.' ];
        $text = nab_staff_extract_text( nab_staff_docs_dir() . '/' . $stored, $ext );
        if ( $title === '' ) $title = ucwords( str_replace( [ '-', '_' ], ' ', pathinfo( $orig, PATHINFO_FILENAME ) ) );
    } elseif ( $pasted !== '' ) {
        $text = mb_substr( $pasted, 0, 200000 );
    } else {
        return [ 'error' => 'Choose a file to upload, or paste the text.' ];
    }
    if ( $title === '' ) return [ 'error' => 'Please give the document a name.' ];

    $id = wp_insert_post( [ 'post_type' => 'nab_staff_doc', 'post_status' => 'publish', 'post_title' => mb_substr( $title, 0, 150 ), 'post_content' => $text, 'post_author' => get_current_user_id() ], true );
    if ( is_wp_error( $id ) ) return [ 'error' => 'The document could not be saved.' ];
    update_post_meta( $id, '_ai', '1' );
    if ( $stored ) {
        update_post_meta( $id, '_file', $stored );
        update_post_meta( $id, '_orig', $orig );
        update_post_meta( $id, '_ext', $ext );
        update_post_meta( $id, '_size', $size );
    } else {
        update_post_meta( $id, '_ext', 'txt' );
    }
    $msg = $text === ''
        ? 'Saved. We could not read any text from this file (it may be a scanned PDF), so please open it and paste the text in, or the assistant won\'t be able to use it.'
        : 'Saved. The assistant will use it from the next reply.';
    return [ 'message' => $msg, 'doc' => nab_staff_doc_view( get_post( $id ) ), 'noText' => $text === '' ];
}

function nab_staff_doc_get_text( $id ) {
    $p = get_post( $id );
    if ( ! $p || $p->post_type !== 'nab_staff_doc' ) return [ 'error' => 'Document not found.' ];
    return [ 'title' => get_the_title( $p ), 'text' => (string) $p->post_content ];
}

function nab_staff_doc_save_text( $id ) {
    $p = get_post( $id );
    if ( ! $p || $p->post_type !== 'nab_staff_doc' ) return [ 'error' => 'Document not found.' ];
    $title = sanitize_text_field( wp_unslash( $_POST['title'] ?? '' ) );
    $text  = sanitize_textarea_field( wp_unslash( $_POST['text'] ?? '' ) );
    wp_update_post( [ 'ID' => $id, 'post_title' => $title !== '' ? mb_substr( $title, 0, 150 ) : $p->post_title, 'post_content' => mb_substr( $text, 0, 200000 ) ] );
    return [ 'message' => 'Saved.', 'doc' => nab_staff_doc_view( get_post( $id ) ) ];
}

function nab_staff_doc_toggle( $id ) {
    $p = get_post( $id );
    if ( ! $p || $p->post_type !== 'nab_staff_doc' ) return [ 'error' => 'Document not found.' ];
    $on = get_post_meta( $id, '_ai', true ) === '1' ? '0' : '1';
    update_post_meta( $id, '_ai', $on );
    return [ 'ai' => $on === '1' ];
}

function nab_staff_doc_delete( $id ) {
    $p = get_post( $id );
    if ( ! $p || $p->post_type !== 'nab_staff_doc' ) return [ 'error' => 'Document not found.' ];
    $file = get_post_meta( $id, '_file', true );
    if ( $file ) @unlink( nab_staff_docs_dir() . '/' . basename( $file ) );
    wp_delete_post( $id, true );
    return [ 'message' => 'Deleted.' ];
}

/** Download the original file (team only). */
function nab_staff_doc_download( $id ) {
    $p    = get_post( $id );
    $file = $p && $p->post_type === 'nab_staff_doc' ? basename( (string) get_post_meta( $id, '_file', true ) ) : '';
    $path = $file ? nab_staff_docs_dir() . '/' . $file : '';
    if ( ! $path || ! is_file( $path ) ) wp_die( 'File not found.', 404 );
    $types = [ 'pdf' => 'application/pdf', 'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document', 'txt' => 'text/plain; charset=utf-8' ];
    $ext   = get_post_meta( $id, '_ext', true );
    nocache_headers();
    header( 'Content-Type: ' . ( $types[ $ext ] ?? 'application/octet-stream' ) );
    header( 'Content-Disposition: attachment; filename="' . str_replace( '"', '', get_post_meta( $id, '_orig', true ) ?: 'document.' . $ext ) . '"' );
    header( 'Content-Length: ' . filesize( $path ) );
    header( 'X-Content-Type-Options: nosniff' );
    readfile( $path );
    exit;
}
