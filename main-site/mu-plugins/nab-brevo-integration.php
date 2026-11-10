<?php
/**
 * NAB Solutions — Gravity Forms → Brevo + Portal Account Creation
 * Upload to: nabsolutions.ca/wp-content/mu-plugins/nab-brevo-integration.php
 * 
 * Sends ONE welcome email to the member with their portal login.
 *
 * Updated Nov 2026:
 *  - No "New Member Signed Up" copy to admin (Gravity Forms already sends the
 *    admin notification), to save Brevo credits.
 *  - If the email already has a portal account, the email no longer sends a new
 *    password (it never worked); it shows their username and a reset link.
 *  - If the portal account could not be created, the email says the login is on
 *    its way, and admin@ gets an alert to create it by hand.
 */

if ( ! defined( 'ABSPATH' ) ) exit;

if ( ! defined( 'NAB_BREVO_API_KEY' ) ) define( 'NAB_BREVO_API_KEY', 'PASTE-YOUR-BREVO-KEY-HERE' ); // copy the key from line 11 of your old file
// Warn in wp-admin if the key above was not filled in (sign up emails would not send).
add_action( 'admin_notices', function () {
    if ( NAB_BREVO_API_KEY === 'PASTE-YOUR-BREVO-KEY-HERE' && current_user_can( 'manage_options' ) ) {
        echo '<div class="notice notice-error"><p><strong>NAB sign up emails are off:</strong> add the Brevo API key on line 19 of wp-content/mu-plugins/nab-brevo-integration.php.</p></div>';
    }
} );
if ( ! defined( 'NAB_PORTAL_URL' ) )    define( 'NAB_PORTAL_URL',    'https://members.nabsolutions.ca' );

// ─── MAIN HOOK ───────────────────────────────────────────────────────────────
add_action( 'gform_after_submission_3', 'nab_handle_signup_submission', 10, 2 );
add_action( 'gform_after_submission_12', 'nab_handle_signup_submission', 10, 2 );

function nab_handle_signup_submission( $entry, $form ) {

    $first_name  = rgar( $entry, '36.3' );
    $last_name   = rgar( $entry, '36.6' );
    $email       = rgar( $entry, '9' );
    $phone       = rgar( $entry, '11' );
    $dob         = rgar( $entry, '10' );
    $gender      = rgar( $entry, '23' );
    $street      = rgar( $entry, '40.1' );
    $city        = rgar( $entry, '40.3' );
    $province    = rgar( $entry, '40.4' );
    $postal      = rgar( $entry, '40.5' );
    $bank_name   = rgar( $entry, '32' );
    $institution = rgar( $entry, '37' );
    $transit     = rgar( $entry, '38' );
    $account     = rgar( $entry, '39' );

    if ( empty( $email ) ) {
        nab_log( 'No email in entry ' . $entry['id'] );
        return;
    }

    $full_name     = trim( $first_name . ' ' . $last_name );
    $address       = trim( $street . ', ' . $city . ', ' . $province . ' ' . $postal );
    $temp_password = wp_generate_password( 10, false );
    $username      = nab_generate_username( $first_name, $last_name, $email );
    $date_today    = date( 'F j, Y' );

    nab_log( "Processing signup: {$email}" );

    // 1. Create WP user on portal
    $result = nab_create_portal_user([
        'email'       => $email,
        'username'    => $username,
        'password'    => $temp_password,
        'first_name'  => $first_name,
        'last_name'   => $last_name,
        'phone'       => $phone,
        'dob'         => $dob,
        'address'     => $address,
        'bank_name'   => $bank_name,
        'institution' => $institution,
        'transit'     => $transit,
        'account'     => $account,
    ]);

    if ( ! empty( $result['username'] ) ) {
        $username = $result['username'];
    }

    nab_log( "Portal result: " . json_encode( $result ) );

    // 2. Add to Brevo contacts
    nab_add_to_brevo( $email, $first_name, $last_name, $phone, $dob, $gender, $address );

    // What the portal said: 'created' (new account, this password works),
    // 'existing' (account already there, password unchanged) or 'failed'.
    if ( ! empty( $result['existing'] ) ) {
        $portal_status = 'existing';
    } elseif ( ! empty( $result['success'] ) ) {
        $portal_status = 'created';
    } else {
        $portal_status = 'failed';
        nab_alert_admin_portal_failed( $full_name, $email, $result );
    }

    // 3. Send ONE welcome email with the portal login
    nab_send_welcome_with_pad( $email, $full_name, $username, $temp_password, $portal_status, [
        'name'        => $full_name,
        'email'       => $email,
        'address'     => $address,
        'dob'         => $dob,
        'date'        => $date_today,
        'bank_name'   => $bank_name,
        'institution' => $institution,
        'transit'     => $transit,
        'account'     => $account,
    ]);

    nab_log( "Done: {$email}" );
}

