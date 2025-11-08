# TODO-6106: YML Workflow Seeder - COMPLETED ✅

**Completion Date:** 2025-11-08
**Status:** All tasks completed and tested successfully

## Summary

Successfully implemented YML-based workflow seeding system for WP State Machine plugin. The system allows:
- Defining workflows in YML files
- Automatic seeding from YML templates
- Reset to default functionality
- Full validation and error handling
- Backup system before reset

## Implementation Details

### 1. Data Structure (`/src/Data/`)
```
/src/Data/
├── defaults/                          # YML workflow definitions
│   └── blog-post-workflow.yml        # Sample workflow (tested)
├── backups/                           # Auto-backups before reset
│   └── .gitkeep
├── YmlParser.php                      # YML parser with validation
├── WorkflowSeeder.php                 # Core seeding logic
└── README.md                          # Documentation
```

### 2. Core Classes Created

#### YmlParser.php
- **Purpose:** Parse and validate YML workflow files
- **Features:**
  - Symfony YAML component integration
  - Structure validation (workflow_group, state_machine, states, transitions)
  - State type validation (initial, intermediate, final)
  - Transition reference validation
  - Data sanitization
- **Methods:**
  - `parseFile()` - Parse YML file
  - `validate()` - Validate structure
  - `normalize()` - Sanitize data
  - `getDefaultFiles()` - List available YML files

#### WorkflowSeeder.php
- **Purpose:** Import YML workflows to database
- **Features:**
  - Transaction support (atomic operations)
  - State slug to ID mapping
  - Foreign key relationship handling
  - Backup system
  - Error handling and logging
- **Methods:**
  - `seedFromFile()` - Seed single workflow
  - `seedAllDefaults()` - Seed all YML files
  - `resetToDefaults()` - Delete defaults and re-seed
  - `createBackup()` - JSON backup before reset

#### WorkflowSeederController.php
- **Purpose:** AJAX handlers for seeding operations
- **Endpoints:**
  - `wp_ajax_seed_default_workflows`
  - `wp_ajax_reset_to_default_workflows`
  - `wp_ajax_get_seeder_status`
- **Security:** Nonce verification and capability checks

### 3. Database Schema Updates

#### StateMachinesDB.php (v1.2.0)
Added fields:
```php
is_default tinyint(1) NOT NULL DEFAULT 0 COMMENT '1=seeded from YML, 0=user created'
```

Updated indexes:
```php
KEY is_default_index (is_default)
KEY default_custom_index (is_default, is_custom)
```

#### StateMachineModel.php
Updated `prepareInsertData()` to support:
- `is_default` - Flag for YML-seeded workflows
- `is_custom` - Flag for user modifications
- `created_by` - User ID tracking

### 4. UI Implementation

#### Settings Page (Database Tab)
Added section: **"Default Workflows"**

**Seed Workflows Button:**
- Imports all YML files from `/src/Data/defaults/`
- Creates workflow groups, machines, states, transitions
- Sets `is_default=1` flag
- Shows success count and errors

**Reset to Defaults Button:**
- Deletes all workflows where `is_default=1`
- Creates JSON backup (optional)
- Re-seeds from YML files
- Preserves custom workflows (`is_default=0`)
- Shows confirmation warning

#### JavaScript (settings.js)
Added methods:
- `seedWorkflows()` - Handle seed button click
- `resetWorkflows()` - Handle reset button click
- Confirmation dialogs with warnings
- Success/error toast notifications

### 5. Dependencies

#### Composer Packages
```json
{
    "require": {
        "symfony/yaml": "^7.3"
    }
}
```

#### Autoloader Update
Updated `class-autoloader.php` to include composer autoload:
```php
public function register() {
    // Load composer autoload if available
    $composer_autoload = $this->baseDir . 'vendor/autoload.php';
    if (file_exists($composer_autoload)) {
        require_once $composer_autoload;
    }
    spl_autoload_register([$this, 'loadClass']);
}
```

### 6. Sample YML Structure

