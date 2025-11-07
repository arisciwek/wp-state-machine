<?php
/**
 * Role Guard
 *
 * @package     WP_State_Machine
 * @subpackage  Guards
 * @version     1.0.0
 * @author      arisciwek
 *
 * Path: /wp-state-machine/src/Guards/RoleGuard.php
 *
 * Description: Checks if user has required WordPress role(s).
 *              Supports multiple roles (OR logic).
 *              Works with both default and custom roles.
 *
 * Configuration Format:
 * ```
 * "RoleGuard:administrator,editor"
 * ```
 *
 * Usage Examples:
 *
 * Example 1: Allow only administrators
 * ```php
 * $transition->guard_class = 'RoleGuard:administrator';
 * ```
 *
 * Example 2: Allow administrators or editors
 * ```php
 * $transition->guard_class = 'RoleGuard:administrator,editor';
 * ```
 *
 * Example 3: Allow any user with author role or higher
 * ```php
 * $transition->guard_class = 'RoleGuard:administrator,editor,author';
 * ```
 *
 * Example 4: Custom role
 * ```php
 * $transition->guard_class = 'RoleGuard:shop_manager,product_manager';
 * ```
 *
 * Default WordPress Roles:
 * - administrator: Full access
 * - editor: Publish and manage posts
 * - author: Publish and manage own posts
 * - contributor: Write and manage own posts (can't publish)
 * - subscriber: Read-only access
 *
 * Return Structure:
 * ```php
 * [
 *     'allowed' => true|false,
 *     'message' => 'Success or failure message',
 *     'code' => 'success|insufficient_role|invalid_user',
 *     'data' => [
 *         'user_roles' => ['administrator'],
 *         'required_roles' => ['administrator', 'editor'],
 *         'matched_role' => 'administrator'
 *     ]
 * ]
 * ```
 *
 * Benefits:
 * - Simple role-based access control
 * - Supports multiple roles (OR logic)
 * - Works with custom roles
 * - Clear error messages
 * - Debugging data included
 *
 * Changelog:
 * 1.0.0 - 2025-11-07
 * - Initial creation for Prioritas #4
 * - Multiple role support
 * - Custom role support
 * - Detailed result data
 */

namespace WPStateMachine\Guards;

defined('ABSPATH') || exit;

class RoleGuard extends AbstractGuard {

    /**
     * Check if user has required role(s)
     *
     * @param int $entity_id Entity being transitioned (not used in role checks)
     * @param int $user_id User attempting transition
     * @param array $context Additional context (not used in role checks)
     * @return array Result array
     */
    public function check(int $entity_id, int $user_id, array $context = []): array {
        // Validate user exists
        if (!$this->userExists($user_id)) {
            return $this->failure(
                __('Invalid user', 'wp-state-machine'),
                'invalid_user',
                ['user_id' => $user_id]
            );
        }

        // Get user object
        $user = $this->getUser($user_id);
        if (!$user) {
            return $this->failure(
                __('Could not load user data', 'wp-state-machine'),
                'user_load_failed',
                ['user_id' => $user_id]
            );
        }

        // Get user roles
        $user_roles = (array) $user->roles;

        // Get required roles from config
        $required_roles = $this->getRequiredRoles();

        // Check if user has any of the required roles
        $matched_roles = array_intersect($user_roles, $required_roles);

        if (!empty($matched_roles)) {
            return $this->success(
                sprintf(
                    __('User has required role: %s', 'wp-state-machine'),
                    implode(', ', $matched_roles)
                ),
                [
                    'user_roles' => $user_roles,
                    'required_roles' => $required_roles,
                    'matched_roles' => $matched_roles
                ]
            );
        }

        // User doesn't have required role
        return $this->failure(
            sprintf(
                __('User does not have required role. Required: %s. User has: %s', 'wp-state-machine'),
                implode(', ', $required_roles),
                empty($user_roles) ? __('none', 'wp-state-machine') : implode(', ', $user_roles)
            ),
            'insufficient_role',
            [
                'user_roles' => $user_roles,
                'required_roles' => $required_roles
            ]
        );
    }

    /**
     * Get guard name
     *
     * @return string
     */
    public function getName(): string {
        return __('Role Guard', 'wp-state-machine');
    }

    /**
     * Get guard description
     *
     * @return string
     */
    public function getDescription(): string {
        return __('Checks if user has one of the required WordPress roles', 'wp-state-machine');
    }

    /**
     * Validate configuration
     *
     * @param array $config Configuration to validate
     * @return array Validation errors
     */
    public function validateConfig(array $config): array {
        $errors = [];

        // Config should be array of role names
        if (empty($config)) {
            $errors[] = __('At least one role must be specified', 'wp-state-machine');
            return $errors;
        }

        // Validate each role
        $all_roles = $this->getAllRoles();
        foreach ($config as $role) {
            if (!is_string($role)) {
                $errors[] = sprintf(
                    __('Invalid role format: %s', 'wp-state-machine'),
                    gettype($role)
                );
                continue;
            }

            // Warn if role doesn't exist (but don't fail - might be custom role added later)
            if (!isset($all_roles[$role])) {
                $errors[] = sprintf(
                    __('Warning: Role "%s" does not exist (might be custom role)', 'wp-state-machine'),
                    $role
                );
            }
        }

        return $errors;
    }

    // ========================================
    // HELPER METHODS
    // ========================================

    /**
     * Get required roles from config
     *
     * @return array Required role names
     */
    protected function getRequiredRoles(): array {
        if (empty($this->config)) {
            return [];
        }

        // Config is array of role names
        return array_map('trim', $this->config);
    }

    /**
     * Get all available WordPress roles
     *
     * @return array Role name => Role object
     */
    protected function getAllRoles(): array {
        global $wp_roles;

        if (!isset($wp_roles)) {
            $wp_roles = new \WP_Roles();
        }

        return $wp_roles->roles;
    }

    /**
     * Check if role exists
     *
     * @param string $role Role name
     * @return bool True if role exists
     */
    protected function roleExists(string $role): bool {
        $all_roles = $this->getAllRoles();
        return isset($all_roles[$role]);
    }
}
