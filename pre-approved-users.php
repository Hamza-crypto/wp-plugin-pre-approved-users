<?php
/*
Plugin Name: Pre-approved Email Registration
Description: Only allow user registration if the email is in a pre-approved list.
Version: 1.7
Author: Code Copilot
*/

define('PAER_ENCRYPTION_KEY', 'Mn7dtBT.S/8wz5RU6P@Gm<'); // Replace with your secure key

// Add a menu item for the plugin settings
add_action('admin_menu', 'paer_add_admin_menu');

function paer_add_admin_menu() {
    add_options_page(
        'Pre-approved Email Registration', 
        'Pre-approved Email Registration', 
        'manage_options', 
        'paer', 
        'paer_options_page'
    );
}

// Enqueue JavaScript for AJAX
add_action('admin_enqueue_scripts', 'paer_enqueue_admin_scripts');
function paer_enqueue_admin_scripts($hook) {
    if ($hook != 'settings_page_paer') {
        return;
    }
    wp_enqueue_script('paer-admin-js', plugin_dir_url(__FILE__) . 'paer-admin.js', array('jquery'), null, true);
    wp_localize_script('paer-admin-js', 'paer_ajax', array('ajax_url' => admin_url('admin-ajax.php')));
}

// Display the list of pre-approved emails
function paer_display_preapproved_emails() {
    $preapproved_emails = paer_get_preapproved_emails();
    error_log('Displaying pre-approved emails: ' . print_r($preapproved_emails, true)); // Debug log

    if (!empty($preapproved_emails)) {
        sort($preapproved_emails);

        echo '<table class="widefat fixed" cellspacing="0">
                <thead>
                    <tr>
                        <th>Email Address</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody id="paer-email-list">';

        foreach ($preapproved_emails as $email) {
            $nonce = wp_create_nonce('paer_delete_email_nonce');
            echo '<tr data-email="' . esc_attr($email) . '">
                    <td>' . esc_html($email) . '</td>
                    <td><a href="#" class="paer-delete-email" data-email="' . esc_js($email) . '" data-nonce="' . $nonce . '">Delete</a></td>
                  </tr>';
        }

        echo '  </tbody>
              </table>';
    } else {
        echo '<p>No pre-approved emails found.</p>';
    }
}

// Register settings and fields
add_action('admin_init', 'paer_settings_init');

function paer_settings_init() {
    register_setting('paer_options_group', 'paer_preapproved_emails', 'paer_sanitize_emails');

    add_settings_section(
        'paer_settings_section', 
        'Pre-approved Registration Emails', 
        'paer_settings_section_callback', 
        'paer'
    );

    add_settings_field(
        'paer_textarea', 
        'Add Pre-approved Emails', 
        'paer_textarea_render', 
        'paer', 
        'paer_settings_section'
    );
}

function paer_settings_section_callback() {
    echo 'Please enter a list of email addresses (one email per line) to add to the pre-approved list.';
}

function paer_textarea_render() {
    ?>
    <textarea name="paer_preapproved_emails" rows="10" cols="50" class="large-text"></textarea>
    <?php
}

function paer_options_page() {
    ?>
    <div class="wrap">
        <h1>Pre-approved User Account Email Registration</h1>
        <form method="post" action="options.php">
            <?php 
            if (FALSE === get_option('paer_preapproved_emails') && FALSE === update_option('paer_preapproved_emails',FALSE)) add_option('paer_preapproved_emails',array());
            settings_fields('paer_options_group');
            do_settings_sections('paer');
            ?>
            <input type="submit" class="button-primary" value="Add to Pre-approved Emails">
        </form>
        <h2>Pre-approved Emails List</h2>
        <?php 
        if (isset($_GET['deleted']) && $_GET['deleted'] === 'true') {
            echo '<div class="updated"><p>Email deleted successfully.</p></div>';
        }   
        paer_display_preapproved_emails(); 
        ?>
        <br>
        <button onclick="confirmDeleteEmails()" class="button-primary" style="margin-right: 5px;">Delete all emails from the pre-approved list above</button>
        <button onclick="confirmDeleteSubscribers()" class="button-primary">Delete all 'subscriber' accounts which do not appear in the list above</button>
    </div>
    <script>

        function confirmDeleteSubscribers() {
            if (confirm("Are you sure you want to delete all 'subscriber' accounts which do not appear in the pre-approved list?")) {
                window.location.href = "<?php echo admin_url('admin-post.php?action=paer_delete_subscribers'); ?>";
            }
        }
        
        function confirmDeleteEmails() {
            if (confirm("Are you sure you want to delete all emails from the pre-approved list?")) {
                window.location.href = "<?php echo admin_url('admin-post.php?action=paer_delete_emails'); ?>";
            }
        }
        
    </script>
    <?php
}

