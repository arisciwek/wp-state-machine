<?php
/**
 * Workflow Seeder Controller
 *
 * @package     WP_State_Machine
 * @subpackage  Controllers
 * @version     1.0.0
 * @author      arisciwek
 *
 * Path: /wp-state-machine/src/Controllers/WorkflowSeederController.php
 *
 * Description: Handle AJAX requests for seeding and resetting workflows.
 *              Provides endpoints for:
 *              - Seeding default workflows from YML files
 *              - Resetting to default workflows
 *              - Getting seeder status
 *
 * AJAX Endpoints:
 * - wp_ajax_seed_default_workflows
 * - wp_ajax_reset_to_default_workflows
 * - wp_ajax_get_seeder_status
 * - wp_ajax_get_workflows_data
 * - wp_ajax_seed_individual_workflow
 * - wp_ajax_reset_individual_workflow
 *
 * Dependencies:
 * - WorkflowSeeder: Core seeding functionality
 * - WordPress AJAX API
 *
 * Changelog:
 * 1.0.0 - 2025-11-08
 * - Initial creation
 * - AJAX handlers for seed and reset
 * - Security checks and error handling
 */

namespace WPStateMachine\Controllers;

use WPStateMachine\Data\WorkflowSeeder;
use WPStateMachine\Data\YmlParser;

defined('ABSPATH') || exit;

class WorkflowSeederController {
    /**
     * Workflow Seeder instance
     *
     * @var WorkflowSeeder
     */
    private $seeder;

    /**
     * Constructor
     */
    public function __construct() {
        $this->seeder = new WorkflowSeeder();

        // Register AJAX handlers
        $this->registerAjaxHandlers();
    }

    /**
     * Register AJAX handlers
     *
     * @return void
     */
    private function registerAjaxHandlers() {
        add_action('wp_ajax_seed_default_workflows', [$this, 'seedDefaultWorkflows']);
        add_action('wp_ajax_reset_to_default_workflows', [$this, 'resetToDefaultWorkflows']);
        add_action('wp_ajax_get_seeder_status', [$this, 'getSeederStatus']);
        add_action('wp_ajax_get_workflows_data', [$this, 'getWorkflowsData']);
        add_action('wp_ajax_seed_individual_workflow', [$this, 'seedIndividualWorkflow']);
        add_action('wp_ajax_reset_individual_workflow', [$this, 'resetIndividualWorkflow']);
    }

