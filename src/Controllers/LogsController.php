<?php
/**
 * Logs Controller
 *
 * @package     WP_State_Machine
 * @subpackage  Controllers
 * @version     1.0.1
 * @author      arisciwek
 *
 * Path: /wp-state-machine/src/Controllers/LogsController.php
 *
 * Description: Controller untuk viewing transition logs dengan filtering.
 *              Supports per-plugin table queries dan central table.
 *              Read-only controller (no create/edit/delete).
 *              Standalone controller (no abstraction needed).
 *
 * Features:
 * - DataTables server-side processing
 * - Plugin filter dropdown
 * - Date range filtering
 * - Machine filtering
 * - User filtering
 * - Export to CSV
 * - Permission checks (view_state_machine_logs)
 *
 * Usage:
 * ```php
 * $logs_controller = new LogsController();
 * // AJAX handlers registered automatically
 * // Assets managed by class-dependencies.php
 * ```
 *
 * AJAX Actions:
 * - sm_logs_datatable    : Get logs for DataTable
 * - sm_logs_get_plugins  : Get available plugins for dropdown
 * - sm_logs_export       : Export logs to CSV
 *
 * Changelog:
 * 1.0.1 - 2025-11-07 (TODO-6104)
 * - Moved asset enqueuing to class-dependencies.php
 * - Removed enqueueAssets() method
 * - Assets now centrally managed
 * 1.0.0 - 2025-11-07 (TODO-6104)
 * - Initial creation for Prioritas #6
 * - DataTable handler with plugin filtering
 * - Multi-source log support (per-plugin + central)
 * - Export functionality
 * - Permission checks
 */

namespace WPStateMachine\Controllers;

use WPStateMachine\Models\TransitionLog\TransitionLogModel;

defined('ABSPATH') || exit;

class LogsController {

    /**
     * Cache of log model instances per plugin
     * @var array
     */
    private $log_models = [];

    /**
     * Constructor
     * Register AJAX handlers
     */
    public function __construct() {
        $this->registerAjaxHandlers();
    }

    /**
     * Register AJAX handlers
     * @return void
     */
    private function registerAjaxHandlers() {
        add_action('wp_ajax_sm_logs_datatable', [$this, 'handleDataTableRequest']);
        add_action('wp_ajax_sm_logs_get_plugins', [$this, 'handleGetPlugins']);
        add_action('wp_ajax_sm_logs_export', [$this, 'handleExport']);
    }

    /**
     * Get log model for plugin or central
     * Uses caching to avoid multiple instantiations
     *
     * @param string|null $plugin_slug Plugin slug or null for central
     * @return TransitionLogModel
     */
    private function getLogModel($plugin_slug = null) {
        $key = $plugin_slug ?? 'central';

        if (!isset($this->log_models[$key])) {
            $this->log_models[$key] = new TransitionLogModel($plugin_slug);
        }

        return $this->log_models[$key];
    }

    /**
     * Handle DataTable AJAX request
     * @return void
     */
    public function handleDataTableRequest() {
        check_ajax_referer('wp_state_machine_nonce', 'nonce');

        // Permission check
        if (!current_user_can('view_state_machine_logs')) {
            wp_send_json_error(['message' => __('Access denied', 'wp-state-machine')]);
        }

        try {
            // Get parameters
            $plugin_slug = sanitize_text_field($_POST['plugin_slug'] ?? '');
            $plugin_slug = ($plugin_slug === 'all' || $plugin_slug === '') ? null : $plugin_slug;

            // Get appropriate log model
            $log_model = $this->getLogModel($plugin_slug);

            // Get DataTable parameters
            $params = $this->getDataTableParams();

            // Get logs with pagination
            $result = $this->getLogsForDataTable($log_model, $params);

            wp_send_json_success($result);

        } catch (\Exception $e) {
            wp_send_json_error([
                'message' => __('Failed to load logs', 'wp-state-machine'),
                'error' => $e->getMessage()
            ]);
        }
    }

