<?php
/**
 * State Controller Class
 *
 * @package     WP_State_Machine
 * @subpackage  Controllers
 * @version     1.0.0
 * @author      arisciwek
 *
 * Path: /wp-state-machine/src/Controllers/StateController.php
 *
 * Description: Handles all CRUD operations for states.
 *              Follows wp-agency AgencyController pattern.
 *              Manages DataTables integration, AJAX handlers, and validation.
 *
 * Dependencies:
 * - StateModel: Database operations
 * - StateValidator: Form and permission validation
 * - StateMachineCacheManager: Caching layer
 *
 * Changelog:
 * 1.0.0 - 2025-11-07
 * - Initial creation following StateMachineController pattern
 * - AJAX handlers for DataTables and CRUD
 * - Permission validation integration
 * - Cache management
 * - Machine-specific filtering
 */

namespace WPStateMachine\Controllers;

use WPStateMachine\Models\State\StateModel;
use WPStateMachine\Validators\StateValidator;
use WPStateMachine\Cache\StateMachineCacheManager;

defined('ABSPATH') || exit;

class StateController {
    /**
     * State Model instance
     *
     * @var StateModel
     */
    private $model;

    /**
     * State Validator instance
     *
     * @var StateValidator
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
        $this->model = new StateModel();
        $this->validator = new StateValidator();
        $this->cache = new StateMachineCacheManager();

        // Register AJAX handlers
        add_action('wp_ajax_handle_state_datatable', [$this, 'handleDataTableRequest']);
        add_action('wp_ajax_create_state', [$this, 'store']);
        add_action('wp_ajax_update_state', [$this, 'update']);
        add_action('wp_ajax_delete_state', [$this, 'delete']);
        add_action('wp_ajax_show_state', [$this, 'show']);
        add_action('wp_ajax_get_states_by_machine', [$this, 'getStatesByMachine']);
    }

    /**
     * Render main state admin page
     *
     * @return void
     */
    public function renderMainPage() {
        // Check permission
        if (!current_user_can('view_state_machines')) {
            wp_die(__('You do not have permission to access this page.', 'wp-state-machine'));
        }

        // Load view
        $view_path = WP_STATE_MACHINE_PATH . 'src/Views/admin/states/states-view.php';
        if (file_exists($view_path)) {
            include $view_path;
        } else {
            echo '<div class="wrap">';
            echo '<h1>' . esc_html__('States', 'wp-state-machine') . '</h1>';
            echo '<p>' . esc_html__('State management interface.', 'wp-state-machine') . '</p>';
            echo '</div>';
        }
    }

