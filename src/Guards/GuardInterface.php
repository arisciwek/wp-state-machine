<?php
/**
 * Guard Interface
 *
 * @package     WP_State_Machine
 * @subpackage  Guards
 * @version     1.0.0
 * @author      arisciwek
 *
 * Path: /wp-state-machine/src/Guards/GuardInterface.php
 *
 * Description: Defines contract for transition guards.
 *              Guards control who can execute transitions.
 *              Provides consistent interface for all guard types.
 *
 * Guard Types:
 * - RoleGuard: Check user roles (administrator, editor, etc.)
 * - CapabilityGuard: Check user capabilities (manage_options, edit_posts, etc.)
 * - OwnerGuard: Check entity ownership
 * - CallbackGuard: Custom validation logic
 *
 * Usage:
 * ```php
 * // In transition configuration:
 * $transition->guard_class = 'RoleGuard:administrator,editor';
 *
 * // Guard execution:
 * $guard = GuardFactory::create($transition->guard_class);
 * $result = $guard->check($entity_id, $user_id, $context);
 *
 * if ($result['allowed']) {
 *     // Execute transition
 * } else {
 *     // Show $result['message']
 * }
 * ```
 *
 * Benefits:
 * - Consistent interface across all guard types
 * - Structured return format with clear messages
 * - Easy to extend with custom guards
 * - Plugin developers can create their own guards
 *
 * Changelog:
 * 1.0.0 - 2025-11-07
 * - Initial creation for Prioritas #4
 * - Define guard contract
 * - Support for configuration parameters
 * - Structured result format
 */

namespace WPStateMachine\Guards;

defined('ABSPATH') || exit;

interface GuardInterface {

    /**
     * Check if transition is allowed
     *
     * This is the core method that determines if a transition can be executed.
     * All guards must implement this method.
     *
     * @param int $entity_id    The entity being transitioned
     * @param int $user_id      The user attempting the transition
     * @param array $context    Additional context (transition data, machine data, etc.)
     * @return array Result with structure:
     *               [
     *                   'allowed' => bool,      // Whether transition is allowed
     *                   'message' => string,    // Human-readable reason
     *                   'code' => string,       // Machine-readable error code
     *                   'data' => array         // Additional data for debugging
     *               ]
     *
     * @example
     * ```php
     * $result = $guard->check(123, 45, [
     *     'transition' => $transition_obj,
     *     'machine' => $machine_obj,
     *     'entity_data' => ['status' => 'pending']
     * ]);
     *
     * if (!$result['allowed']) {
     *     wp_send_json_error(['message' => $result['message']]);
     * }
     * ```
     */
    public function check(int $entity_id, int $user_id, array $context = []): array;

    /**
     * Get guard name
     *
     * Returns human-readable name for this guard type.
     * Used in admin UI and error messages.
     *
     * @return string Guard name (e.g., "Role Guard", "Capability Guard")
     *
     * @example
     * ```php
     * echo $guard->getName(); // "Role Guard"
     * ```
     */
    public function getName(): string;

    /**
     * Get guard description
     *
     * Returns detailed description of what this guard checks.
     * Used in admin UI for documentation.
     *
     * @return string Guard description
     *
     * @example
     * ```php
     * echo $guard->getDescription();
     * // "Checks if user has one of the required roles"
     * ```
     */
    public function getDescription(): string;

    /**
     * Set configuration parameters
     *
     * Guards can accept configuration parameters from the guard_class string.
     * Format: "GuardClass:param1,param2,param3"
     *
     * @param array $config Configuration parameters
     * @return self For method chaining
     *
     * @example
     * ```php
     * // From "RoleGuard:administrator,editor"
     * $guard->setConfig(['administrator', 'editor']);
     *
     * // From "OwnerGuard:author_id"
     * $guard->setConfig(['field' => 'author_id']);
     * ```
     */
    public function setConfig(array $config): self;

    /**
     * Get configuration parameters
     *
     * Returns current configuration for debugging and logging.
     *
     * @return array Configuration parameters
     *
     * @example
     * ```php
     * $config = $guard->getConfig();
     * // ['administrator', 'editor']
     * ```
     */
    public function getConfig(): array;

    /**
     * Validate configuration
     *
     * Checks if the provided configuration is valid for this guard.
     * Called during setup to catch configuration errors early.
     *
     * @param array $config Configuration to validate
     * @return array Validation errors (empty if valid)
     *
     * @example
     * ```php
     * $errors = $guard->validateConfig(['invalid_role']);
     * if (!empty($errors)) {
     *     // Handle configuration errors
     * }
     * ```
     */
    public function validateConfig(array $config): array;
}
