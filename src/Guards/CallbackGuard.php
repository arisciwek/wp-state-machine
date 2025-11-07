<?php
/**
 * Callback Guard
 *
 * @package     WP_State_Machine
 * @subpackage  Guards
 * @version     1.0.0
 * @author      arisciwek
 *
 * Path: /wp-state-machine/src/Guards/CallbackGuard.php
 *
 * Description: Executes custom validation logic via WordPress hooks.
 *              Most flexible guard type for complex business rules.
 *              Plugin developers register callbacks via add_filter().
 *
 * Configuration Format:
 * ```
 * "CallbackGuard:callback_name"
 * ```
 *
 * Hook Format:
 * ```
 * wp_state_machine_guard_callback_{callback_name}
 * ```
 *
 * Usage Examples:
 *
 * Example 1: Simple custom check
 * ```php
 * // In transition configuration:
 * $transition->guard_class = 'CallbackGuard:check_business_hours';
 *
 * // In plugin code:
 * add_filter('wp_state_machine_guard_callback_check_business_hours', function($result, $entity_id, $user_id, $context) {
 *     $current_hour = intval(date('H'));
 *
 *     if ($current_hour >= 9 && $current_hour <= 17) {
 *         return [
 *             'allowed' => true,
 *             'message' => 'Within business hours',
 *             'code' => 'success',
 *             'data' => ['hour' => $current_hour]
 *         ];
 *     }
 *
 *     return [
 *         'allowed' => false,
 *         'message' => 'Outside business hours (9 AM - 5 PM)',
 *         'code' => 'outside_business_hours',
 *         'data' => ['hour' => $current_hour]
 *     ];
 * }, 10, 4);
 * ```
 *
 * Example 2: Check order value
 * ```php
 * $transition->guard_class = 'CallbackGuard:check_order_value';
 *
 * add_filter('wp_state_machine_guard_callback_check_order_value', function($result, $entity_id, $user_id, $context) {
 *     $entity_data = $context['entity_data'] ?? null;
 *     $order_total = $entity_data['total'] ?? 0;
 *
 *     // Require manager approval for orders over $1000
 *     if ($order_total > 1000 && !current_user_can('manage_options')) {
 *         return [
 *             'allowed' => false,
 *             'message' => 'Orders over $1000 require manager approval',
 *             'code' => 'requires_manager_approval',
 *             'data' => ['order_total' => $order_total]
 *         ];
 *     }
 *
 *     return [
 *         'allowed' => true,
 *         'message' => 'Order value approved',
 *         'code' => 'success',
 *         'data' => ['order_total' => $order_total]
 *     ];
 * }, 10, 4);
 * ```
 *
 * Example 3: Check inventory
 * ```php
 * $transition->guard_class = 'CallbackGuard:check_inventory';
 *
 * add_filter('wp_state_machine_guard_callback_check_inventory', function($result, $entity_id, $user_id, $context) {
 *     $entity_data = $context['entity_data'] ?? null;
 *     $product_id = $entity_data['product_id'] ?? 0;
 *     $quantity = $entity_data['quantity'] ?? 0;
 *
 *     $available = get_product_inventory($product_id);
 *
 *     if ($available >= $quantity) {
 *         return [
 *             'allowed' => true,
 *             'message' => sprintf('Inventory available: %d', $available),
 *             'code' => 'success',
 *             'data' => ['available' => $available, 'requested' => $quantity]
 *         ];
 *     }
 *
 *     return [
 *         'allowed' => false,
 *         'message' => sprintf('Insufficient inventory. Available: %d, Requested: %d', $available, $quantity),
 *         'code' => 'insufficient_inventory',
 *         'data' => ['available' => $available, 'requested' => $quantity]
 *     ];
 * }, 10, 4);
 * ```
 *
 * Example 4: Multiple conditions
 * ```php
 * $transition->guard_class = 'CallbackGuard:approve_document';
 *
 * add_filter('wp_state_machine_guard_callback_approve_document', function($result, $entity_id, $user_id, $context) {
 *     $entity_data = $context['entity_data'] ?? null;
 *
 *     // Check 1: Document must be reviewed
 *     if (empty($entity_data['reviewed_by'])) {
 *         return [
 *             'allowed' => false,
 *             'message' => 'Document must be reviewed before approval',
 *             'code' => 'not_reviewed',
 *             'data' => []
 *         ];
 *     }
 *
 *     // Check 2: User must be senior staff
 *     $user_level = get_user_meta($user_id, 'staff_level', true);
 *     if ($user_level !== 'senior') {
 *         return [
 *             'allowed' => false,
 *             'message' => 'Only senior staff can approve documents',
 *             'code' => 'insufficient_level',
 *             'data' => ['user_level' => $user_level]
 *         ];
 *     }
 *
 *     // Check 3: Check approval limit
 *     $approval_limit = get_user_meta($user_id, 'approval_limit', true);
 *     if ($entity_data['value'] > $approval_limit) {
 *         return [
 *             'allowed' => false,
 *             'message' => sprintf('Value exceeds your approval limit of $%d', $approval_limit),
 *             'code' => 'exceeds_approval_limit',
 *             'data' => ['limit' => $approval_limit, 'value' => $entity_data['value']]
 *         ];
 *     }
 *
 *     return [
 *         'allowed' => true,
 *         'message' => 'All approval conditions met',
 *         'code' => 'success',
 *         'data' => []
 *     ];
 * }, 10, 4);
 * ```
 *
 * Callback Parameters:
 * @param array $result Default result (can be modified)
 * @param int $entity_id Entity being transitioned
 * @param int $user_id User attempting transition
 * @param array $context Full context including entity_data, transition, machine
 *
 * Return Structure:
 * ```php
 * [
 *     'allowed' => true|false,        // Required
 *     'message' => 'Result message',  // Required
 *     'code' => 'success|error_code', // Required
 *     'data' => [...]                 // Optional
 * ]
 * ```
 *
 * Benefits:
 * - Ultimate flexibility for custom business logic
 * - Access to full WordPress environment
 * - Can integrate with other plugins/systems
 * - Multiple callbacks can be registered for same name
 * - Easy to test and maintain separately
 *
 * Changelog:
 * 1.0.0 - 2025-11-07
 * - Initial creation for Prioritas #4
 * - WordPress filter-based callbacks
 * - Support for multiple callback handlers
 * - Detailed documentation and examples
 */