    /**
     * Handle DataTable AJAX request
     * Supports server-side processing with pagination, search, and sorting
     * Filters by machine_id if provided
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
            $machine_id = isset($_POST['machine_id']) ? intval($_POST['machine_id']) : 0;

            // Map column index to database column
            $columns = ['id', 'name', 'slug', 'type', 'sort_order', 'created_at'];
            $order_column = isset($columns[$order_column_index]) ? $columns[$order_column_index] : 'sort_order';

            // DISABLE CACHE FOR NOW - causing stuck spinner issues
            // Build cache key
            // $cache_key = sprintf(
            //     'datatable_%d_%d_%d_%s_%s_%s',
            //     $machine_id,
            //     $start,
            //     $length,
            //     $search,
            //     $order_column,
            //     $order_dir
            // );

            // Try to get from cache
            // $cached_result = $this->cache->get('states_list', $cache_key);
            // if ($cached_result !== null) {
            //     wp_send_json($cached_result);
            //     return;
            // }

            // Get data from model
            $params = [
                'machine_id' => $machine_id,
                'start' => $start,
                'length' => $length,
                'search' => $search,
                'order_by' => $order_column,
                'order_dir' => $order_dir
            ];

            $data = $this->getForDataTable($params);
            $total_records = $machine_id > 0 ? $this->model->countByMachine($machine_id) : $this->model->getTotalCount();
            $filtered_records = !empty($search) ? $this->getFilteredCount($params) : $total_records;

            // Format data for DataTables
            $formatted_data = [];
            foreach ($data as $state) {
                $formatted_data[] = [
                    'id' => $state->id,
                    'name' => esc_html($state->name),
                    'slug' => esc_html($state->slug),
                    'type' => ucfirst(esc_html($state->type)),
                    'color' => !empty($state->color) ? esc_html($state->color) : '-',
                    'sort_order' => $state->sort_order,
                    'machine_name' => !empty($state->machine_name) ? esc_html($state->machine_name) : '-',
                    'created_at' => mysql2date(get_option('date_format'), $state->created_at),
                    'actions' => $this->getActionButtons($state)
                ];
            }

            $response = [
                'draw' => $draw,
                'recordsTotal' => $total_records,
                'recordsFiltered' => $filtered_records,
                'data' => $formatted_data
            ];

            // DISABLE CACHE - causing stuck spinner issues
            // Cache the result
            // $this->cache->set('states_list', $response, 300, $cache_key);

            wp_send_json($response);

        } catch (\Exception $e) {
            error_log('State DataTable Error: ' . $e->getMessage());
            wp_send_json_error([
                'message' => __('Failed to load states', 'wp-state-machine')
            ]);
        }
    }

    /**
     * Get states for DataTable with optional machine filtering
     *
     * @param array $params Query parameters
     * @return array Array of state objects
     */
    private function getForDataTable(array $params): array {
        global $wpdb;

        $table_name = $wpdb->prefix . 'app_sm_states';
        $machine_table = $wpdb->prefix . 'app_sm_machines';

        // Build WHERE clause
        $where = ['1=1'];
        $where_values = [];

        if (!empty($params['machine_id'])) {
            $where[] = "s.machine_id = %d";
            $where_values[] = $params['machine_id'];
        }

        if (!empty($params['search'])) {
            $where[] = "(s.name LIKE %s OR s.slug LIKE %s OR s.type LIKE %s)";
            $search_term = '%' . $wpdb->esc_like($params['search']) . '%';
            $where_values[] = $search_term;
            $where_values[] = $search_term;
            $where_values[] = $search_term;
        }

        $where_clause = implode(' AND ', $where);

        // Build ORDER BY clause
        $order_by = $params['order_by'] ?? 'sort_order';
        $order_dir = strtoupper($params['order_dir']) === 'DESC' ? 'DESC' : 'ASC';

        // Build LIMIT clause
        $limit = '';
        if (isset($params['length']) && $params['length'] > 0) {
            $offset = $params['start'] ?? 0;
            $limit = $wpdb->prepare("LIMIT %d, %d", $offset, $params['length']);
        }

        // Build final query with JOIN to get machine name
        $sql = "SELECT s.*, m.name as machine_name
                FROM {$table_name} s
                LEFT JOIN {$machine_table} m ON s.machine_id = m.id
                WHERE {$where_clause}
                ORDER BY s.{$order_by} {$order_dir}
                {$limit}";

        if (!empty($where_values)) {
            $sql = $wpdb->prepare($sql, $where_values);
        }

        return $wpdb->get_results($sql);
    }

    /**
     * Get filtered count for search results
     *
     * @param array $params Query parameters
     * @return int Filtered record count
     */
    private function getFilteredCount(array $params): int {
        global $wpdb;

        $table_name = $wpdb->prefix . 'app_sm_states';

        // Build WHERE clause
        $where = ['1=1'];
        $where_values = [];

        if (!empty($params['machine_id'])) {
            $where[] = "machine_id = %d";
            $where_values[] = $params['machine_id'];
        }

        if (!empty($params['search'])) {
            $where[] = "(name LIKE %s OR slug LIKE %s OR type LIKE %s)";
            $search_term = '%' . $wpdb->esc_like($params['search']) . '%';
            $where_values[] = $search_term;
            $where_values[] = $search_term;
            $where_values[] = $search_term;
        }

        $where_clause = implode(' AND ', $where);

        $sql = "SELECT COUNT(*) FROM {$table_name} WHERE {$where_clause}";

        if (!empty($where_values)) {
            $sql = $wpdb->prepare($sql, $where_values);
        }

        return (int) $wpdb->get_var($sql);
    }

