<?php
/**
 * Permission Model
 *
 * @package     WP_State_Machine
 * @subpackage  Models/Settings
 * @version     1.0.0
 * @author      arisciwek
 *
 * Path: /wp-state-machine/src/Models/Settings/PermissionModel.php
 *
 * Description: Manages capabilities for state machine plugin.
 *              Handles adding and removing capabilities from roles.
 *              Follows wp-agency pattern for separation of concerns.
 *
 * Capabilities:
 * - manage_state_machines  : Manage state machine definitions
 * - view_state_machines    : View state machines
 * - edit_state_machines    : Edit state machines
 * - delete_state_machines  : Delete state machines
 * - manage_transitions     : Manage transitions
 * - view_transition_logs   : View transition history
 *
 * Changelog:
 * 1.0.0 - 2025-11-07
 * - Initial creation
 * - Basic capability management
 */

namespace WPStateMachine\Models\Settings;

defined('ABSPATH') || exit;

class PermissionModel {
    /**
     * Get list of plugin capabilities
     *
     * @return array List of capabilities
     */
    public static function getCapabilities() {
        return [
            'manage_state_machines',
            'view_state_machines',
            'edit_state_machines',
            'delete_state_machines',
            'manage_transitions',
            'view_transition_logs',
        ];
    }

    /**
     * Add capabilities to administrator role
     *
     * @return void
     */
    public function addCapabilities() {
        $role = get_role('administrator');

        if (!$role) {
            error_log('[StateMachine PermissionModel] Administrator role not found');
            return;
        }

        $capabilities = self::getCapabilities();

        foreach ($capabilities as $cap) {
            $role->add_cap($cap);
        }

        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log('[StateMachine PermissionModel] Added ' . count($capabilities) . ' capabilities to administrator role');
        }
    }

    /**
     * Remove capabilities from all roles
     *
     * @return void
     */
    public function removeCapabilities() {
        global $wp_roles;

        if (!isset($wp_roles)) {
            $wp_roles = new \WP_Roles();
        }

        $capabilities = self::getCapabilities();
        $roles = $wp_roles->get_names();

        foreach ($roles as $role_name => $display_name) {
            $role = get_role($role_name);
            if ($role) {
                foreach ($capabilities as $cap) {
                    $role->remove_cap($cap);
                }
            }
        }

        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log('[StateMachine PermissionModel] Removed capabilities from all roles');
        }
    }

    /**
     * Check if user has capability
     *
     * @param int    $user_id User ID
     * @param string $capability Capability to check
     * @return bool True if user has capability
     */
    public static function userCan($user_id, $capability) {
        $user = get_user_by('id', $user_id);

        if (!$user) {
            return false;
        }

        return $user->has_cap($capability);
    }

    /**
     * Check if current user has capability
     *
     * @param string $capability Capability to check
     * @return bool True if current user has capability
     */
    public static function currentUserCan($capability) {
        return current_user_can($capability);
    }
}
