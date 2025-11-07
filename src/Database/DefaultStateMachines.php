<?php
/**
 * Default State Machines Registry
 *
 * @package     WP_State_Machine
 * @subpackage  Database
 * @version     1.0.0
 * @author      arisciwek
 *
 * Path: /wp-state-machine/src/Database/DefaultStateMachines.php
 *
 * Description: Central registry for all state machine definitions.
 *              Plugins register their state machines via WordPress filter.
 *              Provides default state machines for reference.
 *
 * Usage:
 * ```php
 * // In your plugin:
 * add_filter('wp_state_machine_register_machines', function($machines) {
 *     $machines[] = [
 *         'plugin' => 'wp-rfq',
 *         'name' => 'RFQ Workflow',
 *         'slug' => 'rfq-workflow',
 *         'entity_type' => 'rfq',
 *         'states' => [...],
 *         'transitions' => [...]
 *     ];
 *     return $machines;
 * });
 * ```
 *
 * Changelog:
 * 1.0.0 - 2025-11-07
 * - Initial creation
 * - Filter-based registration system
 * - Support for multi-plugin architecture
 */

namespace WPStateMachine\Database;

defined('ABSPATH') || exit;

class DefaultStateMachines {

    /**
     * Get all registered state machines from plugins
     * Uses WordPress filter to allow plugins to register
     *
     * @return array Array of state machine definitions
     */
    public static function getMachines(): array {
        $machines = [];

        /**
         * Filter: wp_state_machine_register_machines
         *
         * Allows plugins to register their state machines
         *
         * @param array $machines Array of state machine definitions
         * @return array Modified array of machines
         *
         * Example machine structure:
         * [
         *     'plugin' => 'wp-rfq',           // Plugin slug (owner)
         *     'name' => 'RFQ Workflow',       // Display name
         *     'slug' => 'rfq-workflow',       // Machine slug (unique per plugin)
         *     'entity_type' => 'rfq',         // Entity type this machine applies to
         *     'description' => '...',         // Optional description
         *     'workflow_group' => 'b2b',      // Optional workflow group
         *     'states' => [                   // Array of states
         *         [
         *             'name' => 'Draft',
         *             'slug' => 'draft',
         *             'type' => 'initial',    // initial|intermediate|final
         *             'description' => '...'
         *         ],
         *         // ... more states
         *     ],
         *     'transitions' => [              // Array of allowed transitions
         *         [
         *             'name' => 'Publish',
         *             'slug' => 'publish',
         *             'from_state' => 'draft',
         *             'to_state' => 'published',
         *             'guard' => 'can_publish', // Optional guard callback
         *             'description' => '...'
         *         ],
         *         // ... more transitions
         *     ]
         * ]
         */
        $machines = apply_filters('wp_state_machine_register_machines', $machines);

        return $machines;
    }

    /**
     * Get state machines for a specific plugin
     *
     * @param string $plugin_slug Plugin slug
     * @return array Array of state machines for this plugin
     */
    public static function getByPlugin(string $plugin_slug): array {
        $all_machines = self::getMachines();

        return array_filter($all_machines, function($machine) use ($plugin_slug) {
            return isset($machine['plugin']) && $machine['plugin'] === $plugin_slug;
        });
    }

