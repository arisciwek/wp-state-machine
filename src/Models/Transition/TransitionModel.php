<?php
/**
 * Transition Model Class
 *
 * @package     WP_State_Machine
 * @subpackage  Models/Transition
 * @version     1.0.0
 * @author      arisciwek
 *
 * Path: /wp-state-machine/src/Models/Transition/TransitionModel.php
 *
 * Description: Handles all database operations for state machine transitions.
 *              Extends AbstractStateMachineModel for CRUD operations.
 *              Includes cache integration and WordPress hooks.
 *
 * Dependencies:
 * - AbstractStateMachineModel: Base CRUD operations
 * - StateMachineCacheManager: Caching layer
 * - WordPress $wpdb: Database operations
 *
 * Hooks Fired (via AbstractStateMachineModel):
 * - wp_state_machine_transition_before_insert: Before transition creation
 * - wp_state_machine_transition_created: After transition creation
 * - wp_state_machine_transition_updated: After transition update
 * - wp_state_machine_transition_before_delete: Before transition deletion
 * - wp_state_machine_transition_deleted: After transition deletion
 *
 * Changelog:
 * 1.0.0 - 2025-11-07
 * - Initial creation extending AbstractStateMachineModel
 * - CRUD operations inherited from base class
 * - Custom methods for machine and state queries
 * - Enhanced find() with state name JOINs
 */

namespace WPStateMachine\Models\Transition;

use WPStateMachine\Models\AbstractStateMachineModel;
use WPStateMachine\Cache\StateMachineCacheManager;

defined('ABSPATH') || exit;

