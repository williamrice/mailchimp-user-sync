<?php

/**
 * Plugin Name: Mailchimp User Sync
 * Description: Sync WordPress user registrations with Mailchimp.
 * Version: 1.0
 * Author: William Rice
 */

if (! defined('ABSPATH')) exit; // Exit if accessed directly.

// Add admin menu item
function mailchimp_user_sync_admin_menu()
{
    add_menu_page(
        'Mailchimp User Sync',
        'Mailchimp Sync',
        'manage_options',
        'mailchimp-user-sync',
        'mailchimp_user_sync_settings_page',
        'dashicons-email',
        100
    );
}
add_action('admin_menu', 'mailchimp_user_sync_admin_menu');

// Settings page content
function mailchimp_user_sync_settings_page()
{
    // Handle form submission
    if (isset($_POST['mailchimp_user_sync_save']) && check_admin_referer('mailchimp_user_sync_save_nonce')) {
        update_option('mailchimp_api_key', sanitize_text_field($_POST['mailchimp_api_key']));
        update_option('mailchimp_list_id', sanitize_text_field($_POST['mailchimp_list_id']));
        update_option('mailchimp_server_prefix', sanitize_text_field($_POST['mailchimp_server_prefix']));
        echo '<div class="updated"><p>Settings saved successfully.</p></div>';
    }

    $api_key = get_option('mailchimp_api_key', '');
    $list_id = get_option('mailchimp_list_id', '');
    $server_prefix = get_option('mailchimp_server_prefix', '');

?>
    <div class="wrap">
        <h1>Mailchimp User Sync Settings</h1>
        <form method="post">
            <?php wp_nonce_field('mailchimp_user_sync_save_nonce'); ?>
            <table class="form-table">
                <tr>
                    <th scope="row">
                        <label for="mailchimp_api_key">Mailchimp API Key</label>
                    </th>
                    <td>
                        <input type="text" name="mailchimp_api_key" id="mailchimp_api_key" value="<?php echo esc_attr($api_key); ?>" class="regular-text" />
                    </td>
                </tr>
                <tr>
                    <th scope="row">
                        <label for="mailchimp_list_id">Mailchimp List ID</label>
                    </th>
                    <td>
                        <input type="text" name="mailchimp_list_id" id="mailchimp_list_id" value="<?php echo esc_attr($list_id); ?>" class="regular-text" />
                    </td>
                </tr>
                <tr>
                    <th scope="row">
                        <label for="mailchimp_server_prefix">Mailchimp Server Prefix</label>
                    </th>
                    <td>
                        <input type="text" name="mailchimp_server_prefix" id="mailchimp_server_prefix" value="<?php echo esc_attr($server_prefix); ?>" class="regular-text" />
                    </td>
                </tr>
            </table>
            </table>
            <p class="submit">
                <input type="submit" name="mailchimp_user_sync_save" class="button-primary" value="Save Settings" />
            </p>
        </form>
    </div>
<?php
}

// Listen for user registrations
function mailchimp_user_sync_on_registration($user_id)
{
    $api_key = get_option('mailchimp_api_key');
    $list_id = get_option('mailchimp_list_id');
    $mailchimp_server_prefix = get_option('mailchimp_server_prefix');

    if (! $api_key || ! $list_id) {
        return; // Do nothing if API key or List ID is not set
    }

    error_log('Syncing user with Mailchimp: ' . $user_id);

    $user_info = get_userdata($user_id);
    $email = $user_info->user_email;
    $data = [
        'email_address' => $email,
        'status'        => 'subscribed',
        "merge_fields" => [
            "FNAME" => $user_info->first_name,
            "LNAME" => $user_info->last_name,
        ]
    ];

    $url = "https://{$mailchimp_server_prefix}.api.mailchimp.com/3.0/lists/{$list_id}/members/";
    $data_center = substr($api_key, strpos($api_key, '-') + 1);
    $url = str_replace('<dc>', $data_center, $url);

    $args = [
        'body'    => json_encode($data),
        'headers' => [
            'Authorization' => 'Basic ' . base64_encode("user:{$api_key}"),
            'Content-Type'  => 'application/json',
        ],
    ];

    $response = wp_remote_post($url, $args);

    if (is_wp_error($response)) {
        error_log("Error Registering User with Mailchimp: " . $user_info->user_email);
        error_log('Mailchimp API Error: ' . $response->get_error_message());
    }
}
add_action('user_register', 'mailchimp_user_sync_on_registration');
