<?php
/**
 * NAB Member Portal — Dispute Letter Builder (v1.14.0, reworked in v1.18.0)
 *
 * The member picks what is wrong on their credit report and ONE bureau
 * (Equifax or TransUnion), lists every account or item they are disputing,
 * checks their details, and the portal writes a plain, professional dispute
 * letter for them. They review the whole letter, download the PDF, sign it
 * and submit it on the bureau's own website. NAB never sends anything.
 *
 * v1.18.0 (Matthew's review):
 *   - a short explanation under each dispute type
 *   - one bureau per letter, shown with its logo, plus a reminder
 *   - several accounts or creditors in one letter, each with: name,
 *     account number (last 4 digits), what the report shows, why it is
 *     wrong, and what should be corrected
 *   - details come from the profile and can be edited; no date of birth
 *   - optional private uploads (supporting documents, bureau responses)
 *   - a "check before you download" list and a sending checklist
 *   - status tracker: Draft, Ready to send, Sent, Response received,
 *     Resolved, Not resolved, with dates and notes
 *
 * The letter wording lives in one place, nab_dispute_letter_blocks(): the
 * page preview and the PDF are both drawn from it, so they always match.
 *
 * User meta:
 *   nab_dispute_letters  — JSON [ { id, type, bureau, f: { name, street, city,
 *                          province, postal, items: [ … ], police, aware, fileref },
 *                          status, created, ready, sent, response, closed,
 *                          notes, files: [ { id, name, kind, size, mime, added } ],
 *                          reminded } ]
 *   nab_dispute_profile  — JSON { name, street, city, province, postal }
 *   nab_dispute_notified — Y-m-d of the last reminder check (once a day)
 *   Uploaded files live in uploads/nab-dispute-files/<user id>/, which is
 *   closed to the web; they are only served to their owner through AJAX.
 *
 * AJAX (nab_portal_nonce, logged in):
 *   nab_dispute_save / _status / _notes / _delete
 *   nab_dispute_upload / _file_delete
 *   nab_dispute_pdf, nab_dispute_file (GET downloads)
 *
 * @package NAB_Member_Portal
 * @since   1.14.0
 */
if ( ! defined( 'ABSPATH' ) ) exit;

/* ═══════════════════════════════════════════════════════════
   SETTINGS
   ═══════════════════════════════════════════════════════════ */

/** The two Canadian bureaus. Links and logos can be set in ACF on the Dispute Center page. */
function nab_dispute_bureaus() {
    static $page = null;
    if ( $page === null ) {
        $found = get_pages( [ 'meta_key' => '_wp_page_template', 'meta_value' => 'nab-dispute-center', 'number' => 1 ] );
        $page  = $found ? (int) $found[0]->ID : 0;
    }
    $acf = function( $name ) use ( $page ) {
        if ( ! $page || ! function_exists( 'get_field' ) ) return '';
        $v = get_field( $name, $page );
        if ( is_array( $v ) ) $v = $v['url'] ?? '';
        return esc_url_raw( trim( (string) $v ) );
    };
    return apply_filters( 'nab_dispute_bureaus', [
        'equifax' => [
            'name'  => 'Equifax Canada',
            'legal' => 'Equifax Canada Co.',
            'dept'  => 'National Consumer Relations',
            'url'   => $acf( 'dc_equifax_url' ) ?: 'https://www.consumer.equifax.ca/personal/dispute-credit-report',
            'logo'  => $acf( 'dc_equifax_logo' ),
        ],
        'transunion' => [
            'name'  => 'TransUnion Canada',
            'legal' => 'TransUnion Canada',
            'dept'  => 'Consumer Relations Department',
            'url'   => $acf( 'dc_transunion_url' ) ?: 'https://www.transunion.ca/assistance/credit-report-disputes',
            'logo'  => $acf( 'dc_transunion_logo' ),
        ],
    ] );
}

function nab_dispute_provinces() {
    return [
        'AB' => 'Alberta', 'BC' => 'British Columbia', 'MB' => 'Manitoba', 'NB' => 'New Brunswick',
        'NL' => 'Newfoundland and Labrador', 'NS' => 'Nova Scotia', 'NT' => 'Northwest Territories',
        'NU' => 'Nunavut', 'ON' => 'Ontario', 'PE' => 'Prince Edward Island', 'QC' => 'Quebec',
        'SK' => 'Saskatchewan', 'YT' => 'Yukon',
    ];
}

/** Letter statuses, in order. resolved and unresolved are the two possible endings. */
function nab_dispute_statuses() {
    return [
        'draft'      => [ 'label' => 'Draft',             'hint' => 'Letter created, not downloaded yet.' ],
        'ready'      => [ 'label' => 'Ready to send',     'hint' => 'Downloaded and ready to submit.' ],
        'sent'       => [ 'label' => 'Sent',              'hint' => 'You submitted it to the bureau.' ],
        'response'   => [ 'label' => 'Response received', 'hint' => 'The bureau replied.' ],
        'resolved'   => [ 'label' => 'Resolved',          'hint' => 'The error was corrected.' ],
        'unresolved' => [ 'label' => 'Not resolved',      'hint' => 'The error is still there.' ],
    ];
}

/** Field sets for the items in a letter. `req` fields must be filled in. */
function nab_dispute_item_fields( $mode ) {
    $sets = [
        'accounts' => [
            [ 'key' => 'creditor', 'label' => 'Creditor or account name', 'req' => true ],
            [ 'key' => 'account',  'label' => 'Account number', 'hint' => 'The last 4 digits are enough.' ],
            [ 'key' => 'info',     'label' => 'What your report shows', 'req' => true, 'long' => true ],
            [ 'key' => 'reason',   'label' => 'Why it is wrong', 'req' => true, 'long' => true ],
            [ 'key' => 'fix',      'label' => 'What should be corrected', 'long' => true ],
        ],
        'inquiries' => [
            [ 'key' => 'company', 'label' => 'Company that checked your credit', 'req' => true ],
            [ 'key' => 'date',    'label' => 'Date of the inquiry', 'type' => 'date' ],
            [ 'key' => 'reason',  'label' => 'Why you are disputing it', 'long' => true ],
        ],
        'personal' => [
            [ 'key' => 'info', 'label' => 'What your report shows', 'req' => true, 'long' => true ],
            [ 'key' => 'fix',  'label' => 'The correct information', 'req' => true, 'long' => true ],
        ],
    ];
    return $sets[ $mode ] ?? $sets['accounts'];
}

/**
 * The six dispute types, in the order members see them. `ph` holds example
 * text for the item fields; `fix` is the request used when the member leaves
 * "What should be corrected" empty.
 */