    /**
     * Seed default workflows from YML files
     * AJAX Handler
     *
     * @return void
     */
    public function seedDefaultWorkflows() {
        // Clean output buffer
        if (ob_get_length()) {
            ob_clean();
        }

        try {
            // Verify nonce
            if (!check_ajax_referer('wp_state_machine_nonce', 'nonce', false)) {
                throw new \Exception(__('Security check failed', 'wp-state-machine'));
            }

            // Check permissions
            if (!current_user_can('manage_state_machines')) {
                throw new \Exception(__('Insufficient permissions', 'wp-state-machine'));
            }

            // Seed all default workflows
            $result = $this->seeder->seedAllDefaults();

            if ($result['success']) {
                wp_send_json_success([
                    'message' => $result['message'],
                    'details' => $result,
                ]);
            } else {
                throw new \Exception($result['message']);
            }

        } catch (\Exception $e) {
            wp_send_json_error([
                'message' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Reset to default workflows
     * AJAX Handler
     *
     * @return void
     */
    public function resetToDefaultWorkflows() {
        // Clean output buffer
        if (ob_get_length()) {
            ob_clean();
        }

        try {
            // Verify nonce
            if (!check_ajax_referer('wp_state_machine_nonce', 'nonce', false)) {
                throw new \Exception(__('Security check failed', 'wp-state-machine'));
            }

            // Check permissions
            if (!current_user_can('manage_state_machines')) {
                throw new \Exception(__('Insufficient permissions', 'wp-state-machine'));
            }

            // Get create_backup parameter (default: true)
            $create_backup = isset($_POST['create_backup']) ? (bool) $_POST['create_backup'] : true;

            // Reset to defaults
            $result = $this->seeder->resetToDefaults($create_backup);

            if ($result['success']) {
                wp_send_json_success([
                    'message' => $result['message'],
                    'details' => $result,
                ]);
            } else {
                throw new \Exception($result['message']);
            }

        } catch (\Exception $e) {
            wp_send_json_error([
                'message' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Get seeder status
     * AJAX Handler
     *
     * @return void
     */
    public function getSeederStatus() {
        // Clean output buffer
        if (ob_get_length()) {
            ob_clean();
        }

        try {
            // Verify nonce
            if (!check_ajax_referer('wp_state_machine_nonce', 'nonce', false)) {
                throw new \Exception(__('Security check failed', 'wp-state-machine'));
            }

            // Check permissions
            if (!current_user_can('view_state_machines')) {
                throw new \Exception(__('Insufficient permissions', 'wp-state-machine'));
            }

            // Get available YML files
            $yml_files = YmlParser::getDefaultFiles();

            // Get database counts
            global $wpdb;
            $machines_table = $wpdb->prefix . 'app_sm_machines';

            $default_count = $wpdb->get_var("SELECT COUNT(*) FROM {$machines_table} WHERE is_default = 1");
            $custom_count = $wpdb->get_var("SELECT COUNT(*) FROM {$machines_table} WHERE is_default = 0");
            $total_count = $wpdb->get_var("SELECT COUNT(*) FROM {$machines_table}");

            // Check development mode
            $settings = get_option('wp_state_machine_settings', []);
            $dev_mode = isset($settings['enable_development']) && $settings['enable_development'];

            wp_send_json_success([
                'yml_files_count' => count($yml_files),
                'yml_files' => array_map('basename', $yml_files),
                'database' => [
                    'default_workflows' => (int) $default_count,
                    'custom_workflows' => (int) $custom_count,
                    'total_workflows' => (int) $total_count,
                ],
                'can_seed' => count($yml_files) > 0,
                'has_defaults' => $default_count > 0,
                'development_mode' => $dev_mode,
                'can_reset' => $dev_mode && $default_count > 0,
            ]);

        } catch (\Exception $e) {
            wp_send_json_error([
                'message' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Get workflows data with status
     * AJAX Handler
     *
     * @return void
     */
    public function getWorkflowsData() {
        // Clean output buffer
        if (ob_get_length()) {
            ob_clean();
        }

        try {
            // Verify nonce
            if (!check_ajax_referer('wp_state_machine_nonce', 'nonce', false)) {
                throw new \Exception(__('Security check failed', 'wp-state-machine'));
            }

            // Check permissions
            if (!current_user_can('view_state_machines')) {
                throw new \Exception(__('Insufficient permissions', 'wp-state-machine'));
            }

            // Get available YML files
            $yml_files = YmlParser::getDefaultFiles();

            global $wpdb;
            $machines_table = $wpdb->prefix . 'app_sm_machines';
            $states_table = $wpdb->prefix . 'app_sm_states';
            $transitions_table = $wpdb->prefix . 'app_sm_transitions';

            $workflows = [];

            foreach ($yml_files as $file_path) {
                $filename = basename($file_path);

                // Parse YML to get workflow details
                $workflow_data = YmlParser::parseFile($file_path);

                if (!$workflow_data || !isset($workflow_data['state_machine'])) {
                    continue;
                }

                $state_machine = $workflow_data['state_machine'];

                if (!isset($state_machine['name']) || !isset($state_machine['slug'])) {
                    continue;
                }

                // Check if workflow is seeded in database
                $machine = $wpdb->get_row($wpdb->prepare(
                    "SELECT id, name, slug, is_default FROM {$machines_table} WHERE slug = %s",
                    $state_machine['slug']
                ));

                $is_seeded = $machine !== null;
                $states_count = 0;
                $transitions_count = 0;

                if ($is_seeded) {
                    $states_count = $wpdb->get_var($wpdb->prepare(
                        "SELECT COUNT(*) FROM {$states_table} WHERE machine_id = %d",
                        $machine->id
                    ));
                    $transitions_count = $wpdb->get_var($wpdb->prepare(
                        "SELECT COUNT(*) FROM {$transitions_table} WHERE machine_id = %d",
                        $machine->id
                    ));
                }

                $workflows[] = [
                    'filename' => $filename,
                    'name' => $state_machine['name'],
                    'slug' => $state_machine['slug'],
                    'description' => $state_machine['description'] ?? '',
                    'is_seeded' => $is_seeded,
                    'is_default' => $is_seeded ? (bool) $machine->is_default : false,
                    'states_count' => (int) $states_count,
                    'transitions_count' => (int) $transitions_count,
                ];
            }

            // Get development mode from database (authoritative source)
            $settings = get_option('wp_state_machine_settings', []);
            $dev_mode = !empty($settings['enable_development']);

            wp_send_json_success([
                'workflows' => $workflows,
                'total_count' => count($workflows),
                'development_mode' => $dev_mode,  // Send to frontend for proper button rendering
            ]);

        } catch (\Exception $e) {
            wp_send_json_error([
                'message' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Seed individual workflow
     * AJAX Handler
     *
     * @return void
     */
    public function seedIndividualWorkflow() {
        // Clean output buffer
        if (ob_get_length()) {
            ob_clean();
        }

        try {
            // Verify nonce
            if (!check_ajax_referer('wp_state_machine_nonce', 'nonce', false)) {
                throw new \Exception(__('Security check failed', 'wp-state-machine'));
            }

            // Check permissions
            if (!current_user_can('manage_state_machines')) {
                throw new \Exception(__('Insufficient permissions', 'wp-state-machine'));
            }

            // Get filename parameter
            $filename = isset($_POST['filename']) ? sanitize_file_name($_POST['filename']) : '';

            if (empty($filename)) {
                throw new \Exception(__('Filename is required', 'wp-state-machine'));
            }

            // Construct file path
            $file_path = WP_STATE_MACHINE_PATH . 'src/Data/defaults/' . $filename;

            if (!file_exists($file_path)) {
                throw new \Exception(__('Workflow file not found', 'wp-state-machine'));
            }

            // Seed the workflow
            $result = $this->seeder->seedFromFile($file_path);

            if ($result['success']) {
                wp_send_json_success([
                    'message' => $result['message'],
                    'details' => $result,
                ]);
            } else {
                throw new \Exception($result['message']);
            }

        } catch (\Exception $e) {
            wp_send_json_error([
                'message' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Reset individual workflow
     * AJAX Handler
     *
     * @return void
     */
    public function resetIndividualWorkflow() {
        // Clean output buffer
        if (ob_get_length()) {
            ob_clean();
        }

        try {
            // Verify nonce
            if (!check_ajax_referer('wp_state_machine_nonce', 'nonce', false)) {
                throw new \Exception(__('Security check failed', 'wp-state-machine'));
            }

            // Check permissions
            if (!current_user_can('manage_state_machines')) {
                throw new \Exception(__('Insufficient permissions', 'wp-state-machine'));
            }

            // Check development mode (must be saved in database)
            $settings = get_option('wp_state_machine_settings', []);
            $dev_mode = !empty($settings['enable_development']);

            if (!$dev_mode) {
                throw new \Exception(__('Development Mode must be enabled and saved in Settings before you can reset workflows.', 'wp-state-machine'));
            }

            // Get slug parameter
            $slug = isset($_POST['slug']) ? sanitize_text_field($_POST['slug']) : '';

            if (empty($slug)) {
                throw new \Exception(__('Workflow slug is required', 'wp-state-machine'));
            }

            global $wpdb;
            $machines_table = $wpdb->prefix . 'app_sm_machines';
            $states_table = $wpdb->prefix . 'app_sm_states';
            $transitions_table = $wpdb->prefix . 'app_sm_transitions';
            $logs_table = $wpdb->prefix . 'app_sm_transition_logs';

            // Find workflow by slug
            $machine = $wpdb->get_row($wpdb->prepare(
                "SELECT id, slug, is_default FROM {$machines_table} WHERE slug = %s",
                $slug
            ));

            if (!$machine) {
                throw new \Exception(__('Workflow not found', 'wp-state-machine'));
            }

            if (!$machine->is_default) {
                throw new \Exception(__('Cannot reset custom workflow. Only default workflows can be reset.', 'wp-state-machine'));
            }

            // Find corresponding YML file
            $yml_files = YmlParser::getDefaultFiles();
            $file_path = null;

            foreach ($yml_files as $file) {
                $workflow_data = YmlParser::parseFile($file);
                if ($workflow_data &&
                    isset($workflow_data['state_machine']) &&
                    isset($workflow_data['state_machine']['slug']) &&
                    $workflow_data['state_machine']['slug'] === $slug) {
                    $file_path = $file;
                    break;
                }
            }

            if (!$file_path) {
                throw new \Exception(__('YML file not found for this workflow', 'wp-state-machine'));
            }

            // Start transaction
            $wpdb->query('START TRANSACTION');

            try {
                // Delete workflow and related data (child to parent order)
                $wpdb->query($wpdb->prepare("DELETE FROM {$logs_table} WHERE machine_id = %d", $machine->id));
                $wpdb->query($wpdb->prepare("DELETE FROM {$transitions_table} WHERE machine_id = %d", $machine->id));
                $wpdb->query($wpdb->prepare("DELETE FROM {$states_table} WHERE machine_id = %d", $machine->id));
                $wpdb->query($wpdb->prepare("DELETE FROM {$machines_table} WHERE id = %d", $machine->id));

                // Re-seed from YML
                $result = $this->seeder->seedFromFile($file_path);

                if (!$result['success']) {
                    throw new \Exception($result['message']);
                }

                // Commit transaction
                $wpdb->query('COMMIT');

                wp_send_json_success([
                    'message' => __('Workflow reset and re-seeded successfully', 'wp-state-machine'),
                    'details' => $result,
                ]);

            } catch (\Exception $e) {
                $wpdb->query('ROLLBACK');
                throw $e;
            }

        } catch (\Exception $e) {
            wp_send_json_error([
                'message' => $e->getMessage(),
            ]);
        }
    }
}
