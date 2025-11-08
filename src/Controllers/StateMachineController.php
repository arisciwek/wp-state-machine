<?php
/**
 * State Machine Controller Class
 *
 * @package     WP_State_Machine
 * @subpackage  Controllers
 * @version     1.0.0
 * @author      arisciwek
 *
 * Path: /wp-state-machine/src/Controllers/StateMachineController.php
 *
 * Description: Handles all CRUD operations for state machines.
 *              Follows wp-agency AgencyController pattern.
 *              Manages DataTables integration, AJAX handlers, and validation.
 *
 * Dependencies:
 * - StateMachineModel: Database operations
 * - StateMachineValidator: Form and permission validation
 * - StateMachineCacheManager: Caching layer
 *
 * Changelog:
 * 1.0.0 - 2025-11-07
 * - Initial creation
 * - AJAX handlers for DataTables and CRUD
 * - Permission validation integration
 * - Cache management
 * - Follow wp-agency pattern exactly
 */

namespace WPStateMachine\Controllers;

use WPStateMachine\Models\StateMachine\StateMachineModel;
use WPStateMachine\Validators\StateMachineValidator;
use WPStateMachine\Cache\StateMachineCacheManager;

defined('ABSPATH') || exit;

class StateMachineController {
    /**
     * State Machine Model instance
     *
     * @var StateMachineModel
     */
    private $model;

    /**
     * State Machine Validator instance
     *
     * @var StateMachineValidator
     */
    private $validator;

    /**
     * Cache Manager instance
     *
     * @var StateMachineCacheManager
     */
    private $cache;

    /**
     * Constructor
     * Initializes model, validator, and cache manager
     * Registers AJAX handlers
     */
    public function __construct() {
        $this->model = new StateMachineModel();
        $this->validator = new StateMachineValidator();
        $this->cache = new StateMachineCacheManager();

        // Register AJAX handlers
        add_action('wp_ajax_handle_state_machine_datatable', [$this, 'handleDataTableRequest']);
        add_action('wp_ajax_create_state_machine', [$this, 'store']);
        add_action('wp_ajax_update_state_machine', [$this, 'update']);
        add_action('wp_ajax_delete_state_machine', [$this, 'delete']);
        add_action('wp_ajax_show_state_machine', [$this, 'show']);
    }

    /**
     * Render main state machine admin page
     *
     * @return void
     */
    public function renderMainPage() {
        // Check permission
        if (!current_user_can('view_state_machines')) {
            wp_die(__('You do not have permission to access this page.', 'wp-state-machine'));
        }

        // Load view
        $view_path = WP_STATE_MACHINE_PATH . 'src/Views/admin/state-machines/machines-view.php';
        if (file_exists($view_path)) {
            include $view_path;
        } else {
            echo '<div class="wrap">';
            echo '<h1>' . esc_html__('State Machines', 'wp-state-machine') . '</h1>';
            echo '<p>' . esc_html__('State machine management interface.', 'wp-state-machine') . '</p>';
            echo '</div>';
        }
    }