function nab_dispute_types() {
    $id_address = [ 'Copy of government-issued photo ID', 'Proof of current address (for example a utility bill or bank statement)' ];
    return apply_filters( 'nab_dispute_types', [
        'late' => [
            'label' => 'Late payment error',
            'desc'  => 'A payment was reported late even though it was paid on time, or the reporting is incorrect.',
            'icon'  => 'clock', 'tone' => 'orange', 'mode' => 'accounts',
            'ph'    => [ 'creditor' => 'e.g. ABC Bank Visa', 'account' => 'e.g. ending 1234', 'info' => 'e.g. My March 2026 payment shows as 30 days late', 'reason' => 'e.g. I paid on March 10, before the due date. My bank statement shows the payment.', 'fix' => 'e.g. Show the March 2026 payment as paid on time' ],
            'fix'   => 'Please correct this entry to show the payment as made on time, or remove it if it cannot be verified.',
            'enclose' => [ 'Bank or payment statement showing the date you paid', 'Payment confirmation or receipt', 'Copy of the credit report page showing the late payment' ],
        ],
        'notmine' => [
            'label' => 'Account not mine',
            'desc'  => 'An account appears on your credit report that you do not recognize.',
            'icon'  => 'user', 'tone' => 'purple', 'mode' => 'accounts',
            'ph'    => [ 'creditor' => 'e.g. XYZ Finance', 'account' => 'e.g. ending 5678', 'info' => 'e.g. A loan account opened in 2024 with a $3,200 balance', 'reason' => 'e.g. I have never had an account with XYZ Finance', 'fix' => 'e.g. Remove this account from my credit file' ],
            'fix'   => 'Please confirm that this account does not belong to me and remove it from my credit file.',
            'enclose' => array_merge( $id_address, [ 'Copy of the credit report page showing the account' ] ),
        ],
        'fraud' => [
            'label' => 'Fraudulent account',
            'desc'  => 'An account was opened using your personal information without your permission.',
            'icon'  => 'triangle-alert', 'tone' => 'red', 'mode' => 'accounts',
            'ph'    => [ 'creditor' => 'e.g. Quick Loans Inc.', 'account' => 'e.g. ending 9012', 'info' => 'e.g. A credit card opened in May 2026', 'reason' => 'e.g. I did not apply for or open this account. Someone used my information.', 'fix' => 'e.g. Remove this account and add a fraud alert to my file' ],
            'fix'   => 'Please remove this fraudulent account from my credit file.',
            'extras' => [
                [ 'key' => 'aware',  'label' => 'When you found out about it', 'type' => 'date' ],
                [ 'key' => 'police', 'label' => 'Police or Canadian Anti-Fraud Centre report number', 'hint' => "Leave empty if you haven't reported it yet." ],
            ],
            'enclose' => [ 'Copy of government-issued photo ID', 'Police report or Canadian Anti-Fraud Centre reference, if you have one', 'Copy of the credit report page showing the account' ],
        ],
        'inquiry' => [
            'label' => 'Hard inquiry',
            'desc'  => 'An inquiry appears that you did not authorize or do not recognize.',
            'icon'  => 'search', 'tone' => 'teal', 'mode' => 'inquiries',
            'ph'    => [ 'company' => 'e.g. ABC Lending', 'reason' => 'e.g. I did not apply for credit with this company' ],
            'fix'   => 'I did not authorize this inquiry.',
            'enclose' => array_merge( $id_address, [ 'Copy of the credit report page showing the inquiries' ] ),
        ],
        'personal' => [
            'label' => 'Incorrect personal information',
            'desc'  => 'Your name, address or other personal details are wrong.',
            'icon'  => 'pencil', 'tone' => 'indigo', 'mode' => 'personal',
            'ph'    => [ 'info' => 'e.g. My last name is spelled "Smyth"', 'fix' => 'e.g. My last name is "Smith"' ],
            'extras' => [
                [ 'key' => 'fileref', 'label' => 'File or reference number on your report', 'hint' => 'Optional.' ],
            ],
            'enclose' => [ 'Copy of government-issued photo ID', 'Proof of current address (for example a utility bill or bank statement)', 'Copy of the credit report page with the error highlighted' ],
        ],
        'general' => [
            'label' => 'General dispute',
            'desc'  => 'For other reporting errors that do not fall under the categories above.',
            'icon'  => 'file-text', 'tone' => 'blue', 'mode' => 'accounts',
            'ph'    => [ 'creditor' => 'e.g. ABC Bank', 'account' => 'e.g. ending 1234', 'info' => 'e.g. The balance shows $2,400', 'reason' => 'e.g. I paid this account in full in January 2026', 'fix' => 'e.g. Update the balance to $0 and show the account as paid' ],
            'fix'   => 'Please investigate this item and correct or remove it if it cannot be verified.',
            'enclose' => array_merge( $id_address, [ 'Copy of the credit report page showing the item' ] ),
        ],
    ] );
}

/* ═══════════════════════════════════════════════════════════
   DATA
   ═══════════════════════════════════════════════════════════ */

/**
 * Brings a stored letter up to the current shape. Letters made before
 * v1.18.0 had one account per letter and three statuses; they are read
 * as the new shape here and saved that way on their next change.
 */
function nab_dispute_normalize( $l ) {
    $f   = (array) ( $l['f'] ?? [] );
    $v   = function( $k ) use ( $f ) { return trim( (string) ( $f[ $k ] ?? '' ) ); };
    $type = $l['type'] ?? 'general';
    if ( $type === 'round1' ) $type = 'general';

    if ( ! isset( $f['items'] ) || ! is_array( $f['items'] ) || ( $l['type'] ?? '' ) === 'round1' ) {
        $items = [];
        switch ( $l['type'] ?? '' ) {
            case 'round1':
                foreach ( (array) ( $f['items'] ?? [] ) as $r ) {
                    $items[] = [ 'creditor' => $r['creditor'] ?? '', 'account' => $r['account'] ?? '', 'info' => '', 'reason' => $r['reason'] ?? '', 'fix' => '' ];
                }
                break;
            case 'late':
                $items[] = [ 'creditor' => $v( 'creditor' ), 'account' => $v( 'account' ),
                    'info'   => $v( 'late_date' ) ? 'A payment is reported as late on ' . nab_dispute_fmt_date( $v( 'late_date' ) ) . '.' : 'A payment is reported as late.',
                    'reason' => trim( ( $v( 'paid_date' ) ? 'I made the payment on ' . nab_dispute_fmt_date( $v( 'paid_date' ) ) . '. ' : '' ) . ( $v( 'proof' ) ? 'My proof: ' . $v( 'proof' ) . '.' : '' ) ),
                    'fix'    => '' ];
                break;
            case 'notmine':
            case 'fraud':
                $items[] = [ 'creditor' => $v( 'creditor' ), 'account' => $v( 'account' ), 'info' => 'This account appears on my credit report.',
                    'reason' => $l['type'] === 'fraud' ? 'I did not open or authorize this account.' : 'This account does not belong to me.', 'fix' => '' ];
                break;
            case 'personal':
                $items[] = [ 'info' => $v( 'wrong' ), 'fix' => $v( 'correct' ) ];
                break;
        }
        $f['items'] = $items;
    }
    unset( $f['dob'], $f['creditor'], $f['account'], $f['late_date'], $f['paid_date'], $f['proof'], $f['wrong'], $f['correct'], $f['opened'] );

    $status = $l['status'] ?? 'draft';
    if ( $status === 'created' ) $status = 'draft';
    if ( ! isset( nab_dispute_statuses()[ $status ] ) ) $status = 'draft';

    return [
        'id'       => (string) ( $l['id'] ?? '' ),
        'type'     => $type,
        'bureau'   => (string) ( $l['bureau'] ?? '' ),
        'f'        => $f,
        'status'   => $status,
        'created'  => (string) ( $l['created'] ?? '' ),
        'ready'    => (string) ( $l['ready'] ?? '' ),
        'sent'     => (string) ( $l['sent'] ?? '' ),
        'response' => (string) ( $l['response'] ?? '' ),
        'closed'   => (string) ( $l['closed'] ?? '' ),
        'notes'    => (string) ( $l['notes'] ?? '' ),
        'files'    => array_values( (array) ( $l['files'] ?? [] ) ),
        'reminded' => ! empty( $l['reminded'] ),
    ];
}

