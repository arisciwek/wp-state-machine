<?php
/**
 * Settings View Template
 *
 * @package     WP_State_Machine
 * @subpackage  Views/Admin/Settings
 * @version     1.0.0
 * @author      arisciwek
 *
 * Path: /wp-state-machine/src/Views/admin/settings/settings-view.php
 *
 * Description: Clean view template untuk settings page dengan multi-tab interface.
 *              No inline CSS/JS - all assets separated and managed by class-dependencies.php
 *
 * Variables Available:
 * @var array $settings Current settings array
 * @var array $tabs Tab configuration array
 * @var string $nonce Security nonce
 *
 * Changelog:
 * 1.0.0 - 2025-11-07
 * - Initial creation
 * - Multi-tab interface: General, Permissions, Cache, Database
 * - Clean HTML structure
 * - Assets separated
 */

defined('ABSPATH') || exit;
?>

<div class="wrap sm-settings-wrap">
    <h1 class="wp-heading-inline">
        <span class="dashicons dashicons-admin-settings"></span>
        <?php echo esc_html__('State Machine Settings', 'wp-state-machine'); ?>
    </h1>

    <hr class="wp-header-end">

    <!-- Tab Navigation -->
    <nav class="nav-tab-wrapper wp-clearfix sm-settings-tabs">
        <?php foreach ($tabs as $tab_id => $tab): ?>
            <a href="#<?php echo esc_attr($tab_id); ?>"
               class="nav-tab <?php echo $tab_id === 'general' ? 'nav-tab-active' : ''; ?>"
               data-tab="<?php echo esc_attr($tab_id); ?>">
                <span class="dashicons <?php echo esc_attr($tab['icon']); ?>"></span>
                <?php echo esc_html($tab['label']); ?>
            </a>
        <?php endforeach; ?>
    </nav>

    <!-- Settings Form -->
    <form id="settings-form" method="post">
        <input type="hidden" name="nonce" id="settings-nonce" value="<?php echo esc_attr($nonce); ?>">
        <input type="hidden" name="active_tab" id="active-tab" value="general">

        <!-- General Tab -->
        <div id="general" class="sm-settings-tab active">
            <div class="sm-settings-section">
                <h2><?php echo esc_html__('General Settings', 'wp-state-machine'); ?></h2>

                <table class="form-table" role="presentation">
                    <tbody>
                        <tr>
                            <th scope="row">
                                <label for="enable_logging">
                                    <?php echo esc_html__('Enable Logging', 'wp-state-machine'); ?>
                                </label>
                            </th>
                            <td>
                                <label class="sm-switch">
                                    <input type="checkbox"
                                           id="enable_logging"
                                           name="settings[enable_logging]"
                                           value="1"
                                           <?php checked($settings['enable_logging'], true); ?>>
                                    <span class="sm-slider"></span>
                                </label>
                                <p class="description">
                                    <?php echo esc_html__('Enable transition logging for all state machines', 'wp-state-machine'); ?>
                                </p>
                            </td>
                        </tr>

                        <tr>
                            <th scope="row">
                                <label for="log_retention_days">
                                    <?php echo esc_html__('Log Retention (Days)', 'wp-state-machine'); ?>
                                </label>
                            </th>
                            <td>
                                <input type="number"
                                       id="log_retention_days"
                                       name="settings[log_retention_days]"
                                       value="<?php echo esc_attr($settings['log_retention_days']); ?>"
                                       min="1"
                                       max="365"
                                       class="small-text">
                                <p class="description">
                                    <?php echo esc_html__('Number of days to keep transition logs (1-365)', 'wp-state-machine'); ?>
                                </p>
                            </td>
                        </tr>

                        <tr>
                            <th scope="row">
                                <label for="enable_notifications">
                                    <?php echo esc_html__('Enable Notifications', 'wp-state-machine'); ?>
                                </label>
                            </th>
                            <td>
                                <label class="sm-switch">
                                    <input type="checkbox"
                                           id="enable_notifications"
                                           name="settings[enable_notifications]"
                                           value="1"
                                           <?php checked($settings['enable_notifications'], true); ?>>
                                    <span class="sm-slider"></span>
                                </label>
                                <p class="description">
                                    <?php echo esc_html__('Enable email notifications for transition events', 'wp-state-machine'); ?>
                                </p>
                            </td>
                        </tr>

                        <tr>
                            <th scope="row">
                                <label for="notification_email">
                                    <?php echo esc_html__('Notification Email', 'wp-state-machine'); ?>
                                </label>
                            </th>
                            <td>
                                <input type="email"
                                       id="notification_email"
                                       name="settings[notification_email]"
                                       value="<?php echo esc_attr($settings['notification_email']); ?>"
                                       class="regular-text"
                                       placeholder="<?php echo esc_attr(get_option('admin_email')); ?>">
                                <p class="description">
                                    <?php echo esc_html__('Email address for notifications (leave blank to use admin email)', 'wp-state-machine'); ?>
                                </p>
                            </td>
                        </tr>
                    </tbody>
                </table>

                <p class="submit">
                    <button type="submit" class="button button-primary sm-save-settings">
                        <?php echo esc_html__('Save General Settings', 'wp-state-machine'); ?>
                    </button>
                </p>
            </div>
        </div>

        <!-- Permissions Tab -->
        <div id="permissions" class="sm-settings-tab">
            <div class="sm-settings-section">
                <h2><?php echo esc_html__('Permission Settings', 'wp-state-machine'); ?></h2>

                <table class="form-table" role="presentation">
                    <tbody>
                        <tr>
                            <th scope="row">
                                <label for="allow_plugin_manage_permissions">
                                    <?php echo esc_html__('Plugin Permission Management', 'wp-state-machine'); ?>
                                </label>
                            </th>
                            <td>
                                <label class="sm-switch">
                                    <input type="checkbox"
                                           id="allow_plugin_manage_permissions"
                                           name="settings[allow_plugin_manage_permissions]"
                                           value="1"
                                           <?php checked($settings['allow_plugin_manage_permissions'], true); ?>>
                                    <span class="sm-slider"></span>
                                </label>
                                <p class="description">
                                    <?php echo esc_html__('Allow plugins to define their own permission requirements', 'wp-state-machine'); ?>
                                </p>
                            </td>
                        </tr>

                        <tr>
                            <th scope="row">
                                <label for="default_view_capability">
                                    <?php echo esc_html__('Default View Capability', 'wp-state-machine'); ?>
                                </label>
                            </th>
                            <td>
                                <input type="text"
                                       id="default_view_capability"
                                       name="settings[default_view_capability]"
                                       value="<?php echo esc_attr($settings['default_view_capability']); ?>"
                                       class="regular-text">
                                <p class="description">
                                    <?php echo esc_html__('Default capability required to view state machines', 'wp-state-machine'); ?>
                                </p>
                            </td>
                        </tr>

                        <tr>
                            <th scope="row">
                                <label for="default_edit_capability">
                                    <?php echo esc_html__('Default Edit Capability', 'wp-state-machine'); ?>
                                </label>
                            </th>
                            <td>
                                <input type="text"
                                       id="default_edit_capability"
                                       name="settings[default_edit_capability]"
                                       value="<?php echo esc_attr($settings['default_edit_capability']); ?>"
                                       class="regular-text">
                                <p class="description">
                                    <?php echo esc_html__('Default capability required to edit state machines', 'wp-state-machine'); ?>
                                </p>
                            </td>
                        </tr>
                    </tbody>
                </table>

                <div class="sm-info-box">
                    <h4><?php echo esc_html__('Available Capabilities:', 'wp-state-machine'); ?></h4>
                    <ul>
                        <li><code>view_state_machines</code> - View state machines</li>
                        <li><code>edit_state_machines</code> - Edit state machines</li>
                        <li><code>delete_state_machines</code> - Delete state machines</li>
                        <li><code>view_state_machine_logs</code> - View transition logs</li>
                        <li><code>manage_state_machine_settings</code> - Manage plugin settings</li>
                    </ul>
                </div>

                <p class="submit">
                    <button type="submit" class="button button-primary sm-save-settings">
                        <?php echo esc_html__('Save Permission Settings', 'wp-state-machine'); ?>
                    </button>
                </p>
            </div>
        </div>

        <!-- Cache Tab -->
        <div id="cache" class="sm-settings-tab">
            <div class="sm-settings-section">
                <h2><?php echo esc_html__('Cache Settings', 'wp-state-machine'); ?></h2>

                <table class="form-table" role="presentation">
                    <tbody>
                        <tr>
                            <th scope="row">
                                <label for="enable_cache">
                                    <?php echo esc_html__('Enable Cache', 'wp-state-machine'); ?>
                                </label>
                            </th>
                            <td>
                                <label class="sm-switch">
                                    <input type="checkbox"
                                           id="enable_cache"
                                           name="settings[enable_cache]"
                                           value="1"
                                           <?php checked($settings['enable_cache'], true); ?>>
                                    <span class="sm-slider"></span>
                                </label>
                                <p class="description">
                                    <?php echo esc_html__('Enable caching for state machine data', 'wp-state-machine'); ?>
                                </p>
                            </td>
                        </tr>

                        <tr>
                            <th scope="row">
                                <label for="cache_expiration">
                                    <?php echo esc_html__('Cache Expiration (Seconds)', 'wp-state-machine'); ?>
                                </label>
                            </th>
                            <td>
                                <input type="number"
                                       id="cache_expiration"
                                       name="settings[cache_expiration]"
                                       value="<?php echo esc_attr($settings['cache_expiration']); ?>"
                                       min="300"
                                       max="86400"
                                       class="small-text">
                                <p class="description">
                                    <?php echo esc_html__('Cache expiration time in seconds (300-86400)', 'wp-state-machine'); ?>
                                    <br>
                                    <?php echo esc_html__('Default: 3600 (1 hour)', 'wp-state-machine'); ?>
                                </p>
                            </td>
                        </tr>

                        <tr>
                            <th scope="row">
                                <?php echo esc_html__('Clear Cache', 'wp-state-machine'); ?>
                            </th>
                            <td>
                                <button type="button" class="button button-secondary" id="btn-clear-cache">
                                    <span class="dashicons dashicons-trash"></span>
                                    <?php echo esc_html__('Clear All Cache', 'wp-state-machine'); ?>
                                </button>
                                <p class="description">
                                    <?php echo esc_html__('Clear all cached state machine data', 'wp-state-machine'); ?>
                                </p>
                            </td>
                        </tr>
                    </tbody>
                </table>

                <p class="submit">
                    <button type="submit" class="button button-primary sm-save-settings">
                        <?php echo esc_html__('Save Cache Settings', 'wp-state-machine'); ?>
                    </button>
                </p>
            </div>
        </div>

        <!-- Database Tab -->
        <div id="database" class="sm-settings-tab">
            <div class="sm-settings-section">
                <h2><?php echo esc_html__('Database Settings', 'wp-state-machine'); ?></h2>

                <table class="form-table" role="presentation">
                    <tbody>
                        <tr>
                            <th scope="row">
                                <label for="auto_cleanup_enabled">
                                    <?php echo esc_html__('Auto Cleanup', 'wp-state-machine'); ?>
                                </label>
                            </th>
                            <td>
                                <label class="sm-switch">
                                    <input type="checkbox"
                                           id="auto_cleanup_enabled"
                                           name="settings[auto_cleanup_enabled]"
                                           value="1"
                                           <?php checked($settings['auto_cleanup_enabled'], true); ?>>
                                    <span class="sm-slider"></span>
                                </label>
                                <p class="description">
                                    <?php echo esc_html__('Automatically cleanup old logs based on schedule', 'wp-state-machine'); ?>
                                </p>
                            </td>
                        </tr>

                        <tr>
                            <th scope="row">
                                <label for="cleanup_frequency">
                                    <?php echo esc_html__('Cleanup Frequency', 'wp-state-machine'); ?>
                                </label>
                            </th>
                            <td>
                                <select id="cleanup_frequency" name="settings[cleanup_frequency]">
                                    <option value="daily" <?php selected($settings['cleanup_frequency'], 'daily'); ?>>
                                        <?php echo esc_html__('Daily', 'wp-state-machine'); ?>
                                    </option>
                                    <option value="weekly" <?php selected($settings['cleanup_frequency'], 'weekly'); ?>>
                                        <?php echo esc_html__('Weekly', 'wp-state-machine'); ?>
                                    </option>
                                    <option value="monthly" <?php selected($settings['cleanup_frequency'], 'monthly'); ?>>
                                        <?php echo esc_html__('Monthly', 'wp-state-machine'); ?>
                                    </option>
                                </select>
                                <p class="description">
                                    <?php echo esc_html__('How often to run automatic cleanup', 'wp-state-machine'); ?>
                                </p>
                            </td>
                        </tr>

                        <tr>
                            <th scope="row">
                                <label for="keep_logs_days">
                                    <?php echo esc_html__('Keep Logs (Days)', 'wp-state-machine'); ?>
                                </label>
                            </th>
                            <td>
                                <input type="number"
                                       id="keep_logs_days"
                                       name="settings[keep_logs_days]"
                                       value="<?php echo esc_attr($settings['keep_logs_days']); ?>"
                                       min="7"
                                       max="365"
                                       class="small-text">
                                <p class="description">
                                    <?php echo esc_html__('Number of days to keep logs before cleanup (7-365)', 'wp-state-machine'); ?>
                                </p>
                            </td>
                        </tr>

                        <tr>
                            <th scope="row">
                                <?php echo esc_html__('Manual Cleanup', 'wp-state-machine'); ?>
                            </th>
                            <td>
                                <button type="button" class="button button-secondary" id="btn-cleanup-logs">
                                    <span class="dashicons dashicons-database-remove"></span>
                                    <?php echo esc_html__('Cleanup Old Logs Now', 'wp-state-machine'); ?>
                                </button>
                                <p class="description">
                                    <?php echo esc_html__('Manually delete logs older than specified days', 'wp-state-machine'); ?>
                                </p>
                            </td>
                        </tr>

                        <tr>
                            <th scope="row">
                                <?php echo esc_html__('Database Statistics', 'wp-state-machine'); ?>
                            </th>
                            <td>
                                <button type="button" class="button button-secondary" id="btn-load-stats">
                                    <span class="dashicons dashicons-chart-bar"></span>
                                    <?php echo esc_html__('Load Statistics', 'wp-state-machine'); ?>
                                </button>
                                <div id="database-stats" class="sm-stats-container" style="display:none; margin-top:15px;">
                                    <!-- Stats will be loaded here via AJAX -->
                                </div>
                            </td>
                        </tr>
                    </tbody>
                </table>

                <p class="submit">
                    <button type="submit" class="button button-primary sm-save-settings">
                        <?php echo esc_html__('Save Database Settings', 'wp-state-machine'); ?>
                    </button>
                </p>
            </div>
        </div>
    </form>

    <!-- Toast Notification -->
    <div id="sm-toast" class="sm-toast"></div>
</div>
