# Multi-Plugin Support Documentation

**WP State Machine v1.1.0**

## ✅ Status: FULLY SUPPORTED

WP State Machine is designed as a **Foundation Layer** plugin that supports multiple plugins, each with their own independent state machines.

---

## 🏗️ Architecture Overview

### Plugin Isolation Pattern

Each plugin registers and manages its own state machines through WordPress filters. State machines are isolated by `plugin_slug` field in the database.

```
wp-state-machine (Foundation)
    ↓
├── wp-rfq (state machines for RFQ workflow)
├── wp-quotation (state machines for Quotation workflow)
├── wp-inspection (state machines for Inspection workflow)
├── wp-certificate (state machines for Certificate workflow)
└── ... (other plugins)
```

### Benefits

✅ **Plugin Isolation**: Each plugin owns its state machines
✅ **Independent Reset**: Reset wp-rfq without affecting wp-quotation
✅ **Decentralized Management**: Each plugin defines its own workflows
✅ **Cross-Plugin Events**: Subscribe to state change events from any plugin
✅ **Audit Trail**: All transitions logged with plugin context

---

## 📋 Implementation Status

### ✅ Completed Components

| Component | Status | Description |
|-----------|--------|-------------|
| Database Schema | ✅ | `plugin_slug` field for isolation |
| DefaultStateMachines | ✅ | Central registry with filter support |
| Seeder | ✅ | Plugin-specific seed/reset |
| StateMachineModel | ✅ | `getByPlugin()` method |
| Integration Example | ✅ | Complete wp-rfq example |
| WordPress Hooks | ✅ | Filter registration system |

### 🔄 Pending Components

| Component | Status | Priority |
|-----------|--------|----------|
| StateMachineEngine | 📋 Planned | High - Apply transitions |
| Guard Validation | 📋 Planned | High - Permission checks |
| State Models | 📋 Planned | Medium - CRUD for states |
| Transition Models | 📋 Planned | Medium - CRUD for transitions |
| Admin UI | 📋 Planned | Low - Visual management |

---

## 🚀 How to Integrate

### Step 1: Register State Machine

```php
// In your plugin (e.g., wp-rfq)
add_filter('wp_state_machine_register_machines', function($machines) {
    $machines[] = [
        'plugin' => 'wp-rfq',
        'name' => 'RFQ Workflow',
        'slug' => 'rfq-workflow',
        'entity_type' => 'rfq',
        'states' => [
            ['name' => 'Draft', 'slug' => 'draft', 'type' => 'initial'],
            ['name' => 'Published', 'slug' => 'published', 'type' => 'intermediate'],
            ['name' => 'Closed', 'slug' => 'closed', 'type' => 'final']
        ],
        'transitions' => [
            [
                'name' => 'Publish',
                'slug' => 'publish',
                'from_state' => 'draft',
                'to_state' => 'published'
            ]
        ]
    ];
    return $machines;
});
```

### Step 2: Seed on Activation

```php
// In your plugin activation hook
function your_plugin_activate() {
    $seeder = new \WPStateMachine\Database\Seeder();
    $seeder->seedByPlugin('your-plugin-slug');
}
register_activation_hook(__FILE__, 'your_plugin_activate');
```

### Step 3: Subscribe to Events

```php
// Listen to state changes
add_action('wp_state_machine_after_transition', function(
    $entity_type,
    $entity_id,
    $from_state,
    $to_state,
    $transition
) {
    if ($entity_type === 'rfq') {
        // Handle RFQ state change
        error_log("RFQ #{$entity_id} moved from {$from_state} to {$to_state}");
    }
}, 10, 5);
```

---

## 📊 Database Structure

### Tables (Plugin-Isolated)

```sql
-- Each machine belongs to a plugin
app_sm_machines
    - id
    - plugin_slug VARCHAR(100)  ← Plugin ownership
    - name
    - slug
    - entity_type
    ...

-- States belong to machines
app_sm_states
    - id
    - machine_id (FK to machines)
    - name
    - slug
    ...

-- Transitions define allowed state changes
app_sm_transitions
    - id
    - machine_id (FK to machines)
    - from_state_id (FK to states)
    - to_state_id (FK to states)
    ...

-- Logs track all transitions
app_sm_transition_logs
    - id
    - machine_id (FK to machines)
    - entity_type VARCHAR(50)  ← e.g., 'rfq', 'quotation'
    - entity_id INT           ← e.g., RFQ post ID
    - from_state_id
    - to_state_id
    - transition_id
    - user_id
    - created_at
    ...
```

---

## 🔧 Management Operations

### Seed State Machines

```php
$seeder = new \WPStateMachine\Database\Seeder();

// Seed all machines for wp-rfq
$seeder->seedByPlugin('wp-rfq');
```

### Reset State Machines

```php
// Delete all wp-rfq state machines and reseed from registry
$seeder->resetByPlugin('wp-rfq');

// This WILL NOT affect wp-quotation or other plugins!
```

### Check if Seeded

```php
if ($seeder->isSeeded('wp-rfq')) {
    echo 'RFQ state machines already seeded';
}
```

---

## 🌐 B2B Ecosystem Integration

According to `/wp-docs/01-architecture/plugin-registry.md`, wp-state-machine will manage workflows for:

### Core B2B Flow
- **wp-rfq**: draft → published → quoted → closed
- **wp-quotation**: draft → submitted → accepted/rejected
- **wp-purchase-order**: draft → issued → confirmed → completed
- **wp-project**: created → in_progress → completed → invoiced

### Operations
- **wp-inspection**: scheduled → in_progress → completed → reported
- **wp-report**: draft → submitted → approved → published

### Support Services
- **wp-certificate**: pending → issued → active → expired/superseded/revoked
- **wp-licence**: pending → active → expired → suspended → revoked

---

## 📚 Example: RFQ Integration

See complete example: `/examples/wp-rfq-integration-example.php`

Shows:
- ✅ Filter registration
- ✅ Activation seeding
- ✅ State change events
- ✅ Guard callbacks
- ✅ Current state queries

---

## 🎯 Next Steps

For full workflow functionality, implement:

1. **StateMachineEngine** - Apply transitions with validation
2. **Guard System** - Permission and condition checks
3. **Event System** - Complete hook integration
4. **Admin UI** - Visual state machine management

---

## 📖 References

- [Plugin Registry](/wp-docs/01-architecture/plugin-registry.md)
- [Integration Example](/examples/wp-rfq-integration-example.php)
- [Main README](/README.md)

---

**Last Updated:** 2025-11-07
**Version:** 1.1.0
**Status:** Multi-plugin support ✅ READY
