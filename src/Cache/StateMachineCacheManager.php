<?php
/**
 * State Machine Cache Manager Class
 *
 * @package     WP_State_Machine
 * @subpackage  Cache
 * @version     1.0.1
 * @author      arisciwek
 *
 * Path: /wp-state-machine/src/Cache/StateMachineCacheManager.php
 *
 * Description: Manages caching for state machine data.
 *              Follows wp-agency AgencyCacheManager pattern.
 *              Uses WordPress Object Cache API.
 *
 * Cache Groups:
 * - wp_state_machine: Main group for all cache
 *
 * Cache Keys:
 * - state_machine_{id}: Single state machine data
 * - state_machines_list: List of state machines
 * - state_machines_count: Count statistics
 * - workflow_group_{id}: Single workflow group data
 * - workflow_groups_list: List of workflow groups
 * - state_{id}: Single state data
 * - states_list: List of states
 * - transition_{id}: Single transition data
 * - transitions_list: List of transitions
 *
 * Dependencies:
 * - WordPress Object Cache API
 *
 * Changelog:
 * 1.0.1 - 2025-11-08
 * - Fixed DataTable cache invalidation using index tracking
 * - Added DataTable cache key index for reliable deletion
 * - Fixed prefix pattern for DataTable cache (type_datatable instead of datatable_type)
 * - Improved deleteByPrefix to use index instead of iterating cache group
 *
 * 1.0.0 - 2025-11-07
 * - Initial creation
 * - State machine caching support
 * - Workflow group caching
 * - States and transitions caching
 * - Follow wp-agency pattern exactly
 */

namespace WPStateMachine\Cache;

defined('ABSPATH') || exit;

class StateMachineCacheManager {
    /**
     * Cache group name
     */
    private const CACHE_GROUP = 'wp_state_machine';

    /**
     * Default cache expiry in seconds (1 hour)
     */
    private const CACHE_EXPIRY = 1 * HOUR_IN_SECONDS;

    /**
     * Cache keys for state machines
     */
    private const KEY_STATE_MACHINE = 'state_machine';
    private const KEY_STATE_MACHINES_LIST = 'state_machines_list';
    private const KEY_STATE_MACHINES_COUNT = 'state_machines_count';

    /**
     * Cache keys for workflow groups
     */
    private const KEY_WORKFLOW_GROUP = 'workflow_group';
    private const KEY_WORKFLOW_GROUPS_LIST = 'workflow_groups_list';
    private const KEY_WORKFLOW_GROUPS_COUNT = 'workflow_groups_count';

    /**
     * Cache keys for states
     */
    private const KEY_STATE = 'state';
    private const KEY_STATES_LIST = 'states_list';
    private const KEY_STATES_COUNT = 'states_count';

    /**
     * Cache keys for transitions
     */
    private const KEY_TRANSITION = 'transition';
    private const KEY_TRANSITIONS_LIST = 'transitions_list';
    private const KEY_TRANSITIONS_COUNT = 'transitions_count';

    /**
     * Cache keys for logs
     */
    private const KEY_LOGS_LIST = 'logs_list';
    private const KEY_LOGS_COUNT = 'logs_count';

    /**
     * Get cache group name
     *
     * @return string Cache group name
     */
    public static function getCacheGroup(): string {
        return self::CACHE_GROUP;
    }

    /**
     * Get default cache expiry
     *
     * @return int Cache expiry in seconds
     */
    public static function getCacheExpiry(): int {
        return self::CACHE_EXPIRY;
    }

    /**
     * Get cache key constant by type
     *
     * @param string $type Cache key type
     * @return string Cache key constant
     */
    public static function getCacheKey(string $type): string {
        $constants = [
            'state_machine' => self::KEY_STATE_MACHINE,
            'state_machines_list' => self::KEY_STATE_MACHINES_LIST,
            'state_machines_count' => self::KEY_STATE_MACHINES_COUNT,
            'workflow_group' => self::KEY_WORKFLOW_GROUP,
            'workflow_groups_list' => self::KEY_WORKFLOW_GROUPS_LIST,
            'workflow_groups_count' => self::KEY_WORKFLOW_GROUPS_COUNT,
            'state' => self::KEY_STATE,
            'states_list' => self::KEY_STATES_LIST,
            'states_count' => self::KEY_STATES_COUNT,
            'transition' => self::KEY_TRANSITION,
            'transitions_list' => self::KEY_TRANSITIONS_LIST,
            'transitions_count' => self::KEY_TRANSITIONS_COUNT,
            'logs_list' => self::KEY_LOGS_LIST,
            'logs_count' => self::KEY_LOGS_COUNT,
        ];

        return $constants[$type] ?? '';
    }

    /**
     * Generate valid cache key from components
     *
     * @param string ...$components Key components
     * @return string Generated cache key
     */
    private function generateKey(string ...$components): string {
        // Filter out empty components
        $validComponents = array_filter($components, function($component) {
            return !empty($component) && is_string($component);
        });

        if (empty($validComponents)) {
            // Generate default key from components hash
            return 'default_' . md5(serialize($components));
        }

        // Join with underscore
        $key = implode('_', $validComponents);

        // WordPress has a key length limit of 172 characters
        if (strlen($key) > 172) {
            $key = substr($key, 0, 140) . '_' . md5($key);
        }

        return $key;
    }

