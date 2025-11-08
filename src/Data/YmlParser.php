<?php
/**
 * YML Parser for Workflow Definitions
 *
 * @package     WP_State_Machine
 * @subpackage  Data
 * @version     1.0.0
 * @author      arisciwek
 *
 * Path: /wp-state-machine/src/Data/YmlParser.php
 *
 * Description: Parse YML workflow definition files.
 *              Validates structure and returns normalized data.
 *              Supports workflow groups, machines, states, and transitions.
 *
 * Dependencies:
 * - Symfony YAML Component (symfony/yaml)
 * - PHP >= 7.4
 *
 * YML Structure Expected:
 * ```yaml
 * workflow_group:
 *   name: "Blog Management"
 *   slug: "blog-management"
 *   description: "..."
 *
 * state_machine:
 *   name: "Blog Post Workflow"
 *   slug: "blog-post-workflow"
 *   entity_type: "post"
 *   plugin_slug: "wp-state-machine"
 *
 * states:
 *   - name: "Draft"
 *     slug: "draft"
 *     type: "initial"
 *   - name: "Review"
 *     slug: "review"
 *     type: "intermediate"
 *
 * transitions:
 *   - name: "Submit for Review"
 *     from_state: "draft"
 *     to_state: "review"
 * ```
 *
 * Changelog:
 * 1.0.0 - 2025-11-08
 * - Initial creation
 * - YML parsing with Symfony YAML
 * - Structure validation
 * - Error handling
 */

namespace WPStateMachine\Data;

use Symfony\Component\Yaml\Yaml;
use Symfony\Component\Yaml\Exception\ParseException;

defined('ABSPATH') || exit;

class YmlParser {
    /**
     * Parse YML file
     *
     * @param string $file_path Path to YML file
     * @return array Parsed and validated data
     * @throws \Exception If file not found or invalid structure
     */
    public static function parseFile(string $file_path): array {
        // Check if file exists
        if (!file_exists($file_path)) {
            throw new \Exception("YML file not found: {$file_path}");
        }

        // Check if Symfony YAML component is available
        if (!class_exists('\Symfony\Component\Yaml\Yaml')) {
            throw new \Exception("Symfony YAML component not found. Please run: composer require symfony/yaml");
        }

        try {
            // Parse YML file
            $data = Yaml::parseFile($file_path);

            // Validate structure
            self::validate($data, $file_path);

            // Normalize data
            return self::normalize($data);

        } catch (ParseException $e) {
            throw new \Exception("Failed to parse YML file {$file_path}: " . $e->getMessage());
        }
    }

    /**
     * Validate YML data structure
     *
     * @param array $data Parsed YML data
     * @param string $file_path File path for error messages
     * @return void
     * @throws \Exception If structure is invalid
     */
    private static function validate(array $data, string $file_path): void {
        // Required top-level keys
        $required_keys = ['workflow_group', 'state_machine', 'states', 'transitions'];

        foreach ($required_keys as $key) {
            if (!isset($data[$key])) {
                throw new \Exception("Missing required key '{$key}' in {$file_path}");
            }
        }

        // Validate workflow_group
        self::validateWorkflowGroup($data['workflow_group'], $file_path);

        // Validate state_machine
        self::validateStateMachine($data['state_machine'], $file_path);

        // Validate states
        self::validateStates($data['states'], $file_path);

        // Validate transitions
        self::validateTransitions($data['transitions'], $data['states'], $file_path);
    }

    /**
     * Validate workflow group structure
     *
     * @param array $group Workflow group data
     * @param string $file_path File path for error messages
     * @return void
     * @throws \Exception If invalid
     */
    private static function validateWorkflowGroup(array $group, string $file_path): void {
        $required = ['name', 'slug', 'description'];

        foreach ($required as $field) {
            if (!isset($group[$field]) || empty($group[$field])) {
                throw new \Exception("Missing or empty '{$field}' in workflow_group in {$file_path}");
            }
        }
    }

    /**
     * Validate state machine structure
     *
     * @param array $machine State machine data
     * @param string $file_path File path for error messages
     * @return void
     * @throws \Exception If invalid
     */
    private static function validateStateMachine(array $machine, string $file_path): void {
        $required = ['name', 'slug', 'entity_type', 'plugin_slug'];

        foreach ($required as $field) {
            if (!isset($machine[$field]) || empty($machine[$field])) {
                throw new \Exception("Missing or empty '{$field}' in state_machine in {$file_path}");
            }
        }
    }

    /**
     * Validate states array
     *
     * @param array $states States data
     * @param string $file_path File path for error messages
     * @return void
     * @throws \Exception If invalid
     */
    private static function validateStates(array $states, string $file_path): void {
        if (empty($states)) {
            throw new \Exception("States array is empty in {$file_path}");
        }

        $state_slugs = [];
        $has_initial = false;
        $has_final = false;

        foreach ($states as $index => $state) {
            // Required fields
            if (!isset($state['name']) || !isset($state['slug']) || !isset($state['type'])) {
                throw new \Exception("State at index {$index} missing required fields (name, slug, type) in {$file_path}");
            }

            // Check duplicate slugs
            if (in_array($state['slug'], $state_slugs)) {
                throw new \Exception("Duplicate state slug '{$state['slug']}' in {$file_path}");
            }
            $state_slugs[] = $state['slug'];

            // Check state type
            $valid_types = ['initial', 'intermediate', 'final'];
            if (!in_array($state['type'], $valid_types)) {
                throw new \Exception("Invalid state type '{$state['type']}' for state '{$state['slug']}' in {$file_path}. Must be: " . implode(', ', $valid_types));
            }

            // Track initial and final states
            if ($state['type'] === 'initial') {
                if ($has_initial) {
                    throw new \Exception("Multiple initial states found in {$file_path}. Only one initial state allowed.");
                }
                $has_initial = true;
            }

            if ($state['type'] === 'final') {
                $has_final = true;
            }
        }

        // Must have at least one initial state
        if (!$has_initial) {
            throw new \Exception("No initial state found in {$file_path}. At least one state must have type: initial");
        }

        // Must have at least one final state
        if (!$has_final) {
            throw new \Exception("No final state found in {$file_path}. At least one state must have type: final");
        }
    }

