<?php
/**
 * Workflow Seeder Class
 *
 * @package     WP_State_Machine
 * @subpackage  Data
 * @version     1.0.0
 * @author      arisciwek
 *
 * Path: /wp-state-machine/src/Data/WorkflowSeeder.php
 *
 * Description: Seed default workflows from YML files into database.
 *              Handles workflow groups, state machines, states, and transitions.
 *              Supports "Reset to Default" functionality.
 *
 * Dependencies:
 * - YmlParser: Parse YML workflow files
 * - Model Classes: WorkflowGroupModel, StateMachineModel, StateModel, TransitionModel
 * - WordPress $wpdb: Transaction support
 *
 * Features:
 * - Atomic transactions (all or nothing)
 * - Backup before reset (optional)
 * - State slug to ID mapping
 * - Foreign key relationship handling
 * - Error reporting and logging
 *
 * Usage:
 * ```php
 * // Seed from specific YML file
 * $seeder = new WorkflowSeeder();
 * $result = $seeder->seedFromFile('/path/to/workflow.yml');
 *
 * // Seed all default workflows
 * $result = $seeder->seedAllDefaults();
 *
 * // Reset to defaults (delete existing + reseed)
 * $result = $seeder->resetToDefaults();
 * ```
 *
 * Changelog:
 * 1.0.0 - 2025-11-08
 * - Initial creation
 * - YML file seeding with transactions
 * - Reset to default functionality
 * - State mapping and FK handling
 */

namespace WPStateMachine\Data;

use WPStateMachine\Models\WorkflowGroup\WorkflowGroupModel;
use WPStateMachine\Models\StateMachine\StateMachineModel;
use WPStateMachine\Models\State\StateModel;
use WPStateMachine\Models\Transition\TransitionModel;

defined('ABSPATH') || exit;

class WorkflowSeeder {
    /**
     * Workflow Group Model
     *
     * @var WorkflowGroupModel
     */
    private $workflow_group_model;

    /**
     * State Machine Model
     *
     * @var StateMachineModel
     */
    private $state_machine_model;

    /**
     * State Model
     *
     * @var StateModel
     */
    private $state_model;

    /**
     * Transition Model
     *
     * @var TransitionModel
     */
    private $transition_model;

    /**
     * Constructor
     */
    public function __construct() {
        $this->workflow_group_model = new WorkflowGroupModel();
        $this->state_machine_model = new StateMachineModel();
        $this->state_model = new StateModel();
        $this->transition_model = new TransitionModel();
    }

