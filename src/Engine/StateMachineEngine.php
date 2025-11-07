<?php
/**
 * State Machine Engine
 *
 * @package     WP_State_Machine
 * @subpackage  Engine
 * @version     1.0.0
 * @author      arisciwek
 *
 * Path: /wp-state-machine/src/Engine/StateMachineEngine.php
 *
 * Description: Core execution engine for state machine transitions.
 *              Validates transitions, checks guards, executes state changes,
 *              logs activities, and fires WordPress hooks.
 *              This is the heart of the wp-state-machine plugin.
 *
 * Core Responsibilities:
 * 1. Validate current state before transition
 * 2. Check guard permissions
 * 3. Execute state transition
 * 4. Log to transition_logs table
 * 5. Fire WordPress hooks for extensibility
 * 6. Handle errors gracefully
 * 7. Provide rollback mechanism
 *
 * Usage Examples:
 *
 * Example 1: Check if transition is allowed
 * ```php
 * $engine = new StateMachineEngine();
 * $result = $engine->canTransition([
 *     'machine_slug' => 'order-workflow',
 *     'entity_type' => 'order',
 *     'entity_id' => 123,
 *     'transition_id' => 5,
 *     'user_id' => get_current_user_id()
 * ]);
 *
 * if ($result['allowed']) {
 *     // Transition is allowed
 * } else {
 *     echo $result['message'];
 * }
 * ```
 *
 * Example 2: Apply transition
 * ```php
 * $engine = new StateMachineEngine();
 * $result = $engine->applyTransition([
 *     'machine_slug' => 'order-workflow',
 *     'entity_type' => 'order',
 *     'entity_id' => 123,
 *     'transition_id' => 5,
 *     'user_id' => get_current_user_id(),
 *     'comment' => 'Approved by manager',
 *     'metadata' => ['ip' => $_SERVER['REMOTE_ADDR']]
 * ]);
 *
 * if ($result['success']) {
 *     echo "Transitioned to: " . $result['to_state']->name;
 * } else {
 *     echo "Error: " . $result['message'];
 * }
 * ```
 *
 * Example 3: Get available transitions for entity
 * ```php
 * $transitions = $engine->getAvailableTransitions([
 *     'machine_slug' => 'order-workflow',
 *     'entity_type' => 'order',
 *     'entity_id' => 123,
 *     'user_id' => get_current_user_id()
 * ]);
 *
 * foreach ($transitions as $transition) {
 *     echo "<button>{$transition->label}</button>";
 * }
 * ```
 *
 * WordPress Hooks Fired:
 *
 * Before transition:
 * ```php
 * do_action('wp_state_machine_before_transition', [
 *     'entity_type' => 'order',
 *     'entity_id' => 123,
 *     'from_state' => $from_state_object,
 *     'to_state' => $to_state_object,
 *     'transition' => $transition_object,
 *     'user_id' => 45,
 *     'context' => ['comment' => '...', 'metadata' => [...]]
 * ]);
 * ```
 *
 * After successful transition:
 * ```php
 * do_action('wp_state_machine_after_transition', [
 *     'entity_type' => 'order',
 *     'entity_id' => 123,
 *     'from_state' => $from_state_object,
 *     'to_state' => $to_state_object,
 *     'transition' => $transition_object,
 *     'user_id' => 45,
 *     'log_id' => 789,
 *     'context' => [...]
 * ]);
 * ```
 *
 * On transition failure:
 * ```php
 * do_action('wp_state_machine_transition_failed', [
 *     'entity_type' => 'order',
 *     'entity_id' => 123,
 *     'transition' => $transition_object,
 *     'error' => 'Error message',
 *     'error_code' => 'guard_failed',
 *     'user_id' => 45
 * ]);
 * ```
 *
 * Architecture:
 * - Uses GuardFactory for permission checks
 * - Uses TransitionLogModel for audit trail
 * - Uses Model classes for data access
 * - Fires hooks for plugin extensibility
 * - Provides detailed error messages
 *
 * Changelog:
 * 1.0.0 - 2025-11-07
 * - Initial creation for Prioritas #5
 * - Core transition methods
 * - Guard integration
 * - Logging integration
 * - WordPress hooks
 * - Error handling
 */

namespace WPStateMachine\Engine;

use WPStateMachine\Models\StateMachine\StateMachineModel;
use WPStateMachine\Models\State\StateModel;
use WPStateMachine\Models\Transition\TransitionModel;
use WPStateMachine\Models\TransitionLog\TransitionLogModel;
use WPStateMachine\Guards\GuardFactory;

