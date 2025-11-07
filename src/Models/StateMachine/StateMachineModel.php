<?php
/**
 * State Machine Model Class
 *
 * @package     WP_State_Machine
 * @subpackage  Models/StateMachine
 * @version     1.0.0
 * @author      arisciwek
 *
 * Path: /wp-state-machine/src/Models/StateMachine/StateMachineModel.php
 *
 * Description: Handles all database operations for state machines.
 *              Follows wp-agency AgencyModel pattern.
 *              Includes cache integration and WordPress hooks.
 *
 * Dependencies:
 * - StateMachineCacheManager: Caching layer
 * - WordPress $wpdb: Database operations
 *
 * Hooks Fired:
 * - wp_state_machine_created: After machine creation
 * - wp_state_machine_updated: After machine update
 * - wp_state_machine_deleted: After machine deletion
 * - wp_state_machine_before_delete: Before machine deletion
 *
 * Changelog:
 * 1.0.0 - 2025-11-07
 * - Initial creation
 * - CRUD operations with cache
 * - WordPress hooks integration
 * - DataTable support
 * - Follow wp-agency pattern exactly
 */

namespace WPStateMachine\Models\StateMachine;

use WPStateMachine\Cache\StateMachineCacheManager;

defined('ABSPATH') || exit;

class StateMachineModel {
    /**
     * Database table name
     *
     * @var string
     */
    private $table_name;

    /**
     * Cache Manager instance
     *
     * @var StateMachineCacheManager
     */
    private $cache;

    /**
     * Constructor
     * Initializes table name and cache manager
     */
    public function __construct() {
        global $wpdb;
        $this->table_name = $wpdb->prefix . 'app_sm_machines';
        $this->cache = new StateMachineCacheManager();
    }

    /**
     * Find state machine by ID
     * Includes cache integration
     *
     * @param int $id State machine ID
     * @return object|null State machine object or null if not found
     */
    public function find(int $id): ?object {
        global $wpdb;

        // Try to get from cache
        $cached_result = $this->cache->get('state_machine', $id);
        if ($cached_result !== null) {
            return $cached_result;
        }

        $sql = $wpdb->prepare(
            "SELECT sm.*,
                    wg.name as workflow_group_name,
                    wg.label as workflow_group_label
             FROM {$this->table_name} sm
             LEFT JOIN {$wpdb->prefix}app_sm_workflow_groups wg ON sm.workflow_group_id = wg.id
             WHERE sm.id = %d",
            $id
        );

        $result = $wpdb->get_row($sql);

        if ($result) {
            // Cache the result
            $this->cache->set('state_machine', $result, 120, $id);
        }

        return $result;
    }

    /**
     * Create new state machine
     * Fires wp_state_machine_created hook
     *
     * @param array $data State machine data
     * @return int|false New state machine ID or false on failure
     */
    public function create(array $data) {
        global $wpdb;

        // Prepare data for insertion
        $insert_data = [
            'name' => $data['name'],
            'slug' => $data['slug'],
            'plugin_slug' => $data['plugin_slug'],
            'entity_type' => $data['entity_type'],
            'workflow_group_id' => $data['workflow_group_id'] ?? null,
            'description' => $data['description'] ?? '',
            'is_active' => isset($data['is_active']) ? (int) $data['is_active'] : 1,
            'created_at' => current_time('mysql'),
            'updated_at' => current_time('mysql')
        ];

        $format = [
            '%s', // name
            '%s', // slug
            '%s', // plugin_slug
            '%s', // entity_type
            '%d', // workflow_group_id
            '%s', // description
            '%d', // is_active
            '%s', // created_at
            '%s'  // updated_at
        ];

        $result = $wpdb->insert($this->table_name, $insert_data, $format);

        if ($result === false) {
            error_log('Failed to create state machine: ' . $wpdb->last_error);
            return false;
        }

        $machine_id = $wpdb->insert_id;

        // Clear cache
        $this->cache->delete('state_machines_list');

        // Fire hook
        do_action('wp_state_machine_created', $machine_id, $data);

        return $machine_id;
    }

