<?php
/**
 * Plugin Deactivator Class
 *
 * @package     WP_State_Machine
 * @subpackage  Includes
 * @version     1.0.0
 * @author      arisciwek
 *
 * Path: /wp-state-machine/includes/class-deactivator.php
 *
 * Description: Menangani proses deaktivasi plugin:
 *              - Cache cleanup
 *              - Settings cleanup
 *              - PRESERVES all database tables and data
 *              - PRESERVES capabilities
 *
 * Changelog:
 * 1.0.0 - 2025-11-07
 * - Initial creation
 * - Cache cleanup on deactivation
 * - Data preservation (no table drop)
 * - Follow wp-agency pattern
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
     * Deactivate plugin
     * Clears cache and transients only
     * PRESERVES all tables, foreign keys, and data
     *
     * @return void
     */
    public static function deactivate() {
        try {
            self::debug("Starting plugin deactivation");

            // Clear cache using StateMachineCacheManager
            try {
                $cache_manager = new StateMachineCacheManager();
                $cleared = $cache_manager->clearAll();
                self::debug("Cache clearing result: " . ($cleared ? 'success' : 'failed'));
            } catch (\Exception $e) {
                self::debug("Error clearing cache: " . $e->getMessage());
            }

            // Clear transients
            self::clear_transients();

            // Flush rewrite rules
            flush_rewrite_rules();

            self::debug("Plugin deactivation complete");

        } catch (\Exception $e) {
            self::debug("Error during deactivation: " . $e->getMessage());
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
