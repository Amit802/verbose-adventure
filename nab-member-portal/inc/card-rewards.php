<?php
/**
 * NAB Member Portal — Card Rewards Checker (v1.24.0)
 *
 * The member picks the credit cards they already have (names only, never a
 * card number), searches a store and enters an amount. The page shows which
 * of their cards earns the most there, how it was worked out, and other cards
 * that would earn more at that store.
 *
 * Data
 * - Card earn rates: nab_rewards_cards(). Taken from each issuer's own
 *   website on the date in nab_rewards_checked_date(). Rates change, so check
 *   them every few months and update the date. Filter: nab_rewards_cards.
 * - Stores: nab_rewards_merchants() (store name → category, brand, which
 *   networks it takes). Filter: nab_rewards_merchants.
 * - The member's cards: user meta nab_reward_cards (JSON), saved through the
 *   nab_rewards_save AJAX action.
 * - All maths runs in the browser (assets/js/nab-card-rewards.js).
 *
 * Affiliate links: a card may carry 'apply_url' (filter nab_rewards_cards).
 * When set, the "Cards that would earn more here" list shows an Apply button
 * marked as a paid link. The order of the cards never depends on it: cards
 * are ranked only by what they earn.
 *
 * @package NAB_Member_Portal
 * @since   1.24.0
 */
if ( ! defined( 'ABSPATH' ) ) exit;

/** Date the earn rates were last checked against the issuers' websites (Y-m-d). */
function nab_rewards_checked_date() {
    return apply_filters( 'nab_rewards_checked_date', '2026-11-26' );
}

/** Spending categories. 'pick' = offered as a "type of store" when a store isn't listed. */
function nab_rewards_categories() {
    return apply_filters( 'nab_rewards_categories', [
        'grocery'       => [ 'label' => 'Groceries',                    'icon' => 'shopping-cart' ],
        'dining'        => [ 'label' => 'Restaurants and food delivery', 'icon' => 'receipt' ],
        'gas'           => [ 'label' => 'Gas and EV charging',          'icon' => 'car' ],
        'drugstore'     => [ 'label' => 'Drugstores',                   'icon' => 'heart-pulse' ],
        'transit'       => [ 'label' => 'Transit, taxis and rideshare', 'icon' => 'route' ],
        'streaming'     => [ 'label' => 'Streaming and digital media',  'icon' => 'play' ],
        'recurring'     => [ 'label' => 'Bills paid automatically',     'icon' => 'calendar' ],
        'entertainment' => [ 'label' => 'Movies and events',            'icon' => 'star' ],
        'home'          => [ 'label' => 'Home improvement',             'icon' => 'house' ],
        'furniture'     => [ 'label' => 'Furniture',                    'icon' => 'house' ],
        'other'         => [ 'label' => 'Everything else',              'icon' => 'shopping-bag' ],
    ] );
}

/**
 * Canadian credit cards and how they earn.
 *
 *   network  visa | mastercard | amex
 *   fee      annual fee in dollars
 *   kind     cash (rates are %) | points (rates are points per $1)
 *   unit     name of the points, cpp = what one point is worth in cents
 *   base     rate on everything else
 *   cats     rate by category (keys of nab_rewards_categories())
 *   brands   rate at a group of stores, beats cats (keys used in nab_rewards_merchants())
 *   choose   member picks some categories: n, max (with a condition), rate, options, note
 *   caps     short note shown when the rate has a spending limit, by category or brand
 *   note     one line shown under the card
 *   url      the issuer's page the rates came from
 */
