<?php
/**
 * Example: WP RFQ Integration with WP State Machine
 *
 * This file demonstrates how to integrate wp-state-machine
 * into your plugin (using wp-rfq as example).
 *
 * @package     WP_State_Machine
 * @subpackage  Examples
 * @version     1.0.0
 *
 * Path: /wp-state-machine/examples/wp-rfq-integration-example.php
 *
 * DO NOT include this file in production!
 * This is for reference only.
 */

// ============================================
// STEP 1: Register State Machine via Filter
// ============================================

/**
 * Register RFQ state machine with wp-state-machine
 * Hook this in your plugin's init or plugins_loaded
 */
add_filter('wp_state_machine_register_machines', function($machines) {

    // Define your RFQ workflow
    $machines[] = [
        'plugin' => 'wp-rfq',                    // Your plugin slug
        'name' => 'RFQ Workflow',                // Display name
        'slug' => 'rfq-workflow',                // Unique slug
        'entity_type' => 'rfq',                  // Entity type
        'description' => 'Request for Quotation workflow management',
        'workflow_group' => 'b2b',               // Optional workflow group

        // Define states
        'states' => [
            [
                'name' => 'Draft',
                'slug' => 'draft',
                'type' => 'initial',             // initial|intermediate|final
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
                'name' => 'Awarded',
                'slug' => 'awarded',
                'type' => 'intermediate',
                'description' => 'RFQ has been awarded to a vendor'
            ],
            [
                'name' => 'Closed',
                'slug' => 'closed',
                'type' => 'final',
                'description' => 'RFQ is closed'
            ]
        ],

        // Define allowed transitions
        'transitions' => [
            [
                'name' => 'Publish',
                'slug' => 'publish',
                'from_state' => 'draft',
                'to_state' => 'published',
                'description' => 'Publish RFQ to invite quotes',
                'guard' => 'wp_rfq_can_publish'  // Optional guard callback
            ],
            [
                'name' => 'Receive Quote',
                'slug' => 'receive_quote',
                'from_state' => 'published',
                'to_state' => 'quoted',
                'description' => 'First quotation received'
            ],
            [
                'name' => 'Award',
                'slug' => 'award',
                'from_state' => 'quoted',
                'to_state' => 'awarded',
                'description' => 'Award RFQ to selected vendor',
                'guard' => 'wp_rfq_can_award'
            ],
            [
                'name' => 'Close',
                'slug' => 'close',
                'from_state' => 'awarded',
                'to_state' => 'closed',
                'description' => 'Close RFQ after completion'
            ],
            [
                'name' => 'Cancel',
                'slug' => 'cancel',
                'from_state' => 'published',
                'to_state' => 'closed',
                'description' => 'Cancel RFQ without awarding'
            ]
        ]
    ];

    return $machines;
}, 10);


// ============================================
// STEP 2: Seed on Plugin Activation
// ============================================

/**
 * Seed state machine data when wp-rfq is activated
 * Add this to your plugin's activation hook
 */
function wp_rfq_activation_seed_state_machine() {
    // Check if wp-state-machine is active
    if (!class_exists('WPStateMachine\\Database\\Seeder')) {
        error_log('WP State Machine plugin is required for wp-rfq');
        return;
    }

    $seeder = new \WPStateMachine\Database\Seeder();

    // Seed state machines for wp-rfq
    if ($seeder->seedByPlugin('wp-rfq')) {
        error_log('Successfully seeded RFQ state machines');
    } else {
        error_log('Failed to seed RFQ state machines');
    }
}
register_activation_hook(__FILE__, 'wp_rfq_activation_seed_state_machine');


// ============================================
// STEP 3: Reset to Defaults (Optional)
// ============================================

/**
 * Reset RFQ state machines to default
 * Useful for admin settings or debugging
 */
function wp_rfq_reset_state_machines() {
    if (!current_user_can('manage_options')) {
        return;
    }

    $seeder = new \WPStateMachine\Database\Seeder();

    // This will delete all wp-rfq state machines and reseed from registry
    if ($seeder->resetByPlugin('wp-rfq')) {
        wp_send_json_success([
            'message' => 'State machines reset successfully'
        ]);
    } else {
        wp_send_json_error([
            'message' => 'Failed to reset state machines'
        ]);
    }
}
add_action('wp_ajax_reset_rfq_state_machines', 'wp_rfq_reset_state_machines');


