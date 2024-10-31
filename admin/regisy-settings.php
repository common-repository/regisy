<?php
if (!defined('ABSPATH')) {
    exit(); // Exit if accessed directly
}

// admin/regisy-settings.php

// Add escaping within regisy_api_key_callback():
function regisy_api_key_callback()
{
    $value = get_option('regisy_api_key', '');
    echo '<input type="text" name="regisy_api_key" value="' . esc_attr($value) . '" placeholder="' . esc_attr__('Enter your Regisy API Key', 'regisy-integration') . '" />';

    echo '<div class="button-container">';
    echo '<button type="button" id="test-connection-button">' . esc_html__('Test Connection', 'regisy-integration') . '</button>';
    echo '<button type="button" id="sync-existing-users-button">' . esc_html__('Sync Existing Users', 'regisy-integration') . '</button>';
    echo '<p class="description" id="sync-description">' . esc_html__('Syncing existing users may take some time. Please be patient. Do not close the Regisy settings screen.', 'regisy-integration') . '</p>';
    echo '</div>';
    echo '<div class="description-container">';
    echo '<p class="description" id="below-description">' .
        sprintf(
            // Translators: Please be aware of the placeholders - %1$s, %2$s, etc. These represent links or emphasis tags.
            esc_html__(
                'Login or %1$sRegister%2$s here to create your %3$sRegisy API Key%4$s. Get your %5$sRegisy API Key%6$s and enter it above and %7$sSave Changes%8$s below. This key is required for connecting to Regisy services. If you need assistance, please check our %9$sdocumentation%10$s. For free email templates, visit %11$sEmail Templates%12$s.',
                'regisy-integration'
            ),
            '<a href="https://my.regisy.com/login" target="_blank">',
            '</a>',
            '<strong>',
            '</strong>',
            '<a href="https://my.regisy.com/api-tokens" target="_blank">',
            '</a>',
            '<strong>',
            '</strong>',
            '<a href="https://regisy.com/docs" target="_blank">',
            '</a>',
            '<a href="https://regisy.com/email-templates" target="_blank">',
            '</a>'
        ) .
        '</p>';
    echo '</div>';
}

// Add an action to enqueue assets for admin pages
function regisy_admin_assets($hook)
{
    if ($hook === 'toplevel_page_regisy-settings') {
        wp_enqueue_style('regisy-styles', esc_url(plugin_dir_url(__FILE__)) . '../assets/css/regisy-styles.css', [], '1.0.0');

    }
}
add_action('admin_enqueue_scripts', 'regisy_admin_assets');

