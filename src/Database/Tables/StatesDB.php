<?php
/**
 * States Table Schema
 *
 * @package     WP_State_Machine
 * @subpackage  Database/Tables
 * @version     1.0.0
 * @author      arisciwek
 *
 * Path: /wp-state-machine/src/Database/Tables/StatesDB.php
 *
 * Description: Mendefinisikan struktur tabel states.
 *              Menyimpan state-state dalam setiap state machine.
 *              Setiap state memiliki tipe (initial, normal, final).
 *
 * Fields:
 * - id             : Primary key
 * - machine_id     : Foreign key ke sm_machines
 * - name           : Nama state (e.g., "Draft", "Published")
 * - slug           : Slug untuk state
 * - type           : Tipe state (initial/normal/final)
 * - color          : Warna untuk UI display (nullable)
 * - metadata       : JSON untuk data tambahan (nullable)
 * - sort_order     : Urutan display
 * - created_at     : Timestamp pembuatan
 * - updated_at     : Timestamp update terakhir
 *
 * Foreign Keys:
 * - machine_id     : REFERENCES app_sm_machines(id) ON DELETE CASCADE
 *
 * Indexes:
 * - machine_id     : KEY untuk query berdasarkan machine
 * - type           : KEY untuk filter berdasarkan tipe
 * - unique_state   : UNIQUE KEY (machine_id, slug)
 *
 * Changelog:
 * 1.0.0 - 2025-11-07
 * - Initial version
 * - Basic state structure
 */

namespace WPStateMachine\Database\Tables;

defined('ABSPATH') || exit;

class StatesDB {
    /**
     * Get table schema for states
     *
     * @return string SQL schema
     */
    public static function get_schema() {
        global $wpdb;
        $table_name = $wpdb->prefix . 'app_sm_states';
        $charset_collate = $wpdb->get_charset_collate();

        return "CREATE TABLE {$table_name} (
            id bigint(20) UNSIGNED NOT NULL auto_increment,
            machine_id bigint(20) UNSIGNED NOT NULL,
            name varchar(100) NOT NULL,
            slug varchar(100) NOT NULL,
            type enum('initial','normal','final') NOT NULL DEFAULT 'normal',
            color varchar(7) NULL,
            metadata text NULL,
            sort_order int(11) NOT NULL DEFAULT 0,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY  (id),
            UNIQUE KEY unique_state (machine_id, slug),
            KEY machine_id_index (machine_id),
            KEY type_index (type),
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
        $table_name = $wpdb->prefix . 'app_sm_states';
        $machines_table = $wpdb->prefix . 'app_sm_machines';

        $constraints = [
            [
                'name' => 'fk_sm_states_machine',
                'sql' => "ALTER TABLE {$table_name}
                         ADD CONSTRAINT fk_sm_states_machine
                         FOREIGN KEY (machine_id)
                         REFERENCES {$machines_table}(id)
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
                error_log("[StatesDB] Failed to add FK {$constraint['name']}: " . $wpdb->last_error);
            }
        }
    }
}