    /**
     * Get logs for DataTable with pagination
     *
     * @param TransitionLogModel $log_model Log model instance
     * @param array $params Query parameters
     * @return array DataTable formatted data
     */
    private function getLogsForDataTable($log_model, $params) {
        global $wpdb;

        $log_table = $log_model->getTableName();
        $state_table = $wpdb->prefix . 'app_sm_states';
        $machine_table = $wpdb->prefix . 'app_sm_machines';
        $user_table = $wpdb->prefix . 'users';

        // Build WHERE clause
        $where_clauses = ['1=1'];
        $where_values = [];

        // Machine filter
        if (!empty($params['machine_id'])) {
            $where_clauses[] = 'l.machine_id = %d';
            $where_values[] = $params['machine_id'];
        }

        // Date range filter
        if (!empty($params['date_from'])) {
            $where_clauses[] = 'l.created_at >= %s';
            $where_values[] = $params['date_from'] . ' 00:00:00';
        }
        if (!empty($params['date_to'])) {
            $where_clauses[] = 'l.created_at <= %s';
            $where_values[] = $params['date_to'] . ' 23:59:59';
        }

        // User filter
        if (!empty($params['user_id'])) {
            $where_clauses[] = 'l.user_id = %d';
            $where_values[] = $params['user_id'];
        }

        // Search filter
        if (!empty($params['search'])) {
            $where_clauses[] = '(m.name LIKE %s OR l.entity_type LIKE %s OR l.comment LIKE %s OR u.display_name LIKE %s)';
            $search_term = '%' . $wpdb->esc_like($params['search']) . '%';
            $where_values[] = $search_term;
            $where_values[] = $search_term;
            $where_values[] = $search_term;
            $where_values[] = $search_term;
        }

        $where_sql = implode(' AND ', $where_clauses);

        // Count total records
        $count_sql = "SELECT COUNT(*)
                      FROM {$log_table} l
                      LEFT JOIN {$machine_table} m ON l.machine_id = m.id
                      LEFT JOIN {$user_table} u ON l.user_id = u.ID
                      WHERE {$where_sql}";

        if (!empty($where_values)) {
            $count_sql = $wpdb->prepare($count_sql, $where_values);
        }
        $total_records = (int) $wpdb->get_var($count_sql);

        // Get paginated data
        $data_sql = "SELECT l.*,
                            fs.name as from_state_name, fs.slug as from_state_slug, fs.color as from_state_color,
                            ts.name as to_state_name, ts.slug as to_state_slug, ts.color as to_state_color,
                            m.name as machine_name, m.slug as machine_slug,
                            u.display_name as user_name
                     FROM {$log_table} l
                     LEFT JOIN {$state_table} fs ON l.from_state_id = fs.id
                     LEFT JOIN {$state_table} ts ON l.to_state_id = ts.id
                     LEFT JOIN {$machine_table} m ON l.machine_id = m.id
                     LEFT JOIN {$user_table} u ON l.user_id = u.ID
                     WHERE {$where_sql}
                     ORDER BY l.created_at DESC
                     LIMIT %d OFFSET %d";

        $data_values = array_merge($where_values, [$params['length'], $params['start']]);
        $data_sql = $wpdb->prepare($data_sql, $data_values);
        $logs = $wpdb->get_results($data_sql);

        // Process metadata
        foreach ($logs as $log) {
            if (!empty($log->metadata)) {
                $log->metadata = json_decode($log->metadata, true);
            }
        }

        return [
            'draw' => $params['draw'],
            'recordsTotal' => $total_records,
            'recordsFiltered' => $total_records,
            'data' => $logs
        ];
    }

    /**
     * Get plugins with state machines
     * For filter dropdown
     * @return void
     */
    public function handleGetPlugins() {
        check_ajax_referer('wp_state_machine_nonce', 'nonce');

        if (!current_user_can('view_state_machine_logs')) {
            wp_send_json_error(['message' => __('Access denied', 'wp-state-machine')]);
        }

        try {
            global $wpdb;
            $machines_table = $wpdb->prefix . 'app_sm_machines';

            // Get distinct plugin slugs with machine count
            $plugins = $wpdb->get_results(
                "SELECT DISTINCT plugin_slug,
                        COUNT(*) as machine_count,
                        GROUP_CONCAT(DISTINCT name ORDER BY name SEPARATOR ', ') as machines
                 FROM {$machines_table}
                 GROUP BY plugin_slug
                 ORDER BY plugin_slug"
            );

            wp_send_json_success($plugins);

        } catch (\Exception $e) {
            wp_send_json_error([
                'message' => __('Failed to load plugins', 'wp-state-machine'),
                'error' => $e->getMessage()
            ]);
        }
    }

    /**
     * Handle export request
     * @return void
     */
    public function handleExport() {
        check_ajax_referer('wp_state_machine_nonce', 'nonce');

        if (!current_user_can('view_state_machine_logs')) {
            wp_die(__('Access denied', 'wp-state-machine'));
        }

        try {
            // Get parameters
            $plugin_slug = sanitize_text_field($_GET['plugin_slug'] ?? '');
            $plugin_slug = ($plugin_slug === 'all' || $plugin_slug === '') ? null : $plugin_slug;

            // Get log model
            $log_model = $this->getLogModel($plugin_slug);

            // Get all logs (no pagination for export)
            $params = [
                'machine_id' => intval($_GET['machine_id'] ?? 0),
                'date_from' => sanitize_text_field($_GET['date_from'] ?? ''),
                'date_to' => sanitize_text_field($_GET['date_to'] ?? ''),
                'user_id' => intval($_GET['user_id'] ?? 0)
            ];

            $logs = $this->getLogsForExport($log_model, $params);

            // Export as CSV
            $this->exportAsCSV($logs, $plugin_slug);

        } catch (\Exception $e) {
            wp_die(
                sprintf(__('Export failed: %s', 'wp-state-machine'), $e->getMessage())
            );
        }
    }