function nab_dispute_get_letters( $uid ) {
    return array_map( 'nab_dispute_normalize', array_values( nab_dash_json_meta( $uid, 'nab_dispute_letters' ) ) );
}
function nab_dispute_put_letters( $uid, $letters ) {
    update_user_meta( $uid, 'nab_dispute_letters', wp_json_encode( array_values( $letters ) ) );
}

/** The member's details for a new letter: from their last letter, else their profile. */
function nab_dispute_get_profile( $uid ) {
    $p = nab_dash_json_meta( $uid, 'nab_dispute_profile' );
    if ( $p ) { unset( $p['dob'] ); return $p; }
    $u    = get_userdata( $uid );
    $name = $u ? trim( $u->first_name . ' ' . $u->last_name ) : '';
    return [
        'name'   => $name ?: ( $u ? $u->display_name : '' ),
        'street' => (string) get_user_meta( $uid, 'nab_address', true ),
        'city'   => '', 'province' => '', 'postal' => '',
    ];
}

/** Everything the page needs. Each letter carries its finished text and its checks. */
function nab_dispute_page_data( $uid ) {
    $letters = nab_dispute_get_letters( $uid );
    foreach ( $letters as $i => $l ) {
        $letters[ $i ]['blocks'] = nab_dispute_letter_blocks( $l );
        $letters[ $i ]['checks'] = nab_dispute_letter_checks( $l );
    }
    $types = nab_dispute_types();
    foreach ( $types as $k => $t ) $types[ $k ]['fields'] = nab_dispute_item_fields( $t['mode'] );
    $nonce = wp_create_nonce( 'nab_portal_nonce' );
    return [
        'today'     => current_time( 'Y-m-d' ),
        'types'     => $types,
        'bureaus'   => nab_dispute_bureaus(),
        'provinces' => nab_dispute_provinces(),
        'statuses'  => nab_dispute_statuses(),
        'profile'   => nab_dispute_get_profile( $uid ),
        'letters'   => array_reverse( $letters ), // newest first
        'maxItems'  => 10,
        'maxFiles'  => 10,
        'maxFileMb' => 8,
        'pdf'       => add_query_arg( [ 'action' => 'nab_dispute_pdf', 'nonce' => $nonce ], admin_url( 'admin-ajax.php' ) ),
        'fileUrl'   => add_query_arg( [ 'action' => 'nab_dispute_file', 'nonce' => $nonce ], admin_url( 'admin-ajax.php' ) ),
    ];
}

/* ═══════════════════════════════════════════════════════════
   THE LETTER
   Blocks: [ 't' => 'lines', 's' => [ … ] ]  lines with no space between
           [ 't' => 'p', 's' => '…' ]        paragraph
           [ 't' => 'b', 's' => '…' ]        bold paragraph (subject, headings)
           [ 't' => 'li', 's' => '…' ]       bullet
           [ 't' => 'item', 'n', 'title', 'rows' => [ [ label, value ] ] ]
                                             one disputed account or item
           [ 't' => 'sign' ]                 room and a line for a signature
   The tone is plain and polite: state the facts, ask for an investigation
   and a correction, thank them. No legal threats.
   ═══════════════════════════════════════════════════════════ */

/** Y-m-d check that also allows older dates (nab_cal_valid_date() starts at 2000). */
function nab_dispute_valid_date( $d ) {
    if ( ! is_string( $d ) || ! preg_match( '/^\d{4}-\d{2}-\d{2}$/', $d ) ) return false;
    list( $y, $m, $day ) = array_map( 'intval', explode( '-', $d ) );
    return checkdate( $m, $day, $y ) && $y >= 1900 && $y <= 2100;
}

function nab_dispute_fmt_date( $d ) {
    return nab_dispute_valid_date( $d ) ? wp_date( 'F j, Y', strtotime( $d . ' 12:00' ) ) : '';
}

/** "ABC Bank, account ending 1234" */
function nab_dispute_item_title( $it ) {
    $name = trim( (string) ( $it['creditor'] ?? $it['company'] ?? '' ) );
    $acct = trim( (string) ( $it['account'] ?? '' ) );
    if ( $acct !== '' ) {
        $acct = preg_match( '/^\d{2,}$/', $acct ) ? 'account ending ' . substr( $acct, -4 ) : 'account ' . $acct;
        return $name . ', ' . $acct;
    }
    return $name;
}

