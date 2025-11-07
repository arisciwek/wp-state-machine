<?php
/**
 * Abstract State Machine Model
 *
 * Base class for all state machine entity models.
 * Provides shared CRUD implementation for state machines, states, transitions, etc.
 *
 * @package     WP_State_Machine
 * @subpackage  Models
 * @version     1.0.0
 * @author      arisciwek
 *
 * Path: /wp-state-machine/src/Models/AbstractStateMachineModel.php
 *
 * Description: Abstract base class for state machine entity models.
 *              Eliminates code duplication by providing common CRUD operations.
 *              Designed as STANDALONE - does NOT depend on wp-app-core.
 *
 * Philosophy:
 * - wp-state-machine is a library plugin (like wp-qb)
 * - Can be used independently without wp-app-core
 * - Provides its own base abstraction for state machine entities
 *
 * Architecture Note:
 * - Plugins MAY use multiple abstract bases for different purposes
 * - AbstractCrudModel (wp-app-core) for general entities
 * - AbstractStateMachineModel (wp-state-machine) for state machine entities
 * - This is NORMAL separation of concerns, NOT duplication
 *
 * Usage:
 * ```php
 * class StateMachineModel extends AbstractStateMachineModel {
 *     public function __construct() {
 *         parent::__construct(StateMachineCache::getInstance());
 *     }
 *
 *     protected function getTableName(): string {
 *         global $wpdb;
 *         return $wpdb->prefix . 'app_sm_machines';
 *     }
 *
 *     protected function getEntityName(): string {
 *         return 'state_machine';
 *     }
 *
 *     // ... implement other abstract methods
 *
 *     // ✅ find() - inherited FREE!
 *     // ✅ create() - inherited FREE with hooks!
 *     // ✅ update() - inherited FREE!
 *     // ✅ delete() - inherited FREE!
 * }
 * ```
 *
 * Benefits:
 * - 60%+ code reduction in child models
 * - Consistent hook patterns
 * - Standardized cache management
 * - Single source of truth for CRUD
 *
 * Changelog:
 * 1.0.0 - 2025-11-07
 * - Initial creation as standalone base class
 * - CRUD operations: find(), create(), update(), delete()
 * - Hook system integration
 * - Cache management
 * - Format array building
 * - Independent from wp-app-core
 */

namespace WPStateMachine\Models;

defined('ABSPATH') || exit;

abstract class AbstractStateMachineModel {

    /**
     * Cache handler instance
     *
     * @var object Cache handler for this entity
     */
    protected $cache;

    /**
     * Constructor
     *
     * @param object $cache_handler Cache handler instance
     */
    public function __construct($cache_handler) {
        $this->cache = $cache_handler;
    }

    // ========================================
    // ABSTRACT METHODS (Must be implemented)
    // ========================================

    /**
     * Get database table name
     *
     * @return string Full table name with prefix
     *
     * @example
     * ```php
     * protected function getTableName(): string {
     *     global $wpdb;
     *     return $wpdb->prefix . 'app_sm_machines';
     * }
     * ```
     */
    abstract protected function getTableName(): string;

    /**
     * Get cache method prefix
     *
     * @return string Cache method prefix (e.g., 'StateMachine', 'State')
     *
     * @example
     * ```php
     * protected function getCacheKey(): string {
     *     return 'StateMachine';  // getStateMachine(), setStateMachine()
     * }
     * ```
     */
    abstract protected function getCacheKey(): string;

    /**
     * Get entity name (singular, lowercase)
     *
     * Used for hook names.
     *
     * @return string Entity name (e.g., 'state_machine', 'state', 'transition')
     *
     * @example
     * ```php
     * protected function getEntityName(): string {
     *     return 'state_machine';
     * }
     * ```
     */
    abstract protected function getEntityName(): string;