    /**
     * Validate transitions array
     *
     * @param array $transitions Transitions data
     * @param array $states States data for reference
     * @param string $file_path File path for error messages
     * @return void
     * @throws \Exception If invalid
     */
    private static function validateTransitions(array $transitions, array $states, string $file_path): void {
        if (empty($transitions)) {
            throw new \Exception("Transitions array is empty in {$file_path}");
        }

        // Build state slug lookup
        $state_slugs = array_column($states, 'slug');

        foreach ($transitions as $index => $transition) {
            // Required fields
            if (!isset($transition['name']) || !isset($transition['from_state']) || !isset($transition['to_state'])) {
                throw new \Exception("Transition at index {$index} missing required fields (name, from_state, to_state) in {$file_path}");
            }

            // Check if referenced states exist
            if (!in_array($transition['from_state'], $state_slugs)) {
                throw new \Exception("Transition '{$transition['name']}' references non-existent from_state '{$transition['from_state']}' in {$file_path}");
            }

            if (!in_array($transition['to_state'], $state_slugs)) {
                throw new \Exception("Transition '{$transition['name']}' references non-existent to_state '{$transition['to_state']}' in {$file_path}");
            }

            // Validate slug if provided
            if (isset($transition['slug'])) {
                if (!preg_match('/^[a-z0-9-]+$/', $transition['slug'])) {
                    throw new \Exception("Invalid slug format '{$transition['slug']}' in transition '{$transition['name']}' in {$file_path}");
                }
            }
        }
    }

    /**
     * Normalize parsed data
     *
     * @param array $data Raw parsed data
     * @return array Normalized data
     */
    private static function normalize(array $data): array {
        return [
            'workflow_group' => [
                'name' => sanitize_text_field($data['workflow_group']['name']),
                'slug' => sanitize_title($data['workflow_group']['slug']),
                'description' => sanitize_textarea_field($data['workflow_group']['description']),
                'is_active' => true,
            ],
            'state_machine' => [
                'name' => sanitize_text_field($data['state_machine']['name']),
                'slug' => sanitize_title($data['state_machine']['slug']),
                'entity_type' => sanitize_text_field($data['state_machine']['entity_type']),
                'description' => isset($data['state_machine']['description'])
                    ? sanitize_textarea_field($data['state_machine']['description'])
                    : '',
                'plugin_slug' => sanitize_text_field($data['state_machine']['plugin_slug']),
                'is_active' => true,
                'is_default' => true,  // YML files are default workflows
                'is_custom' => false,
            ],
            'states' => self::normalizeStates($data['states']),
            'transitions' => self::normalizeTransitions($data['transitions']),
        ];
    }

    /**
     * Normalize states data
     *
     * @param array $states Raw states data
     * @return array Normalized states
     */
    private static function normalizeStates(array $states): array {
        $normalized = [];

        foreach ($states as $state) {
            $normalized[] = [
                'name' => sanitize_text_field($state['name']),
                'slug' => sanitize_title($state['slug']),
                'type' => sanitize_text_field($state['type']),
                'description' => isset($state['description'])
                    ? sanitize_textarea_field($state['description'])
                    : '',
                'is_active' => true,
            ];
        }

        return $normalized;
    }

    /**
     * Normalize transitions data
     *
     * @param array $transitions Raw transitions data
     * @return array Normalized transitions
     */
    private static function normalizeTransitions(array $transitions): array {
        $normalized = [];

        foreach ($transitions as $transition) {
            $normalized[] = [
                'name' => sanitize_text_field($transition['name']),
                'slug' => isset($transition['slug'])
                    ? sanitize_title($transition['slug'])
                    : sanitize_title($transition['name']),
                'from_state' => sanitize_title($transition['from_state']),
                'to_state' => sanitize_title($transition['to_state']),
                'description' => isset($transition['description'])
                    ? sanitize_textarea_field($transition['description'])
                    : '',
                'conditions' => isset($transition['conditions']) ? $transition['conditions'] : [],
                'actions' => isset($transition['actions']) ? $transition['actions'] : [],
            ];
        }

        return $normalized;
    }

    /**
     * Get all YML files from defaults directory
     *
     * @return array Array of file paths
     */
    public static function getDefaultFiles(): array {
        $defaults_dir = WP_STATE_MACHINE_PATH . 'src/Data/defaults';

        if (!is_dir($defaults_dir)) {
            return [];
        }

        $files = glob($defaults_dir . '/*.yml');
        return $files ? $files : [];
    }
}
