<?php
/**
 * NAB Member Portal — Tool Registry (v1.9.1)
 *
 * SINGLE SOURCE OF TRUTH for every portal page and every sidebar/search
 * entry. Before v1.9.1 a new tool had to be added by hand in ~8 places
 * (template-loader x2, the nabPortal enqueue list, the LiteSpeed no-cache
 * list, the sidebar, the dashboard search, ...) and missing one caused
 * bugs like "Session error. Please refresh the page." Now:
 *
 *   1. nab_tools()        — page templates  → template loader, page-template
 *                           dropdown, nabPortal (ajax url + nonce), no-cache
 *   2. nab_nav_sections() — sidebar groups and items → sidebar + search
 *
 * Adding a tool = one entry in each list + the template file. See
 * DEVELOPER.md ("Adding a new tool").
 *
 * Both lists are filterable ('nab_tools', 'nab_nav_sections') so an
 * add-on plugin can register tools without editing this file.
 *
 * @package NAB_Member_Portal
 * @since   1.9.1
 */
if ( ! defined( 'ABSPATH' ) ) exit;

/* ═══════════════════════════════════════════════════════════
   1. PAGE TEMPLATES
   key = value stored in the page's _wp_page_template meta.
     name  — label in Page Attributes → Template
     file  — file in /templates
     cache — false = never page-cache (default false: portal pages
             show member-specific data and nonces)
   Order = order in the template dropdown.
   ═══════════════════════════════════════════════════════════ */
function nab_tools() {
    static $tools = null;
    if ( $tools !== null ) return $tools;
    $tools = apply_filters( 'nab_tools', [
        'nab-dashboard'      => [ 'name' => 'NAB Member Dashboard',        'file' => 'page-nab-dashboard.php' ],
        'nab-lfp'            => [ 'name' => 'NAB Lending Finder Program',  'file' => 'page-nab-lfp.php' ],
        'nab-simulator'      => [ 'name' => 'NAB Credit Score Simulator',  'file' => 'page-nab-simulator.php' ],
        'nab-utilization'    => [ 'name' => 'NAB Utilization Checker',     'file' => 'page-utilization-checker.php' ],
        'nab-credit-report'  => [ 'name' => 'NAB Credit Report Access',    'file' => 'credit-report-template.php' ],
        'nab-dispute-center' => [ 'name' => 'NAB Credit Dispute Center',   'file' => 'dispute-center-template.php' ],
        'nab-loan'           => [ 'name' => 'NAB Auto Loan Tool',          'file' => 'page-nab-loan.php' ],
        'nab-card-match'     => [ 'name' => 'NAB Credit Card Matcher',     'file' => 'page-nab-card-match.php' ],
        'nab-pad'            => [ 'name' => 'NAB PAD Agreement',           'file' => 'page-nab-pad.php' ],
        'nab-chatbot'        => [ 'name' => 'NAB AI Chatbot',              'file' => 'page-nab-chatbot.php' ],
        'nab-support'        => [ 'name' => 'NAB Support Center',          'file' => 'page-nab-support.php' ],
        'nab-learning'       => [ 'name' => 'NAB Learning Center',         'file' => 'page-nab-learning.php' ],
        'nab-plans'          => [ 'name' => 'NAB Membership Plans',        'file' => 'page-membership-plans.php' ],
        'nab-emergency-fund' => [ 'name' => 'NAB Emergency Fund Planner',  'file' => 'page-nab-emergency-fund.php' ],
        'nab-roadmap'        => [ 'name' => 'NAB Financial Roadmap',       'file' => 'page-nab-roadmap.php' ],
    ] );
    return $tools;
}

/** Template slug of the page being viewed, if it is a portal page — else ''. */
function nab_current_tool() {
    if ( ! is_page() ) return '';
    $slug = get_post_meta( get_queried_object_id(), '_wp_page_template', true );
    return isset( nab_tools()[ $slug ] ) ? $slug : '';
}

/* ═══════════════════════════════════════════════════════════
   2. SIDEBAR + SEARCH
   Each item:
     key      — id used for the active state (nab_render_sidebar( $key ))
                and the link lookup in nab_get_nav_links()
     label    — sidebar text
     icon     — name from inc/icons.php
     tab      — (optional) dashboard tab to open instead of a page
     template — (optional) page template to link to when nab_get_nav_links()
                has no entry for this key — new tools need no ACF field
     desc / keywords — shown / matched by the dashboard search
   ═══════════════════════════════════════════════════════════ */
