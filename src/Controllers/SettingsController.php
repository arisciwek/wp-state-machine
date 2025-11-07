<?php
/**
 * Settings Controller
 *
 * @package     WP_State_Machine
 * @subpackage  Controllers
 * @version     1.0.0
 * @author      arisciwek
 *
 * Path: /wp-state-machine/src/Controllers/SettingsController.php
 *
 * Description: Menangani pengaturan plugin State Machine.
 *              Includes General, Permissions, Cache, dan Database settings.
 *              Follows clean MVC pattern dengan separated assets.
 *
 * Changelog:
 * 1.0.0 - 2025-11-07
 * - Initial creation
 * - Multi-tab settings interface
 * - WordPress Settings API integration
 * - Cache management
 * - Database cleanup options
 */

namespace WPStateMachine\Controllers;

use WPStateMachine\Cache\StateMachineCacheManager;

defined('ABSPATH') || exit;

class SettingsController {
    /**
     * Cache manager instance
     *
     * @var StateMachineCacheManager
     */
    private $cache;

    /**
     * Settings option name
     *
     * @var string
     */
    private $option_name = 'wp_state_machine_settings';

    /**
     * Default settings
     *
     * @var array
     */
    private $default_settings = [
        // General settings
        'enable_logging' => true,
        'log_retention_days' => 90,
        'enable_notifications' => true,
        'notification_email' => '',

        // Cache settings
        'enable_cache' => true,
        'cache_expiration' => 3600, // 1 hour

        // Permission settings
        'allow_plugin_manage_permissions' => true,
        'default_view_capability' => 'view_state_machines',
        'default_edit_capability' => 'edit_state_machines',

        // Database settings
        'auto_cleanup_enabled' => false,
        'cleanup_frequency' => 'monthly',
        'keep_logs_days' => 90,
    ];

    /**
     * Constructor
     */
    public function __construct() {
        $this->cache = new StateMachineCacheManager();

        // Register AJAX handlers
        $this->registerAjaxHandlers();

        // Register settings
        add_action('admin_init', [$this, 'registerSettings']);
    }

    /**
     * Register AJAX handlers
     *
     * @return void
     */
    private function registerAjaxHandlers() {
        add_action('wp_ajax_save_state_machine_settings', [$this, 'saveSettings']);
        add_action('wp_ajax_clear_all_cache', [$this, 'clearAllCache']);
        add_action('wp_ajax_cleanup_old_logs', [$this, 'cleanupOldLogs']);
        add_action('wp_ajax_get_database_stats', [$this, 'getDatabaseStats']);
    }

    /**
     * Register WordPress settings
     *
     * @return void
     */
    public function registerSettings() {
        register_setting(
            'wp_state_machine_settings',
            $this->option_name,
            [
                'type' => 'array',
                'sanitize_callback' => [$this, 'sanitizeSettings'],
                'default' => $this->default_settings,
            ]
        );
    }

    /**
     * Render settings page
     *
     * @return void
     */
    public function renderPage() {
        // Assets enqueued by class-dependencies.php

        // Get current settings
        $settings = $this->getSettings();

        // Prepare data for view
        $data = [
            'settings' => $settings,
            'tabs' => $this->getTabs(),
            'nonce' => wp_create_nonce('wp_state_machine_nonce'),
        ];

        // Load view
        $view_path = WP_STATE_MACHINE_PATH . 'src/Views/admin/settings/settings-view.php';
        extract($data);
        include $view_path;
    }

    /**
     * Get settings tabs configuration
     *
     * @return array
     */
    private function getTabs() {
        return [
            'general' => [
                'id' => 'general',
                'label' => __('General', 'wp-state-machine'),
                'icon' => 'dashicons-admin-settings',
            ],
            'permissions' => [
                'id' => 'permissions',
                'label' => __('Permissions', 'wp-state-machine'),
                'icon' => 'dashicons-admin-users',
            ],
            'cache' => [
                'id' => 'cache',
                'label' => __('Cache', 'wp-state-machine'),
                'icon' => 'dashicons-performance',
            ],
            'database' => [
                'id' => 'database',
                'label' => __('Database', 'wp-state-machine'),
                'icon' => 'dashicons-database',
            ],
        ];
    }

