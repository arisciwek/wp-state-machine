<?php
/**
 * Guard Factory
 *
 * @package     WP_State_Machine
 * @subpackage  Guards
 * @version     1.0.0
 * @author      arisciwek
 *
 * Path: /wp-state-machine/src/Guards/GuardFactory.php
 *
 * Description: Factory for creating guard instances.
 *              Parses guard_class string and instantiates appropriate guard.
 *              Handles configuration parsing and validation.
 *
 * Guard Class Format:
 * ```
 * "GuardType:param1,param2,param3"
 * ```
 *
 * Supported Guards:
 * - RoleGuard: Check user roles
 * - CapabilityGuard: Check user capabilities
 * - OwnerGuard: Check entity ownership
 * - CallbackGuard: Custom validation logic
 *
 * Usage Examples:
 *
 * Example 1: Create RoleGuard
 * ```php
 * $guard = GuardFactory::create('RoleGuard:administrator,editor');
 * $result = $guard->check($entity_id, $user_id, $context);
 * ```
 *
 * Example 2: Create CapabilityGuard
 * ```php
 * $guard = GuardFactory::create('CapabilityGuard:manage_options');
 * ```
 *
 * Example 3: Create OwnerGuard
 * ```php
 * $guard = GuardFactory::create('OwnerGuard:author_id');
 * ```
 *
 * Example 4: Create CallbackGuard
 * ```php
 * $guard = GuardFactory::create('CallbackGuard:check_business_hours');
 * ```
 *
 * Example 5: From transition object
 * ```php
 * $transition = $transition_model->find($transition_id);
 * if (!empty($transition->guard_class)) {
 *     $guard = GuardFactory::create($transition->guard_class);
 *     $result = $guard->check($entity_id, $user_id, $context);
 *
 *     if (!$result['allowed']) {
 *         wp_send_json_error(['message' => $result['message']]);
 *     }
 * }
 * ```
 *
 * Parsing Logic:
 * ```
 * Input: "RoleGuard:administrator,editor"
 * ↓
 * Guard Type: "RoleGuard"
 * Config: ["administrator", "editor"]
 * ↓
 * Instantiate: new RoleGuard()
 * ↓
 * Configure: $guard->setConfig(["administrator", "editor"])
 * ↓
 * Validate: $guard->validateConfig(["administrator", "editor"])
 * ```
 *
 * Error Handling:
 * ```php
 * try {
 *     $guard = GuardFactory::create('InvalidGuard:test');
 * } catch (\Exception $e) {
 *     // Handle: "Unknown guard type: InvalidGuard"
 * }
 * ```
 *
 * Custom Guards:
 * Plugin developers can register custom guards via filter:
 * ```php
 * add_filter('wp_state_machine_guard_types', function($types) {
 *     $types['CustomGuard'] = 'MyPlugin\\Guards\\CustomGuard';
 *     return $types;
 * });
 * ```
 *
 * Benefits:
 * - Centralized guard instantiation
 * - Consistent configuration parsing
 * - Extensible for custom guards
 * - Built-in validation
 * - Clear error messages
 *
 * Changelog:
 * 1.0.0 - 2025-11-07
 * - Initial creation for Prioritas #4
 * - Support for all default guards
 * - Configuration parsing
 * - Validation integration
 * - Custom guard registration
 */

namespace WPStateMachine\Guards;

defined('ABSPATH') || exit;

class GuardFactory {

    /**
     * Default guard type mapping
     *
     * @var array
     */
    private static $guard_types = [
        'RoleGuard' => RoleGuard::class,
        'CapabilityGuard' => CapabilityGuard::class,
        'OwnerGuard' => OwnerGuard::class,
        'CallbackGuard' => CallbackGuard::class,
    ];

