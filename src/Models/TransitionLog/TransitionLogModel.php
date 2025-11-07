<?php
/**
 * Transition Log Model
 *
 * @package     WP_State_Machine
 * @subpackage  Models/TransitionLog
 * @version     1.0.0
 * @author      arisciwek
 *
 * Path: /wp-state-machine/src/Models/TransitionLog/TransitionLogModel.php
 *
 * Description: Manages transition log data operations.
 *              Records history of all state transitions.
 *              Provides audit trail and tracking.
 *              Extends AbstractStateMachineModel for base functionality.
 *
 * Fields:
 * - id              : Primary key
 * - machine_id      : State machine ID
 * - entity_id       : Entity being transitioned (e.g., post_id, order_id)
 * - entity_type     : Entity type (e.g., "post", "order")
 * - from_state_id   : Source state (nullable for initial)
 * - to_state_id     : Destination state
 * - transition_id   : Transition used (nullable)
 * - user_id         : User who performed transition
 * - comment         : Optional comment/note
 * - metadata        : JSON additional data
 * - created_at      : Transition timestamp
 *
 * Usage Examples:
 *
 * Example 1: Log a transition
 * ```php
 * $log_model = new TransitionLogModel();
 * $log_id = $log_model->create([
 *     'machine_id' => 1,
 *     'entity_id' => 123,
 *     'entity_type' => 'post',
 *     'from_state_id' => 2,
 *     'to_state_id' => 3,
 *     'transition_id' => 5,
 *     'user_id' => get_current_user_id(),
 *     'comment' => 'Approved by manager',
 *     'metadata' => ['ip' => $_SERVER['REMOTE_ADDR']]
 * ]);
 * ```
 *
 * Example 2: Get entity history
 * ```php
 * $logs = $log_model->getEntityHistory('post', 123);
 * foreach ($logs as $log) {
 *     echo "{$log->created_at}: {$log->from_state_name} → {$log->to_state_name}";
 * }
 * ```
 *
 * Example 3: Get user activity
 * ```php
 * $user_logs = $log_model->getUserLogs($user_id, 10);
 * ```
 *
 * Changelog:
 * 1.0.0 - 2025-11-07
 * - Initial creation for Prioritas #5
 * - Extended AbstractStateMachineModel
 * - Entity history methods
 * - User activity methods
 * - Machine activity methods
 */

namespace WPStateMachine\Models\TransitionLog;

use WPStateMachine\Models\AbstractStateMachineModel;

defined('ABSPATH') || exit;

class TransitionLogModel extends AbstractStateMachineModel {

    /**
     * Table name (without prefix)
     *
     * @var string
     */
    protected $table = 'app_sm_transition_logs';

    /**
     * Primary key field
     *
     * @var string
     */
    protected $primary_key = 'id';

    /**
     * Cache group for this model
     *
     * @var string
     */
    protected $cache_group = 'transition_logs';

    /**
     * Constructor
     * Initialize parent and set table name
     */
    public function __construct() {
        parent::__construct();
        $this->setTableName($this->table);
    }

    // ========================================
    // CREATE METHODS
    // ========================================

    /**
     * Create a new transition log entry
     *
     * @param array $data Log data
     * @return int|false Log ID on success, false on failure
     */
    public function create(array $data) {
        global $wpdb;

        // Prepare data
        $prepared_data = $this->prepareLogData($data);

        // Validate required fields
        $validation_errors = $this->validateLogData($prepared_data);
        if (!empty($validation_errors)) {
            error_log('[TransitionLogModel] Validation errors: ' . print_r($validation_errors, true));
            return false;
        }

        // Insert into database
        $result = $wpdb->insert(
            $this->getTableName(),
            $prepared_data,
            [
                '%d', // machine_id
                '%d', // entity_id
                '%s', // entity_type
                '%d', // from_state_id (nullable handled by prepare)
                '%d', // to_state_id
                '%d', // transition_id (nullable handled by prepare)
                '%d', // user_id
                '%s', // comment
                '%s', // metadata
            ]
        );

        if ($result === false) {
            error_log('[TransitionLogModel] Insert failed: ' . $wpdb->last_error);
            return false;
        }

        $log_id = $wpdb->insert_id;

        // Invalidate related caches
        $this->invalidateCaches($prepared_data);

        // Fire action hook
        do_action('wp_state_machine_log_created', $log_id, $prepared_data);

        return $log_id;
    }

    // ========================================
    // READ METHODS
    // ========================================