defined('ABSPATH') || exit;

class StateMachineEngine {

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
     * Transition Model instance
     *
     * @var TransitionModel
     */
    private $transition_model;

    /**
     * Transition Log Model instance
     *
     * @var TransitionLogModel
     */
    private $log_model;

    /**
     * Enable debug logging
     *
     * @var bool
     */
    private $debug = false;

    /**
     * Constructor
     * Initialize model instances
     */
    public function __construct() {
        $this->machine_model = new StateMachineModel();
        $this->state_model = new StateModel();
        $this->transition_model = new TransitionModel();
        $this->log_model = new TransitionLogModel();

        // Enable debug if WP_DEBUG is on
        $this->debug = (defined('WP_DEBUG') && WP_DEBUG);
    }

    // ========================================
    // CORE TRANSITION METHODS
    // ========================================

    /**
     * Check if transition is allowed
     *
     * Validates:
     * - Machine exists
     * - Transition exists
     * - Current state matches from_state
     * - Guard permissions
     *
     * @param array $params Transition parameters
     * @return array Result ['allowed' => bool, 'message' => string, 'data' => array]
     */
    public function canTransition(array $params): array {
        // Validate and extract parameters
        $validation = $this->validateParams($params);
        if (!$validation['valid']) {
            return $this->failure($validation['message'], 'invalid_params', $validation);
        }

        $machine = $validation['machine'];
        $transition = $validation['transition'];
        $current_state = $validation['current_state'];
        $entity_type = $params['entity_type'];
        $entity_id = $params['entity_id'];
        $user_id = $params['user_id'] ?? get_current_user_id();

        // Check if current state matches transition's from_state
        if ($current_state && $current_state->id != $transition->from_state_id) {
            return $this->failure(
                sprintf(
                    __('Invalid transition. Current state is "%s" but transition requires "%s"', 'wp-state-machine'),
                    $current_state->name,
                    $transition->from_state_name
                ),
                'invalid_current_state',
                [
                    'current_state_id' => $current_state->id,
                    'required_state_id' => $transition->from_state_id
                ]
            );
        }

        // Check guard permissions if guard is configured
        if (!empty($transition->guard_class)) {
            $guard_result = $this->checkGuard($transition, $entity_id, $user_id, $params);
            if (!$guard_result['allowed']) {
                return $this->failure(
                    $guard_result['message'],
                    'guard_failed',
                    $guard_result
                );
            }
        }

        // All checks passed
        return $this->success(__('Transition is allowed', 'wp-state-machine'), [
            'machine' => $machine,
            'transition' => $transition,
            'current_state' => $current_state,
            'to_state' => $validation['to_state']
        ]);
    }

    /**
     * Apply state transition
     *
     * Executes the transition and logs it.
     * This is the main method for executing state changes.
     *
     * @param array $params Transition parameters
     * @return array Result ['success' => bool, 'message' => string, 'data' => array]
     */
    public function applyTransition(array $params): array {
        // Check if transition is allowed
        $can_transition = $this->canTransition($params);
        if (!$can_transition['allowed']) {
            // Fire failure hook
            $this->fireTransitionFailed($params, $can_transition['message'], $can_transition['code']);
            return [
                'success' => false,
                'message' => $can_transition['message'],
                'code' => $can_transition['code'],
                'data' => $can_transition['data']
            ];
        }

        $machine = $can_transition['data']['machine'];
        $transition = $can_transition['data']['transition'];
        $from_state = $can_transition['data']['current_state'];
        $to_state = $can_transition['data']['to_state'];
        $entity_type = $params['entity_type'];
        $entity_id = $params['entity_id'];
        $user_id = $params['user_id'] ?? get_current_user_id();

        // Fire before transition hook
        $context = $this->buildContext($params, $from_state, $to_state, $transition);
        do_action('wp_state_machine_before_transition', $context);

        // Log the transition
        $log_id = $this->log_model->create([
            'machine_id' => $machine->id,
            'entity_id' => $entity_id,
            'entity_type' => $entity_type,
            'from_state_id' => $from_state ? $from_state->id : null,
            'to_state_id' => $to_state->id,
            'transition_id' => $transition->id,
            'user_id' => $user_id,
            'comment' => $params['comment'] ?? null,
            'metadata' => $params['metadata'] ?? null
        ]);

        if (!$log_id) {
            $error_message = __('Failed to log transition', 'wp-state-machine');
            $this->fireTransitionFailed($params, $error_message, 'log_failed');
            return [
                'success' => false,
                'message' => $error_message,
                'code' => 'log_failed',
                'data' => []
            ];
        }

        $this->debug('Transition applied successfully', [
            'log_id' => $log_id,
            'entity' => "{$entity_type}:{$entity_id}",
            'transition' => "{$from_state->name} → {$to_state->name}"
        ]);

        // Fire after transition hook
        $context['log_id'] = $log_id;
        do_action('wp_state_machine_after_transition', $context);

        // Return success
        return [
            'success' => true,
            'message' => sprintf(
                __('Successfully transitioned from "%s" to "%s"', 'wp-state-machine'),
                $from_state ? $from_state->name : __('initial', 'wp-state-machine'),
                $to_state->name
            ),
            'code' => 'success',
            'data' => [
                'log_id' => $log_id,
                'from_state' => $from_state,
                'to_state' => $to_state,
                'transition' => $transition
            ]
        ];
    }

