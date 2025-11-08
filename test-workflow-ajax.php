<?php
/**
 * Test file for Workflow Groups AJAX
 * Access via: /wp-content/plugins/wp-state-machine/test-workflow-ajax.php
 */

// Load WordPress
require_once('../../../wp-load.php');

// Check if user is logged in
if (!is_user_logged_in()) {
    die('Please login first');
}

echo '<h1>Workflow Groups AJAX Test</h1>';

// Simulate AJAX request
$_POST['action'] = 'handle_workflow_group_datatable';
$_POST['nonce'] = wp_create_nonce('wp_state_machine_nonce');
$_POST['draw'] = 1;
$_POST['start'] = 0;
$_POST['length'] = 10;
$_POST['search'] = ['value' => ''];
$_POST['order'] = [['column' => 0, 'dir' => 'asc']];

echo '<h2>Request Data:</h2>';
echo '<pre>';
print_r($_POST);
echo '</pre>';

// Trigger the AJAX action
do_action('wp_ajax_handle_workflow_group_datatable');

echo '<h2>Response should appear above (if any)</h2>';
echo '<p>If empty, check:</p>';
echo '<ul>';
echo '<li>WorkflowGroupController is instantiated</li>';
echo '<li>AJAX handler is registered</li>';
echo '<li>Database table exists</li>';
echo '</ul>';
