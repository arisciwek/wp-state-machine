<?php
/**
 * Workflow Group Model Class
 *
 * @package     WP_State_Machine
 * @subpackage  Models/WorkflowGroup
 * @version     1.0.0
 * @author      arisciwek
 *
 * Path: /wp-state-machine/src/Models/WorkflowGroup/WorkflowGroupModel.php
 *
 * Description: Handles all database operations for workflow groups.
 *              Extends AbstractStateMachineModel for CRUD operations.
 *              Parent table untuk organizing state machines.
 *              Includes cache integration and WordPress hooks.
 *
 * Dependencies:
 * - AbstractStateMachineModel: Base CRUD operations
 * - StateMachineCacheManager: Caching layer
 * - WordPress $wpdb: Database operations
 *
 * Hooks Fired (via AbstractStateMachineModel):
 * - wp_state_machine_workflow_group_before_insert: Before group creation
 * - wp_state_machine_workflow_group_created: After group creation
 * - wp_state_machine_workflow_group_updated: After group update
 * - wp_state_machine_workflow_group_before_delete: Before group deletion
 * - wp_state_machine_workflow_group_deleted: After group deletion
 *
 * Changelog:
 * 1.0.0 - 2025-11-07 (TODO-6102 PRIORITAS #7)
 * - Initial creation for FASE 3
 * - Extends AbstractStateMachineModel
 * - Basic CRUD operations with cache
 * - DataTable support
 * - Machine count tracking
 */

namespace WPStateMachine\Models\WorkflowGroup;

use WPStateMachine\Models\AbstractStateMachineModel;
use WPStateMachine\Cache\StateMachineCacheManager;

defined('ABSPATH') || exit;

