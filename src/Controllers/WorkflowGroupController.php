<?php
/**
 * Workflow Group Controller Class
 *
 * @package     WP_State_Machine
 * @subpackage  Controllers
 * @version     1.0.0
 * @author      arisciwek
 *
 * Path: /wp-state-machine/src/Controllers/WorkflowGroupController.php
 *
 * Description: Handles all CRUD operations for workflow groups.
 *              Follows StateMachineController pattern for consistency.
 *              Manages DataTables integration, AJAX handlers, and validation.
 *
 * Dependencies:
 * - WorkflowGroupModel: Database operations
 * - WorkflowGroupValidator: Form and permission validation
 * - StateMachineCacheManager: Caching layer
 *
 * Changelog:
 * 1.0.0 - 2025-11-07 (TODO-6102 PRIORITAS #7)
 * - Initial creation for FASE 3
 * - AJAX handlers for DataTables and CRUD
 * - Permission validation integration
 * - Cache management
 * - Sort order management
 */

namespace WPStateMachine\Controllers;

use WPStateMachine\Models\WorkflowGroup\WorkflowGroupModel;
use WPStateMachine\Validators\WorkflowGroupValidator;
use WPStateMachine\Cache\StateMachineCacheManager;

defined('ABSPATH') || exit;

class WorkflowGroupController {
    /**
     * Workflow Group Model instance
     *
     * @var WorkflowGroupModel
     */
    private $model;

    /**
     * Workflow Group Validator instance
     *
     * @var WorkflowGroupValidator
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
        $this->model = new WorkflowGroupModel();
        $this->validator = new WorkflowGroupValidator();
        $this->cache = new StateMachineCacheManager();

        // Register AJAX handlers
        add_action('wp_ajax_handle_workflow_group_datatable', [$this, 'handleDataTableRequest']);
        add_action('wp_ajax_create_workflow_group', [$this, 'store']);
        add_action('wp_ajax_update_workflow_group', [$this, 'update']);
        add_action('wp_ajax_delete_workflow_group', [$this, 'delete']);
        add_action('wp_ajax_show_workflow_group', [$this, 'show']);
        add_action('wp_ajax_update_workflow_group_sort_order', [$this, 'updateSortOrder']);
    }

    /**
     * Render main workflow groups admin page
     *
     * @return void
     */
    public function renderMainPage() {
        // Check permission
        if (!current_user_can('view_state_machines')) {
            wp_die(__('You do not have permission to access this page.', 'wp-state-machine'));
        }

        // Assets enqueued by class-dependencies.php

        // Load view
        $view_path = WP_STATE_MACHINE_PATH . 'src/Views/admin/workflow-groups/workflow-groups-view.php';
        if (file_exists($view_path)) {
            include $view_path;
        } else {
            echo '<div class="wrap">';
            echo '<h1>' . esc_html__('Workflow Groups', 'wp-state-machine') . '</h1>';
            echo '<p>' . esc_html__('Organize state machines into logical groups.', 'wp-state-machine') . '</p>';
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
            $order_dir = isset($_POST['order'][0]['dir']) ? sanitize_text_field($_POST['order'][0]['dir']) : 'ASC';

            // Map column index to database column
            $columns = ['id', 'name', 'slug', 'sort_order', 'is_active', 'created_at'];
            $order_column = isset($columns[$order_column_index]) ? $columns[$order_column_index] : 'sort_order';

            // Build cache key
            $cache_key = sprintf(
                'datatable_%d_%d_%s_%s_%s',
                $start,
                $length,
                $search,
                $order_column,
                $order_dir
            );

            // Try to get from cache
            $cached_result = $this->cache->get('workflow_groups_list', $cache_key);
            if ($cached_result !== null) {
                wp_send_json($cached_result);
                return;
            }

            // Get data from model
            $params = [
                'start' => $start,
                'length' => $length,
                'search' => $search,
                'order_column' => $order_column,
                'order_dir' => $order_dir,
                'draw' => $draw
            ];

            $result = $this->model->getForDataTable($params);

            // Add action buttons to each row
            if (!empty($result['data'])) {
                foreach ($result['data'] as $index => $row) {
                    // Convert object to array if needed
                    $rowData = is_object($row) ? (array) $row : $row;
                    $rowData['actions'] = $this->getActionButtons($row);
                    $result['data'][$index] = $rowData;
                }
            }

            // Cache result for 5 minutes
            $this->cache->set('workflow_groups_list', $result, 300, $cache_key);

            wp_send_json($result);

        } catch (\Exception $e) {
            error_log('WorkflowGroupController: DataTable error - ' . $e->getMessage());
            wp_send_json_error([
                'message' => __('Failed to load workflow groups', 'wp-state-machine')
            ]);
        }
    }