```yaml
workflow_group:
  name: "Blog Management"
  slug: "blog-management"
  description: "Content management workflows"

state_machine:
  name: "Blog Post Workflow"
  slug: "blog-post-workflow"
  entity_type: "post"
  plugin_slug: "wp-state-machine"

states:
  - name: "Draft"
    slug: "draft"
    type: "initial"
    description: "Initial draft state"

transitions:
  - name: "Submit for Review"
    slug: "submit-for-review"
    from_state: "draft"
    to_state: "review"
    conditions: []
    actions:
      - notify_editor
```

## Testing Results

### Test 1: YML Parser ✅
```
✓ YML file parsed successfully
- Workflow Group: Blog Management
- State Machine: Blog Post Workflow
- States: 5
- Transitions: 5
```

### Test 2: Workflow Seeder ✅
```
✓ Workflow seeded successfully
- Group ID: 1
- Machine ID: 1
- States Created: 5
- Transitions Created: 5
```

### Test 3: Database Verification ✅
```
- Workflow Groups: 1
- State Machines: 1 (is_default=TRUE)
- States: 5
- Transitions: 5
- All FK constraints: ✓ VALID
```

## Files Created/Modified

### New Files:
1. `/src/Data/YmlParser.php` (340 lines)
2. `/src/Data/WorkflowSeeder.php` (520 lines)
3. `/src/Controllers/WorkflowSeederController.php` (180 lines)
4. `/src/Data/defaults/blog-post-workflow.yml` (sample)
5. `/test-workflow-seeder.php` (test script)

### Modified Files:
1. `/src/Database/Tables/StateMachinesDB.php` - Added is_default field
2. `/src/Models/StateMachine/StateMachineModel.php` - Updated prepareInsertData()
3. `/src/Views/admin/settings/settings-view.php` - Added UI section
4. `/assets/js/settings.js` - Added seeder handlers
5. `/includes/class-autoloader.php` - Added composer autoload
6. `/wp-state-machine.php` - Registered WorkflowSeederController

## Usage Instructions

### For Developers:

**1. Define Workflow in YML:**
```bash
# Create YML file in /src/Data/defaults/
nano src/Data/defaults/your-workflow.yml
```

**2. Seed from PHP:**
```php
use WPStateMachine\Data\WorkflowSeeder;

$seeder = new WorkflowSeeder();
$result = $seeder->seedAllDefaults();
```

**3. Via Admin UI:**
- Go to: State Machines > Settings > Database tab
- Click "Seed Default Workflows"
- Or "Reset to Default Workflows" to re-seed

### For Users:

**Seed Workflows:**
1. Navigate to State Machines > Settings
2. Click "Database" tab
3. Scroll to "Default Workflows" section
4. Click "Seed Default Workflows"

**Reset to Defaults:**
1. Same location as above
2. Click "Reset to Default Workflows"
3. Confirm the warning (backup created automatically)

## Safety Features

1. **Double Transaction Safety:** All operations wrapped in transactions with ROLLBACK on error
2. **Automatic Backups:** JSON backups created before reset
3. **Custom Workflow Preservation:** Only deletes workflows with `is_default=1`
4. **Validation:** Comprehensive YML structure validation before import
5. **FK Handling:** Proper foreign key relationship management
6. **Error Logging:** Detailed error messages and debug logging

## Future Enhancements

Potential improvements for future versions:
1. YML file upload via admin UI
2. Export existing workflows to YML
3. Workflow versioning system
4. Import/export single workflows
5. YML syntax validation in UI
6. Preview before import
7. Rollback to previous backup

## Related Documentation

- Main implementation roadmap: `/TODO/TODO-6102-implementation-roadmap.md`
- DataTable enhancements: `/TODO/TODO-6105-datatable-enhancements.md`

## Conclusion

All TODO-6106 tasks completed successfully:
- ✅ YML Parser implementation
- ✅ Workflow Seeder system
- ✅ Database schema updates
- ✅ AJAX handlers
- ✅ UI integration
- ✅ Testing and verification

The system is production-ready and can be used immediately for seeding default workflows from YML files.

---
**Developer:** Claude Code
**Reviewer:** arisciwek
**Version:** 1.0.0