function nab_dispute_letter_blocks( $l ) {
    $types   = nab_dispute_types();
    $bureaus = nab_dispute_bureaus();
    $type    = $types[ $l['type'] ?? '' ] ?? null;
    $bureau  = $bureaus[ $l['bureau'] ?? '' ] ?? null;
    if ( ! $type || ! $bureau ) return [];
    $f     = (array) ( $l['f'] ?? [] );
    $v     = function( $k ) use ( $f ) { return trim( (string) ( $f[ $k ] ?? '' ) ); };
    $prov  = nab_dispute_provinces()[ $v( 'province' ) ] ?? $v( 'province' );
    $items = array_values( (array) ( $f['items'] ?? [] ) );
    $many  = count( $items ) > 1;

    $out = [
        [ 't' => 'lines', 's' => array_values( array_filter( [ $v( 'name' ), $v( 'street' ), trim( $v( 'city' ) . ( $prov ? ', ' . $prov : '' ) . ' ' . $v( 'postal' ) ) ] ) ) ],
        [ 't' => 'lines', 's' => [ nab_dispute_fmt_date( $l['created'] ?? '' ) ] ],
        [ 't' => 'lines', 's' => [ $bureau['legal'], $bureau['dept'] ] ],
    ];
    $p = function( $s ) use ( &$out ) { $out[] = [ 't' => 'p', 's' => $s ]; };
    $b = function( $s ) use ( &$out ) { $out[] = [ 't' => 'b', 's' => $s ]; };

    switch ( $l['type'] ) {
        case 'late':
            $b( 'Re: Request to correct late payment reporting' . ( $v( 'name' ) ? ', ' . $v( 'name' ) : '' ) );
            $p( 'Dear ' . $bureau['name'] . ',' );
            $p( 'I am writing to ask you to investigate late payment information on my credit report that I believe is incorrect. ' . ( $many ? 'The accounts are listed below.' : 'The details are below.' ) );
            break;
        case 'notmine':
            $b( 'Re: Request to investigate ' . ( $many ? 'accounts' : 'an account' ) . ' that ' . ( $many ? 'do' : 'does' ) . ' not belong to me' . ( $v( 'name' ) ? ', ' . $v( 'name' ) : '' ) );
            $p( 'Dear ' . $bureau['name'] . ',' );
            $p( 'I am writing about ' . ( $many ? 'accounts' : 'an account' ) . ' on my credit report that I do not recognize. I have never had ' . ( $many ? 'these accounts' : 'this account' ) . ', and I believe ' . ( $many ? 'they belong' : 'it belongs' ) . ' to someone else or ' . ( $many ? 'were' : 'was' ) . ' added to my file by mistake.' );
            break;
        case 'fraud':
            $b( 'Re: Fraudulent ' . ( $many ? 'accounts' : 'account' ) . ' on my credit report' . ( $v( 'name' ) ? ', ' . $v( 'name' ) : '' ) );
            $p( 'Dear ' . $bureau['name'] . ',' );
            $p( 'I am a victim of identity theft. The ' . ( $many ? 'accounts' : 'account' ) . ' below ' . ( $many ? 'were' : 'was' ) . ' opened using my personal information without my permission. I did not apply for, open or use ' . ( $many ? 'them' : 'it' ) . '.' );
            $extra = [];
            if ( $v( 'aware' ) ) $extra[] = 'I first found out about this on ' . nab_dispute_fmt_date( $v( 'aware' ) ) . '.';
            if ( $v( 'police' ) ) $extra[] = 'I have reported it, and the report number is ' . $v( 'police' ) . '.';
            if ( $extra ) $p( implode( ' ', $extra ) );
            break;
        case 'inquiry':
            $b( 'Re: Request to remove unauthorized hard ' . ( $many ? 'inquiries' : 'inquiry' ) . ( $v( 'name' ) ? ', ' . $v( 'name' ) : '' ) );
            $p( 'Dear ' . $bureau['name'] . ',' );
            $p( 'I am writing about hard ' . ( $many ? 'inquiries' : 'an inquiry' ) . ' on my credit report that I did not authorize or do not recognize. I did not give ' . ( $many ? 'these companies' : 'this company' ) . ' permission to check my credit.' );
            break;
        case 'personal':
            $b( 'Re: Request to correct my personal information' . ( $v( 'name' ) ? ', ' . $v( 'name' ) : '' ) . ( $v( 'fileref' ) ? ', file ' . $v( 'fileref' ) : '' ) );
            $p( 'Dear ' . $bureau['name'] . ',' );
            $p( 'Some of the personal information on my credit report is incorrect. Please correct the details below.' );
            break;
        default: // general
            $b( 'Re: Request to investigate information on my credit report' . ( $v( 'name' ) ? ', ' . $v( 'name' ) : '' ) );
            $p( 'Dear ' . $bureau['name'] . ',' );
            $p( 'I am writing to ask you to investigate information on my credit report that I believe is not accurate. ' . ( $many ? 'Each item is listed below.' : 'The details are below.' ) );
            break;
    }

    foreach ( $items as $n => $it ) {
        $it   = array_map( function( $x ) { return trim( (string) $x ); }, (array) $it );
        $rows = [];
        switch ( $type['mode'] ) {
            case 'inquiries':
                if ( nab_dispute_valid_date( $it['date'] ?? '' ) ) $rows[] = [ 'Date of the inquiry', nab_dispute_fmt_date( $it['date'] ) ];
                $rows[] = [ 'Reason', ( $it['reason'] ?? '' ) !== '' ? $it['reason'] : $type['fix'] ];
                $out[]  = [ 't' => 'item', 'n' => $n + 1, 'title' => $it['company'] ?? '', 'rows' => $rows ];
                break;
            case 'personal':
                $out[] = [ 't' => 'item', 'n' => $n + 1, 'title' => 'Incorrect information', 'rows' => [
                    [ 'Shown on my report', $it['info'] ?? '' ],
                    [ 'Correct information', $it['fix'] ?? '' ],
                ] ];
                break;
            default:
                $out[] = [ 't' => 'item', 'n' => $n + 1, 'title' => nab_dispute_item_title( $it ), 'rows' => [
                    [ 'What my report shows', $it['info'] ?? '' ],
                    [ 'Why it is wrong', $it['reason'] ?? '' ],
                    [ 'What I am asking for', ( $it['fix'] ?? '' ) !== '' ? $it['fix'] : $type['fix'] ],
                ] ];
        }
    }

    switch ( $l['type'] ) {
        case 'fraud':
            $p( 'Please remove ' . ( $many ? 'these accounts' : 'this account' ) . ' from my credit file and add a fraud alert to my file. I would also appreciate a list of any organizations that received information about ' . ( $many ? 'these accounts' : 'this account' ) . ', so I can contact them.' );
            break;
        case 'inquiry':
            $p( 'Please check whether each company had my permission to access my credit file. If this cannot be confirmed, please remove the ' . ( $many ? 'inquiries' : 'inquiry' ) . '.' );
            break;
        case 'personal':
            $p( 'I have included documents that confirm my correct information.' );
            break;
        default:
            $p( 'I have included copies of documents that support my request. Please investigate ' . ( $many ? 'each item' : 'this item' ) . ' and correct or remove any information that is inaccurate or cannot be verified.' );
    }
    $p( 'Please send me the results of your investigation in writing, along with an updated copy of my credit report.' );
    $p( 'Thank you for your time and help.' );
    $p( 'Sincerely,' );
    $out[] = [ 't' => 'sign' ];
    $out[] = [ 't' => 'lines', 's' => [ $v( 'name' ) ] ];
    $b( 'Enclosures:' );
    foreach ( $type['enclose'] as $e ) {
        if ( $l['type'] === 'fraud' && $v( 'police' ) === '' && strpos( $e, 'Police' ) === 0 ) continue;
        $out[] = [ 't' => 'li', 's' => $e ];
    }
    return $out;
}

/** "Check before you download": things worth fixing or adding. */
function nab_dispute_letter_checks( $l ) {
    $types = nab_dispute_types();
    $type  = $types[ $l['type'] ] ?? null;
    $f     = (array) ( $l['f'] ?? [] );
    $out   = [];
    if ( ! $type ) return $out;
    if ( trim( (string) ( $f['street'] ?? '' ) ) === '' || trim( (string) ( $f['postal'] ?? '' ) ) === '' ) $out[] = 'Your address is incomplete. The bureau uses it to find your file.';
    foreach ( array_values( (array) ( $f['items'] ?? [] ) ) as $n => $it ) {
        $label = 'Item ' . ( $n + 1 );
        if ( $type['mode'] === 'accounts' ) {
            if ( trim( (string) ( $it['account'] ?? '' ) ) === '' ) $out[] = $label . ' has no account number. Adding the last 4 digits helps the bureau find it.';
            if ( trim( (string) ( $it['fix'] ?? '' ) ) === '' ) $out[] = $label . ': you didn\'t say what should be corrected, so the letter uses a standard request.';
        }
        if ( $type['mode'] === 'inquiries' && ! nab_dispute_valid_date( $it['date'] ?? '' ) ) $out[] = $label . ' has no inquiry date. Adding it helps the bureau find it.';
    }
    $evidence = array_filter( (array) ( $l['files'] ?? [] ), function( $x ) { return ( $x['kind'] ?? '' ) === 'evidence'; } );
    if ( ! $evidence ) $out[] = 'No supporting documents saved yet. Remember to attach your proof when you submit the letter.';
    return $out;
}

/* ═══════════════════════════════════════════════════════════
   PDF, a small, dependency-free writer for a text letter:
   US Letter, Helvetica 11 pt, word wrap and page breaks.
   ═══════════════════════════════════════════════════════════ */