    /**
     * Store new workflow group
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
            // Sanitize input
            $data = $this->validator->sanitize($_POST);

            // Validate data
            $errors = $this->validator->validateForm($data);
            if (!empty($errors)) {
                wp_send_json_error([
                    'message' => __('Validation failed', 'wp-state-machine'),
                    'errors' => $errors
                ]);
            }

            // Create workflow group
            $id = $this->model->create($data);

            if (!$id) {
                wp_send_json_error([
                    'message' => __('Failed to create workflow group', 'wp-state-machine')
                ]);
            }

            // Clear cache
            $this->cache->invalidateDataTableCache('workflow_groups_list');
            $this->cache->delete('workflow_groups_list');
            $this->cache->delete('workflow_groups_count', 'total');
            $this->cache->delete('workflow_group', $id);

            // Get created group
            $group = $this->model->find($id);

            wp_send_json_success([
                'message' => __('Workflow group created successfully', 'wp-state-machine'),
                'group' => $group
            ]);

        } catch (\Exception $e) {
            error_log('WorkflowGroupController: Store error - ' . $e->getMessage());
            wp_send_json_error([
                'message' => __('An error occurred while creating workflow group', 'wp-state-machine')
            ]);
        }
    }

    /**
     * Update existing workflow group
     *
     * @return void
     */
    public function update() {
        // Verify nonce
        check_ajax_referer('wp_state_machine_nonce', 'nonce');

        // Get workflow group ID
        $id = isset($_POST['id']) ? intval($_POST['id']) : 0;

        // Check if workflow group exists
        $group = $this->model->find($id);
        if (!$group) {
            wp_send_json_error([
                'message' => __('Workflow group not found', 'wp-state-machine')
            ]);
        }

        // Check permission
        if (!current_user_can('manage_state_machines')) {
            wp_send_json_error([
                'message' => __('Permission denied', 'wp-state-machine')
            ]);
        }

        try {
            // Sanitize input
            $data = $this->validator->sanitize($_POST);

            // Validate data
            $errors = $this->validator->validateForm($data, $id);
            if (!empty($errors)) {
                wp_send_json_error([
                    'message' => __('Validation failed', 'wp-state-machine'),
                    'errors' => $errors
                ]);
            }

            // Update workflow group
            $updated = $this->model->update($id, $data);

            if (!$updated) {
                wp_send_json_error([
                    'message' => __('Failed to update workflow group', 'wp-state-machine')
                ]);
            }

            // Mark as custom (user modified)
            $this->model->markAsCustom($id);

            // Clear cache
            $this->cache->invalidateDataTableCache('workflow_groups_list');
            $this->cache->delete('workflow_groups_list');
            $this->cache->delete('workflow_groups_count', 'total');
            $this->cache->delete('workflow_group', $id);

            // Get updated group
            $group = $this->model->find($id);

            wp_send_json_success([
                'message' => __('Workflow group updated successfully', 'wp-state-machine'),
                'group' => $group
            ]);

        } catch (\Exception $e) {
            error_log('WorkflowGroupController: Update error - ' . $e->getMessage());
            wp_send_json_error([
                'message' => __('An error occurred while updating workflow group', 'wp-state-machine')
            ]);
        }
    }

    /**
     * Delete workflow group
     *
     * @return void
     */
    public function delete() {
        // Verify nonce
        check_ajax_referer('wp_state_machine_nonce', 'nonce');

        // Get workflow group ID
        $id = isset($_POST['id']) ? intval($_POST['id']) : 0;

        // Check if workflow group exists
        $group = $this->model->find($id);
        if (!$group) {
            wp_send_json_error([
                'message' => __('Workflow group not found', 'wp-state-machine')
            ]);
        }

        // Check permission
        if (!current_user_can('manage_state_machines')) {
            wp_send_json_error([
                'message' => __('Permission denied', 'wp-state-machine')
            ]);
        }

        try {
            // Validate delete operation (check for assigned machines)
            $errors = $this->validator->validateDelete($id);
            if (!empty($errors)) {
                wp_send_json_error([
                    'message' => reset($errors), // Get first error message
                    'errors' => $errors
                ]);
            }

            // Delete workflow group
            $deleted = $this->model->delete($id);

            if (!$deleted) {
                wp_send_json_error([
                    'message' => __('Failed to delete workflow group', 'wp-state-machine')
                ]);
            }

            // Clear cache
            $this->cache->invalidateDataTableCache('workflow_groups_list');
            $this->cache->delete('workflow_groups_list');
            $this->cache->delete('workflow_groups_count', 'total');
            $this->cache->delete('workflow_group', $id);

            wp_send_json_success([
                'message' => __('Workflow group deleted successfully', 'wp-state-machine')
            ]);

        } catch (\Exception $e) {
            error_log('WorkflowGroupController: Delete error - ' . $e->getMessage());
            wp_send_json_error([
                'message' => __('An error occurred while deleting workflow group', 'wp-state-machine')
            ]);
        }
    }

