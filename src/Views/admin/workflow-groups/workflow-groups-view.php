<?php
/**
 * Workflow Groups View
 *
 * @package     WP_State_Machine
 * @subpackage  Views/Admin/WorkflowGroups
 * @version     1.0.0
 * @author      arisciwek
 *
 * Path: /wp-state-machine/src/Views/admin/workflow-groups/workflow-groups-view.php
 *
 * Description: Admin view for managing workflow groups.
 *              Displays groups in DataTable with CRUD operations.
 *              Supports drag-drop sorting (future enhancement).
 *
 *              CSS: /assets/css/workflow-groups.css
 *              JS:  /assets/js/workflow-groups.js
 *
 * Features:
 * - DataTables with server-side processing
 * - Add/Edit/Delete groups
 * - Active/Inactive toggle
 * - Machine count display
 * - Dashicon selector
 * - Responsive design
 *
 * Changelog:
 * 1.0.0 - 2025-11-07 (TODO-6102 PRIORITAS #7)
 * - Initial creation for FASE 3
 * - DataTables integration
 * - CRUD modals
 * - Machine count tracking
 */

defined('ABSPATH') || exit;
?>

<div class="wrap">
    <h1 class="wp-heading-inline">
        <span class="dashicons dashicons-networking" style="font-size: 28px; margin-right: 8px;"></span>
        <?php echo esc_html__('Workflow Groups', 'wp-state-machine'); ?>
    </h1>

    <button type="button" class="page-title-action" id="btn-add-group">
        <span class="dashicons dashicons-plus-alt" style="margin-top: 3px;"></span>
        <?php echo esc_html__('Add New Group', 'wp-state-machine'); ?>
    </button>

    <p class="description">
        <?php echo esc_html__('Organize state machines into logical groups for better management and clarity.', 'wp-state-machine'); ?>
    </p>

    <hr class="wp-header-end">

    <!-- DataTable -->
    <table id="workflow-groups-table" class="wp-list-table widefat fixed striped" style="margin-top: 20px;">
        <thead>
            <tr>
                <th style="width: 60px;"><?php echo esc_html__('ID', 'wp-state-machine'); ?></th>
                <th style="width: 50px;"><?php echo esc_html__('Icon', 'wp-state-machine'); ?></th>
                <th><?php echo esc_html__('Name', 'wp-state-machine'); ?></th>
                <th><?php echo esc_html__('Slug', 'wp-state-machine'); ?></th>
                <th style="width: 120px;"><?php echo esc_html__('Machines', 'wp-state-machine'); ?></th>
                <th style="width: 100px;"><?php echo esc_html__('Sort Order', 'wp-state-machine'); ?></th>
                <th style="width: 100px;"><?php echo esc_html__('Status', 'wp-state-machine'); ?></th>
                <th style="width: 150px;"><?php echo esc_html__('Actions', 'wp-state-machine'); ?></th>
            </tr>
        </thead>
        <tbody>
            <tr>
                <td colspan="8" class="dataTables_empty">
                    <?php echo esc_html__('Loading...', 'wp-state-machine'); ?>
                </td>
            </tr>
        </tbody>
    </table>

</div>

