<?php
/**
 * Dependencies Handler Class
 *
 * @package     WP_State_Machine
 * @subpackage  Includes
 * @version     1.0.6
 * @author      arisciwek
 *
 * Path: /wp-state-machine/includes/class-dependencies.php
 *
 * Description: Menangani dependencies plugin seperti CSS, JavaScript,
 *              dan library eksternal untuk State Machine plugin
 *
 * Changelog:
 * 1.0.6 - 2025-11-08
 * - Added transitions page assets enqueuing
 * - Added localize_transitions_scripts() method
 * - Registered transitions.css and transitions.js
 * - Fixed: Refactored transitions view to follow clean architecture
 *
 * 1.0.5 - 2025-11-08
 * - Added states page assets enqueuing
 * - Added localize_states_scripts() method
 * - Registered states.css and states.js
 * - Fixed: Refactored states view to follow clean architecture
 *
 * 1.0.4 - 2025-11-08
 * - Added machines page assets enqueuing
 * - Added localize_machines_scripts() method
 * - Registered machines.css and machines.js
 * - Fixed: Moved wp_localize_script from view to dependencies class
 * 1.0.3 - 2025-11-07 (TODO-6102 PRIORITAS #8)
 * - Added settings page assets enqueuing
 * - Added localize_settings_scripts() method
 * - Registered settings.css and settings.js
 * - Fixed settings screen ID to match actual submenu
 * 1.0.2 - 2025-11-07 (TODO-6102 PRIORITAS #7)
 * - Added workflow groups page assets enqueuing
 * - Added localize_workflow_groups_scripts() method
 * - Registered workflow-groups.css and workflow-groups.js
 * 1.0.1 - 2025-11-07 (TODO-6104)
 * - Added logs page assets enqueuing
 * - Added localize_logs_scripts() method
 * - Registered transition-logs.css and transition-logs.js
 * 1.0.0 - 2025-11-07
 * - Initial creation
 * - Added asset enqueuing methods
 * - Added CDN dependencies
 * - Follow wp-agency pattern
 */

defined('ABSPATH') || exit;

class WP_State_Machine_Dependencies {
    /**
     * Plugin name
     *
     * @var string
     */
    private $plugin_name;

    /**
     * Plugin version
     *
     * @var string
     */
    private $version;

    /**
     * Constructor
     *
     * @param string $plugin_name Plugin name
     * @param string $version Plugin version
     */
    public function __construct($plugin_name, $version) {
        $this->plugin_name = $plugin_name;
        $this->version = $version;

        // Register hooks
        add_action('admin_enqueue_scripts', [$this, 'enqueue_styles']);
        add_action('admin_enqueue_scripts', [$this, 'enqueue_scripts']);
    }

    /**
     * Enqueue admin styles
     *
     * @return void
     */
    public function enqueue_styles() {
        $screen = get_current_screen();
        if (!$screen) {
            return;
        }

        // Main State Machine pages
        if (strpos($screen->id, 'wp-state-machine') !== false) {
            // DataTables CSS
            wp_enqueue_style(
                'datatables',
                'https://cdn.datatables.net/1.13.7/css/jquery.dataTables.min.css',
                [],
                '1.13.7'
            );

            // TODO: Enqueue plugin-specific admin styles when ready
            // wp_enqueue_style(
            //     'wp-state-machine-admin',
            //     WP_STATE_MACHINE_URL . 'assets/css/admin.css',
            //     [],
            //     $this->version
            // );

            // wp_enqueue_style(
            //     'wp-state-machine-toast',
            //     WP_STATE_MACHINE_URL . 'assets/css/toast.css',
            //     [],
            //     $this->version
            // );
        }

        // Machines page specific styles
        if ($screen->id === 'state-machines_page_wp-state-machine-machines') {
            wp_enqueue_style(
                'wp-state-machine-machines',
                WP_STATE_MACHINE_URL . 'assets/css/machines.css',
                [],
                $this->version
            );
        }

        // Workflow Groups page specific styles (now uses parent slug)
        if ($screen->id === 'toplevel_page_wp-state-machine') {
            wp_enqueue_style(
                'wp-state-machine-workflow-groups',
                WP_STATE_MACHINE_URL . 'assets/css/workflow-groups.css',
                [],
                $this->version
            );
        }

        // States page specific styles
        if ($screen->id === 'state-machines_page_wp-state-machine-states') {
            wp_enqueue_style(
                'wp-state-machine-states',
                WP_STATE_MACHINE_URL . 'assets/css/states.css',
                [],
                $this->version
            );
        }

        // Transitions page specific styles
        if ($screen->id === 'state-machines_page_wp-state-machine-transitions') {
            wp_enqueue_style(
                'wp-state-machine-transitions',
                WP_STATE_MACHINE_URL . 'assets/css/transitions.css',
                [],
                $this->version
            );
        }

        // Settings page specific styles
        if ($screen->id === 'state-machines_page_wp-state-machine-settings') {
            wp_enqueue_style(
                'wp-state-machine-settings',
                WP_STATE_MACHINE_URL . 'assets/css/settings.css',
                [],
                $this->version
            );
        }

        // Logs page specific styles
        if ($screen->id === 'state-machines_page_wp-state-machine-logs') {
            wp_enqueue_style(
                'wp-state-machine-transition-logs',
                WP_STATE_MACHINE_URL . 'assets/css/transition-logs.css',
                [],
                $this->version
            );
        }
    }

