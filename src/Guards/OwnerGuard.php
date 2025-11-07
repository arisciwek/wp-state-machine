<?php
/**
 * Owner Guard
 *
 * @package     WP_State_Machine
 * @subpackage  Guards
 * @version     1.0.0
 * @author      arisciwek
 *
 * Path: /wp-state-machine/src/Guards/OwnerGuard.php
 *
 * Description: Checks if user is the owner of the entity being transitioned.
 *              Requires entity data in context with owner field.
 *              Flexible ownership field configuration.
 *
 * Configuration Format:
 * ```
 * "OwnerGuard:author_id"        // Check 'author_id' field
 * "OwnerGuard:user_id"          // Check 'user_id' field
 * "OwnerGuard:created_by"       // Check 'created_by' field
 * "OwnerGuard:owner_id"         // Check custom 'owner_id' field
 * ```
 *
 * Usage Examples:
 *
 * Example 1: Check if user is the author
 * ```php
 * $transition->guard_class = 'OwnerGuard:author_id';
 *
 * // In execution:
 * $context = [
 *     'entity_data' => [
 *         'id' => 123,
 *         'author_id' => 45,
 *         'title' => 'My Document'
 *     ]
 * ];
 * $result = $guard->check(123, 45, $context);
 * // Returns: ['allowed' => true, ...]
 * ```
 *
 * Example 2: Check post author
 * ```php
 * $transition->guard_class = 'OwnerGuard:post_author';
 *
 * $context = [
 *     'entity_data' => get_post(123) // WordPress post object
 * ];
 * ```
 *
 * Example 3: Check custom ownership field
 * ```php
 * $transition->guard_class = 'OwnerGuard:assigned_to';
 *
 * $context = [
 *     'entity_data' => [
 *         'id' => 456,
 *         'assigned_to' => 78,
 *         'status' => 'pending'
 *     ]
 * ];
 * ```
 *
 * Context Requirements:
 * ```php
 * $context = [
 *     'entity_data' => [
 *         'owner_field' => user_id,  // Field specified in config
 *         // ... other entity data
 *     ]
 * ];
 * ```
 *
 * Return Structure:
 * ```php
 * [
 *     'allowed' => true|false,
 *     'message' => 'Success or failure message',
 *     'code' => 'success|not_owner|missing_data|invalid_config',
 *     'data' => [
 *         'user_id' => 45,
 *         'owner_id' => 45,
 *         'owner_field' => 'author_id',
 *         'entity_id' => 123
 *     ]
 * ]
 * ```
 *
 * Use Cases:
 * - Users can only edit their own posts
 * - Authors can only publish their own articles
 * - Customers can only cancel their own orders
 * - Users can only approve items they created
 *
 * Combining with Other Guards:
 * For "owner OR admin" logic, the engine should support multiple guards:
 * ```php
 * // Not supported yet in single guard_class, but future enhancement:
 * $transition->guards = [
 *     ['type' => 'OwnerGuard', 'config' => ['author_id']],
 *     ['type' => 'RoleGuard', 'config' => ['administrator']],
 *     'logic' => 'OR'
 * ];
 * ```
 *
 * Changelog:
 * 1.0.0 - 2025-11-07
 * - Initial creation for Prioritas #4
 * - Flexible owner field configuration
 * - Support for array and object entity data
 * - Detailed error messages
 */

namespace WPStateMachine\Guards;

defined('ABSPATH') || exit;

class OwnerGuard extends AbstractGuard {

