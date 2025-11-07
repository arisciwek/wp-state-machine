<?php
/**
 * Transition Log Model
 *
 * @package     WP_State_Machine
 * @subpackage  Models/TransitionLog
 * @version     1.0.1
 * @author      arisciwek
 *
 * Path: /wp-state-machine/src/Models/TransitionLog/TransitionLogModel.php
 *
 * Description: Manages transition log data operations.
 *              Records history of all state transitions.
 *              Provides audit trail and tracking.
 *              Extends AbstractStateMachineModel for base functionality.
 *              Supports per-plugin tables for scalability.
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
 * Per-Plugin Tables:
 * Supports isolated log tables per plugin to prevent performance issues
 * with millions of records in single table.
 *
 * Usage Examples:
 *
 * Example 1: Central table (backward compatible)
 * ```php
 * $log_model = new TransitionLogModel();
 * // Uses: app_sm_transition_logs (central)
 * ```
 *
 * Example 2: Per-plugin table (recommended for high-volume)
 * ```php
 * $log_model = new TransitionLogModel('wp-rfq');
 * // Uses: app_wp_rfq_sm_logs (isolated)
 * // Table created automatically if not exists
 * ```
 *
 * Example 3: Log a transition
 * ```php
 * $log_model = new TransitionLogModel('wp-rfq');
 * $log_id = $log_model->create([
 *     'machine_id' => 1,
 *     'entity_id' => 123,
 *     'entity_type' => 'order',
 *     'from_state_id' => 2,
 *     'to_state_id' => 3,
 *     'transition_id' => 5,
 *     'user_id' => get_current_user_id(),
 *     'comment' => 'Approved by manager',
 *     'metadata' => ['ip' => $_SERVER['REMOTE_ADDR']]
 * ]);
 * ```
 *
 * Example 4: Get entity history
 * ```php
 * $logs = $log_model->getEntityHistory('order', 123);
 * foreach ($logs as $log) {
 *     echo "{$log->created_at}: {$log->from_state_name} → {$log->to_state_name}";
 * }
 * ```
 *
 * Changelog:
 * 1.0.1 - 2025-11-07 (TODO-6104)
 * - Added per-plugin table support
 * - Dynamic table name resolution
 * - Auto table creation for plugin-specific logs
 * - Dynamic cache group per plugin
 * - Backward compatible with central table
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
     * Plugin slug for isolated table
     * null = use central table (backward compatible)
     *
     * @var string|null
     */
    protected $plugin_slug;

    /**
     * Table name (without prefix)
     * Will be set dynamically based on plugin_slug
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
     * Will be set dynamically based on plugin_slug
     *
     * @var string
     */
    protected $cache_group = 'transition_logs';

    /**
     * Constructor
     * Initialize parent and set table name (dynamic per plugin)
     *
     * @param string|null $plugin_slug Plugin slug for per-plugin table, null for central
     */
    public function __construct($plugin_slug = null) {
        parent::__construct();

        $this->plugin_slug = $plugin_slug;
        $this->table = $this->resolveTableName($plugin_slug);
        $this->setTableName($this->table);

        // Dynamic cache group per plugin
        $this->cache_group = $plugin_slug
            ? "{$plugin_slug}_transition_logs"
            : 'transition_logs';

        // Create plugin table if needed (per-plugin tables only)
        if ($plugin_slug) {
            $this->maybeCreatePluginTable();
        }
    }

    /**
     * Resolve table name based on plugin slug
     *
     * @param string|null $plugin_slug Plugin slug
     * @return string Full table name with prefix
     */
    protected function resolveTableName($plugin_slug): string {
        global $wpdb;

        if ($plugin_slug) {
            // Per-plugin table: app_wp_rfq_sm_logs
            return $wpdb->prefix . "app_{$plugin_slug}_sm_logs";
        }

        // Central table (backward compatible)
        return $wpdb->prefix . 'app_sm_transition_logs';
    }

    /**
     * Create plugin-specific table if not exists
     *
     * @return void
     */
    private function maybeCreatePluginTable() {
        global $wpdb;
        $table_name = $this->getTableName();

        // Check if table exists
        $table_exists = $wpdb->get_var(
            $wpdb->prepare("SHOW TABLES LIKE %s", $table_name)
        );

        if (!$table_exists) {
            $this->createPluginTable();
        }
    }

    /**
     * Create plugin-specific logs table
     * Uses same schema as central table
     *
     * @return void
     */
    private function createPluginTable() {
        global $wpdb;
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');

        $table_name = $this->getTableName();
        $charset_collate = $wpdb->get_charset_collate();

        // Same schema as TransitionLogsDB but with dynamic table name
        $sql = "CREATE TABLE {$table_name} (
            id bigint(20) UNSIGNED NOT NULL auto_increment,
            machine_id bigint(20) UNSIGNED NOT NULL,
            entity_id bigint(20) UNSIGNED NOT NULL,
            entity_type varchar(50) NOT NULL,
            from_state_id bigint(20) UNSIGNED NULL,
            to_state_id bigint(20) UNSIGNED NOT NULL,
            transition_id bigint(20) UNSIGNED NULL,
            user_id bigint(20) UNSIGNED NOT NULL,
            comment text NULL,
            metadata text NULL,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY  (id),
            KEY machine_id_index (machine_id),
            KEY entity_index (entity_type, entity_id),
            KEY from_state_index (from_state_id),
            KEY to_state_index (to_state_id),
            KEY user_id_index (user_id),
            KEY created_at_index (created_at)
        ) $charset_collate;";

        dbDelta($sql);

        // Add foreign keys
        $this->addForeignKeys($table_name);

        // Log table creation
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log("[TransitionLogModel] Created plugin table: {$table_name}");
        }
    }

    /**
     * Add foreign key constraints to plugin table
     *
     * @param string $table_name Table name
     * @return void
     */
    private function addForeignKeys($table_name) {
        global $wpdb;
        $machines_table = $wpdb->prefix . 'app_sm_machines';
        $states_table = $wpdb->prefix . 'app_sm_states';
        $transitions_table = $wpdb->prefix . 'app_sm_transitions';

        // Use plugin_slug for unique constraint names
        $slug = $this->plugin_slug ? $this->plugin_slug : 'central';
        $slug = str_replace('-', '_', $slug); // Replace hyphens with underscores for SQL

        $constraints = [
            [
                'name' => "fk_{$slug}_logs_machine",
                'sql' => "ALTER TABLE {$table_name}
                         ADD CONSTRAINT fk_{$slug}_logs_machine
                         FOREIGN KEY (machine_id)
                         REFERENCES {$machines_table}(id)
                         ON DELETE CASCADE"
            ],
            [
                'name' => "fk_{$slug}_logs_from_state",
                'sql' => "ALTER TABLE {$table_name}
                         ADD CONSTRAINT fk_{$slug}_logs_from_state
                         FOREIGN KEY (from_state_id)
                         REFERENCES {$states_table}(id)
                         ON DELETE SET NULL"
            ],
            [
                'name' => "fk_{$slug}_logs_to_state",
                'sql' => "ALTER TABLE {$table_name}
                         ADD CONSTRAINT fk_{$slug}_logs_to_state
                         FOREIGN KEY (to_state_id)
                         REFERENCES {$states_table}(id)
                         ON DELETE CASCADE"
            ],
            [
                'name' => "fk_{$slug}_logs_transition",
                'sql' => "ALTER TABLE {$table_name}
                         ADD CONSTRAINT fk_{$slug}_logs_transition
                         FOREIGN KEY (transition_id)
                         REFERENCES {$transitions_table}(id)
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

            if ($constraint_exists > 0) {
                continue; // Skip if exists
            }

            // Add foreign key constraint
            $result = $wpdb->query($constraint['sql']);
            if ($result === false && defined('WP_DEBUG') && WP_DEBUG) {
                error_log("[TransitionLogModel] Failed to add FK {$constraint['name']}: " . $wpdb->last_error);
            }
        }
    }

    /**
     * Get plugin slug
     *
     * @return string|null Plugin slug or null for central table
     */
    public function getPluginSlug(): ?string {
        return $this->plugin_slug;
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
