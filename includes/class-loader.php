<?php
/**
 * Loader Class
 *
 * @package     WP_State_Machine
 * @subpackage  Includes
 * @version     1.0.0
 * @author      arisciwek
 *
 * Path: /wp-state-machine/includes/class-loader.php
 *
 * Description: Handles loading dependencies, actions, and filters
 *              Registry pattern for managing WordPress hooks
 *
 * Changelog:
 * 1.0.0 - 2025-11-07
 * - Initial creation
 * - Added actions and filters registration
 * - Follow wp-agency pattern
 */

defined('ABSPATH') || exit;

class WP_State_Machine_Loader {
    /**
     * Array of actions registered with WordPress
     *
     * @var array
     */
    protected $actions;

    /**
     * Array of filters registered with WordPress
     *
     * @var array
     */
    protected $filters;

    /**
     * Constructor
     */
    public function __construct() {
        $this->actions = array();
        $this->filters = array();
    }

    /**
     * Add a new action to the collection
     *
     * @param string $hook          The name of the WordPress action
     * @param object $component     A reference to the instance of the object
     * @param string $callback      The name of the function definition on the $component
     * @param int    $priority      Optional. The priority for the hook. Default 10.
     * @param int    $accepted_args Optional. Number of args the callback accepts. Default 1.
     * @return void
     */
    public function add_action($hook, $component, $callback, $priority = 10, $accepted_args = 1) {
        $this->actions = $this->add($this->actions, $hook, $component, $callback, $priority, $accepted_args);
    }

    /**
     * Add a new filter to the collection
     *
     * @param string $hook          The name of the WordPress filter
     * @param object $component     A reference to the instance of the object
     * @param string $callback      The name of the function definition on the $component
     * @param int    $priority      Optional. The priority for the hook. Default 10.
     * @param int    $accepted_args Optional. Number of args the callback accepts. Default 1.
     * @return void
     */
    public function add_filter($hook, $component, $callback, $priority = 10, $accepted_args = 1) {
        $this->filters = $this->add($this->filters, $hook, $component, $callback, $priority, $accepted_args);
    }

    /**
     * Add a hook to the collection
     *
     * @param array  $hooks         The collection of hooks
     * @param string $hook          The name of the WordPress hook
     * @param object $component     A reference to the instance of the object
     * @param string $callback      The name of the function definition on the $component
     * @param int    $priority      The priority for the hook
     * @param int    $accepted_args Number of args the callback accepts
     * @return array The collection of hooks
     */
    private function add($hooks, $hook, $component, $callback, $priority, $accepted_args) {
        $hooks[] = array(
            'hook'          => $hook,
            'component'     => $component,
            'callback'      => $callback,
            'priority'      => $priority,
            'accepted_args' => $accepted_args
        );
        return $hooks;
    }

    /**
     * Register the filters and actions with WordPress
     *
     * @return void
     */
    public function run() {
        // Register all filters
        foreach ($this->filters as $hook) {
            add_filter(
                $hook['hook'],
                array($hook['component'], $hook['callback']),
                $hook['priority'],
                $hook['accepted_args']
            );
        }

        // Register all actions
        foreach ($this->actions as $hook) {
            add_action(
                $hook['hook'],
                array($hook['component'], $hook['callback']),
                $hook['priority'],
                $hook['accepted_args']
            );
        }
    }
}