    /**
     * Get entity transition history
     * Returns all transitions for a specific entity
     *
     * @param string $entity_type Entity type
     * @param int $entity_id Entity ID
     * @param int $limit Number of records to return (0 = all)
     * @return array Log entries with state names
     */
    public function getEntityHistory(string $entity_type, int $entity_id, int $limit = 0): array {
        global $wpdb;

        $cache_key = "entity_history_{$entity_type}_{$entity_id}_{$limit}";
        $cached = $this->getCached($cache_key);
        if ($cached !== false) {
            return $cached;
        }

        $log_table = $this->getTableName();
        $state_table = $wpdb->prefix . 'app_sm_states';
        $machine_table = $wpdb->prefix . 'app_sm_machines';
        $user_table = $wpdb->prefix . 'users';

        $sql = "SELECT l.*,
                       fs.name as from_state_name, fs.slug as from_state_slug, fs.color as from_state_color,
                       ts.name as to_state_name, ts.slug as to_state_slug, ts.color as to_state_color,
                       m.name as machine_name, m.slug as machine_slug,
                       u.display_name as user_name
                FROM {$log_table} l
                LEFT JOIN {$state_table} fs ON l.from_state_id = fs.id
                LEFT JOIN {$state_table} ts ON l.to_state_id = ts.id
                LEFT JOIN {$machine_table} m ON l.machine_id = m.id
                LEFT JOIN {$user_table} u ON l.user_id = u.ID
                WHERE l.entity_type = %s AND l.entity_id = %d
                ORDER BY l.created_at DESC";

        if ($limit > 0) {
            $sql .= $wpdb->prepare(" LIMIT %d", $limit);
        }

        $results = $wpdb->get_results($wpdb->prepare($sql, $entity_type, $entity_id));

        // Process metadata
        foreach ($results as $log) {
            if (!empty($log->metadata)) {
                $log->metadata = json_decode($log->metadata, true);
            }
        }

        $this->setCache($cache_key, $results);

        return $results;
    }

    /**
     * Get logs for a specific machine
     *
     * @param int $machine_id Machine ID
     * @param int $limit Number of records
     * @return array Log entries
     */
    public function getMachineLogs(int $machine_id, int $limit = 100): array {
        global $wpdb;

        $cache_key = "machine_logs_{$machine_id}_{$limit}";
        $cached = $this->getCached($cache_key);
        if ($cached !== false) {
            return $cached;
        }

        $log_table = $this->getTableName();
        $state_table = $wpdb->prefix . 'app_sm_states';

        $sql = "SELECT l.*,
                       fs.name as from_state_name,
                       ts.name as to_state_name
                FROM {$log_table} l
                LEFT JOIN {$state_table} fs ON l.from_state_id = fs.id
                LEFT JOIN {$state_table} ts ON l.to_state_id = ts.id
                WHERE l.machine_id = %d
                ORDER BY l.created_at DESC
                LIMIT %d";

        $results = $wpdb->get_results($wpdb->prepare($sql, $machine_id, $limit));

        $this->setCache($cache_key, $results);

        return $results;
    }

    /**
     * Get logs for a specific user
     *
     * @param int $user_id User ID
     * @param int $limit Number of records
     * @return array Log entries
     */
    public function getUserLogs(int $user_id, int $limit = 100): array {
        global $wpdb;

        $cache_key = "user_logs_{$user_id}_{$limit}";
        $cached = $this->getCached($cache_key);
        if ($cached !== false) {
            return $cached;
        }

        $log_table = $this->getTableName();
        $state_table = $wpdb->prefix . 'app_sm_states';
        $machine_table = $wpdb->prefix . 'app_sm_machines';

        $sql = "SELECT l.*,
                       fs.name as from_state_name,
                       ts.name as to_state_name,
                       m.name as machine_name
                FROM {$log_table} l
                LEFT JOIN {$state_table} fs ON l.from_state_id = fs.id
                LEFT JOIN {$state_table} ts ON l.to_state_id = ts.id
                LEFT JOIN {$machine_table} m ON l.machine_id = m.id
                WHERE l.user_id = %d
                ORDER BY l.created_at DESC
                LIMIT %d";

        $results = $wpdb->get_results($wpdb->prepare($sql, $user_id, $limit));

        $this->setCache($cache_key, $results);

        return $results;
    }

    /**
     * Get current state for entity
     * Returns the most recent transition log
     *
     * @param string $entity_type Entity type
     * @param int $entity_id Entity ID
     * @return object|null Log entry or null
     */
    public function getCurrentState(string $entity_type, int $entity_id): ?object {
        $history = $this->getEntityHistory($entity_type, $entity_id, 1);
        return !empty($history) ? $history[0] : null;
    }