    /**
     * Update existing state machine
     * Fires wp_state_machine_updated hook
     *
     * @param int $id State machine ID
     * @param array $data Updated data
     * @return bool True on success, false on failure
     */
    public function update(int $id, array $data): bool {
        global $wpdb;

        // Get old data for hook
        $old_data = $this->find($id);
        if (!$old_data) {
            return false;
        }

        // Prepare data for update
        $update_data = [
            'name' => $data['name'],
            'slug' => $data['slug'],
            'plugin_slug' => $data['plugin_slug'],
            'entity_type' => $data['entity_type'],
            'workflow_group_id' => $data['workflow_group_id'] ?? null,
            'description' => $data['description'] ?? '',
            'is_active' => isset($data['is_active']) ? (int) $data['is_active'] : 1,
            'updated_at' => current_time('mysql')
        ];

        $format = [
            '%s', // name
            '%s', // slug
            '%s', // plugin_slug
            '%s', // entity_type
            '%d', // workflow_group_id
            '%s', // description
            '%d', // is_active
            '%s'  // updated_at
        ];

        $where = ['id' => $id];
        $where_format = ['%d'];

        $result = $wpdb->update($this->table_name, $update_data, $where, $format, $where_format);

        if ($result === false) {
            error_log('Failed to update state machine: ' . $wpdb->last_error);
            return false;
        }

        // Clear cache
        $this->cache->delete('state_machines_list');
        $this->cache->delete('state_machine', $id);

        // Fire hook
        do_action('wp_state_machine_updated', $id, $data, $old_data);

        return true;
    }

    /**
     * Delete state machine
     * Fires wp_state_machine_before_delete and wp_state_machine_deleted hooks
     *
     * @param int $id State machine ID
     * @return bool True on success, false on failure
     */
    public function delete(int $id): bool {
        global $wpdb;

        // Get data before deletion for hook
        $machine = $this->find($id);
        if (!$machine) {
            return false;
        }

        // Fire before delete hook
        do_action('wp_state_machine_before_delete', $id, $machine);

        // Delete the state machine
        $result = $wpdb->delete(
            $this->table_name,
            ['id' => $id],
            ['%d']
        );

        if ($result === false) {
            error_log('Failed to delete state machine: ' . $wpdb->last_error);
            return false;
        }

        // Clear cache
        $this->cache->delete('state_machines_list');
        $this->cache->delete('state_machine', $id);

        // Fire after delete hook
        do_action('wp_state_machine_deleted', $id, $machine);

        return true;
    }

    /**
     * Get state machines for DataTable
     * Supports pagination, search, and sorting
     *
     * @param array $params DataTable parameters
     * @return array Array of state machine objects
     */
    public function getForDataTable(array $params): array {
        global $wpdb;

        $start = isset($params['start']) ? intval($params['start']) : 0;
        $length = isset($params['length']) ? intval($params['length']) : 10;
        $search = isset($params['search']) ? sanitize_text_field($params['search']) : '';
        $order_by = isset($params['order_by']) ? sanitize_text_field($params['order_by']) : 'id';
        $order_dir = isset($params['order_dir']) ? sanitize_text_field($params['order_dir']) : 'DESC';

        // Validate order direction
        $order_dir = in_array(strtoupper($order_dir), ['ASC', 'DESC']) ? $order_dir : 'DESC';

        // Build query
        $sql = "SELECT sm.*,
                       wg.name as workflow_group_name,
                       wg.label as workflow_group_label
                FROM {$this->table_name} sm
                LEFT JOIN {$wpdb->prefix}app_sm_workflow_groups wg ON sm.workflow_group_id = wg.id";

        // Add search condition
        if (!empty($search)) {
            $search_term = '%' . $wpdb->esc_like($search) . '%';
            $sql .= $wpdb->prepare(
                " WHERE (sm.name LIKE %s
                        OR sm.slug LIKE %s
                        OR sm.plugin_slug LIKE %s
                        OR sm.entity_type LIKE %s
                        OR sm.description LIKE %s
                        OR wg.name LIKE %s)",
                $search_term,
                $search_term,
                $search_term,
                $search_term,
                $search_term,
                $search_term
            );
        }

        // Add ordering
        $sql .= " ORDER BY sm.{$order_by} {$order_dir}";

        // Add pagination
        $sql .= $wpdb->prepare(" LIMIT %d, %d", $start, $length);