    /**
     * Get example state machine definitions for reference
     * These are examples from the B2B ecosystem documentation
     *
     * @return array Example state machine definitions
     */
    public static function getExamples(): array {
        return [
            // Example: RFQ State Machine
            [
                'plugin' => 'wp-rfq',
                'name' => 'RFQ Workflow',
                'slug' => 'rfq-workflow',
                'entity_type' => 'rfq',
                'description' => 'Request for Quotation workflow',
                'workflow_group' => 'b2b',
                'states' => [
                    [
                        'name' => 'Draft',
                        'slug' => 'draft',
                        'type' => 'initial',
                        'description' => 'RFQ is being created'
                    ],
                    [
                        'name' => 'Published',
                        'slug' => 'published',
                        'type' => 'intermediate',
                        'description' => 'RFQ is published and open for quotes'
                    ],
                    [
                        'name' => 'Quoted',
                        'slug' => 'quoted',
                        'type' => 'intermediate',
                        'description' => 'RFQ has received quotations'
                    ],
                    [
                        'name' => 'Closed',
                        'slug' => 'closed',
                        'type' => 'final',
                        'description' => 'RFQ is closed'
                    ]
                ],
                'transitions' => [
                    [
                        'name' => 'Publish',
                        'slug' => 'publish',
                        'from_state' => 'draft',
                        'to_state' => 'published',
                        'description' => 'Publish RFQ to invite quotes'
                    ],
                    [
                        'name' => 'Receive Quote',
                        'slug' => 'receive_quote',
                        'from_state' => 'published',
                        'to_state' => 'quoted',
                        'description' => 'First quotation received'
                    ],
                    [
                        'name' => 'Close',
                        'slug' => 'close',
                        'from_state' => 'quoted',
                        'to_state' => 'closed',
                        'description' => 'Close RFQ and select winner'
                    ]
                ]
            ],

            // Example: Quotation State Machine
            [
                'plugin' => 'wp-quotation',
                'name' => 'Quotation Workflow',
                'slug' => 'quotation-workflow',
                'entity_type' => 'quotation',
                'description' => 'Quotation response workflow',
                'workflow_group' => 'b2b',
                'states' => [
                    [
                        'name' => 'Draft',
                        'slug' => 'draft',
                        'type' => 'initial',
                        'description' => 'Quotation is being prepared'
                    ],
                    [
                        'name' => 'Submitted',
                        'slug' => 'submitted',
                        'type' => 'intermediate',
                        'description' => 'Quotation submitted to customer'
                    ],
                    [
                        'name' => 'Accepted',
                        'slug' => 'accepted',
                        'type' => 'final',
                        'description' => 'Quotation accepted by customer'
                    ],
                    [
                        'name' => 'Rejected',
                        'slug' => 'rejected',
                        'type' => 'final',
                        'description' => 'Quotation rejected by customer'
                    ]
                ],
                'transitions' => [
                    [
                        'name' => 'Submit',
                        'slug' => 'submit',
                        'from_state' => 'draft',
                        'to_state' => 'submitted',
                        'description' => 'Submit quotation to customer'
                    ],
                    [
                        'name' => 'Accept',
                        'slug' => 'accept',
                        'from_state' => 'submitted',
                        'to_state' => 'accepted',
                        'description' => 'Customer accepts quotation'
                    ],
                    [
                        'name' => 'Reject',
                        'slug' => 'reject',
                        'from_state' => 'submitted',
                        'to_state' => 'rejected',
                        'description' => 'Customer rejects quotation'
                    ]
                ]
            ],

            // Example: Inspection State Machine
            [
                'plugin' => 'wp-inspection',
                'name' => 'Inspection Workflow',
                'slug' => 'inspection-workflow',
                'entity_type' => 'inspection',
                'description' => 'Inspection service workflow',
                'workflow_group' => 'operations',
                'states' => [
                    [
                        'name' => 'Scheduled',
                        'slug' => 'scheduled',
                        'type' => 'initial',
                        'description' => 'Inspection is scheduled'
                    ],
                    [
                        'name' => 'In Progress',
                        'slug' => 'in_progress',
                        'type' => 'intermediate',
                        'description' => 'Inspection is being performed'
                    ],
                    [
                        'name' => 'Completed',
                        'slug' => 'completed',
                        'type' => 'intermediate',
                        'description' => 'Inspection completed, awaiting report'
                    ],
                    [
                        'name' => 'Reported',
                        'slug' => 'reported',
                        'type' => 'final',
                        'description' => 'Report generated and submitted'
                    ]
                ],
                'transitions' => [
                    [
                        'name' => 'Start Inspection',
                        'slug' => 'start',
                        'from_state' => 'scheduled',
                        'to_state' => 'in_progress',
                        'guard' => 'has_valid_license',
                        'description' => 'Begin inspection work'
                    ],
                    [
                        'name' => 'Complete',
                        'slug' => 'complete',
                        'from_state' => 'in_progress',
                        'to_state' => 'completed',
                        'description' => 'Finish inspection work'
                    ],
                    [
                        'name' => 'Generate Report',
                        'slug' => 'report',
                        'from_state' => 'completed',
                        'to_state' => 'reported',
                        'description' => 'Create and submit inspection report'
                    ]
                ]
            ]
        ];
    }
}
