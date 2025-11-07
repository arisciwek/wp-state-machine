<?php
/**
 * Transition Logs Table Schema
 *
 * @package     WP_State_Machine
 * @subpackage  Database/Tables
 * @version     1.0.0
 * @author      arisciwek
 *
 * Path: /wp-state-machine/src/Database/Tables/TransitionLogsDB.php
 *
 * Description: Mendefinisikan struktur tabel transition logs.
 *              Menyimpan history/log setiap transisi yang terjadi.
 *              Untuk audit trail dan tracking perubahan state.
 *
 * Fields:
 * - id             : Primary key
 * - machine_id     : Foreign key ke sm_machines
 * - entity_id      : ID entity yang di-transisi (e.g., post_id, order_id)
 * - entity_type    : Tipe entity (e.g., "post", "order")
 * - from_state_id  : Foreign key ke sm_states (state asal, nullable untuk initial)
 * - to_state_id    : Foreign key ke sm_states (state tujuan)
 * - transition_id  : Foreign key ke sm_transitions (nullable)
 * - user_id        : User yang melakukan transisi
 * - comment        : Komentar/notes untuk transisi (nullable)
 * - metadata       : JSON untuk data tambahan (nullable)
 * - created_at     : Timestamp transisi
 *
 * Foreign Keys:
 * - machine_id     : REFERENCES app_sm_machines(id) ON DELETE CASCADE
 * - from_state_id  : REFERENCES app_sm_states(id) ON DELETE SET NULL
 * - to_state_id    : REFERENCES app_sm_states(id) ON DELETE CASCADE
 * - transition_id  : REFERENCES app_sm_transitions(id) ON DELETE SET NULL
 *
 * Indexes:
 * - machine_id     : KEY untuk query berdasarkan machine
 * - entity         : KEY untuk query history per entity
 * - user_id        : KEY untuk query berdasarkan user
 * - created_at     : KEY untuk sorting chronological
 *
 * Changelog:
 * 1.0.0 - 2025-11-07
 * - Initial version
 * - Basic log structure
 */

namespace WPStateMachine\Database\Tables;

defined('ABSPATH') || exit;

class TransitionLogsDB {
    /**
     * Get table schema for transition logs
     *
     * @return string SQL schema
     */
    public static function get_schema() {
        global $wpdb;
        $table_name = $wpdb->prefix . 'app_sm_transition_logs';
        $charset_collate = $wpdb->get_charset_collate();

        return "CREATE TABLE {$table_name} (
            id bigint(20) UNSIGNED NOT NULL auto_increment,
            machine_id bigint(20) UNSIGNED NOT NULL,
            entity_id bigint(20) UNSIGNED NOT NULL,
            entity_type varchar(50) NOT NULL,
            from_state_id bigint(20) UNSIGNED NULL,
            to_state_id bigint(20) UNSIGNED NOT NULL,
            transition_id bigint(20) UNSIGNED NULL,
            user_id bigint(20) UNSIGNED NOT NULL,
            comment text NULL,
            metadata text NULL,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY  (id),
            KEY machine_id_index (machine_id),
            KEY entity_index (entity_type, entity_id),
            KEY from_state_index (from_state_id),
            KEY to_state_index (to_state_id),
            KEY user_id_index (user_id),
            KEY created_at_index (created_at)
        ) $charset_collate;";
    }

    /**
     * Add foreign key constraints
     * Called after table creation
     *
     * @return void
     */
    public static function add_foreign_keys() {
        global $wpdb;
        $table_name = $wpdb->prefix . 'app_sm_transition_logs';
        $machines_table = $wpdb->prefix . 'app_sm_machines';
        $states_table = $wpdb->prefix . 'app_sm_states';
        $transitions_table = $wpdb->prefix . 'app_sm_transitions';

        $constraints = [
            [
                'name' => 'fk_sm_logs_machine',
                'sql' => "ALTER TABLE {$table_name}
                         ADD CONSTRAINT fk_sm_logs_machine
                         FOREIGN KEY (machine_id)
                         REFERENCES {$machines_table}(id)
                         ON DELETE CASCADE"
            ],
            [
                'name' => 'fk_sm_logs_from_state',
                'sql' => "ALTER TABLE {$table_name}
                         ADD CONSTRAINT fk_sm_logs_from_state
                         FOREIGN KEY (from_state_id)
                         REFERENCES {$states_table}(id)
                         ON DELETE SET NULL"
            ],
            [
                'name' => 'fk_sm_logs_to_state',
                'sql' => "ALTER TABLE {$table_name}
                         ADD CONSTRAINT fk_sm_logs_to_state
                         FOREIGN KEY (to_state_id)
                         REFERENCES {$states_table}(id)
                         ON DELETE CASCADE"
            ],
            [
                'name' => 'fk_sm_logs_transition',
                'sql' => "ALTER TABLE {$table_name}
                         ADD CONSTRAINT fk_sm_logs_transition
                         FOREIGN KEY (transition_id)
                         REFERENCES {$transitions_table}(id)
                         ON DELETE SET NULL"
            ]
        ];

        foreach ($constraints as $constraint) {
            // Check if constraint already exists
            $constraint_exists = $wpdb->get_var($wpdb->prepare(
                "SELECT COUNT(*) FROM information_schema.TABLE_CONSTRAINTS
                 WHERE CONSTRAINT_SCHEMA = DATABASE()
                 AND TABLE_NAME = %s
                 AND CONSTRAINT_NAME = %s",
                $table_name,
                $constraint['name']
            ));

            // If constraint exists, drop it first
            if ($constraint_exists > 0) {
                $wpdb->query("ALTER TABLE {$table_name} DROP FOREIGN KEY `{$constraint['name']}`");
            }

            // Add foreign key constraint
            $result = $wpdb->query($constraint['sql']);
            if ($result === false) {
                error_log("[TransitionLogsDB] Failed to add FK {$constraint['name']}: " . $wpdb->last_error);
            }
        }
    }
}
