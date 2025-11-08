<?php
/**
 * Plugin Deactivator Class
 *
 * @package     WP_State_Machine
 * @subpackage  Includes
 * @version     1.2.0
 * @author      arisciwek
 *
 * Path: /wp-state-machine/includes/class-deactivator.php
 *
 * Description: Menangani proses deaktivasi plugin:
 *              - Cache cleanup
 *              - Settings cleanup (optional)
 *              - Database cleanup (hanya dalam mode development)
 *              - Capabilities cleanup (optional)
 *
 * Development Mode (Double Safety):
 * - Requires TWO checkboxes enabled:
 *   1. Settings > Database > "Development Mode"
 *   2. Settings > Database > "Clear data on deactivate"
 * - Drops all tables and foreign keys
 * - Removes capabilities
 * - Deletes all options
 *
 * Production Mode (default):
 * - Only clears cache and transients
 * - PRESERVES all database tables and data
 * - PRESERVES capabilities
 *
 * Changelog:
 * 1.2.0 - 2025-11-08
 * - Added double safety: requires TWO checkboxes
 * - Added enable_development flag check
 * - Follows wp-customer pattern exactly
 * 1.1.0 - 2025-11-08
 * - Added development mode support
 * - Added table drop functionality
 * - Added capabilities removal
 * - Added development settings check
 * 1.0.0 - 2025-11-07
 * - Initial creation
 * - Cache cleanup on deactivation
 * - Data preservation (no table drop)
 */

use WPStateMachine\Cache\StateMachineCacheManager;

class WP_State_Machine_Deactivator {

