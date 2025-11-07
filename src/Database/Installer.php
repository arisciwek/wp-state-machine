<?php
/**
 * Database Installer
 *
 * @package     WP_State_Machine
 * @subpackage  Database
 * @version     1.1.0
 * @author      arisciwek
 *
 * Path: /wp-state-machine/src/Database/Installer.php
 *
 * Description: Mengelola instalasi dan pembaruan struktur database plugin.
 *              Mendukung pembuatan tabel dengan dependencies dan foreign keys.
 *              Menggunakan dbDelta untuk membuat/mengubah struktur tabel.
 *              Menambahkan foreign key constraints secara terpisah.
 *
 * Struktur tabel:
 * - app_sm_workflow_groups   : Workflow groups for organization (NEW)
 * - app_sm_machines          : State machine definitions
 * - app_sm_states            : States dalam setiap machine
 * - app_sm_transitions       : Allowed transitions antar states
 * - app_sm_transition_logs   : History/log setiap transition
 *
 * Changelog:
 * 1.1.0 - 2025-11-07
 * - Added: app_sm_workflow_groups table for organization
 * - Added: group_id FK in app_sm_machines
 * - Adopted Drupal Workflow Groups pattern
 * - Support for 16+ plugins ecosystem
 *
 * 1.0.0 - 2025-11-07
 * - Initial version
 * - Table creation with foreign keys
 * - Transaction support for rollback
 * - Table verification after creation
 */

namespace WPStateMachine\Database;

defined('ABSPATH') || exit;

class Installer {
    /**
     * Complete list of tables to install, in dependency order
     * Parent tables first, then child tables
     *
     * @var array
     */
    private static $tables = [
        'app_sm_workflow_groups',   // Parent - no dependencies (NEW)
        'app_sm_machines',          // Child of workflow_groups (optional FK)
        'app_sm_states',            // Child of machines
        'app_sm_transitions',       // Child of machines and states
        'app_sm_transition_logs'    // Child of machines, states, transitions
    ];

    /**
     * Table class mappings for easier maintenance
     *
     * @var array
     */
    private static $table_classes = [
        'app_sm_workflow_groups' => Tables\WorkflowGroupsDB::class,
        'app_sm_machines' => Tables\StateMachinesDB::class,
        'app_sm_states' => Tables\StatesDB::class,
        'app_sm_transitions' => Tables\TransitionsDB::class,
        'app_sm_transition_logs' => Tables\TransitionLogsDB::class
    ];