    /**
     * Enqueue admin scripts
     *
     * @return void
     */
    public function enqueue_scripts() {
        $screen = get_current_screen();
        if (!$screen) {
            return;
        }

        // Main State Machine pages
        if (strpos($screen->id, 'wp-state-machine') !== false) {
            // jQuery (if not already loaded)
            wp_enqueue_script('jquery');

            // DataTables JS
            wp_enqueue_script(
                'datatables',
                'https://cdn.datatables.net/1.13.7/js/jquery.dataTables.min.js',
                ['jquery'],
                '1.13.7',
                true
            );

            // jQuery Validation
            wp_enqueue_script(
                'jquery-validate',
                'https://cdn.jsdelivr.net/npm/jquery-validation@1.19.5/dist/jquery.validate.min.js',
                ['jquery'],
                '1.19.5',
                true
            );

            // TODO: Enqueue plugin-specific admin scripts when ready
            // wp_enqueue_script(
            //     'wp-state-machine-admin',
            //     WP_STATE_MACHINE_URL . 'assets/js/admin.js',
            //     ['jquery', 'datatables'],
            //     $this->version,
            //     true
            // );

            // Localize script with AJAX data
            $this->localize_admin_scripts();
        }

        // Settings page specific scripts
        if ($screen->id === 'state-machines_page_wp-state-machine-settings') {
            wp_enqueue_script(
                'wp-state-machine-settings',
                WP_STATE_MACHINE_URL . 'assets/js/settings.js',
                ['jquery'],
                $this->version,
                true
            );

            // Localize script for settings page
            $this->localize_settings_scripts();
        }

        // Workflow Groups page specific scripts (now uses parent slug - first position)
        if ($screen->id === 'toplevel_page_wp-state-machine') {
            wp_enqueue_script(
                'wp-state-machine-workflow-groups',
                WP_STATE_MACHINE_URL . 'assets/js/workflow-groups.js',
                ['jquery', 'datatables'],
                $this->version,
                true
            );

            // Localize script for workflow groups page
            $this->localize_workflow_groups_scripts();
        }

        // Machines page specific scripts (now uses different slug)
        if ($screen->id === 'state-machines_page_wp-state-machine-machines') {
            wp_enqueue_script(
                'wp-state-machine-machines',
                WP_STATE_MACHINE_URL . 'assets/js/machines.js',
                ['jquery', 'datatables'],
                $this->version,
                true
            );

            // Localize script for machines page
            $this->localize_machines_scripts();
        }

        // States page specific scripts
        if ($screen->id === 'state-machines_page_wp-state-machine-states') {
            wp_enqueue_script(
                'wp-state-machine-states',
                WP_STATE_MACHINE_URL . 'assets/js/states.js',
                ['jquery', 'datatables'],
                $this->version,
                true
            );

            // Localize script for states page
            $this->localize_states_scripts();
        }

        // Transitions page specific scripts
        if ($screen->id === 'state-machines_page_wp-state-machine-transitions') {
            wp_enqueue_script(
                'wp-state-machine-transitions',
                WP_STATE_MACHINE_URL . 'assets/js/transitions.js',
                ['jquery', 'datatables'],
                $this->version,
                true
            );

            // Localize script for transitions page
            $this->localize_transitions_scripts();
        }

        // Logs page specific scripts
        if ($screen->id === 'state-machines_page_wp-state-machine-logs') {
            // Register DataTables handle for dependencies
            wp_register_script(
                'wp-state-machine-datatables',
                'https://cdn.datatables.net/1.13.7/js/jquery.dataTables.min.js',
                ['jquery'],
                '1.13.7',
                true
            );

            wp_enqueue_script(
                'wp-state-machine-transition-logs',
                WP_STATE_MACHINE_URL . 'assets/js/transition-logs.js',
                ['jquery', 'wp-state-machine-datatables'],
                $this->version,
                true
            );

            // Localize script for logs page
            $this->localize_logs_scripts();
        }
    }