    /**
     * Handle DataTable AJAX request
     * Supports server-side processing with pagination, search, and sorting
     *
     * @return void
     */
    public function handleDataTableRequest() {
        // Verify nonce
        check_ajax_referer('wp_state_machine_nonce', 'nonce');

        // Check permission
        if (!current_user_can('view_state_machines')) {
            wp_send_json_error([
                'message' => __('Permission denied', 'wp-state-machine')
            ]);
        }

        try {
            // Get DataTable parameters
            $draw = isset($_POST['draw']) ? intval($_POST['draw']) : 1;
            $start = isset($_POST['start']) ? intval($_POST['start']) : 0;
            $length = isset($_POST['length']) ? intval($_POST['length']) : 10;
            $search = isset($_POST['search']['value']) ? sanitize_text_field($_POST['search']['value']) : '';
            $order_column_index = isset($_POST['order'][0]['column']) ? intval($_POST['order'][0]['column']) : 0;
            $order_dir = isset($_POST['order'][0]['dir']) ? sanitize_text_field($_POST['order'][0]['dir']) : 'DESC';

            // Map column index to database column
            $columns = ['id', 'name', 'slug', 'plugin_slug', 'entity_type', 'created_at'];
            $order_column = isset($columns[$order_column_index]) ? $columns[$order_column_index] : 'id';

            // DISABLE CACHE FOR NOW - causing stuck spinner issues
            // Build cache key for DataTable response
            // $cache_key = sprintf(
            //     'datatable_%d_%d_%s_%s_%s_%s',
            //     $start,
            //     $length,
            //     $search,
            //     $order_column,
            //     $order_dir,
            //     $workflow_group_id ?? 'all'
            // );

            // Try to get from cache
            // $cached_result = $this->cache->get('state_machines_list', $cache_key);
            // if ($cached_result !== null) {
            //     wp_send_json($cached_result);
            //     return;
            // }

            // Get workflow group filter
            $workflow_group_id = isset($_POST['workflow_group_id']) && $_POST['workflow_group_id'] !== ''
                ? intval($_POST['workflow_group_id'])
                : null;

            // Get data from model
            $params = [
                'start' => $start,
                'length' => $length,
                'search' => $search,
                'order_by' => $order_column,
                'order_dir' => $order_dir,
                'workflow_group_id' => $workflow_group_id
            ];

            $data = $this->model->getForDataTable($params);
            $total_records = $this->model->getTotalCount();
            $filtered_records = (!empty($search) || $workflow_group_id)
                ? $this->model->getFilteredCount($search, $workflow_group_id)
                : $total_records;

            // Format data for DataTables
            $formatted_data = [];
            foreach ($data as $machine) {
                $formatted_data[] = [
                    'id' => $machine->id,
                    'name' => esc_html($machine->name),
                    'slug' => esc_html($machine->slug),
                    'description' => !empty($machine->description) ? esc_html($machine->description) : '-',
                    'workflow_group_name' => !empty($machine->workflow_group_name) ? esc_html($machine->workflow_group_name) : '-',
                    'is_active' => $machine->is_active,
                    'created_at' => mysql2date(get_option('date_format'), $machine->created_at),
                    'actions' => $this->getActionButtons($machine)
                ];
            }

            $response = [
                'draw' => $draw,
                'recordsTotal' => $total_records,
                'recordsFiltered' => $filtered_records,
                'data' => $formatted_data
            ];

            // DISABLE CACHE - causing stuck spinner issues
            // Cache the result (5 minutes expiry for DataTable responses)
            // $this->cache->set('state_machines_list', $response, 300, $cache_key);

            wp_send_json($response);

        } catch (\Exception $e) {
            error_log('State Machine DataTable Error: ' . $e->getMessage());
            wp_send_json_error([
                'message' => __('Failed to load state machines', 'wp-state-machine')
            ]);
        }
    }

