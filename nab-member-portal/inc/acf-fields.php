<?php
/**
 * NAB Member Portal — ACF Field Registrations (ACF Free compatible)
 *
 * HOW TO EDIT TEXT IN THE PORTAL:
 *   → Go to WP Admin → Edit your Dashboard page
 *   → Scroll down — you will see tabbed ACF panels
 *   → "Global Site Settings" — company name, support email, phone, address
 *   → "NAB Dashboard Settings" — all nav links, LoanConnect API, blog categories
 *   → Each tool page also has its own ACF group for that page's text
 *
 * All groups registered via PHP — no JSON import needed.
 * No repeater fields — ACF Free compatible.
 */
if ( ! defined( 'ABSPATH' ) ) exit;

add_action( 'acf/init', 'nab_register_all_acf_fields' );

function nab_register_all_acf_fields() {
    if ( ! function_exists( 'acf_add_local_field_group' ) ) return;

    /* ════════════════════════════════════════════════════════════
       GROUP 0 — GLOBAL SITE SETTINGS
       Shown on Dashboard page. ONE place to update contact details.
    ════════════════════════════════════════════════════════════ */
    acf_add_local_field_group( [
        'key'        => 'group_nab_global_v1',
        'title'      => 'NAB Portal — Global Site Settings',
        'location'   => [ [ [ 'param' => 'page_template', 'operator' => '==', 'value' => 'nab-dashboard' ] ] ],
        'position'   => 'normal',
        'menu_order' => 0,
        'fields'     => [
            [ 'key'=>'f_gs_t1', 'label'=>'Company Info', 'name'=>'', 'type'=>'tab', 'placement'=>'left', 'endpoint'=>0 ],

            [ 'key'=>'f_gs_company', 'label'=>'Company Name', 'name'=>'nab_company_name',
              'type'=>'text', 'default_value'=>'NAB Solutions',
              'instructions'=>'Used in PAD agreement and portal labels.' ],

            [ 'key'=>'f_gs_email', 'label'=>'Support Email', 'name'=>'nab_support_email',
              'type'=>'email', 'default_value'=>'admin@nabsolutions.ca', 'wrapper'=>['width'=>'50'],
              'instructions'=>'Shown on suspension screens, dispute center, and error pages. Update here when your email changes.' ],

            [ 'key'=>'f_gs_phone', 'label'=>'Support Phone', 'name'=>'nab_support_phone',
              'type'=>'text', 'default_value'=>'1-800-NAB-HELP', 'wrapper'=>['width'=>'50'],
              'instructions'=>'Shown in PAD agreement.' ],

            [ 'key'=>'f_gs_address', 'label'=>'Company Address', 'name'=>'nab_company_address',
              'type'=>'text', 'default_value'=>'Toronto, ON, Canada',
              'instructions'=>'Shown in PAD agreement.' ],

            [ 'key'=>'f_gs_tagline', 'label'=>'Sidebar Tagline', 'name'=>'nab_portal_tagline',
              'type'=>'text', 'default_value'=>'Member Portal',
              'instructions'=>'The small text under "NAB Solutions" in the sidebar on every page.' ],

            [ 'key'=>'f_gs_susp_msg', 'label'=>'Default Suspension Message', 'name'=>'nab_suspension_message',
              'type'=>'textarea', 'rows'=>3,
              'default_value'=>'Your account access has been temporarily suspended. Please contact support to resolve this.',
              'instructions'=>'Shown when member status = Suspended.' ],
        ],
    ] );

    /* ════════════════════════════════════════════════════════════
       GROUP 1 — DASHBOARD SETTINGS
       Nav links, LoanConnect API, blog categories, UM form IDs.
    ════════════════════════════════════════════════════════════ */
    acf_add_local_field_group( [
        'key'        => 'group_nab_dashboard_v3',
        'title'      => 'NAB Dashboard Settings',
        'location'   => [ [ [ 'param' => 'page_template', 'operator' => '==', 'value' => 'nab-dashboard' ] ] ],
        'position'   => 'normal',
        'menu_order' => 1,
        'fields'     => [

            /* ── Tab: Member Status (ACF fallback when MemberPress not active) ── */
            [ 'key'=>'f_nab_t1', 'label'=>'Member Status', 'name'=>'', 'type'=>'tab', 'placement'=>'left', 'endpoint'=>0 ],

            [ 'key'=>'f_nab_member_id', 'label'=>'Member ID', 'name'=>'nab_member_id',
              'type'=>'text', 'placeholder'=>'NAB-0001', 'wrapper'=>['width'=>'50'],
              'instructions'=>'Auto-generated from User ID on registration. Only set manually if needed.' ],

            [ 'key'=>'f_nab_status', 'label'=>'Member Status', 'name'=>'nab_member_status',
              'type'=>'select', 'default_value'=>'Active', 'wrapper'=>['width'=>'25'],
              'instructions'=>'When MemberPress is active, status is pulled from there automatically.',
              'choices'=>['Active'=>'Active','Suspended'=>'Suspended','Pending'=>'Pending'] ],

            [ 'key'=>'f_nab_payment', 'label'=>'Payment Status', 'name'=>'nab_payment_status',
              'type'=>'select', 'default_value'=>'Secured', 'wrapper'=>['width'=>'25'],
              'choices'=>['Secured'=>'Secured','Pending'=>'Pending','Overdue'=>'Overdue'] ],

            [ 'key'=>'f_nab_since', 'label'=>'Member Since', 'name'=>'nab_member_since',
              'type'=>'text', 'placeholder'=>'e.g. Jan 2024', 'wrapper'=>['width'=>'50'],
              'instructions'=>'Shown in welcome card. MemberPress overrides this automatically when active.' ],

            /* ── Tab: Navigation Links ── */
            [ 'key'=>'f_nab_t2', 'label'=>'Navigation Links', 'name'=>'', 'type'=>'tab', 'placement'=>'left', 'endpoint'=>0 ],

            [ 'key'=>'f_nab_lnk_profile',  'label'=>'Profile Page',           'name'=>'nab_link_profile',    'type'=>'page_link', 'allow_null'=>1, 'wrapper'=>['width'=>'50'] ],
            [ 'key'=>'f_nab_lnk_report',   'label'=>'Credit Report Page',     'name'=>'nab_link_report',     'type'=>'page_link', 'allow_null'=>1, 'wrapper'=>['width'=>'50'] ],
            [ 'key'=>'f_nab_lnk_util',     'label'=>'Utilization Checker',    'name'=>'nab_link_utilization','type'=>'page_link', 'allow_null'=>1, 'wrapper'=>['width'=>'50'] ],
            [ 'key'=>'f_nab_lnk_sim',      'label'=>'Score Simulator',        'name'=>'nab_link_simulator',  'type'=>'page_link', 'allow_null'=>1, 'wrapper'=>['width'=>'50'] ],
            [ 'key'=>'f_nab_lnk_dispute',  'label'=>'Dispute Center',         'name'=>'nab_link_dispute',    'type'=>'page_link', 'allow_null'=>1, 'wrapper'=>['width'=>'50'] ],
            [ 'key'=>'f_nab_lnk_loan',     'label'=>'Auto Loan Tool',         'name'=>'nab_link_loan',       'type'=>'page_link', 'allow_null'=>1, 'wrapper'=>['width'=>'50'],
              'instructions'=>'Create a page → template "NAB Auto Loan Tool" → select here.' ],
            [ 'key'=>'f_nab_lnk_cards',    'label'=>'Credit Card Matcher',    'name'=>'nab_link_card_match', 'type'=>'page_link', 'allow_null'=>1, 'wrapper'=>['width'=>'50'],
              'instructions'=>'Create a page → template "NAB Credit Card Matcher" → select here.' ],
            [ 'key'=>'f_nab_lnk_pad',      'label'=>'PAD Agreement Page',     'name'=>'nab_link_pad',        'type'=>'page_link', 'allow_null'=>1, 'wrapper'=>['width'=>'50'],
              'instructions'=>'Create a page → template "NAB PAD Agreement" → select here.' ],
            [ 'key'=>'f_nab_lnk_ef',       'label'=>'Emergency Fund Planner', 'name'=>'nab_link_emergency_fund', 'type'=>'page_link', 'allow_null'=>1, 'wrapper'=>['width'=>'50'],
              'instructions'=>'Create a page → template "NAB Emergency Fund Planner" → select here.' ],
            [ 'key'=>'f_nab_lnk_roadmap',  'label'=>'Financial Roadmap',      'name'=>'nab_link_roadmap',        'type'=>'page_link', 'allow_null'=>1, 'wrapper'=>['width'=>'50'],
              'instructions'=>'Create a page → template "NAB Financial Roadmap" → select here.' ],
            [ 'key'=>'f_nab_lnk_booking',  'label'=>'Book Specialist URL',    'name'=>'nab_link_booking',    'type'=>'url', 'wrapper'=>['width'=>'50'],
              'instructions'=>'Calendly or booking page URL. Also used as the fallback when loan offers are not found.' ],
            [ 'key'=>'f_nab_lnk_learning', 'label'=>'Learning Center URL',   'name'=>'nab_link_learning',   'type'=>'url', 'wrapper'=>['width'=>'50'],
              'instructions'=>'URL of the Learning Center page. Set after creating the page with NAB Learning Center template.' ],

            [ 'key'=>'f_nab_lnk_support',  'label'=>'Support Center URL',     'name'=>'nab_link_support',    'type'=>'url', 'wrapper'=>['width'=>'50'],
              'instructions'=>'URL of your Support Center page. Set after creating the page with NAB Support Center template.' ],

            [ 'key'=>'f_nab_lnk_chatbot',  'label'=>'NAB AI Chatbot URL',     'name'=>'nab_link_chatbot',    'type'=>'url', 'wrapper'=>['width'=>'50'],
              'instructions'=>'Leave blank → card shows SOON badge.' ],
            [ 'key'=>'f_nab_lnk_blog',     'label'=>'Education Blog URL',     'name'=>'nab_link_blog',       'type'=>'url', 'wrapper'=>['width'=>'50'],
              'instructions'=>'External blog URL for the "View all" link. Posts shown from WP category below.' ],
            [ 'key'=>'f_nab_lnk_diy',      'label'=>'DIY Repair Guide URL',   'name'=>'nab_link_diy',        'type'=>'url', 'wrapper'=>['width'=>'50'],
              'instructions'=>'External DIY guide URL for the "View all" link.' ],

            /* ── Tab: Blog & Content ── */
            [ 'key'=>'f_nab_t_blog', 'label'=>'Blog & Content', 'name'=>'', 'type'=>'tab', 'placement'=>'left', 'endpoint'=>0 ],

            [ 'key'=>'f_nab_blog_cat', 'label'=>'Education Blog — Category Slug', 'name'=>'nab_blog_category_slug',
              'type'=>'text', 'default_value'=>'credit-education', 'wrapper'=>['width'=>'50'],
              'instructions'=>'WP category slug. Posts in this category show in the Education Blog tab. Find slug: WP Admin → Posts → Categories.' ],
            [ 'key'=>'f_nab_diy_cat', 'label'=>'DIY Guide — Category Slug', 'name'=>'nab_diy_category_slug',
              'type'=>'text', 'default_value'=>'diy-guide', 'wrapper'=>['width'=>'50'],
              'instructions'=>'WP category slug. Posts in this category show in the DIY Guide tab.' ],

            /* ── Tab: Ultimate Member ── */
            // UM fields removed v1.6.2 - profile form is now native

            /* ── Tab: Education Modules ── */
            [ 'key'=>'f_nab_t_edu', 'label'=>'Education Modules', 'name'=>'', 'type'=>'tab', 'placement'=>'left', 'endpoint'=>0 ],

            [ 'key'=>'f_nab_vid1_url',   'label'=>'Video 1 URL (YouTube/Vimeo embed)',  'name'=>'nab_vid1_url',   'type'=>'url', 'wrapper'=>['width'=>'70'], 'instructions'=>'YouTube: click Share → Embed → copy src URL only. e.g. https://www.youtube.com/embed/XXXXX' ],
            [ 'key'=>'f_nab_vid1_thumb', 'label'=>'Video 1 Thumbnail',                  'name'=>'nab_vid1_thumb', 'type'=>'image', 'wrapper'=>['width'=>'30'], 'return_format'=>'url' ],

            [ 'key'=>'f_nab_vid2_url',   'label'=>'Video 2 URL',   'name'=>'nab_vid2_url',   'type'=>'url', 'wrapper'=>['width'=>'70'] ],
            [ 'key'=>'f_nab_vid2_thumb', 'label'=>'Video 2 Thumbnail', 'name'=>'nab_vid2_thumb', 'type'=>'image', 'wrapper'=>['width'=>'30'], 'return_format'=>'url' ],

            [ 'key'=>'f_nab_vid3_url',   'label'=>'Video 3 URL',   'name'=>'nab_vid3_url',   'type'=>'url', 'wrapper'=>['width'=>'70'] ],
            [ 'key'=>'f_nab_vid3_thumb', 'label'=>'Video 3 Thumbnail', 'name'=>'nab_vid3_thumb', 'type'=>'image', 'wrapper'=>['width'=>'30'], 'return_format'=>'url' ],

            [ 'key'=>'f_nab_vid4_url',   'label'=>'Video 4 URL',   'name'=>'nab_vid4_url',   'type'=>'url', 'wrapper'=>['width'=>'70'] ],
            [ 'key'=>'f_nab_vid4_thumb', 'label'=>'Video 4 Thumbnail', 'name'=>'nab_vid4_thumb', 'type'=>'image', 'wrapper'=>['width'=>'30'], 'return_format'=>'url' ],

            [ 'key'=>'f_nab_vid5_url',   'label'=>'Video 5 URL',   'name'=>'nab_vid5_url',   'type'=>'url', 'wrapper'=>['width'=>'70'] ],
            [ 'key'=>'f_nab_vid5_thumb', 'label'=>'Video 5 Thumbnail', 'name'=>'nab_vid5_thumb', 'type'=>'image', 'wrapper'=>['width'=>'30'], 'return_format'=>'url' ],

            [ 'key'=>'f_nab_vid6_url',   'label'=>'Video 6 URL',   'name'=>'nab_vid6_url',   'type'=>'url', 'wrapper'=>['width'=>'70'] ],
            [ 'key'=>'f_nab_vid6_thumb', 'label'=>'Video 6 Thumbnail', 'name'=>'nab_vid6_thumb', 'type'=>'image', 'wrapper'=>['width'=>'30'], 'return_format'=>'url' ],

            [ 'key'=>'f_nab_vid7_url',   'label'=>'Video 7 URL',   'name'=>'nab_vid7_url',   'type'=>'url', 'wrapper'=>['width'=>'70'] ],
            [ 'key'=>'f_nab_vid7_thumb', 'label'=>'Video 7 Thumbnail', 'name'=>'nab_vid7_thumb', 'type'=>'image', 'wrapper'=>['width'=>'30'], 'return_format'=>'url' ],

            /* ── Tab: Support Center (Freshdesk) ── */
            [ 'key'=>'f_nab_t_fd', 'label'=>'Support Center', 'name'=>'', 'type'=>'tab', 'placement'=>'left', 'endpoint'=>0 ],

            [ 'key'=>'f_nab_fd_key', 'label'=>'Freshdesk API Key', 'name'=>'nab_freshdesk_key',
              'type'=>'password', 'wrapper'=>['width'=>'50'],
              'default_value'=>'0D70ZUpg0Ka68uSDsDk_',
              'instructions'=>'Get from Freshdesk → Profile Settings → API Key.' ],

            [ 'key'=>'f_nab_fd_domain', 'label'=>'Freshdesk Account URL', 'name'=>'nab_freshdesk_domain',
              'type'=>'text', 'default_value'=>'nabsolutions.freshdesk.com', 'wrapper'=>['width'=>'50'],
              'instructions'=>'Your Freshdesk subdomain e.g. nabsolutions.freshdesk.com' ],

            /* ── Tab: OpenAI / AI Features ── */
            [ 'key'=>'f_nab_t_ai', 'label'=>'OpenAI / AI Features', 'name'=>'', 'type'=>'tab', 'placement'=>'left', 'endpoint'=>0 ],

            [ 'key'=>'f_nab_openai_key', 'label'=>'OpenAI API Key', 'name'=>'nab_openai_key',
              'type'=>'password', 'wrapper'=>['width'=>'100'],
              'instructions'=>'Paste your OpenAI API key here (starts with sk-...). Once added, the AI NAB Assistant chatbot, Credit Card Finder assistant, and AI Credit Decision Explainer all activate automatically. Get your key from platform.openai.com → API Keys.' ],

            [ 'key'=>'f_nab_ai_model', 'label'=>'AI Model', 'name'=>'nab_ai_model',
              'type'=>'select', 'wrapper'=>['width'=>'50'],
              'choices'=>['gpt-4o-mini'=>'GPT-4o Mini (recommended — fast & affordable)','gpt-4o'=>'GPT-4o (more powerful, higher cost)'],
              'default_value'=>'gpt-4o-mini',
              'instructions'=>'GPT-4o Mini is recommended for most use cases. Switch to GPT-4o for more detailed AI responses.' ],

            [ 'key'=>'f_nab_ai_enabled', 'label'=>'AI Features Enabled', 'name'=>'nab_ai_enabled',
              'type'=>'true_false', 'wrapper'=>['width'=>'50'],
              'default_value'=>1, 'ui'=>1,
              'instructions'=>'Toggle all AI features on/off without removing the API key.' ],

            /* ── Tab: MemberPress Plans ── */
            [ 'key'=>'f_nab_t_mp', 'label'=>'Membership Plans', 'name'=>'', 'type'=>'tab', 'placement'=>'left', 'endpoint'=>0 ],

            [ 'key'=>'f_nab_mp_basic_id', 'label'=>'Basic Plan ID', 'name'=>'nab_mp_plan_basic',
              'type'=>'number', 'default_value'=>0, 'wrapper'=>['width'=>'33'],
              'instructions'=>'WP Admin → MemberPress → Memberships → Edit Basic → copy number from URL bar (?post=XXX)' ],

            [ 'key'=>'f_nab_mp_std_id', 'label'=>'Standard Plan ID', 'name'=>'nab_mp_plan_standard',
              'type'=>'number', 'default_value'=>0, 'wrapper'=>['width'=>'33'],
              'instructions'=>'WP Admin → MemberPress → Memberships → Edit Standard → copy number from URL bar (?post=XXX)' ],

            [ 'key'=>'f_nab_mp_prem_id', 'label'=>'Premium Plan ID', 'name'=>'nab_mp_plan_premium',
              'type'=>'number', 'default_value'=>0, 'wrapper'=>['width'=>'33'],
              'instructions'=>'WP Admin → MemberPress → Memberships → Edit Premium → copy number from URL bar (?post=XXX)' ],

            [ 'key'=>'f_nab_mp_basic_price',  'label'=>'Basic Price Label',    'name'=>'nab_mp_basic_price',
              'type'=>'text', 'default_value'=>'$19/month', 'wrapper'=>['width'=>'33'],
              'instructions'=>'Display label shown on plan cards e.g. $19/month' ],

            [ 'key'=>'f_nab_mp_std_price',    'label'=>'Standard Price Label', 'name'=>'nab_mp_standard_price',
              'type'=>'text', 'default_value'=>'$39/month', 'wrapper'=>['width'=>'33'],
              'instructions'=>'Display label shown on plan cards' ],

            [ 'key'=>'f_nab_mp_prem_price',   'label'=>'Premium Price Label',  'name'=>'nab_mp_premium_price',
              'type'=>'text', 'default_value'=>'$79/month', 'wrapper'=>['width'=>'33'],
              'instructions'=>'Display label shown on plan cards' ],

            [ 'key'=>'f_nab_mp_plans_page', 'label'=>'Membership Plans Page URL', 'name'=>'nab_mp_plans_url',
              'type'=>'url', 'default_value'=>'', 'wrapper'=>['width'=>'100'],
              'instructions'=>'URL of your /membership-plans/ page. Used in "View Plans" and upgrade links throughout the portal.' ],

            /* ── Tab: LoanConnect API ── */
            [ 'key'=>'f_nab_t_lc', 'label'=>'LoanConnect API', 'name'=>'', 'type'=>'tab', 'placement'=>'left', 'endpoint'=>0 ],

            [ 'key'=>'f_nab_lc_affid', 'label'=>'Affiliate ID (affid)', 'name'=>'nab_lc_affid',
              'type'=>'text', 'wrapper'=>['width'=>'50'],
              'instructions'=>'From LoanConnect Partner Dashboard → API Credentials. KEEP SECRET — do not share.' ],
            [ 'key'=>'f_nab_lc_key', 'label'=>'API Key (key)', 'name'=>'nab_lc_key',
              'type'=>'password', 'wrapper'=>['width'=>'50'],
              'instructions'=>'From LoanConnect Partner Dashboard → API Credentials. KEEP SECRET — do not share.' ],
            [ 'key'=>'f_nab_lc_decline', 'label'=>'Loan Decline Redirect URL', 'name'=>'nab_lc_decline_url',
              'type'=>'url', 'wrapper'=>['width'=>'100'],
              'instructions'=>'Where to send members when no loan offers return. Leave blank → shows "Book Specialist" button using the booking URL above.' ],

            /* ── Tab: Credit Provider URLs ── */
            [ 'key'=>'f_nab_t3', 'label'=>'Credit Provider URLs', 'name'=>'', 'type'=>'tab', 'placement'=>'left', 'endpoint'=>0 ],

            [ 'key'=>'f_nab_equifax',     'label'=>'Equifax Canada URL',    'name'=>'nab_equifax_url',     'type'=>'url', 'default_value'=>'https://www.equifax.ca',     'wrapper'=>['width'=>'50'] ],
            [ 'key'=>'f_nab_transunion',  'label'=>'TransUnion Canada URL', 'name'=>'nab_transunion_url',  'type'=>'url', 'default_value'=>'https://www.transunion.ca',  'wrapper'=>['width'=>'50'] ],
            [ 'key'=>'f_nab_borrowell',   'label'=>'Borrowell URL',         'name'=>'nab_borrowell_url',   'type'=>'url', 'default_value'=>'https://www.borrowell.com',  'wrapper'=>['width'=>'50'] ],
            [ 'key'=>'f_nab_creditkarma', 'label'=>'Credit Karma URL',      'name'=>'nab_creditkarma_url', 'type'=>'url', 'default_value'=>'https://www.creditkarma.ca', 'wrapper'=>['width'=>'50'] ],
        ],
    ] );

    /* ════════════════════════════════════════════════════════════
       GROUP 2 — CREDIT SCORE SIMULATOR
    ════════════════════════════════════════════════════════════ */
    acf_add_local_field_group( [
        'key'      => 'group_nab_simulator_v2',
        'title'    => 'Score Simulator — Editable Text',
        'location' => [ [ [ 'param' => 'page_template', 'operator' => '==', 'value' => 'nab-simulator' ] ] ],
        'position' => 'normal',
        'fields'   => [
            [ 'key'=>'f_sim_t1', 'label'=>'Page Text', 'name'=>'', 'type'=>'tab', 'placement'=>'left', 'endpoint'=>0 ],
            [ 'key'=>'f_sim_hero_title',  'label'=>'Page Title',         'name'=>'sim_hero_title',
              'type'=>'text', 'default_value'=>'Credit Score Impact Simulator' ],
            [ 'key'=>'f_sim_hero_desc',   'label'=>'Hero Description',   'name'=>'sim_hero_desc',
              'type'=>'textarea', 'rows'=>2,
              'default_value'=>"Simulate 'what-if' scenarios to see how certain actions could affect your score range." ],
            [ 'key'=>'f_sim_disclaimer',  'label'=>'Disclaimer Text',    'name'=>'sim_disclaimer',
              'type'=>'textarea', 'rows'=>3,
              'default_value'=>'Estimates are for educational purposes only and are based on simplified scenarios. Actual score changes will vary based on your full credit profile.' ],
            [ 'key'=>'f_sim_guidance',    'label'=>'Guidance Note',      'name'=>'sim_guidance_note',
              'type'=>'text', 'default_value'=>'For personalized guidance, consider speaking with our qualified credit expert.' ],
            [ 'key'=>'f_sim_liability',   'label'=>'Liability Statement','name'=>'sim_liability',
              'type'=>'textarea', 'rows'=>2,
              'default_value'=>'NAB Solutions does not guarantee the accuracy of simulator outputs and is not liable for any decisions taken based on these estimates.' ],
        ],
    ] );

    /* ════════════════════════════════════════════════════════════
       GROUP 3 — UTILIZATION CHECKER
    ════════════════════════════════════════════════════════════ */
    acf_add_local_field_group( [
        'key'      => 'group_nab_util_v2',
        'title'    => 'Utilization Checker — Editable Text',
        'location' => [ [ [ 'param' => 'page_template', 'operator' => '==', 'value' => 'nab-utilization' ] ] ],
        'position' => 'normal',
        'fields'   => [
            [ 'key'=>'f_uc_t1', 'label'=>'Page Text', 'name'=>'', 'type'=>'tab', 'placement'=>'left', 'endpoint'=>0 ],
            [ 'key'=>'f_uc_hero_title', 'label'=>'Page Title',       'name'=>'uc_hero_title',
              'type'=>'text', 'default_value'=>'Utilization Checker' ],
            [ 'key'=>'f_uc_hero_desc',  'label'=>'Hero Description', 'name'=>'uc_hero_desc',
              'type'=>'textarea', 'rows'=>3,
              'default_value'=>'Check your revolving credit utilization across all cards. Many scoring models consider under 30% as generally favorable, while under 10% is often associated with stronger score outcomes.' ],
            [ 'key'=>'f_uc_form_note',  'label'=>'Form Sub-note',    'name'=>'uc_form_note',
              'type'=>'text',
              'default_value'=>'You can add up to 5 revolving credit cards. Information is saved to your account and used only within this tool.' ],
        ],
    ] );

    /* ════════════════════════════════════════════════════════════
       GROUP 4 — CREDIT REPORT ACCESS
    ════════════════════════════════════════════════════════════ */
    acf_add_local_field_group( [
        'key'      => 'group_nab_report_v2',
        'title'    => 'Credit Report Access — Editable Text',
        'location' => [ [ [ 'param' => 'page_template', 'operator' => '==', 'value' => 'nab-credit-report' ] ] ],
        'position' => 'normal',
        'fields'   => [
            [ 'key'=>'f_cr_t1', 'label'=>'Page Text', 'name'=>'', 'type'=>'tab', 'placement'=>'left', 'endpoint'=>0 ],
            [ 'key'=>'f_cr_hero_title',    'label'=>'Hero Title',         'name'=>'hero_title',         'type'=>'text',     'default_value'=>'Credit Report Access' ],
            [ 'key'=>'f_cr_hero_subtitle', 'label'=>'Hero Subtitle',      'name'=>'hero_subtitle',      'type'=>'textarea', 'rows'=>2,
              'default_value'=>'Access your credit report and score through trusted Canadian providers.' ],
            [ 'key'=>'f_cr_sec_subhd',     'label'=>'Section Subheading', 'name'=>'section_note',       'type'=>'text',
              'default_value'=>'These are official, secure services that include free access options.' ],
            [ 'key'=>'f_cr_disclaimer',    'label'=>'Bottom Disclaimer',  'name'=>'cr_disclaimer',      'type'=>'textarea', 'rows'=>3,
              'default_value'=>'All links open third-party websites. NAB Solutions is not affiliated with or compensated by these providers. We do not control their content, terms, or privacy practices.' ],

            [ 'key'=>'f_cr_t2', 'label'=>'Provider Links', 'name'=>'', 'type'=>'tab', 'placement'=>'left', 'endpoint'=>0 ],
            [ 'key'=>'f_cr_eq_url',  'label'=>'Equifax URL',      'name'=>'cr_equifax_url',     'type'=>'url', 'default_value'=>'https://www.equifax.ca/personal/credit-report-score/' ],
            [ 'key'=>'f_cr_tu_url',  'label'=>'TransUnion URL',   'name'=>'cr_transunion_url',  'type'=>'url', 'default_value'=>'https://www.transunion.ca' ],
            [ 'key'=>'f_cr_bo_url',  'label'=>'Borrowell URL',    'name'=>'cr_borrowell_url',   'type'=>'url', 'default_value'=>'https://www.borrowell.com' ],
            [ 'key'=>'f_cr_ck_url',  'label'=>'Credit Karma URL', 'name'=>'cr_creditkarma_url', 'type'=>'url', 'default_value'=>'https://www.creditkarma.ca' ],
        ],
    ] );

    /* ════════════════════════════════════════════════════════════
       GROUP 5 — DISPUTE CENTER
    ════════════════════════════════════════════════════════════ */
    acf_add_local_field_group( [
        'key'      => 'group_nab_dispute_v2',
        'title'    => 'Dispute Center — Editable Text',
        'location' => [ [ [ 'param' => 'page_template', 'operator' => '==', 'value' => 'nab-dispute-center' ] ] ],
        'position' => 'normal',
        'fields'   => [
            [ 'key'=>'f_dc_t1', 'label'=>'Page Text', 'name'=>'', 'type'=>'tab', 'placement'=>'left', 'endpoint'=>0 ],
            [ 'key'=>'f_dc_disclosure',   'label'=>'Top Disclosure',      'name'=>'dc_disclosure',
              'type'=>'textarea', 'rows'=>4,
              'default_value'=>'We assist you in preparing dispute information but we are not a credit bureau, lender, or law firm. You may dispute information on your credit report directly with the credit bureaus at no cost. Submitting this form does not guarantee any specific outcome.' ],
            [ 'key'=>'f_dc_response',     'label'=>'Response Time Note',  'name'=>'dc_response_time',
              'type'=>'textarea', 'rows'=>3,
              'default_value'=>'We will review your submission and respond within 5–7 business days. This does not control how quickly credit bureaus update your report.' ],
            [ 'key'=>'f_dc_bureau_note',  'label'=>'Bureau Timeline Note','name'=>'dc_bureau_note',
              'type'=>'textarea', 'rows'=>2,
              'default_value'=>'Credit bureaus generally have up to 30 days to investigate disputes submitted directly to them.' ],
        ],
    ] );

    /* ════════════════════════════════════════════════════════════
       GROUP 6 — AUTO LOAN TOOL
    ════════════════════════════════════════════════════════════ */
    acf_add_local_field_group( [
        'key'      => 'group_nab_loan_v1',
        'title'    => 'Auto Loan Tool — Editable Text',
        'location' => [ [ [ 'param' => 'page_template', 'operator' => '==', 'value' => 'nab-loan' ] ] ],
        'position' => 'normal',
        'fields'   => [
            [ 'key'=>'f_ln_t1', 'label'=>'Page Text', 'name'=>'', 'type'=>'tab', 'placement'=>'left', 'endpoint'=>0 ],
            [ 'key'=>'f_ln_heading', 'label'=>'Page Heading',    'name'=>'loan_heading',
              'type'=>'text', 'default_value'=>'Find Your Auto Loan' ],
            [ 'key'=>'f_ln_sub',     'label'=>'Subheading',      'name'=>'loan_subheading',
              'type'=>'textarea', 'rows'=>2,
              'default_value'=>"We'll match you with Canadian lenders in under 30 seconds. Transmitted securely to LoanConnect. Does not affect your credit score." ],
            [ 'key'=>'f_ln_disc',    'label'=>'Privacy Disclosure', 'name'=>'loan_disclosure',
              'type'=>'textarea', 'rows'=>3,
              'default_value'=>'By submitting this form you consent to NAB Solutions sharing your information with LoanConnect and its network of Canadian lenders. Approval is not guaranteed and is subject to each lender\'s individual criteria.' ],
        ],
    ] );

    /* ════════════════════════════════════════════════════════════
       GROUP 7 — PAD AGREEMENT
    ════════════════════════════════════════════════════════════ */
    acf_add_local_field_group( [
        'key'      => 'group_nab_pad_v1',
        'title'    => 'PAD Agreement — Settings',
        'location' => [ [ [ 'param' => 'page_template', 'operator' => '==', 'value' => 'nab-pad' ] ] ],
        'position' => 'normal',
        'fields'   => [
            [ 'key'=>'f_pad_t1', 'label'=>'PAD Details', 'name'=>'', 'type'=>'tab', 'placement'=>'left', 'endpoint'=>0 ],
            [ 'key'=>'f_pad_pdf',     'label'=>'PAD Agreement PDF',   'name'=>'nab_pad_pdf_url',
              'type'=>'file', 'return_format'=>'url', 'mime_types'=>'pdf',
              'instructions'=>'Upload the signed PAD agreement PDF. Members will see a Download PDF button.' ],
            [ 'key'=>'f_pad_date',    'label'=>'Agreement Date',      'name'=>'nab_pad_date',
              'type'=>'text', 'placeholder'=>'e.g. January 15, 2024', 'wrapper'=>['width'=>'50'] ],
            [ 'key'=>'f_pad_amount',  'label'=>'Monthly Amount (CAD)','name'=>'nab_pad_amount',
              'type'=>'number', 'placeholder'=>'e.g. 49.99', 'wrapper'=>['width'=>'50'] ],
            [ 'key'=>'f_pad_acct4',   'label'=>'Bank Account Last 4', 'name'=>'nab_pad_account_last4',
              'type'=>'text', 'maxlength'=>4, 'placeholder'=>'e.g. 4567', 'wrapper'=>['width'=>'50'],
              'instructions'=>'Last 4 digits of member bank account shown on agreement.' ],
        ],
    ] );

    /* ════════════════════════════════════════════════════════════
       GROUP 7B — FINANCIAL ROADMAP
       Hero text only. The checklist steps themselves (titles, links,
       auto-complete logic) are defined in nab_get_roadmap_steps() in
       inc/helpers.php, NOT here — they read real portal data (credit
       score, EF goal, loan history, etc.) so they can't be configured
       as flat ACF text fields without duplicating that logic. Keeping
       them in code also avoids needing a repeater field, which ACF
       Free doesn't support.
    ════════════════════════════════════════════════════════════ */
    acf_add_local_field_group( [
        'key'      => 'group_nab_roadmap_v1',
        'title'    => 'Financial Roadmap — Editable Text',
        'location' => [ [ [ 'param' => 'page_template', 'operator' => '==', 'value' => 'nab-roadmap' ] ] ],
        'position' => 'normal',
        'fields'   => [
            [ 'key'=>'f_rm_t1', 'label'=>'Page Text', 'name'=>'', 'type'=>'tab', 'placement'=>'left', 'endpoint'=>0 ],
            [ 'key'=>'f_rm_hero_title', 'label'=>'Hero Title', 'name'=>'roadmap_hero_title',
              'type'=>'text', 'default_value'=>'Your Financial Roadmap' ],
            [ 'key'=>'f_rm_hero_sub',   'label'=>'Hero Subtitle', 'name'=>'roadmap_hero_sub',
              'type'=>'textarea', 'rows'=>2,
              'default_value'=>"A personalised checklist built from what you've already done in your portal. Complete a step and it checks off automatically." ],
        ],
    ] );

    /* ════════════════════════════════════════════════════════════
       GROUP 8 — DISPUTE STATUS (shown on User edit screen in WP Admin)
       Admin fills these in to update a member's dispute status.
    ════════════════════════════════════════════════════════════ */
    acf_add_local_field_group( [
        'key'      => 'group_nab_dispute_status',
        'title'    => 'NAB Dispute Status (Admin Use)',
        'location' => [ [ [ 'param' => 'user_form', 'operator' => '==', 'value' => 'all' ] ] ],
        'position' => 'normal',
        'fields'   => [
            [ 'key'=>'f_disp_status', 'label'=>'Dispute Status',                'name'=>'dispute_status',
              'type'=>'select', 'default_value'=>'Pending',
              'choices'=>['Pending'=>'Pending','In Review'=>'In Review','Resolved'=>'Resolved','Closed'=>'Closed'] ],
            [ 'key'=>'f_disp_ref',    'label'=>'Reference Number',               'name'=>'dispute_reference', 'type'=>'text' ],
            [ 'key'=>'f_disp_note',   'label'=>'Status Note (shown to member)',   'name'=>'dispute_note',   'type'=>'textarea', 'rows'=>3 ],
        ],
    ] );
}
