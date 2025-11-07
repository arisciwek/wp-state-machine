<?php
/**
 * Init Hooks Class
 *
 * @package     WP_State_Machine
 * @subpackage  Includes
 * @version     1.0.0
 * @author      arisciwek
 *
 * Path: /wp-state-machine/includes/class-init-hooks.php
 *
 * Description: Defines all hooks and filters needed by the plugin during
 *              initialization. Includes text domain loading, AJAX handlers,
 *              and WordPress action hooks.
 *
 * Changelog:
 * 1.0.0 - 2025-11-07
 * - Initial creation
 * - Added textdomain loading
 * - Added AJAX handler registration
 * - Follow wp-agency pattern
 */

defined('ABSPATH') || exit;

class WP_State_Machine_Init_Hooks {

    /**
     * Initialize all hooks
     *
     * @return void
     */
    public function init() {
        // Load text domain - must be first before anything else
        add_action('init', [$this, 'load_textdomain'], 1);

        // Register custom filters
        $this->register_filters();

        // Register custom actions
        $this->register_actions();
    }

    /**
     * Load plugin textdomain for i18n/l10n
     * Uses WP_STATE_MACHINE_PATH constant defined in main file
     *
     * @return void
     */
    public function load_textdomain() {
        $plugin_rel_path = dirname(plugin_basename(WP_STATE_MACHINE_PATH . 'wp-state-machine.php')) . '/languages';

        load_plugin_textdomain(
            'wp-state-machine',
            false,
            $plugin_rel_path
        );
    }

    /**
     * Register custom WordPress filters
     *
     * @return void
     */
    private function register_filters() {
        // Allow other plugins to register state machines
        // Example: add_filter('wp_state_machine_register_machines', ...);

        // Allow other plugins to register workflow groups
        // Example: add_filter('wp_state_machine_register_workflow_groups', ...);

        // Allow customization of transition validation
        // add_filter('wp_state_machine_can_transition', [$this, 'filter_can_transition'], 10, 4);
    }

    /**
     * Register custom WordPress actions
     *
     * @return void
     */
    private function register_actions() {
        // Allow other plugins to hook into state machine events
        // Fired before transition: do_action('wp_state_machine_before_transition', ...);
        // Fired after transition: do_action('wp_state_machine_after_transition', ...);
        // Fired on transition error: do_action('wp_state_machine_transition_error', ...);
    }
}
