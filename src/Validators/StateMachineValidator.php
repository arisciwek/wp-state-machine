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

class StateMachineValidator extends AbstractStateMachineValidator {
    /**
     * State Machine Model instance
     * Note: Also available as protected $model from parent class
     *
     * @var StateMachineModel
     */
    private $machine_model;

    /**
     * Constructor
     * Initializes model instance
     * Parent constructor automatically calls getModel()
     */
    public function __construct() {
        parent::__construct();
        $this->machine_model = $this->model;
    }

    // ========================================
    // ABSTRACT METHOD IMPLEMENTATIONS
    // ========================================

    /**
     * Get model instance for this validator
     *
     * @return StateMachineModel
     */
    protected function getModel() {
        return new StateMachineModel();
    }

    /**
     * Get capability prefix for permission checks
     *
     * @return string
     */
    protected function getCapabilityPrefix(): string {
        return 'state_machines';
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

    // ========================================
    // Permission methods inherited from AbstractStateMachineValidator:
    // - validatePermission(int $id, string $operation): array
    // - getUserRelation(int $id): array
    // - canView(int $id): bool
    // - canUpdate(int $id): bool
    // - canDelete(int $id): bool
    // - validateBulkOperation(array $ids, string $operation): array
    // ========================================

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

    // validateBulkOperation() inherited from AbstractStateMachineValidator
}