namespace WPStateMachine\Guards;

defined('ABSPATH') || exit;

class CallbackGuard extends AbstractGuard {

    /**
     * Check transition using registered callback
     *
     * @param int $entity_id Entity being transitioned
     * @param int $user_id User attempting transition
     * @param array $context Additional context
     * @return array Result array
     */
    public function check(int $entity_id, int $user_id, array $context = []): array {
        // Get callback name from config
        $callback_name = $this->getCallbackName();

        if (!$callback_name) {
            return $this->failure(
                __('Callback name not configured', 'wp-state-machine'),
                'invalid_config',
                []
            );
        }

        // Build hook name
        $hook_name = $this->getHookName($callback_name);

        // Check if any callbacks are registered
        if (!has_filter($hook_name)) {
            return $this->failure(
                sprintf(
                    __('No callback registered for "%s". Hook: %s', 'wp-state-machine'),
                    $callback_name,
                    $hook_name
                ),
                'no_callback_registered',
                [
                    'callback_name' => $callback_name,
                    'hook_name' => $hook_name
                ]
            );
        }

        // Default result (will be passed to filter)
        $default_result = [
            'allowed' => false,
            'message' => __('Callback did not return a result', 'wp-state-machine'),
            'code' => 'no_result',
            'data' => []
        ];

        // Execute callback via filter
        $result = apply_filters(
            $hook_name,
            $default_result,
            $entity_id,
            $user_id,
            $context
        );

        // Validate result structure
        $validation_errors = $this->validateCallbackResult($result);
        if (!empty($validation_errors)) {
            return $this->failure(
                sprintf(
                    __('Invalid callback result: %s', 'wp-state-machine'),
                    implode(', ', $validation_errors)
                ),
                'invalid_callback_result',
                [
                    'callback_name' => $callback_name,
                    'errors' => $validation_errors,
                    'result' => $result
                ]
            );
        }

        // Log and return result
        if ($result['allowed']) {
            return $this->success(
                $result['message'],
                $result['data'] ?? []
            );
        } else {
            return $this->failure(
                $result['message'],
                $result['code'] ?? 'callback_failed',
                $result['data'] ?? []
            );
        }
    }

