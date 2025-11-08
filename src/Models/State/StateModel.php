<?php
/**
 * State Model Class
 *
 * @package     WP_State_Machine
 * @subpackage  Models/State
 * @version     1.0.0
 * @author      arisciwek
 *
 * Path: /wp-state-machine/src/Models/State/StateModel.php
 *
 * Description: Handles all database operations for state machine states.
 *              Extends AbstractStateMachineModel for CRUD operations.
 *              Includes cache integration and WordPress hooks.
 *
 * Dependencies:
 * - AbstractStateMachineModel: Base CRUD operations
 * - StateMachineCacheManager: Caching layer
 * - WordPress $wpdb: Database operations
 *
 * Hooks Fired (via AbstractStateMachineModel):
 * - wp_state_machine_state_before_insert: Before state creation
 * - wp_state_machine_state_created: After state creation
 * - wp_state_machine_state_updated: After state update
 * - wp_state_machine_state_before_delete: Before state deletion
 * - wp_state_machine_state_deleted: After state deletion
 *
 * Changelog:
 * 1.0.0 - 2025-11-07
 * - Initial creation extending AbstractStateMachineModel
 * - CRUD operations inherited from base class
 * - Custom methods for machine-specific queries
 * - Type validation (initial, normal, final)
 */

namespace WPStateMachine\Models\State;

use WPStateMachine\Models\AbstractStateMachineModel;
use WPStateMachine\Cache\StateMachineCacheManager;

defined('ABSPATH') || exit;