/** Helvetica / Helvetica-Bold widths (1/1000 em) for Windows-1252 bytes 32–255. */
function nab_dispute_pdf_widths( $bold ) {
    static $w = [];
    if ( ! isset( $w[ $bold ] ) ) {
        $list = $bold
            ? '278,333,474,556,556,889,722,238,333,333,389,584,278,333,278,278,556,556,556,556,556,556,556,556,556,556,333,333,584,584,584,611,975,722,722,722,722,667,611,778,722,278,556,722,611,833,722,778,667,778,722,667,611,722,667,944,667,667,611,333,278,333,584,556,333,556,611,556,611,556,333,611,611,278,278,556,278,889,611,611,611,611,389,556,333,611,556,778,556,556,500,389,280,389,584,761,556,0,278,556,500,1000,556,556,333,1000,667,333,1000,0,611,0,0,278,278,500,500,350,556,1000,333,1000,556,333,944,0,500,667,278,333,556,556,556,556,280,556,333,737,370,556,584,333,737,333,400,584,333,333,333,611,556,278,333,333,365,556,834,834,834,611,722,722,722,722,722,722,1000,722,667,667,667,667,278,278,278,278,722,722,778,778,778,778,778,584,778,722,722,722,722,667,667,611,556,556,556,556,556,556,889,556,556,556,556,556,278,278,278,278,611,611,611,611,611,611,611,584,611,611,611,611,611,556,611,556'
            : '278,278,355,556,556,889,667,191,333,333,389,584,278,333,278,278,556,556,556,556,556,556,556,556,556,556,278,278,584,584,584,556,1015,667,667,722,722,667,611,778,722,278,500,667,556,833,722,778,667,778,722,667,611,722,667,944,667,667,611,278,278,278,469,556,333,556,556,500,556,556,278,556,556,222,222,500,222,833,556,556,556,556,333,500,278,556,500,722,500,500,500,334,260,334,584,761,556,0,222,556,333,1000,556,556,333,1000,667,333,1000,0,611,0,0,222,222,333,333,350,556,1000,333,1000,500,333,944,0,500,667,278,333,556,556,556,556,260,556,333,737,370,556,584,333,737,333,400,584,333,333,333,556,537,278,333,333,365,556,834,834,834,611,667,667,667,667,667,667,1000,722,667,667,667,667,278,278,278,278,722,722,778,778,778,778,778,584,778,722,722,722,722,667,667,611,556,556,556,556,556,556,889,500,556,556,556,556,278,278,278,278,556,556,556,556,556,556,556,584,611,556,556,556,556,500,556,500';
        $w[ $bold ] = array_map( 'intval', explode( ',', $list ) );
    }
    return $w[ $bold ];
}

/** UTF-8 → Windows-1252 (the encoding of the built-in PDF fonts). */
function nab_dispute_pdf_enc( $s ) {
    $s = str_replace( [ "\r", "\n", "\t" ], ' ', (string) $s );
    if ( function_exists( 'iconv' ) ) {
        $out = @iconv( 'UTF-8', 'Windows-1252//TRANSLIT//IGNORE', $s );
        if ( $out !== false ) return $out;
    }
    if ( function_exists( 'mb_convert_encoding' ) ) return mb_convert_encoding( $s, 'Windows-1252', 'UTF-8' );
    return preg_replace( '/[^\x20-\x7E]/', '?', $s );
}

function nab_dispute_pdf_width( $s, $bold, $size ) {
    $w = nab_dispute_pdf_widths( $bold );
    $t = 0;
    for ( $i = 0, $n = strlen( $s ); $i < $n; $i++ ) {
        $c  = ord( $s[ $i ] );
        $t += $c >= 32 ? $w[ $c - 32 ] : 0;
    }
    return $t * $size / 1000;
}

/** Splits an encoded string into lines no wider than $max points. */
function nab_dispute_pdf_wrap( $s, $bold, $size, $max ) {
    $lines = [];
    $line  = '';
    foreach ( explode( ' ', $s ) as $word ) {
        if ( $word === '' ) continue;
        $try = $line === '' ? $word : $line . ' ' . $word;
        if ( $line !== '' && nab_dispute_pdf_width( $try, $bold, $size ) > $max ) {
            $lines[] = $line;
            $line    = $word;
        } else {
            $line = $try;
        }
        // a single word longer than the line (a long account number): hard-break it
        while ( nab_dispute_pdf_width( $line, $bold, $size ) > $max && strlen( $line ) > 1 ) {
            $cut = strlen( $line ) - 1;
            while ( $cut > 1 && nab_dispute_pdf_width( substr( $line, 0, $cut ), $bold, $size ) > $max ) $cut--;
            $lines[] = substr( $line, 0, $cut );
            $line    = substr( $line, $cut );
        }
    }
    if ( $line !== '' ) $lines[] = $line;
    return $lines ?: [ '' ];
}

function nab_dispute_pdf_str( $s ) {
    return '(' . str_replace( [ '\\', '(', ')' ], [ '\\\\', '\\(', '\\)' ], $s ) . ')';
}

/** Builds the PDF file for a list of letter blocks. Returns the file as a string. */
function nab_dispute_pdf( $blocks, $title ) {
    $W = 612; $H = 792; $left = 72; $right = 72; $top = 72; $bottom = 72;
    $size = 11; $lead = 15; $gap = 9; $indent = 16;
    $max  = $W - $left - $right;

    $pages = [];
    $ops   = '';
    $y     = $H - $top;
    $newpage = function() use ( &$ops, &$y, &$pages, $H, $top ) { $pages[] = $ops; $ops = ''; $y = $H - $top; };
    $emit    = function( $text, $bold, $x ) use ( &$ops, &$y, $newpage, $size, $lead, $bottom ) {
        if ( $y < $bottom ) $newpage();
        $ops .= 'BT /' . ( $bold ? 'F2' : 'F1' ) . ' ' . $size . ' Tf ' . $x . ' ' . round( $y, 2 ) . ' Td ' . nab_dispute_pdf_str( $text ) . " Tj ET\n";
        $y   -= $lead;
    };

    foreach ( $blocks as $i => $bl ) {
        switch ( $bl['t'] ) {
            case 'lines':
                foreach ( $bl['s'] as $s ) {
                    foreach ( nab_dispute_pdf_wrap( nab_dispute_pdf_enc( $s ), false, $size, $max ) as $ln ) $emit( $ln, false, $left );
                }
                break;
            case 'p':
            case 'b':
                $bold = $bl['t'] === 'b';
                foreach ( nab_dispute_pdf_wrap( nab_dispute_pdf_enc( $bl['s'] ), $bold, $size, $max ) as $ln ) $emit( $ln, $bold, $left );
                break;
            case 'li':
                foreach ( nab_dispute_pdf_wrap( nab_dispute_pdf_enc( $bl['s'] ), false, $size, $max - $indent ) as $k => $ln ) {
                    if ( $y < $bottom ) $newpage();
                    // the bullet sits on the same baseline as the item's first line
                    if ( $k === 0 ) $ops .= 'BT /F1 ' . $size . ' Tf ' . ( $left + 4 ) . ' ' . round( $y, 2 ) . ' Td ' . nab_dispute_pdf_str( "\x95" ) . " Tj ET\n";
                    $emit( $ln, false, $left + $indent );
                }
                break;
            case 'item': // one disputed account: bold title, then indented "Label: value" lines
                foreach ( nab_dispute_pdf_wrap( nab_dispute_pdf_enc( $bl['n'] . '. ' . $bl['title'] ), true, $size, $max ) as $ln ) $emit( $ln, true, $left );
                foreach ( $bl['rows'] as $row ) {
                    foreach ( nab_dispute_pdf_wrap( nab_dispute_pdf_enc( $row[0] . ': ' . $row[1] ), false, $size, $max - $indent ) as $ln ) $emit( $ln, false, $left + $indent );
                }
                break;
            case 'sign': // room for a handwritten signature, then a line to sign on
                $y -= $lead * 2;
                $emit( '______________________________', false, $left );
                $y += 4;
                break;
        }
        // bullets in a list sit closer together than paragraphs
        $y -= ( $bl['t'] === 'li' && ( $blocks[ $i + 1 ]['t'] ?? '' ) === 'li' ) ? 4 : $gap;
    }
    $pages[] = $ops;

    // Objects: 1 catalog, 2 page tree, 3–4 fonts, 5 info, then a page + its content per page
    $objs   = [];
    $kids   = [];
    $n      = 6;
    foreach ( $pages as $content ) {
        $kids[]       = $n . ' 0 R';
        $objs[ $n ]   = '<< /Type /Page /Parent 2 0 R /MediaBox [0 0 ' . $W . ' ' . $H . '] /Resources << /Font << /F1 3 0 R /F2 4 0 R >> >> /Contents ' . ( $n + 1 ) . ' 0 R >>';
        $objs[ $n + 1 ] = '<< /Length ' . strlen( $content ) . " >>\nstream\n" . $content . "endstream";
        $n += 2;
    }
    $objs[1] = '<< /Type /Catalog /Pages 2 0 R >>';
    $objs[2] = '<< /Type /Pages /Kids [' . implode( ' ', $kids ) . '] /Count ' . count( $kids ) . ' >>';
    $objs[3] = '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>';
    $objs[4] = '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica-Bold /Encoding /WinAnsiEncoding >>';
    $objs[5] = '<< /Title ' . nab_dispute_pdf_str( nab_dispute_pdf_enc( $title ) ) . ' /Producer (NAB Member Portal) >>';
    ksort( $objs );

    $pdf     = "%PDF-1.4\n%\xE2\xE3\xCF\xD3\n";
    $offsets = [];
    foreach ( $objs as $i => $body ) {
        $offsets[ $i ] = strlen( $pdf );
        $pdf .= $i . " 0 obj\n" . $body . "\nendobj\n";
    }
    $xref = strlen( $pdf );
    $pdf .= 'xref' . "\n0 " . ( count( $objs ) + 1 ) . "\n0000000000 65535 f \n";
    foreach ( $objs as $i => $body ) $pdf .= sprintf( "%010d 00000 n \n", $offsets[ $i ] );
    $pdf .= 'trailer' . "\n<< /Size " . ( count( $objs ) + 1 ) . " /Root 1 0 R /Info 5 0 R >>\nstartxref\n" . $xref . "\n%%EOF\n";
    return $pdf;
}