    /**
     * Localize admin scripts with data
     *
     * @return void
     */
    private function localize_admin_scripts() {
        // Prepare data for JavaScript
        $localize_data = [
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('wp_state_machine_nonce'),
            'i18n' => [
                'confirmDelete' => __('Are you sure you want to delete this item?', 'wp-state-machine'),
                'saving' => __('Saving...', 'wp-state-machine'),
                'save' => __('Save', 'wp-state-machine'),
                'cancel' => __('Cancel', 'wp-state-machine'),
                'error' => __('An error occurred. Please try again.', 'wp-state-machine'),
                'success' => __('Operation completed successfully.', 'wp-state-machine'),
                'loading' => __('Loading...', 'wp-state-machine'),
            ]
        ];

        // TODO: Uncomment when admin script is ready
        // wp_localize_script(
        //     'wp-state-machine-admin',
        //     'wpStateMachineData',
        //     $localize_data
        // );
    }

    /**
     * Localize scripts for logs page
     *
     * @return void
     */
    private function localize_logs_scripts() {
        $localize_data = [
            'nonce' => wp_create_nonce('wp_state_machine_nonce'),
            'i18n' => [
                'loadError' => __('Failed to load logs data. Please refresh the page.', 'wp-state-machine'),
                'dataTable' => [
                    'emptyTable' => __('No logs found', 'wp-state-machine'),
                    'info' => __('Showing _START_ to _END_ of _TOTAL_ entries', 'wp-state-machine'),
                    'infoEmpty' => __('Showing 0 to 0 of 0 entries', 'wp-state-machine'),
                    'infoFiltered' => __('(filtered from _MAX_ total entries)', 'wp-state-machine'),
                    'lengthMenu' => __('Show _MENU_ entries', 'wp-state-machine'),
                    'loadingRecords' => __('Loading...', 'wp-state-machine'),
                    'processing' => __('Processing...', 'wp-state-machine'),
                    'search' => __('Search:', 'wp-state-machine'),
                    'zeroRecords' => __('No matching records found', 'wp-state-machine'),
                    'paginate' => [
                        'first' => __('First', 'wp-state-machine'),
                        'last' => __('Last', 'wp-state-machine'),
                        'next' => __('Next', 'wp-state-machine'),
                        'previous' => __('Previous', 'wp-state-machine')
                    ]
                ]
            ]
        ];

        wp_localize_script(
            'wp-state-machine-transition-logs',
            'wpStateMachineLogsData',
            $localize_data
        );
    }

    /**
     * Localize scripts for workflow groups page
     *
     * @return void
     */
    private function localize_workflow_groups_scripts() {
        $localize_data = [
            'nonce' => wp_create_nonce('wp_state_machine_nonce'),
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'i18n' => [
                'addTitle' => __('Add New Workflow Group', 'wp-state-machine'),
                'editTitle' => __('Edit Workflow Group', 'wp-state-machine'),
                'confirmDelete' => __('Are you sure you want to delete this workflow group?', 'wp-state-machine'),
                'deleteError' => __('Cannot delete group. Please reassign or remove all machines first.', 'wp-state-machine'),
                'active' => __('Active', 'wp-state-machine'),
                'inactive' => __('Inactive', 'wp-state-machine'),
                'noMachines' => __('No machines assigned', 'wp-state-machine'),
                'emptyTable' => __('No workflow groups found', 'wp-state-machine'),
                'processing' => __('Processing...', 'wp-state-machine')
            ]
        ];

        wp_localize_script(
            'wp-state-machine-workflow-groups',
            'wpStateMachineWorkflowGroupsData',
            $localize_data
        );
    }

