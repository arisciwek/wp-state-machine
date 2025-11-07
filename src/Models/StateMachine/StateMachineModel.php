<?php
/**
 * State Machine Model Class
 *
 * @package     WP_State_Machine
 * @subpackage  Models/StateMachine
 * @version     1.1.0
 * @author      arisciwek
 *
 * Path: /wp-state-machine/src/Models/StateMachine/StateMachineModel.php
 *
 * Description: Handles all database operations for state machines.
 *              Extends AbstractStateMachineModel for CRUD operations.
 *              Includes cache integration and WordPress hooks.
 *
 * Dependencies:
 * - AbstractStateMachineModel: Base CRUD operations
 * - StateMachineCacheManager: Caching layer
 * - WordPress $wpdb: Database operations
 *
 * Hooks Fired (via AbstractStateMachineModel):
 * - wp_state_machine_state_machine_before_insert: Before machine creation
 * - wp_state_machine_state_machine_created: After machine creation
 * - wp_state_machine_state_machine_updated: After machine update
 * - wp_state_machine_state_machine_before_delete: Before machine deletion
 * - wp_state_machine_state_machine_deleted: After machine deletion
 *
 * Changelog:
 * 1.1.0 - 2025-11-07
 * - Refactored to extend AbstractStateMachineModel
 * - 60%+ code reduction by inheriting CRUD operations
 * - Maintained all custom methods (getByPlugin, getForDataTable, etc.)
 * 1.0.0 - 2025-11-07
 * - Initial creation
 * - CRUD operations with cache
 * - WordPress hooks integration
 * - DataTable support
 */

namespace WPStateMachine\Models\StateMachine;

use WPStateMachine\Models\AbstractStateMachineModel;
use WPStateMachine\Cache\StateMachineCacheManager;

defined('ABSPATH') || exit;

class StateMachineModel extends AbstractStateMachineModel {
    /**
     * Constructor
     * Initializes cache manager and passes to parent
     */
    public function __construct() {
        parent::__construct(new StateMachineCacheManager());
    }

    // ========================================
    // ABSTRACT METHOD IMPLEMENTATIONS
    // ========================================

    /**
     * Get database table name
     *
     * @return string Full table name with prefix
     */
    protected function getTableName(): string {
        global $wpdb;
        return $wpdb->prefix . 'app_sm_machines';
    }

    /**
     * Get cache key for this entity
     *
     * @return string Cache key (e.g., 'StateMachine')
     */
    protected function getCacheKey(): string {
        return 'StateMachine';
    }

    /**
     * Get entity name for hooks
     *
     * @return string Entity name (singular, lowercase)
     */
    protected function getEntityName(): string {
        return 'state_machine';
    }

    /**
     * Get allowed fields for updates
     *
     * @return array Field names that can be updated
     */
    protected function getAllowedFields(): array {
        return [
            'name',
            'slug',
            'plugin_slug',
            'entity_type',
            'workflow_group_id',
            'description',
            'is_active'
        ];
    }

    /**
     * Prepare data for insertion
     *
     * @param array $data Raw request data
     * @return array Prepared insert data
     */
    protected function prepareInsertData(array $data): array {
        return [
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
    }

    /**
     * Get format map for wpdb
     *
     * @return array Field => format map (%d for int, %s for string)
     */
    protected function getFormatMap(): array {
        return [
            'id' => '%d',
            'name' => '%s',
            'slug' => '%s',
            'plugin_slug' => '%s',
            'entity_type' => '%s',
            'workflow_group_id' => '%d',
            'description' => '%s',
            'is_active' => '%d',
            'created_at' => '%s',
            'updated_at' => '%s'
        ];
    }

    // ========================================
    // OVERRIDDEN METHODS (Enhanced with JOINs)
    // ========================================

    /**
     * Find state machine by ID with workflow group data
     * Overrides parent to include workflow group name/label via JOIN
     *
     * @param int $id State machine ID
     * @return object|null State machine object or null if not found
     */
    public function find(int $id): ?object {
        global $wpdb;

        // Try to get from cache
        $cache_type = strtolower(preg_replace('/(?<!^)[A-Z]/', '_$0', $this->getCacheKey()));
        $cached_result = $this->cache->get($cache_type, $id);
        if ($cached_result !== null) {
            return $cached_result;
        }

        // Query with JOIN for workflow group data
        $sql = $wpdb->prepare(
            "SELECT sm.*,
                    wg.name as workflow_group_name,
                    wg.label as workflow_group_label
             FROM {$this->getTableName()} sm
             LEFT JOIN {$wpdb->prefix}app_sm_workflow_groups wg ON sm.workflow_group_id = wg.id
             WHERE sm.id = %d",
            $id
        );

        $result = $wpdb->get_row($sql);

        if ($result) {
            // Cache the result
            $this->cache->set($cache_type, $result, 120, $id);
        }

        return $result;
    }

    /**
     * Invalidate state machine cache
     * Overrides parent to also clear list caches
     *
     * @param int $id Entity ID
     * @param mixed ...$additional_keys Additional cache keys
     */
    protected function invalidateCache(int $id, ...$additional_keys): void {
        // Call parent to invalidate entity cache
        parent::invalidateCache($id, ...$additional_keys);

        // Also clear list caches specific to state machines
        $this->cache->delete('state_machines_list');
        $this->cache->delete('state_machines_count', 'total');
    }

    // ========================================
    // CUSTOM METHODS (State Machine specific)
    // ========================================

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
