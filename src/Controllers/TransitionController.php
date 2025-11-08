<?php
/**
 * Transition Controller Class
 *
 * @package     WP_State_Machine
 * @subpackage  Controllers
 * @version     1.0.0
 * @author      arisciwek
 *
 * Path: /wp-state-machine/src/Controllers/TransitionController.php
 *
 * Description: Handles all CRUD operations for transitions.
 *              Follows wp-agency AgencyController pattern.
 *              Manages DataTables integration, AJAX handlers, and validation.
 *
 * Dependencies:
 * - TransitionModel: Database operations
 * - TransitionValidator: Form and permission validation
 * - StateMachineCacheManager: Caching layer
 *
 * Changelog:
 * 1.0.0 - 2025-11-07
 * - Initial creation following StateController pattern
 * - AJAX handlers for DataTables and CRUD
 * - Permission validation integration
 * - Cache management
 * - Machine-specific filtering
 * - State name JOINs in DataTable
 */

namespace WPStateMachine\Controllers;

use WPStateMachine\Models\Transition\TransitionModel;
use WPStateMachine\Validators\TransitionValidator;
use WPStateMachine\Cache\StateMachineCacheManager;

defined('ABSPATH') || exit;

class TransitionController {
    /**
     * Transition Model instance
     *
     * @var TransitionModel
     */
    private $model;

    /**
     * Transition Validator instance
     *
     * @var TransitionValidator
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
        $this->model = new TransitionModel();
        $this->validator = new TransitionValidator();
        $this->cache = new StateMachineCacheManager();

        // Register AJAX handlers
        add_action('wp_ajax_handle_transition_datatable', [$this, 'handleDataTableRequest']);
        add_action('wp_ajax_create_transition', [$this, 'store']);
        add_action('wp_ajax_update_transition', [$this, 'update']);
        add_action('wp_ajax_delete_transition', [$this, 'delete']);
        add_action('wp_ajax_show_transition', [$this, 'show']);
    }

    /**
     * Render main transition admin page
     *
     * @return void
     */
    public function renderMainPage() {
        // Check permission
        if (!current_user_can('view_state_machines')) {
            wp_die(__('You do not have permission to access this page.', 'wp-state-machine'));
        }

        // Load view
        $view_path = WP_STATE_MACHINE_PATH . 'src/Views/admin/transitions/transitions-view.php';
        if (file_exists($view_path)) {
            include $view_path;
        } else {
            echo '<div class="wrap">';
            echo '<h1>' . esc_html__('Transitions', 'wp-state-machine') . '</h1>';
            echo '<p>' . esc_html__('Transition management interface.', 'wp-state-machine') . '</p>';
            echo '</div>';
        }
    }

