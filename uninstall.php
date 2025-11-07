<?php
/**
 * File: uninstall.php
 * Path: /wp-state-machine/uninstall.php
 * Description: Plugin uninstallation handler
 *
 * @package     WP_State_Machine
 * @subpackage  Root
 * @version     1.0.0
 * @author      arisciwek
 *
 * Description: Handles complete plugin removal:
 *              - Drops all database tables
 *              - Removes foreign key constraints
 *              - Deletes all plugin options
 *              - Removes capabilities from roles
 *              - Complete cleanup (permanent removal)
 *
 * Note: This is UNINSTALLATION, not DEACTIVATION
 *       - Uninstallation = permanent removal, cleanup everything
 *       - Deactivation = temporary disable, preserve data
 *       - This file is called by WordPress during plugin deletion
 *
 * Changelog:
 * 1.0.0 - 2025-11-07
 * - Initial creation
 * - Drop all tables and FK constraints
 * - Delete all options
 * - Remove capabilities
 */

// If uninstall not called from WordPress, exit
if (!defined('WP_UNINSTALL_PLUGIN')) {
    exit;
}

/**
 * Load required files
 */
$plugin_path = plugin_dir_path(__FILE__);

// Load autoloader
require_once $plugin_path . 'includes/class-autoloader.php';
$autoloader = new WP_State_Machine_Autoloader();
$autoloader->register();

// Define constants if not defined
if (!defined('WP_STATE_MACHINE_PATH')) {
    define('WP_STATE_MACHINE_PATH', $plugin_path);
}
if (!defined('WP_STATE_MACHINE_VERSION')) {
    define('WP_STATE_MACHINE_VERSION', '1.1.0');
}

use WPStateMachine\Database\Installer;
use WPStateMachine\Models\Settings\PermissionModel;

/**
 * Log uninstall actions
 */
function wp_state_machine_log($message) {
    if (defined('WP_DEBUG') && WP_DEBUG) {
        error_log("[StateMachine Uninstall] {$message}");
    }
}

try {
    wp_state_machine_log('Starting plugin uninstallation...');

    // 1. Drop all database tables and foreign keys
    wp_state_machine_log('Dropping database tables...');
    $drop_result = Installer::drop_tables();
    if ($drop_result) {
        wp_state_machine_log('Database tables dropped successfully');
    } else {
        wp_state_machine_log('Warning: Some tables may not have been dropped');
    }

    // 2. Delete all plugin options
    wp_state_machine_log('Deleting plugin options...');
    $options = [
        'wp_state_machine_version',
        'wp_state_machine_db_version',
        'wp_state_machine_activated_time',
    ];

    foreach ($options as $option) {
        delete_option($option);
        // For multisite
        delete_site_option($option);
    }
    wp_state_machine_log('Plugin options deleted');

    // 3. Remove capabilities from all roles
    wp_state_machine_log('Removing capabilities...');
    try {
        $permission = new PermissionModel();
        $permission->removeCapabilities();
        wp_state_machine_log('Capabilities removed successfully');
    } catch (\Exception $e) {
        wp_state_machine_log('Error removing capabilities: ' . $e->getMessage());
    }

    // 4. Clear any transients
    wp_state_machine_log('Clearing transients...');
    global $wpdb;
    $wpdb->query(
        "DELETE FROM {$wpdb->options}
         WHERE option_name LIKE '_transient_wp_state_machine_%'
         OR option_name LIKE '_transient_timeout_wp_state_machine_%'"
    );

    if (is_multisite()) {
        $wpdb->query(
            "DELETE FROM {$wpdb->sitemeta}
             WHERE meta_key LIKE '_site_transient_wp_state_machine_%'
             OR meta_key LIKE '_site_transient_timeout_wp_state_machine_%'"
        );
    }
    wp_state_machine_log('Transients cleared');

    /**
     * Fire action after plugin uninstallation
     *
     * Allows other plugins to cleanup their state machine data
     *
     * @since 1.0.0
     */
    do_action('wp_state_machine_uninstalled');

    wp_state_machine_log('Plugin uninstallation completed successfully');

} catch (\Exception $e) {
    wp_state_machine_log('Critical error during uninstallation: ' . $e->getMessage());
}
