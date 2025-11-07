<?php
/**
 * State Machine Validator Class
 *
 * @package     WP_State_Machine
 * @subpackage  Validators
 * @version     1.0.0
 * @author      arisciwek
 *
 * Path: /wp-state-machine/src/Validators/StateMachineValidator.php
 *
 * Description: Handles validation for state machines.
 *              Follows wp-agency AgencyValidator pattern.
 *              Includes form validation and permission checking.
 *
 * Dependencies:
 * - StateMachineModel: For data verification
 *
 * Methods:
 * - validateForm(): Validate form input data
 * - validatePermission(): Check user permissions
 * - getUserRelation(): Get user's relation to state machine
 * - canView(): Check if user can view
 * - canUpdate(): Check if user can update
 * - canDelete(): Check if user can delete
 *
 * Changelog:
 * 1.0.0 - 2025-11-07
 * - Initial creation
 * - Form validation with error handling
 * - Permission validation
 * - Follow wp-agency pattern exactly
 */

namespace WPStateMachine\Validators;

use WPStateMachine\Models\StateMachine\StateMachineModel;

defined('ABSPATH') || exit;

class StateMachineValidator {
    /**
     * State Machine Model instance
     *
     * @var StateMachineModel
     */
    private $model;

    /**
     * Constructor
     * Initializes model instance
     */
    public function __construct() {
        $this->model = new StateMachineModel();
    }

    /**
     * Validate form data
     * Checks required fields, format, and uniqueness
     *
     * @param array $data Form data to validate
     * @param int|null $id State machine ID (for updates)
     * @return array Array of validation errors (empty if valid)
     */
    public function validateForm(array $data, ?int $id = null): array {
        $errors = [];

        // Validate name
        if (empty($data['name'])) {
            $errors['name'] = __('Name is required', 'wp-state-machine');
        } elseif (strlen($data['name']) < 3) {
            $errors['name'] = __('Name must be at least 3 characters', 'wp-state-machine');
        } elseif (strlen($data['name']) > 100) {
            $errors['name'] = __('Name must not exceed 100 characters', 'wp-state-machine');
        }

        // Validate slug
        if (empty($data['slug'])) {
            $errors['slug'] = __('Slug is required', 'wp-state-machine');
        } elseif (!preg_match('/^[a-z0-9-_]+$/', $data['slug'])) {
            $errors['slug'] = __('Slug can only contain lowercase letters, numbers, hyphens, and underscores', 'wp-state-machine');
        } elseif (strlen($data['slug']) < 3) {
            $errors['slug'] = __('Slug must be at least 3 characters', 'wp-state-machine');
        } elseif (strlen($data['slug']) > 100) {
            $errors['slug'] = __('Slug must not exceed 100 characters', 'wp-state-machine');
        } else {
            // Check slug uniqueness
            if ($this->model->slugExists($data['slug'], $id)) {
                $errors['slug'] = __('This slug is already in use', 'wp-state-machine');
            }
        }

        // Validate plugin_slug
        if (empty($data['plugin_slug'])) {
            $errors['plugin_slug'] = __('Plugin slug is required', 'wp-state-machine');
        } elseif (!preg_match('/^[a-z0-9-_]+$/', $data['plugin_slug'])) {
            $errors['plugin_slug'] = __('Plugin slug can only contain lowercase letters, numbers, hyphens, and underscores', 'wp-state-machine');
        } elseif (strlen($data['plugin_slug']) > 100) {
            $errors['plugin_slug'] = __('Plugin slug must not exceed 100 characters', 'wp-state-machine');
        }

        // Validate entity_type
        if (empty($data['entity_type'])) {
            $errors['entity_type'] = __('Entity type is required', 'wp-state-machine');
        } elseif (!preg_match('/^[a-z0-9-_]+$/', $data['entity_type'])) {
            $errors['entity_type'] = __('Entity type can only contain lowercase letters, numbers, hyphens, and underscores', 'wp-state-machine');
        } elseif (strlen($data['entity_type']) > 50) {
            $errors['entity_type'] = __('Entity type must not exceed 50 characters', 'wp-state-machine');
        }

        // Validate workflow_group_id (optional)
        if (!empty($data['workflow_group_id'])) {
            $workflow_group_id = intval($data['workflow_group_id']);
            if ($workflow_group_id <= 0) {
                $errors['workflow_group_id'] = __('Invalid workflow group ID', 'wp-state-machine');
            } else {
                // Check if workflow group exists
                global $wpdb;
                $wg_table = $wpdb->prefix . 'app_sm_workflow_groups';
                $wg_exists = $wpdb->get_var($wpdb->prepare(
                    "SELECT COUNT(*) FROM {$wg_table} WHERE id = %d",
                    $workflow_group_id
                ));
                if (!$wg_exists) {
                    $errors['workflow_group_id'] = __('Workflow group does not exist', 'wp-state-machine');
                }
            }
        }

        // Validate description (optional)
        if (!empty($data['description']) && strlen($data['description']) > 500) {
            $errors['description'] = __('Description must not exceed 500 characters', 'wp-state-machine');
        }

        return $errors;
    }

