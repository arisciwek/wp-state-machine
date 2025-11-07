<?php
/**
 * Workflow Group Validator Class
 *
 * @package     WP_State_Machine
 * @subpackage  Validators
 * @version     1.0.0
 * @author      arisciwek
 *
 * Path: /wp-state-machine/src/Validators/WorkflowGroupValidator.php
 *
 * Description: Handles validation for workflow groups.
 *              Extends AbstractStateMachineValidator for consistency.
 *              Includes form validation and permission checking.
 *
 * Dependencies:
 * - WorkflowGroupModel: For data verification
 * - AbstractStateMachineValidator: Base validation methods
 *
 * Methods:
 * - validateForm(): Validate form input data
 * - validatePermission(): Check user permissions (inherited)
 * - canView(): Check if user can view (inherited)
 * - canUpdate(): Check if user can update (inherited)
 * - canDelete(): Check if user can delete (inherited)
 *
 * Changelog:
 * 1.0.0 - 2025-11-07 (TODO-6102 PRIORITAS #7)
 * - Initial creation for FASE 3
 * - Extends AbstractStateMachineValidator
 * - Form validation with error handling
 * - Permission validation
 */

namespace WPStateMachine\Validators;

use WPStateMachine\Models\WorkflowGroup\WorkflowGroupModel;

defined('ABSPATH') || exit;

class WorkflowGroupValidator extends AbstractStateMachineValidator {
    /**
     * Workflow Group Model instance
     * Note: Also available as protected $model from parent class
     *
     * @var WorkflowGroupModel
     */
    private $group_model;

    /**
     * Constructor
     * Initializes model instance
     * Parent constructor automatically calls getModel()
     */
    public function __construct() {
        parent::__construct();
        $this->group_model = $this->model;
    }

    // ========================================
    // ABSTRACT METHOD IMPLEMENTATIONS
    // ========================================

    /**
     * Get model instance for this validator
     *
     * @return WorkflowGroupModel
     */
    protected function getModel() {
        return new WorkflowGroupModel();
    }

    /**
     * Get capability prefix for permission checks
     *
     * @return string
     */
    protected function getCapabilityPrefix(): string {
        return 'workflow_groups';
    }

    /**
     * Validate form data
     * Checks required fields, format, and uniqueness
     *
     * @param array $data Form data to validate
     * @param int|null $id Workflow group ID (for updates)
     * @return array Array of validation errors (empty if valid)
     */
    public function validateForm(array $data, ?int $id = null): array {
        $errors = [];

        // Validate name
        if (empty($data['name'])) {
            $errors['name'] = __('Group name is required', 'wp-state-machine');
        } elseif (strlen($data['name']) < 3) {
            $errors['name'] = __('Group name must be at least 3 characters', 'wp-state-machine');
        } elseif (strlen($data['name']) > 100) {
            $errors['name'] = __('Group name must not exceed 100 characters', 'wp-state-machine');
        }

        // Validate slug
        if (empty($data['slug'])) {
            $errors['slug'] = __('Slug is required', 'wp-state-machine');
        } else {
            // Check slug format
            if (!preg_match('/^[a-z0-9-]+$/', $data['slug'])) {
                $errors['slug'] = __('Slug must contain only lowercase letters, numbers, and hyphens', 'wp-state-machine');
            } elseif (strlen($data['slug']) < 3) {
                $errors['slug'] = __('Slug must be at least 3 characters', 'wp-state-machine');
            } elseif (strlen($data['slug']) > 100) {
                $errors['slug'] = __('Slug must not exceed 100 characters', 'wp-state-machine');
            } else {
                // Check slug uniqueness
                if ($this->group_model->slugExists($data['slug'], $id)) {
                    $errors['slug'] = __('This slug already exists', 'wp-state-machine');
                }
            }
        }

        // Validate description (optional)
        if (isset($data['description']) && strlen($data['description']) > 500) {
            $errors['description'] = __('Description must not exceed 500 characters', 'wp-state-machine');
        }

        // Validate icon (optional, dashicon class)
        if (!empty($data['icon'])) {
            if (!preg_match('/^dashicons-[a-z0-9-]+$/', $data['icon'])) {
                $errors['icon'] = __('Icon must be a valid dashicon class (e.g., dashicons-networking)', 'wp-state-machine');
            }
        }

        // Validate sort_order (optional)
        if (isset($data['sort_order'])) {
            if (!is_numeric($data['sort_order']) || $data['sort_order'] < 0) {
                $errors['sort_order'] = __('Sort order must be a positive number', 'wp-state-machine');
            }
        }

        // Validate is_active (optional)
        if (isset($data['is_active'])) {
            if (!in_array($data['is_active'], [0, 1, '0', '1', true, false], true)) {
                $errors['is_active'] = __('Active status must be 0 or 1', 'wp-state-machine');
            }
        }

        return $errors;
    }

    /**
     * Validate delete operation
     * Checks if group can be safely deleted (no assigned machines)
     *
     * @param int $id Group ID
     * @return array Validation errors
     */
    public function validateDelete(int $id): array {
        $errors = [];

        // Check if group exists
        $group = $this->group_model->find($id);
        if (!$group) {
            $errors['group'] = __('Workflow group not found', 'wp-state-machine');
            return $errors;
        }

        // Check if group has assigned machines
        $machines = $this->group_model->getMachines($id);
        if (!empty($machines)) {
            $errors['machines'] = sprintf(
                __('Cannot delete group. %d machine(s) are assigned to this group. Please reassign them first.', 'wp-state-machine'),
                count($machines)
            );
        }

        return $errors;
    }

    /**
     * Sanitize form data
     * Clean and prepare data for database insertion
     *
     * @param array $data Raw form data
     * @return array Sanitized data
     */
    public function sanitize(array $data): array {
        return [
            'name' => sanitize_text_field($data['name'] ?? ''),
            'slug' => sanitize_title($data['slug'] ?? ''),
            'description' => sanitize_textarea_field($data['description'] ?? ''),
            'icon' => sanitize_text_field($data['icon'] ?? 'dashicons-networking'),
            'sort_order' => isset($data['sort_order']) ? absint($data['sort_order']) : 0,
            'is_active' => isset($data['is_active']) ? (int) $data['is_active'] : 1
        ];
    }

    /**
     * Validate sort order update
     * For drag-drop reordering
     *
     * @param array $order_data Array of ['id' => sort_order]
     * @return array Validation errors
     */
    public function validateSortOrders(array $order_data): array {
        $errors = [];

        if (empty($order_data)) {
            $errors['order_data'] = __('Sort order data is required', 'wp-state-machine');
            return $errors;
        }

        foreach ($order_data as $id => $sort_order) {
            // Validate ID
            if (!is_numeric($id) || $id <= 0) {
                $errors["id_{$id}"] = __('Invalid group ID', 'wp-state-machine');
            }

            // Validate sort_order
            if (!is_numeric($sort_order) || $sort_order < 0) {
                $errors["sort_{$id}"] = __('Invalid sort order value', 'wp-state-machine');
            }

            // Check if group exists
            if (!$this->group_model->find($id)) {
                $errors["group_{$id}"] = __('Group not found', 'wp-state-machine');
            }
        }

        return $errors;
    }
}
