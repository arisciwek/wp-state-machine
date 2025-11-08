<?php
/**
 * States Admin View
 *
 * @package     WP_State_Machine
 * @subpackage  Views/Admin/States
 * @version     1.0.0
 * @author      arisciwek
 *
 * Path: /wp-state-machine/src/Views/admin/states/index.php
 *
 * Description: Admin interface for managing state machine states.
 *              Includes DataTable listing, CRUD modals, and machine filtering.
 *              Follows wp-agency admin view pattern.
 *
 * Changelog:
 * 1.0.0 - 2025-11-07
 * - Initial creation
 * - DataTable integration
 * - CRUD modal forms
 * - Machine filter dropdown
 */

defined('ABSPATH') || exit;

// Get all machines for the filter dropdown
global $wpdb;
$machine_table = $wpdb->prefix . 'app_sm_machines';
$machines = $wpdb->get_results("SELECT id, name FROM {$machine_table} WHERE is_active = 1 ORDER BY name ASC");
?>

<div class="wrap wp-state-machine-admin">
    <h1 class="wp-heading-inline"><?php _e('States', 'wp-state-machine'); ?></h1>
    <button type="button" class="page-title-action" id="btn-add-state">
        <?php _e('Add New State', 'wp-state-machine'); ?>
    </button>
    <hr class="wp-header-end">

    <!-- Machine Filter -->
    <div class="tablenav top">
        <div class="alignleft actions">
            <label for="filter-machine" class="screen-reader-text">
                <?php _e('Filter by machine', 'wp-state-machine'); ?>
            </label>
            <select name="machine_id" id="filter-machine">
                <option value=""><?php _e('All Machines', 'wp-state-machine'); ?></option>
                <?php foreach ($machines as $machine): ?>
                    <option value="<?php echo esc_attr($machine->id); ?>">
                        <?php echo esc_html($machine->name); ?>
                    </option>
                <?php endforeach; ?>
            </select>
            <button type="button" class="button" id="btn-filter">
                <?php _e('Filter', 'wp-state-machine'); ?>
            </button>
        </div>
    </div>

    <!-- DataTable -->
    <table id="states-table" class="wp-list-table widefat fixed striped">
        <thead>
            <tr>
                <th><?php _e('ID', 'wp-state-machine'); ?></th>
                <th><?php _e('Name', 'wp-state-machine'); ?></th>
                <th><?php _e('Slug', 'wp-state-machine'); ?></th>
                <th><?php _e('Type', 'wp-state-machine'); ?></th>
                <th><?php _e('Color', 'wp-state-machine'); ?></th>
                <th><?php _e('Sort Order', 'wp-state-machine'); ?></th>
                <th><?php _e('Machine', 'wp-state-machine'); ?></th>
                <th><?php _e('Created', 'wp-state-machine'); ?></th>
                <th><?php _e('Actions', 'wp-state-machine'); ?></th>
            </tr>
        </thead>
        <tbody>
        </tbody>
    </table>
</div>