    /**
     * Get available transitions for entity
     *
     * Returns transitions that:
     * - Match current state
     * - Pass guard checks (optional)
     *
     * @param array $params Query parameters
     * @param bool $check_guards Whether to filter by guard permissions
     * @return array Available transitions
     */
    public function getAvailableTransitions(array $params, bool $check_guards = true): array {
        // Get machine
        $machine = null;
        if (!empty($params['machine_slug'])) {
            $machine = $this->machine_model->findBySlug($params['machine_slug']);
        } elseif (!empty($params['machine_id'])) {
            $machine = $this->machine_model->find($params['machine_id']);
        }

        if (!$machine) {
            return [];
        }

        // Get current state
        $current_state = $this->getCurrentState(
            $params['entity_type'],
            $params['entity_id'],
            $machine->id
        );

        // Get available transitions from model
        $transitions = $this->transition_model->getAvailableTransitions(
            $machine->id,
            $current_state ? $current_state->to_state_id : null
        );

        // Filter by guard permissions if requested
        if ($check_guards && !empty($params['user_id'])) {
            $user_id = $params['user_id'];
            $entity_id = $params['entity_id'];

            $transitions = array_filter($transitions, function($transition) use ($entity_id, $user_id, $params) {
                if (empty($transition->guard_class)) {
                    return true; // No guard = allow
                }

                $guard_result = $this->checkGuard($transition, $entity_id, $user_id, $params);
                return $guard_result['allowed'];
            });
        }

        return array_values($transitions);
    }

    /**
     * Get current state for entity
     *
     * @param string $entity_type Entity type
     * @param int $entity_id Entity ID
     * @param int $machine_id Machine ID
     * @return object|null Current state log or null if no history
     */
    public function getCurrentState(string $entity_type, int $entity_id, int $machine_id): ?object {
        return $this->log_model->getCurrentState($entity_type, $entity_id);
    }

    /**
     * Get entity transition history
     *
     * @param string $entity_type Entity type
     * @param int $entity_id Entity ID
     * @param int $limit Number of records
     * @return array Transition history
     */
    public function getEntityHistory(string $entity_type, int $entity_id, int $limit = 0): array {
        return $this->log_model->getEntityHistory($entity_type, $entity_id, $limit);
    }

    // ========================================
    // VALIDATION & GUARD METHODS
    // ========================================

    /**
     * Validate transition parameters
     *
     * @param array $params Parameters to validate
     * @return array Validation result
     */
    private function validateParams(array $params): array {
        $errors = [];

        // Required parameters
        $required = ['entity_type', 'entity_id', 'transition_id'];
        foreach ($required as $field) {
            if (empty($params[$field])) {
                $errors[] = sprintf(__('%s is required', 'wp-state-machine'), $field);
            }
        }

        if (!empty($errors)) {
            return [
                'valid' => false,
                'message' => implode(', ', $errors)
            ];
        }

        // Get machine
        $machine = null;
        if (!empty($params['machine_slug'])) {
            $machine = $this->machine_model->findBySlug($params['machine_slug']);
        } elseif (!empty($params['machine_id'])) {
            $machine = $this->machine_model->find($params['machine_id']);
        }

        if (!$machine) {
            return [
                'valid' => false,
                'message' => __('State machine not found', 'wp-state-machine')
            ];
        }

        // Get transition
        $transition = $this->transition_model->find($params['transition_id']);
        if (!$transition) {
            return [
                'valid' => false,
                'message' => __('Transition not found', 'wp-state-machine')
            ];
        }

        // Verify transition belongs to machine
        if ($transition->machine_id != $machine->id) {
            return [
                'valid' => false,
                'message' => __('Transition does not belong to this machine', 'wp-state-machine')
            ];
        }

        // Get current state
        $current_state = $this->getCurrentState(
            $params['entity_type'],
            $params['entity_id'],
            $machine->id
        );

        // Get from and to states
        $from_state = $this->state_model->find($transition->from_state_id);
        $to_state = $this->state_model->find($transition->to_state_id);

        if (!$to_state) {
            return [
                'valid' => false,
                'message' => __('Target state not found', 'wp-state-machine')
            ];
        }

        return [
            'valid' => true,
            'machine' => $machine,
            'transition' => $transition,
            'current_state' => $current_state,
            'from_state' => $from_state,
            'to_state' => $to_state
        ];
    }