class StateModel extends AbstractStateMachineModel {
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
        return $wpdb->prefix . 'app_sm_states';
    }

    /**
     * Get cache key for this entity
     *
     * @return string Cache key (e.g., 'State')
     */
    protected function getCacheKey(): string {
        return 'State';
    }

    /**
     * Get entity name for hooks
     *
     * @return string Entity name (singular, lowercase)
     */
    protected function getEntityName(): string {
        return 'state';
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
            'type',
            'color',
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
        // Validate and set state type
        $type = 'normal'; // default
        if (isset($data['type'])) {
            $state_type = strtolower($data['type']);
            if (in_array($state_type, ['initial', 'normal', 'final', 'intermediate'])) {
                // Map 'intermediate' to 'normal' for database
                $type = ($state_type === 'intermediate') ? 'normal' : $state_type;
            }
        }

        // Prepare metadata as JSON if provided
        $metadata = null;
        if (isset($data['metadata'])) {
            $metadata = is_string($data['metadata'])
                ? $data['metadata']
                : json_encode($data['metadata']);
        }

        return [
            'machine_id' => (int) $data['machine_id'],
            'name' => $data['name'],
            'slug' => $data['slug'],
            'type' => $type,
            'color' => $data['color'] ?? null,
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
            'name' => '%s',
            'slug' => '%s',
            'type' => '%s',
            'color' => '%s',
            'metadata' => '%s',
            'sort_order' => '%d',
            'created_at' => '%s',
            'updated_at' => '%s'
        ];
    }

    // ========================================
    // OVERRIDDEN METHODS
    // ========================================

    /**
     * Invalidate state cache
     * Overrides parent to also clear machine-specific list caches
     *
     * @param int $id State ID
     * @param mixed ...$additional_keys Additional cache keys
     */
    protected function invalidateCache(int $id, ...$additional_keys): void {
        // Call parent to invalidate entity cache
        parent::invalidateCache($id, ...$additional_keys);

        // Clear list caches
        $this->cache->delete('states_list');

        // If we have the state data, clear machine-specific cache
        $state = $this->find($id);
        if ($state && isset($state->machine_id)) {
            $this->cache->delete('states_by_machine', $state->machine_id);
        }
    }

    // ========================================
    // CUSTOM METHODS (State specific)
    // ========================================

    /**
     * Get all states for a specific machine
     *
     * @param int $machine_id State machine ID
     * @return array Array of state objects
     */
    public function getByMachine(int $machine_id): array {
        global $wpdb;

        // Try cache first
        $cached = $this->cache->get('states_by_machine', $machine_id);
        if ($cached !== null) {
            return $cached;
        }

        $sql = $wpdb->prepare(
            "SELECT * FROM {$this->getTableName()}
             WHERE machine_id = %d
             ORDER BY sort_order ASC, name ASC",
            $machine_id
        );

        $results = $wpdb->get_results($sql);

        // Cache the results
        $this->cache->set('states_by_machine', $results, 300, $machine_id);

        return $results;
    }

    /**
     * Get initial state for a machine
     *
     * @param int $machine_id State machine ID
     * @return object|null Initial state object or null
     */
    public function getInitialState(int $machine_id): ?object {
        global $wpdb;

        $sql = $wpdb->prepare(
            "SELECT * FROM {$this->getTableName()}
             WHERE machine_id = %d AND type = 'initial'
             LIMIT 1",
            $machine_id
        );

        return $wpdb->get_row($sql);
    }

    /**
     * Get all final states for a machine
     *
     * @param int $machine_id State machine ID
     * @return array Array of final state objects
     */
    public function getFinalStates(int $machine_id): array {
        global $wpdb;

        $sql = $wpdb->prepare(
            "SELECT * FROM {$this->getTableName()}
             WHERE machine_id = %d AND type = 'final'
             ORDER BY sort_order ASC, name ASC",
            $machine_id
        );

        return $wpdb->get_results($sql);
    }

    /**
     * Get states by type
     *
     * @param int $machine_id State machine ID
     * @param string $type State type (initial, normal, final)
     * @return array Array of state objects
     */
    public function getByType(int $machine_id, string $type): array {
        global $wpdb;

        $valid_types = ['initial', 'normal', 'final'];
        if (!in_array($type, $valid_types)) {
            return [];
        }

        $sql = $wpdb->prepare(
            "SELECT * FROM {$this->getTableName()}
             WHERE machine_id = %d AND type = %s
             ORDER BY sort_order ASC, name ASC",
            $machine_id,
            $type
        );

        return $wpdb->get_results($sql);
    }

    /**
     * Check if slug exists within a machine
     *
     * @param int $machine_id State machine ID
     * @param string $slug Slug to check
     * @param int|null $exclude_id ID to exclude from check
     * @return bool True if slug exists, false otherwise
     */
    public function slugExists(int $machine_id, string $slug, ?int $exclude_id = null): bool {
        global $wpdb;

        $sql = $wpdb->prepare(
            "SELECT COUNT(*) FROM {$this->getTableName()}
             WHERE machine_id = %d AND slug = %s",
            $machine_id,
            $slug
        );

        if ($exclude_id !== null) {
            $sql .= $wpdb->prepare(" AND id != %d", $exclude_id);
        }

        $count = $wpdb->get_var($sql);

        return $count > 0;
    }

    /**
     * Get state by slug within a machine
     *
     * @param int $machine_id State machine ID
     * @param string $slug State slug
     * @return object|null State object or null
     */
    public function getBySlug(int $machine_id, string $slug): ?object {
        global $wpdb;

        $sql = $wpdb->prepare(
            "SELECT * FROM {$this->getTableName()}
             WHERE machine_id = %d AND slug = %s
             LIMIT 1",
            $machine_id,
            $slug
        );

        return $wpdb->get_row($sql);
    }

    /**
     * Update sort order for multiple states
     *
     * @param array $sort_data Array of ['id' => sort_order] pairs
     * @return bool True on success
     */
    public function updateSortOrder(array $sort_data): bool {
        global $wpdb;

        $success = true;

        foreach ($sort_data as $state_id => $sort_order) {
            $result = $wpdb->update(
                $this->getTableName(),
                ['sort_order' => (int) $sort_order],
                ['id' => (int) $state_id],
                ['%d'],
                ['%d']
            );

            if ($result === false) {
                $success = false;
                error_log("Failed to update sort order for state {$state_id}");
            }

            // Invalidate cache for this state
            $this->invalidateCache((int) $state_id);
        }

        return $success;
    }

    /**
     * Count states for a machine
     *
     * @param int $machine_id State machine ID
     * @return int Number of states
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
     * Get total count of all states
     *
     * @return int Total number of states
     */
    public function getTotalCount(): int {
        global $wpdb;

        // Try to get from cache
        $cached_count = $this->cache->get('states_count', 'total');
        if ($cached_count !== null) {
            return (int) $cached_count;
        }

        $count = $wpdb->get_var("SELECT COUNT(*) FROM {$this->getTableName()}");

        // Cache the count
        $this->cache->set('states_count', $count, 300, 'total');

        return (int) $count;
    }

    /**
     * Delete all states for a machine
     * Used when deleting a machine
     *
     * @param int $machine_id State machine ID
     * @return bool True on success
     */
    public function deleteByMachine(int $machine_id): bool {
        global $wpdb;

        // Get all state IDs first for cache invalidation
        $state_ids = $wpdb->get_col($wpdb->prepare(
            "SELECT id FROM {$this->getTableName()} WHERE machine_id = %d",
            $machine_id
        ));

        // Delete all states
        $result = $wpdb->delete(
            $this->getTableName(),
            ['machine_id' => $machine_id],
            ['%d']
        );

        // Invalidate cache for each state
        foreach ($state_ids as $state_id) {
            $this->invalidateCache((int) $state_id);
        }

        // Clear machine-specific cache
        $this->cache->delete('states_by_machine', $machine_id);

        return $result !== false;
    }
}
