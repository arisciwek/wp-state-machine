<?php
/**
 * File: class-activator.php
 * Path: /wp-state-machine/includes/class-activator.php
 * Description: Handles plugin activation and database installation
 *
 * @package     WP_State_Machine
 * @subpackage  Includes
 * @version     1.0.0
 * @author      arisciwek
 *
 * Description: Menangani proses aktivasi plugin dan instalasi database.
 *              Termasuk di dalamnya:
 *              - Instalasi tabel database melalui Database\Installer
 *              - Menambahkan versi plugin ke options table
 *              - Setup permission dan capabilities
 *
 * Dependencies:
 * - WPStateMachine\Database\Installer untuk instalasi database
 * - WPStateMachine\Models\Settings\PermissionModel untuk setup capabilities
 * - WordPress Options API
 *
 * Changelog:
 * 1.0.0 - 2025-11-07
 * - Initial creation
 * - Added activation handling
 * - Added version management
 * - Added permissions setup
 * - Follow wp-agency pattern
 */

use WPStateMachine\Models\Settings\PermissionModel;
use WPStateMachine\Database\Installer;

class WP_State_Machine_Activator {

    /**
     * Log error messages
     *
     * @param string $message Error message to log
     * @return void
     */
    private static function logError($message) {
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log("WP_State_Machine_Activator Error: {$message}");
        }
    }

    /**
     * Activate plugin
     * Runs database installation, adds capabilities, sets version
     *
     * @return void
     */
    public static function activate() {
        try {
            // Load textdomain first
            load_textdomain('wp-state-machine', WP_STATE_MACHINE_PATH . 'languages/wp-state-machine-id_ID.mo');

            // 1. Run database installation first
            $installer = new Installer();
            if (!$installer->run()) {
                self::logError('Failed to install database tables');
                return;
            }

            // 2. Initialize permission model and add capabilities
            try {
                $permission_model = new PermissionModel();
                $permission_model->addCapabilities();
            } catch (\Exception $e) {
                self::logError('Error adding capabilities: ' . $e->getMessage());
            }

            // 3. Add version to options
            self::addVersion();

            // 4. Flush rewrite rules
            flush_rewrite_rules();

        } catch (\Exception $e) {
            self::logError('Critical error during activation: ' . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Add plugin version to options table
     *
     * @return void
     */
    private static function addVersion() {
        add_option('wp_state_machine_version', WP_STATE_MACHINE_VERSION);
    }
}