    /**
     * Get current settings
     *
     * @return array
     */
    public function getSettings() {
        $settings = get_option($this->option_name, $this->default_settings);
        return wp_parse_args($settings, $this->default_settings);
    }

    /**
     * Save settings via AJAX
     *
     * @return void
     */
    public function saveSettings() {
        // Clean any output buffer to prevent HTML in JSON response
        if (ob_get_length()) {
            ob_clean();
        }

        try {
            // Debug: log all POST data first
            error_log('=== SETTINGS SAVE AJAX CALLED ===');
            error_log('POST data: ' . print_r($_POST, true));
            error_log('Nonce from POST: ' . (isset($_POST['nonce']) ? $_POST['nonce'] : 'NOT SET'));

            // Verify nonce
            $nonce_check = check_ajax_referer('wp_state_machine_nonce', 'nonce', false);
            error_log('Nonce check result: ' . ($nonce_check ? 'PASSED' : 'FAILED'));

            if (!$nonce_check) {
                error_log('Security check failed - nonce invalid');
                throw new \Exception(__('Security check failed', 'wp-state-machine'));
            }

            // Check permissions
            if (!current_user_can('manage_options')) {
                throw new \Exception(__('Insufficient permissions', 'wp-state-machine'));
            }

            // Get posted data
            $tab = isset($_POST['tab']) ? sanitize_text_field($_POST['tab']) : '';
            $settings_data = isset($_POST['settings']) ? $_POST['settings'] : [];

            // Debug logging
            error_log('=== SETTINGS SAVE DEBUG ===');
            error_log('Tab: ' . $tab);
            error_log('Posted settings data: ' . print_r($settings_data, true));
            error_log('Raw POST: ' . print_r($_POST, true));

            // Get current settings
            $current_settings = $this->getSettings();
            error_log('Current settings before update: ' . print_r($current_settings, true));

            // Update settings based on tab
            switch ($tab) {
                case 'general':
                    $current_settings = $this->updateGeneralSettings($current_settings, $settings_data);
                    break;
                case 'permissions':
                    $current_settings = $this->updatePermissionSettings($current_settings, $settings_data);
                    break;
                case 'cache':
                    $current_settings = $this->updateCacheSettings($current_settings, $settings_data);
                    break;
                case 'database':
                    $current_settings = $this->updateDatabaseSettings($current_settings, $settings_data);
                    break;
                default:
                    throw new \Exception(__('Invalid settings tab', 'wp-state-machine'));
            }

            // Sanitize and save
            error_log('Settings after tab update: ' . print_r($current_settings, true));
            $current_settings = $this->sanitizeSettings($current_settings);
            error_log('Settings after sanitize: ' . print_r($current_settings, true));

            $saved = update_option($this->option_name, $current_settings);
            error_log('Settings saved to DB: ' . ($saved ? 'YES' : 'NO'));

            // Verify what was actually saved
            $verified = get_option($this->option_name);
            error_log('Settings retrieved from DB: ' . print_r($verified, true));

            // Clear cache if cache settings changed
            if ($tab === 'cache') {
                $this->cache->clearAll();
            }

            wp_send_json_success([
                'message' => __('Settings saved successfully', 'wp-state-machine'),
                'settings' => $current_settings,
            ]);

        } catch (\Exception $e) {
            wp_send_json_error([
                'message' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Update general settings
     *
     * @param array $current Current settings
     * @param array $new_data New data
     * @return array Updated settings
     */
    private function updateGeneralSettings($current, $new_data) {
        $current['enable_logging'] = isset($new_data['enable_logging']) ? (bool) $new_data['enable_logging'] : false;
        $current['log_retention_days'] = isset($new_data['log_retention_days']) ? absint($new_data['log_retention_days']) : 90;
        $current['enable_notifications'] = isset($new_data['enable_notifications']) ? (bool) $new_data['enable_notifications'] : false;
        $current['notification_email'] = isset($new_data['notification_email']) ? sanitize_email($new_data['notification_email']) : '';

        return $current;
    }

    /**
     * Update permission settings
     *
     * @param array $current Current settings
     * @param array $new_data New data
     * @return array Updated settings
     */
    private function updatePermissionSettings($current, $new_data) {
        $current['allow_plugin_manage_permissions'] = isset($new_data['allow_plugin_manage_permissions']) ? (bool) $new_data['allow_plugin_manage_permissions'] : false;
        $current['default_view_capability'] = isset($new_data['default_view_capability']) ? sanitize_text_field($new_data['default_view_capability']) : 'view_state_machines';
        $current['default_edit_capability'] = isset($new_data['default_edit_capability']) ? sanitize_text_field($new_data['default_edit_capability']) : 'edit_state_machines';

        return $current;
    }

    /**
     * Update cache settings
     *
     * @param array $current Current settings
     * @param array $new_data New data
     * @return array Updated settings
     */
    private function updateCacheSettings($current, $new_data) {
        $current['enable_cache'] = isset($new_data['enable_cache']) ? (bool) $new_data['enable_cache'] : false;
        $current['cache_expiration'] = isset($new_data['cache_expiration']) ? absint($new_data['cache_expiration']) : 3600;

        return $current;
    }

    /**
     * Update database settings
     *
     * @param array $current Current settings
     * @param array $new_data New data
     * @return array Updated settings
     */
    private function updateDatabaseSettings($current, $new_data) {
        $current['auto_cleanup_enabled'] = isset($new_data['auto_cleanup_enabled']) ? (bool) $new_data['auto_cleanup_enabled'] : false;
        $current['cleanup_frequency'] = isset($new_data['cleanup_frequency']) ? sanitize_text_field($new_data['cleanup_frequency']) : 'monthly';
        $current['keep_logs_days'] = isset($new_data['keep_logs_days']) ? absint($new_data['keep_logs_days']) : 90;

        return $current;
    }

    /**
     * Sanitize settings
     *
     * @param array $settings Settings to sanitize
     * @return array Sanitized settings
     */
    public function sanitizeSettings($settings) {
        $sanitized = [];

        // Boolean fields
        $boolean_fields = ['enable_logging', 'enable_notifications', 'enable_cache', 'allow_plugin_manage_permissions', 'auto_cleanup_enabled'];
        foreach ($boolean_fields as $field) {
            $sanitized[$field] = isset($settings[$field]) ? (bool) $settings[$field] : false;
        }

        // Integer fields
        $sanitized['log_retention_days'] = isset($settings['log_retention_days']) ? absint($settings['log_retention_days']) : 90;
        $sanitized['cache_expiration'] = isset($settings['cache_expiration']) ? absint($settings['cache_expiration']) : 3600;
        $sanitized['keep_logs_days'] = isset($settings['keep_logs_days']) ? absint($settings['keep_logs_days']) : 90;

        // Email field
        $sanitized['notification_email'] = isset($settings['notification_email']) ? sanitize_email($settings['notification_email']) : '';

        // Text fields
        $sanitized['default_view_capability'] = isset($settings['default_view_capability']) ? sanitize_text_field($settings['default_view_capability']) : 'view_state_machines';
        $sanitized['default_edit_capability'] = isset($settings['default_edit_capability']) ? sanitize_text_field($settings['default_edit_capability']) : 'edit_state_machines';
        $sanitized['cleanup_frequency'] = isset($settings['cleanup_frequency']) ? sanitize_text_field($settings['cleanup_frequency']) : 'monthly';

        return $sanitized;
    }

    /**
     * Clear all cache via AJAX
     *
     * @return void
     */
    public function clearAllCache() {
        // Clean any output buffer to prevent HTML in JSON response
        if (ob_get_length()) {
            ob_clean();
        }

        try {
            // Verify nonce
            if (!check_ajax_referer('wp_state_machine_nonce', 'nonce', false)) {
                throw new \Exception(__('Security check failed', 'wp-state-machine'));
            }

            // Check permissions
            if (!current_user_can('manage_options')) {
                throw new \Exception(__('Insufficient permissions', 'wp-state-machine'));
            }

            // Clear cache
            $this->cache->clearAll();

            wp_send_json_success([
                'message' => __('All cache cleared successfully', 'wp-state-machine'),
            ]);

        } catch (\Exception $e) {
            wp_send_json_error([
                'message' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Cleanup old logs via AJAX
     *
     * @return void
     */
    public function cleanupOldLogs() {
        // Clean any output buffer to prevent HTML in JSON response
        if (ob_get_length()) {
            ob_clean();
        }

        try {
            // Verify nonce
            if (!check_ajax_referer('wp_state_machine_nonce', 'nonce', false)) {
                throw new \Exception(__('Security check failed', 'wp-state-machine'));
            }

            // Check permissions
            if (!current_user_can('manage_options')) {
                throw new \Exception(__('Insufficient permissions', 'wp-state-machine'));
            }

            global $wpdb;
            $settings = $this->getSettings();
            $days = $settings['keep_logs_days'];

            // Delete old logs from central table
            $result = $wpdb->query($wpdb->prepare(
                "DELETE FROM {$wpdb->prefix}app_sm_transition_logs
                WHERE created_at < DATE_SUB(NOW(), INTERVAL %d DAY)",
                $days
            ));

            $deleted_count = $result !== false ? $result : 0;

            wp_send_json_success([
                'message' => sprintf(
                    __('Deleted %d old log entries', 'wp-state-machine'),
                    $deleted_count
                ),
                'deleted_count' => $deleted_count,
            ]);

        } catch (\Exception $e) {
            wp_send_json_error([
                'message' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Get database statistics via AJAX
     *
     * @return void
     */
    public function getDatabaseStats() {
        // Clean any output buffer to prevent HTML in JSON response
        if (ob_get_length()) {
            ob_clean();
        }

        // Suppress WordPress database errors for AJAX
        global $wpdb;
        $wpdb->suppress_errors = true;
        $wpdb->hide_errors();

        try {
            // Verify nonce
            if (!check_ajax_referer('wp_state_machine_nonce', 'nonce', false)) {
                throw new \Exception(__('Security check failed', 'wp-state-machine'));
            }

            // Check permissions
            if (!current_user_can('manage_options')) {
                throw new \Exception(__('Insufficient permissions', 'wp-state-machine'));
            }

            // Check if tables exist first
            $tables_exist = $wpdb->get_var($wpdb->prepare(
                "SELECT COUNT(*) FROM information_schema.TABLES
                WHERE table_schema = %s
                AND table_name = %s",
                DB_NAME,
                $wpdb->prefix . 'app_sm_machines'
            ));

            if (!$tables_exist) {
                throw new \Exception(__('State machine tables not found. Please activate the plugin first.', 'wp-state-machine'));
            }

            // Get table counts with error suppression
            $stats = [
                'machines' => (int) $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}app_sm_machines"),
                'states' => (int) $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}app_sm_states"),
                'transitions' => (int) $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}app_sm_transitions"),
                'logs' => (int) $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}app_sm_transition_logs"),
                'workflow_groups' => (int) $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}app_sm_workflow_groups"),
            ];

            // Get database size
            $db_size = $wpdb->get_var($wpdb->prepare(
                "SELECT ROUND(SUM(data_length + index_length) / 1024 / 1024, 2) as size_mb
                FROM information_schema.TABLES
                WHERE table_schema = %s
                AND table_name LIKE %s",
                DB_NAME,
                $wpdb->prefix . 'app_sm_%'
            ));

            $stats['database_size_mb'] = $db_size ? (float) $db_size : 0;

            wp_send_json_success([
                'stats' => $stats,
            ]);

        } catch (\Exception $e) {
            wp_send_json_error([
                'message' => $e->getMessage(),
            ]);
        }
    }
}
