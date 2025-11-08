<?php
/**
 * Test JavaScript Loading for Workflow Groups
 * Access via: /wp-content/plugins/wp-state-machine/test-workflow-js.php
 */

// Load WordPress
require_once('../../../wp-load.php');

// Check if user is logged in
if (!is_user_logged_in()) {
    die('Please login first');
}

?>
<!DOCTYPE html>
<html>
<head>
    <title>Workflow Groups JS Test</title>
    <style>
        body { font-family: Arial, sans-serif; padding: 20px; }
        .success { color: green; font-weight: bold; }
        .error { color: red; font-weight: bold; }
        pre { background: #f5f5f5; padding: 10px; border: 1px solid #ddd; overflow: auto; }
        .test-section { margin: 20px 0; padding: 15px; border: 1px solid #ccc; }
    </style>
    <?php
    // Load WordPress scripts and styles
    wp_head();
    ?>
</head>
<body>
    <h1>Workflow Groups JavaScript Test</h1>

    <div class="test-section">
        <h2>1. Check if DataTables is loaded:</h2>
        <p id="datatables-check">Checking...</p>
    </div>

    <div class="test-section">
        <h2>2. Check if wpStateMachineWorkflowGroupsData exists:</h2>
        <p id="localized-check">Checking...</p>
        <pre id="localized-data"></pre>
    </div>

    <div class="test-section">
        <h2>3. Check if workflow-groups.js is loaded:</h2>
        <p id="script-check">Checking...</p>
    </div>

    <div class="test-section">
        <h2>4. Test Modal Elements:</h2>
        <p id="modal-check">Checking...</p>
        <button id="test-modal-btn" class="button button-primary">Test Open Modal</button>
    </div>

    <div class="test-section">
        <h2>5. Console Output:</h2>
        <p>Open browser console (F12) to see any JavaScript errors</p>
    </div>

    <script>
    (function($) {
        $(document).ready(function() {
            // Test 1: DataTables
            if (typeof $.fn.DataTable !== 'undefined') {
                $('#datatables-check').html('<span class="success">✓ DataTables is loaded</span>');
            } else {
                $('#datatables-check').html('<span class="error">✗ DataTables NOT loaded</span>');
            }

            // Test 2: Localized data
            if (typeof wpStateMachineWorkflowGroupsData !== 'undefined') {
                $('#localized-check').html('<span class="success">✓ wpStateMachineWorkflowGroupsData exists</span>');
                $('#localized-data').text(JSON.stringify(wpStateMachineWorkflowGroupsData, null, 2));
            } else {
                $('#localized-check').html('<span class="error">✗ wpStateMachineWorkflowGroupsData NOT found</span>');
                $('#localized-data').text('Variable not defined. Check if localize_workflow_groups_scripts() is called.');
            }

            // Test 3: Script loaded check
            var scripts = document.querySelectorAll('script[src*="workflow-groups.js"]');
            if (scripts.length > 0) {
                $('#script-check').html('<span class="success">✓ workflow-groups.js found in page</span><br>Path: ' + scripts[0].src);
            } else {
                $('#script-check').html('<span class="error">✗ workflow-groups.js NOT found in page</span>');
            }

            // Test 4: Modal
            var modal = $('#group-modal');
            if (modal.length > 0) {
                $('#modal-check').html('<span class="success">✓ Modal element exists (#group-modal)</span>');

                // Test modal open
                $('#test-modal-btn').on('click', function() {
                    $('#group-modal').fadeIn();
                    alert('Modal should be visible now. If not, check CSS.');
                });
            } else {
                $('#modal-check').html('<span class="error">✗ Modal element NOT found</span>');
            }

            // Log everything to console
            console.log('=== WORKFLOW GROUPS DEBUG ===');
            console.log('jQuery version:', $.fn.jquery);
            console.log('DataTables loaded:', typeof $.fn.DataTable !== 'undefined');
            console.log('Localized data:', typeof wpStateMachineWorkflowGroupsData !== 'undefined' ? wpStateMachineWorkflowGroupsData : 'NOT FOUND');
            console.log('Modal exists:', $('#group-modal').length > 0);
            console.log('Add button exists:', $('#btn-add-group').length > 0);
        });
    })(jQuery);
    </script>

    <?php wp_footer(); ?>

    <hr>
    <p><a href="<?php echo admin_url('admin.php?page=workflow-groups'); ?>">← Back to Workflow Groups</a></p>
</body>
</html>
