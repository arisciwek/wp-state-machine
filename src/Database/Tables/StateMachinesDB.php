<?php
/**
 * State Machines Table Schema
 *
 * @package     WP_State_Machine
 * @subpackage  Database/Tables
 * @version     1.1.0
 * @author      arisciwek
 *
 * Path: /wp-state-machine/src/Database/Tables/StateMachinesDB.php
 *
 * Description: Mendefinisikan struktur tabel state machines.
 *              Central table untuk state machine definitions.
 *              Supports plugin isolation via plugin_slug field.
 *
 * Fields:
 * - id             : Primary key
 * - group_id       : Foreign key ke workflow_groups (optional)
 * - name           : Machine name
 * - slug           : Unique slug
 * - entity_type    : Entity type (e.g., "rfq", "quotation")
 * - description    : Machine description (nullable)
 * - plugin_slug    : Plugin owner (e.g., "wp-rfq")
 * - is_active      : Active status
 * - is_custom      : 0=default, 1=user modified
 * - created_by     : User ID who created
 * - created_at     : Timestamp pembuatan
 * - updated_at     : Timestamp update terakhir
 *
 * Foreign Keys:
 * - group_id       : REFERENCES app_sm_workflow_groups(id) ON DELETE SET NULL
 *
 * Indexes:
 * - slug           : UNIQUE KEY
 * - group_id       : KEY untuk query by group
 * - entity_type    : KEY untuk query by entity
 * - plugin_slug    : KEY untuk plugin isolation queries
 * - is_active      : KEY untuk filter active machines
 * - is_custom      : KEY untuk tracking modifications
 * - created_by     : KEY untuk audit
 *
 * Changelog:
 * 1.1.0 - 2025-11-07
 * - Added plugin_slug field for plugin isolation
 * - Added group_id FK to workflow_groups
 * - Added is_custom flag for tracking modifications
 * - Support for decentralized pattern
 */

namespace WPStateMachine\Database\Tables;

defined('ABSPATH') || exit;

class StateMachinesDB {
    /**
     * Get table schema for state machines
     *
     * @return string SQL schema
     */
    public static function get_schema() {
        global $wpdb;
        $table_name = $wpdb->prefix . 'app_sm_machines';
        $charset_collate = $wpdb->get_charset_collate();

        return "CREATE TABLE {$table_name} (
            id bigint(20) UNSIGNED NOT NULL auto_increment,
            workflow_group_id bigint(20) UNSIGNED NULL,
            name varchar(100) NOT NULL,
            slug varchar(100) NOT NULL,
            entity_type varchar(50) NOT NULL,
            description text NULL,
            plugin_slug varchar(100) NULL,
            is_active tinyint(1) NOT NULL DEFAULT 1,
            is_custom tinyint(1) NOT NULL DEFAULT 0,
            created_by bigint(20) UNSIGNED NOT NULL,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY  (id),
            UNIQUE KEY slug (slug),
            KEY workflow_group_id_index (workflow_group_id),
            KEY entity_type_index (entity_type),
            KEY plugin_slug_index (plugin_slug),
            KEY is_active_index (is_active),
            KEY is_custom_index (is_custom),
            KEY created_by_index (created_by)
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
        $table_name = $wpdb->prefix . 'app_sm_machines';
        $groups_table = $wpdb->prefix . 'app_sm_workflow_groups';

        $constraints = [
            [
                'name' => 'fk_machine_workflow_group',
                'sql' => "ALTER TABLE {$table_name}
                         ADD CONSTRAINT fk_machine_workflow_group
                         FOREIGN KEY (workflow_group_id)
                         REFERENCES {$groups_table}(id)
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
                error_log("[StateMachinesDB] Failed to add FK {$constraint['name']}: " . $wpdb->last_error);
            }
        }
    }
}
