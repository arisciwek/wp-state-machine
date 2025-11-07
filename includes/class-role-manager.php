<?php
/**
 * Role Manager Class
 *
 * @package     WP_State_Machine
 * @subpackage  Includes
 * @version     1.0.0
 * @author      arisciwek
 *
 * Path: /wp-state-machine/includes/class-role-manager.php
 *
 * Description: Placeholder for role management.
 *              WP State Machine does NOT create custom roles.
 *              It only adds capabilities to existing WordPress roles
 *              (primarily Administrator).
 *
 *              This file exists for:
 *              1. Pattern consistency with wp-agency
 *              2. Future extensibility if custom roles are needed
 *              3. Clear documentation that no custom roles are used
 *
 * Usage:
 * - State Machine capabilities are managed by PermissionModel
 * - No custom roles are created
 * - All permissions use existing WordPress roles
 *
 * Changelog:
 * 1.0.0 - 2025-11-07
 * - Initial creation
 * - Placeholder for future role management if needed
 * - Follow wp-agency pattern (file structure only)
 */

defined('ABSPATH') || exit;

class WP_State_Machine_Role_Manager {
    /**
     * Get all plugin-specific roles
     * Returns empty array as State Machine doesn't create custom roles
     *
     * @return array Empty array (no custom roles)
     */
    public static function getRoles(): array {
        // State Machine doesn't create custom roles
        // It uses capabilities on existing WordPress roles
        return [];
    }

    /**
     * Get role slugs
     * Returns empty array as State Machine doesn't create custom roles
     *
     * @return array Empty array (no custom roles)
     */
    public static function getRoleSlugs(): array {
        return array_keys(self::getRoles());
    }

    /**
     * Check if a role is managed by this plugin
     * Always returns false as State Machine doesn't manage roles
     *
     * @param string $role_slug Role slug to check
     * @return bool Always false (no custom roles)
     */
    public static function isPluginRole(string $role_slug): bool {
        return false;
    }

    /**
     * Check if a WordPress role exists
     *
     * @param string $role_slug Role slug to check
     * @return bool True if role exists in WordPress
     */
    public static function roleExists(string $role_slug): bool {
        return get_role($role_slug) !== null;
    }

    /**
     * Get display name for a role
     * Returns null as State Machine doesn't create custom roles
     *
     * @param string $role_slug Role slug
     * @return string|null Always null (no custom roles)
     */
    public static function getRoleName(string $role_slug): ?string {
        return null;
    }
}