    /**
     * Debug logging
     *
     * @param string $message Log message
     * @return void
     */
    private static function debug($message) {
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log("[WP_State_Machine_Deactivator] {$message}");
        }
    }

    /**
     * Check if data should be cleared on deactivation
     * Checks Settings > Database > Development Settings
     * Requires BOTH checkboxes to be enabled (double safety)
     *
     * @return bool True if data should be cleared
     */
    private static function should_clear_data() {
        $settings = get_option('wp_state_machine_settings', []);

        // Both enable_development AND clear_data_on_deactivate must be true
        $dev_mode = isset($settings['enable_development']) && $settings['enable_development'];
        $clear_data = isset($settings['clear_data_on_deactivate']) && $settings['clear_data_on_deactivate'];

        return ($dev_mode && $clear_data);
    }

    /**
     * Deactivate plugin
     *
     * Behavior depends on settings:
     * - Development Mode: Drops tables, removes caps, clears all data
     * - Production Mode: Only clears cache and transients
     *
     * @return void
     */
    public static function deactivate() {
        global $wpdb;

        $should_clear_data = self::should_clear_data();

        try {
            self::debug("Starting plugin deactivation (clear_data: " . ($should_clear_data ? 'yes' : 'no') . ")");

            // Only proceed with data cleanup if enabled in settings
            if (!$should_clear_data) {
                self::debug("Skipping data cleanup - only clearing cache");
                self::clear_cache_only();
                return;
            }

            // DEVELOPMENT MODE - Full cleanup
            self::debug("Development mode active - performing full cleanup");

            // Hapus settings terlebih dahulu
            delete_option('wp_state_machine_settings');
            self::debug("Settings cleared");

            // Remove capabilities
            self::remove_capabilities();

            // Start transaction
            $wpdb->query('START TRANSACTION');

            // Disable foreign key checks
            $wpdb->query('SET FOREIGN_KEY_CHECKS = 0');

            // Drop foreign keys first
            self::drop_foreign_keys();

            // Drop tables in correct order (child tables first)
            $tables = [
                'app_sm_transition_logs',        // First - no dependencies
                'app_sm_transitions',            // References states
                'app_sm_states',                 // References machines
                'app_sm_machines',               // References workflow_groups
                'app_sm_workflow_groups'         // Last - parent table
            ];

            foreach ($tables as $table) {
                $table_name = $wpdb->prefix . $table;
                self::debug("Dropping table: {$table_name}");
                $wpdb->query("DROP TABLE IF EXISTS {$table_name}");
            }

            // Re-enable foreign key checks
            $wpdb->query('SET FOREIGN_KEY_CHECKS = 1');

            // Clear cache
            self::clear_cache_only();

            // Clear all transients
            self::clear_transients();

            // Commit transaction
            $wpdb->query('COMMIT');

            self::debug("Full cleanup complete");

        } catch (\Exception $e) {
            $wpdb->query('ROLLBACK');
            self::debug("Error during deactivation: " . $e->getMessage());
        }
    }

    /**
     * Clear cache only (safe operation)
     *
     * @return void
     */
    private static function clear_cache_only() {
        try {
            $cache_manager = new StateMachineCacheManager();
            $cleared = $cache_manager->clearAll();
            self::debug("Cache clearing result: " . ($cleared ? 'success' : 'failed'));
        } catch (\Exception $e) {
            self::debug("Error clearing cache: " . $e->getMessage());
        }

        // Flush rewrite rules
        flush_rewrite_rules();
    }

    /**
     * Drop all foreign key constraints
     *
     * @return void
     */
    private static function drop_foreign_keys() {
        global $wpdb;

        try {
            // Define all foreign key constraints (actual names from database)
            $constraints = [
                // Machines table
                [
                    'table' => $wpdb->prefix . 'app_sm_machines',
                    'constraint' => 'fk_sm_machine_workflow_group'
                ],
                // States table
                [
                    'table' => $wpdb->prefix . 'app_sm_states',
                    'constraint' => 'fk_sm_states_machine'
                ],
                // Transitions table
                [
                    'table' => $wpdb->prefix . 'app_sm_transitions',
                    'constraint' => 'fk_sm_transitions_machine'
                ],
                [
                    'table' => $wpdb->prefix . 'app_sm_transitions',
                    'constraint' => 'fk_sm_transitions_from_state'
                ],
                [
                    'table' => $wpdb->prefix . 'app_sm_transitions',
                    'constraint' => 'fk_sm_transitions_to_state'
                ],
                // Transition logs table
                [
                    'table' => $wpdb->prefix . 'app_sm_transition_logs',
                    'constraint' => 'fk_sm_logs_machine'
                ],
                [
                    'table' => $wpdb->prefix . 'app_sm_transition_logs',
                    'constraint' => 'fk_sm_logs_transition'
                ],
                [
                    'table' => $wpdb->prefix . 'app_sm_transition_logs',
                    'constraint' => 'fk_sm_logs_from_state'
                ],
                [
                    'table' => $wpdb->prefix . 'app_sm_transition_logs',
                    'constraint' => 'fk_sm_logs_to_state'
                ]
            ];

            foreach ($constraints as $fk) {
                // Check if constraint exists
                $constraint_exists = $wpdb->get_var($wpdb->prepare(
                    "SELECT COUNT(*) FROM information_schema.TABLE_CONSTRAINTS
                     WHERE CONSTRAINT_SCHEMA = DATABASE()
                     AND TABLE_NAME = %s
                     AND CONSTRAINT_NAME = %s
                     AND CONSTRAINT_TYPE = 'FOREIGN KEY'",
                    $fk['table'],
                    $fk['constraint']
                ));

                if ($constraint_exists > 0) {
                    self::debug("Dropping FK: {$fk['constraint']} from {$fk['table']}");
                    $wpdb->query("ALTER TABLE {$fk['table']} DROP FOREIGN KEY `{$fk['constraint']}`");
                }
            }

            self::debug("All foreign keys dropped successfully");
        } catch (\Exception $e) {
            self::debug("Error dropping foreign keys: " . $e->getMessage());
        }
    }

    /**
     * Remove plugin capabilities from all roles
     *
     * @return void
     */
    private static function remove_capabilities() {
        try {
            $capabilities = [
                'view_state_machines',
                'manage_state_machines',
                'view_states',
                'manage_states',
                'view_transitions',
                'manage_transitions',
                'view_workflow_groups',
                'manage_workflow_groups',
                'view_state_machine_logs',
                'export_state_machine_logs',
                'manage_state_machine_settings'
            ];

            // Remove capabilities from all roles
            foreach (get_editable_roles() as $role_name => $role_info) {
                $role = get_role($role_name);
                if (!$role) continue;

                foreach ($capabilities as $cap) {
                    $role->remove_cap($cap);
                }
            }

            self::debug("Capabilities removed successfully");
        } catch (\Exception $e) {
            self::debug("Error removing capabilities: " . $e->getMessage());
        }
    }

    /**
     * Clear plugin-specific transients
     *
     * @return void
     */
    private static function clear_transients() {
        global $wpdb;

        try {
            // Delete transients with our prefix
            $wpdb->query("
                DELETE FROM {$wpdb->options}
                WHERE option_name LIKE '_transient_wp_state_machine_%'
                   OR option_name LIKE '_transient_timeout_wp_state_machine_%'
            ");

            self::debug("Transients cleared");
        } catch (\Exception $e) {
            self::debug("Error clearing transients: " . $e->getMessage());
        }
    }
}