    /**
     * Create new state
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
                'machine_id' => isset($_POST['machine_id']) ? intval($_POST['machine_id']) : 0,
                'name' => isset($_POST['name']) ? sanitize_text_field($_POST['name']) : '',
                'slug' => isset($_POST['slug']) ? sanitize_title($_POST['slug']) : '',
                'type' => isset($_POST['type']) ? sanitize_text_field($_POST['type']) : 'normal',
                'color' => isset($_POST['color']) ? sanitize_hex_color($_POST['color']) : '',
                'metadata' => isset($_POST['metadata']) ? sanitize_textarea_field($_POST['metadata']) : '',
                'sort_order' => isset($_POST['sort_order']) ? intval($_POST['sort_order']) : 0
            ];

            // Validate form data
            $errors = $this->validator->validateForm($data);
            if (!empty($errors)) {
                wp_send_json_error([
                    'message' => __('Validation failed', 'wp-state-machine'),
                    'errors' => $errors
                ]);
            }

            // Create state
            $state_id = $this->model->create($data);

            if ($state_id) {
                // Clear ALL cache variations
                $this->cache->invalidateDataTableCache('states_list');
                $this->cache->delete('states_list');
                $this->cache->delete('states_count', 'total');
                $this->cache->delete('state', $state_id);
                $this->cache->delete('states_by_machine', $data['machine_id']);

                wp_send_json_success([
                    'message' => __('State created successfully', 'wp-state-machine'),
                    'id' => $state_id
                ]);
            } else{
                wp_send_json_error([
                    'message' => __('Failed to create state', 'wp-state-machine')
                ]);
            }

        } catch (\Exception $e) {
            error_log('Create State Error: ' . $e->getMessage());
            wp_send_json_error([
                'message' => __('An error occurred while creating the state', 'wp-state-machine')
            ]);
        }
    }

    /**
     * Update existing state
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
                    'message' => __('Invalid state ID', 'wp-state-machine')
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
                'machine_id' => isset($_POST['machine_id']) ? intval($_POST['machine_id']) : 0,
                'name' => isset($_POST['name']) ? sanitize_text_field($_POST['name']) : '',
                'slug' => isset($_POST['slug']) ? sanitize_title($_POST['slug']) : '',
                'type' => isset($_POST['type']) ? sanitize_text_field($_POST['type']) : 'normal',
                'color' => isset($_POST['color']) ? sanitize_hex_color($_POST['color']) : '',
                'metadata' => isset($_POST['metadata']) ? sanitize_textarea_field($_POST['metadata']) : '',
                'sort_order' => isset($_POST['sort_order']) ? intval($_POST['sort_order']) : 0
            ];

            // Validate form data
            $errors = $this->validator->validateForm($data, $id);
            if (!empty($errors)) {
                wp_send_json_error([
                    'message' => __('Validation failed', 'wp-state-machine'),
                    'errors' => $errors
                ]);
            }

            // Update state
            $result = $this->model->update($id, $data);

            if ($result) {
                // Clear ALL cache variations
                $this->cache->invalidateDataTableCache('states_list');
                $this->cache->delete('states_list');
                $this->cache->delete('states_count', 'total');
                $this->cache->delete('state', $id);
                $this->cache->delete('states_by_machine', $data['machine_id']);

                wp_send_json_success([
                    'message' => __('State updated successfully', 'wp-state-machine')
                ]);
            } else {
                wp_send_json_error([
                    'message' => __('Failed to update state', 'wp-state-machine')
                ]);
            }

        } catch (\Exception $e) {
            error_log('Update State Error: ' . $e->getMessage());
            wp_send_json_error([
                'message' => __('An error occurred while updating the state', 'wp-state-machine')
            ]);
        }
    }

    /**
     * Delete state
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
                    'message' => __('Invalid state ID', 'wp-state-machine')
                ]);
            }

            // Get state before deletion for cache clearing
            $state = $this->model->find($id);
            if (!$state) {
                wp_send_json_error([
                    'message' => __('State not found', 'wp-state-machine')
                ]);
            }

            // Validate permission
            $permission = $this->validator->validatePermission($id, 'delete');
            if (!$permission['allowed']) {
                wp_send_json_error([
                    'message' => $permission['message']
                ]);
            }

            // Delete state
            $result = $this->model->delete($id);

            if ($result) {
                // Clear ALL cache variations
                $this->cache->invalidateDataTableCache('states_list');
                $this->cache->delete('states_list');
                $this->cache->delete('states_count', 'total');
                $this->cache->delete('state', $id);
                $this->cache->delete('states_by_machine', $state->machine_id);

                wp_send_json_success([
                    'message' => __('State deleted successfully', 'wp-state-machine')
                ]);
            } else {
                wp_send_json_error([
                    'message' => __('Failed to delete state', 'wp-state-machine')
                ]);
            }

        } catch (\Exception $e) {
            error_log('Delete State Error: ' . $e->getMessage());
            wp_send_json_error([
                'message' => __('An error occurred while deleting the state', 'wp-state-machine')
            ]);
        }
    }

    /**
     * Get single state details
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
                    'message' => __('Invalid state ID', 'wp-state-machine')
                ]);
            }

            // Validate permission
            $permission = $this->validator->validatePermission($id, 'view');
            if (!$permission['allowed']) {
                wp_send_json_error([
                    'message' => $permission['message']
                ]);
            }

            // Get state
            $state = $this->model->find($id);

            if ($state) {
                wp_send_json_success([
                    'data' => $state
                ]);
            } else {
                wp_send_json_error([
                    'message' => __('State not found', 'wp-state-machine')
                ]);
            }

        } catch (\Exception $e) {
            error_log('Show State Error: ' . $e->getMessage());
            wp_send_json_error([
                'message' => __('An error occurred while loading the state', 'wp-state-machine')
            ]);
        }
    }

    /**
     * Generate action buttons for DataTable row
     *
     * @param object $state State object
     * @return string HTML for action buttons
     */
    private function getActionButtons($state) {
        $buttons = [];

        // View button
        if ($this->validator->canView($state->id)) {
            $buttons[] = sprintf(
                '<button type="button" class="button button-small btn-view-state" data-id="%d" title="%s">
                    <span class="dashicons dashicons-visibility"></span>
                </button>',
                $state->id,
                esc_attr__('View', 'wp-state-machine')
            );
        }

        // Edit button
        if ($this->validator->canUpdate($state->id)) {
            $buttons[] = sprintf(
                '<button type="button" class="button button-small btn-edit-state" data-id="%d" title="%s">
                    <span class="dashicons dashicons-edit"></span>
                </button>',
                $state->id,
                esc_attr__('Edit', 'wp-state-machine')
            );
        }

        // Delete button
        if ($this->validator->canDelete($state->id)) {
            $buttons[] = sprintf(
                '<button type="button" class="button button-small btn-delete-state" data-id="%d" title="%s">
                    <span class="dashicons dashicons-trash"></span>
                </button>',
                $state->id,
                esc_attr__('Delete', 'wp-state-machine')
            );
        }

        return implode(' ', $buttons);
    }

    /**
     * Get states for a specific machine (AJAX handler for transitions)
     *
     * @return void
     */
    public function getStatesByMachine() {
        // Verify nonce
        check_ajax_referer('wp_state_machine_nonce', 'nonce');

        // Check permission
        if (!current_user_can('view_state_machines')) {
            wp_send_json_error([
                'message' => __('Permission denied', 'wp-state-machine')
            ]);
        }

        try {
            $machine_id = isset($_POST['machine_id']) ? intval($_POST['machine_id']) : 0;

            if (!$machine_id) {
                wp_send_json_error([
                    'message' => __('Invalid machine ID', 'wp-state-machine')
                ]);
            }

            // Get states for this machine
            $states = $this->model->getByMachine($machine_id);

            wp_send_json_success($states);

        } catch (\Exception $e) {
            error_log('Get States By Machine Error: ' . $e->getMessage());
            wp_send_json_error([
                'message' => __('An error occurred while loading states', 'wp-state-machine')
            ]);
        }
    }
}
