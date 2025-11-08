<?php
/**
 * Workflow Groups Table Schema
 *
 * @package     WP_State_Machine
 * @subpackage  Database/Tables
 * @version     1.1.0
 * @author      arisciwek
 *
 * Path: /wp-state-machine/src/Database/Tables/WorkflowGroupsDB.php
 *
 * Description: Mendefinisikan struktur tabel workflow_groups.
 *              Parent table untuk grouping state machines.
 *              Adopted from Drupal Workflow Groups pattern.
 *              Supports 16+ plugins ecosystem organization.
 *
 * Fields:
 * - id             : Primary key
 * - name           : Group name (e.g., "B2B Transaction Flow")
 * - label          : Display label (nullable, friendly name for UI)
 * - slug           : Unique slug
 * - description    : Group description (nullable)
 * - icon           : Dashicon class (nullable)
 * - sort_order     : Display order
 * - is_active      : Active status
 * - is_custom      : 0=default, 1=user modified
 * - created_at     : Timestamp pembuatan
 * - updated_at     : Timestamp update terakhir
 *
 * Foreign Keys: None (parent table)
 *
 * Indexes:
 * - slug           : UNIQUE KEY
 * - is_active      : KEY untuk filter active groups
 * - is_custom      : KEY untuk tracking modifications
 * - sort_order     : KEY untuk display ordering
 *
 * Changelog:
 * 1.1.0 - 2025-11-07
 * - Initial creation for workflow groups
 * - Added is_custom flag for tracking user modifications
 * - Support for plugin ecosystem organization
 */

namespace WPStateMachine\Database\Tables;

defined('ABSPATH') || exit;

class WorkflowGroupsDB {
    /**
     * Get table schema for workflow groups
     *
     * @return string SQL schema
     */
    public static function get_schema() {
        global $wpdb;
        $table_name = $wpdb->prefix . 'app_sm_workflow_groups';
        $charset_collate = $wpdb->get_charset_collate();

        return "CREATE TABLE {$table_name} (
            id bigint(20) UNSIGNED NOT NULL auto_increment,
            name varchar(100) NOT NULL,
            label varchar(150) NULL,
            slug varchar(100) NOT NULL,
            description text NULL,
            icon varchar(50) NULL,
            sort_order int(11) NOT NULL DEFAULT 0,
            is_active tinyint(1) NOT NULL DEFAULT 1,
            is_custom tinyint(1) NOT NULL DEFAULT 0,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY  (id),
            UNIQUE KEY slug (slug),
            KEY is_active_index (is_active),
            KEY is_custom_index (is_custom),
            KEY sort_order_index (sort_order)
        ) $charset_collate;";
    }

    /**
     * No foreign keys needed (parent table)
     *
     * @return void
     */
    public static function add_foreign_keys() {
        // No foreign keys - this is the parent table
    }
}
