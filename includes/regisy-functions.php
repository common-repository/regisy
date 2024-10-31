<?php
if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}
// includes/regisy-functions.php

// Define regisy_get_api_token function
function regisy_get_api_token()
{
    return get_option('regisy_api_key', ''); // Retrieve the API key from WordPress options
}

// New function to sync existing users
if (!function_exists('regisy_sync_existing_users')) {
    // New function to sync existing users
    function regisy_sync_existing_users()
    {
        $users = get_users();

        foreach ($users as $user) {
            $user_email = $user->user_email;
            regisy_subscribe_user($user_email);
        }
    }
}

// Modify the regisy_subscribe_user function to accept email and handle resubscription
function regisy_subscribe_user($user_email)
{
    $api_token = regisy_get_api_token();

    // Check if the API token is available
    if (empty($api_token)) {
        error_log('Regisy Subscribe Error: Missing API token');
        return; // Exit the function if the API token is missing
    }

    $subscribe_url = trailingslashit('https://my.regisy.com/api/v1/') . 'subscribers'; // Use the correct endpoint for subscribing

    // Build your request data
    $data = [
        'email' => $user_email,
        'unsubscribed_at' => null, // Set unsubscribed_at to null for resubscription
        // Add other necessary data for your Regisy subscription
    ];

    $headers = [
        'Accept' => 'application/json',
        'Content-Type' => 'application/json',
        'Authorization' => 'Bearer ' . $api_token,
    ];

    // Debugging: Output the email before making the API request
    error_log('Regisy Subscribe Debug: Email - ' . $user_email);

    // Example POST request for resubscription/update
    $response_subscribe = wp_remote_post($subscribe_url, ['body' => wp_json_encode($data), 'headers' => $headers]);

    if (!is_wp_error($response_subscribe)) {
        $body_subscribe = wp_remote_retrieve_body($response_subscribe);
        $data_subscribe = json_decode($body_subscribe, true);
        error_log('Regisy Subscribe Debug: Resubscription Request - ' . print_r($data_subscribe, true));
    } else {
        // Handle errors
        error_log('Regisy Subscribe Error: Resubscription Request - ' . $response_subscribe->get_error_message());
    }
}

// Action hook to execute the function when the Contact Form 7 form is submitted
add_action('wpcf7_mail_sent', 'regisy_subscribe_contact_form', 10, 1);

// Function to handle form submission and trigger Regisy subscription
function regisy_subscribe_contact_form($cf7)
{
    // Get the submitted email from the form
    $submission = WPCF7_Submission::get_instance();

    if ($submission) {
        $posted_data = $submission->get_posted_data();

        // Get the form ID dynamically
        $form_id = $cf7->id();

        // Replace 'your-email' with the actual field name
        $user_email = isset($posted_data['your-email']) ? $posted_data['your-email'] : '';

        // Check if the form ID and email are valid
        if ($form_id && $user_email) {
            // Your logic to subscribe the user with Regisy
            regisy_subscribe_user($user_email);
        }
    }
}

// Action hook to execute the function when the WPForms form is submitted
add_action('wpforms_process_complete', 'regisy_subscribe_wpforms', 10, 4);

// Function to handle form submission and trigger Regisy subscription
function regisy_subscribe_wpforms($fields, $entry, $form_data, $entry_id)
{
    // Loop through all form fields to find the email field dynamically
    foreach ($form_data['fields'] as $field) {
        if ($field['type'] === 'email') {
            // Found an email field
            $field_id = $field['id'];

            // Check if the email value is set and is a valid email address
            if (isset($fields[$field_id]['value']) && is_scalar($fields[$field_id]['value']) && is_email($fields[$field_id]['value'])) {
                $user_email = $fields[$field_id]['value'];

                // Your logic to subscribe the user with Regisy
                $result = regisy_subscribe_user($user_email);

                // Check if the subscription was successful
                if ($result === true) {
                    error_log('Regisy Subscribe Debug: User subscribed successfully - ' . $user_email);
                } else {
                    // Log the error message
                    error_log('Regisy Subscribe Error: ' . $result);
                }
            } else {
                // Log a warning with additional information
                error_log('Regisy Subscribe Warning: Invalid or empty email value - ' . print_r($fields[$field_id], true));
            }

            // Exit the loop once an email field is found
            break;
        }
    }
}

// Action hook to execute the function when the Gravity Forms form is submitted
add_action('gform_after_submission', 'regisy_subscribe_gravityforms', 10, 2);

// Function to handle form submission and trigger Regisy subscription for Gravity Forms
function regisy_subscribe_gravityforms($entry, $form)
{
    // Specify the form IDs that should trigger Regisy subscription
    $target_form_ids = array(1, 2, 3); // Add or remove form IDs as needed

    // Check if the submitted form is in the target forms array
    if (in_array($form['id'], $target_form_ids)) {
        // Get the submitted email from the form
        $user_email = rgar($entry, '1'); // Adjust the field index based on your form
        regisy_subscribe_user($user_email);
    }
}

// Action hook to execute the function when the Ninja Forms form is submitted
add_action('ninja_forms_after_submission', 'regisy_subscribe_ninjaforms');

// Function to handle form submission and trigger Regisy subscription for Ninja Forms
function regisy_subscribe_ninjaforms($form_data)
{
    // Specify the form IDs that should trigger Regisy subscription
    $target_form_ids = array(1, 2, 3); // Add or remove form IDs as needed

    // Check if the submitted form is in the target forms array
    if (in_array($form_data['form_id'], $target_form_ids)) {
        // Get the submitted email from the form
        $user_email = $form_data['fields'][0]['value']; // Adjust the field index based on your form
        regisy_subscribe_user($user_email);
    }
}