// ─── GENERATE USERNAME ────────────────────────────────────────────────────────
function nab_generate_username( $first, $last, $email ) {
    $base = sanitize_user( strtolower( preg_replace( '/\s+/', '', $first . $last ) ), true );
    if ( empty( $base ) ) $base = sanitize_user( strstr( $email, '@', true ), true );
    return $base . rand( 10, 99 );
}

// ─── CREATE PORTAL USER ───────────────────────────────────────────────────────
function nab_create_portal_user( $data ) {
    $response = wp_remote_post( NAB_PORTAL_URL . '/wp-json/nab/v1/create-member', [
        'timeout'   => 30,
        'sslverify' => false,
        'headers'   => [
            'Content-Type' => 'application/json',
            'X-NAB-Secret' => 'nab_portal_secret_2024',
        ],
        'body' => json_encode( $data ),
    ]);
    if ( is_wp_error( $response ) ) {
        nab_log( 'Portal error: ' . $response->get_error_message() );
        return [ 'success' => false ];
    }
    $body = json_decode( wp_remote_retrieve_body( $response ), true );
    nab_log( "Portal response " . wp_remote_retrieve_response_code( $response ) );
    return $body ?: [ 'success' => false ];
}

// ─── ADD TO BREVO ─────────────────────────────────────────────────────────────
function nab_add_to_brevo( $email, $first, $last, $phone, $dob, $gender, $address ) {
    $response = wp_remote_post( 'https://api.brevo.com/v3/contacts', [
        'timeout'   => 20,
        'sslverify' => false,
        'headers'   => [
            'api-key'      => NAB_BREVO_API_KEY,
            'Content-Type' => 'application/json',
            'Accept'       => 'application/json',
        ],
        'body' => json_encode([
            'email'         => $email,
            'updateEnabled' => true,
            'attributes'    => [
                'FIRSTNAME' => $first,
                'LASTNAME'  => $last,
                'SMS'       => $phone,
                'DOB'       => $dob,
                'GENDER'    => $gender,
                'ADDRESS'   => $address,
            ],
            'listIds' => [ 2 ],
        ]),
    ]);
    nab_log( "Brevo contact: " . wp_remote_retrieve_response_code( $response ) );
}