/* ═══════════════════════════════════════════════════════════
   PRIVATE FILES — supporting documents and bureau responses the
   member saves for their own records. Stored outside the normal
   media library in a folder the web server refuses to serve; only
   the owner can download them, through nab_dispute_file.
   ═══════════════════════════════════════════════════════════ */

function nab_dispute_file_types() {
    return [ 'pdf' => 'application/pdf', 'jpg' => 'image/jpeg', 'jpeg' => 'image/jpeg', 'png' => 'image/png', 'webp' => 'image/webp', 'heic' => 'image/heic' ];
}

/** The member's private folder, created (and closed to the web) on first use. */
function nab_dispute_file_dir( $uid ) {
    $up   = wp_upload_dir( null, false );
    $base = trailingslashit( $up['basedir'] ) . 'nab-dispute-files';
    $dir  = $base . '/' . (int) $uid;
    if ( ! is_dir( $dir ) ) wp_mkdir_p( $dir );
    if ( ! file_exists( $base . '/.htaccess' ) ) {
        @file_put_contents( $base . '/.htaccess', "# NAB Member Portal: private dispute files\n<IfModule mod_authz_core.c>\n  Require all denied\n</IfModule>\n<IfModule !mod_authz_core.c>\n  Deny from all\n</IfModule>\n" );
        @file_put_contents( $base . '/index.php', "<?php\n// Silence is golden.\n" );
        @file_put_contents( $base . '/web.config', "<?xml version=\"1.0\"?>\n<configuration><system.webServer><authorization><deny users=\"*\" /></authorization></system.webServer></configuration>\n" );
    }
    if ( ! file_exists( $dir . '/index.php' ) ) @file_put_contents( $dir . '/index.php', "<?php\n// Silence is golden.\n" );
    return $dir;
}

function nab_dispute_file_path( $uid, $file ) {
    $ext = strtolower( pathinfo( (string) ( $file['name'] ?? '' ), PATHINFO_EXTENSION ) );
    return nab_dispute_file_dir( $uid ) . '/' . preg_replace( '/[^a-z0-9]/', '', (string) $file['id'] ) . '.' . preg_replace( '/[^a-z0-9]/', '', $ext );
}

function nab_dispute_delete_files( $uid, $files ) {
    foreach ( (array) $files as $file ) {
        $path = nab_dispute_file_path( $uid, $file );
        if ( is_file( $path ) ) @unlink( $path );
    }
}

/* ═══════════════════════════════════════════════════════════
   REMINDER — once a day, when the member opens any portal page:
   a bell notification for each letter marked as sent 30+ days ago
   with no response yet. Each letter reminds once.
   ═══════════════════════════════════════════════════════════ */
function nab_dispute_send_reminders( $uid ) {
    $today = current_time( 'Y-m-d' );
    if ( get_user_meta( $uid, 'nab_dispute_notified', true ) === $today ) return;
    if ( ! function_exists( 'nab_add_auto_notification' ) ) return;
    update_user_meta( $uid, 'nab_dispute_notified', $today );
    $letters = nab_dispute_get_letters( $uid );
    $due     = wp_date( 'Y-m-d', strtotime( $today . ' -30 days' ) );
    $changed = false;
    $bureaus = nab_dispute_bureaus();
    $types   = nab_dispute_types();
    foreach ( $letters as $i => $l ) {
        if ( $l['status'] !== 'sent' || $l['reminded'] || ( $l['sent'] ?: '9999' ) > $due ) continue;
        $bureau = $bureaus[ $l['bureau'] ]['name'] ?? 'the bureau';
        $what   = $types[ $l['type'] ]['label'] ?? 'dispute';
        nab_add_auto_notification( $uid, 'dispute', 'Any reply from ' . $bureau . ' about your ' . strtolower( $what ) . ' letter?',
            'You sent it on ' . nab_dispute_fmt_date( $l['sent'] ) . '. Bureaus usually reply within about 30 days. When you hear back, update the letter in the Dispute Center, or contact ' . $bureau . ' to check on it.', 30 * DAY_IN_SECONDS );
        $letters[ $i ]['reminded'] = true;
        $changed = true;
    }
    if ( $changed ) nab_dispute_put_letters( $uid, $letters );
}
add_action( 'template_redirect', function() {
    if ( ! is_user_logged_in() || ! function_exists( 'nab_portal_current_tool' ) || ! nab_portal_current_tool() ) return;
    nab_dispute_send_reminders( get_current_user_id() );
}, 6 );

/* ═══════════════════════════════════════════════════════════
   AJAX
   ═══════════════════════════════════════════════════════════ */
function nab_dispute_ajax_uid() {
    check_ajax_referer( 'nab_portal_nonce', 'nonce' );
    $uid = get_current_user_id();
    if ( ! $uid ) wp_send_json_error( [ 'message' => 'Please log in again.' ], 401 );
    return $uid;
}

/** One text value from the request (arrays and other odd input count as empty). */
function nab_dispute_clean( $v, $len ) {
    return is_scalar( $v ) ? mb_substr( sanitize_text_field( wp_unslash( (string) $v ) ), 0, $len ) : '';
}
function nab_dispute_clean_long( $v, $len ) {
    return is_scalar( $v ) ? mb_substr( sanitize_textarea_field( wp_unslash( (string) $v ) ), 0, $len ) : '';
}

/** Finds the member's letter by id: [ index, letter ] or sends an error. */
function nab_dispute_find( $letters, $id ) {
    foreach ( $letters as $i => $l ) if ( $id !== '' && $l['id'] === $id ) return [ $i, $l ];
    wp_send_json_error( [ 'message' => 'That letter could not be found.' ] );
}

function nab_dispute_done( $uid, $message, $extra = [] ) {
    wp_send_json_success( array_merge( [ 'message' => $message, 'data' => nab_dispute_page_data( $uid ) ], $extra ) );
}

