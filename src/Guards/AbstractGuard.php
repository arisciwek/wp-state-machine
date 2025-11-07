<?php
/**
 * Abstract Guard Base Class
 *
 * @package     WP_State_Machine
 * @subpackage  Guards
 * @version     1.0.0
 * @author      arisciwek
 *
 * Path: /wp-state-machine/src/Guards/AbstractGuard.php
 *
 * Description: Base implementation for all guards.
 *              Provides common functionality and helper methods.
 *              Reduces code duplication across concrete guards.
 *
 * Provides:
 * - Configuration management (setConfig, getConfig)
 * - Structured result formatting
 * - Common validation helpers
 * - Logging support
 *
 * Child Classes Must Implement:
 * - check(): Core guard logic
 * - getName(): Guard name
 * - getDescription(): Guard description
 * - validateConfig(): Configuration validation
 *
 * Usage:
 * ```php
 * class RoleGuard extends AbstractGuard {
 *     public function check(int $entity_id, int $user_id, array $context = []): array {
 *         // Check user role
 *         if ($this->userHasRole($user_id, $this->config)) {
 *             return $this->success('User has required role');
 *         }
 *         return $this->failure('User does not have required role', 'insufficient_role');
 *     }
 *
 *     public function getName(): string {
 *         return 'Role Guard';
 *     }
 *
 *     public function getDescription(): string {
 *         return 'Checks if user has required role(s)';
 *     }
 * }
 * ```
 *
 * Benefits:
 * - ~50 lines saved per guard implementation
 * - Consistent result format
 * - Built-in logging support
 * - Easy to extend
 *
 * Changelog:
 * 1.0.0 - 2025-11-07
 * - Initial creation
 * - Configuration management
 * - Result formatting helpers
 * - Common validation methods
 */

namespace WPStateMachine\Guards;

defined('ABSPATH') || exit;

abstract class AbstractGuard implements GuardInterface {

    /**
     * Guard configuration parameters
     *
     * @var array
     */
    protected $config = [];

    /**
     * Whether to log guard checks
     *
     * @var bool
     */
    protected $enable_logging = false;

    // ========================================
    // ABSTRACT METHODS (Must be implemented)
    // ========================================

    /**
     * Check if transition is allowed
     * Child classes must implement their specific logic
     *
     * @param int $entity_id Entity being transitioned
     * @param int $user_id User attempting transition
     * @param array $context Additional context
     * @return array Result array
     */
    abstract public function check(int $entity_id, int $user_id, array $context = []): array;

    /**
     * Get guard name
     * Child classes must provide their name
     *
     * @return string Guard name
     */
    abstract public function getName(): string;

    /**
     * Get guard description
     * Child classes must provide their description
     *
     * @return string Guard description
     */
    abstract public function getDescription(): string;

    /**
     * Validate configuration
     * Child classes must validate their specific config requirements
     *
     * @param array $config Configuration to validate
     * @return array Validation errors
     */
    abstract public function validateConfig(array $config): array;

    // ========================================
    // CONCRETE METHODS (Inherited by children)
    // ========================================

    /**
     * Set configuration parameters
     *
     * @param array $config Configuration parameters
     * @return self
     */
    public function setConfig(array $config): self {
        $this->config = $config;
        return $this;
    }

    /**
     * Get configuration parameters
     *
     * @return array Configuration
     */
    public function getConfig(): array {
        return $this->config;
    }

    /**
     * Enable or disable logging
     *
     * @param bool $enable Whether to enable logging
     * @return self
     */
    public function enableLogging(bool $enable = true): self {
        $this->enable_logging = $enable;
        return $this;
    }

    // ========================================
    // HELPER METHODS
    // ========================================

    /**
     * Create success result
     *
     * @param string $message Success message
     * @param array $data Additional data
     * @return array Success result
     */
    protected function success(string $message = '', array $data = []): array {
        $result = [
            'allowed' => true,
            'message' => $message ?: __('Check passed', 'wp-state-machine'),
            'code' => 'success',
            'data' => $data
        ];

        $this->log('success', $result);

        return $result;
    }

    /**
     * Create failure result
     *
     * @param string $message Failure message
     * @param string $code Error code
     * @param array $data Additional data
     * @return array Failure result
     */
    protected function failure(string $message, string $code = 'guard_failed', array $data = []): array {
        $result = [
            'allowed' => false,
            'message' => $message,
            'code' => $code,
            'data' => $data
        ];

        $this->log('failure', $result);

        return $result;
    }

    /**
     * Get user object
     *
     * @param int $user_id User ID
     * @return \WP_User|false User object or false
     */
    protected function getUser(int $user_id) {
        $user = get_userdata($user_id);
        return $user ? $user : false;
    }

    /**
     * Check if user exists
     *
     * @param int $user_id User ID
     * @return bool True if user exists
     */
    protected function userExists(int $user_id): bool {
        return (bool) get_userdata($user_id);
    }

    /**
     * Get entity data from context
     *
     * @param array $context Context array
     * @return array|null Entity data or null
     */
    protected function getEntityData(array $context): ?array {
        return $context['entity_data'] ?? null;
    }

    /**
     * Get transition data from context
     *
     * @param array $context Context array
     * @return object|null Transition object or null
     */
    protected function getTransition(array $context): ?object {
        return $context['transition'] ?? null;
    }

    /**
     * Get machine data from context
     *
     * @param array $context Context array
     * @return object|null Machine object or null
     */
    protected function getMachine(array $context): ?object {
        return $context['machine'] ?? null;
    }

    /**
     * Log guard check result
     *
     * @param string $type Log type (success|failure)
     * @param array $result Result data
     * @return void
     */
    protected function log(string $type, array $result): void {
        if (!$this->enable_logging) {
            return;
        }

        // Use WordPress debug log
        if (defined('WP_DEBUG') && WP_DEBUG && defined('WP_DEBUG_LOG') && WP_DEBUG_LOG) {
            $log_message = sprintf(
                '[WP-State-Machine] Guard %s - %s: %s',
                $this->getName(),
                $type,
                $result['message']
            );

            if (!empty($result['data'])) {
                $log_message .= ' | Data: ' . wp_json_encode($result['data']);
            }

            error_log($log_message);
        }

        // Hook for custom logging
        do_action('wp_state_machine_guard_checked', [
            'guard' => $this->getName(),
            'type' => $type,
            'result' => $result,
            'config' => $this->config
        ]);
    }

    /**
     * Check if config has required keys
     *
     * @param array $config Config to check
     * @param array $required_keys Required keys
     * @return array Missing keys
     */
    protected function checkRequiredKeys(array $config, array $required_keys): array {
        $missing = [];

        foreach ($required_keys as $key) {
            if (!isset($config[$key]) || empty($config[$key])) {
                $missing[] = $key;
            }
        }

        return $missing;
    }

    /**
     * Format validation error
     *
     * @param string $key Config key with error
     * @param string $message Error message
     * @return string Formatted error
     */
    protected function validationError(string $key, string $message): string {
        return sprintf('%s: %s', $key, $message);
    }
}