function nab_rewards_cards() {
    static $cards = null;
    if ( $cards !== null ) return $cards;
    $cards = apply_filters( 'nab_rewards_cards', [
        'cibc-dividend-vi' => [
            'name' => 'CIBC Dividend Visa Infinite', 'issuer' => 'CIBC', 'network' => 'visa', 'fee' => 120, 'kind' => 'cash',
            'base' => 1, 'cats' => [ 'grocery' => 4, 'gas' => 4, 'transit' => 2, 'dining' => 2, 'recurring' => 2 ],
            'url'  => 'https://www.cibc.com/en/personal-banking/credit-cards/all-credit-cards/dividend-visa-infinite-card.html',
        ],
        'cibc-dividend' => [
            'name' => 'CIBC Dividend Visa', 'issuer' => 'CIBC', 'network' => 'visa', 'fee' => 0, 'kind' => 'cash',
            'base' => 0.5, 'cats' => [ 'grocery' => 2, 'gas' => 1, 'transit' => 1, 'dining' => 1, 'recurring' => 1 ],
            'url'  => 'https://www.cibc.com/en/personal-banking/credit-cards/all-credit-cards/dividend-visa-card.html',
        ],
        'cibc-costco' => [
            'name' => 'CIBC Costco Mastercard', 'issuer' => 'CIBC', 'network' => 'mastercard', 'fee' => 0, 'kind' => 'cash',
            'base' => 1, 'cats' => [ 'dining' => 3, 'gas' => 2 ], 'brands' => [ 'costco_gas' => 3, 'costco_online' => 2, 'costco' => 1 ],
            'note' => 'No annual fee with a Costco membership.',
            'url'  => 'https://www.cibc.com/en/personal-banking/credit-cards/all-credit-cards/costco-mastercard.html',
        ],
        'simplii-cashback' => [
            'name' => 'Simplii Financial Cash Back Visa', 'issuer' => 'Simplii Financial', 'network' => 'visa', 'fee' => 0, 'kind' => 'cash',
            'base' => 0.5, 'cats' => [ 'dining' => 4, 'gas' => 1.5, 'grocery' => 1.5, 'drugstore' => 1.5, 'recurring' => 1.5 ],
            'caps' => [
                'dining'    => '4% on up to $5,000 a year at restaurants, bars and coffee shops, then 0.5%.',
                'gas'       => '1.5% on up to $15,000 a year in gas, groceries, drugstores and pre-authorized payments together, then 0.5%.',
                'grocery'   => '1.5% on up to $15,000 a year in gas, groceries, drugstores and pre-authorized payments together, then 0.5%.',
                'drugstore' => '1.5% on up to $15,000 a year in gas, groceries, drugstores and pre-authorized payments together, then 0.5%.',
                'recurring' => '1.5% on up to $15,000 a year in gas, groceries, drugstores and pre-authorized payments together, then 0.5%.',
            ],
            'url'  => 'https://www.simplii.com/en/credit-cards/cash-back-visa.html',
        ],
        'tangerine-moneyback' => [
            'name' => 'Tangerine Money-Back Credit Card', 'issuer' => 'Tangerine', 'network' => 'mastercard', 'fee' => 0, 'kind' => 'cash',
            'base' => 0.5,
            'choose' => [
                'n' => 2, 'max' => 3, 'rate' => 2,
                'options' => [ 'grocery', 'dining', 'gas', 'drugstore', 'recurring', 'transit', 'entertainment', 'home', 'furniture' ],
                'note' => 'You get 2% in the categories you chose with Tangerine: 2 categories, or 3 if your cash back goes into a Tangerine Savings Account.',
            ],
            'url'  => 'https://www.tangerine.ca/en/personal/spend/credit-cards/money-back-credit-card',
        ],
        'scotia-momentum-vi' => [
            'name' => 'Scotia Momentum Visa Infinite', 'issuer' => 'Scotiabank', 'network' => 'visa', 'fee' => 120, 'kind' => 'cash',
            'base' => 1, 'cats' => [ 'grocery' => 4, 'recurring' => 4, 'gas' => 2, 'transit' => 2 ],
            'caps' => [
                'grocery'   => '4% on up to $25,000 a year in groceries and recurring payments together, then 1%.',
                'recurring' => '4% on up to $25,000 a year in groceries and recurring payments together, then 1%.',
                'gas'       => '2% on up to $25,000 a year in gas and daily transit together, then 1%.',
                'transit'   => '2% on up to $25,000 a year in gas and daily transit together, then 1%.',
            ],
            'url'  => 'https://www.scotiabank.com/ca/en/personal/credit-cards/visa/momentum-infinite-card.html',
        ],
        'scotia-momentum-nofee' => [
            'name' => 'Scotia Momentum No-Fee Visa', 'issuer' => 'Scotiabank', 'network' => 'visa', 'fee' => 0, 'kind' => 'cash',
            'base' => 0.5, 'cats' => [ 'grocery' => 1, 'drugstore' => 1, 'gas' => 1, 'transit' => 1, 'recurring' => 1, 'streaming' => 1 ],
            'caps' => array_fill_keys( [ 'grocery', 'drugstore', 'gas', 'transit', 'recurring', 'streaming' ], '1% on up to $15,000 a year in these categories together, then 0.5%.' ),
            'url'  => 'https://www.scotiabank.com/ca/en/personal/credit-cards/visa/momentum-no-fee-card.html',
        ],
        'scotia-gold-amex' => [
            'name' => 'Scotiabank Gold American Express', 'issuer' => 'Scotiabank', 'network' => 'amex', 'fee' => 120, 'kind' => 'points', 'unit' => 'Scene+ points', 'cpp' => 1,
            'base' => 1, 'cats' => [ 'grocery' => 5, 'dining' => 5, 'entertainment' => 5, 'gas' => 3, 'transit' => 3, 'streaming' => 3 ],
            'brands' => [ 'sobeys' => 6 ],
            'note' => 'No foreign transaction fees.',
            'url'  => 'https://www.scotiabank.com/ca/en/personal/credit-cards/american-express/gold-card.html',
        ],
        'td-cashback-vi' => [
            'name' => 'TD Cash Back Visa Infinite', 'issuer' => 'TD', 'network' => 'visa', 'fee' => 139, 'kind' => 'cash',
            'base' => 1, 'cats' => [ 'grocery' => 3, 'gas' => 3, 'transit' => 3, 'recurring' => 3, 'streaming' => 3 ],
            'url'  => 'https://www.td.com/ca/en/personal-banking/products/credit-cards/cash-back/cash-back-visa-infinite-card',
        ],
        'td-cashback' => [
            'name' => 'TD Cash Back Visa', 'issuer' => 'TD', 'network' => 'visa', 'fee' => 0, 'kind' => 'cash',
            'base' => 0.5, 'cats' => [ 'grocery' => 1, 'gas' => 1, 'transit' => 1, 'recurring' => 1, 'streaming' => 1 ],
            'url'  => 'https://www.td.com/ca/en/personal-banking/products/credit-cards/cash-back/cash-back-visa-card',
        ],
        'rbc-cashback' => [
            'name' => 'RBC Cash Back Mastercard', 'issuer' => 'RBC', 'network' => 'mastercard', 'fee' => 0, 'kind' => 'cash',
            'base' => 0.5, 'cats' => [ 'grocery' => 2, 'gas' => 1, 'transit' => 1 ],
            'url'  => 'https://www.rbcroyalbank.com/credit-cards/cash-back/rbc-cashback-mastercard.html',
        ],
        'amex-cobalt' => [
            'name' => 'American Express Cobalt', 'issuer' => 'American Express', 'network' => 'amex', 'fee' => 191.88, 'kind' => 'points', 'unit' => 'Membership Rewards points', 'cpp' => 1,
            'base' => 1, 'cats' => [ 'dining' => 5, 'grocery' => 5, 'streaming' => 3, 'gas' => 2, 'transit' => 2 ],
            'caps' => [
                'dining'  => '5 points per $1 on up to $2,500 a month in restaurants, groceries and food delivery together, then 1.',
                'grocery' => '5 points per $1 on up to $2,500 a month in restaurants, groceries and food delivery together, then 1. Counts at stand-alone grocery stores.',
            ],
            'note' => 'Fee is $15.99 a month. Points counted at 1 cent each (1,000 points = $10 off a purchase).',
            'url'  => 'https://www.americanexpress.com/en-ca/credit-cards/cobalt-card/',
        ],
        'pc-mastercard' => [
            'name' => 'PC Mastercard', 'issuer' => 'PC Financial', 'network' => 'mastercard', 'fee' => 0, 'kind' => 'points', 'unit' => 'PC Optimum points', 'cpp' => 0.1,
            'base' => 10, 'brands' => [ 'shoppers' => 25 ],
            'note' => '1,000 PC Optimum points = $1.',
            'url'  => 'https://www.pcfinancial.ca/en/credit-cards/pc-mastercard/',
        ],
        'pc-world' => [
            'name' => 'PC World Mastercard', 'issuer' => 'PC Financial', 'network' => 'mastercard', 'fee' => 0, 'kind' => 'points', 'unit' => 'PC Optimum points', 'cpp' => 0.1,
            'base' => 10, 'brands' => [ 'loblaw' => 20, 'shoppers' => 35 ],
            'note' => '1,000 PC Optimum points = $1.',
            'url'  => 'https://www.pcfinancial.ca/en/credit-cards/world-mastercard/',
        ],
        'pc-world-elite' => [
            'name' => 'PC World Elite Mastercard', 'issuer' => 'PC Financial', 'network' => 'mastercard', 'fee' => 0, 'kind' => 'points', 'unit' => 'PC Optimum points', 'cpp' => 0.1,
            'base' => 10, 'brands' => [ 'loblaw' => 30, 'shoppers' => 45, 'esso' => 20 ],
            'caps' => [ 'esso' => 'At Esso and Mobil you get at least 30 points per litre. We count that as 20 points per $1, at $1.50 a litre.' ],
            'note' => '1,000 PC Optimum points = $1.',
            'url'  => 'https://www.pcfinancial.ca/en/credit-cards/world-elite/',
        ],
    ] );
    return $cards;
}

