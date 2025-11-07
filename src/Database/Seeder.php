<?php
/**
 * Database Seeder Class
 *
 * @package     WP_State_Machine
 * @subpackage  Database
 * @version     1.0.0
 * @author      arisciwek
 *
 * Path: /wp-state-machine/src/Database/Seeder.php
 *
 * Description: Seeds state machine data for plugins.
 *              Supports plugin isolation - each plugin can seed/reset
 *              its own state machines without affecting others.
 *
 * Key Methods:
 * - seedByPlugin(): Seed all state machines for a plugin
 * - resetByPlugin(): Reset (delete & reseed) state machines for a plugin
 * - seedSingleMachine(): Seed one specific state machine
 *
 * Usage:
 * ```php
 * // In your plugin activation:
 * $seeder = new Seeder();
 * $seeder->seedByPlugin('wp-rfq');
 *
 * // To reset:
 * $seeder->resetByPlugin('wp-rfq');
 * ```
 *
 * Changelog:
 * 1.0.0 - 2025-11-07
 * - Initial creation
 * - Plugin-isolated seeding
 * - Support for multi-plugin architecture
 */

namespace WPStateMachine\Database;

defined('ABSPATH') || exit;

class Seeder {

    /**
     * Seed all state machines for a specific plugin
     *
     * @param string $plugin_slug Plugin slug to seed
     * @return bool True on success, false on failure
     */
    public function seedByPlugin(string $plugin_slug): bool {
        global $wpdb;

        try {
            // Get machines for this plugin from registry
            $machines = DefaultStateMachines::getByPlugin($plugin_slug);

            if (empty($machines)) {
                error_log("No state machines registered for plugin: {$plugin_slug}");
                return false;
            }

            $wpdb->query('START TRANSACTION');

            foreach ($machines as $machine_data) {
                if (!$this->seedSingleMachine($machine_data)) {
                    $wpdb->query('ROLLBACK');
                    return false;
                }
            }

            $wpdb->query('COMMIT');

            do_action('wp_state_machine_seeded', $plugin_slug, $machines);

            return true;

        } catch (\Exception $e) {
            $wpdb->query('ROLLBACK');
            error_log("Error seeding state machines for {$plugin_slug}: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Reset state machines for a plugin
     * Deletes all existing machines and reseeds from registry
     *
     * @param string $plugin_slug Plugin slug to reset
     * @return bool True on success, false on failure
     */
    public function resetByPlugin(string $plugin_slug): bool {
        global $wpdb;

        try {
            $wpdb->query('START TRANSACTION');

            // Get all machine IDs for this plugin
            $table = $wpdb->prefix . 'app_sm_machines';
            $machine_ids = $wpdb->get_col($wpdb->prepare(
                "SELECT id FROM {$table} WHERE plugin_slug = %s",
                $plugin_slug
            ));

            if (!empty($machine_ids)) {
                // Delete in correct order (child tables first)
                $logs_table = $wpdb->prefix . 'app_sm_transition_logs';
                $transitions_table = $wpdb->prefix . 'app_sm_transitions';
                $states_table = $wpdb->prefix . 'app_sm_states';

                $ids_placeholder = implode(',', array_fill(0, count($machine_ids), '%d'));

                // Delete logs
                $wpdb->query($wpdb->prepare(
                    "DELETE FROM {$logs_table} WHERE machine_id IN ({$ids_placeholder})",
                    ...$machine_ids
                ));

                // Delete transitions
                $wpdb->query($wpdb->prepare(
                    "DELETE FROM {$transitions_table} WHERE machine_id IN ({$ids_placeholder})",
                    ...$machine_ids
                ));

                // Delete states
                $wpdb->query($wpdb->prepare(
                    "DELETE FROM {$states_table} WHERE machine_id IN ({$ids_placeholder})",
                    ...$machine_ids
                ));

                // Delete machines
                $wpdb->query($wpdb->prepare(
                    "DELETE FROM {$table} WHERE plugin_slug = %s",
                    $plugin_slug
                ));
            }

            // Now reseed from registry
            $result = $this->seedByPlugin($plugin_slug);

            if ($result) {
                $wpdb->query('COMMIT');
                do_action('wp_state_machine_reset', $plugin_slug);
            } else {
                $wpdb->query('ROLLBACK');
            }

            return $result;

        } catch (\Exception $e) {
            $wpdb->query('ROLLBACK');
            error_log("Error resetting state machines for {$plugin_slug}: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Seed a single state machine with its states and transitions
     *
     * @param array $machine_data Machine definition from DefaultStateMachines
     * @return bool True on success, false on failure
     */
    public function seedSingleMachine(array $machine_data): bool {
        global $wpdb;

        try {
            // Validate required fields
            if (empty($machine_data['plugin']) || empty($machine_data['slug'])) {
                error_log("Invalid machine data: missing plugin or slug");
                return false;
            }

            // Check if machine already exists
            $machines_table = $wpdb->prefix . 'app_sm_machines';
            $exists = $wpdb->get_var($wpdb->prepare(
                "SELECT id FROM {$machines_table}
                 WHERE plugin_slug = %s AND slug = %s",
                $machine_data['plugin'],
                $machine_data['slug']
            ));

            if ($exists) {
                error_log("Machine already exists: {$machine_data['plugin']}/{$machine_data['slug']}");
                return true; // Not an error, already seeded
            }

            // Get workflow group ID if specified
            $workflow_group_id = null;
            if (!empty($machine_data['workflow_group'])) {
                $groups_table = $wpdb->prefix . 'app_sm_workflow_groups';
                $workflow_group_id = $wpdb->get_var($wpdb->prepare(
                    "SELECT id FROM {$groups_table} WHERE slug = %s",
                    $machine_data['workflow_group']
                ));
            }

            // Insert machine
            $wpdb->insert(
                $machines_table,
                [
                    'name' => $machine_data['name'],
                    'slug' => $machine_data['slug'],
                    'plugin_slug' => $machine_data['plugin'],
                    'entity_type' => $machine_data['entity_type'] ?? '',
                    'workflow_group_id' => $workflow_group_id,
                    'description' => $machine_data['description'] ?? '',
                    'is_active' => 1,
                    'created_at' => current_time('mysql'),
                    'updated_at' => current_time('mysql')
                ],
                ['%s', '%s', '%s', '%s', '%d', '%s', '%d', '%s', '%s']
            );

            $machine_id = $wpdb->insert_id;

            if (!$machine_id) {
                error_log("Failed to insert machine: " . $wpdb->last_error);
                return false;
            }

            // Insert states
            $state_ids = [];
            if (!empty($machine_data['states'])) {
                foreach ($machine_data['states'] as $state) {
                    $state_id = $this->seedState($machine_id, $state);
                    if ($state_id) {
                        $state_ids[$state['slug']] = $state_id;
                    }
                }
            }

            // Insert transitions
            if (!empty($machine_data['transitions'])) {
                foreach ($machine_data['transitions'] as $transition) {
                    $this->seedTransition($machine_id, $transition, $state_ids);
                }
            }

            do_action('wp_state_machine_machine_seeded', $machine_id, $machine_data);

            return true;

        } catch (\Exception $e) {
            error_log("Error seeding machine: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Seed a single state
     *
     * @param int $machine_id Machine ID
     * @param array $state_data State definition
     * @return int|false State ID or false on failure
     */
    private function seedState(int $machine_id, array $state_data) {
        global $wpdb;

        $states_table = $wpdb->prefix . 'app_sm_states';

        // Determine state type (initial, normal, final)
        $type = 'normal'; // default
        if (isset($state_data['type'])) {
            $state_type = strtolower($state_data['type']);
            if (in_array($state_type, ['initial', 'normal', 'final', 'intermediate'])) {
                // Map 'intermediate' to 'normal' for database
                $type = ($state_type === 'intermediate') ? 'normal' : $state_type;
            }
        }

        $wpdb->insert(
            $states_table,
            [
                'machine_id' => $machine_id,
                'name' => $state_data['name'],
                'slug' => $state_data['slug'],
                'type' => $type,
                'color' => $state_data['color'] ?? null,
                'metadata' => isset($state_data['metadata']) ? json_encode($state_data['metadata']) : null,
                'sort_order' => $state_data['sort_order'] ?? 0,
                'created_at' => current_time('mysql'),
                'updated_at' => current_time('mysql')
            ],
            ['%d', '%s', '%s', '%s', '%s', '%s', '%d', '%s', '%s']
        );

        return $wpdb->insert_id;
    }

    /**
     * Seed a single transition
     *
     * @param int $machine_id Machine ID
     * @param array $transition_data Transition definition
     * @param array $state_ids Map of state slugs to IDs
     * @return int|false Transition ID or false on failure
     */
    private function seedTransition(int $machine_id, array $transition_data, array $state_ids) {
        global $wpdb;

        // Get from/to state IDs
        $from_state_id = $state_ids[$transition_data['from_state']] ?? null;
        $to_state_id = $state_ids[$transition_data['to_state']] ?? null;

        if (!$from_state_id || !$to_state_id) {
            $slug = $transition_data['slug'] ?? $transition_data['name'] ?? 'unknown';
            error_log("Invalid state references in transition: " . $slug);
            return false;
        }

        $transitions_table = $wpdb->prefix . 'app_sm_transitions';

        // Prepare metadata (includes description and other data)
        $metadata = [];
        if (isset($transition_data['description'])) {
            $metadata['description'] = $transition_data['description'];
        }
        if (isset($transition_data['slug'])) {
            $metadata['slug'] = $transition_data['slug'];
        }
        $metadata_json = !empty($metadata) ? json_encode($metadata) : null;

        $wpdb->insert(
            $transitions_table,
            [
                'machine_id' => $machine_id,
                'from_state_id' => $from_state_id,
                'to_state_id' => $to_state_id,
                'label' => $transition_data['name'],
                'guard_class' => $transition_data['guard'] ?? null,
                'metadata' => $metadata_json,
                'sort_order' => $transition_data['sort_order'] ?? 0,
                'created_at' => current_time('mysql'),
                'updated_at' => current_time('mysql')
            ],
            ['%d', '%d', '%d', '%s', '%s', '%s', '%d', '%s', '%s']
        );

        return $wpdb->insert_id;
    }

    /**
     * Check if a plugin has seeded its state machines
     *
     * @param string $plugin_slug Plugin slug
     * @return bool True if seeded, false otherwise
     */
    public function isSeeded(string $plugin_slug): bool {
        global $wpdb;

        $table = $wpdb->prefix . 'app_sm_machines';
        $count = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$table} WHERE plugin_slug = %s",
            $plugin_slug
        ));

        return $count > 0;
    }
}