    /**
     * Create guard instance from guard_class string
     *
     * @param string $guard_class Guard class string (e.g., "RoleGuard:administrator,editor")
     * @param bool $enable_logging Enable guard logging
     * @return GuardInterface Guard instance
     * @throws \Exception If guard type is invalid or configuration is invalid
     */
    public static function create(string $guard_class, bool $enable_logging = false): GuardInterface {
        // Parse guard class string
        $parsed = self::parseGuardClass($guard_class);

        if (!$parsed) {
            throw new \Exception(
                sprintf(__('Invalid guard class format: %s', 'wp-state-machine'), $guard_class)
            );
        }

        $guard_type = $parsed['type'];
        $config = $parsed['config'];

        // Get guard type mapping (allow filtering for custom guards)
        $guard_types = apply_filters('wp_state_machine_guard_types', self::$guard_types);

        // Check if guard type exists
        if (!isset($guard_types[$guard_type])) {
            throw new \Exception(
                sprintf(
                    __('Unknown guard type: %s. Available types: %s', 'wp-state-machine'),
                    $guard_type,
                    implode(', ', array_keys($guard_types))
                )
            );
        }

        // Get guard class name
        $guard_class_name = $guard_types[$guard_type];

        // Check if class exists
        if (!class_exists($guard_class_name)) {
            throw new \Exception(
                sprintf(__('Guard class not found: %s', 'wp-state-machine'), $guard_class_name)
            );
        }

        // Instantiate guard
        $guard = new $guard_class_name();

        // Verify guard implements interface
        if (!$guard instanceof GuardInterface) {
            throw new \Exception(
                sprintf(
                    __('Guard class must implement GuardInterface: %s', 'wp-state-machine'),
                    $guard_class_name
                )
            );
        }

        // Set configuration
        $guard->setConfig($config);

        // Enable logging if requested
        if ($enable_logging && method_exists($guard, 'enableLogging')) {
            $guard->enableLogging(true);
        }

        // Validate configuration
        $validation_errors = $guard->validateConfig($config);
        if (!empty($validation_errors)) {
            throw new \Exception(
                sprintf(
                    __('Invalid guard configuration for %s: %s', 'wp-state-machine'),
                    $guard_type,
                    implode(', ', $validation_errors)
                )
            );
        }

        // Hook for guard post-creation customization
        do_action('wp_state_machine_guard_created', $guard, $guard_type, $config);

        return $guard;
    }

    /**
     * Parse guard_class string into type and config
     *
     * Format: "GuardType:param1,param2,param3"
     *
     * @param string $guard_class Guard class string
     * @return array|null Parsed data or null if invalid
     */
    public static function parseGuardClass(string $guard_class): ?array {
        $guard_class = trim($guard_class);

        if (empty($guard_class)) {
            return null;
        }

        // Split by first colon
        $parts = explode(':', $guard_class, 2);

        $guard_type = trim($parts[0]);
        $config_string = isset($parts[1]) ? trim($parts[1]) : '';

        // Parse configuration
        $config = [];
        if (!empty($config_string)) {
            // Split by comma
            $config = array_map('trim', explode(',', $config_string));
            // Remove empty values
            $config = array_filter($config, function($value) {
                return $value !== '';
            });
        }

        return [
            'type' => $guard_type,
            'config' => $config
        ];
    }

    /**
     * Get list of available guard types
     *
     * @return array Guard type names
     */
    public static function getAvailableGuards(): array {
        $guard_types = apply_filters('wp_state_machine_guard_types', self::$guard_types);
        return array_keys($guard_types);
    }

    /**
     * Check if guard type exists
     *
     * @param string $guard_type Guard type name
     * @return bool True if guard type exists
     */
    public static function guardExists(string $guard_type): bool {
        $guard_types = apply_filters('wp_state_machine_guard_types', self::$guard_types);
        return isset($guard_types[$guard_type]);
    }

    /**
     * Get guard information
     * Returns name and description for all available guards
     *
     * @return array Guard information
     */
    public static function getGuardInfo(): array {
        $info = [];
        $guard_types = apply_filters('wp_state_machine_guard_types', self::$guard_types);

        foreach ($guard_types as $type => $class) {
            try {
                if (class_exists($class)) {
                    $guard = new $class();
                    if ($guard instanceof GuardInterface) {
                        $info[$type] = [
                            'name' => $guard->getName(),
                            'description' => $guard->getDescription(),
                            'class' => $class
                        ];
                    }
                }
            } catch (\Exception $e) {
                // Skip guards that can't be instantiated
                continue;
            }
        }

        return $info;
    }

    /**
     * Validate guard_class string format
     * Checks if string is valid without instantiating guard
     *
     * @param string $guard_class Guard class string
     * @return array Validation result ['valid' => bool, 'errors' => array]
     */
    public static function validate(string $guard_class): array {
        $errors = [];

        // Parse guard class
        $parsed = self::parseGuardClass($guard_class);

        if (!$parsed) {
            return [
                'valid' => false,
                'errors' => [__('Invalid guard class format', 'wp-state-machine')]
            ];
        }

        $guard_type = $parsed['type'];
        $config = $parsed['config'];

        // Check if guard type exists
        if (!self::guardExists($guard_type)) {
            $errors[] = sprintf(
                __('Unknown guard type: %s', 'wp-state-machine'),
                $guard_type
            );
        }

        return [
            'valid' => empty($errors),
            'errors' => $errors,
            'parsed' => $parsed
        ];
    }
}