    /**
     * Get guard name
     *
     * @return string
     */
    public function getName(): string {
        return __('Callback Guard', 'wp-state-machine');
    }

    /**
     * Get guard description
     *
     * @return string
     */
    public function getDescription(): string {
        return __('Executes custom validation logic via WordPress filter hooks', 'wp-state-machine');
    }

    /**
     * Validate configuration
     *
     * @param array $config Configuration to validate
     * @return array Validation errors
     */
    public function validateConfig(array $config): array {
        $errors = [];

        // Config should be array with single element (callback name)
        if (empty($config)) {
            $errors[] = __('Callback name must be specified', 'wp-state-machine');
            return $errors;
        }

        if (count($config) > 1) {
            $errors[] = __('Only one callback name should be specified', 'wp-state-machine');
        }

        $callback_name = $config[0] ?? null;
        if (!is_string($callback_name)) {
            $errors[] = sprintf(
                __('Callback name must be a string, %s given', 'wp-state-machine'),
                gettype($callback_name)
            );
        }

        if (is_string($callback_name) && trim($callback_name) === '') {
            $errors[] = __('Callback name cannot be empty', 'wp-state-machine');
        }

        // Validate callback name format (alphanumeric, underscore, dash)
        if (is_string($callback_name) && !preg_match('/^[a-z0-9_-]+$/i', $callback_name)) {
            $errors[] = __('Callback name can only contain letters, numbers, underscores, and dashes', 'wp-state-machine');
        }

        return $errors;
    }

    // ========================================
    // HELPER METHODS
    // ========================================

    /**
     * Get callback name from config
     *
     * @return string|null Callback name
     */
    protected function getCallbackName(): ?string {
        if (empty($this->config)) {
            return null;
        }

        return trim($this->config[0]);
    }

    /**
     * Build WordPress hook name from callback name
     *
     * @param string $callback_name Callback name
     * @return string Hook name
     */
    protected function getHookName(string $callback_name): string {
        return 'wp_state_machine_guard_callback_' . $callback_name;
    }

    /**
     * Validate callback result structure
     *
     * @param mixed $result Result to validate
     * @return array Validation errors
     */
    protected function validateCallbackResult($result): array {
        $errors = [];

        // Must be array
        if (!is_array($result)) {
            $errors[] = sprintf(
                __('Result must be an array, %s given', 'wp-state-machine'),
                gettype($result)
            );
            return $errors;
        }

        // Required fields
        $required_fields = ['allowed', 'message', 'code'];
        foreach ($required_fields as $field) {
            if (!isset($result[$field])) {
                $errors[] = sprintf(
                    __('Missing required field: %s', 'wp-state-machine'),
                    $field
                );
            }
        }

        // Validate field types
        if (isset($result['allowed']) && !is_bool($result['allowed'])) {
            $errors[] = __('Field "allowed" must be boolean', 'wp-state-machine');
        }

        if (isset($result['message']) && !is_string($result['message'])) {
            $errors[] = __('Field "message" must be string', 'wp-state-machine');
        }

        if (isset($result['code']) && !is_string($result['code'])) {
            $errors[] = __('Field "code" must be string', 'wp-state-machine');
        }

        if (isset($result['data']) && !is_array($result['data'])) {
            $errors[] = __('Field "data" must be array', 'wp-state-machine');
        }

        return $errors;
    }
}
