<?php
/**
 * Dependencies Handler Class
 *
 * @package     WP_State_Machine
 * @subpackage  Includes
 * @version     1.0.0
 * @author      arisciwek
 *
 * Path: /wp-state-machine/includes/class-dependencies.php
 *
 * Description: Menangani dependencies plugin seperti CSS, JavaScript,
 *              dan library eksternal untuk State Machine plugin
 *
 * Changelog:
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

        // Settings page specific styles
        if ($screen->id === 'wp-state-machine_page_wp-state-machine-settings') {
            // wp_enqueue_style(
            //     'wp-state-machine-settings',
            //     WP_STATE_MACHINE_URL . 'assets/css/settings.css',
            //     [],
            //     $this->version
            // );
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
        if ($screen->id === 'wp-state-machine_page_wp-state-machine-settings') {
            // wp_enqueue_script(
            //     'wp-state-machine-settings',
            //     WP_STATE_MACHINE_URL . 'assets/js/settings.js',
            //     ['jquery'],
            //     $this->version,
            //     true
            // );
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