class TransitionModel extends AbstractStateMachineModel {
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
        return $wpdb->prefix . 'app_sm_transitions';
    }

    /**
     * Get cache key for this entity
     *
     * @return string Cache key (e.g., 'Transition')
     */
    protected function getCacheKey(): string {
        return 'Transition';
    }

    /**
     * Get entity name for hooks
     *
     * @return string Entity name (singular, lowercase)
     */
    protected function getEntityName(): string {
        return 'transition';
    }

    /**
     * Get allowed fields for updates
     * Note: from_state_id and to_state_id should not be changed after creation
     *
     * @return array Field names that can be updated
     */
    protected function getAllowedFields(): array {
        return [
            'label',
            'guard_class',
            'metadata',
            'sort_order'
        ];
    }

    /**
     * Prepare data for insertion
     *
     * @param array $data Raw request data
     * @return array Prepared insert data
     */
    protected function prepareInsertData(array $data): array {
        // Prepare metadata as JSON if provided
        $metadata = null;
        if (isset($data['metadata'])) {
            $metadata = is_string($data['metadata'])
                ? $data['metadata']
                : json_encode($data['metadata']);
        }

        return [
            'machine_id' => (int) $data['machine_id'],
            'from_state_id' => (int) $data['from_state_id'],
            'to_state_id' => (int) $data['to_state_id'],
            'label' => $data['label'],
            'guard_class' => $data['guard_class'] ?? null,
            'metadata' => $metadata,
            'sort_order' => isset($data['sort_order']) ? (int) $data['sort_order'] : 0,
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
            'machine_id' => '%d',
            'from_state_id' => '%d',
            'to_state_id' => '%d',
            'label' => '%s',
            'guard_class' => '%s',
            'metadata' => '%s',
            'sort_order' => '%d',
            'created_at' => '%s',
            'updated_at' => '%s'
        ];
    }

    // ========================================
    // OVERRIDDEN METHODS (Enhanced with JOINs)
    // ========================================

    /**
     * Find transition by ID with state names
     * Overrides parent to include from/to state names via JOINs
     *
     * @param int $id Transition ID
     * @return object|null Transition object or null if not found
     */
    public function find(int $id): ?object {
        global $wpdb;

        // Try to get from cache
        $cache_type = strtolower(preg_replace('/(?<!^)[A-Z]/', '_$0', $this->getCacheKey()));
        $cached_result = $this->cache->get($cache_type, $id);
        if ($cached_result !== null) {
            return $cached_result;
        }

        // Query with JOINs for state names
        $sql = $wpdb->prepare(
            "SELECT t.*,
                    fs.name as from_state_name,
                    fs.slug as from_state_slug,
                    ts.name as to_state_name,
                    ts.slug as to_state_slug
             FROM {$this->getTableName()} t
             LEFT JOIN {$wpdb->prefix}app_sm_states fs ON t.from_state_id = fs.id
             LEFT JOIN {$wpdb->prefix}app_sm_states ts ON t.to_state_id = ts.id
             WHERE t.id = %d",
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
     * Invalidate transition cache
     * Overrides parent to also clear machine and state-specific list caches
     *
     * @param int $id Transition ID
     * @param mixed ...$additional_keys Additional cache keys
     */
    protected function invalidateCache(int $id, ...$additional_keys): void {
        // Call parent to invalidate entity cache
        parent::invalidateCache($id, ...$additional_keys);

        // Clear list caches
        $this->cache->delete('transitions_list');

        // If we have the transition data, clear machine-specific cache
        $transition = $this->find($id);
        if ($transition && isset($transition->machine_id)) {
            $this->cache->delete('transitions_by_machine', $transition->machine_id);

            // Clear from_state cache
            if (isset($transition->from_state_id)) {
                $this->cache->delete('transitions_from_state', $transition->from_state_id);
            }

            // Clear to_state cache
            if (isset($transition->to_state_id)) {
                $this->cache->delete('transitions_to_state', $transition->to_state_id);
            }
        }
    }

    // ========================================
    // CUSTOM METHODS (Transition specific)
    // ========================================

    /**
     * Get all transitions for a specific machine
     *
     * @param int $machine_id State machine ID
     * @return array Array of transition objects with state names
     */
    public function getByMachine(int $machine_id): array {
        global $wpdb;

        // Try cache first
        $cached = $this->cache->get('transitions_by_machine', $machine_id);
        if ($cached !== null) {
            return $cached;
        }

        $sql = $wpdb->prepare(
            "SELECT t.*,
                    fs.name as from_state_name,
                    fs.slug as from_state_slug,
                    ts.name as to_state_name,
                    ts.slug as to_state_slug
             FROM {$this->getTableName()} t
             LEFT JOIN {$wpdb->prefix}app_sm_states fs ON t.from_state_id = fs.id
             LEFT JOIN {$wpdb->prefix}app_sm_states ts ON t.to_state_id = ts.id
             WHERE t.machine_id = %d
             ORDER BY t.sort_order ASC, t.label ASC",
            $machine_id
        );

        $results = $wpdb->get_results($sql);

        // Cache the results
        $this->cache->set('transitions_by_machine', $results, 300, $machine_id);

        return $results;
    }

    /**
     * Get available transitions from a specific state
     *
     * @param int $from_state_id Source state ID
     * @return array Array of available transition objects
     */
    public function getAvailableTransitions(int $from_state_id): array {
        global $wpdb;

        // Try cache first
        $cached = $this->cache->get('transitions_from_state', $from_state_id);
        if ($cached !== null) {
            return $cached;
        }

        $sql = $wpdb->prepare(
            "SELECT t.*,
                    fs.name as from_state_name,
                    fs.slug as from_state_slug,
                    ts.name as to_state_name,
                    ts.slug as to_state_slug
             FROM {$this->getTableName()} t
             LEFT JOIN {$wpdb->prefix}app_sm_states fs ON t.from_state_id = fs.id
             LEFT JOIN {$wpdb->prefix}app_sm_states ts ON t.to_state_id = ts.id
             WHERE t.from_state_id = %d
             ORDER BY t.sort_order ASC, t.label ASC",
            $from_state_id
        );

        $results = $wpdb->get_results($sql);

        // Cache the results
        $this->cache->set('transitions_from_state', $results, 300, $from_state_id);

        return $results;
    }

    /**
     * Get transitions that lead to a specific state
     *
     * @param int $to_state_id Target state ID
     * @return array Array of transition objects
     */
    public function getTransitionsToState(int $to_state_id): array {
        global $wpdb;

        // Try cache first
        $cached = $this->cache->get('transitions_to_state', $to_state_id);
        if ($cached !== null) {
            return $cached;
        }

        $sql = $wpdb->prepare(
            "SELECT t.*,
                    fs.name as from_state_name,
                    fs.slug as from_state_slug,
                    ts.name as to_state_name,
                    ts.slug as to_state_slug
             FROM {$this->getTableName()} t
             LEFT JOIN {$wpdb->prefix}app_sm_states fs ON t.from_state_id = fs.id
             LEFT JOIN {$wpdb->prefix}app_sm_states ts ON t.to_state_id = ts.id
             WHERE t.to_state_id = %d
             ORDER BY t.sort_order ASC, t.label ASC",
            $to_state_id
        );

        $results = $wpdb->get_results($sql);

        // Cache the results
        $this->cache->set('transitions_to_state', $results, 300, $to_state_id);

        return $results;
    }

    /**
     * Get a specific transition between two states
     *
     * @param int $from_state_id Source state ID
     * @param int $to_state_id Target state ID
     * @return object|null Transition object or null
     */
    public function getTransition(int $from_state_id, int $to_state_id): ?object {
        global $wpdb;

        $sql = $wpdb->prepare(
            "SELECT t.*,
                    fs.name as from_state_name,
                    fs.slug as from_state_slug,
                    ts.name as to_state_name,
                    ts.slug as to_state_slug
             FROM {$this->getTableName()} t
             LEFT JOIN {$wpdb->prefix}app_sm_states fs ON t.from_state_id = fs.id
             LEFT JOIN {$wpdb->prefix}app_sm_states ts ON t.to_state_id = ts.id
             WHERE t.from_state_id = %d AND t.to_state_id = %d
             LIMIT 1",
            $from_state_id,
            $to_state_id
        );

        return $wpdb->get_row($sql);
    }

    /**
     * Check if a transition exists between two states
     *
     * @param int $from_state_id Source state ID
     * @param int $to_state_id Target state ID
     * @param int|null $exclude_id Transition ID to exclude
     * @return bool True if transition exists
     */
    public function transitionExists(int $from_state_id, int $to_state_id, ?int $exclude_id = null): bool {
        global $wpdb;

        $sql = $wpdb->prepare(
            "SELECT COUNT(*) FROM {$this->getTableName()}
             WHERE from_state_id = %d AND to_state_id = %d",
            $from_state_id,
            $to_state_id
        );

        if ($exclude_id !== null) {
            $sql .= $wpdb->prepare(" AND id != %d", $exclude_id);
        }

        $count = $wpdb->get_var($sql);

        return $count > 0;
    }

    /**
     * Update sort order for multiple transitions
     *
     * @param array $sort_data Array of ['id' => sort_order] pairs
     * @return bool True on success
     */
    public function updateSortOrder(array $sort_data): bool {
        global $wpdb;

        $success = true;

        foreach ($sort_data as $transition_id => $sort_order) {
            $result = $wpdb->update(
                $this->getTableName(),
                ['sort_order' => (int) $sort_order],
                ['id' => (int) $transition_id],
                ['%d'],
                ['%d']
            );

            if ($result === false) {
                $success = false;
                error_log("Failed to update sort order for transition {$transition_id}");
            }

            // Invalidate cache for this transition
            $this->invalidateCache((int) $transition_id);
        }

        return $success;
    }

    /**
     * Count transitions for a machine
     *
     * @param int $machine_id State machine ID
     * @return int Number of transitions
     */
    public function countByMachine(int $machine_id): int {
        global $wpdb;

        $sql = $wpdb->prepare(
            "SELECT COUNT(*) FROM {$this->getTableName()}
             WHERE machine_id = %d",
            $machine_id
        );

        return (int) $wpdb->get_var($sql);
    }

    /**
     * Delete all transitions for a machine
     * Used when deleting a machine
     *
     * @param int $machine_id State machine ID
     * @return bool True on success
     */
    public function deleteByMachine(int $machine_id): bool {
        global $wpdb;

        // Get all transition IDs first for cache invalidation
        $transition_ids = $wpdb->get_col($wpdb->prepare(
            "SELECT id FROM {$this->getTableName()} WHERE machine_id = %d",
            $machine_id
        ));

        // Delete all transitions
        $result = $wpdb->delete(
            $this->getTableName(),
            ['machine_id' => $machine_id],
            ['%d']
        );

        // Invalidate cache for each transition
        foreach ($transition_ids as $transition_id) {
            $this->invalidateCache((int) $transition_id);
        }

        // Clear machine-specific cache
        $this->cache->delete('transitions_by_machine', $machine_id);

        return $result !== false;
    }

    /**
     * Delete all transitions involving a specific state
     * Used when deleting a state
     *
     * @param int $state_id State ID
     * @return bool True on success
     */
    public function deleteByState(int $state_id): bool {
        global $wpdb;

        // Get all transition IDs first for cache invalidation
        $transition_ids = $wpdb->get_col($wpdb->prepare(
            "SELECT id FROM {$this->getTableName()}
             WHERE from_state_id = %d OR to_state_id = %d",
            $state_id,
            $state_id
        ));

        // Delete transitions
        $wpdb->query($wpdb->prepare(
            "DELETE FROM {$this->getTableName()}
             WHERE from_state_id = %d OR to_state_id = %d",
            $state_id,
            $state_id
        ));

        // Invalidate cache for each transition
        foreach ($transition_ids as $transition_id) {
            $this->invalidateCache((int) $transition_id);
        }

        // Clear state-specific caches
        $this->cache->delete('transitions_from_state', $state_id);
        $this->cache->delete('transitions_to_state', $state_id);

        return true;
    }
}