function nab_nav_sections() {
    static $sections = null;
    if ( $sections !== null ) return $sections;
    $sections = apply_filters( 'nab_nav_sections', [
        'main' => [ 'label' => 'Main', 'items' => [
            [ 'key' => 'dashboard', 'label' => 'Dashboard', 'icon' => 'house', 'desc' => 'Your overview', 'keywords' => 'home overview' ],
        ] ],
        'credit' => [ 'label' => 'Credit Tools', 'items' => [
            [ 'key' => 'report',  'label' => 'Credit Report Access', 'icon' => 'file-text',    'desc' => 'Access your Equifax, TransUnion, Borrowell or Credit Karma report', 'keywords' => 'credit report equifax transunion borrowell' ],
            [ 'key' => 'util',    'label' => 'Utilization Checker',  'icon' => 'gauge',        'desc' => 'See how much of your credit you are using',  'keywords' => 'utilization checker credit usage cards' ],
            [ 'key' => 'sim',     'label' => 'Score Simulator',      'icon' => 'trending-up',  'desc' => 'Simulate what-if scenarios for your score',  'keywords' => 'score simulator credit score what if' ],
            [ 'key' => 'dispute', 'label' => 'Dispute Center',       'icon' => 'shield-check', 'desc' => 'Submit a credit dispute and track your case', 'keywords' => 'dispute center credit error' ],
        ] ],
        'services' => [ 'label' => 'Services', 'items' => [
            [ 'key' => 'booking', 'label' => 'Book Specialist', 'icon' => 'calendar-days',       'desc' => 'Schedule a 1-on-1 session with a credit advisor', 'keywords' => 'book specialist appointment advisor session' ],
            [ 'key' => 'chatbot', 'label' => 'NAB AI Chatbot',  'icon' => 'message-square-text', 'desc' => 'Ask the NAB assistant anything about credit',     'keywords' => 'ai chatbot assistant help question' ],
            [ 'key' => 'support', 'label' => 'Support Center',  'icon' => 'life-buoy',           'desc' => 'Submit a support ticket or track existing tickets', 'keywords' => 'support ticket help contact' ],
        ] ],
        'loan' => [ 'label' => 'Loan Tools', 'items' => [
            [ 'key' => 'lfp',        'label' => 'Lending Finder Program', 'icon' => 'landmark',    'desc' => 'Find lenders that fit your profile',            'keywords' => 'lending finder lfp lender loan' ],
            [ 'key' => 'loan',       'label' => 'Auto Loan Matcher',      'icon' => 'car',         'desc' => 'Auto loan offers from Canadian lenders',          'keywords' => 'auto loan car vehicle lender' ],
            [ 'key' => 'card-match', 'label' => 'Card Matcher',           'icon' => 'credit-card', 'desc' => 'Get matched to the best credit card for you',    'keywords' => 'credit card matcher best card' ],
        ] ],
        'resources' => [ 'label' => 'Resources', 'items' => [
            [ 'key' => 'learning', 'label' => 'Learning Center',        'icon' => 'graduation-cap', 'desc' => '7-part credit education video series',   'keywords' => 'learning center education videos modules lessons' ],
            [ 'key' => 'blog',     'label' => 'Education Blog',         'icon' => 'book-open',      'tab' => 'blog', 'desc' => 'Credit articles and tips',  'keywords' => 'education blog articles credit tips' ],
            [ 'key' => 'diy',      'label' => 'DIY Repair Guide',       'icon' => 'file-pen-line',  'tab' => 'diy',  'desc' => 'Ready-to-use dispute letter templates', 'keywords' => 'diy repair guide dispute letter template' ],
            [ 'key' => 'ef',       'label' => 'Emergency Fund Planner', 'icon' => 'piggy-bank',     'desc' => 'Set a savings goal and track progress', 'keywords' => 'emergency fund savings goal planner' ],
            [ 'key' => 'roadmap',  'label' => 'Financial Roadmap',      'icon' => 'map',            'desc' => 'Your personalised action plan',         'keywords' => 'financial roadmap plan goals steps' ],
            [ 'key' => 'pad',      'label' => 'PAD Agreement',          'icon' => 'file-check',     'desc' => 'Your Pre-Authorized Debit agreement',   'keywords' => 'pad agreement debit authorization' ],
        ] ],
    ] );
    return $sections;
}

/** Resolved URL for a nav item, or '#' when it isn't set up yet. */
function nab_nav_item_url( $item ) {
    $nav = nab_get_nav_links();
    $url = $nav[ $item['key'] ] ?? '#';
    if ( $url === '#' && ! empty( $item['template'] ) ) $url = nab_get_template_url( $item['template'] );
    return $url ?: '#';
}

/**
 * Items for the dashboard search box, built from the sidebar so every
 * tool is searchable the moment it is registered.
 */
function nab_search_items() {
    $items = [];
    $learning = nab_get_nav_links()['learning'] ?? '#';
    foreach ( nab_nav_sections() as $section ) {
        foreach ( $section['items'] as $it ) {
            if ( $it['key'] === 'dashboard' ) continue;
            $url = empty( $it['tab'] ) ? nab_nav_item_url( $it ) : '';
            if ( empty( $it['tab'] ) && $url === '#' ) continue; // not set up on this site yet
            $items[] = [
                'icon'  => $it['icon'],
                'title' => $it['label'],
                'desc'  => $it['desc'] ?? '',
                'tab'   => $it['tab'] ?? '',
                'url'   => $url,
                'key'   => $it['keywords'] ?? '',
            ];
        }
    }
    // Learning Center lessons (titles mirror the module list on the dashboard)
    if ( $learning !== '#' ) {
        $lessons = [
            'What is Credit, Really?', 'The Trust Recipe: How Credit Scores Are Calculated', 'The Escalating Cost of Bad Advice',
            'Free Canadian Credit Reports: The Complete Guide', 'Securing Your Free Canadian Credit Reports',
            'The Credit Inquiry Guide: Hard and Soft Pulls', 'Building Better Financial Habits',
        ];
        foreach ( $lessons as $i => $t ) {
            $items[] = [ 'icon' => 'play', 'title' => $t, 'desc' => 'Lesson ' . ( $i + 1 ) . ' · Learning Center', 'tab' => '', 'url' => $learning, 'key' => 'module lesson video ' . ( $i + 1 ) ];
        }
    }
    return apply_filters( 'nab_search_items', $items );
}