    /**
     * Localize scripts for settings page
     *
     * @return void
     */
    private function localize_settings_scripts() {
        $localize_data = [
            'nonce' => wp_create_nonce('wp_state_machine_nonce'),
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'i18n' => [
                'error' => __('An error occurred. Please try again.', 'wp-state-machine'),
                'success' => __('Settings saved successfully', 'wp-state-machine'),
                'confirmClearCache' => __('Are you sure you want to clear all cache?', 'wp-state-machine'),
                'confirmCleanupLogs' => __('Are you sure you want to delete old logs? This action cannot be undone.', 'wp-state-machine'),
                'entries' => __('entries', 'wp-state-machine'),
                'machines' => __('Machines', 'wp-state-machine'),
                'states' => __('States', 'wp-state-machine'),
                'transitions' => __('Transitions', 'wp-state-machine'),
                'logs' => __('Logs', 'wp-state-machine'),
                'workflowGroups' => __('Workflow Groups', 'wp-state-machine'),
                'databaseSize' => __('Database Size', 'wp-state-machine'),
            ]
        ];

        wp_localize_script(
            'wp-state-machine-settings',
            'wpStateMachineSettingsData',
            $localize_data
        );
    }

    /**
     * Localize scripts for machines page
     *
     * @return void
     */
    private function localize_machines_scripts() {
        $localize_data = [
            'nonce' => wp_create_nonce('wp_state_machine_nonce'),
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'i18n' => [
                'emptyTable' => __('No state machines found. Click "Add New State Machine" to create one.', 'wp-state-machine'),
                'processing' => __('Loading...', 'wp-state-machine'),
                'confirmDelete' => __('Are you sure you want to delete this state machine? This will also delete all associated states and transitions.', 'wp-state-machine'),
                'addTitle' => __('Add New State Machine', 'wp-state-machine'),
                'editTitle' => __('Edit State Machine', 'wp-state-machine'),
                'active' => __('Active', 'wp-state-machine'),
                'inactive' => __('Inactive', 'wp-state-machine'),
            ]
        ];

        wp_localize_script(
            'wp-state-machine-machines',
            'wpStateMachineMachinesData',
            $localize_data
        );
    }

    /**
     * Localize scripts for states page
     *
     * @return void
     */
    private function localize_states_scripts() {
        $localize_data = [
            'nonce' => wp_create_nonce('wp_state_machine_nonce'),
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'i18n' => [
                'emptyTable' => __('No states found. Select a machine or click "Add New State".', 'wp-state-machine'),
                'processing' => __('Loading...', 'wp-state-machine'),
                'confirmDelete' => __('Are you sure you want to delete this state?', 'wp-state-machine'),
                'addTitle' => __('Add New State', 'wp-state-machine'),
                'editTitle' => __('Edit State', 'wp-state-machine'),
            ]
        ];

        wp_localize_script(
            'wp-state-machine-states',
            'wpStateMachineStatesData',
            $localize_data
        );
    }

    /**
     * Localize scripts for transitions page
     *
     * @return void
     */
    private function localize_transitions_scripts() {
        $localize_data = [
            'nonce' => wp_create_nonce('wp_state_machine_nonce'),
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'i18n' => [
                'emptyTable' => __('No transitions found. Select a machine or click "Add New Transition".', 'wp-state-machine'),
                'processing' => __('Loading...', 'wp-state-machine'),
                'confirmDelete' => __('Are you sure you want to delete this transition?', 'wp-state-machine'),
                'addTitle' => __('Add New Transition', 'wp-state-machine'),
                'editTitle' => __('Edit Transition', 'wp-state-machine'),
                'selectMachine' => __('Select machine first', 'wp-state-machine'),
                'selectState' => __('Select state', 'wp-state-machine'),
            ]
        ];

        wp_localize_script(
            'wp-state-machine-transitions',
            'wpStateMachineTransitionsData',
            $localize_data
        );
    }

    /**
     * Enqueue frontend styles (if needed)
     *
     * @return void
     */
    public function enqueue_frontend_styles() {
        // Skip admin and AJAX requests
        if (is_admin() || wp_doing_ajax()) {
            return;
        }

        // TODO: Add frontend styles if needed for public-facing features
    }

    /**
     * Enqueue frontend scripts (if needed)
     *
     * @return void
     */
    public function enqueue_frontend_scripts() {
        // Skip admin and AJAX requests
        if (is_admin() || wp_doing_ajax()) {
            return;
        }

        // TODO: Add frontend scripts if needed for public-facing features
    }
}