// ─── SEND WELCOME EMAIL + PAD ATTACHED ───────────────────────────────────────
function nab_send_welcome_with_pad( $email, $name, $username, $password, $portal_status, $pad_data ) {
    $login_url = NAB_PORTAL_URL . '/wp-login.php?redirect_to=' . urlencode( NAB_PORTAL_URL . '/dashboard/' );
    $reset_url = NAB_PORTAL_URL . '/wp-login.php?action=lostpassword';
    $user_html = esc_html( $username );
    $pass_html = esc_html( $password );

    // The login box depends on what the portal did.
    if ( $portal_status === 'existing' ) {
        $login_box = "
          <h3 style='color:#0D5C9B;margin-top:0;'>Your Login Details</h3>
          <p>You already have a member portal account, so your password has not changed.</p>
          <p><strong>Login URL:</strong> <a href='{$login_url}'>members.nabsolutions.ca/dashboard</a></p>
          <p><strong>Username:</strong> <code style='background:#f0f0f0;padding:2px 6px;border-radius:4px;'>{$user_html}</code></p>
          <p>Forgot your password? <a href='{$reset_url}'>Reset it here</a>.</p>";
    } elseif ( $portal_status === 'failed' ) {
        $login_box = "
          <h3 style='color:#0D5C9B;margin-top:0;'>Your Login Details</h3>
          <p>We're finishing setting up your member portal. You'll get your login details in a separate email shortly.</p>";
    } else {
        $login_box = "
          <h3 style='color:#0D5C9B;margin-top:0;'>Your Login Credentials</h3>
          <p><strong>Login URL:</strong> <a href='{$login_url}'>members.nabsolutions.ca/dashboard</a></p>
          <p><strong>Username:</strong> <code style='background:#f0f0f0;padding:2px 6px;border-radius:4px;'>{$user_html}</code></p>
          <p><strong>Password:</strong> <code style='background:#fff3cd;padding:2px 6px;border-radius:4px;color:#e74c3c;'>{$pass_html}</code></p>";
    }

    $html = "
    <div style='font-family:Arial,sans-serif;max-width:600px;margin:0 auto;border:1px solid #e0e0e0;border-radius:8px;overflow:hidden;'>
      <div style='background:#0D5C9B;padding:30px;text-align:center;'>
        <h1 style='color:#fff;margin:0;font-size:22px;'>Welcome to NAB Solutions!</h1>
      </div>
      <div style='padding:30px;background:#ffffff;'>
        <p style='font-size:16px;'>Dear <strong>{$name}</strong>,</p>
        <p style='font-size:15px;'>Welcome to NAB Solutions membership program!</p>
        <p style='font-size:14px;color:#444;'>Thanks for signing up. Our goal is simple: to help you take control of your credit and build a stronger financial future, one step at a time.</p>

        <div style='background:#fff;border:2px solid #0D5C9B;border-radius:8px;padding:20px;margin:20px 0;'>{$login_box}
        </div>

        <h3 style='color:#0D5C9B;'>Here is what your membership gives you access to:</h3>
        <p style='font-size:14px;color:#444;'><strong>Credit help</strong> - A personalized action plan, plus support disputing errors on your credit report (or DIY templates if you would rather handle it yourself).</p>
        <p style='font-size:14px;color:#444;'><strong>Lender Finder Program and Credit Card matching</strong> - We can connect you with lenders and credit cards that fit your situation, including auto financing.</p>
        <p style='font-size:14px;color:#444;'><strong>Learn as you go</strong> - Short videos covering credit basics, budgeting, and protecting yourself from identity theft.</p>
        <p style='font-size:14px;color:#444;'><strong>Your roadmap</strong> - A simple, personalized plan showing what to focus on first.</p>
        <p style='font-size:14px;color:#444;'><strong>Track your progress</strong> - Tools to monitor your credit score and manage everything yourself, whenever you want.</p>
        <p style='font-size:14px;color:#444;'><strong>AI NAB Bot</strong> - Get 24/7 instant answers for your credit questions and portal navigation.</p>
        <p style='font-size:14px;color:#444;'><strong>Support when you need it</strong> - A member portal where you can message us, upload documents, and track any requests.</p>

        <div style='text-align:center;margin:28px 0;'>
          <a href='{$login_url}' style='background:#FF6B35;color:#fff;padding:14px 32px;border-radius:8px;text-decoration:none;font-size:16px;font-weight:bold;display:inline-block;'>Access Your Member Portal</a>
        </div>

        <p style='font-size:14px;color:#444;'>Please review the following:</p>
       
        <p style='font-size:14px;color:#444;'>Your membership welcome packet here: <a href='https://nabsolutions.ca/clientwelcomepacket'>https://nabsolutions.ca/clientwelcomepacket</a></p>
        <p style='font-size:14px;color:#444;'>If you have questions, please reach out to us at <strong>1-855-542-6078</strong> any day from 9AM to 3PM mountain time via <a href='mailto:admin@nabsolutions.ca'>admin@nabsolutions.ca</a>.</p>
        <p style='font-size:14px;color:#444;'>Thank you for being a valued client of NAB Solutions.</p>
        <p style='font-size:14px;'><strong>Team NAB Solutions Ltd.</strong><br>admin@nabsolutions.ca<br>1-855-542-6078</p>
      </div>
      <div style='background:#f8f8f8;padding:16px;text-align:center;border-top:1px solid #e0e0e0;'>
        <p style='font-size:11px;color:#888;margin:0;'>Please note: NAB Solutions is not a lender, credit bureau, or financial advisor. Any loan or credit decisions are made by independent third-party providers. For billing questions, reach us at billing@nabsolutions.ca.</p>
      </div>
      <div style='background:#0D5C9B;padding:14px;text-align:center;'>
        <p style='color:#fff;margin:0;font-size:12px;'>NAB Solutions Ltd. | Suite 290, 6815-8 Street NE, Calgary, Alberta T2E 7H7</p>
      </div>
    </div>";

    // Generate PAD as plain text attachment (no binary corruption)
   // $pad_text = nab_generate_pad_text( $pad_data );
   // $pad_b64  = base64_encode( $pad_text );
  //  $pad_name = 'NAB-Membership-Agreement-' . sanitize_file_name( $pad_data['name'] ) . '.txt'; 

    // Send ONE email to member with PAD attached
    $response = wp_remote_post( 'https://api.brevo.com/v3/smtp/email', [
        'timeout'   => 30,
        'sslverify' => false,
        'headers'   => [
            'api-key'      => NAB_BREVO_API_KEY,
            'Content-Type' => 'application/json',
            'Accept'       => 'application/json',
        ],
        'body' => json_encode([
            'sender'      => [ 'name' => 'NAB Solutions', 'email' => 'noreply@nabsolutions.ca' ],
            'to'          => [ [ 'email' => $email, 'name' => $name ] ],
            'subject'     => 'Welcome to NAB Solutions — Your Portal Access',
            'htmlContent' => $html,
            
        ]),
    ]);

    $code = wp_remote_retrieve_response_code( $response );
    nab_log( "Welcome email to member ({$portal_status}): {$code}" );

}

