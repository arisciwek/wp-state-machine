# WP State Machine

Flexible state machine workflow management for WordPress plugins.

## Version
1.1.0

## Features

- ✅ **Decentralized Pattern**: Each plugin manages its own state machines
- ✅ **Plugin Isolation**: Reset one plugin's state machines without affecting others
- ✅ **Workflow Groups**: Organize state machines (Drupal pattern)
- ✅ **Database Schema**: 5 tables with proper foreign keys
- ✅ **Activation/Deactivation**: Proper data preservation
- ✅ **Admin Menu**: Following wp-agency MenuManager pattern

## Database Structure

### Tables
1. `app_sm_workflow_groups` - Workflow group organization
2. `app_sm_machines` - State machine definitions
3. `app_sm_states` - States in each machine
4. `app_sm_transitions` - Allowed transitions between states
5. `app_sm_transition_logs` - Audit trail of all transitions

### Foreign Keys (9 total)
- `fk_machine_group` - machines → workflow_groups
- `fk_sm_states_machine` - states → machines
- `fk_sm_transitions_machine` - transitions → machines
- `fk_sm_transitions_from_state` - transitions → states (from)
- `fk_sm_transitions_to_state` - transitions → states (to)
- `fk_sm_logs_machine` - logs → machines
- `fk_sm_logs_from_state` - logs → states (from)
- `fk_sm_logs_to_state` - logs → states (to)
- `fk_sm_logs_transition` - logs → transitions

## Admin Menu Structure

**Main Menu:** State Machines (capability: `view_state_machines`)
- **Workflow Groups** - Manage workflow groups
- **Machines** - Manage state machines
- **Logs** - View transition history
- **Settings** - Plugin settings (admin only)

## Capabilities

- `manage_state_machines` - Full management
- `view_state_machines` - View state machines
- `edit_state_machines` - Edit state machines
- `delete_state_machines` - Delete state machines
- `manage_transitions` - Manage transitions
- `view_transition_logs` - View transition logs

## Activation/Deactivation Behavior

### Activation
- Creates all database tables
- Creates foreign key constraints
- Adds capabilities to administrator role
- Saves version information
- Flushes rewrite rules

### Deactivation
- ✅ **PRESERVES all data** (tables, foreign keys, data)
- Flushes rewrite rules
- Clears transients/cache
- Does NOT remove capabilities
- Does NOT drop tables

### Uninstallation
- Drops all tables and foreign keys
- Deletes all plugin options
- Removes capabilities from all roles
- Complete cleanup

## Pattern Followed

This plugin follows the **wp-agency** architecture pattern:

1. **MenuManager** (`src/Controllers/MenuManager.php`)
   - Follows wp-agency MenuManager structure
   - Constructor accepts plugin_name and version
   - init() method for hook registration
   - registerMenus() for menu setup

2. **PermissionModel** (`src/Models/Settings/PermissionModel.php`)
   - Separated from role management
   - Manages capabilities only
   - Similar to wp-agency pattern

3. **Database Tables** (`src/Database/Tables/*.php`)
   - Follows AgencysDB pattern
   - get_schema() method for table structure
   - add_foreign_keys() method for FK constraints

4. **Installer** (`src/Database/Installer.php`)
   - Transaction support
   - Table verification
   - Foreign key handling
   - Follows wp-agency Installer pattern

## TODO

- [ ] Implement DefaultStateMachines.php (data registry)
- [ ] Implement Seeder.php (seedByPlugin, resetByPlugin)
- [ ] Create example integration (wp-rfq-integration-example.php)
- [ ] Implement SettingsController
- [ ] Implement StateMachineController
- [ ] Add admin UI for state machine management
- [ ] Add admin UI for workflow groups
- [ ] Add admin UI for transition logs

## Installation

1. Upload plugin to `/wp-content/plugins/wp-state-machine/`
2. Activate plugin via WordPress admin
3. Database tables will be created automatically

## Usage

### For Plugin Developers

See `examples/wp-rfq-integration-example.php` for complete integration example.

```php
// 1. Register state machine via filter
add_filter('wp_state_machine_register_machines', function($machines) {
    $machines[] = [
        'plugin' => 'your-plugin-slug',
        'name' => 'Your Workflow',
        'slug' => 'your-workflow',
        'entity_type' => 'your_entity',
        'states' => [...],
        'transitions' => [...]
    ];
    return $machines;
});

// 2. Seed on activation
use WPStateMachine\Database\Seeder;
$seeder = new Seeder();
$seeder->seedByPlugin('your-plugin-slug');

// 3. Reset to defaults
$seeder->resetByPlugin('your-plugin-slug');
```

## Support

For issues and feature requests, please contact the plugin author.

## Author

arisciwek

## License

GPL v2 or later