<!-- Create/Edit Modal -->
<div id="state-modal" class="wp-state-machine-modal" style="display:none;">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h2 id="modal-title"><?php _e('Add New State', 'wp-state-machine'); ?></h2>
                <button type="button" class="modal-close" aria-label="<?php esc_attr_e('Close', 'wp-state-machine'); ?>">
                    <span class="dashicons dashicons-no"></span>
                </button>
            </div>
            <div class="modal-body">
                <form id="state-form">
                    <input type="hidden" id="state-id" name="id" value="">

                    <table class="form-table">
                        <tr>
                            <th scope="row">
                                <label for="state-machine-id"><?php _e('Machine', 'wp-state-machine'); ?> <span class="required">*</span></label>
                            </th>
                            <td>
                                <select id="state-machine-id" name="machine_id" class="regular-text" required>
                                    <option value=""><?php _e('Select Machine', 'wp-state-machine'); ?></option>
                                    <?php foreach ($machines as $machine): ?>
                                        <option value="<?php echo esc_attr($machine->id); ?>">
                                            <?php echo esc_html($machine->name); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                                <p class="description"><?php _e('The state machine this state belongs to', 'wp-state-machine'); ?></p>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row">
                                <label for="state-name"><?php _e('Name', 'wp-state-machine'); ?> <span class="required">*</span></label>
                            </th>
                            <td>
                                <input type="text" id="state-name" name="name" class="regular-text" required>
                                <p class="description"><?php _e('Display name for this state (e.g., "Pending Approval")', 'wp-state-machine'); ?></p>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row">
                                <label for="state-slug"><?php _e('Slug', 'wp-state-machine'); ?> <span class="required">*</span></label>
                            </th>
                            <td>
                                <input type="text" id="state-slug" name="slug" class="regular-text" pattern="[a-z0-9_\-]+" readonly required>
                                <p class="description" id="slug-description">
                                    <span class="dashicons dashicons-info"></span>
                                    <?php _e('Auto-generated from name (cannot be changed after creation)', 'wp-state-machine'); ?>
                                </p>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row">
                                <label for="state-type"><?php _e('Type', 'wp-state-machine'); ?> <span class="required">*</span></label>
                            </th>
                            <td>
                                <select id="state-type" name="type" class="regular-text" required>
                                    <option value="normal"><?php _e('Normal', 'wp-state-machine'); ?></option>
                                    <option value="initial"><?php _e('Initial', 'wp-state-machine'); ?></option>
                                    <option value="intermediate"><?php _e('Intermediate', 'wp-state-machine'); ?></option>
                                    <option value="final"><?php _e('Final', 'wp-state-machine'); ?></option>
                                </select>
                                <p class="description">
                                    <?php _e('Initial: Starting state | Normal/Intermediate: Regular state | Final: End state', 'wp-state-machine'); ?>
                                </p>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row">
                                <label for="state-color"><?php _e('Color', 'wp-state-machine'); ?></label>
                            </th>
                            <td>
                                <input type="color" id="state-color" name="color" value="#3498db">
                                <p class="description"><?php _e('Color for visual representation (e.g., in diagrams)', 'wp-state-machine'); ?></p>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row">
                                <label for="state-sort-order"><?php _e('Sort Order', 'wp-state-machine'); ?></label>
                            </th>
                            <td>
                                <input type="number" id="state-sort-order" name="sort_order" class="small-text" value="0" min="0">
                                <p class="description"><?php _e('Display order (lower numbers appear first)', 'wp-state-machine'); ?></p>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row">
                                <label for="state-metadata"><?php _e('Metadata', 'wp-state-machine'); ?></label>
                            </th>
                            <td>
                                <textarea id="state-metadata" name="metadata" class="large-text code" rows="4"></textarea>
                                <p class="description"><?php _e('Optional JSON metadata for custom data', 'wp-state-machine'); ?></p>
                            </td>
                        </tr>
                    </table>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="button button-secondary modal-close">
                    <?php _e('Cancel', 'wp-state-machine'); ?>
                </button>
                <button type="button" class="button button-primary" id="btn-save-state">
                    <?php _e('Save State', 'wp-state-machine'); ?>
                </button>
            </div>
        </div>
    </div>
</div>

<!-- View Modal -->
<div id="view-state-modal" class="wp-state-machine-modal" style="display:none;">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h2><?php _e('State Details', 'wp-state-machine'); ?></h2>
                <button type="button" class="modal-close" aria-label="<?php esc_attr_e('Close', 'wp-state-machine'); ?>">
                    <span class="dashicons dashicons-no"></span>
                </button>
            </div>
            <div class="modal-body">
                <table class="form-table">
                    <tr>
                        <th><?php _e('ID', 'wp-state-machine'); ?>:</th>
                        <td id="view-state-id"></td>
                    </tr>
                    <tr>
                        <th><?php _e('Machine', 'wp-state-machine'); ?>:</th>
                        <td id="view-state-machine"></td>
                    </tr>
                    <tr>
                        <th><?php _e('Name', 'wp-state-machine'); ?>:</th>
                        <td id="view-state-name"></td>
                    </tr>
                    <tr>
                        <th><?php _e('Slug', 'wp-state-machine'); ?>:</th>
                        <td id="view-state-slug"></td>
                    </tr>
                    <tr>
                        <th><?php _e('Type', 'wp-state-machine'); ?>:</th>
                        <td id="view-state-type"></td>
                    </tr>
                    <tr>
                        <th><?php _e('Color', 'wp-state-machine'); ?>:</th>
                        <td id="view-state-color"></td>
                    </tr>
                    <tr>
                        <th><?php _e('Sort Order', 'wp-state-machine'); ?>:</th>
                        <td id="view-state-sort-order"></td>
                    </tr>
                    <tr>
                        <th><?php _e('Metadata', 'wp-state-machine'); ?>:</th>
                        <td><pre id="view-state-metadata"></pre></td>
                    </tr>
                    <tr>
                        <th><?php _e('Created', 'wp-state-machine'); ?>:</th>
                        <td id="view-state-created"></td>
                    </tr>
                    <tr>
                        <th><?php _e('Updated', 'wp-state-machine'); ?>:</th>
                        <td id="view-state-updated"></td>
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

<!-- Assets (states.css and states.js) are loaded via class-dependencies.php -->
<!-- JavaScript data is localized via wpStateMachineStatesData -->