    /**
     * Get value from cache
     *
     * @param string $type Cache type
     * @param mixed ...$keyComponents Additional key components
     * @return mixed|null Cached value or null if not found
     */
    public function get(string $type, ...$keyComponents) {
        $key = $this->generateKey($type, ...$keyComponents);

        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log("Cache attempt - Key: {$key}, Type: {$type}");
        }

        $result = wp_cache_get($key, self::CACHE_GROUP);

        if ($result === false) {
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log("Cache miss - Key: {$key}");
            }
            return null;
        }

        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log("Cache hit - Key: {$key}");
        }

        return $result;
    }

    /**
     * Set value in cache
     *
     * @param string $type Cache type
     * @param mixed $value Value to cache
     * @param int|null $expiry Expiry time in seconds
     * @param mixed ...$keyComponents Additional key components
     * @return bool True on success, false on failure
     */
    public function set(string $type, $value, int $expiry = null, ...$keyComponents): bool {
        try {
            $key = $this->generateKey($type, ...$keyComponents);

            if ($expiry === null) {
                $expiry = self::CACHE_EXPIRY;
            }

            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log("Setting cache - Key: {$key}, Type: {$type}, Expiry: {$expiry}s");
            }

            $result = wp_cache_set($key, $value, self::CACHE_GROUP, $expiry);

            // Track DataTable cache keys for easier invalidation
            if ($result && strpos($key, '_datatable_') !== false) {
                $this->addToDataTableIndex($type, $key, $expiry);
            }

            return $result;
        } catch (\Exception $e) {
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log("Cache set failed: " . $e->getMessage());
            }
            return false;
        }
    }

    /**
     * Add DataTable cache key to index for tracking
     *
     * @param string $type Cache type
     * @param string $key Cache key
     * @param int $expiry Expiry time
     * @return void
     */
    private function addToDataTableIndex(string $type, string $key, int $expiry): void {
        $index_key = $type . '_datatable_index';
        $index = wp_cache_get($index_key, self::CACHE_GROUP);

        if (!is_array($index)) {
            $index = [];
        }

        $index[$key] = time() + $expiry;

        // Set index with same expiry as the longest cached item
        wp_cache_set($index_key, $index, self::CACHE_GROUP, $expiry);

        $this->debugLog(sprintf('Added key %s to DataTable index %s', $key, $index_key));
    }

    /**
     * Delete value from cache
     *
     * @param string $type Cache type
     * @param mixed ...$keyComponents Additional key components
     * @return bool True on success, false on failure
     */
    public function delete(string $type, ...$keyComponents): bool {
        $key = $this->generateKey($type, ...$keyComponents);

        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log("Deleting cache - Key: {$key}, Type: {$type}");
        }

        return wp_cache_delete($key, self::CACHE_GROUP);
    }

    /**
     * Check if key exists in cache
     *
     * @param string $type Cache type
     * @param mixed ...$keyComponents Additional key components
     * @return bool True if exists, false otherwise
     */
    public function exists(string $type, ...$keyComponents): bool {
        $key = $this->generateKey($type, ...$keyComponents);
        return wp_cache_get($key, self::CACHE_GROUP) !== false;
    }

    /**
     * Invalidate state machine cache
     * Clears all cache related to a specific state machine
     *
     * @param int $machine_id State machine ID
     * @return void
     */
    public function invalidateStateMachineCache(int $machine_id): void {
        $this->delete('state_machine', $machine_id);
        $this->delete('state_machines_list');
        $this->delete('state_machines_count');
        $this->delete('states_list', $machine_id);
        $this->delete('transitions_list', $machine_id);

        // Clear DataTable cache for state machines
        $this->invalidateDataTableCache('state_machines_list');
    }

    /**
     * Invalidate workflow group cache
     * Clears all cache related to a specific workflow group
     *
     * @param int $group_id Workflow group ID
     * @return void
     */
    public function invalidateWorkflowGroupCache(int $group_id): void {
        $this->delete('workflow_group', $group_id);
        $this->delete('workflow_groups_list');
        $this->delete('workflow_groups_count');

        // Clear DataTable cache for workflow groups
        $this->invalidateDataTableCache('workflow_groups_list');
    }

    /**
     * Invalidate DataTable cache for a specific context
     *
     * @param string $context Cache context
     * @param array|null $filters Optional filters
     * @return bool True on success, false on failure
     */
    public function invalidateDataTableCache(string $context, ?array $filters = null): bool {
        try {
            if (empty($context)) {
                $this->debugLog('Invalid context in invalidateDataTableCache');
                return false;
            }

            $this->debugLog(sprintf(
                'Attempting to invalidate DataTable cache - Context: %s, Filters: %s',
                $context,
                $filters ? json_encode($filters) : 'none'
            ));

            // Use prefix-based deletion to clear all related DataTable caches
            // Cache keys are built as: {context}_datatable_{params}
            $prefix = $context . '_datatable';
            $result = $this->deleteByPrefix($prefix);

            $this->debugLog(sprintf(
                'Invalidated all DataTable cache entries for context %s. Result: %s',
                $context,
                $result ? 'success' : 'failed'
            ));

            return $result;

        } catch (\Exception $e) {
            $this->debugLog('Error in invalidateDataTableCache: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Delete all cache keys with a specific prefix using index tracking
     *
     * @param string $prefix Key prefix (format: {type}_datatable)
     * @return bool True on success, false on failure
     */
    private function deleteByPrefix(string $prefix): bool {
        $deleted = 0;

        // Extract type from prefix (remove '_datatable' suffix)
        $type = str_replace('_datatable', '', $prefix);
        $index_key = $type . '_datatable_index';

        // Get index of all DataTable cache keys
        $index = wp_cache_get($index_key, self::CACHE_GROUP);

        if (!is_array($index) || empty($index)) {
            $this->debugLog(sprintf('No DataTable cache index found for type: %s', $type));
            return true;
        }

        $this->debugLog(sprintf('Found %d keys in DataTable index for type: %s', count($index), $type));

        // Delete all tracked keys
        foreach (array_keys($index) as $key) {
            $result = wp_cache_delete($key, self::CACHE_GROUP);
            if ($result) {
                $deleted++;
                $this->debugLog(sprintf('Deleted DataTable cache key: %s', $key));
            }
        }

        // Delete the index itself
        wp_cache_delete($index_key, self::CACHE_GROUP);
        $this->debugLog(sprintf('Deleted DataTable index: %s', $index_key));

        $this->debugLog(sprintf('Total deleted: %d DataTable cache keys for prefix: %s', $deleted, $prefix));
        return true;
    }

    /**
     * Clear all caches in group
     *
     * @return bool True on success, false on failure
     */
    public function clearAll(): bool {
        try {
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log('Attempting to clear all caches in group: ' . self::CACHE_GROUP);
            }

            $result = $this->clearCache();

            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log('Cache clear result: ' . ($result ? 'success' : 'failed'));
            }

            return $result;
        } catch (\Exception $e) {
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log('Error in clearAll(): ' . $e->getMessage());
            }
            return false;
        }
    }

    /**
     * Clear all cache entries
     *
     * @return bool True on success, false on failure
     */
    private function clearCache(): bool {
        try {
            global $wp_object_cache;

            // Check if using default WordPress object cache
            if (isset($wp_object_cache->cache[self::CACHE_GROUP])) {
                if (is_array($wp_object_cache->cache[self::CACHE_GROUP])) {
                    foreach (array_keys($wp_object_cache->cache[self::CACHE_GROUP]) as $key) {
                        wp_cache_delete($key, self::CACHE_GROUP);
                    }
                }
                unset($wp_object_cache->cache[self::CACHE_GROUP]);
                return true;
            }

            // Alternative approach for external cache plugins
            if (function_exists('wp_cache_flush_group')) {
                return wp_cache_flush_group(self::CACHE_GROUP);
            }

            // Fallback method - iteratively clear known cache keys
            $known_types = [
                'state_machine',
                'state_machines_list',
                'workflow_group',
                'workflow_groups_list',
                'state',
                'states_list',
                'transition',
                'transitions_list',
                'logs_list',
                'datatable'
            ];

            foreach ($known_types as $type) {
                if ($cached_keys = wp_cache_get($type . '_keys', self::CACHE_GROUP)) {
                    if (is_array($cached_keys)) {
                        foreach ($cached_keys as $key) {
                            wp_cache_delete($key, self::CACHE_GROUP);
                        }
                    }
                }
            }

            // Clear master key list
            wp_cache_delete('cache_keys', self::CACHE_GROUP);

            return true;

        } catch (\Exception $e) {
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log('Error clearing cache: ' . $e->getMessage());
            }
            return false;
        }
    }

    /**
     * Dump all cache keys for debugging
     *
     * @return string Debug output
     */
    public function dumpCacheKeys() {
        global $wp_object_cache;

        if (!defined('WP_DEBUG') || !WP_DEBUG) {
            return 'Debug mode not enabled';
        }

        $output = "Cache keys in " . self::CACHE_GROUP . ":\n";

        if (isset($wp_object_cache->cache[self::CACHE_GROUP])) {
            $keys = array_keys($wp_object_cache->cache[self::CACHE_GROUP]);
            foreach ($keys as $key) {
                $output .= "- $key\n";
            }
            $output .= "Total: " . count($keys) . " keys";
        } else {
            $output .= "No keys found or cache group not accessible";
        }

        error_log($output);
        return $output;
    }

    /**
     * Debug logging helper
     *
     * @param string $message Log message
     * @param mixed $data Optional data to log
     * @return void
     */
    private function debugLog(string $message, $data = null): void {
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log(sprintf(
                '[StateMachineCacheManager] %s %s',
                $message,
                $data ? '| Data: ' . print_r($data, true) : ''
            ));
        }
    }
}