    /**
     * Get statistics for a machine
     *
     * @param int $machine_id Machine ID
     * @return array Statistics
     */
    public function getMachineStats(int $machine_id): array {
        global $wpdb;

        $cache_key = "machine_stats_{$machine_id}";
        $cached = $this->getCached($cache_key);
        if ($cached !== false) {
            return $cached;
        }

        $log_table = $this->getTableName();

        $stats = [
            'total_transitions' => 0,
            'unique_entities' => 0,
            'transitions_by_state' => [],
            'transitions_by_user' => [],
        ];

        // Total transitions
        $stats['total_transitions'] = (int) $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$log_table} WHERE machine_id = %d",
            $machine_id
        ));

        // Unique entities
        $stats['unique_entities'] = (int) $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(DISTINCT CONCAT(entity_type, '-', entity_id)) FROM {$log_table} WHERE machine_id = %d",
            $machine_id
        ));

        $this->setCache($cache_key, $stats);

        return $stats;
    }

    // ========================================
    // HELPER METHODS
    // ========================================

    /**
     * Prepare log data for insertion
     *
     * @param array $data Raw data
     * @return array Prepared data
     */
    private function prepareLogData(array $data): array {
        $prepared = [];

        // Required fields
        $prepared['machine_id'] = isset($data['machine_id']) ? intval($data['machine_id']) : 0;
        $prepared['entity_id'] = isset($data['entity_id']) ? intval($data['entity_id']) : 0;
        $prepared['entity_type'] = isset($data['entity_type']) ? sanitize_text_field($data['entity_type']) : '';
        $prepared['to_state_id'] = isset($data['to_state_id']) ? intval($data['to_state_id']) : 0;
        $prepared['user_id'] = isset($data['user_id']) ? intval($data['user_id']) : get_current_user_id();

        // Optional fields
        $prepared['from_state_id'] = isset($data['from_state_id']) ? intval($data['from_state_id']) : null;
        $prepared['transition_id'] = isset($data['transition_id']) ? intval($data['transition_id']) : null;
        $prepared['comment'] = isset($data['comment']) ? sanitize_textarea_field($data['comment']) : null;

        // Metadata (encode as JSON)
        if (isset($data['metadata'])) {
            if (is_array($data['metadata'])) {
                $prepared['metadata'] = wp_json_encode($data['metadata']);
            } elseif (is_string($data['metadata'])) {
                $prepared['metadata'] = $data['metadata'];
            }
        } else {
            $prepared['metadata'] = null;
        }

        return $prepared;
    }

    /**
     * Validate log data
     *
     * @param array $data Prepared data
     * @return array Validation errors
     */
    private function validateLogData(array $data): array {
        $errors = [];

        if (empty($data['machine_id'])) {
            $errors[] = 'machine_id is required';
        }

        if (empty($data['entity_id'])) {
            $errors[] = 'entity_id is required';
        }

        if (empty($data['entity_type'])) {
            $errors[] = 'entity_type is required';
        }

        if (empty($data['to_state_id'])) {
            $errors[] = 'to_state_id is required';
        }

        if (empty($data['user_id'])) {
            $errors[] = 'user_id is required';
        }

        return $errors;
    }

    /**
     * Invalidate related caches
     *
     * @param array $data Log data
     * @return void
     */
    private function invalidateCaches(array $data): void {
        // Invalidate entity history cache
        if (!empty($data['entity_type']) && !empty($data['entity_id'])) {
            $pattern = "entity_history_{$data['entity_type']}_{$data['entity_id']}_*";
            $this->deleteCachePattern($pattern);
        }

        // Invalidate machine logs cache
        if (!empty($data['machine_id'])) {
            $pattern = "machine_logs_{$data['machine_id']}_*";
            $this->deleteCachePattern($pattern);
            $this->deleteCache("machine_stats_{$data['machine_id']}");
        }

        // Invalidate user logs cache
        if (!empty($data['user_id'])) {
            $pattern = "user_logs_{$data['user_id']}_*";
            $this->deleteCachePattern($pattern);
        }
    }

    /**
     * Delete cache entries matching pattern
     *
     * @param string $pattern Cache key pattern with wildcard
     * @return void
     */
    private function deleteCachePattern(string $pattern): void {
        // WordPress doesn't support wildcard cache deletion natively
        // This is a simplified version - in production you might use Redis/Memcached patterns
        // For now, we'll just clear specific known variations
        $limits = [0, 10, 20, 50, 100];
        foreach ($limits as $limit) {
            $key = str_replace('*', $limit, $pattern);
            $this->deleteCache($key);
        }
    }
}