<!-- Add/Edit Group Modal -->
<div id="group-modal" class="sm-modal" style="display: none;">
    <div class="sm-modal-content">
        <div class="sm-modal-header">
            <h2 id="modal-title"><?php echo esc_html__('Add Workflow Group', 'wp-state-machine'); ?></h2>
            <button type="button" class="sm-modal-close">&times;</button>
        </div>

        <form id="group-form">
            <input type="hidden" id="group-id" name="id" value="">

            <div class="sm-form-row">
                <label for="group-name">
                    <?php echo esc_html__('Group Name', 'wp-state-machine'); ?>
                    <span class="required">*</span>
                </label>
                <input type="text" id="group-name" name="name" class="regular-text" required>
                <span class="error-message" id="error-name"></span>
            </div>

            <div class="sm-form-row">
                <label for="group-slug">
                    <?php echo esc_html__('Slug', 'wp-state-machine'); ?>
                    <span class="required">*</span>
                </label>
                <input type="text" id="group-slug" name="slug" class="regular-text" required>
                <span class="error-message" id="error-slug"></span>
                <p class="description">
                    <?php echo esc_html__('URL-friendly identifier (lowercase, numbers, hyphens only)', 'wp-state-machine'); ?>
                </p>
            </div>

            <div class="sm-form-row">
                <label for="group-description">
                    <?php echo esc_html__('Description', 'wp-state-machine'); ?>
                </label>
                <textarea id="group-description" name="description" class="large-text" rows="3"></textarea>
                <span class="error-message" id="error-description"></span>
            </div>

            <div class="sm-form-row">
                <label for="group-icon">
                    <?php echo esc_html__('Icon', 'wp-state-machine'); ?>
                </label>
                <input type="text" id="group-icon" name="icon" class="regular-text" value="dashicons-networking">
                <span class="error-message" id="error-icon"></span>
                <p class="description">
                    <?php echo esc_html__('Dashicon class (e.g., dashicons-networking, dashicons-chart-pie)', 'wp-state-machine'); ?>
                    <a href="https://developer.wordpress.org/resource/dashicons/" target="_blank">
                        <?php echo esc_html__('Browse Dashicons', 'wp-state-machine'); ?>
                    </a>
                </p>
            </div>

            <div class="sm-form-row">
                <label for="group-sort-order">
                    <?php echo esc_html__('Sort Order', 'wp-state-machine'); ?>
                </label>
                <input type="number" id="group-sort-order" name="sort_order" class="small-text" value="0" min="0">
                <span class="error-message" id="error-sort_order"></span>
                <p class="description">
                    <?php echo esc_html__('Lower numbers appear first', 'wp-state-machine'); ?>
                </p>
            </div>

            <div class="sm-form-row">
                <label>
                    <input type="checkbox" id="group-is-active" name="is_active" value="1" checked>
                    <?php echo esc_html__('Active', 'wp-state-machine'); ?>
                </label>
                <p class="description">
                    <?php echo esc_html__('Inactive groups will not be shown in dropdown selections', 'wp-state-machine'); ?>
                </p>
            </div>

            <div class="sm-modal-footer">
                <button type="button" class="button" id="btn-cancel">
                    <?php echo esc_html__('Cancel', 'wp-state-machine'); ?>
                </button>
                <button type="submit" class="button button-primary" id="btn-save">
                    <span class="dashicons dashicons-yes" style="margin-top: 3px;"></span>
                    <?php echo esc_html__('Save Group', 'wp-state-machine'); ?>
                </button>
            </div>
        </form>
    </div>
</div>

<!-- View Group Details Modal -->
<div id="view-group-modal" class="sm-modal" style="display: none;">
    <div class="sm-modal-content">
        <div class="sm-modal-header">
            <h2><?php echo esc_html__('Group Details', 'wp-state-machine'); ?></h2>
            <button type="button" class="sm-modal-close">&times;</button>
        </div>

        <div class="sm-modal-body">
            <table class="form-table">
                <tr>
                    <th><?php echo esc_html__('ID', 'wp-state-machine'); ?>:</th>
                    <td id="view-id"></td>
                </tr>
                <tr>
                    <th><?php echo esc_html__('Icon', 'wp-state-machine'); ?>:</th>
                    <td><span id="view-icon-preview" class="dashicons"></span> <span id="view-icon"></span></td>
                </tr>
                <tr>
                    <th><?php echo esc_html__('Name', 'wp-state-machine'); ?>:</th>
                    <td id="view-name"></td>
                </tr>
                <tr>
                    <th><?php echo esc_html__('Slug', 'wp-state-machine'); ?>:</th>
                    <td><code id="view-slug"></code></td>
                </tr>
                <tr>
                    <th><?php echo esc_html__('Description', 'wp-state-machine'); ?>:</th>
                    <td id="view-description"></td>
                </tr>
                <tr>
                    <th><?php echo esc_html__('Sort Order', 'wp-state-machine'); ?>:</th>
                    <td id="view-sort-order"></td>
                </tr>
                <tr>
                    <th><?php echo esc_html__('Status', 'wp-state-machine'); ?>:</th>
                    <td id="view-status"></td>
                </tr>
                <tr>
                    <th><?php echo esc_html__('Assigned Machines', 'wp-state-machine'); ?>:</th>
                    <td id="view-machines"></td>
                </tr>
                <tr>
                    <th><?php echo esc_html__('Created', 'wp-state-machine'); ?>:</th>
                    <td id="view-created"></td>
                </tr>
                <tr>
                    <th><?php echo esc_html__('Modified', 'wp-state-machine'); ?>:</th>
                    <td id="view-updated"></td>
                </tr>
            </table>
        </div>

        <div class="sm-modal-footer">
            <button type="button" class="button" id="btn-close-view">
                <?php echo esc_html__('Close', 'wp-state-machine'); ?>
            </button>
        </div>
    </div>
</div>