// ============================================
// STEP 4: Use State Machine in Your Code
// ============================================

/**
 * Example: Apply transition to an RFQ entity
 *
 * @param int $rfq_id RFQ ID
 * @return bool Success status
 */
function wp_rfq_publish($rfq_id) {
    // TODO: This will be implemented when StateMachineEngine is created
    // For now, this is a placeholder showing the intended API

    /*
    $engine = new \WPStateMachine\Engine\StateMachineEngine();

    $result = $engine->applyTransition([
        'entity_type' => 'rfq',
        'entity_id' => $rfq_id,
        'transition_slug' => 'publish',
        'user_id' => get_current_user_id()
    ]);

    return $result['success'];
    */

    return false; // Placeholder
}


// ============================================
// STEP 5: Hook into State Change Events
// ============================================

/**
 * Listen to state change events
 * Fired after a successful transition
 */
add_action('wp_state_machine_after_transition', function($entity_type, $entity_id, $from_state, $to_state, $transition) {
    // Only handle RFQ state changes
    if ($entity_type !== 'rfq') {
        return;
    }

    // Do something based on the transition
    switch ($to_state) {
        case 'published':
            // Send notifications to invited vendors
            wp_rfq_notify_vendors($entity_id);
            break;

        case 'quoted':
            // Notify customer about new quote
            wp_rfq_notify_customer_new_quote($entity_id);
            break;

        case 'awarded':
            // Create purchase order
            wp_rfq_create_purchase_order($entity_id);
            break;

        case 'closed':
            // Archive RFQ
            wp_rfq_archive($entity_id);
            break;
    }
}, 10, 5);


// ============================================
// STEP 6: Guard Callbacks (Optional)
// ============================================

/**
 * Guard callback: Check if RFQ can be published
 * Return true to allow transition, false to block
 *
 * @param int $entity_id RFQ ID
 * @param int $user_id User attempting the transition
 * @return bool Allow transition
 */
function wp_rfq_can_publish($entity_id, $user_id) {
    // Check if RFQ has at least one equipment
    $equipment_count = get_post_meta($entity_id, '_rfq_equipment_count', true);
    if (empty($equipment_count) || $equipment_count < 1) {
        return false;
    }

    // Check if user owns this RFQ
    $rfq = get_post($entity_id);
    if ($rfq->post_author != $user_id) {
        return false;
    }

    return true;
}

/**
 * Guard callback: Check if RFQ can be awarded
 *
 * @param int $entity_id RFQ ID
 * @param int $user_id User attempting the transition
 * @return bool Allow transition
 */
function wp_rfq_can_award($entity_id, $user_id) {
    // Check if there are quotations
    $quotes_count = wp_rfq_get_quotes_count($entity_id);
    if ($quotes_count < 1) {
        return false;
    }

    // Check if a winner is selected
    $selected_quote = get_post_meta($entity_id, '_selected_quotation_id', true);
    if (empty($selected_quote)) {
        return false;
    }

    return true;
}


// ============================================
// STEP 7: Query Current State (Optional)
// ============================================

/**
 * Get current state of an RFQ
 *
 * @param int $rfq_id RFQ ID
 * @return string|null Current state slug or null
 */
function wp_rfq_get_current_state($rfq_id) {
    global $wpdb;

    $logs_table = $wpdb->prefix . 'app_sm_transition_logs';
    $states_table = $wpdb->prefix . 'app_sm_states';

    // Get latest transition log for this RFQ
    $current_state = $wpdb->get_var($wpdb->prepare("
        SELECT s.slug
        FROM {$logs_table} l
        INNER JOIN {$states_table} s ON l.to_state_id = s.id
        WHERE l.entity_type = 'rfq' AND l.entity_id = %d
        ORDER BY l.created_at DESC
        LIMIT 1
    ", $rfq_id));

    return $current_state ?: 'draft'; // Default to draft if no transitions yet
}


// ============================================
// Helper Functions (Placeholders)
// ============================================

function wp_rfq_notify_vendors($rfq_id) { /* ... */ }
function wp_rfq_notify_customer_new_quote($rfq_id) { /* ... */ }
function wp_rfq_create_purchase_order($rfq_id) { /* ... */ }
function wp_rfq_archive($rfq_id) { /* ... */ }
function wp_rfq_get_quotes_count($rfq_id) { return 0; }