    /**
     * Handle DataTable AJAX request
     * Supports server-side processing with pagination, search, and sorting
     * Filters by machine_id if provided
     * Includes from_state_name and to_state_name via JOINs
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
            $columns = ['id', 'label', 'from_state_name', 'to_state_name', 'guard_class', 'sort_order', 'created_at'];
            $order_column = isset($columns[$order_column_index]) ? $columns[$order_column_index] : 'sort_order';

            // Build cache key
            $cache_key = sprintf(
                'datatable_%d_%d_%d_%s_%s_%s',
                $machine_id,
                $start,
                $length,
                $search,
                $order_column,
                $order_dir
            );

            // Try to get from cache
            $cached_result = $this->cache->get('transitions_list', $cache_key);
            if ($cached_result !== null) {
                wp_send_json($cached_result);
                return;
            }

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
            foreach ($data as $transition) {
                $formatted_data[] = [
                    'id' => $transition->id,
                    'label' => esc_html($transition->label),
                    'from_state_name' => !empty($transition->from_state_name) ? esc_html($transition->from_state_name) : '-',
                    'to_state_name' => !empty($transition->to_state_name) ? esc_html($transition->to_state_name) : '-',
                    'guard_class' => !empty($transition->guard_class) ? esc_html($transition->guard_class) : '-',
                    'sort_order' => $transition->sort_order,
                    'machine_name' => !empty($transition->machine_name) ? esc_html($transition->machine_name) : '-',
                    'created_at' => mysql2date(get_option('date_format'), $transition->created_at),
                    'actions' => $this->getActionButtons($transition)
                ];
            }

            $response = [
                'draw' => $draw,
                'recordsTotal' => $total_records,
                'recordsFiltered' => $filtered_records,
                'data' => $formatted_data
            ];

            // Cache the result
            $this->cache->set('transitions_list', $response, 300, $cache_key);

            wp_send_json($response);

        } catch (\Exception $e) {
            error_log('Transition DataTable Error: ' . $e->getMessage());
            wp_send_json_error([
                'message' => __('Failed to load transitions', 'wp-state-machine')
            ]);
        }
    }

    /**
     * Get transitions for DataTable with optional machine filtering
     * Includes JOINs for state names and machine name
     *
     * @param array $params Query parameters
     * @return array Array of transition objects
     */
    private function getForDataTable(array $params): array {
        global $wpdb;

        $table_name = $wpdb->prefix . 'app_sm_transitions';
        $state_table = $wpdb->prefix . 'app_sm_states';
        $machine_table = $wpdb->prefix . 'app_sm_machines';

        // Build WHERE clause
        $where = ['1=1'];
        $where_values = [];

        if (!empty($params['machine_id'])) {
            $where[] = "t.machine_id = %d";
            $where_values[] = $params['machine_id'];
        }

        if (!empty($params['search'])) {
            $where[] = "(t.label LIKE %s OR fs.name LIKE %s OR ts.name LIKE %s OR t.guard_class LIKE %s)";
            $search_term = '%' . $wpdb->esc_like($params['search']) . '%';
            $where_values[] = $search_term;
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

        // Build final query with JOINs
        $sql = "SELECT t.*,
                       fs.name as from_state_name,
                       ts.name as to_state_name,
                       m.name as machine_name
                FROM {$table_name} t
                LEFT JOIN {$state_table} fs ON t.from_state_id = fs.id
                LEFT JOIN {$state_table} ts ON t.to_state_id = ts.id
                LEFT JOIN {$machine_table} m ON t.machine_id = m.id
                WHERE {$where_clause}
                ORDER BY t.{$order_by} {$order_dir}
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

        $table_name = $wpdb->prefix . 'app_sm_transitions';
        $state_table = $wpdb->prefix . 'app_sm_states';

        // Build WHERE clause
        $where = ['1=1'];
        $where_values = [];

        if (!empty($params['machine_id'])) {
            $where[] = "t.machine_id = %d";
            $where_values[] = $params['machine_id'];
        }

        if (!empty($params['search'])) {
            $where[] = "(t.label LIKE %s OR fs.name LIKE %s OR ts.name LIKE %s OR t.guard_class LIKE %s)";
            $search_term = '%' . $wpdb->esc_like($params['search']) . '%';
            $where_values[] = $search_term;
            $where_values[] = $search_term;
            $where_values[] = $search_term;
            $where_values[] = $search_term;
        }

        $where_clause = implode(' AND ', $where);

        $sql = "SELECT COUNT(*) FROM {$table_name} t
                LEFT JOIN {$state_table} fs ON t.from_state_id = fs.id
                LEFT JOIN {$state_table} ts ON t.to_state_id = ts.id
                WHERE {$where_clause}";

        if (!empty($where_values)) {
            $sql = $wpdb->prepare($sql, $where_values);
        }

        return (int) $wpdb->get_var($sql);
    }

    /**
     * Create new transition
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
                'from_state_id' => isset($_POST['from_state_id']) ? intval($_POST['from_state_id']) : 0,
                'to_state_id' => isset($_POST['to_state_id']) ? intval($_POST['to_state_id']) : 0,
                'label' => isset($_POST['label']) ? sanitize_text_field($_POST['label']) : '',
                'guard_class' => isset($_POST['guard_class']) ? sanitize_text_field($_POST['guard_class']) : '',
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

            // Create transition
            $transition_id = $this->model->create($data);

            if ($transition_id) {
                // Clear cache
                $this->cache->invalidateDataTableCache('transitions_list');
                $this->cache->delete('transitions_list');
                $this->cache->delete('transitions_count', 'total');
                $this->cache->delete('transition', $transition_id);
                $this->cache->delete('transitions_by_machine', $data['machine_id']);

                wp_send_json_success([
                    'message' => __('Transition created successfully', 'wp-state-machine'),
                    'id' => $transition_id
                ]);
            } else {
                wp_send_json_error([
                    'message' => __('Failed to create transition', 'wp-state-machine')
                ]);
            }

        } catch (\Exception $e) {
            error_log('Create Transition Error: ' . $e->getMessage());
            wp_send_json_error([
                'message' => __('An error occurred while creating the transition', 'wp-state-machine')
            ]);
        }
    }

    /**
     * Update existing transition
     * Note: from_state_id and to_state_id cannot be changed after creation
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
                    'message' => __('Invalid transition ID', 'wp-state-machine')
                ]);
            }

            // Validate permission
            $permission = $this->validator->validatePermission($id, 'update');
            if (!$permission['allowed']) {
                wp_send_json_error([
                    'message' => $permission['message']
                ]);
            }

            // Get and sanitize POST data (only updatable fields)
            $data = [
                'label' => isset($_POST['label']) ? sanitize_text_field($_POST['label']) : '',
                'guard_class' => isset($_POST['guard_class']) ? sanitize_text_field($_POST['guard_class']) : '',
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

            // Get current transition for machine_id
            $current = $this->model->find($id);

            // Update transition
            $result = $this->model->update($id, $data);

            if ($result) {
                // Clear cache
                $this->cache->invalidateDataTableCache('transitions_list');
                $this->cache->delete('transitions_list');
                $this->cache->delete('transitions_count', 'total');
                $this->cache->delete('transition', $id);
                $this->cache->delete('transitions_by_machine', $current->machine_id);

                wp_send_json_success([
                    'message' => __('Transition updated successfully', 'wp-state-machine')
                ]);
            } else {
                wp_send_json_error([
                    'message' => __('Failed to update transition', 'wp-state-machine')
                ]);
            }

        } catch (\Exception $e) {
            error_log('Update Transition Error: ' . $e->getMessage());
            wp_send_json_error([
                'message' => __('An error occurred while updating the transition', 'wp-state-machine')
            ]);
        }
    }

    /**
     * Delete transition
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
                    'message' => __('Invalid transition ID', 'wp-state-machine')
                ]);
            }

            // Get transition before deletion for cache clearing
            $transition = $this->model->find($id);
            if (!$transition) {
                wp_send_json_error([
                    'message' => __('Transition not found', 'wp-state-machine')
                ]);
            }

            // Validate permission
            $permission = $this->validator->validatePermission($id, 'delete');
            if (!$permission['allowed']) {
                wp_send_json_error([
                    'message' => $permission['message']
                ]);
            }

            // Delete transition
            $result = $this->model->delete($id);

            if ($result) {
                // Clear cache
                $this->cache->invalidateDataTableCache('transitions_list');
                $this->cache->delete('transitions_list');
                $this->cache->delete('transitions_count', 'total');
                $this->cache->delete('transition', $id);
                $this->cache->delete('transitions_by_machine', $transition->machine_id);

                wp_send_json_success([
                    'message' => __('Transition deleted successfully', 'wp-state-machine')
                ]);
            } else {
                wp_send_json_error([
                    'message' => __('Failed to delete transition', 'wp-state-machine')
                ]);
            }

        } catch (\Exception $e) {
            error_log('Delete Transition Error: ' . $e->getMessage());
            wp_send_json_error([
                'message' => __('An error occurred while deleting the transition', 'wp-state-machine')
            ]);
        }
    }

    /**
     * Get single transition details
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
                    'message' => __('Invalid transition ID', 'wp-state-machine')
                ]);
            }

            // Validate permission
            $permission = $this->validator->validatePermission($id, 'view');
            if (!$permission['allowed']) {
                wp_send_json_error([
                    'message' => $permission['message']
                ]);
            }

            // Get transition
            $transition = $this->model->find($id);

            if ($transition) {
                wp_send_json_success([
                    'data' => $transition
                ]);
            } else {
                wp_send_json_error([
                    'message' => __('Transition not found', 'wp-state-machine')
                ]);
            }

        } catch (\Exception $e) {
            error_log('Show Transition Error: ' . $e->getMessage());
            wp_send_json_error([
                'message' => __('An error occurred while loading the transition', 'wp-state-machine')
            ]);
        }
    }

    /**
     * Generate action buttons for DataTable row
     *
     * @param object $transition Transition object
     * @return string HTML for action buttons
     */
    private function getActionButtons($transition) {
        $buttons = [];

        // View button
        if ($this->validator->canView($transition->id)) {
            $buttons[] = sprintf(
                '<button type="button" class="button button-small btn-view-transition" data-id="%d" title="%s">
                    <span class="dashicons dashicons-visibility"></span>
                </button>',
                $transition->id,
                esc_attr__('View', 'wp-state-machine')
            );
        }

        // Edit button
        if ($this->validator->canUpdate($transition->id)) {
            $buttons[] = sprintf(
                '<button type="button" class="button button-small btn-edit-transition" data-id="%d" title="%s">
                    <span class="dashicons dashicons-edit"></span>
                </button>',
                $transition->id,
                esc_attr__('Edit', 'wp-state-machine')
            );
        }

        // Delete button
        if ($this->validator->canDelete($transition->id)) {
            $buttons[] = sprintf(
                '<button type="button" class="button button-small btn-delete-transition" data-id="%d" title="%s">
                    <span class="dashicons dashicons-trash"></span>
                </button>',
                $transition->id,
                esc_attr__('Delete', 'wp-state-machine')
            );
        }

        return implode(' ', $buttons);
    }
}