    /**
     * Seed workflow from YML file
     *
     * @param string $file_path Path to YML file
     * @return array Result with success status and message
     */
    public function seedFromFile(string $file_path): array {
        global $wpdb;

        try {
            // Parse YML file
            $data = YmlParser::parseFile($file_path);

            // Start transaction
            $wpdb->query('START TRANSACTION');

            // 1. Create or get workflow group
            $group_id = $this->seedWorkflowGroup($data['workflow_group']);
            if (!$group_id) {
                throw new \Exception('Failed to create workflow group');
            }

            // 2. Create state machine
            $data['state_machine']['workflow_group_id'] = $group_id;
            $machine_id = $this->seedStateMachine($data['state_machine']);
            if (!$machine_id) {
                throw new \Exception('Failed to create state machine');
            }

            // 3. Create states and build slug->ID map
            $state_map = $this->seedStates($machine_id, $data['states']);
            if (empty($state_map)) {
                throw new \Exception('Failed to create states');
            }

            // 4. Create transitions using state map
            $transitions_created = $this->seedTransitions($machine_id, $data['transitions'], $state_map);
            if (!$transitions_created) {
                throw new \Exception('Failed to create transitions');
            }

            // Commit transaction
            $wpdb->query('COMMIT');

            return [
                'success' => true,
                'message' => sprintf(
                    'Successfully seeded workflow: %s (Group: %s, Machine: %s, States: %d, Transitions: %d)',
                    $data['state_machine']['name'],
                    $data['workflow_group']['name'],
                    $machine_id,
                    count($state_map),
                    $transitions_created
                ),
                'data' => [
                    'group_id' => $group_id,
                    'machine_id' => $machine_id,
                    'states_count' => count($state_map),
                    'transitions_count' => $transitions_created,
                ],
            ];

        } catch (\Exception $e) {
            $wpdb->query('ROLLBACK');

            $this->log('Error seeding workflow: ' . $e->getMessage());

            return [
                'success' => false,
                'message' => 'Failed to seed workflow: ' . $e->getMessage(),
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Seed all default YML files
     *
     * @return array Result with success status and details
     */
    public function seedAllDefaults(): array {
        $files = YmlParser::getDefaultFiles();

        if (empty($files)) {
            return [
                'success' => false,
                'message' => 'No YML files found in defaults directory',
            ];
        }

        $results = [];
        $success_count = 0;
        $error_count = 0;

        foreach ($files as $file) {
            $result = $this->seedFromFile($file);

            if ($result['success']) {
                $success_count++;
            } else {
                $error_count++;
            }

            $results[] = [
                'file' => basename($file),
                'result' => $result,
            ];
        }

        return [
            'success' => $error_count === 0,
            'message' => sprintf(
                'Seeded %d workflows (%d succeeded, %d failed)',
                count($files),
                $success_count,
                $error_count
            ),
            'results' => $results,
            'summary' => [
                'total' => count($files),
                'success' => $success_count,
                'errors' => $error_count,
            ],
        ];
    }

    /**
     * Reset to defaults: Delete all default workflows and re-seed
     * REQUIRES Development Mode to be enabled
     *
     * @param bool $create_backup Whether to create backup before reset
     * @return array Result with success status and message
     */
    public function resetToDefaults(bool $create_backup = false): array {
        global $wpdb;

        try {
            // Check if development mode is enabled
            $settings = get_option('wp_state_machine_settings', []);
            $dev_mode = isset($settings['enable_development']) && $settings['enable_development'];

            if (!$dev_mode) {
                throw new \Exception('Development Mode must be enabled to reset workflows. Enable it in Settings > Database > Development Settings.');
            }

            // Optional: Create backup
            if ($create_backup) {
                $backup_result = $this->createBackup();
                if (!$backup_result['success']) {
                    throw new \Exception('Backup failed: ' . $backup_result['message']);
                }
            }

            // Start transaction
            $wpdb->query('START TRANSACTION');

            // Delete all default workflows (is_default = 1)
            $deleted_count = $this->deleteDefaultWorkflows();
            $this->log("Deleted {$deleted_count} default workflows in transaction");

            // Commit deletion
            $wpdb->query('COMMIT');
            $this->log("Transaction committed - deletion complete");

            // Re-seed all defaults (uses separate transactions per file)
            $seed_result = $this->seedAllDefaults();

            if (!$seed_result['success']) {
                throw new \Exception('Re-seeding failed: ' . $seed_result['message']);
            }

            return [
                'success' => true,
                'message' => 'Successfully reset to default workflows',
                'seed_result' => $seed_result,
            ];

        } catch (\Exception $e) {
            $wpdb->query('ROLLBACK');

            $this->log('Error resetting to defaults: ' . $e->getMessage());

            return [
                'success' => false,
                'message' => 'Failed to reset to defaults: ' . $e->getMessage(),
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Seed workflow group
     *
     * @param array $group_data Workflow group data
     * @return int|null Group ID or null on failure
     */
    private function seedWorkflowGroup(array $group_data): ?int {
        global $wpdb;

        // Check if group already exists by slug
        $table = $wpdb->prefix . 'app_sm_workflow_groups';
        $existing = $wpdb->get_var($wpdb->prepare(
            "SELECT id FROM {$table} WHERE slug = %s",
            $group_data['slug']
        ));

        if ($existing) {
            $this->log("Workflow group '{$group_data['slug']}' already exists (ID: {$existing})");
            return (int) $existing;
        }

        // Create new group
        $group_id = $this->workflow_group_model->create($group_data);

        if ($group_id) {
            $this->log("Created workflow group: {$group_data['name']} (ID: {$group_id})");
        }

        return $group_id;
    }

    /**
     * Seed state machine
     *
     * @param array $machine_data State machine data
     * @return int|null Machine ID or null on failure
     */
    private function seedStateMachine(array $machine_data): ?int {
        global $wpdb;

        // Check if machine already exists by slug
        $table = $wpdb->prefix . 'app_sm_machines';
        $existing = $wpdb->get_row($wpdb->prepare(
            "SELECT id, is_default FROM {$table} WHERE slug = %s",
            $machine_data['slug']
        ));

        if ($existing) {
            // If existing machine is a default (from previous seed), delete and recreate
            if ($existing->is_default == 1) {
                $this->log("State machine '{$machine_data['slug']}' already exists as default (ID: {$existing->id}). Deleting to recreate with latest YML data.");

                // Delete existing states and transitions first (child to parent order)
                $states_table = $wpdb->prefix . 'app_sm_states';
                $transitions_table = $wpdb->prefix . 'app_sm_transitions';
                $logs_table = $wpdb->prefix . 'app_sm_transition_logs';

                $wpdb->query($wpdb->prepare("DELETE FROM {$logs_table} WHERE machine_id = %d", $existing->id));
                $wpdb->query($wpdb->prepare("DELETE FROM {$transitions_table} WHERE machine_id = %d", $existing->id));
                $wpdb->query($wpdb->prepare("DELETE FROM {$states_table} WHERE machine_id = %d", $existing->id));
                $wpdb->query($wpdb->prepare("DELETE FROM {$table} WHERE id = %d", $existing->id));

                $this->log("Deleted existing default machine ID: {$existing->id} and its relations");
                // Continue to create new machine below
            } else {
                // If existing machine is custom (user-created), throw error
                throw new \Exception("State machine '{$machine_data['slug']}' already exists as custom workflow (ID: {$existing->id}). Cannot overwrite custom workflows.");
            }
        }

        // Create new machine
        $machine_id = $this->state_machine_model->create($machine_data);

        if ($machine_id) {
            $this->log("Created state machine: {$machine_data['name']} (ID: {$machine_id})");
        }

        return $machine_id;
    }

    /**
     * Seed states and build slug->ID map
     *
     * @param int $machine_id State machine ID
     * @param array $states_data States data
     * @return array State slug to ID mapping
     */
    private function seedStates(int $machine_id, array $states_data): array {
        $state_map = [];

        foreach ($states_data as $state) {
            $state['machine_id'] = $machine_id;

            $state_id = $this->state_model->create($state);

            if (!$state_id) {
                throw new \Exception("Failed to create state: {$state['name']}");
            }

            $state_map[$state['slug']] = $state_id;

            $this->log("Created state: {$state['name']} (ID: {$state_id})");
        }

        return $state_map;
    }

    /**
     * Seed transitions using state map
     *
     * @param int $machine_id State machine ID
     * @param array $transitions_data Transitions data
     * @param array $state_map State slug to ID mapping
     * @return int Number of transitions created
     */
    private function seedTransitions(int $machine_id, array $transitions_data, array $state_map): int {
        $count = 0;

        foreach ($transitions_data as $transition) {
            // Map state slugs to IDs
            if (!isset($state_map[$transition['from_state']])) {
                throw new \Exception("From state '{$transition['from_state']}' not found in state map");
            }

            if (!isset($state_map[$transition['to_state']])) {
                throw new \Exception("To state '{$transition['to_state']}' not found in state map");
            }

            $transition_data = [
                'machine_id' => $machine_id,
                'from_state_id' => $state_map[$transition['from_state']],
                'to_state_id' => $state_map[$transition['to_state']],
                'label' => $transition['name'],
                'metadata' => json_encode([
                    'conditions' => $transition['conditions'] ?? [],
                    'actions' => $transition['actions'] ?? [],
                    'description' => $transition['description'] ?? '',
                ]),
            ];

            $transition_id = $this->transition_model->create($transition_data);

            if (!$transition_id) {
                throw new \Exception("Failed to create transition: {$transition['name']}");
            }

            $count++;
            $this->log("Created transition: {$transition['name']} (ID: {$transition_id})");
        }

        return $count;
    }

    /**
     * Delete all default workflows (is_default = 1)
     *
     * @return int Number of machines deleted
     */
    private function deleteDefaultWorkflows(): int {
        global $wpdb;

        // Get all default machines
        $machines_table = $wpdb->prefix . 'app_sm_machines';
        $machine_ids = $wpdb->get_col("SELECT id FROM {$machines_table} WHERE is_default = 1");

        if (empty($machine_ids)) {
            $this->log('No default workflows to delete');
            return 0;
        }

        $count = count($machine_ids);
        $machine_ids_str = implode(',', array_map('intval', $machine_ids));

        $this->log("Found {$count} default workflows to delete: " . $machine_ids_str);

        // Delete in correct order (child to parent)

        // 1. Delete transition logs
        $logs_table = $wpdb->prefix . 'app_sm_transition_logs';
        $logs_deleted = $wpdb->query("DELETE FROM {$logs_table} WHERE machine_id IN ({$machine_ids_str})");
        $this->log("Deleted {$logs_deleted} transition logs");

        // 2. Delete transitions
        $transitions_table = $wpdb->prefix . 'app_sm_transitions';
        $transitions_deleted = $wpdb->query("DELETE FROM {$transitions_table} WHERE machine_id IN ({$machine_ids_str})");
        $this->log("Deleted {$transitions_deleted} transitions");

        // 3. Delete states
        $states_table = $wpdb->prefix . 'app_sm_states';
        $states_deleted = $wpdb->query("DELETE FROM {$states_table} WHERE machine_id IN ({$machine_ids_str})");
        $this->log("Deleted {$states_deleted} states");

        // 4. Delete machines
        $machines_deleted = $wpdb->query("DELETE FROM {$machines_table} WHERE id IN ({$machine_ids_str})");
        $this->log("Deleted {$machines_deleted} machines");

        // Verify deletion
        $remaining = $wpdb->get_var("SELECT COUNT(*) FROM {$machines_table} WHERE id IN ({$machine_ids_str})");
        if ($remaining > 0) {
            $this->log("WARNING: {$remaining} machines still exist after deletion!");
        }

        // Note: We don't delete workflow groups as they might be shared

        $this->log("Completed deletion of {$count} default workflows");

        return $count;
    }

    /**
     * Create backup of current data
     *
     * @return array Result with success status
     */
    private function createBackup(): array {
        try {
            $backup_dir = WP_STATE_MACHINE_PATH . 'src/Data/backups';

            // Create directory if it doesn't exist
            if (!is_dir($backup_dir)) {
                if (!wp_mkdir_p($backup_dir)) {
                    throw new \Exception("Failed to create backup directory: {$backup_dir}");
                }
            }

            // Check if directory is writable
            if (!is_writable($backup_dir)) {
                throw new \Exception("Backup directory is not writable: {$backup_dir}");
            }

            $timestamp = current_time('Y-m-d_H-i-s');
            $backup_file = $backup_dir . "/backup_{$timestamp}.json";

            global $wpdb;

            $backup_data = [
                'timestamp' => current_time('mysql'),
                'workflow_groups' => $wpdb->get_results("SELECT * FROM {$wpdb->prefix}app_sm_workflow_groups", ARRAY_A),
                'machines' => $wpdb->get_results("SELECT * FROM {$wpdb->prefix}app_sm_machines", ARRAY_A),
                'states' => $wpdb->get_results("SELECT * FROM {$wpdb->prefix}app_sm_states", ARRAY_A),
                'transitions' => $wpdb->get_results("SELECT * FROM {$wpdb->prefix}app_sm_transitions", ARRAY_A),
            ];

            $json = wp_json_encode($backup_data, JSON_PRETTY_PRINT);

            if ($json === false) {
                throw new \Exception('Failed to encode backup data to JSON');
            }

            $bytes_written = file_put_contents($backup_file, $json);

            if ($bytes_written === false) {
                throw new \Exception("Failed to write backup file: {$backup_file}");
            }

            $this->log("Backup created successfully: {$backup_file} ({$bytes_written} bytes)");

            return [
                'success' => true,
                'message' => 'Backup created successfully',
                'backup_file' => $backup_file,
                'size_bytes' => $bytes_written,
            ];

        } catch (\Exception $e) {
            $this->log('Backup creation failed: ' . $e->getMessage());

            return [
                'success' => false,
                'message' => $e->getMessage(),
            ];
        }
    }

    /**
     * Log message
     *
     * @param string $message Log message
     * @return void
     */
    private function log(string $message): void {
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log("[WorkflowSeeder] {$message}");
        }
    }
}