// AJAX handler for deleting a single pre-approved email
add_action('wp_ajax_paer_delete_email', 'paer_ajax_delete_email');
function paer_ajax_delete_email() {
    if (isset($_POST['email']) && isset($_POST['nonce']) && wp_verify_nonce($_POST['nonce'], 'paer_delete_email_nonce')) {
        $email_to_delete = sanitize_email($_POST['email']);
        
        $preapproved_emails = paer_get_preapproved_emails();
        $index = array_search($email_to_delete, $preapproved_emails);

        if ($index !== false) {
            error_log('1'); // DEBUG LOG
            unset($preapproved_emails[$index]);
            error_log('2'); // DEBUG LOG
            $preapproved_emails = array_values($preapproved_emails);
            error_log('3'); // DEBUG LOG
            
            $encrypted_emails = array_map('paer_encrypt', $preapproved_emails);
            error_log('4'); // DEBUG LOG
            //update_option('paer_preapproved_emails', $encrypted_emails);
            error_log('5'); // DEBUG LOG
            //wp_cache_flush();
            //error_log('6'); // DEBUG LOG

            // Debugging information
            error_log('Email deleted: ' . $email_to_delete); // DEBUG LOG
            error_log('Updated pre-approved emails: ' . print_r($preapproved_emails, true)); // DEBUG LOG
            $encrypted_emails = array_map('paer_encrypt', $preapproved_emails);
            update_option('paer_preapproved_emails', $encrypted_emails);

            wp_send_json_success(array('email' => $email_to_delete));
        } else {
            wp_send_json_error(array('message' => 'Email not found in pre-approved list'));
        }
    } else {
        wp_send_json_error(array('message' => 'Invalid request'));
    }
}

// Hook into admin_post action to delete pre-approved emails
add_action('admin_post_paer_delete_emails', 'paer_delete_emails');
function paer_delete_emails() {
    delete_option('paer_preapproved_emails');
    wp_cache_flush(); // Clear cache
    wp_redirect(admin_url('options-general.php?page=paer'));
    exit;
}

// Hook into admin_post action
add_action('admin_post_paer_delete_subscribers', 'paer_delete_subscribers');
function paer_delete_subscribers() {
    $preapproved_emails = paer_get_preapproved_emails();

    $subscribers = get_users(array(
        'role' => 'subscriber'
    ));

    foreach ($subscribers as $subscriber) {
        if (!in_array($subscriber->user_email, $preapproved_emails)) {
            wp_delete_user($subscriber->ID);
        }
    }

    wp_redirect(admin_url('options-general.php?page=paer'));
    exit;
}

function paer_sanitize_emails($input) {
    if (is_array($input)) {
        $input = implode("\n", $input);
    }

    $existing_emails = paer_get_preapproved_emails();

    $emails = array_map('trim', explode("\n", $input));
    $valid_emails = array();

    foreach ($emails as $email) {
        if (is_email($email)) {
            $valid_emails[] = sanitize_email($email);
        }
    }

    $merged_emails = array_merge($existing_emails, $valid_emails);

    $unique_emails = array_unique($merged_emails);

    $encrypted_emails = array_map('paer_encrypt', $unique_emails);

    return $encrypted_emails;
}

// Hook into user registration
add_filter('registration_errors', 'paer_check_preapproved_email', 10, 3);

function paer_check_preapproved_email($errors, $sanitized_user_login, $user_email) {
    $preapproved_emails = paer_get_preapproved_emails();

    if (!is_array($preapproved_emails)) {
        $preapproved_emails = array();
    }

    if (!in_array($user_email, $preapproved_emails)) {
        $errors->add('email_not_preapproved', 'Your email is not in the list of pre-approved email addresses.');
    }

    return $errors;
}

function paer_encrypt($email) {
    $key = PAER_ENCRYPTION_KEY;
    $iv = openssl_random_pseudo_bytes(openssl_cipher_iv_length('aes-256-cbc'));
    $encrypted = openssl_encrypt($email, 'aes-256-cbc', $key, 0, $iv);
    return base64_encode($encrypted . '::' . base64_encode($iv));
}

function paer_decrypt($encrypted_email) {
    $key = PAER_ENCRYPTION_KEY;
    list($encrypted_data, $iv) = explode('::', base64_decode($encrypted_email), 2);
    return openssl_decrypt($encrypted_data, 'aes-256-cbc', $key, 0, base64_decode($iv));
}

function paer_get_preapproved_emails() {
    $encrypted_emails = get_option('paer_preapproved_emails', array());

    if (!is_array($encrypted_emails)) {
        $encrypted_emails = array();
    }

    // Add logging for debugging
    $decrypted_emails = array_map('paer_decrypt', $encrypted_emails);
    error_log('PAER_GET_PREAPPROVED_EMAILS: Retrieved decrypted pre-approved emails: ' . print_r($decrypted_emails, true)); // Debug log

    return $decrypted_emails;
}