// Add JavaScript to handle the button clicks
function regisy_integration_settings_callback()
{
    ?>
    <div class="wrap">
        <img class="regisy-logo" src="<?php echo esc_url(plugin_dir_url(__FILE__) . '../assets/img/regisy-logo.png'); ?>" alt="<?php esc_attr_e('Regisy Logo', 'regisy-integration'); ?>" style="max-width: 100%;">

        <form method="post" action="options.php">
            <?php settings_fields('regisy_integration_settings_group'); ?>
            <?php do_settings_sections('regisy_integration_settings'); ?>

            <?php // Nonce field for form submission security ?>
            <?php wp_nonce_field('regisy_settings_nonce', 'regisy_settings_nonce'); ?>

            <table class="form-table" role="presentation">
                <tbody>
                    <tr>
                        <th scope="row">
                            <label for="regisy_api_key"><?php esc_html_e('Regisy API Key', 'regisy-integration'); ?></label>
                        </th>
                        <td>
                            <?php regisy_api_key_callback(); ?> 
                        </td>
                    </tr>
                </tbody>
            </table>

            <?php submit_button(); ?>
        </form>

        <script>
            // Use a variable to store the AJAX endpoint
            var ajaxurl = '<?php echo esc_url(admin_url('admin-ajax.php')); ?>';

            var syncInProgress = false;
            var syncPaused = false;
            var testConnectionNotice;
            var testConnectionErrorNotice;

            document.getElementById('sync-existing-users-button').addEventListener('click', function () {
                if (syncInProgress) {
                    syncPaused = !syncPaused;
                    var syncButton = document.getElementById('sync-existing-users-button');
                    syncButton.innerHTML = syncPaused ? '<?php echo esc_html__('Resume Sync', 'regisy-integration'); ?>' : '<?php echo esc_html__('Pause Sync', 'regisy-integration'); ?>';

                    // Show processing status when paused
                    var syncDescription = document.getElementById('sync-description');
                    syncDescription.innerHTML = syncPaused ? '<?php echo esc_html__('Processing...', 'regisy-integration'); ?>' : '<?php echo esc_html__('Syncing in progress... Please wait.', 'regisy-integration'); ?>';

                    // Remove test connection notices from under the buttons
                    removeTestConnectionNotice();
                    removeTestConnectionErrorNotice();

                    return;
                }

                syncInProgress = true;

                var nonce = '<?php echo esc_js(wp_create_nonce('regisy_sync_users_nonce')); ?>';
 
                var syncButton = document.getElementById('sync-existing-users-button');
                var syncDescription = document.getElementById('sync-description');

                // Display wait message
                syncButton.innerHTML = '<?php echo esc_html__('Pause Sync', 'regisy-integration'); ?>';
                syncDescription.innerHTML = '<?php echo esc_html__('Syncing in progress... Please wait.', 'regisy-integration'); ?>';

                // Remove test connection notices from under the buttons
                removeTestConnectionNotice();
                removeTestConnectionErrorNotice();

                // Handle the API response after syncing users
                fetch(ajaxurl, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: new URLSearchParams({
                        action: 'regisy_sync_existing_users',
                        regisy_nonce: nonce,
                    }),
                })
                .then(response => response.json())
                .then(data => {
                    console.log(data);
                    if (data.success) {
                        // Add any specific handling for success if needed
                    } else {
                        console.error('<?php echo esc_html__('Sync failed:', 'regisy-integration'); ?>' + data.data);
                    }
                })
                .catch(error => {
                    console.error(error);
                    alert('<?php echo esc_html__('Sync failed:', 'regisy-integration'); ?>' + error.message);
                })
                .finally(() => {
                    // Restore the sync button after completion
                    syncButton.innerHTML = '<?php echo esc_html__('Sync Existing Users', 'regisy-integration'); ?>';
                    syncInProgress = false;
                    syncPaused = false; // Reset pause state
                    syncDescription.innerHTML = '<?php echo esc_html__('Sync Completed.', 'regisy-integration'); ?>';
                });
            });

            document.getElementById('test-connection-button').addEventListener('click', function () {
                var api_key = document.querySelector('[name="regisy_api_key"]').value;
                var nonce = '<?php echo esc_js(wp_create_nonce('regisy_nonce_action')); ?>';
                var testButton = document.getElementById('test-connection-button');

                // Display wait message
                testButton.innerHTML = '<?php echo esc_html__('Testing connection... Please wait.', 'regisy-integration'); ?>';
                testButton.disabled = true;

                // Remove previous test connection notices from under the buttons
                removeTestConnectionNotice();
                removeTestConnectionErrorNotice();

                fetch(ajaxurl, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: new URLSearchParams({
                        action: 'regisy_test_connection',
                        regisy_api_key: api_key,
                        regisy_nonce: nonce,
                    }),
                })
                .then(response => response.json())
                .then(data => {
                    console.log(data);
                    if (data.success) {
                        // Add test connection notice under the sync button
                        addTestConnectionNotice(data.data);
                    } else {
                        // Add test connection error notice under the sync button
                        addTestConnectionErrorNotice(data.data);
                    }
                })
                .catch(error => {
                    console.error(error);
                    // Add test connection error notice under the sync button
                    addTestConnectionErrorNotice(error.message);
                })
                .finally(() => {
                    // Restore the test button after completion
                    testButton.innerHTML = '<?php echo esc_html__('Test Connection', 'regisy-integration'); ?>';
                    testButton.disabled = false;
                });
            });

            function addTestConnectionNotice(message) {
                var syncButtonContainer = document.getElementById('test-connection-button').parentNode;
                testConnectionNotice = document.createElement('p');
                testConnectionNotice.setAttribute('class', 'description');
                testConnectionNotice.innerHTML = message;
                testConnectionNotice.id = 'test-connection-notice';
                syncButtonContainer.appendChild(testConnectionNotice);
            }

            function removeTestConnectionNotice() {
                if (testConnectionNotice) {
                    testConnectionNotice.remove();
                }
            }

            function addTestConnectionErrorNotice(errorMessage) {
                var syncButtonContainer = document.getElementById('test-connection-button').parentNode;
                testConnectionErrorNotice = document.createElement('p');
                testConnectionErrorNotice.setAttribute('class', 'description');
                // Remove duplicate "Connection failed:" part
                errorMessage = errorMessage.replace('<?php echo esc_html__('Connection failed: ', 'regisy-integration'); ?>', '');
                testConnectionErrorNotice.innerHTML = '<?php echo esc_html__('Connection failed:', 'regisy-integration'); ?>' + errorMessage;
                testConnectionErrorNotice.id = 'test-connection-error-notice';
                syncButtonContainer.appendChild(testConnectionErrorNotice);
            }

            function removeTestConnectionErrorNotice() {
                if (testConnectionErrorNotice) {
                    testConnectionErrorNotice.remove();
                }
            }
        </script>

    </div>
    <?php
}

// admin/regisy-settings.php

// Register settings and fields
function regisy_integration_register_settings()
{
    // Add nonce verification for form submission security
    if (!isset($_POST['regisy_settings_nonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['regisy_settings_nonce'])), 'regisy_settings_nonce')) {
        return;
    }

    register_setting('regisy_integration_settings_group', 'regisy_api_key', [
        'type' => 'string',
        'sanitize_callback' => 'sanitize_text_field',
        'default' => '',
    ]);

    add_settings_section('regisy_integration_main_section', esc_html__('Regisy Integration Settings', 'regisy-integration'), 'regisy_integration_main_section_callback', 'regisy_integration_settings');

    // No need to add an additional settings field, as we use the 'regisy_api_key' field in the table.
}

add_action('admin_init', 'regisy_integration_register_settings');

// Placeholder for the section callback function
function regisy_integration_main_section_callback()
{
    // This is a placeholder, replace with the actual implementation if needed
}
?>