    /**
     * Create new state machine
     *
     * @return void
     */
    public function store() {
        // Verify nonce
        check_ajax_referer('wp_state_machine_nonce', 'nonce');

        // Check permission
        if (!current_user_can('manage_state_machines')) {
            wp_send_json_error([
                'message' => __('Permission denied', 'wp-state-machine')
            ]);
        }

        try {
            // Get and sanitize POST data
            $data = [
                'name' => isset($_POST['name']) ? sanitize_text_field($_POST['name']) : '',
                'slug' => isset($_POST['slug']) ? sanitize_title($_POST['slug']) : '',
                'plugin_slug' => isset($_POST['plugin_slug']) ? sanitize_text_field($_POST['plugin_slug']) : '',
                'entity_type' => isset($_POST['entity_type']) ? sanitize_text_field($_POST['entity_type']) : '',
                'workflow_group_id' => !empty($_POST['workflow_group_id']) ? intval($_POST['workflow_group_id']) : null,
                'description' => isset($_POST['description']) ? sanitize_textarea_field($_POST['description']) : '',
                'is_active' => isset($_POST['is_active']) ? (bool) $_POST['is_active'] : true
            ];

            // Validate form data
            $errors = $this->validator->validateForm($data);
            if (!empty($errors)) {
                wp_send_json_error([
                    'message' => __('Validation failed', 'wp-state-machine'),
                    'errors' => $errors
                ]);
            }

            // Create state machine
            $machine_id = $this->model->create($data);

            if ($machine_id) {
                // Clear ALL cache variations
                $this->cache->invalidateDataTableCache('state_machines_list');
                $this->cache->delete('state_machines_list');
                $this->cache->delete('state_machines_count', 'total');
                $this->cache->delete('state_machine', $machine_id);

                wp_send_json_success([
                    'message' => __('State machine created successfully', 'wp-state-machine'),
                    'id' => $machine_id
                ]);
            } else {
                wp_send_json_error([
                    'message' => __('Failed to create state machine', 'wp-state-machine')
                ]);
            }

        } catch (\Exception $e) {
            error_log('Create State Machine Error: ' . $e->getMessage());
            wp_send_json_error([
                'message' => __('An error occurred while creating the state machine', 'wp-state-machine')
            ]);
        }
    }

    /**
     * Update existing state machine
     *
     * @return void
     */
    public function update() {
        // Verify nonce
        check_ajax_referer('wp_state_machine_nonce', 'nonce');

        try {
            $id = isset($_POST['id']) ? intval($_POST['id']) : 0;

            if (!$id) {
                wp_send_json_error([
                    'message' => __('Invalid state machine ID', 'wp-state-machine')
                ]);
            }

            // Validate permission
            $permission = $this->validator->validatePermission($id, 'update');
            if (!$permission['allowed']) {
                wp_send_json_error([
                    'message' => $permission['message']
                ]);
            }

            // Get and sanitize POST data
            $data = [
                'name' => isset($_POST['name']) ? sanitize_text_field($_POST['name']) : '',
                'slug' => isset($_POST['slug']) ? sanitize_title($_POST['slug']) : '',
                'plugin_slug' => isset($_POST['plugin_slug']) ? sanitize_text_field($_POST['plugin_slug']) : '',
                'entity_type' => isset($_POST['entity_type']) ? sanitize_text_field($_POST['entity_type']) : '',
                'workflow_group_id' => !empty($_POST['workflow_group_id']) ? intval($_POST['workflow_group_id']) : null,
                'description' => isset($_POST['description']) ? sanitize_textarea_field($_POST['description']) : '',
                'is_active' => isset($_POST['is_active']) ? (bool) $_POST['is_active'] : true
            ];

            // Validate form data
            $errors = $this->validator->validateForm($data, $id);
            if (!empty($errors)) {
                wp_send_json_error([
                    'message' => __('Validation failed', 'wp-state-machine'),
                    'errors' => $errors
                ]);
            }

            // Update state machine
            $result = $this->model->update($id, $data);

            if ($result) {
                // Clear ALL cache variations
                $this->cache->invalidateDataTableCache('state_machines_list');
                $this->cache->delete('state_machines_list');
                $this->cache->delete('state_machines_count', 'total');
                $this->cache->delete('state_machine', $id);

                wp_send_json_success([
                    'message' => __('State machine updated successfully', 'wp-state-machine')
                ]);
            } else {
                wp_send_json_error([
                    'message' => __('Failed to update state machine', 'wp-state-machine')
                ]);
            }

        } catch (\Exception $e) {
            error_log('Update State Machine Error: ' . $e->getMessage());
            wp_send_json_error([
                'message' => __('An error occurred while updating the state machine', 'wp-state-machine')
            ]);
        }
    }

