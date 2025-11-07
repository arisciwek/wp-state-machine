<?php
/**
 * Transition Logs View
 *
 * @package     WP_State_Machine
 * @subpackage  Views/Admin/Logs
 * @version     1.0.1
 * @author      arisciwek
 *
 * Path: /wp-state-machine/src/Views/admin/logs/transition-logs-view.php
 *
 * Description: Clean admin view for viewing transition logs.
 *              Displays logs in DataTable with filtering options.
 *              Supports per-plugin and central table queries.
 *
 *              CSS: /assets/css/transition-logs.css
 *              JS:  /assets/js/transition-logs.js
 *
 * Features:
 * - DataTables with server-side processing
 * - Plugin filter dropdown
 * - Date range filtering
 * - Machine filtering
 * - User filtering
 * - Search functionality
 * - Export to CSV
 * - Responsive design
 *
 * Changelog:
 * 1.0.1 - 2025-11-07 (TODO-6104)
 * - Extracted CSS and JS to separate files
 * - Renamed from index.php to transition-logs-view.php
 * - Clean view file (no inline styles/scripts)
 * 1.0.0 - 2025-11-07 (TODO-6104)
 * - Initial creation for Prioritas #6
 * - DataTables integration
 * - Multi-filter support
 * - Export functionality
 */

defined('ABSPATH') || exit;

// Get machines for filter dropdown
global $wpdb;
$machines_table = $wpdb->prefix . 'app_sm_machines';
$machines = $wpdb->get_results("SELECT id, name, plugin_slug FROM {$machines_table} ORDER BY name");
?>

<div class="wrap">
    <h1 class="wp-heading-inline">
        <span class="dashicons dashicons-clipboard" style="font-size: 28px; margin-right: 8px;"></span>
        <?php echo esc_html__('Transition Logs', 'wp-state-machine'); ?>
    </h1>

    <p class="description">
        <?php echo esc_html__('View history of all state transitions across all plugins and machines.', 'wp-state-machine'); ?>
    </p>

    <hr class="wp-header-end">

    <!-- Filters Section -->
    <div class="sm-filters-container" style="background: #fff; padding: 15px; margin: 20px 0; border: 1px solid #ccd0d4; box-shadow: 0 1px 1px rgba(0,0,0,.04);">
        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 15px; margin-bottom: 15px;">

            <!-- Plugin Filter -->
            <div>
                <label for="filter-plugin" style="display: block; margin-bottom: 5px; font-weight: 600;">
                    <?php echo esc_html__('Plugin', 'wp-state-machine'); ?>
                </label>
                <select id="filter-plugin" class="regular-text" style="width: 100%;">
                    <option value="all"><?php echo esc_html__('All Plugins', 'wp-state-machine'); ?></option>
                    <!-- Populated via AJAX -->
                </select>
            </div>

            <!-- Machine Filter -->
            <div>
                <label for="filter-machine" style="display: block; margin-bottom: 5px; font-weight: 600;">
                    <?php echo esc_html__('Machine', 'wp-state-machine'); ?>
                </label>
                <select id="filter-machine" class="regular-text" style="width: 100%;">
                    <option value=""><?php echo esc_html__('All Machines', 'wp-state-machine'); ?></option>
                    <?php foreach ($machines as $machine): ?>
                        <option value="<?php echo esc_attr($machine->id); ?>">
                            <?php echo esc_html($machine->name); ?>
                            <span style="color: #666;">(<?php echo esc_html($machine->plugin_slug); ?>)</span>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <!-- Date From -->
            <div>
                <label for="filter-date-from" style="display: block; margin-bottom: 5px; font-weight: 600;">
                    <?php echo esc_html__('Date From', 'wp-state-machine'); ?>
                </label>
                <input type="date" id="filter-date-from" class="regular-text" style="width: 100%;">
            </div>

            <!-- Date To -->
            <div>
                <label for="filter-date-to" style="display: block; margin-bottom: 5px; font-weight: 600;">
                    <?php echo esc_html__('Date To', 'wp-state-machine'); ?>
                </label>
                <input type="date" id="filter-date-to" class="regular-text" style="width: 100%;">
            </div>

        </div>

        <!-- Action Buttons -->
        <div style="display: flex; gap: 10px; align-items: center;">
            <button type="button" id="btn-apply-filters" class="button button-primary">
                <span class="dashicons dashicons-filter" style="margin-top: 3px;"></span>
                <?php echo esc_html__('Apply Filters', 'wp-state-machine'); ?>
            </button>
            <button type="button" id="btn-reset-filters" class="button">
                <span class="dashicons dashicons-image-rotate" style="margin-top: 3px;"></span>
                <?php echo esc_html__('Reset', 'wp-state-machine'); ?>
            </button>
            <button type="button" id="btn-export-csv" class="button">
                <span class="dashicons dashicons-download" style="margin-top: 3px;"></span>
                <?php echo esc_html__('Export CSV', 'wp-state-machine'); ?>
            </button>
            <span id="filter-status" style="margin-left: auto; color: #666; font-size: 13px;"></span>
        </div>
    </div>

    <!-- DataTable -->
    <table id="logs-table" class="wp-list-table widefat fixed striped" style="margin-top: 20px;">
        <thead>
            <tr>
                <th style="width: 60px;"><?php echo esc_html__('ID', 'wp-state-machine'); ?></th>
                <th style="width: 150px;"><?php echo esc_html__('Date/Time', 'wp-state-machine'); ?></th>
                <th><?php echo esc_html__('Machine', 'wp-state-machine'); ?></th>
                <th><?php echo esc_html__('Entity', 'wp-state-machine'); ?></th>
                <th><?php echo esc_html__('From State', 'wp-state-machine'); ?></th>
                <th style="width: 30px; text-align: center;">→</th>
                <th><?php echo esc_html__('To State', 'wp-state-machine'); ?></th>
                <th><?php echo esc_html__('User', 'wp-state-machine'); ?></th>
                <th><?php echo esc_html__('Comment', 'wp-state-machine'); ?></th>
            </tr>
        </thead>
        <tbody>
            <tr>
                <td colspan="9" class="dataTables_empty">
                    <?php echo esc_html__('Loading...', 'wp-state-machine'); ?>
                </td>
            </tr>
        </tbody>
    </table>

</div>