class WorkflowGroupModel extends AbstractStateMachineModel {
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
        return $wpdb->prefix . 'app_sm_workflow_groups';
    }

    /**
     * Get cache key for this entity
     *
     * @return string Cache key (e.g., 'WorkflowGroup')
     */
    protected function getCacheKey(): string {
        return 'WorkflowGroup';
    }

    /**
     * Get entity name for hooks
     *
     * @return string Entity name (singular, lowercase)
     */
    protected function getEntityName(): string {
        return 'workflow_group';
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
            'description',
            'icon',
            'sort_order',
            'is_active',
            'is_custom'
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
            'description' => $data['description'] ?? '',
            'icon' => $data['icon'] ?? 'dashicons-networking',
            'sort_order' => $data['sort_order'] ?? 0,
            'is_active' => isset($data['is_active']) ? (int) $data['is_active'] : 1,
            'is_custom' => 0, // Default: not custom
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
            'description' => '%s',
            'icon' => '%s',
            'sort_order' => '%d',
            'is_active' => '%d',
            'is_custom' => '%d',
            'created_at' => '%s',
            'updated_at' => '%s'
        ];
    }

    // ========================================
    // CUSTOM METHODS
    // ========================================

    /**
     * Get groups with machine count
     * For DataTable display
     *
     * @param array $params DataTable parameters
     * @return array DataTable response data
     */
    public function getForDataTable(array $params): array {
        global $wpdb;
        $table = $this->getTableName();
        $machines_table = $wpdb->prefix . 'app_sm_machines';

        // Base query with machine count
        $query = "
            SELECT
                g.*,
                COUNT(m.id) as machine_count
            FROM {$table} g
            LEFT JOIN {$machines_table} m ON g.id = m.workflow_group_id
        ";

        // Search
        $where = [];
        if (!empty($params['search'])) {
            $search = '%' . $wpdb->esc_like($params['search']) . '%';
            $where[] = $wpdb->prepare(
                "(g.name LIKE %s OR g.slug LIKE %s OR g.description LIKE %s)",
                $search, $search, $search
            );
        }

        // Active filter
        if (isset($params['is_active']) && $params['is_active'] !== '') {
            $where[] = $wpdb->prepare("g.is_active = %d", $params['is_active']);
        }

        // Combine WHERE
        if (!empty($where)) {
            $query .= " WHERE " . implode(" AND ", $where);
        }

        // Group by
        $query .= " GROUP BY g.id";

        // Total records (before pagination)
        $total_query = "SELECT COUNT(*) FROM ({$query}) as temp";
        $total = $wpdb->get_var($total_query);

        // Ordering
        $order_column = $params['order_column'] ?? 'sort_order';
        $order_dir = strtoupper($params['order_dir'] ?? 'ASC') === 'DESC' ? 'DESC' : 'ASC';
        $query .= $wpdb->prepare(" ORDER BY g.{$order_column} {$order_dir}");

        // Pagination
        if (isset($params['length']) && $params['length'] > 0) {
            $query .= $wpdb->prepare(
                " LIMIT %d OFFSET %d",
                $params['length'],
                $params['start'] ?? 0
            );
        }

        // Get results
        $results = $wpdb->get_results($query);

        return [
            'draw' => $params['draw'] ?? 1,
            'recordsTotal' => $total,
            'recordsFiltered' => $total,
            'data' => $results
        ];
    }

    /**
     * Get active groups ordered by sort_order
     * For dropdown selects
     *
     * @return array Active groups
     */
    public function getActiveGroups(): array {
        global $wpdb;
        $table = $this->getTableName();
        $cache_key = 'active_groups';

        // Try cache
        $cached = $this->cache_manager->get($cache_key);
        if ($cached !== false) {
            return $cached;
        }

        // Query
        $results = $wpdb->get_results(
            "SELECT id, name, slug, icon, sort_order
             FROM {$table}
             WHERE is_active = 1
             ORDER BY sort_order ASC, name ASC"
        );

        // Cache for 1 hour
        $this->cache_manager->set($cache_key, $results, 3600);

        return $results;
    }

    /**
     * Get group by slug
     *
     * @param string $slug Group slug
     * @return object|null Group data or null
     */
    public function findBySlug(string $slug): ?object {
        return $this->findBy('slug', $slug);
    }

    /**
     * Update sort order for multiple groups
     * Used for drag-drop reordering
     *
     * @param array $order_data Array of ['id' => sort_order]
     * @return bool Success status
     */
    public function updateSortOrders(array $order_data): bool {
        global $wpdb;
        $table = $this->getTableName();

        $wpdb->query('START TRANSACTION');

        try {
            foreach ($order_data as $id => $sort_order) {
                $wpdb->update(
                    $table,
                    ['sort_order' => (int) $sort_order],
                    ['id' => (int) $id],
                    ['%d'],
                    ['%d']
                );
            }

            $wpdb->query('COMMIT');

            // Clear cache
            $this->cache_manager->flush();

            return true;
        } catch (\Exception $e) {
            $wpdb->query('ROLLBACK');
            error_log('Failed to update sort orders: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Check if slug exists (for validation)
     *
     * @param string $slug Slug to check
     * @param int|null $exclude_id ID to exclude from check (for updates)
     * @return bool True if exists
     */
    public function slugExists(string $slug, ?int $exclude_id = null): bool {
        global $wpdb;
        $table = $this->getTableName();

        $query = $wpdb->prepare(
            "SELECT COUNT(*) FROM {$table} WHERE slug = %s",
            $slug
        );

        if ($exclude_id) {
            $query .= $wpdb->prepare(" AND id != %d", $exclude_id);
        }

        return $wpdb->get_var($query) > 0;
    }

    /**
     * Get machines assigned to this group
     *
     * @param int $group_id Group ID
     * @return array Machines in this group
     */
    public function getMachines(int $group_id): array {
        global $wpdb;
        $machines_table = $wpdb->prefix . 'app_sm_machines';

        return $wpdb->get_results($wpdb->prepare(
            "SELECT id, name, slug, plugin_slug, entity_type
             FROM {$machines_table}
             WHERE workflow_group_id = %d
             ORDER BY name ASC",
            $group_id
        ));
    }

    /**
     * Mark group as custom (user modified)
     * Called after any update operation
     *
     * @param int $id Group ID
     * @return bool Success status
     */
    public function markAsCustom(int $id): bool {
        global $wpdb;
        $table = $this->getTableName();

        $result = $wpdb->update(
            $table,
            ['is_custom' => 1],
            ['id' => $id],
            ['%d'],
            ['%d']
        );

        if ($result !== false) {
            $this->cache_manager->delete($id);
        }

        return $result !== false;
    }
}
