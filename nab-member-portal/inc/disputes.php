<?php
/**
 * NAB Member Portal — Dispute Letter Builder (v1.14.0)
 *
 * The member picks what is wrong on their credit report and which bureau
 * (Equifax or TransUnion), fills in a short form, and the portal writes the
 * dispute letter for them from NAB's six letter templates. They preview it,
 * download it as a PDF and submit it on the bureau's own website. NAB never
 * sends anything to a bureau, and nothing goes to an admin to review.
 *
 * The letter wording lives in one place, nab_dispute_letter_blocks(): the
 * page preview and the PDF are both drawn from it, so they always match.
 * The six types follow the original templates in assets/dispute-templates/.
 *
 * User meta:
 *   nab_dispute_letters  — JSON [ { id, type, bureau, f: {fields}, created,
 *                          status, sent, response, reminded } ]
 *                          status: created | sent | response
 *   nab_dispute_profile  — JSON { name, street, city, province, postal, dob }
 *                          the member's details from their last letter, used
 *                          to fill in the next one
 *   nab_dispute_notified — Y-m-d of the last reminder check (once a day)
 *
 * AJAX (nab_portal_nonce, logged in):
 *   nab_dispute_save / nab_dispute_status / nab_dispute_delete
 *   nab_dispute_pdf (GET) — streams the letter as a PDF download
 *
 * @package NAB_Member_Portal
 * @since   1.14.0
 */
if ( ! defined( 'ABSPATH' ) ) exit;

/* ═══════════════════════════════════════════════════════════
   SETTINGS
   ═══════════════════════════════════════════════════════════ */