    /**
     * Delete state machine
     *
     * @return void
     */
    public function delete() {
        // Verify nonce
        check_ajax_referer('wp_state_machine_nonce', 'nonce');

        try {
            $id = isset($_POST['id']) ? intval($_POST['id']) : 0;

            if (!$id) {
                wp_send_json_error([
                    'message' => __('Invalid state machine ID', 'wp-state-machine')
                ]);
            }

            // Validate permission
            $permission = $this->validator->validatePermission($id, 'delete');
            if (!$permission['allowed']) {
                wp_send_json_error([
                    'message' => $permission['message']
                ]);
            }

            // Delete state machine
            $result = $this->model->delete($id);

            if ($result) {
                // Clear ALL cache variations
                $this->cache->invalidateDataTableCache('state_machines_list');
                $this->cache->delete('state_machines_list');
                $this->cache->delete('state_machines_count', 'total');
                $this->cache->delete('state_machine', $id);

                wp_send_json_success([
                    'message' => __('State machine deleted successfully', 'wp-state-machine')
                ]);
            } else {
                wp_send_json_error([
                    'message' => __('Failed to delete state machine', 'wp-state-machine')
                ]);
            }

        } catch (\Exception $e) {
            error_log('Delete State Machine Error: ' . $e->getMessage());
            wp_send_json_error([
                'message' => __('An error occurred while deleting the state machine', 'wp-state-machine')
            ]);
        }
    }

    /**
     * Get single state machine details
     *
     * @return void
     */
    public function show() {
        // Verify nonce
        check_ajax_referer('wp_state_machine_nonce', 'nonce');

        try {
            $id = isset($_POST['id']) ? intval($_POST['id']) : 0;

            if (!$id) {
                wp_send_json_error([
                    'message' => __('Invalid state machine ID', 'wp-state-machine')
                ]);
            }

            // Validate permission
            $permission = $this->validator->validatePermission($id, 'view');
            if (!$permission['allowed']) {
                wp_send_json_error([
                    'message' => $permission['message']
                ]);
            }

            // Get state machine
            $machine = $this->model->find($id);

            if ($machine) {
                wp_send_json_success([
                    'data' => $machine
                ]);
            } else {
                wp_send_json_error([
                    'message' => __('State machine not found', 'wp-state-machine')
                ]);
            }

        } catch (\Exception $e) {
            error_log('Show State Machine Error: ' . $e->getMessage());
            wp_send_json_error([
                'message' => __('An error occurred while loading the state machine', 'wp-state-machine')
            ]);
        }
    }

    /**
     * Generate action buttons for DataTable row
     *
     * @param object $machine State machine object
     * @return string HTML for action buttons
     */
    private function getActionButtons($machine) {
        $buttons = [];

        // View button
        if ($this->validator->canView($machine->id)) {
            $buttons[] = sprintf(
                '<button type="button" class="button button-small btn-view-machine" data-id="%d" title="%s">
                    <span class="dashicons dashicons-visibility"></span>
                </button>',
                $machine->id,
                esc_attr__('View', 'wp-state-machine')
            );
        }

        // Edit button
        if ($this->validator->canUpdate($machine->id)) {
            $buttons[] = sprintf(
                '<button type="button" class="button button-small btn-edit-machine" data-id="%d" title="%s">
                    <span class="dashicons dashicons-edit"></span>
                </button>',
                $machine->id,
                esc_attr__('Edit', 'wp-state-machine')
            );
        }

        // Delete button
        if ($this->validator->canDelete($machine->id)) {
            $buttons[] = sprintf(
                '<button type="button" class="button button-small btn-delete-machine" data-id="%d" title="%s">
                    <span class="dashicons dashicons-trash"></span>
                </button>',
                $machine->id,
                esc_attr__('Delete', 'wp-state-machine')
            );
        }

        return implode(' ', $buttons);
    }
}