/**
 * Stores members can search. cat = category, brand = store group used by
 * card 'brands' rules, only = the only card networks the store takes,
 * tip = short note shown with the result.
 */
function nab_rewards_merchants() {
    static $list = null;
    if ( $list !== null ) return $list;
    $big_box = 'Most cards count Walmart and Costco as general stores, not grocery stores, so grocery rates usually do not apply here.';
    $costco  = 'Costco stores in Canada take Mastercard only (and debit).';
    $bills   = 'Bill rates only count when the bill is charged to your card automatically every month.';
    $m = [];
    $add = function ( $names, $cat, $extra = [] ) use ( &$m ) {
        foreach ( (array) $names as $n ) $m[] = array_merge( [ 'name' => $n, 'cat' => $cat ], $extra );
    };
    $add( [ 'Loblaws', 'No Frills', 'Real Canadian Superstore', 'Zehrs', 'Fortinos', 'Provigo', 'Maxi', 'Your Independent Grocer', 'Valu-mart', 'Atlantic Superstore', 'Dominion' ], 'grocery', [ 'brand' => 'loblaw' ] );
    $add( [ 'Sobeys', 'IGA', 'Safeway', 'FreshCo', 'Foodland', 'Thrifty Foods' ], 'grocery', [ 'brand' => 'sobeys' ] );
    $add( [ 'Metro', 'Food Basics', 'Save-On-Foods', "Longo's", 'Farm Boy', 'T&T Supermarket', 'Co-op Food', 'Whole Foods Market', 'Adonis', 'Super C' ], 'grocery' );
    $add( 'Walmart', 'other', [ 'tip' => $big_box ] );
    $add( 'Costco', 'other', [ 'brand' => 'costco', 'only' => [ 'mastercard' ], 'tip' => $big_box . ' ' . $costco ] );
    $add( 'Costco.ca', 'other', [ 'brand' => 'costco_online' ] );
    $add( 'Costco Gas', 'gas', [ 'brand' => 'costco_gas', 'only' => [ 'mastercard' ], 'tip' => $costco ] );
    $add( [ 'Esso', 'Mobil' ], 'gas', [ 'brand' => 'esso' ] );
    $add( [ 'Shell', 'Petro-Canada', 'Canadian Tire Gas+', 'Husky', 'Ultramar', 'Chevron', 'Pioneer', 'Circle K', 'Irving', 'Co-op Gas', 'Tesla Supercharger', 'FLO EV charging' ], 'gas' );
    $add( [ 'Shoppers Drug Mart', 'Pharmaprix' ], 'drugstore', [ 'brand' => 'shoppers' ] );
    $add( [ 'Rexall', 'Jean Coutu', 'London Drugs', 'Guardian Pharmacy', 'Uniprix', 'Familiprix' ], 'drugstore' );
    $add( [ 'Tim Hortons', "McDonald's", 'Starbucks', 'A&W', 'Subway', 'Boston Pizza', 'Pizza Pizza', "Swiss Chalet", "Harvey's", "Wendy's", 'Burger King', 'KFC', 'Popeyes', "Mary Brown's", 'The Keg', 'Earls', 'Any restaurant or coffee shop', 'Uber Eats', 'DoorDash', 'SkipTheDishes' ], 'dining' );
    $add( [ 'Uber', 'Lyft', 'PRESTO', 'TTC', 'OC Transpo', 'Compass Card (TransLink)', 'STM', 'Calgary Transit', 'Edmonton Transit', 'Taxi', 'Parking' ], 'transit' );
    $add( [ 'Netflix', 'Spotify', 'Disney+', 'Crave', 'Apple Music', 'YouTube Premium', 'Amazon Prime Video', 'Apple TV+' ], 'streaming' );
    $add( [ 'Phone bill (Rogers, Bell, Telus)', 'Internet bill', 'Hydro bill', 'Home heating bill', 'Car insurance', 'Gym membership' ], 'recurring', [ 'tip' => $bills ] );
    $add( [ 'Cineplex', 'Ticketmaster', 'Landmark Cinemas' ], 'entertainment' );
    $add( [ 'Home Depot', 'RONA', "Lowe's", 'Home Hardware', 'Kent' ], 'home' );
    $add( [ 'IKEA', 'Structube', 'The Brick', "Leon's", 'Sleep Country' ], 'furniture' );
    $add( [ 'Amazon.ca', 'Canadian Tire', 'Best Buy', 'Dollarama', 'Winners', 'Sport Chek', 'Indigo', 'Apple Store', "Mark's", 'Staples', 'Giant Tiger' ], 'other' );
    $list = apply_filters( 'nab_rewards_merchants', $m );
    return $list;
}