add_action( 'wp_ajax_nab_dispute_save', function() {
    $uid    = nab_dispute_ajax_uid();
    $types  = nab_dispute_types();
    $id     = sanitize_key( $_POST['id'] ?? '' );
    $type   = sanitize_key( $_POST['type'] ?? '' );
    $bureau = sanitize_key( $_POST['bureau'] ?? '' );
    if ( ! isset( $types[ $type ] ) ) wp_send_json_error( [ 'message' => 'Please choose what you are disputing.' ] );
    if ( ! isset( nab_dispute_bureaus()[ $bureau ] ) ) wp_send_json_error( [ 'message' => 'Please choose one bureau: Equifax or TransUnion.' ] );

    $in = isset( $_POST['f'] ) && is_array( $_POST['f'] ) ? $_POST['f'] : [];
    $f  = [
        'name'     => nab_dispute_clean( $in['name'] ?? '', 80 ),
        'street'   => nab_dispute_clean( $in['street'] ?? '', 120 ),
        'city'     => nab_dispute_clean( $in['city'] ?? '', 60 ),
        'province' => strtoupper( sanitize_key( is_scalar( $in['province'] ?? '' ) ? $in['province'] : '' ) ),
        'postal'   => strtoupper( nab_dispute_clean( $in['postal'] ?? '', 10 ) ),
    ];
    if ( $f['name'] === '' ) wp_send_json_error( [ 'message' => 'Please enter your full name.' ] );
    if ( $f['street'] === '' || $f['city'] === '' || $f['postal'] === '' ) wp_send_json_error( [ 'message' => 'Please enter your full address.' ] );
    if ( ! isset( nab_dispute_provinces()[ $f['province'] ] ) ) wp_send_json_error( [ 'message' => 'Please choose your province or territory.' ] );

    // the accounts or items being disputed
    $fields = nab_dispute_item_fields( $types[ $type ]['mode'] );
    $items  = [];
    foreach ( array_slice( (array) ( $in['items'] ?? [] ), 0, 10 ) as $r ) {
        if ( ! is_array( $r ) ) continue;
        $row = [];
        foreach ( $fields as $fd ) {
            $raw = $r[ $fd['key'] ] ?? '';
            if ( ( $fd['type'] ?? '' ) === 'date' ) {
                $d = nab_dispute_clean( $raw, 10 );
                $row[ $fd['key'] ] = nab_dispute_valid_date( $d ) ? $d : '';
            } else {
                $row[ $fd['key'] ] = ! empty( $fd['long'] ) ? nab_dispute_clean_long( $raw, 500 ) : nab_dispute_clean( $raw, 120 );
            }
        }
        if ( implode( '', $row ) === '' ) continue; // an empty card the member didn't fill in
        foreach ( $fields as $fd ) {
            if ( ! empty( $fd['req'] ) && $row[ $fd['key'] ] === '' ) {
                wp_send_json_error( [ 'message' => 'Item ' . ( count( $items ) + 1 ) . ': please fill in "' . $fd['label'] . '".' ] );
            }
        }
        $items[] = $row;
    }
    if ( ! $items ) wp_send_json_error( [ 'message' => 'Please add at least one account or item to dispute.' ] );
    $f['items'] = $items;

    foreach ( (array) ( $types[ $type ]['extras'] ?? [] ) as $fd ) {
        $raw = $in[ $fd['key'] ] ?? '';
        if ( ( $fd['type'] ?? '' ) === 'date' ) {
            $d = nab_dispute_clean( $raw, 10 );
            $f[ $fd['key'] ] = nab_dispute_valid_date( $d ) ? $d : '';
        } else {
            $f[ $fd['key'] ] = nab_dispute_clean( $raw, 120 );
        }
    }

    $letters = nab_dispute_get_letters( $uid );
    $found   = false;
    foreach ( $letters as $i => $l ) {
        if ( $id && $l['id'] === $id ) {
            // editing keeps the letter's history; a changed letter needs downloading again
            $letters[ $i ]['type']   = $type;
            $letters[ $i ]['bureau'] = $bureau;
            $letters[ $i ]['f']      = $f;
            if ( $l['status'] === 'ready' ) { $letters[ $i ]['status'] = 'draft'; $letters[ $i ]['ready'] = ''; }
            $found = true;
        }
    }
    if ( ! $found ) {
        if ( count( $letters ) >= 30 ) wp_send_json_error( [ 'message' => 'You can keep up to 30 letters. Delete an old one to make room.' ] );
        $id        = strtolower( wp_generate_password( 10, false ) );
        $letters[] = nab_dispute_normalize( [ 'id' => $id, 'type' => $type, 'bureau' => $bureau, 'f' => $f, 'status' => 'draft', 'created' => current_time( 'Y-m-d' ) ] );
    }
    nab_dispute_put_letters( $uid, $letters );
    update_user_meta( $uid, 'nab_dispute_profile', wp_json_encode( array_intersect_key( $f, array_flip( [ 'name', 'street', 'city', 'province', 'postal' ] ) ) ) );

    nab_dispute_done( $uid, $found ? 'Letter updated.' : 'Your letter is ready to review.', [ 'id' => $id ] );
} );

/** Moves a letter to a status; dates of later steps are cleared when going back. */
function nab_dispute_set_status( $l, $status ) {
    $order = [ 'draft' => 0, 'ready' => 1, 'sent' => 2, 'response' => 3, 'resolved' => 4, 'unresolved' => 4 ];
    $dates = [ 'ready' => 1, 'sent' => 2, 'response' => 3, 'closed' => 4 ];
    $today = current_time( 'Y-m-d' );
    $to    = $order[ $status ];
    foreach ( $dates as $key => $step ) {
        if ( $step > $to ) $l[ $key ] = '';
        elseif ( $l[ $key ] === '' ) $l[ $key ] = $today; // a skipped step counts as today
    }
    if ( $to < 2 ) $l['reminded'] = false;
    $l['status'] = $status;
    return $l;
}

add_action( 'wp_ajax_nab_dispute_status', function() {
    $uid    = nab_dispute_ajax_uid();
    $status = sanitize_key( $_POST['status'] ?? '' );
    if ( ! isset( nab_dispute_statuses()[ $status ] ) ) wp_send_json_error( [ 'message' => 'Unknown status.' ] );
    $letters = nab_dispute_get_letters( $uid );
    list( $i, $l ) = nab_dispute_find( $letters, sanitize_key( $_POST['id'] ?? '' ) );
    $letters[ $i ] = nab_dispute_set_status( $l, $status );
    nab_dispute_put_letters( $uid, $letters );
    $msg = [ 'draft' => 'Moved back to draft.', 'ready' => 'Marked as ready to send.', 'sent' => 'Marked as sent. We\'ll remind you if you haven\'t heard back in 30 days.', 'response' => 'Response saved. Let us know if the error was fixed.', 'resolved' => 'Great news. Marked as resolved.', 'unresolved' => 'Marked as not resolved. You can create a follow-up letter with more proof.' ];
    nab_dispute_done( $uid, $msg[ $status ] );
} );

add_action( 'wp_ajax_nab_dispute_notes', function() {
    $uid     = nab_dispute_ajax_uid();
    $letters = nab_dispute_get_letters( $uid );
    list( $i ) = nab_dispute_find( $letters, sanitize_key( $_POST['id'] ?? '' ) );
    $letters[ $i ]['notes'] = nab_dispute_clean_long( $_POST['notes'] ?? '', 2000 );
    nab_dispute_put_letters( $uid, $letters );
    nab_dispute_done( $uid, 'Notes saved.' );
} );