    /**
     * Get allowed fields for updates
     *
     * @return array Field names that can be updated
     *
     * @example
     * ```php
     * protected function getAllowedFields(): array {
     *     return ['name', 'slug', 'description', 'is_active'];
     * }
     * ```
     */
    abstract protected function getAllowedFields(): array;

    /**
     * Prepare insert data
     *
     * @param array $data Raw request data
     * @return array Prepared insert data
     *
     * @example
     * ```php
     * protected function prepareInsertData(array $data): array {
     *     return [
     *         'name' => $data['name'],
     *         'slug' => $data['slug'],
     *         'description' => $data['description'] ?? '',
     *         'created_at' => current_time('mysql')
     *     ];
     * }
     * ```
     */
    abstract protected function prepareInsertData(array $data): array;

    /**
     * Get format map for wpdb
     *
     * @return array Field => format map (%d for int, %s for string)
     *
     * @example
     * ```php
     * protected function getFormatMap(): array {
     *     return [
     *         'id' => '%d',
     *         'name' => '%s',
     *         'slug' => '%s',
     *         'description' => '%s',
     *         'is_active' => '%d',
     *         'created_at' => '%s'
     *     ];
     * }
     * ```
     */
    abstract protected function getFormatMap(): array;

    // ========================================
    // CONCRETE METHODS (Shared implementation)
    // ========================================