    /**
     * Check guard permissions
     *
     * @param object $transition Transition object
     * @param int $entity_id Entity ID
     * @param int $user_id User ID
     * @param array $params Additional parameters
     * @return array Guard result
     */
    private function checkGuard(object $transition, int $entity_id, int $user_id, array $params): array {
        try {
            // Create guard instance
            $guard = GuardFactory::create($transition->guard_class, $this->debug);

            // Build context
            $context = [
                'transition' => $transition,
                'entity_data' => $params['entity_data'] ?? null,
                'metadata' => $params['metadata'] ?? []
            ];

            // Check guard
            $result = $guard->check($entity_id, $user_id, $context);

            $this->debug('Guard checked', [
                'guard' => $transition->guard_class,
                'allowed' => $result['allowed'],
                'message' => $result['message']
            ]);

            return $result;

        } catch (\Exception $e) {
            $this->debug('Guard check failed', [
                'guard' => $transition->guard_class,
                'error' => $e->getMessage()
            ]);

            return [
                'allowed' => false,
                'message' => sprintf(
                    __('Guard check failed: %s', 'wp-state-machine'),
                    $e->getMessage()
                ),
                'code' => 'guard_error',
                'data' => ['exception' => $e->getMessage()]
            ];
        }
    }

    // ========================================
    // HELPER METHODS
    // ========================================

    /**
     * Build context for hooks
     *
     * @param array $params Parameters
     * @param object|null $from_state From state
     * @param object $to_state To state
     * @param object $transition Transition
     * @return array Context
     */
    private function buildContext(array $params, ?object $from_state, object $to_state, object $transition): array {
        return [
            'entity_type' => $params['entity_type'],
            'entity_id' => $params['entity_id'],
            'from_state' => $from_state,
            'to_state' => $to_state,
            'transition' => $transition,
            'user_id' => $params['user_id'] ?? get_current_user_id(),
            'comment' => $params['comment'] ?? null,
            'metadata' => $params['metadata'] ?? null,
            'entity_data' => $params['entity_data'] ?? null
        ];
    }

    /**
     * Fire transition failed hook
     *
     * @param array $params Parameters
     * @param string $error Error message
     * @param string $error_code Error code
     * @return void
     */
    private function fireTransitionFailed(array $params, string $error, string $error_code): void {
        do_action('wp_state_machine_transition_failed', [
            'entity_type' => $params['entity_type'] ?? null,
            'entity_id' => $params['entity_id'] ?? null,
            'transition_id' => $params['transition_id'] ?? null,
            'error' => $error,
            'error_code' => $error_code,
            'user_id' => $params['user_id'] ?? get_current_user_id()
        ]);
    }

    /**
     * Create success result
     *
     * @param string $message Success message
     * @param array $data Additional data
     * @return array Success result
     */
    private function success(string $message, array $data = []): array {
        return [
            'allowed' => true,
            'message' => $message,
            'code' => 'success',
            'data' => $data
        ];
    }

    /**
     * Create failure result
     *
     * @param string $message Failure message
     * @param string $code Error code
     * @param array $data Additional data
     * @return array Failure result
     */
    private function failure(string $message, string $code, array $data = []): array {
        return [
            'allowed' => false,
            'message' => $message,
            'code' => $code,
            'data' => $data
        ];
    }

    /**
     * Debug log
     *
     * @param string $message Log message
     * @param array $data Additional data
     * @return void
     */
    private function debug(string $message, array $data = []): void {
        if (!$this->debug) {
            return;
        }

        if (defined('WP_DEBUG_LOG') && WP_DEBUG_LOG) {
            $log_message = '[StateMachineEngine] ' . $message;
            if (!empty($data)) {
                $log_message .= ' | ' . wp_json_encode($data);
            }
            error_log($log_message);
        }
    }

    /**
     * Enable or disable debug logging
     *
     * @param bool $enable Enable debug
     * @return self
     */
    public function setDebug(bool $enable): self {
        $this->debug = $enable;
        return $this;
    }
}
