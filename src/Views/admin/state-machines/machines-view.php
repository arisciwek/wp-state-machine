<?php
/**
 * State Machines Admin View
 *
 * @package     WP_State_Machine
 * @subpackage  Views/Admin/StateMachines
 * @version     1.0.0
 * @author      arisciwek
 *
 * Path: /wp-state-machine/src/Views/admin/state-machines/machines-view.php
 *
 * Description: Admin interface for managing state machines.
 *              Clean HTML only - no inline CSS or JavaScript.
 *              Assets loaded via class-dependencies.php
 *
 * Changelog:
 * 1.0.0 - 2025-11-08
 * - Initial creation
 * - Clean HTML structure
 * - DataTable markup
 * - Modal forms
 */

defined('ABSPATH') || exit;

// Get all workflow groups for the filter and form dropdown
global $wpdb;
$workflow_groups_table = $wpdb->prefix . 'app_sm_workflow_groups';
$workflow_groups = $wpdb->get_results("SELECT id, name FROM {$workflow_groups_table} ORDER BY name ASC");

// Note: wpStateMachineMachinesData is localized in class-dependencies.php
?>

<div class="wrap wp-state-machine-admin">
    <h1 class="wp-heading-inline"><?php _e('State Machines', 'wp-state-machine'); ?></h1>
    <button type="button" class="page-title-action" id="btn-add-machine">
        <?php _e('Add New State Machine', 'wp-state-machine'); ?>
    </button>
    <hr class="wp-header-end">

    <!-- Workflow Group Filter -->
    <?php if (!empty($workflow_groups)): ?>
    <div class="tablenav top">
        <div class="alignleft actions">
            <label for="filter-workflow-group" class="screen-reader-text">
                <?php _e('Filter by workflow group', 'wp-state-machine'); ?>
            </label>
            <select name="workflow_group_id" id="filter-workflow-group">
                <option value=""><?php _e('All Workflow Groups', 'wp-state-machine'); ?></option>
                <?php foreach ($workflow_groups as $group): ?>
                    <option value="<?php echo esc_attr($group->id); ?>">
                        <?php echo esc_html($group->name); ?>
                    </option>
                <?php endforeach; ?>
            </select>
            <button type="button" class="button" id="btn-filter">
                <?php _e('Filter', 'wp-state-machine'); ?>
            </button>
        </div>
    </div>
    <?php endif; ?>

    <!-- DataTable -->
    <table id="machines-table" class="wp-list-table widefat fixed striped">
        <thead>
            <tr>
                <th><?php _e('ID', 'wp-state-machine'); ?></th>
                <th><?php _e('Name', 'wp-state-machine'); ?></th>
                <th><?php _e('Slug', 'wp-state-machine'); ?></th>
                <th><?php _e('Description', 'wp-state-machine'); ?></th>
                <th><?php _e('Workflow Group', 'wp-state-machine'); ?></th>
                <th><?php _e('Active', 'wp-state-machine'); ?></th>
                <th><?php _e('Created', 'wp-state-machine'); ?></th>
                <th><?php _e('Actions', 'wp-state-machine'); ?></th>
            </tr>
        </thead>
        <tbody>
        </tbody>
    </table>
</div>