    /**
     * Find entity by ID with caching
     *
     * @param int $id Entity ID
     * @return object|null Entity object or null
     */
    public function find(int $id): ?object {
        // 1. Check cache (support both dynamic methods and get/set pattern)
        $cache_key = $this->getCacheKey();
        $cached = null;

        // Try dynamic method first (e.g., getStateMachine($id))
        $get_method = 'get' . $cache_key;
        if (method_exists($this->cache, $get_method)) {
            $cached = $this->cache->$get_method($id);
        }
        // Fallback to get($type, $key) pattern
        elseif (method_exists($this->cache, 'get')) {
            $cache_type = strtolower(preg_replace('/(?<!^)[A-Z]/', '_$0', $cache_key));
            $cached = $this->cache->get($cache_type, $id);
        }

        if ($cached !== null) {
            return $cached;
        }

        // 2. Query database
        global $wpdb;
        $table = $this->getTableName();

        $result = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$table} WHERE id = %d",
            $id
        ));

        // 3. Cache result (support both patterns)
        if ($result) {
            $set_method = 'set' . $cache_key;
            if (method_exists($this->cache, $set_method)) {
                $this->cache->$set_method($id, $result);
            }
            elseif (method_exists($this->cache, 'set')) {
                $cache_type = strtolower(preg_replace('/(?<!^)[A-Z]/', '_$0', $cache_key));
                $this->cache->set($cache_type, $result, 120, $id);
            }
        }

        return $result;
    }

    /**
     * Create new entity with hooks
     *
     * Fires hooks:
     * - wp_state_machine_{entity}_before_insert
     * - wp_state_machine_{entity}_created
     *
     * @param array $data Request data
     * @return int|null New entity ID or null on failure
     */
    public function create(array $data): ?int {
        global $wpdb;

        try {
            // 1. Prepare insert data
            $insert_data = $this->prepareInsertData($data);

            // 2. Hook: Before insert
            $entity = $this->getEntityName();
            $insert_data = apply_filters(
                "wp_state_machine_{$entity}_before_insert",
                $insert_data,
                $data
            );

            // 3. Handle static ID injection (for demo/migration)
            $static_id = null;
            if (isset($insert_data['id']) && !isset($data['id'])) {
                $static_id = $insert_data['id'];
            }

            // 4. Build format array
            $format = $this->buildFormatArray($insert_data);

            // 5. Insert to database
            $result = $wpdb->insert(
                $this->getTableName(),
                $insert_data,
                $format
            );

            if ($result === false) {
                throw new \Exception($wpdb->last_error);
            }

            // 6. Get insert ID
            $new_id = $static_id ?? $wpdb->insert_id;

            // 7. Invalidate cache
            $this->invalidateCache($new_id);

            // 8. Hook: After create
            do_action(
                "wp_state_machine_{$entity}_created",
                $new_id,
                $insert_data
            );

            return $new_id;

        } catch (\Exception $e) {
            error_log("AbstractStateMachineModel::create() error: " . $e->getMessage());
            return null;
        }
    }

    /**
     * Update entity by ID
     *
     * @param int $id Entity ID
     * @param array $data Update data
     * @return bool True on success
     */
    public function update(int $id, array $data): bool {
        global $wpdb;

        try {
            // 1. Verify exists
            $current = $this->find($id);
            if (!$current) {
                return false;
            }

            // 2. Prepare update data (allowed fields only)
            $update_data = [];
            foreach ($this->getAllowedFields() as $field) {
                if (isset($data[$field])) {
                    $update_data[$field] = $data[$field];
                }
            }

            if (empty($update_data)) {
                return false;
            }

            // 3. Build format array
            $format = $this->buildFormatArray($update_data);

            // 4. Update database
            $result = $wpdb->update(
                $this->getTableName(),
                $update_data,
                ['id' => $id],
                $format,
                ['%d']
            );

            // 5. Invalidate cache
            $this->invalidateCache($id);

            // 6. Hook: After update
            $entity = $this->getEntityName();
            do_action(
                "wp_state_machine_{$entity}_updated",
                $id,
                $update_data,
                $current
            );

            return $result !== false;

        } catch (\Exception $e) {
            error_log("AbstractStateMachineModel::update() error: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Delete entity by ID
     *
     * @param int $id Entity ID
     * @return bool True on success
     */
    public function delete(int $id): bool {
        global $wpdb;

        try {
            // 1. Verify exists
            $current = $this->find($id);
            if (!$current) {
                return false;
            }

            // 2. Hook: Before delete
            $entity = $this->getEntityName();
            do_action("wp_state_machine_{$entity}_before_delete", $id, $current);

            // 3. Delete from database
            $result = $wpdb->delete(
                $this->getTableName(),
                ['id' => $id],
                ['%d']
            );

            // 4. Invalidate cache
            $this->invalidateCache($id);

            // 5. Hook: After delete
            do_action("wp_state_machine_{$entity}_deleted", $id, $current);

            return $result !== false;

        } catch (\Exception $e) {
            error_log("AbstractStateMachineModel::delete() error: " . $e->getMessage());
            return false;
        }
    }

    // ========================================
    // UTILITY METHODS
    // ========================================

    /**
     * Build format array for wpdb
     *
     * @param array $data Data to format
     * @return array Format array
     */
    protected function buildFormatArray(array $data): array {
        $format = [];
        $format_map = $this->getFormatMap();

        foreach (array_keys($data) as $key) {
            $format[] = $format_map[$key] ?? '%s';
        }

        return $format;
    }

    /**
     * Invalidate entity cache
     *
     * @param int $id Entity ID
     * @param mixed ...$additional_keys Additional cache keys
     */
    protected function invalidateCache(int $id, ...$additional_keys): void {
        $cache_key = $this->getCacheKey();

        // Try dynamic invalidate method first
        $invalidate_method = 'invalidate' . $cache_key . 'Cache';
        if (method_exists($this->cache, $invalidate_method)) {
            $this->cache->$invalidate_method($id, ...$additional_keys);
            return;
        }

        // Fallback to delete($type, $key) pattern
        if (method_exists($this->cache, 'delete')) {
            $cache_type = strtolower(preg_replace('/(?<!^)[A-Z]/', '_$0', $cache_key));
            $this->cache->delete($cache_type, $id);

            // Delete additional cache keys if provided
            foreach ($additional_keys as $key) {
                if (is_string($key)) {
                    $this->cache->delete($key);
                }
            }
        }
    }
}