        return $wpdb->get_results($sql);
    }

    /**
     * Get total count of state machines
     *
     * @return int Total number of state machines
     */
    public function getTotalCount(): int {
        global $wpdb;

        // Try to get from cache
        $cached_count = $this->cache->get('state_machines_count', 'total');
        if ($cached_count !== null) {
            return (int) $cached_count;
        }

        $count = $wpdb->get_var("SELECT COUNT(*) FROM {$this->table_name}");

        // Cache the count
        $this->cache->set('state_machines_count', $count, 300, 'total');

        return (int) $count;
    }

    /**
     * Get filtered count for search
     *
     * @param string $search Search term
     * @return int Filtered count
     */
    public function getFilteredCount(string $search): int {
        global $wpdb;

        if (empty($search)) {
            return $this->getTotalCount();
        }

        $search_term = '%' . $wpdb->esc_like($search) . '%';

        $sql = $wpdb->prepare(
            "SELECT COUNT(*)
             FROM {$this->table_name} sm
             LEFT JOIN {$wpdb->prefix}app_sm_workflow_groups wg ON sm.workflow_group_id = wg.id
             WHERE (sm.name LIKE %s
                    OR sm.slug LIKE %s
                    OR sm.plugin_slug LIKE %s
                    OR sm.entity_type LIKE %s
                    OR sm.description LIKE %s
                    OR wg.name LIKE %s)",
            $search_term,
            $search_term,
            $search_term,
            $search_term,
            $search_term,
            $search_term
        );

        return (int) $wpdb->get_var($sql);
    }

    /**
     * Get state machines by plugin slug
     *
     * @param string $plugin_slug Plugin slug
     * @return array Array of state machine objects
     */
    public function getByPlugin(string $plugin_slug): array {
        global $wpdb;

        $sql = $wpdb->prepare(
            "SELECT sm.*,
                    wg.name as workflow_group_name,
                    wg.label as workflow_group_label
             FROM {$this->table_name} sm
             LEFT JOIN {$wpdb->prefix}app_sm_workflow_groups wg ON sm.workflow_group_id = wg.id
             WHERE sm.plugin_slug = %s
             ORDER BY sm.created_at DESC",
            $plugin_slug
        );

        return $wpdb->get_results($sql);
    }

    /**
     * Get state machines by workflow group
     *
     * @param int $workflow_group_id Workflow group ID
     * @return array Array of state machine objects
     */
    public function getByWorkflowGroup(int $workflow_group_id): array {
        global $wpdb;

        $sql = $wpdb->prepare(
            "SELECT * FROM {$this->table_name}
             WHERE workflow_group_id = %d
             ORDER BY name ASC",
            $workflow_group_id
        );

        return $wpdb->get_results($sql);
    }

    /**
     * Check if slug exists
     *
     * @param string $slug Slug to check
     * @param int|null $exclude_id ID to exclude from check
     * @return bool True if slug exists, false otherwise
     */
    public function slugExists(string $slug, ?int $exclude_id = null): bool {
        global $wpdb;

        $sql = $wpdb->prepare(
            "SELECT COUNT(*) FROM {$this->table_name} WHERE slug = %s",
            $slug
        );

        if ($exclude_id !== null) {
            $sql .= $wpdb->prepare(" AND id != %d", $exclude_id);
        }

        $count = $wpdb->get_var($sql);

        return $count > 0;
    }

    /**
     * Get active state machines
     *
     * @return array Array of active state machine objects
     */
    public function getActive(): array {
        global $wpdb;

        // Try to get from cache
        $cached_result = $this->cache->get('state_machines_list', 'active');
        if ($cached_result !== null) {
            return $cached_result;
        }

        $sql = "SELECT sm.*,
                       wg.name as workflow_group_name,
                       wg.label as workflow_group_label
                FROM {$this->table_name} sm
                LEFT JOIN {$wpdb->prefix}app_sm_workflow_groups wg ON sm.workflow_group_id = wg.id
                WHERE sm.is_active = 1
                ORDER BY sm.name ASC";

        $results = $wpdb->get_results($sql);

        // Cache the results
        $this->cache->set('state_machines_list', $results, 300, 'active');

        return $results;
    }
}