/** The two bureaus NAB works with. Links can be changed in ACF (Dispute Center page). */
function nab_dispute_bureaus() {
    static $page = null;
    if ( $page === null ) {
        $found = get_pages( [ 'meta_key' => '_wp_page_template', 'meta_value' => 'nab-dispute-center', 'number' => 1 ] );
        $page  = $found ? (int) $found[0]->ID : 0;
    }
    $acf = function( $name ) use ( $page ) {
        return $page && function_exists( 'get_field' ) ? esc_url_raw( trim( (string) get_field( $name, $page ) ) ) : '';
    };
    return apply_filters( 'nab_dispute_bureaus', [
        'equifax' => [
            'name'  => 'Equifax Canada',
            'legal' => 'Equifax Canada Co.',
            'dept'  => 'National Consumer Relations',
            'url'   => $acf( 'dc_equifax_url' ) ?: 'https://www.consumer.equifax.ca/personal/dispute-credit-report',
        ],
        'transunion' => [
            'name'  => 'TransUnion Canada',
            'legal' => 'TransUnion Canada',
            'dept'  => 'Consumer Relations Department',
            'url'   => $acf( 'dc_transunion_url' ) ?: 'https://www.transunion.ca/assistance/credit-report-disputes',
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

/**
 * The six letter types. Fields: text | date | textarea | rows (a small table).
 * `optional` fields may be left empty. `enclose` is the "attach these" list,
 * printed under the letter and shown on the page as a checklist.
 */
function nab_dispute_types() {
    $id_address = [ 'Copy of government-issued photo ID', 'Proof of current address' ];
    return apply_filters( 'nab_dispute_types', [
        'round1' => [
            'label' => 'General dispute (Round 1)',
            'desc'  => 'Start here. Items that are inaccurate, incomplete, outdated or duplicated.',
            'icon'  => 'file-text', 'tone' => 'blue',
            'fields' => [
                [ 'key' => 'items', 'type' => 'rows', 'label' => 'Items you are disputing', 'max' => 5, 'cols' => [
                    [ 'key' => 'creditor', 'label' => 'Creditor', 'ph' => 'e.g. ABC Bank' ],
                    [ 'key' => 'account',  'label' => 'Account number', 'ph' => 'e.g. ending 1234' ],
                    [ 'key' => 'reason',   'label' => 'What is wrong', 'ph' => 'e.g. Shows a balance I paid off in 2024', 'long' => true ],
                ] ],
                [ 'key' => 'dob', 'type' => 'date', 'label' => 'Your date of birth', 'optional' => true, 'hint' => 'Printed in the "correct personal information" part of the letter.' ],
            ],
            'enclose' => array_merge( $id_address, [ 'Copy of the credit report page showing the disputed items' ] ),
        ],
        'late' => [
            'label' => 'Late payment error',
            'desc'  => 'A payment shows as late or missed, but you paid on time.',
            'icon'  => 'clock', 'tone' => 'orange',
            'fields' => [
                [ 'key' => 'creditor',  'type' => 'text', 'label' => 'Creditor', 'ph' => 'e.g. ABC Bank' ],
                [ 'key' => 'account',   'type' => 'text', 'label' => 'Account number', 'ph' => 'e.g. ending 1234' ],
                [ 'key' => 'late_date', 'type' => 'date', 'label' => 'Payment date reported as late' ],
                [ 'key' => 'paid_date', 'type' => 'date', 'label' => 'Date you actually paid' ],
                [ 'key' => 'proof',     'type' => 'text', 'label' => 'Your proof of payment', 'ph' => 'e.g. bank statement, payment confirmation' ],
            ],
            'enclose' => [ 'Bank or payment statement showing the transaction date', 'Payment confirmation number or receipt', 'Copy of the credit report page showing the late payment' ],
        ],
        'notmine' => [
            'label' => 'Account not mine',
            'desc'  => "An account that belongs to someone else, often a mixed-up file.",
            'icon'  => 'user', 'tone' => 'purple',
            'fields' => [
                [ 'key' => 'creditor', 'type' => 'text', 'label' => 'Creditor', 'ph' => 'e.g. XYZ Finance' ],
                [ 'key' => 'account',  'type' => 'text', 'label' => 'Account number', 'ph' => 'e.g. ending 1234' ],
            ],
            'enclose' => array_merge( $id_address, [ 'Copy of the credit report page showing the account' ] ),
        ],
        'fraud' => [
            'label' => 'Fraudulent account',
            'desc'  => 'An account opened in your name without your consent (identity theft).',
            'icon'  => 'triangle-alert', 'tone' => 'red',
            'fields' => [
                [ 'key' => 'creditor', 'type' => 'text', 'label' => 'Creditor', 'ph' => 'e.g. XYZ Finance' ],
                [ 'key' => 'account',  'type' => 'text', 'label' => 'Account number', 'ph' => 'e.g. ending 1234' ],
                [ 'key' => 'opened',   'type' => 'date', 'label' => 'Date the account was opened', 'optional' => true ],
                [ 'key' => 'aware',    'type' => 'date', 'label' => 'Date you found out about it' ],
                [ 'key' => 'police',   'type' => 'text', 'label' => 'Police or Canadian Anti-Fraud Centre report number', 'optional' => true, 'hint' => 'Leave empty if you haven\'t reported it yet.' ],
            ],
            'enclose' => [ 'Copy of government-issued photo ID', 'Police report or Canadian Anti-Fraud Centre reference number (if you have one)', 'Copy of the credit report page showing the account' ],
        ],
        'inquiry' => [
            'label' => 'Hard inquiries',
            'desc'  => "Credit checks you don't recognize or didn't authorize.",
            'icon'  => 'search', 'tone' => 'teal',
            'fields' => [
                [ 'key' => 'items', 'type' => 'rows', 'label' => 'Inquiries you are disputing', 'max' => 5, 'cols' => [
                    [ 'key' => 'company', 'label' => 'Company', 'ph' => 'e.g. ABC Lending' ],
                    [ 'key' => 'date',    'label' => 'Date of the inquiry', 'type' => 'date' ],
                ] ],
            ],
            'enclose' => array_merge( $id_address, [ 'Copy of the credit report page showing the inquiries' ] ),
        ],
        'personal' => [
            'label' => 'Incorrect personal information',
            'desc'  => 'Your name, address, date of birth, SIN or employer is wrong.',
            'icon'  => 'pencil', 'tone' => 'indigo',
            'fields' => [
                [ 'key' => 'wrong',   'type' => 'textarea', 'label' => "What's wrong on your report", 'ph' => 'e.g. My last name is spelled "Smyth"' ],
                [ 'key' => 'correct', 'type' => 'textarea', 'label' => 'The correct information', 'ph' => 'e.g. My last name is "Smith"' ],
                [ 'key' => 'fileref', 'type' => 'text', 'label' => 'File or reference number on your report', 'optional' => true ],
            ],
            'enclose' => [ 'Copy of government-issued photo ID', 'Proof of current address (utility bill or bank statement)', 'Copy of the credit report page showing the error, with the error highlighted' ],
        ],
    ] );
}

/* ═══════════════════════════════════════════════════════════
   DATA
   ═══════════════════════════════════════════════════════════ */

function nab_dispute_get_letters( $uid ) {
    return array_values( nab_dash_json_meta( $uid, 'nab_dispute_letters' ) );
}

/** The member's details for the next letter: last letter's details, else their profile. */
function nab_dispute_get_profile( $uid ) {
    $p = nab_dash_json_meta( $uid, 'nab_dispute_profile' );
    if ( $p ) return $p;
    $u    = get_userdata( $uid );
    $name = $u ? trim( $u->first_name . ' ' . $u->last_name ) : '';
    $dob  = (string) get_user_meta( $uid, 'nab_dob', true );
    return [
        'name'     => $name ?: ( $u ? $u->display_name : '' ),
        'street'   => (string) get_user_meta( $uid, 'nab_address', true ),
        'city'     => '', 'province' => '', 'postal' => '',
        'dob'      => nab_dispute_valid_date( $dob ) ? $dob : '',
    ];
}

/** Everything the page needs. Each letter carries its finished text. */
function nab_dispute_page_data( $uid ) {
    $letters = nab_dispute_get_letters( $uid );
    foreach ( $letters as $i => $l ) $letters[ $i ]['blocks'] = nab_dispute_letter_blocks( $l );
    $types = nab_dispute_types();
    return [
        'today'     => current_time( 'Y-m-d' ),
        'types'     => $types,
        'bureaus'   => nab_dispute_bureaus(),
        'provinces' => nab_dispute_provinces(),
        'profile'   => nab_dispute_get_profile( $uid ),
        'letters'   => array_reverse( $letters ), // newest first
        'pdf'       => add_query_arg( [ 'action' => 'nab_dispute_pdf', 'nonce' => wp_create_nonce( 'nab_portal_nonce' ) ], admin_url( 'admin-ajax.php' ) ),
    ];
}

/* ═══════════════════════════════════════════════════════════
   THE LETTER
   Blocks: [ 't' => 'lines', 's' => [ … ] ]  lines with no space between
           [ 't' => 'p', 's' => '…' ]        paragraph
           [ 't' => 'b', 's' => '…' ]        bold paragraph (subject, headings)
           [ 't' => 'li', 's' => '…' ]       bullet
           [ 't' => 'sign' ]                 room for a signature
   ═══════════════════════════════════════════════════════════ */

/** Y-m-d check that also allows birth dates (nab_cal_valid_date() starts at 2000). */
function nab_dispute_valid_date( $d ) {
    if ( ! is_string( $d ) || ! preg_match( '/^\d{4}-\d{2}-\d{2}$/', $d ) ) return false;
    list( $y, $m, $day ) = array_map( 'intval', explode( '-', $d ) );
    return checkdate( $m, $day, $y ) && $y >= 1900 && $y <= 2100;
}

function nab_dispute_fmt_date( $d ) {
    return nab_dispute_valid_date( $d ) ? wp_date( 'F j, Y', strtotime( $d . ' 12:00' ) ) : '';
}

function nab_dispute_letter_blocks( $l ) {
    $types   = nab_dispute_types();
    $bureaus = nab_dispute_bureaus();
    $type    = $types[ $l['type'] ?? '' ] ?? null;
    $bureau  = $bureaus[ $l['bureau'] ?? '' ] ?? null;
    if ( ! $type || ! $bureau ) return [];
    $f    = (array) ( $l['f'] ?? [] );
    $v    = function( $k ) use ( $f ) { return trim( (string) ( $f[ $k ] ?? '' ) ); };
    $prov = nab_dispute_provinces()[ $v( 'province' ) ] ?? $v( 'province' );
    $acct = function() use ( $v ) { return $v( 'account' ) !== '' ? ' – Account #' . $v( 'account' ) : ''; };

    $out = [
        [ 't' => 'lines', 's' => array_values( array_filter( [ $v( 'name' ), $v( 'street' ), trim( $v( 'city' ) . ( $prov ? ', ' . $prov : '' ) . ' ' . $v( 'postal' ) ) ] ) ) ],
        [ 't' => 'lines', 's' => [ nab_dispute_fmt_date( $l['created'] ?? '' ) ] ],
        [ 't' => 'lines', 's' => [ $bureau['legal'], $bureau['dept'] ] ],
    ];
    $p  = function( $s ) use ( &$out ) { $out[] = [ 't' => 'p', 's' => $s ]; };
    $b  = function( $s ) use ( &$out ) { $out[] = [ 't' => 'b', 's' => $s ]; };
    $li = function( $s ) use ( &$out ) { $out[] = [ 't' => 'li', 's' => $s ]; };

    switch ( $l['type'] ) {
        case 'round1':
            $b( 'Re: Request for Investigation of Inaccurate Information on My Credit Report' );
            $p( 'Dear ' . $bureau['name'] . ',' );
            $p( 'I recently obtained a copy of my credit report and identified the items below that I believe are inaccurate or incomplete. I am requesting that you investigate these items and verify their accuracy.' );
            $b( 'Items being disputed:' );
            foreach ( (array) ( $f['items'] ?? [] ) as $r ) {
                $li( $r['creditor'] . ( $r['account'] !== '' ? ' (account ' . $r['account'] . ')' : '' ) . ( $r['reason'] !== '' ? ': ' . $r['reason'] : '' ) );
            }
            $p( 'In addition, please review my personal identifying information. The only personal information that should appear on my credit file is the information listed below and supported by the enclosed documentation.' );
            $out[] = [ 't' => 'lines', 's' => array_values( array_filter( [
                'Name: ' . $v( 'name' ),
                'Current address: ' . trim( $v( 'street' ) . ', ' . $v( 'city' ) . ( $prov ? ', ' . $prov : '' ) . ' ' . $v( 'postal' ) ),
                $v( 'dob' ) ? 'Date of birth: ' . nab_dispute_fmt_date( $v( 'dob' ) ) : '',
            ] ) ) ];
            $p( 'If any other names, addresses or identifying information are inaccurate, outdated or do not belong to me, I request that they be corrected or removed from my credit file.' );
            $p( 'Pursuant to the Personal Information Protection and Electronic Documents Act (PIPEDA) and applicable provincial consumer reporting legislation, I request that you conduct a reasonable investigation into each disputed item and verify its accuracy with the reporting organization.' );
            $p( 'If any disputed information cannot be substantiated or is found to be inaccurate or incomplete, I request that it be corrected or removed from my credit file. I also request written confirmation of the results of your investigation, including any corrections made to my credit report, and the name of the organization that verified each disputed item.' );
            $p( 'Thank you for your prompt attention to this matter. I look forward to receiving your written response upon completion of your investigation.' );
            break;

        case 'late':
            $b( 'RE: Dispute of Inaccurate Late Payment Reporting' . $acct() );
            $p( 'Dear ' . $bureau['name'] . ',' );
            $p( 'I am writing to dispute a late payment entry reported to my credit file for the account referenced above. This entry is inaccurate, as the payment in question was made on time.' );
            $b( 'Details of the disputed entry:' );
            $li( 'Creditor: ' . $v( 'creditor' ) );
            if ( $v( 'account' ) !== '' ) $li( 'Account number: ' . $v( 'account' ) );
            $li( 'Payment date reported as late: ' . nab_dispute_fmt_date( $v( 'late_date' ) ) );
            $li( 'Actual payment date: ' . nab_dispute_fmt_date( $v( 'paid_date' ) ) );
            $p( 'Enclosed is documentation confirming that this payment was made on or before its due date, including my ' . $v( 'proof' ) . '. I ask that you correct this entry to reflect an on-time payment, or remove it entirely if it cannot be verified as accurate.' );
            $p( 'Under the applicable provincial Consumer Reporting Act, furnishers of credit information are required to report accurate data, and consumer reporting agencies must reinvestigate disputed entries, generally within 30 days. I request written confirmation once the correction has been made.' );
            $p( 'Thank you for your attention to this matter.' );
            break;

        case 'notmine':
            $b( 'RE: Dispute – Account Not Belonging to Me' . $acct() );
            $p( 'Dear ' . $bureau['name'] . ',' );
            $p( 'I am writing to dispute an account appearing on my credit file that does not belong to me. I have no relationship with the creditor listed below and did not open or use this account.' );
            $b( 'Details of the disputed account:' );
            $li( 'Creditor: ' . $v( 'creditor' ) );
            if ( $v( 'account' ) !== '' ) $li( 'Account number: ' . $v( 'account' ) );
            $li( 'This account may belong to a different individual with a similar name, and my file may have been merged with theirs (a "mixed file").' );
            $p( 'I request that you investigate this account, confirm that it does not belong to me, and remove it from my credit file. Please also review my file for any other entries that may not belong to me as a result of a possible mixed file, and confirm in writing once the correction has been completed.' );
            $p( 'If, after investigation, you determine this is the result of identity theft rather than a mixed file, please advise me so that I may submit the appropriate fraud documentation.' );
            $p( 'Thank you for your attention to this matter.' );
            break;

        case 'fraud':
            $b( 'RE: Fraudulent Account Dispute – Identity Theft' . $acct() );
            $p( 'Dear ' . $bureau['name'] . ',' );
            $p( 'I am a victim of identity theft. I did not open, authorize or benefit from the account referenced above, and I am formally disputing this account as fraudulent.' );
            $b( 'Details of the fraudulent account:' );
            $li( 'Creditor: ' . $v( 'creditor' ) );
            if ( $v( 'account' ) !== '' ) $li( 'Account number: ' . $v( 'account' ) );
            if ( $v( 'opened' ) !== '' ) $li( 'Date the account was reportedly opened: ' . nab_dispute_fmt_date( $v( 'opened' ) ) );
            $li( 'I first became aware of this fraudulent account on: ' . nab_dispute_fmt_date( $v( 'aware' ) ) );
            if ( $v( 'police' ) !== '' ) {
                $p( 'I have reported this incident, and the report number is ' . $v( 'police' ) . ' for your reference.' );
            }
            $p( 'I request that you place a fraud alert on my file, block or remove this fraudulent account from my credit report, and stop any further reporting activity related to it pending investigation.' );
            $p( 'Please conduct a full investigation as required under the applicable provincial Consumer Reporting Act and provide written confirmation once the fraudulent account has been removed. I also request written confirmation of any information disclosed to third parties regarding this account, so that I may notify them of the error.' );
            $p( 'Thank you for your prompt attention to this matter.' );
            break;

        case 'inquiry':
            $b( 'Subject: Dispute of Unauthorized Hard Credit Inquiries' );
            $p( 'Dear ' . $bureau['name'] . ',' );
            $p( 'I am writing to formally dispute the hard credit inquiries below, which appear on my credit report. I do not recognize them and do not believe I authorized them.' );
            foreach ( (array) ( $f['items'] ?? [] ) as $r ) {
                $li( $r['company'] . ( nab_dispute_valid_date( $r['date'] ) ? ' – ' . nab_dispute_fmt_date( $r['date'] ) : '' ) );
            }
            $p( 'I do not recall applying for credit with these organizations or giving them permission to access my credit file. I respectfully request that you investigate these inquiries and verify that each organization had a lawful basis and my consent to obtain my credit information.' );
            $p( 'Under the Personal Information Protection and Electronic Documents Act (PIPEDA), organizations must obtain meaningful consent before collecting, using or disclosing an individual\'s personal information, except where otherwise permitted by law. I therefore request documentation showing that each inquiry was authorized, such as a signed credit application or other evidence of my consent, and the date and method by which it was obtained.' );
            $p( 'If you are unable to verify that these inquiries were authorized, I request that they be removed from my credit file and that an updated copy of my credit report be provided to me once your investigation is complete.' );
            $p( 'Please confirm receipt of this dispute and advise me of the outcome of your investigation in writing. Thank you for your prompt attention to this matter.' );
            break;

        case 'personal':
            $b( 'RE: Dispute of Inaccurate Personal Information on Credit File – ' . $v( 'name' ) . ( $v( 'fileref' ) !== '' ? ', File/Reference #' . $v( 'fileref' ) : '' ) );
            $p( 'Dear ' . $bureau['name'] . ',' );
            $p( 'I am writing to formally dispute inaccurate personal identifying information contained in my credit file. I request that this matter be investigated and corrected in accordance with applicable Canadian consumer reporting legislation.' );
            $b( 'The following information on my credit report is incorrect:' );
            $li( 'Incorrect: ' . $v( 'wrong' ) );
            $li( 'Correct information: ' . $v( 'correct' ) );
            $p( 'This inaccuracy may affect the reliability of my credit file and could result in confusion with another consumer\'s file (a possible "mixed file"). I ask that you investigate this matter, correct or delete the inaccurate information, and provide me with written confirmation of the outcome and an updated copy of my credit report once corrected.' );
            $p( 'Under the applicable provincial Consumer Reporting Act, a consumer reporting agency is generally required to investigate a disputed item, normally within 30 days of receiving this notice, and to advise me of the results in writing. Please treat this letter as my formal written notice of dispute.' );
            $p( 'Thank you for your attention to this matter.' );
            break;
    }

    $p( 'Sincerely,' );
    $out[] = [ 't' => 'sign' ];
    $out[] = [ 't' => 'lines', 's' => [ $v( 'name' ) ] ];
    $b( 'Enclosures:' );
    foreach ( $type['enclose'] as $e ) {
        if ( $l['type'] === 'fraud' && $v( 'police' ) === '' && strpos( $e, 'Police' ) === 0 ) continue;
        $li( $e );
    }
    return $out;
}

/* ═══════════════════════════════════════════════════════════
   PDF — a small, dependency-free writer for a text letter:
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
            case 'sign':
                $y -= $lead * 2;
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
        if ( ( $l['status'] ?? '' ) !== 'sent' || ! empty( $l['reminded'] ) || ( $l['sent'] ?? '9999' ) > $due ) continue;
        $bureau = $bureaus[ $l['bureau'] ]['name'] ?? 'the bureau';
        $what   = $types[ $l['type'] ]['label'] ?? 'dispute';
        nab_add_auto_notification( $uid, 'dispute', 'Any reply from ' . $bureau . ' about your ' . strtolower( $what ) . ' letter?',
            'You submitted it on ' . nab_dispute_fmt_date( $l['sent'] ) . '. Bureaus usually reply within about 30 days. Click "I got a reply" on the letter in the Dispute Center when you hear back, or contact ' . $bureau . ' to check on it.', 30 * DAY_IN_SECONDS );
        $letters[ $i ]['reminded'] = true;
        $changed = true;
    }
    if ( $changed ) update_user_meta( $uid, 'nab_dispute_letters', wp_json_encode( array_values( $letters ) ) );
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

add_action( 'wp_ajax_nab_dispute_save', function() {
    $uid    = nab_dispute_ajax_uid();
    $types  = nab_dispute_types();
    $id     = sanitize_key( $_POST['id'] ?? '' );
    $type   = sanitize_key( $_POST['type'] ?? '' );
    $bureau = sanitize_key( $_POST['bureau'] ?? '' );
    if ( ! isset( $types[ $type ] ) ) wp_send_json_error( [ 'message' => 'Please choose what you are disputing.' ] );
    if ( ! isset( nab_dispute_bureaus()[ $bureau ] ) ) wp_send_json_error( [ 'message' => 'Please choose Equifax or TransUnion.' ] );

    $in = isset( $_POST['f'] ) && is_array( $_POST['f'] ) ? $_POST['f'] : [];
    $f  = [
        'name'     => nab_dispute_clean( $in['name'] ?? '', 80 ),
        'street'   => nab_dispute_clean( $in['street'] ?? '', 120 ),
        'city'     => nab_dispute_clean( $in['city'] ?? '', 60 ),
        'province' => strtoupper( sanitize_key( $in['province'] ?? '' ) ),
        'postal'   => strtoupper( nab_dispute_clean( $in['postal'] ?? '', 10 ) ),
    ];
    if ( $f['name'] === '' )   wp_send_json_error( [ 'message' => 'Please enter your full name.' ] );
    if ( $f['street'] === '' || $f['city'] === '' || $f['postal'] === '' ) wp_send_json_error( [ 'message' => 'Please enter your full address.' ] );
    if ( ! isset( nab_dispute_provinces()[ $f['province'] ] ) ) wp_send_json_error( [ 'message' => 'Please choose your province or territory.' ] );

    foreach ( $types[ $type ]['fields'] as $fd ) {
        $k = $fd['key'];
        if ( $fd['type'] === 'rows' ) {
            $rows = [];
            foreach ( array_slice( (array) ( $in[ $k ] ?? [] ), 0, $fd['max'] ) as $r ) {
                if ( ! is_array( $r ) ) continue;
                $row = [];
                foreach ( $fd['cols'] as $c ) {
                    $val = $r[ $c['key'] ] ?? '';
                    $row[ $c['key'] ] = ( $c['type'] ?? '' ) === 'date'
                        ? ( nab_dispute_valid_date( $val ) ? $val : '' ) // checks the type and the format
                        : nab_dispute_clean( $val, ! empty( $c['long'] ) ? 300 : 120 );
                }
                if ( $row[ $fd['cols'][0]['key'] ] !== '' ) $rows[] = $row;
            }
            if ( ! $rows ) wp_send_json_error( [ 'message' => 'Please add at least one item to dispute (' . strtolower( $fd['cols'][0]['label'] ) . ').' ] );
            $f[ $k ] = $rows;
            continue;
        }
        $raw = $in[ $k ] ?? '';
        if ( $fd['type'] === 'date' ) {
            $val = nab_dispute_clean( $raw, 10 );
            if ( ! nab_dispute_valid_date( $val ) ) $val = '';
        } elseif ( $fd['type'] === 'textarea' ) {
            $val = is_scalar( $raw ) ? mb_substr( sanitize_textarea_field( wp_unslash( (string) $raw ) ), 0, 600 ) : '';
        } else {
            $val = nab_dispute_clean( $raw, 120 );
        }
        if ( $val === '' && empty( $fd['optional'] ) ) wp_send_json_error( [ 'message' => 'Please fill in: ' . $fd['label'] . '.' ] );
        $f[ $k ] = $val;
    }

    $letters = nab_dispute_get_letters( $uid );
    $row     = [ 'id' => $id ?: strtolower( wp_generate_password( 10, false ) ), 'type' => $type, 'bureau' => $bureau, 'f' => $f,
                 'created' => current_time( 'Y-m-d' ), 'status' => 'created', 'sent' => '', 'response' => '', 'reminded' => false ];
    $found   = false;
    foreach ( $letters as $i => $l ) {
        if ( $id && $l['id'] === $id ) {
            $row['status']   = $l['status'] ?? 'created'; // editing keeps where the letter is up to
            $row['sent']     = $l['sent'] ?? '';
            $row['response'] = $l['response'] ?? '';
            $row['reminded'] = ! empty( $l['reminded'] );
            $letters[ $i ]   = $row;
            $found           = true;
        }
    }
    if ( ! $found ) {
        if ( count( $letters ) >= 30 ) wp_send_json_error( [ 'message' => 'You can keep up to 30 letters. Delete an old one to make room.' ] );
        $letters[] = $row;
    }
    update_user_meta( $uid, 'nab_dispute_letters', wp_json_encode( array_values( $letters ) ) );

    $profile = array_intersect_key( $f, array_flip( [ 'name', 'street', 'city', 'province', 'postal' ] ) );
    $profile['dob'] = $f['dob'] ?? ( nab_dispute_get_profile( $uid )['dob'] ?? '' );
    update_user_meta( $uid, 'nab_dispute_profile', wp_json_encode( $profile ) );

    wp_send_json_success( [ 'message' => $found ? 'Letter updated.' : 'Your letter is ready.', 'id' => $row['id'], 'data' => nab_dispute_page_data( $uid ) ] );
} );

add_action( 'wp_ajax_nab_dispute_status', function() {
    $uid    = nab_dispute_ajax_uid();
    $id     = sanitize_key( $_POST['id'] ?? '' );
    $status = sanitize_key( $_POST['status'] ?? '' );
    if ( ! in_array( $status, [ 'created', 'sent', 'response' ], true ) ) wp_send_json_error( [ 'message' => 'Unknown status.' ] );
    $letters = nab_dispute_get_letters( $uid );
    $today   = current_time( 'Y-m-d' );
    $found   = false;
    foreach ( $letters as $i => $l ) {
        if ( $l['id'] !== $id ) continue;
        $found = true;
        $letters[ $i ]['status'] = $status;
        if ( $status === 'created' ) { $letters[ $i ]['sent'] = ''; $letters[ $i ]['response'] = ''; $letters[ $i ]['reminded'] = false; }
        if ( $status === 'sent' )    { $letters[ $i ]['sent'] = $l['sent'] ?: $today; $letters[ $i ]['response'] = ''; }
        if ( $status === 'response' ) { $letters[ $i ]['sent'] = $l['sent'] ?: $today; $letters[ $i ]['response'] = $today; }
    }
    if ( ! $found ) wp_send_json_error( [ 'message' => 'That letter could not be found.' ] );
    update_user_meta( $uid, 'nab_dispute_letters', wp_json_encode( array_values( $letters ) ) );
    $msg = [ 'created' => 'Marked as not sent yet.', 'sent' => 'Marked as submitted. We\'ll remind you if you haven\'t heard back in 30 days.', 'response' => 'Marked as answered.' ];
    wp_send_json_success( [ 'message' => $msg[ $status ], 'data' => nab_dispute_page_data( $uid ) ] );
} );

add_action( 'wp_ajax_nab_dispute_delete', function() {
    $uid     = nab_dispute_ajax_uid();
    $id      = sanitize_key( $_POST['id'] ?? '' );
    $letters = array_values( array_filter( nab_dispute_get_letters( $uid ), function( $l ) use ( $id ) { return $l['id'] !== $id; } ) );
    update_user_meta( $uid, 'nab_dispute_letters', wp_json_encode( $letters ) );
    wp_send_json_success( [ 'message' => 'Letter deleted.', 'data' => nab_dispute_page_data( $uid ) ] );
} );

/** GET: the letter as a PDF download. Only the member's own letters. */
add_action( 'wp_ajax_nab_dispute_pdf', function() {
    if ( ! check_ajax_referer( 'nab_portal_nonce', 'nonce', false ) ) wp_die( 'Your session has expired. Please refresh the Dispute Center and try again.', 'Session expired', [ 'response' => 403 ] );
    $uid    = get_current_user_id();
    $id     = sanitize_key( $_GET['id'] ?? '' );
    $letter = null;
    foreach ( nab_dispute_get_letters( $uid ) as $l ) if ( $l['id'] === $id ) $letter = $l;
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
    echo $pdf; // binary PDF built above from escaped, encoded text
    exit;
} );
