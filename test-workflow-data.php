<?php
/**
 * Test file for Workflow Groups Database
 * Access via: /wp-content/plugins/wp-state-machine/test-workflow-data.php
 */

// Load WordPress
require_once('../../../wp-load.php');

// Check if user is logged in
if (!is_user_logged_in()) {
    die('Please login first');
}

global $wpdb;

echo '<h1>Workflow Groups Database Test</h1>';

$table_name = $wpdb->prefix . 'app_sm_workflow_groups';

// Check if table exists
$table_exists = $wpdb->get_var("SHOW TABLES LIKE '$table_name'");

echo '<h2>1. Table Check:</h2>';
if ($table_exists) {
    echo '<p style="color:green;">✓ Table exists: ' . $table_name . '</p>';
} else {
    echo '<p style="color:red;">✗ Table NOT found: ' . $table_name . '</p>';
    echo '<p>Run plugin activation to create tables.</p>';
    exit;
}

// Count records
$count = $wpdb->get_var("SELECT COUNT(*) FROM $table_name");
echo '<h2>2. Record Count:</h2>';
echo '<p>Total records: <strong>' . $count . '</strong></p>';

// Get all records
$groups = $wpdb->get_results("SELECT * FROM $table_name ORDER BY sort_order ASC");

echo '<h2>3. All Records:</h2>';
if ($groups) {
    echo '<table border="1" cellpadding="5" style="border-collapse:collapse;">';
    echo '<tr>';
    echo '<th>ID</th><th>Name</th><th>Slug</th><th>Icon</th><th>Active</th><th>Sort Order</th><th>Machine Count</th>';
    echo '</tr>';
    foreach ($groups as $group) {
        echo '<tr>';
        echo '<td>' . $group->id . '</td>';
        echo '<td>' . esc_html($group->name) . '</td>';
        echo '<td><code>' . $group->slug . '</code></td>';
        echo '<td><span class="dashicons ' . $group->icon . '"></span> ' . $group->icon . '</td>';
        echo '<td>' . ($group->is_active ? '✓ Active' : '✗ Inactive') . '</td>';
        echo '<td>' . $group->sort_order . '</td>';

        // Count machines in this group
        $machine_table = $wpdb->prefix . 'app_sm_machines';
        $machine_count = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM $machine_table WHERE workflow_group_id = %d",
            $group->id
        ));
        echo '<td>' . $machine_count . '</td>';
        echo '</tr>';
    }
    echo '</table>';
} else {
    echo '<p style="color:orange;">⚠ No workflow groups found in database.</p>';
    echo '<p>This is normal for a fresh installation. Try creating a group from the admin page.</p>';
}

// Test WorkflowGroupModel
echo '<h2>4. Test WorkflowGroupModel:</h2>';
try {
    require_once WP_STATE_MACHINE_PATH . 'src/Models/WorkflowGroup/WorkflowGroupModel.php';
    $model = new \WPStateMachine\Models\WorkflowGroup\WorkflowGroupModel();
    $groups_from_model = $model->getAll();
    echo '<p style="color:green;">✓ WorkflowGroupModel loaded successfully</p>';
    echo '<p>Groups from model: ' . count($groups_from_model) . '</p>';
} catch (Exception $e) {
    echo '<p style="color:red;">✗ Error: ' . $e->getMessage() . '</p>';
}

// Check permissions
echo '<h2>5. Current User Permissions:</h2>';
echo '<p>Can view_state_machines: ' . (current_user_can('view_state_machines') ? '✓ Yes' : '✗ No') . '</p>';
echo '<p>Can manage_state_machines: ' . (current_user_can('manage_state_machines') ? '✓ Yes' : '✗ No') . '</p>';

echo '<hr>';
echo '<p><a href="' . admin_url('admin.php?page=workflow-groups') . '">← Back to Workflow Groups</a></p>';