// ─── ALERT ADMIN WHEN THE PORTAL ACCOUNT WAS NOT CREATED ─────────────────────
// Only sent when something went wrong, so a member is never left without a login.
function nab_alert_admin_portal_failed( $name, $email, $result ) {
    $why = is_array( $result ) && ! empty( $result['message'] ) ? $result['message'] : 'No answer from the portal';
    wp_remote_post( 'https://api.brevo.com/v3/smtp/email', [
        'timeout'   => 30,
        'headers'   => [
            'api-key'      => NAB_BREVO_API_KEY,
            'Content-Type' => 'application/json',
            'Accept'       => 'application/json',
        ],
        'body' => json_encode([
            'sender'      => [ 'name' => 'NAB Portal', 'email' => 'noreply@nabsolutions.ca' ],
            'to'          => [ [ 'email' => 'admin@nabsolutions.ca', 'name' => 'NAB Admin' ] ],
            'subject'     => 'Action needed: portal account not created for ' . $name,
            'htmlContent' => '<p>A new member signed up, but their member portal account could not be created.</p>'
                . '<p><strong>' . esc_html( $name ) . '</strong> (' . esc_html( $email ) . ')</p>'
                . '<p>Reason: ' . esc_html( $why ) . '</p>'
                . '<p>Please create their account in members.nabsolutions.ca (Users, then Add New) and use "Resend Set-Password Email" on their profile so they get their login.</p>',
        ]),
    ]);
    nab_log( "Portal account failed for {$email}: {$why}" );
}

// ─── GENERATE PAD AS PLAIN TEXT ──────────────────────────────────────────────
function nab_generate_pad_text( $d ) {
    $date = $d['date'];
    return "NAB SOLUTIONS LTD. - MEMBERSHIP AGREEMENT
==========================================
Date: {$date}

MEMBER DETAILS
--------------
Name:             {$d['name']}
Email:            {$d['email']}
Mailing Address:  {$d['address']}
Date of Birth:    {$d['dob']}

SCHEDULE A - PRE-AUTHORIZED DEBIT (PAD) DETAILS
-------------------------------------------------
Bank Name:            {$d['bank_name']}
Institution Number:   {$d['institution']}
Transit Number:       {$d['transit']}
Account Number:       {$d['account']}

AGREEMENT
---------
The Member agrees to all terms and conditions of the NAB Solutions
Membership Agreement as published at nabsolutions.ca/membership-agreement

The Member authorizes NAB Solutions Ltd. to process pre-authorized
payments as described in the Membership Agreement.

Signed electronically on {$date}
Member: {$d['name']}

NAB SOLUTIONS INC.
Suite 290, 6815-8 Street NE
Calgary, Alberta T2E 7H7
admin@nabsolutions.ca | 1-855-542-6078
";
}

// ─── LOGGING ──────────────────────────────────────────────────────────────────
function nab_log( $msg ) {
    error_log( '[NAB Signup] ' . $msg );
}
