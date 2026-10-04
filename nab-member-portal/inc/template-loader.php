<?php
if ( ! defined( 'ABSPATH' ) ) exit;

add_filter( 'theme_page_templates', function( $templates ) {
    $templates['nab-dashboard']      = 'NAB Member Dashboard';
    $templates['nab-lfp']            = 'NAB Lending Finder Program';
    $templates['nab-simulator']      = 'NAB Credit Score Simulator';
    $templates['nab-utilization']    = 'NAB Utilization Checker';
    $templates['nab-credit-report']  = 'NAB Credit Report Access';
    $templates['nab-dispute-center'] = 'NAB Credit Dispute Center';
    $templates['nab-loan']           = 'NAB Auto Loan Tool';
    $templates['nab-card-match']     = 'NAB Credit Card Matcher';
    $templates['nab-pad']            = 'NAB PAD Agreement';
    $templates['nab-chatbot']        = 'NAB AI Chatbot';
    $templates['nab-support']        = 'NAB Support Center';
    $templates['nab-learning']       = 'NAB Learning Center';
    $templates['nab-plans']          = 'NAB Membership Plans';
    $templates['nab-emergency-fund'] = 'NAB Emergency Fund Planner';
    $templates['nab-roadmap']        = 'NAB Financial Roadmap';
    return $templates;
} );

add_filter( 'template_include', function( $template ) {
    if ( ! is_page() ) return $template;
    $slug = get_post_meta( get_the_ID(), '_wp_page_template', true );
    $map  = [
        'nab-dashboard'      => 'page-nab-dashboard.php',
        'nab-simulator'      => 'page-nab-simulator.php',
        'nab-utilization'    => 'page-utilization-checker.php',
        'nab-credit-report'  => 'credit-report-template.php',
        'nab-dispute-center' => 'dispute-center-template.php',
        'nab-loan'           => 'page-nab-loan.php',
        'nab-card-match'     => 'page-nab-card-match.php',
        'nab-pad'            => 'page-nab-pad.php',
        'nab-chatbot'        => 'page-nab-chatbot.php',
        'nab-support'        => 'page-nab-support.php',
        'nab-learning'       => 'page-nab-learning.php',
        'nab-lfp'            => 'page-nab-lfp.php',
        'nab-plans'          => 'page-membership-plans.php',
        'nab-emergency-fund' => 'page-nab-emergency-fund.php',
        'nab-roadmap'        => 'page-nab-roadmap.php',
    ];
    if ( isset( $map[ $slug ] ) ) {
        $file = NAB_DIR . 'templates/' . $map[ $slug ];
        if ( file_exists( $file ) ) return $file;
    }
    return $template;
} );