    /**
     * Show single workflow group details
     *
     * @return void
     */
    public function show() {
        // Verify nonce
        check_ajax_referer('wp_state_machine_nonce', 'nonce');

        // Get workflow group ID
        $id = isset($_POST['id']) ? intval($_POST['id']) : 0;

        // Check permission
        if (!current_user_can('view_state_machines')) {
            wp_send_json_error([
                'message' => __('Permission denied', 'wp-state-machine')
            ]);
        }

        try {
            // Get workflow group
            $group = $this->model->find($id);

            if (!$group) {
                wp_send_json_error([
                    'message' => __('Workflow group not found', 'wp-state-machine')
                ]);
            }

            // Get assigned machines count
            $machines = $this->model->getMachines($id);
            $machine_count = count($machines);

            // Add machine count to group data
            $groupData = (array) $group;
            $groupData['machine_count'] = $machine_count;

            wp_send_json_success([
                'data' => $groupData
            ]);

        } catch (\Exception $e) {
            error_log('WorkflowGroupController: Show error - ' . $e->getMessage());
            wp_send_json_error([
                'message' => __('Failed to load workflow group', 'wp-state-machine')
            ]);
        }
    }

    /**
     * Update sort order for multiple groups
     * For drag-drop reordering
     *
     * @return void
     */
    public function updateSortOrder() {
        // Verify nonce
        check_ajax_referer('wp_state_machine_nonce', 'nonce');

        // Check permission
        if (!current_user_can('manage_state_machines')) {
            wp_send_json_error([
                'message' => __('Permission denied', 'wp-state-machine')
            ]);
        }

        try {
            // Get order data
            $order_data = isset($_POST['order']) ? $_POST['order'] : [];

            // Validate
            $errors = $this->validator->validateSortOrders($order_data);
            if (!empty($errors)) {
                wp_send_json_error([
                    'message' => __('Invalid sort order data', 'wp-state-machine'),
                    'errors' => $errors
                ]);
            }

            // Update sort orders
            $updated = $this->model->updateSortOrders($order_data);

            if (!$updated) {
                wp_send_json_error([
                    'message' => __('Failed to update sort orders', 'wp-state-machine')
                ]);
            }

            // Clear cache
            $this->cache->flush();

            wp_send_json_success([
                'message' => __('Sort order updated successfully', 'wp-state-machine')
            ]);

        } catch (\Exception $e) {
            error_log('WorkflowGroupController: Update sort order error - ' . $e->getMessage());
            wp_send_json_error([
                'message' => __('An error occurred while updating sort order', 'wp-state-machine')
            ]);
        }
    }

    /**
     * Generate action buttons for DataTable row
     *
     * @param array|object $group Workflow group data
     * @return string HTML for action buttons
     */
    private function getActionButtons($group) {
        // Convert to object if array
        if (is_array($group)) {
            $group = (object) $group;
        }

        $buttons = [];

        // View button
        $buttons[] = sprintf(
            '<button type="button" class="button button-small btn-view-group" data-id="%d" title="%s">
                <span class="dashicons dashicons-visibility"></span>
            </button>',
            $group->id,
            esc_attr__('View', 'wp-state-machine')
        );

        // Edit button
        $buttons[] = sprintf(
            '<button type="button" class="button button-small btn-edit-group" data-id="%d" title="%s">
                <span class="dashicons dashicons-edit"></span>
            </button>',
            $group->id,
            esc_attr__('Edit', 'wp-state-machine')
        );

        // Delete button
        $buttons[] = sprintf(
            '<button type="button" class="button button-small btn-delete-group" data-id="%d" title="%s">
                <span class="dashicons dashicons-trash"></span>
            </button>',
            $group->id,
            esc_attr__('Delete', 'wp-state-machine')
        );

        return implode(' ', $buttons);
    }

    /**
     * Get active groups for dropdown
     * Public method for use by other controllers
     *
     * @return array Active workflow groups
     */
    public function getActiveGroups(): array {
        return $this->model->getActiveGroups();
    }
}
