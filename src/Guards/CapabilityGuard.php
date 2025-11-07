<?php
/**
 * Capability Guard
 *
 * @package     WP_State_Machine
 * @subpackage  Guards
 * @version     1.0.0
 * @author      arisciwek
 *
 * Path: /wp-state-machine/src/Guards/CapabilityGuard.php
 *
 * Description: Checks if user has required WordPress capability/capabilities.
 *              More granular than role checking.
 *              Supports multiple capabilities (OR logic).
 *
 * Configuration Format:
 * ```
 * "CapabilityGuard:manage_options,edit_posts"
 * ```
 *
 * Usage Examples:
 *
 * Example 1: Require manage_options capability
 * ```php
 * $transition->guard_class = 'CapabilityGuard:manage_options';
 * ```
 *
 * Example 2: Allow users with edit_posts OR publish_posts
 * ```php
 * $transition->guard_class = 'CapabilityGuard:edit_posts,publish_posts';
 * ```
 *
 * Example 3: Custom capability
 * ```php
 * $transition->guard_class = 'CapabilityGuard:manage_state_machines';
 * ```
 *
 * Example 4: Multiple custom capabilities
 * ```php
 * $transition->guard_class = 'CapabilityGuard:approve_orders,manage_inventory';
 * ```
 *
 * Common WordPress Capabilities:
 * - manage_options: Admin settings access
 * - edit_posts: Edit posts
 * - publish_posts: Publish posts
 * - delete_posts: Delete posts
 * - edit_pages: Edit pages
 * - manage_categories: Manage categories
 * - moderate_comments: Moderate comments
 * - upload_files: Upload media
 * - edit_users: Edit users
 * - delete_users: Delete users
 *
 * Difference from RoleGuard:
 * - RoleGuard: Checks user's assigned role (administrator, editor, etc.)
 * - CapabilityGuard: Checks specific permissions (manage_options, edit_posts, etc.)
 * - CapabilityGuard is more flexible and granular
 *
 * Return Structure:
 * ```php
 * [
 *     'allowed' => true|false,
 *     'message' => 'Success or failure message',
 *     'code' => 'success|insufficient_capability|invalid_user',
 *     'data' => [
 *         'required_capabilities' => ['manage_options', 'edit_posts'],
 *         'matched_capability' => 'manage_options',
 *         'user_id' => 123
 *     ]
 * ]
 * ```
 *
 * Benefits:
 * - Granular permission control
 * - More flexible than role-based checks
 * - Works with custom capabilities
 * - Supports capability plugins (e.g., Members, User Role Editor)
 * - Clear error messages
 *
 * Changelog:
 * 1.0.0 - 2025-11-07
 * - Initial creation for Prioritas #4
 * - Multiple capability support (OR logic)
 * - Custom capability support
 * - Detailed result data
 */

namespace WPStateMachine\Guards;

defined('ABSPATH') || exit;

class CapabilityGuard extends AbstractGuard {

    /**
     * Check if user has required capability/capabilities
     *
     * @param int $entity_id Entity being transitioned (can be used for object-specific caps)
     * @param int $user_id User attempting transition
     * @param array $context Additional context (not used in basic capability checks)
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

        // Get required capabilities from config
        $required_capabilities = $this->getRequiredCapabilities();

        if (empty($required_capabilities)) {
            return $this->failure(
                __('No capabilities configured for this guard', 'wp-state-machine'),
                'no_capabilities_configured',
                []
            );
        }

        // Check if user has any of the required capabilities
        $matched_capability = null;
        foreach ($required_capabilities as $capability) {
            if ($user->has_cap($capability)) {
                $matched_capability = $capability;
                break;
            }
        }

        if ($matched_capability) {
            return $this->success(
                sprintf(
                    __('User has required capability: %s', 'wp-state-machine'),
                    $matched_capability
                ),
                [
                    'required_capabilities' => $required_capabilities,
                    'matched_capability' => $matched_capability,
                    'user_id' => $user_id
                ]
            );
        }

        // User doesn't have required capability
        return $this->failure(
            sprintf(
                __('User does not have required capability. Required: %s', 'wp-state-machine'),
                implode(', ', $required_capabilities)
            ),
            'insufficient_capability',
            [
                'required_capabilities' => $required_capabilities,
                'user_id' => $user_id
            ]
        );
    }

    /**
     * Get guard name
     *
     * @return string
     */
    public function getName(): string {
        return __('Capability Guard', 'wp-state-machine');
    }

    /**
     * Get guard description
     *
     * @return string
     */
    public function getDescription(): string {
        return __('Checks if user has one of the required WordPress capabilities', 'wp-state-machine');
    }

    /**
     * Validate configuration
     *
     * @param array $config Configuration to validate
     * @return array Validation errors
     */
    public function validateConfig(array $config): array {
        $errors = [];

        // Config should be array of capability names
        if (empty($config)) {
            $errors[] = __('At least one capability must be specified', 'wp-state-machine');
            return $errors;
        }

        // Validate each capability
        foreach ($config as $capability) {
            if (!is_string($capability)) {
                $errors[] = sprintf(
                    __('Invalid capability format: %s', 'wp-state-machine'),
                    gettype($capability)
                );
                continue;
            }

            // Check for empty capability
            if (trim($capability) === '') {
                $errors[] = __('Capability name cannot be empty', 'wp-state-machine');
            }

            // Note: We don't validate if capability exists because:
            // 1. Custom capabilities can be added by plugins
            // 2. WordPress doesn't provide a reliable way to enumerate all capabilities
            // 3. Capability might be added later by another plugin
        }

        return $errors;
    }

    // ========================================
    // HELPER METHODS
    // ========================================

    /**
     * Get required capabilities from config
     *
     * @return array Required capability names
     */
    protected function getRequiredCapabilities(): array {
        if (empty($this->config)) {
            return [];
        }

        // Config is array of capability names
        return array_map('trim', $this->config);
    }

    /**
     * Check if user has all capabilities (AND logic)
     * Alternative method for stricter checking
     *
     * @param int $user_id User ID
     * @param array $capabilities Capabilities to check
     * @return bool True if user has all capabilities
     */
    protected function userHasAllCapabilities(int $user_id, array $capabilities): bool {
        $user = $this->getUser($user_id);
        if (!$user) {
            return false;
        }

        foreach ($capabilities as $capability) {
            if (!$user->has_cap($capability)) {
                return false;
            }
        }

        return true;
    }
}