/** Stores shown as one-click buttons. */
function nab_rewards_popular() {
    return apply_filters( 'nab_rewards_popular', [ 'Walmart', 'Costco', 'Loblaws', 'No Frills', 'Shell', 'Tim Hortons', 'Shoppers Drug Mart', 'Amazon.ca' ] );
}

/** The member's saved cards, cleaned: [ 'cards' => [ [id, picks] ], 'custom' => [ [id, name, network, rate] ] ]. */
function nab_rewards_get_member( $uid ) {
    $raw = json_decode( (string) get_user_meta( $uid, 'nab_reward_cards', true ), true );
    return nab_rewards_clean( is_array( $raw ) ? $raw : [] );
}

/** Keeps only known cards, valid picks and sensible custom cards. */
function nab_rewards_clean( $in ) {
    $catalog = nab_rewards_cards();
    $out = [ 'cards' => [], 'custom' => [] ];
    $seen = [];
    foreach ( array_slice( (array) ( $in['cards'] ?? [] ), 0, 25 ) as $c ) {
        $id = sanitize_key( is_array( $c ) ? ( $c['id'] ?? '' ) : $c );
        if ( ! isset( $catalog[ $id ] ) || isset( $seen[ $id ] ) ) continue;
        $seen[ $id ] = true;
        $picks = [];
        if ( ! empty( $catalog[ $id ]['choose'] ) && is_array( $c ) ) {
            $ch = $catalog[ $id ]['choose'];
            foreach ( (array) ( $c['picks'] ?? [] ) as $p ) {
                $p = sanitize_key( $p );
                if ( in_array( $p, $ch['options'], true ) && ! in_array( $p, $picks, true ) ) $picks[] = $p;
            }
            $picks = array_slice( $picks, 0, (int) ( $ch['max'] ?? $ch['n'] ) );
        }
        $out['cards'][] = [ 'id' => $id, 'picks' => $picks ];
    }
    $n = 0;
    foreach ( array_slice( (array) ( $in['custom'] ?? [] ), 0, 5 ) as $c ) {
        if ( ! is_array( $c ) ) continue;
        $name = mb_substr( sanitize_text_field( $c['name'] ?? '' ), 0, 60 );
        if ( $name === '' ) continue;
        $net  = sanitize_key( $c['network'] ?? '' );
        if ( ! in_array( $net, [ 'visa', 'mastercard', 'amex' ], true ) ) $net = 'visa';
        $rate = round( max( 0, min( 10, (float) ( $c['rate'] ?? 0 ) ) ), 2 );
        $out['custom'][] = [ 'id' => 'custom-' . ( ++$n ), 'name' => $name, 'network' => $net, 'rate' => $rate ];
    }
    return $out;
}

/* Save the member's cards. data = JSON { cards:[{id,picks}], custom:[{name,network,rate}] } */
add_action( 'wp_ajax_nab_rewards_save', function () {
    check_ajax_referer( 'nab_portal_nonce', 'nonce' );
    $uid = get_current_user_id();
    if ( ! $uid ) wp_send_json_error( [ 'message' => 'Please log in again.' ] );
    $raw = wp_unslash( $_POST['data'] ?? '' ); // phpcs:ignore -- decoded and cleaned below
    if ( ! is_string( $raw ) || strlen( $raw ) > 20000 ) wp_send_json_error( [ 'message' => 'Your cards could not be saved.' ] );
    $in = json_decode( $raw, true );
    if ( ! is_array( $in ) ) wp_send_json_error( [ 'message' => 'Your cards could not be saved.' ] );
    $clean = nab_rewards_clean( $in );
    update_user_meta( $uid, 'nab_reward_cards', wp_json_encode( $clean ) );
    wp_send_json_success( [ 'message' => 'Saved.', 'saved' => $clean ] );
} );
