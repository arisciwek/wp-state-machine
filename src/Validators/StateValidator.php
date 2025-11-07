<?php
/**
 * State Validator Class
 *
 * @package     WP_State_Machine
 * @subpackage  Validators
 * @version     1.0.0
 * @author      arisciwek
 *
 * Path: /wp-state-machine/src/Validators/StateValidator.php
 *
 * Description: Handles validation for state machine states.
 *              Follows wp-agency AgencyValidator pattern.
 *              Includes form validation and permission checking.
 *
 * Dependencies:
 * - StateModel: For data verification
 * - StateMachineModel: For machine verification
 *
 * Methods:
 * - validateForm(): Validate form input data
 * - validatePermission(): Check user permissions
 * - getUserRelation(): Get user's relation to state
 * - canView(): Check if user can view
 * - canUpdate(): Check if user can update
 * - canDelete(): Check if user can delete
 *
 * Changelog:
 * 1.0.0 - 2025-11-07
 * - Initial creation following StateMachineValidator pattern
 * - Form validation with error handling
 * - Permission validation
 * - Machine-specific validation
 */

namespace WPStateMachine\Validators;

use WPStateMachine\Models\State\StateModel;
use WPStateMachine\Models\StateMachine\StateMachineModel;

defined('ABSPATH') || exit;

class StateValidator extends AbstractStateMachineValidator {
    /**
     * State Model instance
     * Note: Also available as protected $model from parent class
     *
     * @var StateModel
     */
    private $state_model;

    /**
     * State Machine Model instance
     *
     * @var StateMachineModel
     */
    private $machine_model;

    /**
     * Constructor
     * Initializes model instances
     * Parent constructor automatically calls getModel()
     */
    public function __construct() {
        parent::__construct();
        $this->state_model = $this->model;
        $this->machine_model = new StateMachineModel();
    }

    // ========================================
    // ABSTRACT METHOD IMPLEMENTATIONS
    // ========================================

    /**
     * Get model instance for this validator
     *
     * @return StateModel
     */
    protected function getModel() {
        return new StateModel();
    }

    /**
     * Get capability prefix for permission checks
     *
     * @return string
     */
    protected function getCapabilityPrefix(): string {
        return 'state_machines'; // States use same capability as machines
    }

    /**
     * Validate form data
     * Checks required fields, format, and uniqueness
     *
     * @param array $data Form data to validate
     * @param int|null $id State ID (for updates)
     * @return array Array of validation errors (empty if valid)
     */
    public function validateForm(array $data, ?int $id = null): array {
        $errors = [];

        // Validate machine_id
        if (empty($data['machine_id'])) {
            $errors['machine_id'] = __('Machine ID is required', 'wp-state-machine');
        } else {
            $machine_id = intval($data['machine_id']);
            if ($machine_id <= 0) {
                $errors['machine_id'] = __('Invalid machine ID', 'wp-state-machine');
            } else {
                // Check if machine exists
                $machine = $this->machine_model->find($machine_id);
                if (!$machine) {
                    $errors['machine_id'] = __('State machine does not exist', 'wp-state-machine');
                }
            }
        }

        // Validate name
        if (empty($data['name'])) {
            $errors['name'] = __('Name is required', 'wp-state-machine');
        } elseif (strlen($data['name']) < 2) {
            $errors['name'] = __('Name must be at least 2 characters', 'wp-state-machine');
        } elseif (strlen($data['name']) > 100) {
            $errors['name'] = __('Name must not exceed 100 characters', 'wp-state-machine');
        }

        // Validate slug
        if (empty($data['slug'])) {
            $errors['slug'] = __('Slug is required', 'wp-state-machine');
        } elseif (!preg_match('/^[a-z0-9-_]+$/', $data['slug'])) {
            $errors['slug'] = __('Slug can only contain lowercase letters, numbers, hyphens, and underscores', 'wp-state-machine');
        } elseif (strlen($data['slug']) < 2) {
            $errors['slug'] = __('Slug must be at least 2 characters', 'wp-state-machine');
        } elseif (strlen($data['slug']) > 100) {
            $errors['slug'] = __('Slug must not exceed 100 characters', 'wp-state-machine');
        } else {
            // Check slug uniqueness within the machine
            if (!empty($data['machine_id'])) {
                $machine_id = intval($data['machine_id']);
                if ($this->model->slugExists($machine_id, $data['slug'], $id)) {
                    $errors['slug'] = __('This slug is already in use for this machine', 'wp-state-machine');
                }
            }
        }

        // Validate type
        $valid_types = ['initial', 'normal', 'final', 'intermediate'];
        if (empty($data['type'])) {
            $errors['type'] = __('Type is required', 'wp-state-machine');
        } elseif (!in_array(strtolower($data['type']), $valid_types)) {
            $errors['type'] = __('Invalid state type. Must be: initial, normal, intermediate, or final', 'wp-state-machine');
        } else {
            // Check if there's already an initial state for this machine (when creating a new initial state)
            if (strtolower($data['type']) === 'initial' && !empty($data['machine_id'])) {
                $machine_id = intval($data['machine_id']);
                $existing_initial = $this->model->getInitialState($machine_id);

                // If we're creating a new state or updating a different state to initial
                if ($existing_initial && (!$id || $existing_initial->id != $id)) {
                    $errors['type'] = __('This machine already has an initial state', 'wp-state-machine');
                }
            }
        }

        // Validate color (optional)
        if (!empty($data['color'])) {
            // Check if it's a valid hex color
            if (!preg_match('/^#[a-f0-9]{6}$/i', $data['color'])) {
                $errors['color'] = __('Color must be a valid hex color code (e.g., #ff0000)', 'wp-state-machine');
            }
        }

        // Validate metadata (optional)
        if (!empty($data['metadata'])) {
            // If metadata is a string, check if it's valid JSON
            if (is_string($data['metadata'])) {
                $decoded = json_decode($data['metadata']);
                if (json_last_error() !== JSON_ERROR_NONE && $data['metadata'] !== '') {
                    $errors['metadata'] = __('Metadata must be valid JSON', 'wp-state-machine');
                }
            }
        }

        // Validate sort_order (optional)
        if (isset($data['sort_order'])) {
            $sort_order = intval($data['sort_order']);
            if ($sort_order < 0) {
                $errors['sort_order'] = __('Sort order must be a positive number', 'wp-state-machine');
            }
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
     * Validate state can be deleted
     * Check if state is used in any transitions
     *
     * @param int $state_id State ID
     * @return array ['can_delete' => bool, 'message' => string]
     */
    public function canDeleteState(int $state_id): array {
        global $wpdb;

        // Check if state is used in transitions (as from_state or to_state)
        $transition_table = $wpdb->prefix . 'app_sm_transitions';

        $count = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$transition_table}
             WHERE from_state_id = %d OR to_state_id = %d",
            $state_id,
            $state_id
        ));

        if ($count > 0) {
            return [
                'can_delete' => false,
                'message' => sprintf(
                    __('Cannot delete state. It is used in %d transition(s)', 'wp-state-machine'),
                    $count
                )
            ];
        }

        // Check if state is used as current state in any entity
        // This would depend on your entity tracking implementation
        // For now, we'll allow deletion if no transitions reference it

        return [
            'can_delete' => true,
            'message' => ''
        ];
    }

    // validateBulkOperation() inherited from AbstractStateMachineValidator
}
