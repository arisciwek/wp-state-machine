<?php
/**
 * Transition Validator Class
 *
 * @package     WP_State_Machine
 * @subpackage  Validators
 * @version     1.0.0
 * @author      arisciwek
 *
 * Path: /wp-state-machine/src/Validators/TransitionValidator.php
 *
 * Description: Handles validation for state machine transitions.
 *              Follows wp-agency AgencyValidator pattern.
 *              Includes form validation and permission checking.
 *
 * Dependencies:
 * - TransitionModel: For data verification
 * - StateMachineModel: For machine verification
 * - StateModel: For state verification
 *
 * Methods:
 * - validateForm(): Validate form input data
 * - validatePermission(): Check user permissions
 * - getUserRelation(): Get user's relation to transition
 * - canView(): Check if user can view
 * - canUpdate(): Check if user can update
 * - canDelete(): Check if user can delete
 *
 * Changelog:
 * 1.0.0 - 2025-11-07
 * - Initial creation following StateValidator pattern
 * - Form validation with error handling
 * - Permission validation
 * - Machine and state verification
 * - Duplicate transition check
 */

namespace WPStateMachine\Validators;

use WPStateMachine\Models\Transition\TransitionModel;
use WPStateMachine\Models\StateMachine\StateMachineModel;
use WPStateMachine\Models\State\StateModel;

defined('ABSPATH') || exit;

class TransitionValidator extends AbstractStateMachineValidator {
    /**
     * Transition Model instance
     * Note: Also available as protected $model from parent class
     *
     * @var TransitionModel
     */
    private $transition_model;

    /**
     * State Machine Model instance
     *
     * @var StateMachineModel
     */
    private $machine_model;

    /**
     * State Model instance
     *
     * @var StateModel
     */
    private $state_model;

    /**
     * Constructor
     * Initializes model instances
     * Parent constructor automatically calls getModel()
     */
    public function __construct() {
        parent::__construct();
        $this->transition_model = $this->model;
        $this->machine_model = new StateMachineModel();
        $this->state_model = new StateModel();
    }

    // ========================================
    // ABSTRACT METHOD IMPLEMENTATIONS
    // ========================================

    /**
     * Get model instance for this validator
     *
     * @return TransitionModel
     */
    protected function getModel() {
        return new TransitionModel();
    }

    /**
     * Get capability prefix for permission checks
     *
     * @return string
     */
    protected function getCapabilityPrefix(): string {
        return 'state_machines'; // Transitions use same capability as machines
    }

    /**
     * Validate form data
     * Checks required fields, format, and uniqueness
     *
     * @param array $data Form data to validate
     * @param int|null $id Transition ID (for updates)
     * @return array Array of validation errors (empty if valid)
     */
    public function validateForm(array $data, ?int $id = null): array {
        $errors = [];

        // For updates, we only validate updatable fields
        $is_update = ($id !== null);

        // Validate machine_id (only for create)
        if (!$is_update) {
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
        }

        // Validate from_state_id (only for create)
        if (!$is_update) {
            if (empty($data['from_state_id'])) {
                $errors['from_state_id'] = __('From state is required', 'wp-state-machine');
            } else {
                $from_state_id = intval($data['from_state_id']);
                if ($from_state_id <= 0) {
                    $errors['from_state_id'] = __('Invalid from state ID', 'wp-state-machine');
                } else {
                    // Check if state exists
                    $from_state = $this->state_model->find($from_state_id);
                    if (!$from_state) {
                        $errors['from_state_id'] = __('From state does not exist', 'wp-state-machine');
                    } elseif (!empty($data['machine_id']) && $from_state->machine_id != $data['machine_id']) {
                        $errors['from_state_id'] = __('From state does not belong to the selected machine', 'wp-state-machine');
                    }
                }
            }
        }

        // Validate to_state_id (only for create)
        if (!$is_update) {
            if (empty($data['to_state_id'])) {
                $errors['to_state_id'] = __('To state is required', 'wp-state-machine');
            } else {
                $to_state_id = intval($data['to_state_id']);
                if ($to_state_id <= 0) {
                    $errors['to_state_id'] = __('Invalid to state ID', 'wp-state-machine');
                } else {
                    // Check if state exists
                    $to_state = $this->state_model->find($to_state_id);
                    if (!$to_state) {
                        $errors['to_state_id'] = __('To state does not exist', 'wp-state-machine');
                    } elseif (!empty($data['machine_id']) && $to_state->machine_id != $data['machine_id']) {
                        $errors['to_state_id'] = __('To state does not belong to the selected machine', 'wp-state-machine');
                    }
                }
            }

            // Check if from_state and to_state are different
            if (isset($data['from_state_id']) && isset($data['to_state_id']) &&
                $data['from_state_id'] == $data['to_state_id']) {
                $errors['to_state_id'] = __('From state and to state must be different', 'wp-state-machine');
            }

            // Check for duplicate transitions
            if (!empty($data['from_state_id']) && !empty($data['to_state_id']) &&
                empty($errors['from_state_id']) && empty($errors['to_state_id'])) {
                if ($this->model->transitionExists($data['from_state_id'], $data['to_state_id'], $id)) {
                    $errors['to_state_id'] = __('A transition between these states already exists', 'wp-state-machine');
                }
            }
        }

        // Validate label
        if (empty($data['label'])) {
            $errors['label'] = __('Label is required', 'wp-state-machine');
        } elseif (strlen($data['label']) < 2) {
            $errors['label'] = __('Label must be at least 2 characters', 'wp-state-machine');
        } elseif (strlen($data['label']) > 100) {
            $errors['label'] = __('Label must not exceed 100 characters', 'wp-state-machine');
        }

        // Validate guard_class (optional)
        if (!empty($data['guard_class'])) {
            if (strlen($data['guard_class']) > 255) {
                $errors['guard_class'] = __('Guard class must not exceed 255 characters', 'wp-state-machine');
            }
            // TODO: Validate that guard class exists when Guard system is implemented
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
}