    /**
     * Debug logging helper
     *
     * @param string $message Message to log
     * @return void
     */
    private static function debug($message) {
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log("[StateMachine Installer] " . $message);
        }
    }

    /**
     * Verify all tables were created successfully
     *
     * @throws \Exception If table creation failed
     * @return void
     */
    private static function verify_tables() {
        global $wpdb;
        foreach (self::$tables as $table) {
            $table_name = $wpdb->prefix . $table;
            $table_exists = $wpdb->get_var($wpdb->prepare(
                "SHOW TABLES LIKE %s",
                $table_name
            ));
            if (!$table_exists) {
                self::debug("Table not found: {$table_name}");
                throw new \Exception("Failed to create table: {$table_name}");
            }
            self::debug("Verified table exists: {$table_name}");
        }
    }

    /**
     * Installs or updates the database tables
     *
     * Process:
     * 1. Create all tables without foreign keys using dbDelta
     * 2. Verify all tables were created
     * 3. Add foreign key constraints
     * 4. Commit transaction if successful, rollback on error
     *
     * Note: Seeding is NOT done here (decentralized pattern).
     *       Each plugin seeds its own state machines on activation via:
     *       Seeder::seedByPlugin('plugin-slug')
     *
     * @return bool True on success, false on failure
     */
    public static function run() {
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        global $wpdb;

        try {
            $wpdb->query('START TRANSACTION');
            self::debug("Starting database installation...");

            // Create tables in proper order (without foreign keys first)
            foreach (self::$tables as $table) {
                $class = self::$table_classes[$table];
                self::debug("Creating {$table} table using {$class}...");
                dbDelta($class::get_schema());
            }

            // Verify all tables were created
            self::verify_tables();

            // Add foreign key constraints after all tables are created
            self::debug("Adding foreign key constraints...");

            // Add foreign keys for Machines (to WorkflowGroups)
            if (method_exists(Tables\StateMachinesDB::class, 'add_foreign_keys')) {
                self::debug("Adding Machines FK...");
                Tables\StateMachinesDB::add_foreign_keys();
            }

            // Add foreign keys for States
            if (method_exists(Tables\StatesDB::class, 'add_foreign_keys')) {
                self::debug("Adding States FK...");
                Tables\StatesDB::add_foreign_keys();
            }

            // Add foreign keys for Transitions
            if (method_exists(Tables\TransitionsDB::class, 'add_foreign_keys')) {
                self::debug("Adding Transitions FK...");
                Tables\TransitionsDB::add_foreign_keys();
            }

            // Add foreign keys for TransitionLogs
            if (method_exists(Tables\TransitionLogsDB::class, 'add_foreign_keys')) {
                self::debug("Adding TransitionLogs FK...");
                Tables\TransitionLogsDB::add_foreign_keys();
            }

            // NOTE: Seeding removed from here
            // In decentralized pattern, each plugin seeds its own state machines via:
            // - Seeder::seedByPlugin('plugin-slug') on plugin activation
            //
            // Default workflow groups can be seeded by first plugin that activates,
            // or manually via admin UI if needed.
            //
            // This prevents core plugin from needing to know about all plugins.

            self::debug("Database installation completed successfully.");
            $wpdb->query('COMMIT');

            // Update version option
            update_option('wp_state_machine_db_version', WP_STATE_MACHINE_VERSION);

            return true;

        } catch (\Exception $e) {
            $wpdb->query('ROLLBACK');
            self::debug('Database installation failed: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Check if database needs update
     *
     * @return bool True if update needed
     */
    public static function needs_update() {
        $installed_version = get_option('wp_state_machine_db_version', '0.0.0');
        return version_compare($installed_version, WP_STATE_MACHINE_VERSION, '<');
    }

    /**
     * Drop all plugin tables
     * Called during uninstallation
     *
     * @return bool True on success
     */
    public static function drop_tables() {
        global $wpdb;

        try {
            $wpdb->query('START TRANSACTION');
            self::debug("Dropping all state machine tables...");

            // Drop in reverse order to handle foreign keys
            $reverse_tables = array_reverse(self::$tables);

            foreach ($reverse_tables as $table) {
                $table_name = $wpdb->prefix . $table;

                // Drop foreign keys first
                $constraints = $wpdb->get_results($wpdb->prepare(
                    "SELECT CONSTRAINT_NAME
                     FROM information_schema.TABLE_CONSTRAINTS
                     WHERE CONSTRAINT_SCHEMA = DATABASE()
                     AND TABLE_NAME = %s
                     AND CONSTRAINT_TYPE = 'FOREIGN KEY'",
                    $table_name
                ));

                foreach ($constraints as $constraint) {
                    $wpdb->query("ALTER TABLE {$table_name} DROP FOREIGN KEY `{$constraint->CONSTRAINT_NAME}`");
                }

                // Drop table
                $wpdb->query("DROP TABLE IF EXISTS {$table_name}");
                self::debug("Dropped table: {$table_name}");
            }

            $wpdb->query('COMMIT');

            // Remove version option
            delete_option('wp_state_machine_db_version');

            self::debug("All tables dropped successfully.");
            return true;

        } catch (\Exception $e) {
            $wpdb->query('ROLLBACK');
            self::debug('Failed to drop tables: ' . $e->getMessage());
            return false;
        }
    }
}
