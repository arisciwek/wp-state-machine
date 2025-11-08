<?php
/**
 * Workflow Groups Admin View
 *
 * @package     WP_State_Machine
 * @subpackage  Views/Admin/WorkflowGroups
 * @version     1.0.1
 * @author      arisciwek
 *
 * Path: /wp-state-machine/src/Views/admin/workflow-groups/workflow-groups-view.php
 *
 * Description: Admin interface for managing workflow groups.
 *              Clean HTML only - no inline CSS or JavaScript.
 *              Assets loaded via class-dependencies.php
 *
 * Changelog:
 * 1.0.1 - 2025-11-08
 * - Removed all inline styles
 * - Clean HTML structure following machines/states pattern
 * - Consistent modal naming
 * - WordPress admin theme integration
 *
 * 1.0.0 - 2025-11-07
 * - Initial creation
 * - DataTable integration
 * - CRUD modals
 */

defined('ABSPATH') || exit;

// Debug: Display current screen ID
$screen = get_current_screen();
if (defined('WP_DEBUG') && WP_DEBUG && $screen) {
    error_log('Workflow Groups Screen ID: ' . $screen->id);
}
?>

<div class="wrap wp-state-machine-admin">
    <h1 class="wp-heading-inline"><?php _e('Workflow Groups', 'wp-state-machine'); ?></h1>
    <button type="button" class="page-title-action" id="btn-add-group">
        <?php _e('Add New Group', 'wp-state-machine'); ?>
    </button>
    <hr class="wp-header-end">

    <p class="description">
        <?php _e('Organize state machines into logical groups for better management and clarity.', 'wp-state-machine'); ?>
    </p>

    <!-- DataTable -->
    <table id="workflow-groups-table" class="wp-list-table widefat fixed striped">
        <thead>
            <tr>
                <th><?php _e('ID', 'wp-state-machine'); ?></th>
                <th><?php _e('Icon', 'wp-state-machine'); ?></th>
                <th><?php _e('Name', 'wp-state-machine'); ?></th>
                <th><?php _e('Slug', 'wp-state-machine'); ?></th>
                <th><?php _e('Machines', 'wp-state-machine'); ?></th>
                <th><?php _e('Sort Order', 'wp-state-machine'); ?></th>
                <th><?php _e('Status', 'wp-state-machine'); ?></th>
                <th><?php _e('Actions', 'wp-state-machine'); ?></th>
            </tr>
        </thead>
        <tbody>
        </tbody>
    </table>
</div>

<!-- Create/Edit Modal -->
<div id="group-modal" class="wp-state-machine-modal" style="display:none;">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h2 id="modal-title"><?php _e('Add New Workflow Group', 'wp-state-machine'); ?></h2>
                <button type="button" class="modal-close" aria-label="<?php esc_attr_e('Close', 'wp-state-machine'); ?>">
                    <span class="dashicons dashicons-no"></span>
                </button>
            </div>
            <div class="modal-body">
                <form id="group-form">
                    <input type="hidden" id="group-id" name="id" value="">

                    <table class="form-table">
                        <tr>
                            <th scope="row">
                                <label for="group-name"><?php _e('Group Name', 'wp-state-machine'); ?> <span class="required">*</span></label>
                            </th>
                            <td>
                                <input type="text" id="group-name" name="name" class="regular-text" required>
                                <p class="description"><?php _e('Display name for this group (e.g., "Order Management", "User Workflows")', 'wp-state-machine'); ?></p>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row">
                                <label for="group-slug"><?php _e('Slug', 'wp-state-machine'); ?> <span class="required">*</span></label>
                            </th>
                            <td>
                                <input type="text" id="group-slug" name="slug" class="regular-text" pattern="[a-z0-9_\-]+" readonly required>
                                <p class="description">
                                    <span class="dashicons dashicons-info"></span>
                                    <?php _e('Auto-generated from name (cannot be changed after creation)', 'wp-state-machine'); ?>
                                </p>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row">
                                <label for="group-description"><?php _e('Description', 'wp-state-machine'); ?></label>
                            </th>
                            <td>
                                <textarea id="group-description" name="description" class="large-text" rows="3"></textarea>
                                <p class="description"><?php _e('Optional description of this group', 'wp-state-machine'); ?></p>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row">
                                <label for="group-icon"><?php _e('Icon', 'wp-state-machine'); ?></label>
                            </th>
                            <td>
                                <input type="text" id="group-icon" name="icon" class="regular-text" value="dashicons-networking">
                                <p class="description">
                                    <?php _e('Dashicon class (e.g., dashicons-networking, dashicons-chart-pie)', 'wp-state-machine'); ?>
                                    <a href="https://developer.wordpress.org/resource/dashicons/" target="_blank">
                                        <?php _e('Browse Dashicons', 'wp-state-machine'); ?>
                                    </a>
                                </p>
                                <div class="icon-preview">
                                    <span id="icon-preview-display" class="dashicons dashicons-networking"></span>
                                </div>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row">
                                <label for="group-sort-order"><?php _e('Sort Order', 'wp-state-machine'); ?></label>
                            </th>
                            <td>
                                <input type="number" id="group-sort-order" name="sort_order" class="small-text" value="0" min="0">
                                <p class="description"><?php _e('Display order (lower numbers appear first)', 'wp-state-machine'); ?></p>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row">
                                <label for="group-is-active"><?php _e('Status', 'wp-state-machine'); ?></label>
                            </th>
                            <td>
                                <label>
                                    <input type="checkbox" id="group-is-active" name="is_active" value="1" checked>
                                    <?php _e('This group is active and available for use', 'wp-state-machine'); ?>
                                </label>
                            </td>
                        </tr>
                    </table>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="button button-secondary modal-close">
                    <?php _e('Cancel', 'wp-state-machine'); ?>
                </button>
                <button type="button" class="button button-primary" id="btn-save-group">
                    <?php _e('Save Group', 'wp-state-machine'); ?>
                </button>
            </div>
        </div>
    </div>
