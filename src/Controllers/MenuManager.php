<?php
/**
 * File: MenuManager.php
 * Path: /wp-state-machine/src/Controllers/MenuManager.php
 *
 * @package     WP_State_Machine
 * @subpackage  Controllers
 * @version     1.0.0
 * @author      arisciwek
 *
 * Description: Manages admin menu registration for State Machine plugin
 *              Follows wp-agency MenuManager pattern
 *              Handles main menu and submenus with proper capabilities
 *
 * Changelog:
 * 1.0.0 - 2025-11-07
 * - Initial creation
 * - Main menu for State Machines
 * - Settings submenu
 * - Proper capability checking
 */

namespace WPStateMachine\Controllers;

defined('ABSPATH') || exit;

class MenuManager {
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
     * Settings controller instance
     *
     * @var SettingsController
     */
    private $settings_controller;

    /**
     * State Machine controller instance
     *
     * @var StateMachineController
     */
    private $state_machine_controller;

    /**
     * Constructor
     *
     * @param string $plugin_name Plugin name
     * @param string $version Plugin version
     */
    public function __construct($plugin_name, $version) {
        $this->plugin_name = $plugin_name;
        $this->version = $version;

        // Initialize controllers when they are created
        // For now, we'll use placeholder callbacks
        // $this->settings_controller = new SettingsController();
        // $this->state_machine_controller = new StateMachineController();
    }

    /**
     * Initialize menu manager
     *
     * @return void
     */
    public function init() {
        add_action('admin_menu', [$this, 'registerMenus']);

        // Initialize controllers
        // if ($this->settings_controller) {
        //     $this->settings_controller->init();
        // }
    }

    /**
     * Register admin menus
     *
     * @return void
     */
    public function registerMenus() {
        // Main menu: State Machines
        // Using view_state_machines capability so users with this permission can access
        $state_machine_hook = add_menu_page(
            __('State Machines', 'wp-state-machine'),
            __('State Machines', 'wp-state-machine'),
            'view_state_machines',
            'wp-state-machine',
            [$this, 'renderMainPage'],
            'dashicons-networking',
            58
        );

        // Submenu: Workflow Groups
        add_submenu_page(
            'wp-state-machine',
            __('Workflow Groups', 'wp-state-machine'),
            __('Workflow Groups', 'wp-state-machine'),
            'view_state_machines',
            'wp-state-machine-groups',
            [$this, 'renderGroupsPage']
        );

        // Submenu: State Machines (rename default submenu)
        add_submenu_page(
            'wp-state-machine',
            __('State Machines', 'wp-state-machine'),
            __('Machines', 'wp-state-machine'),
            'view_state_machines',
            'wp-state-machine',
            [$this, 'renderMainPage']
        );

        // Submenu: Transition Logs
        add_submenu_page(
            'wp-state-machine',
            __('Transition Logs', 'wp-state-machine'),
            __('Logs', 'wp-state-machine'),
            'view_transition_logs',
            'wp-state-machine-logs',
            [$this, 'renderLogsPage']
        );

        // Submenu: Settings - admin only
        add_submenu_page(
            'wp-state-machine',
            __('State Machine Settings', 'wp-state-machine'),
            __('Settings', 'wp-state-machine'),
            'manage_options',
            'wp-state-machine-settings',
            [$this, 'renderSettingsPage']
        );

        // Load admin scripts and styles for our pages
        add_action("admin_print_scripts-{$state_machine_hook}", [$this, 'enqueueAdminAssets']);
    }

    /**
     * Render main State Machines page
     *
     * @return void
     */
    public function renderMainPage() {
        echo '<div class="wrap">';
        echo '<h1>' . esc_html__('State Machines', 'wp-state-machine') . '</h1>';
        echo '<p>' . esc_html__('Manage your workflow state machines.', 'wp-state-machine') . '</p>';

        // TODO: Implement state machine list/management
        echo '<div class="notice notice-info">';
        echo '<p>' . esc_html__('State machine management interface will be implemented here.', 'wp-state-machine') . '</p>';
        echo '</div>';

        echo '</div>';
    }

    /**
     * Render Workflow Groups page
     *
     * @return void
     */
    public function renderGroupsPage() {
        echo '<div class="wrap">';
        echo '<h1>' . esc_html__('Workflow Groups', 'wp-state-machine') . '</h1>';
        echo '<p>' . esc_html__('Organize state machines into logical groups.', 'wp-state-machine') . '</p>';

        // TODO: Implement workflow groups management
        echo '<div class="notice notice-info">';
        echo '<p>' . esc_html__('Workflow groups management interface will be implemented here.', 'wp-state-machine') . '</p>';
        echo '</div>';

        echo '</div>';
    }

    /**
     * Render Transition Logs page
     *
     * @return void
     */
    public function renderLogsPage() {
        echo '<div class="wrap">';
        echo '<h1>' . esc_html__('Transition Logs', 'wp-state-machine') . '</h1>';
        echo '<p>' . esc_html__('View history of all state transitions.', 'wp-state-machine') . '</p>';

        // TODO: Implement transition logs display
        echo '<div class="notice notice-info">';
        echo '<p>' . esc_html__('Transition logs interface will be implemented here.', 'wp-state-machine') . '</p>';
        echo '</div>';

        echo '</div>';
    }

    /**
     * Render Settings page
     *
     * @return void
     */
    public function renderSettingsPage() {
        echo '<div class="wrap">';
        echo '<h1>' . esc_html__('State Machine Settings', 'wp-state-machine') . '</h1>';

        // TODO: Implement settings interface
        echo '<div class="notice notice-info">';
        echo '<p>' . esc_html__('Settings interface will be implemented here.', 'wp-state-machine') . '</p>';
        echo '</div>';

        echo '</div>';
    }

    /**
     * Enqueue admin assets
     *
     * @return void
     */
    public function enqueueAdminAssets() {
        // TODO: Enqueue admin CSS and JS when ready
        // wp_enqueue_style(
        //     'wp-state-machine-admin',
        //     WP_STATE_MACHINE_URL . 'assets/css/admin.css',
        //     [],
        //     $this->version
        // );

        // wp_enqueue_script(
        //     'wp-state-machine-admin',
        //     WP_STATE_MACHINE_URL . 'assets/js/admin.js',
        //     ['jquery'],
        //     $this->version,
        //     true
        // );
    }
}
