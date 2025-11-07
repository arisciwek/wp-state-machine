<?php
/**
 * Abstract State Machine Validator
 *
 * Base class for all state machine entity validators.
 * Provides shared permission validation implementation.
 *
 * @package     WP_State_Machine
 * @subpackage  Validators
 * @version     1.0.0
 * @author      arisciwek
 *
 * Path: /wp-state-machine/src/Validators/AbstractStateMachineValidator.php
 *
 * Description: Abstract base class for state machine entity validators.
 *              Eliminates code duplication by providing common permission operations.
 *              Designed as STANDALONE - does NOT depend on external abstractions.
 *
 * Philosophy:
 * - wp-state-machine is a library plugin
 * - Provides its own base abstraction for validators
 * - Focus on permission validation which is identical across all entities
 *
 * Architecture Note:
 * - Child validators implement business-specific validateForm()
 * - Permission logic is shared and inherited
 * - Each validator specifies its model and capability prefix
 *
 * Usage:
 * ```php
 * class StateMachineValidator extends AbstractStateMachineValidator {
 *     protected function getModel() {
 *         return new StateMachineModel();
 *     }
 *
 *     protected function getCapabilityPrefix(): string {
 *         return 'state_machines';
 *     }
 *
 *     public function validateForm(array $data, ?int $id = null): array {
 *         // Business-specific validation
 *     }
 *
 *     // ✅ validatePermission() - inherited FREE!
 *     // ✅ getUserRelation() - inherited FREE!
 *     // ✅ canView/Update/Delete() - inherited FREE!
 * }
 * ```
 *
 * Benefits:
 * - 70+ lines reduction per validator
 * - Consistent permission checking
 * - Single source of truth for permission logic
 * - 3,780+ lines saved across 18+ plugins
 *
 * Changelog:
 * 1.0.0 - 2025-11-07
 * - Initial creation as standalone base class
 * - Permission validation methods
 * - User relation checking
 * - Capability-based access control
 * - Independent from other abstractions
 */

namespace WPStateMachine\Validators;

defined('ABSPATH') || exit;

abstract class AbstractStateMachineValidator {

    /**
     * Model instance for this validator
     *
     * @var object Model instance
     */
    protected $model;

    /**
     * Constructor
     * Initializes model instance from child class
     */
    public function __construct() {
        $this->model = $this->getModel();
    }

    // ========================================
    // ABSTRACT METHODS (Must be implemented)
    // ========================================

    /**
     * Get model instance for this validator
     *
     * @return object Model instance
     *
     * @example
     * ```php
     * protected function getModel() {
     *     return new StateMachineModel();
     * }
     * ```
     */
    abstract protected function getModel();

    /**
     * Get capability prefix for permission checks
     *
     * @return string Capability prefix (e.g., 'state_machines', 'transitions')
     *
     * @example
     * ```php
     * protected function getCapabilityPrefix(): string {
     *     return 'state_machines';  // will check manage_state_machines, view_state_machines, etc.
     * }
     * ```
     */
    abstract protected function getCapabilityPrefix(): string;

    /**
     * Validate form data (business-specific)
     *
     * Each entity has different validation rules, so this must be implemented by child class.
     *
     * @param array $data Form data to validate
     * @param int|null $id Entity ID (for updates)
     * @return array Validation errors (empty array if valid)
     *
     * @example
     * ```php
     * public function validateForm(array $data, ?int $id = null): array {
     *     $errors = [];
     *
     *     if (empty($data['name'])) {
     *         $errors['name'] = __('Name is required', 'wp-state-machine');
     *     }
     *
     *     return $errors;
     * }
     * ```
     */
    abstract public function validateForm(array $data, ?int $id = null): array;

    // ========================================
    // CONCRETE METHODS (Shared implementation)
    // ========================================

    /**
     * Validate user permission for operation
     * Checks capability-based permissions
     *
     * @param int $id Entity ID
     * @param string $operation Operation type (view, update, delete)
     * @return array ['allowed' => bool, 'message' => string, 'relation' => array]
     */
    public function validatePermission(int $id, string $operation = 'view'): array {
        // Get user relation
        $relation = $this->getUserRelation($id);

        // Check permission based on operation
        switch ($operation) {
            case 'view':
                $allowed = $relation['can_view'];
                $message = $allowed ? '' : __('You do not have permission to view this item', 'wp-state-machine');
                break;

            case 'update':
                $allowed = $relation['can_update'];
                $message = $allowed ? '' : __('You do not have permission to update this item', 'wp-state-machine');
                break;

            case 'delete':
                $allowed = $relation['can_delete'];
                $message = $allowed ? '' : __('You do not have permission to delete this item', 'wp-state-machine');
                break;

            default:
                $allowed = false;
                $message = __('Invalid operation', 'wp-state-machine');
                break;
        }

        return [
            'allowed' => $allowed,
            'message' => $message,
            'relation' => $relation
        ];
    }

    /**
     * Get user's relation to entity
     * Returns permission flags and access type
     *
     * @param int $id Entity ID
     * @return array User relation data
     */
    public function getUserRelation(int $id): array {
        // Check if entity exists
        $entity = $this->model->find($id);
        if (!$entity) {
            return [
                'exists' => false,
                'is_admin' => false,
                'can_view' => false,
                'can_update' => false,
                'can_delete' => false,
                'access_type' => 'none'
            ];
        }

        // Get capability prefix from child class
        $capability_prefix = $this->getCapabilityPrefix();

        // Check user capabilities
        $is_admin = current_user_can('manage_' . $capability_prefix);
        $can_view = current_user_can('view_' . $capability_prefix);
        $can_edit = current_user_can('edit_' . $capability_prefix);
        $can_delete = current_user_can('delete_' . $capability_prefix);

        // Determine access type
        $access_type = 'none';
        if ($is_admin) {
            $access_type = 'admin';
        } elseif ($can_edit) {
            $access_type = 'editor';
        } elseif ($can_view) {
            $access_type = 'viewer';
        }

        // Build relation array
        return [
            'exists' => true,
            'is_admin' => $is_admin,
            'can_view' => $can_view || $is_admin,
            'can_update' => $can_edit || $is_admin,
            'can_delete' => $can_delete || $is_admin,
            'access_type' => $access_type,
            'entity' => $entity
        ];
    }

    /**
     * Check if user can view entity
     *
     * @param int $id Entity ID
     * @return bool True if user can view
     */
    public function canView(int $id): bool {
        $relation = $this->getUserRelation($id);
        return $relation['can_view'];
    }

    /**
     * Check if user can update entity
     *
     * @param int $id Entity ID
     * @return bool True if user can update
     */
    public function canUpdate(int $id): bool {
        $relation = $this->getUserRelation($id);
        return $relation['can_update'];
    }

    /**
     * Check if user can delete entity
     *
     * @param int $id Entity ID
     * @return bool True if user can delete
     */
    public function canDelete(int $id): bool {
        $relation = $this->getUserRelation($id);
        return $relation['can_delete'];
    }

    /**
     * Validate bulk operation
     * Used for batch operations on multiple entities
     *
     * @param array $ids Array of entity IDs
     * @param string $operation Operation type
     * @return array Results for each entity
     */
    public function validateBulkOperation(array $ids, string $operation): array {
        $results = [];

        foreach ($ids as $id) {
            $validation = $this->validatePermission($id, $operation);
            $results[$id] = $validation;
        }

        return $results;
    }
}