    /**
     * Validate user permission for operation
     * Checks capability-based permissions
     *
     * @param int $machine_id State machine ID
     * @param string $operation Operation type (view, update, delete)
     * @return array ['allowed' => bool, 'message' => string]
     */
    public function validatePermission(int $machine_id, string $operation = 'view'): array {
        // Get user relation
        $relation = $this->getUserRelation($machine_id);

        // Check permission based on operation
        switch ($operation) {
            case 'view':
                $allowed = $relation['can_view'];
                $message = $allowed ? '' : __('You do not have permission to view this state machine', 'wp-state-machine');
                break;

            case 'update':
                $allowed = $relation['can_update'];
                $message = $allowed ? '' : __('You do not have permission to update this state machine', 'wp-state-machine');
                break;

            case 'delete':
                $allowed = $relation['can_delete'];
                $message = $allowed ? '' : __('You do not have permission to delete this state machine', 'wp-state-machine');
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
     * Get user's relation to state machine
     * Returns permission flags and access type
     *
     * @param int $machine_id State machine ID
     * @return array User relation data
     */
    public function getUserRelation(int $machine_id): array {
        // Check if machine exists
        $machine = $this->model->find($machine_id);
        if (!$machine) {
            return [
                'exists' => false,
                'is_admin' => false,
                'can_view' => false,
                'can_update' => false,
                'can_delete' => false,
                'access_type' => 'none'
            ];
        }

        // Check user capabilities
        $is_admin = current_user_can('manage_state_machines');
        $can_view = current_user_can('view_state_machines');
        $can_edit = current_user_can('edit_state_machines');
        $can_delete = current_user_can('delete_state_machines');

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
            'machine' => $machine
        ];
    }

    /**
     * Check if user can view state machine
     *
     * @param int $machine_id State machine ID
     * @return bool True if user can view
     */
    public function canView(int $machine_id): bool {
        $relation = $this->getUserRelation($machine_id);
        return $relation['can_view'];
    }

    /**
     * Check if user can update state machine
     *
     * @param int $machine_id State machine ID
     * @return bool True if user can update
     */
    public function canUpdate(int $machine_id): bool {
        $relation = $this->getUserRelation($machine_id);
        return $relation['can_update'];
    }

    /**
     * Check if user can delete state machine
     *
     * @param int $machine_id State machine ID
     * @return bool True if user can delete
     */
    public function canDelete(int $machine_id): bool {
        $relation = $this->getUserRelation($machine_id);
        return $relation['can_delete'];
    }

    /**
     * Validate state machine data structure
     * Used for programmatic validation (e.g., in Seeder)
     *
     * @param array $machine_data Complete machine data with states and transitions
     * @return array Validation errors
     */
    public function validateMachineStructure(array $machine_data): array {
        $errors = [];

        // Validate basic machine data
        $basic_errors = $this->validateForm($machine_data);
        if (!empty($basic_errors)) {
            $errors['machine'] = $basic_errors;
        }

        // Validate states
        if (empty($machine_data['states']) || !is_array($machine_data['states'])) {
            $errors['states'] = __('State machine must have at least one state', 'wp-state-machine');
        } else {
            // Check for at least one initial state
            $has_initial = false;
            foreach ($machine_data['states'] as $state) {
                if (isset($state['is_initial']) && $state['is_initial']) {
                    $has_initial = true;
                    break;
                }
            }
            if (!$has_initial) {
                $errors['states_initial'] = __('State machine must have at least one initial state', 'wp-state-machine');
            }
        }

        // Validate transitions
        if (empty($machine_data['transitions']) || !is_array($machine_data['transitions'])) {
            $errors['transitions'] = __('State machine must have at least one transition', 'wp-state-machine');
        }

        return $errors;
    }

    /**
     * Validate bulk operation
     * Used for batch operations on multiple state machines
     *
     * @param array $machine_ids Array of state machine IDs
     * @param string $operation Operation type
     * @return array Results for each machine
     */
    public function validateBulkOperation(array $machine_ids, string $operation): array {
        $results = [];

        foreach ($machine_ids as $machine_id) {
            $validation = $this->validatePermission($machine_id, $operation);
            $results[$machine_id] = $validation;
        }

        return $results;
    }
}