add_action( 'wp_ajax_nab_dispute_delete', function() {
    $uid     = nab_dispute_ajax_uid();
    $letters = nab_dispute_get_letters( $uid );
    list( $i, $l ) = nab_dispute_find( $letters, sanitize_key( $_POST['id'] ?? '' ) );
    nab_dispute_delete_files( $uid, $l['files'] );
    array_splice( $letters, $i, 1 );
    nab_dispute_put_letters( $uid, $letters );
    nab_dispute_done( $uid, 'Letter deleted.' );
} );

/** One file per request: a supporting document or the bureau's response. */
add_action( 'wp_ajax_nab_dispute_upload', function() {
    $uid     = nab_dispute_ajax_uid();
    $letters = nab_dispute_get_letters( $uid );
    list( $i, $l ) = nab_dispute_find( $letters, sanitize_key( $_POST['id'] ?? '' ) );
    $kind = ( $_POST['kind'] ?? '' ) === 'response' ? 'response' : 'evidence';
    if ( count( $l['files'] ) >= 10 ) wp_send_json_error( [ 'message' => 'You can save up to 10 files per letter.' ] );

    $up = $_FILES['file'] ?? null;
    if ( ! $up || ! is_array( $up ) || is_array( $up['name'] ?? null ) || ( $up['error'] ?? 1 ) !== UPLOAD_ERR_OK || ! is_uploaded_file( $up['tmp_name'] ) ) {
        wp_send_json_error( [ 'message' => 'The file could not be uploaded. Please try again.' ] );
    }
    if ( $up['size'] > 8 * MB_IN_BYTES ) wp_send_json_error( [ 'message' => 'Files can be up to 8 MB.' ] );

    $name  = sanitize_file_name( wp_unslash( (string) $up['name'] ) );
    $ext   = strtolower( pathinfo( $name, PATHINFO_EXTENSION ) );
    $types = nab_dispute_file_types();
    if ( ! isset( $types[ $ext ] ) ) wp_send_json_error( [ 'message' => 'Please upload a PDF or a photo (JPG, PNG, WEBP or HEIC).' ] );
    $mime = $types[ $ext ];
    if ( function_exists( 'finfo_open' ) ) {
        $fi   = finfo_open( FILEINFO_MIME_TYPE );
        $real = $fi ? (string) finfo_file( $fi, $up['tmp_name'] ) : '';
        if ( $fi ) finfo_close( $fi );
        $ok = $real === $mime || ( $ext === 'heic' && in_array( $real, [ 'image/heif', 'image/heic', 'application/octet-stream' ], true ) );
        if ( $real !== '' && ! $ok ) wp_send_json_error( [ 'message' => 'That file doesn\'t look like a PDF or a photo.' ] );
    }

    $file = [ 'id' => strtolower( wp_generate_password( 16, false ) ), 'name' => mb_substr( $name, 0, 120 ), 'kind' => $kind, 'size' => (int) $up['size'], 'mime' => $mime, 'added' => current_time( 'Y-m-d' ) ];
    if ( ! @move_uploaded_file( $up['tmp_name'], nab_dispute_file_path( $uid, $file ) ) ) {
        wp_send_json_error( [ 'message' => 'The file could not be saved. Please try again.' ] );
    }
    $letters[ $i ]['files'][] = $file;
    nab_dispute_put_letters( $uid, $letters );
    nab_dispute_done( $uid, 'File saved.' );
} );

add_action( 'wp_ajax_nab_dispute_file_delete', function() {
    $uid     = nab_dispute_ajax_uid();
    $letters = nab_dispute_get_letters( $uid );
    list( $i, $l ) = nab_dispute_find( $letters, sanitize_key( $_POST['id'] ?? '' ) );
    $fid  = sanitize_key( $_POST['file'] ?? '' );
    $keep = [];
    foreach ( $l['files'] as $file ) {
        if ( $file['id'] === $fid ) nab_dispute_delete_files( $uid, [ $file ] ); else $keep[] = $file;
    }
    $letters[ $i ]['files'] = $keep;
    nab_dispute_put_letters( $uid, $letters );
    nab_dispute_done( $uid, 'File removed.' );
} );

/** GET: one of the member's own saved files. */
add_action( 'wp_ajax_nab_dispute_file', function() {
    if ( ! check_ajax_referer( 'nab_portal_nonce', 'nonce', false ) ) wp_die( 'Your session has expired. Please refresh the Dispute Center and try again.', 'Session expired', [ 'response' => 403 ] );
    $uid  = get_current_user_id();
    $lid  = sanitize_key( $_GET['id'] ?? '' );
    $fid  = sanitize_key( $_GET['file'] ?? '' );
    $file = null;
    foreach ( nab_dispute_get_letters( $uid ) as $l ) {
        if ( $l['id'] !== $lid ) continue;
        foreach ( $l['files'] as $x ) if ( $x['id'] === $fid ) $file = $x;
    }
    $path = $file ? nab_dispute_file_path( $uid, $file ) : '';
    if ( ! $file || ! is_file( $path ) ) wp_die( 'That file could not be found.', 'Not found', [ 'response' => 404 ] );
    while ( ob_get_level() ) ob_end_clean();
    nocache_headers();
    header( 'Content-Type: ' . $file['mime'] );
    header( 'Content-Disposition: attachment; filename="' . str_replace( '"', '', $file['name'] ) . '"' );
    header( 'Content-Length: ' . filesize( $path ) );
    header( 'X-Content-Type-Options: nosniff' );
    readfile( $path );
    exit;
} );

/** GET: the letter as a PDF download. Downloading a draft marks it ready to send. */
add_action( 'wp_ajax_nab_dispute_pdf', function() {
    if ( ! check_ajax_referer( 'nab_portal_nonce', 'nonce', false ) ) wp_die( 'Your session has expired. Please refresh the Dispute Center and try again.', 'Session expired', [ 'response' => 403 ] );
    $uid     = get_current_user_id();
    $id      = sanitize_key( $_GET['id'] ?? '' );
    $letters = nab_dispute_get_letters( $uid );
    $letter  = null;
    foreach ( $letters as $i => $l ) {
        if ( $l['id'] !== $id ) continue;
        if ( $l['status'] === 'draft' ) {
            $letters[ $i ] = nab_dispute_set_status( $l, 'ready' );
            nab_dispute_put_letters( $uid, $letters );
        }
        $letter = $l;
    }
    if ( ! $letter ) wp_die( 'That letter could not be found.', 'Not found', [ 'response' => 404 ] );

    $bureau = nab_dispute_bureaus()[ $letter['bureau'] ]['name'] ?? 'Bureau';
    $type   = nab_dispute_types()[ $letter['type'] ]['label'] ?? 'Dispute';
    $pdf    = nab_dispute_pdf( nab_dispute_letter_blocks( $letter ), $type . ' – ' . $bureau );
    $file   = 'Dispute-Letter-' . str_replace( ' ', '-', $bureau ) . '-' . $letter['created'] . '.pdf';

    while ( ob_get_level() ) ob_end_clean(); // stray output from other code would corrupt the file
    nocache_headers();
    header( 'Content-Type: application/pdf' );
    header( 'Content-Disposition: ' . ( ! empty( $_GET['view'] ) ? 'inline' : 'attachment' ) . '; filename="' . $file . '"' );
    header( 'Content-Length: ' . strlen( $pdf ) );
    header( 'X-Content-Type-Options: nosniff' );
    echo $pdf; // binary PDF built from escaped, encoded text
    exit;
} );