</div>

<!-- View Modal -->
<div id="view-group-modal" class="wp-state-machine-modal" style="display:none;">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h2><?php _e('Workflow Group Details', 'wp-state-machine'); ?></h2>
                <button type="button" class="modal-close" aria-label="<?php esc_attr_e('Close', 'wp-state-machine'); ?>">
                    <span class="dashicons dashicons-no"></span>
                </button>
            </div>
            <div class="modal-body">
                <table class="form-table">
                    <tr>
                        <th><?php _e('ID', 'wp-state-machine'); ?>:</th>
                        <td id="view-group-id"></td>
                    </tr>
                    <tr>
                        <th><?php _e('Icon', 'wp-state-machine'); ?>:</th>
                        <td>
                            <span id="view-group-icon-preview" class="dashicons"></span>
                            <code id="view-group-icon"></code>
                        </td>
                    </tr>
                    <tr>
                        <th><?php _e('Name', 'wp-state-machine'); ?>:</th>
                        <td id="view-group-name"></td>
                    </tr>
                    <tr>
                        <th><?php _e('Slug', 'wp-state-machine'); ?>:</th>
                        <td><code id="view-group-slug"></code></td>
                    </tr>
                    <tr>
                        <th><?php _e('Description', 'wp-state-machine'); ?>:</th>
                        <td id="view-group-description"></td>
                    </tr>
                    <tr>
                        <th><?php _e('Sort Order', 'wp-state-machine'); ?>:</th>
                        <td id="view-group-sort-order"></td>
                    </tr>
                    <tr>
                        <th><?php _e('Status', 'wp-state-machine'); ?>:</th>
                        <td id="view-group-status"></td>
                    </tr>
                    <tr>
                        <th><?php _e('Assigned Machines', 'wp-state-machine'); ?>:</th>
                        <td id="view-group-machines"></td>
                    </tr>
                    <tr>
                        <th><?php _e('Created', 'wp-state-machine'); ?>:</th>
                        <td id="view-group-created"></td>
                    </tr>
                    <tr>
                        <th><?php _e('Updated', 'wp-state-machine'); ?>:</th>
                        <td id="view-group-updated"></td>
                    </tr>
                </table>
            </div>
            <div class="modal-footer">
                <button type="button" class="button button-secondary modal-close">
                    <?php _e('Close', 'wp-state-machine'); ?>
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Assets (workflow-groups.css and workflow-groups.js) are loaded via class-dependencies.php -->
<!-- JavaScript data is localized via wpStateMachineWorkflowGroupsData -->

<!-- Debug: Inline test script -->
<script>
jQuery(document).ready(function($) {
    console.log('=== WORKFLOW GROUPS DEBUG ===');
    console.log('jQuery loaded:', typeof $ !== 'undefined');
    console.log('DataTables loaded:', typeof $.fn.DataTable !== 'undefined');
    console.log('Localized data loaded:', typeof wpStateMachineWorkflowGroupsData !== 'undefined');
    console.log('Data:', typeof wpStateMachineWorkflowGroupsData !== 'undefined' ? wpStateMachineWorkflowGroupsData : 'NOT FOUND');
    console.log('Add button exists:', $('#btn-add-group').length);
    console.log('Modal exists:', $('#group-modal').length);
    console.log('Table exists:', $('#workflow-groups-table').length);

    // Simple fallback if main script doesn't load
    if (typeof wpStateMachineWorkflowGroupsData === 'undefined') {
        console.error('wpStateMachineWorkflowGroupsData NOT LOADED! Check class-dependencies.php');
        alert('JavaScript configuration error. Check console for details.');
    }
});
</script>