<!-- Create/Edit Modal -->
<div id="machine-modal" class="wp-state-machine-modal" style="display:none;">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h2 id="modal-title"><?php _e('Add New State Machine', 'wp-state-machine'); ?></h2>
                <button type="button" class="modal-close" aria-label="<?php esc_attr_e('Close', 'wp-state-machine'); ?>">
                    <span class="dashicons dashicons-no"></span>
                </button>
            </div>
            <div class="modal-body">
                <form id="machine-form">
                    <input type="hidden" id="machine-id" name="id" value="">
                    <input type="hidden" id="machine-plugin-slug" name="plugin_slug" value="wp-state-machine">
                    <input type="hidden" id="machine-entity-type" name="entity_type" value="generic">

                    <table class="form-table">
                        <tr>
                            <th scope="row">
                                <label for="machine-name"><?php _e('Name', 'wp-state-machine'); ?> <span class="required">*</span></label>
                            </th>
                            <td>
                                <input type="text" id="machine-name" name="name" class="regular-text" required>
                                <p class="description"><?php _e('Display name for this state machine (e.g., "Order Workflow")', 'wp-state-machine'); ?></p>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row">
                                <label for="machine-slug"><?php _e('Slug', 'wp-state-machine'); ?> <span class="required">*</span></label>
                            </th>
                            <td>
                                <input type="text" id="machine-slug" name="slug" class="regular-text" pattern="[a-z0-9_\-]+" readonly required>
                                <p class="description">
                                    <span class="dashicons dashicons-info"></span>
                                    <?php _e('Auto-generated from name (cannot be changed after creation)', 'wp-state-machine'); ?>
                                </p>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row">
                                <label for="machine-description"><?php _e('Description', 'wp-state-machine'); ?></label>
                            </th>
                            <td>
                                <textarea id="machine-description" name="description" class="large-text" rows="3"></textarea>
                                <p class="description"><?php _e('Optional description of this state machine', 'wp-state-machine'); ?></p>
                            </td>
                        </tr>
                        <?php if (!empty($workflow_groups)): ?>
                        <tr>
                            <th scope="row">
                                <label for="machine-workflow-group"><?php _e('Workflow Group', 'wp-state-machine'); ?></label>
                            </th>
                            <td>
                                <select id="machine-workflow-group" name="workflow_group_id" class="regular-text">
                                    <option value=""><?php _e('None', 'wp-state-machine'); ?></option>
                                    <?php foreach ($workflow_groups as $group): ?>
                                        <option value="<?php echo esc_attr($group->id); ?>">
                                            <?php echo esc_html($group->name); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                                <p class="description"><?php _e('Optional grouping for organization', 'wp-state-machine'); ?></p>
                            </td>
                        </tr>
                        <?php endif; ?>
                        <tr>
                            <th scope="row">
                                <label for="machine-is-active"><?php _e('Active', 'wp-state-machine'); ?></label>
                            </th>
                            <td>
                                <label>
                                    <input type="checkbox" id="machine-is-active" name="is_active" value="1" checked>
                                    <?php _e('This state machine is active and available for use', 'wp-state-machine'); ?>
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
                <button type="button" class="button button-primary" id="btn-save-machine">
                    <?php _e('Save State Machine', 'wp-state-machine'); ?>
                </button>
            </div>
        </div>
    </div>
</div>

<!-- View Modal -->
<div id="view-machine-modal" class="wp-state-machine-modal" style="display:none;">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h2><?php _e('State Machine Details', 'wp-state-machine'); ?></h2>
                <button type="button" class="modal-close" aria-label="<?php esc_attr_e('Close', 'wp-state-machine'); ?>">
                    <span class="dashicons dashicons-no"></span>
                </button>
            </div>
            <div class="modal-body">
                <table class="form-table">
                    <tr>
                        <th><?php _e('ID', 'wp-state-machine'); ?>:</th>
                        <td id="view-machine-id"></td>
                    </tr>
                    <tr>
                        <th><?php _e('Name', 'wp-state-machine'); ?>:</th>
                        <td id="view-machine-name"></td>
                    </tr>
                    <tr>
                        <th><?php _e('Slug', 'wp-state-machine'); ?>:</th>
                        <td id="view-machine-slug"></td>
                    </tr>
                    <tr>
                        <th><?php _e('Description', 'wp-state-machine'); ?>:</th>
                        <td id="view-machine-description"></td>
                    </tr>
                    <tr>
                        <th><?php _e('Workflow Group', 'wp-state-machine'); ?>:</th>
                        <td id="view-machine-workflow-group"></td>
                    </tr>
                    <tr>
                        <th><?php _e('Active', 'wp-state-machine'); ?>:</th>
                        <td id="view-machine-is-active"></td>
                    </tr>
                    <tr>
                        <th><?php _e('Created', 'wp-state-machine'); ?>:</th>
                        <td id="view-machine-created"></td>
                    </tr>
                    <tr>
                        <th><?php _e('Updated', 'wp-state-machine'); ?>:</th>
                        <td id="view-machine-updated"></td>
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
