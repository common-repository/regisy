<?php
if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}
/*
Plugin Name: Regisy Integration
Description: Connects your WordPress site with Regisy for email marketing.
Version: 1.1
Author: Unioney
License: GPL-2.0+
Plugin URI: http://regisy.com
Text Domain: regisy-integration
Author URI: https://unioney.com
*/

// regisy-integration.php

// Define your settings URL
$regisy_settings_url = admin_url('admin.php?page=regisy-settings');

// Add a filter to modify the plugin action links
add_filter('plugin_action_links_' . plugin_basename(__FILE__), 'regisy_integration_plugin_action_links');

function regisy_integration_plugin_action_links($links)
{
    // Add your settings link
    global $regisy_settings_url;
    $settings_link = '<a href="' . esc_url($regisy_settings_url) . '">' . esc_html__('Settings', 'regisy-integration') . '</a>';
    array_unshift($links, $settings_link);
    return $links;
}

// New AJAX handler for syncing existing users
add_action('wp_ajax_regisy_sync_existing_users', 'regisy_sync_existing_users_callback');

function regisy_sync_existing_users_callback() {
    check_ajax_referer('regisy_sync_users_nonce', 'regisy_nonce');

    if (!current_user_can('manage_options')) {
        wp_send_json_error('Permission denied');
    }

    regisy_sync_existing_users();

    wp_send_json_success('Sync completed');
}

function regisy_test_api_connection($api_key) {
    $actual_api_key = get_option('regisy_api_key', ''); // Adjust the option name based on your implementation

    if (!empty($api_key) && $api_key === $actual_api_key) {
        return true; // Connection successful
    } else {
        return 'Invalid API key';
    }
}

// Include other files
require_once plugin_dir_path(__FILE__) . 'admin/regisy-settings.php'; // Admin settings page
require_once plugin_dir_path(__FILE__) . 'includes/regisy-functions.php'; // Functions for API requests and more

// Hook into user registration to subscribe to Regisy
add_action('user_register', 'regisy_subscribe_new_user');

// New function to sync existing users with Regisy
if (!function_exists('regisy_sync_existing_users')) {
    function regisy_sync_existing_users() {
        $users = get_users();

        foreach ($users as $user) {
            $user_email = $user->user_email;
            regisy_subscribe_user($user_email);
        }
    }
}

// Function to subscribe a new user to Regisy
function regisy_subscribe_new_user($user_id) {
    $user = get_user_by('ID', $user_id);
    
    if ($user) {
        $user_email = $user->user_email;
        regisy_subscribe_user($user_email);
    }
}

// Trigger an email campaign when needed
function regisy_trigger_email_campaign() {
    // Logic to trigger the email campaign
    // This might involve making a request to Regisy's API

    // Add synchronization of existing users
    regisy_sync_existing_users();
}

// Schedule a cron job for sending emails
if (!wp_next_scheduled('regisy_trigger_email_campaign')) {
    wp_schedule_event(time(), 'daily', 'regisy_trigger_email_campaign');
}

add_action('regisy_trigger_email_campaign', 'regisy_trigger_email_campaign');

// Add top-level menu item with a custom icon
function regisy_add_menu() {
    // Get the URL of the SVG file
    $icon_url = plugin_dir_url( __FILE__ ) . 'assets/img/regisy-dashboard-icon.svg';

    // Add the menu page with the SVG icon URL
    add_menu_page(
        'Regisy Integration',                 // Page title
        'Regisy',                            // Menu title
        'manage_options',                    // Capability
        'regisy-settings',                   // Menu slug
        'regisy_integration_settings_callback', // Callback function to display the settings page
        $icon_url,                           // SVG icon URL
        30                                   // Position in the menu
    );
}

add_action('admin_menu', 'regisy_add_menu');



// Add an action for the AJAX request
add_action('wp_ajax_regisy_test_connection', 'regisy_test_connection');

function regisy_test_connection() {
    // Check the nonce
    check_ajax_referer('regisy_nonce_action', 'regisy_nonce');

    // Check if the user has the required capability (adjust as needed)
    if (!current_user_can('manage_options')) {
        wp_send_json_error('Permission denied');
    }

    // Get the API key from the AJAX request
    $api_key = sanitize_text_field($_POST['regisy_api_key']);

    // Debugging: Output the API key for testing
    error_log('Regisy Test Connection Debug: API Key - ' . $api_key);

    // Simulate the API request (replace with actual API test logic)
    $response = regisy_test_api_connection($api_key);

    if ($response === true) {
        wp_send_json_success('Connection successful');
    } else {
        wp_send_json_error('Connection failed: ' . $response);
    }
}