    /**
     * Get logs for export (no pagination)
     *
     * @param TransitionLogModel $log_model Log model instance
     * @param array $params Query parameters
     * @return array Log entries
     */
    private function getLogsForExport($log_model, $params) {
        global $wpdb;

        $log_table = $log_model->getTableName();
        $state_table = $wpdb->prefix . 'app_sm_states';
        $machine_table = $wpdb->prefix . 'app_sm_machines';
        $user_table = $wpdb->prefix . 'users';

        // Build WHERE clause
        $where_clauses = ['1=1'];
        $where_values = [];

        if (!empty($params['machine_id'])) {
            $where_clauses[] = 'l.machine_id = %d';
            $where_values[] = $params['machine_id'];
        }

        if (!empty($params['date_from'])) {
            $where_clauses[] = 'l.created_at >= %s';
            $where_values[] = $params['date_from'] . ' 00:00:00';
        }

        if (!empty($params['date_to'])) {
            $where_clauses[] = 'l.created_at <= %s';
            $where_values[] = $params['date_to'] . ' 23:59:59';
        }

        if (!empty($params['user_id'])) {
            $where_clauses[] = 'l.user_id = %d';
            $where_values[] = $params['user_id'];
        }

        $where_sql = implode(' AND ', $where_clauses);

        // Get all matching logs
        $sql = "SELECT l.*,
                       fs.name as from_state_name,
                       ts.name as to_state_name,
                       m.name as machine_name,
                       u.display_name as user_name
                FROM {$log_table} l
                LEFT JOIN {$state_table} fs ON l.from_state_id = fs.id
                LEFT JOIN {$state_table} ts ON l.to_state_id = ts.id
                LEFT JOIN {$machine_table} m ON l.machine_id = m.id
                LEFT JOIN {$user_table} u ON l.user_id = u.ID
                WHERE {$where_sql}
                ORDER BY l.created_at DESC";

        if (!empty($where_values)) {
            $sql = $wpdb->prepare($sql, $where_values);
        }

        return $wpdb->get_results($sql);
    }

    /**
     * Export logs as CSV
     *
     * @param array $logs Log entries
     * @param string|null $plugin_slug Plugin slug for filename
     * @return void
     */
    private function exportAsCSV($logs, $plugin_slug) {
        $filename = $plugin_slug
            ? "logs-{$plugin_slug}-" . date('Y-m-d') . '.csv'
            : "logs-all-" . date('Y-m-d') . '.csv';

        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . $filename . '"');

        $output = fopen('php://output', 'w');

        // UTF-8 BOM for Excel compatibility
        fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));

        // Headers
        fputcsv($output, [
            'ID',
            'Date/Time',
            'Machine',
            'Entity Type',
            'Entity ID',
            'From State',
            'To State',
            'User',
            'Comment'
        ]);

        // Data
        foreach ($logs as $log) {
            fputcsv($output, [
                $log->id,
                $log->created_at,
                $log->machine_name,
                $log->entity_type,
                $log->entity_id,
                $log->from_state_name ?? 'Initial',
                $log->to_state_name,
                $log->user_name,
                $log->comment ?? ''
            ]);
        }

        fclose($output);
        exit;
    }

    /**
     * Get DataTable parameters from POST
     * @return array Parameters
     */
    private function getDataTableParams() {
        return [
            'draw' => intval($_POST['draw'] ?? 1),
            'start' => intval($_POST['start'] ?? 0),
            'length' => intval($_POST['length'] ?? 10),
            'search' => sanitize_text_field($_POST['search']['value'] ?? ''),
            'machine_id' => intval($_POST['machine_id'] ?? 0),
            'date_from' => sanitize_text_field($_POST['date_from'] ?? ''),
            'date_to' => sanitize_text_field($_POST['date_to'] ?? ''),
            'user_id' => intval($_POST['user_id'] ?? 0),
        ];
    }

    /**
     * Render logs page
     * Called by MenuManager
     * @return void
     */
    public function renderPage() {
        // Check permission
        if (!current_user_can('view_state_machine_logs')) {
            wp_die(__('Access denied', 'wp-state-machine'));
        }

        // Assets enqueued by class-dependencies.php

        // Load view
        $view_file = WP_STATE_MACHINE_PATH . 'src/Views/admin/logs/transition-logs-view.php';
        if (file_exists($view_file)) {
            include $view_file;
        } else {
            echo '<div class="wrap"><h1>' . __('Transition Logs', 'wp-state-machine') . '</h1>';
            echo '<p>' . __('View file not found', 'wp-state-machine') . '</p></div>';
        }
    }
}
