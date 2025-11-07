<?php
/**
 * Transitions Table Schema
 *
 * @package     WP_State_Machine
 * @subpackage  Database/Tables
 * @version     1.0.0
 * @author      arisciwek
 *
 * Path: /wp-state-machine/src/Database/Tables/TransitionsDB.php
 *
 * Description: Mendefinisikan struktur tabel transitions.
 *              Menyimpan transisi yang diperbolehkan antar states.
 *              Setiap transisi bisa memiliki guard (validasi) dan metadata.
 *
 * Fields:
 * - id             : Primary key
 * - machine_id     : Foreign key ke sm_machines
 * - from_state_id  : Foreign key ke sm_states (state asal)
 * - to_state_id    : Foreign key ke sm_states (state tujuan)
 * - label          : Label untuk transisi (e.g., "Approve", "Reject")
 * - guard_class    : Nama class untuk validasi transition (nullable)
 * - metadata       : JSON untuk data tambahan (nullable)
 * - sort_order     : Display order
 * - created_at     : Timestamp pembuatan
 * - updated_at     : Timestamp update terakhir
 *
 * Foreign Keys:
 * - machine_id     : REFERENCES app_sm_machines(id) ON DELETE CASCADE
 * - from_state_id  : REFERENCES app_sm_states(id) ON DELETE CASCADE
 * - to_state_id    : REFERENCES app_sm_states(id) ON DELETE CASCADE
 *
 * Indexes:
 * - machine_id     : KEY untuk query berdasarkan machine
 * - from_state     : KEY untuk query available transitions
 * - unique_trans   : UNIQUE KEY (machine_id, from_state_id, to_state_id)
 *
 * Changelog:
 * 1.0.0 - 2025-11-07
 * - Initial version
 * - Basic transition structure
 */

namespace WPStateMachine\Database\Tables;

defined('ABSPATH') || exit;

class TransitionsDB {
    /**
     * Get table schema for transitions
     *
     * @return string SQL schema
     */
    public static function get_schema() {
        global $wpdb;
        $table_name = $wpdb->prefix . 'app_sm_transitions';
        $charset_collate = $wpdb->get_charset_collate();

        return "CREATE TABLE {$table_name} (
            id bigint(20) UNSIGNED NOT NULL auto_increment,
            machine_id bigint(20) UNSIGNED NOT NULL,
            from_state_id bigint(20) UNSIGNED NOT NULL,
            to_state_id bigint(20) UNSIGNED NOT NULL,
            label varchar(100) NOT NULL,
            guard_class varchar(255) NULL,
            metadata text NULL,
            sort_order int(11) NOT NULL DEFAULT 0,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY  (id),
            UNIQUE KEY unique_transition (machine_id, from_state_id, to_state_id),
            KEY machine_id_index (machine_id),
            KEY from_state_index (from_state_id),
            KEY to_state_index (to_state_id),
            KEY sort_order_index (sort_order)
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
        $table_name = $wpdb->prefix . 'app_sm_transitions';
        $machines_table = $wpdb->prefix . 'app_sm_machines';
        $states_table = $wpdb->prefix . 'app_sm_states';

        $constraints = [
            [
                'name' => 'fk_sm_transitions_machine',
                'sql' => "ALTER TABLE {$table_name}
                         ADD CONSTRAINT fk_sm_transitions_machine
                         FOREIGN KEY (machine_id)
                         REFERENCES {$machines_table}(id)
                         ON DELETE CASCADE"
            ],
            [
                'name' => 'fk_sm_transitions_from_state',
                'sql' => "ALTER TABLE {$table_name}
                         ADD CONSTRAINT fk_sm_transitions_from_state
                         FOREIGN KEY (from_state_id)
                         REFERENCES {$states_table}(id)
                         ON DELETE CASCADE"
            ],
            [
                'name' => 'fk_sm_transitions_to_state',
                'sql' => "ALTER TABLE {$table_name}
                         ADD CONSTRAINT fk_sm_transitions_to_state
                         FOREIGN KEY (to_state_id)
                         REFERENCES {$states_table}(id)
                         ON DELETE CASCADE"
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
                error_log("[TransitionsDB] Failed to add FK {$constraint['name']}: " . $wpdb->last_error);
            }
        }
    }
}