    /**
     * Check if user is the owner of the entity
     *
     * @param int $entity_id Entity being transitioned
     * @param int $user_id User attempting transition
     * @param array $context Must include 'entity_data' with owner field
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

        // Get entity data from context
        $entity_data = $this->getEntityData($context);
        if (!$entity_data) {
            return $this->failure(
                __('Entity data not provided in context', 'wp-state-machine'),
                'missing_entity_data',
                ['entity_id' => $entity_id]
            );
        }

        // Get owner field name from config
        $owner_field = $this->getOwnerField();
        if (!$owner_field) {
            return $this->failure(
                __('Owner field not configured', 'wp-state-machine'),
                'invalid_config',
                []
            );
        }

        // Get owner ID from entity data
        $owner_id = $this->getOwnerIdFromEntity($entity_data, $owner_field);
        if ($owner_id === null) {
            return $this->failure(
                sprintf(
                    __('Owner field "%s" not found in entity data', 'wp-state-machine'),
                    $owner_field
                ),
                'owner_field_not_found',
                [
                    'owner_field' => $owner_field,
                    'entity_id' => $entity_id,
                    'available_fields' => $this->getAvailableFields($entity_data)
                ]
            );
        }

        // Compare user ID with owner ID
        if (intval($owner_id) === intval($user_id)) {
            return $this->success(
                __('User is the owner of this entity', 'wp-state-machine'),
                [
                    'user_id' => $user_id,
                    'owner_id' => $owner_id,
                    'owner_field' => $owner_field,
                    'entity_id' => $entity_id
                ]
            );
        }

        // User is not the owner
        return $this->failure(
            __('User is not the owner of this entity', 'wp-state-machine'),
            'not_owner',
            [
                'user_id' => $user_id,
                'owner_id' => $owner_id,
                'owner_field' => $owner_field,
                'entity_id' => $entity_id
            ]
        );
    }

    /**
     * Get guard name
     *
     * @return string
     */
    public function getName(): string {
        return __('Owner Guard', 'wp-state-machine');
    }

    /**
     * Get guard description
     *
     * @return string
     */
    public function getDescription(): string {
        return __('Checks if user is the owner of the entity', 'wp-state-machine');
    }

    /**
     * Validate configuration
     *
     * @param array $config Configuration to validate
     * @return array Validation errors
     */
    public function validateConfig(array $config): array {
        $errors = [];

        // Config should be array with single element (owner field name)
        if (empty($config)) {
            $errors[] = __('Owner field name must be specified', 'wp-state-machine');
            return $errors;
        }

        if (count($config) > 1) {
            $errors[] = __('Only one owner field should be specified', 'wp-state-machine');
        }

        $owner_field = $config[0] ?? null;
        if (!is_string($owner_field)) {
            $errors[] = sprintf(
                __('Owner field must be a string, %s given', 'wp-state-machine'),
                gettype($owner_field)
            );
        }

        if (is_string($owner_field) && trim($owner_field) === '') {
            $errors[] = __('Owner field name cannot be empty', 'wp-state-machine');
        }

        return $errors;
    }

    // ========================================
    // HELPER METHODS
    // ========================================

    /**
     * Get owner field name from config
     *
     * @return string|null Owner field name
     */
    protected function getOwnerField(): ?string {
        if (empty($this->config)) {
            return null;
        }

        return trim($this->config[0]);
    }

    /**
     * Get owner ID from entity data
     *
     * Supports both array and object entity data
     *
     * @param mixed $entity_data Entity data (array or object)
     * @param string $owner_field Owner field name
     * @return int|null Owner user ID
     */
    protected function getOwnerIdFromEntity($entity_data, string $owner_field): ?int {
        // Handle array
        if (is_array($entity_data)) {
            return isset($entity_data[$owner_field]) ? intval($entity_data[$owner_field]) : null;
        }

        // Handle object
        if (is_object($entity_data)) {
            return isset($entity_data->$owner_field) ? intval($entity_data->$owner_field) : null;
        }

        return null;
    }

    /**
     * Get available fields from entity data
     * Used for debugging when owner field not found
     *
     * @param mixed $entity_data Entity data
     * @return array Available field names
     */
    protected function getAvailableFields($entity_data): array {
        if (is_array($entity_data)) {
            return array_keys($entity_data);
        }

        if (is_object($entity_data)) {
            return array_keys(get_object_vars($entity_data));
        }

        return [];
    }
}
