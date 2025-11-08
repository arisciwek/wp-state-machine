# TODO-6106: YML Seeder Implementation
**Created:** 2025-11-08
**Version:** 1.0.0
**Context:** Default Workflow Seeding & Reset System
**Priority:** HIGH
**Estimated Effort:** 6-8 hours

---

## OVERVIEW

Implementasi system untuk seed default workflows dari YML files ke database, dengan fitur "Reset to Default" untuk restore workflow yang sudah dimodifikasi user kembali ke konfigurasi original.

### Problem Statement
- User harus manual create workflows dari scratch
- Tidak ada starting templates untuk common workflows
- Tidak bisa restore workflow yang sudah dimodifikasi
- Kehilangan reference ke best practice workflows

### Solution
- Parse YML workflow definitions
- Import ke database dengan flag `is_default`
- UI untuk manage default workflows
- Reset button untuk restore ke original state

### Benefits
- Quick start dengan pre-configured workflows
- Learning by example (3 workflow types)
- Easy recovery dari user modifications
- Consistent workflow patterns

---

## ARCHITECTURE

### Data Flow
```
YML Files (examples/)
    ↓
WorkflowYmlParser
    ↓
WorkflowSeeder
    ↓
Database (with is_default flag)
    ↓
UI (Import/Reset buttons)
    ↓
AJAX Handlers
```

### Database Strategy
```sql
-- Flag untuk tracking default workflows
is_default TINYINT(1) -- 1 = seeded from YML, 0 = user created
is_custom TINYINT(1)  -- 1 = modified from default, 0 = untouched

Kombinasi:
- is_default=1, is_custom=0: Default workflow (not modified)
- is_default=1, is_custom=1: Default workflow (modified by user)
- is_default=0, is_custom=0: User created (new)
- is_default=0, is_custom=1: User created (modified)
```

### YML Structure Reference
```yaml
id: blog_post_workflow
label: Blog Post Workflow
entity_type: post
workflow_group: content-management
initial_state: draft

states:
  draft:
    label: Draft
    description: Post being written
    weight: 0
    color: '#6c757d'

transitions:
  submit_for_review:
    label: Submit for Review
    from: ['draft']
    to: review
```

---

## IMPLEMENTATION ROADMAP

### PHASE 1: Database Schema Update
**Effort:** 30 minutes
**Status:** 🔲 PENDING

**Tasks:**
- [ ] Add `is_default` column to `wp_app_sm_machines` table
- [ ] Add migration script for existing data
- [ ] Update StateMachinesDB.php schema definition
- [ ] Test schema changes
- [ ] Verify foreign key constraints still work

**SQL Migration:**
```sql
ALTER TABLE wp_app_sm_machines
ADD COLUMN is_default TINYINT(1) NOT NULL DEFAULT 0
AFTER is_custom
COMMENT 'Flag: 1=seeded from YML, 0=user created';

-- Index for querying default workflows
CREATE INDEX idx_is_default ON wp_app_sm_machines(is_default);
```

**Files to Update:**
- `/src/Database/Tables/StateMachinesDB.php`
- `/src/Models/StateMachine/StateMachineModel.php` (add field to format map)

---

### PHASE 2: YML Parser Implementation
**Effort:** 2-3 hours
**Status:** 🔲 PENDING

**Tasks:**
- [ ] Check PHP YAML extension availability
- [ ] Install Symfony YAML component as fallback
- [ ] Create WorkflowYmlParser class
- [ ] Implement parse() method
- [ ] Implement validate() method
- [ ] Add error handling for invalid YML
- [ ] Extract workflow components (group, machine, states, transitions)
- [ ] Write unit tests for parser
- [ ] Test with all 3 YML files

**Class Structure:**
```php
namespace WPStateMachine\Seeders;

class WorkflowYmlParser {
    /**
     * Parse YML file into array structure
     * @param string $yml_file Full path to YML file
     * @return array Parsed workflow data
     * @throws Exception if YML invalid
     */
    public function parse(string $yml_file): array;

    /**
     * Validate YML structure
     * @param array $data Parsed YML data
     * @return bool True if valid
     */
    public function validate(array $data): bool;

    /**
     * Extract workflow group data
     */
    public function extractWorkflowGroup(array $data): array;

    /**
     * Extract machine data
     */
    public function extractMachine(array $data): array;

    /**
     * Extract states with proper ordering
     */
    public function extractStates(array $data): array;

    /**
     * Extract transitions with metadata
     */
    public function extractTransitions(array $data): array;

    /**
     * Get YML metadata (version, author, etc)
     */
    public function getMetadata(array $data): array;
}
```

**Validation Rules:**
- Required fields: id, label, states, transitions
- Valid state references in transitions
- No circular dependencies
- Valid color codes
- Valid icon names

**Files to Create:**
- `/src/Seeders/WorkflowYmlParser.php`

**Dependencies:**
```bash
composer require symfony/yaml
```

---

### PHASE 3: Workflow Seeder Implementation
**Effort:** 2-3 hours
**Status:** 🔲 PENDING

**Tasks:**
- [ ] Create WorkflowSeeder class
- [ ] Implement seedFromYml() method
- [ ] Implement resetToDefault() method
- [ ] Add transaction handling
- [ ] Add rollback on error
- [ ] Implement conflict detection
- [ ] Add logging for seeding operations
- [ ] Create helper method for cleanup
- [ ] Test seeding all 3 workflows
- [ ] Test reset functionality

**Class Structure:**
```php
namespace WPStateMachine\Seeders;

use WPStateMachine\Models\WorkflowGroup\WorkflowGroupModel;
use WPStateMachine\Models\StateMachine\StateMachineModel;
use WPStateMachine\Models\State\StateModel;
use WPStateMachine\Models\Transition\TransitionModel;

class WorkflowSeeder {
    private $parser;
    private $group_model;
    private $machine_model;
    private $state_model;
    private $transition_model;

    /**
     * Seed workflow from YML file
     * @param string $yml_file Path to YML file
     * @param bool $force_overwrite Force overwrite if exists
     * @return array Result summary
     */
    public function seedFromYml(string $yml_file, bool $force_overwrite = false): array;

    /**
     * Reset workflow to default YML state
     * @param string $workflow_id Workflow slug/id
     * @return bool Success status
     */
    public function resetToDefault(string $workflow_id): bool;

    /**
     * List all available default workflows
     * @return array List of YML files with status
     */
    public function listDefaultWorkflows(): array;

    /**
     * Check if workflow exists in database
     */
    public function workflowExists(string $workflow_id): bool;

    /**
     * Check if workflow is modified from default
     */
    public function isModified(string $workflow_id): bool;

    /**
     * Get YML file path for workflow
     */
    private function getYmlPath(string $workflow_id): string;

    /**
     * Insert workflow group
     */
    private function insertWorkflowGroup(array $data): int;

    /**
     * Insert state machine
     */
    private function insertMachine(array $data, int $group_id): int;

    /**
     * Insert states for machine
     */
    private function insertStates(int $machine_id, array $states): void;

    /**
     * Insert transitions for machine
     */
    private function insertTransitions(int $machine_id, array $transitions, array $state_map): void;

    /**
     * Delete workflow and all related data
     */
    private function deleteWorkflow(int $machine_id): bool;
}
```

**Seeding Logic:**
```
1. Parse YML file
2. Start database transaction
3. Check workflow_group:
   - Create if not exists
   - Update if exists & not custom
   - Skip if custom (ask confirmation)
4. Check machine:
   - Create new if not exists
   - Update if exists & not custom
   - Error if custom & !force_overwrite
5. Delete existing states & transitions
6. Insert states (preserve weight/order)
7. Build state_name => state_id map
8. Insert transitions (use state_id map)
9. Commit transaction
10. Clear cache
11. Return summary
```

**Reset Logic:**
```
1. Validate workflow_id exists
2. Check is_default flag
3. Get YML file path
4. Confirmation check
5. Start transaction
6. Delete transitions
7. Delete states
8. Delete machine
9. Re-seed from YML
10. Commit transaction
11. Clear cache
12. Log action
```

**Files to Create:**
- `/src/Seeders/WorkflowSeeder.php`

---

### PHASE 4: Settings UI - Default Workflows Tab
**Effort:** 1-2 hours
**Status:** 🔲 PENDING

**Tasks:**
- [ ] Add "Default Workflows" tab to Settings page
- [ ] Create workflow cards UI
- [ ] Show workflow status (Not Seeded, Seeded, Modified)
- [ ] Add Import button for each workflow
- [ ] Add Reset to Default button
- [ ] Add View YML button (modal with YML preview)
- [ ] Add confirmation dialogs
- [ ] Style workflow cards
- [ ] Add loading states
- [ ] Test responsive design

**UI Components:**

```html
<!-- Default Workflows Tab -->
<div id="default-workflows-tab" class="settings-tab">
    <h2>Default Workflows</h2>
    <p class="description">
        Import pre-configured workflows or reset modified workflows to their original state.
    </p>

    <div class="workflow-cards-grid">
        <!-- Card for each YML file -->
        <div class="workflow-card" data-workflow-id="blog_post_workflow">
            <div class="workflow-icon">📝</div>
            <div class="workflow-info">
                <h3>Blog Post Workflow</h3>
                <p>Simple editorial workflow for blog posts</p>
                <div class="workflow-meta">
                    <span class="badge">4 States</span>
                    <span class="badge">5 Transitions</span>
                </div>
            </div>
            <div class="workflow-status">
                <span class="status-badge status-seeded">
                    ✅ Seeded (Not Modified)
                </span>
            </div>
            <div class="workflow-actions">
                <button class="button btn-view-yml" data-yml="blog-post-workflow.yml">
                    View YML
                </button>
                <button class="button btn-reset-default" data-workflow-id="blog_post_workflow">
                    Reset to Default
                </button>
            </div>
        </div>

        <!-- More cards... -->
    </div>
</div>
```

**Status Badges:**
- 🔲 Not Seeded (gray) - Not imported yet
- ✅ Seeded (green) - Imported, not modified
- ⚠️ Modified (orange) - Imported but user changed it
- 🔴 Error (red) - Seeding failed

**Files to Update:**
- `/src/Views/admin/settings/settings-view.php`
- `/assets/css/settings.css`

---

### PHASE 5: JavaScript Handlers
**Effort:** 1-2 hours
**Status:** 🔲 PENDING

**Tasks:**
- [ ] Add default workflows tab handlers
- [ ] Implement import workflow AJAX
- [ ] Implement reset workflow AJAX
- [ ] Implement view YML modal
- [ ] Add confirmation dialogs
- [ ] Add loading indicators
- [ ] Add success/error toasts
- [ ] Update workflow card status after actions
- [ ] Add error handling
- [ ] Test all workflows

**JavaScript Functions:**

```javascript
const DefaultWorkflows = {
    /**
     * Import workflow from YML
     */
    importWorkflow: function(ymlFile) {
        // Show confirmation
        // AJAX to import
        // Update card status
        // Show success toast
    },

    /**
     * Reset workflow to default
     */
    resetWorkflow: function(workflowId) {
        // Confirmation dialog with warning
        // AJAX to reset
        // Update card status
        // Show success toast
    },

    /**
     * View YML content in modal
     */
    viewYml: function(ymlFile) {
        // Fetch YML content
        // Show in modal with syntax highlighting
    },

    /**
     * Refresh workflow cards
     */
    refreshCards: function() {
        // AJAX to get latest status
        // Update all cards
    }
};
```

**Files to Update:**
- `/assets/js/settings.js`

---

### PHASE 6: AJAX Handlers in Controller
**Effort:** 1 hour
**Status:** 🔲 PENDING

**Tasks:**
- [ ] Add import workflow handler
- [ ] Add reset workflow handler
- [ ] Add list workflows handler
- [ ] Add view YML handler
- [ ] Add permission checks
- [ ] Add nonce verification
- [ ] Add error handling
- [ ] Add logging
- [ ] Test all handlers

**Controller Methods:**

```php
// SettingsController.php

/**
 * Import default workflow from YML
 */
public function importDefaultWorkflow() {
    check_ajax_referer('wp_state_machine_nonce', 'nonce');

    if (!current_user_can('manage_state_machines')) {
        wp_send_json_error(['message' => 'Permission denied']);
    }

    $yml_file = sanitize_text_field($_POST['yml_file']);
    $force = isset($_POST['force']) ? (bool)$_POST['force'] : false;

    $seeder = new WorkflowSeeder();
    $result = $seeder->seedFromYml($yml_file, $force);

    if ($result['success']) {
        wp_send_json_success([
            'message' => 'Workflow imported successfully',
            'summary' => $result['summary']
        ]);
    } else {
        wp_send_json_error([
            'message' => $result['error']
        ]);
    }
}

/**
 * Reset workflow to default YML state
 */
public function resetWorkflowToDefault() {
    check_ajax_referer('wp_state_machine_nonce', 'nonce');

    if (!current_user_can('manage_state_machines')) {
        wp_send_json_error(['message' => 'Permission denied']);
    }

    $workflow_id = sanitize_text_field($_POST['workflow_id']);

    $seeder = new WorkflowSeeder();
    $result = $seeder->resetToDefault($workflow_id);

    if ($result) {
        wp_send_json_success([
            'message' => 'Workflow reset to default successfully'
        ]);
    } else {
        wp_send_json_error([
            'message' => 'Failed to reset workflow'
        ]);
    }
}

/**
 * List all default workflows with status
 */
public function listDefaultWorkflows() {
    check_ajax_referer('wp_state_machine_nonce', 'nonce');

    $seeder = new WorkflowSeeder();
    $workflows = $seeder->listDefaultWorkflows();

    wp_send_json_success(['workflows' => $workflows]);
}

/**
 * Get YML file content for preview
 */
public function getYmlContent() {
    check_ajax_referer('wp_state_machine_nonce', 'nonce');

    $yml_file = sanitize_text_field($_POST['yml_file']);
    $file_path = WP_STATE_MACHINE_PATH . 'examples/' . $yml_file;

    if (!file_exists($file_path)) {
        wp_send_json_error(['message' => 'YML file not found']);
    }

    $content = file_get_contents($file_path);
    wp_send_json_success(['content' => $content]);
}
```

**AJAX Actions to Register:**
```php
add_action('wp_ajax_import_default_workflow', [$this, 'importDefaultWorkflow']);
add_action('wp_ajax_reset_workflow_to_default', [$this, 'resetWorkflowToDefault']);
add_action('wp_ajax_list_default_workflows', [$this, 'listDefaultWorkflows']);
add_action('wp_ajax_get_yml_content', [$this, 'getYmlContent']);
```

**Files to Update:**
- `/src/Controllers/SettingsController.php`

---

### PHASE 7: Testing & Validation
**Effort:** 1 hour
**Status:** 🔲 PENDING

**Tasks:**
- [ ] Test YML parser with all 3 files
- [ ] Test seeding blog-post-workflow.yml
- [ ] Test seeding order-state-machine.yml
- [ ] Test seeding support-ticket-workflow.yml
- [ ] Test conflict handling (workflow exists)
- [ ] Test reset functionality
- [ ] Test permissions
- [ ] Test error handling
- [ ] Test cache invalidation
- [ ] Verify data integrity after seed/reset

**Test Cases:**

1. **Import Fresh Workflow**
   - ✓ Workflow group created
   - ✓ Machine created with is_default=1
   - ✓ All states created with correct order
   - ✓ All transitions created with correct references
   - ✓ Initial state set correctly

2. **Import Existing Workflow (Not Modified)**
   - ✓ Updates existing records
   - ✓ Maintains is_default=1
   - ✓ Overwrites changes

3. **Import Existing Workflow (Modified by User)**
   - ✓ Shows confirmation dialog
   - ✓ Requires force flag
   - ✓ Logs overwrite action

4. **Reset to Default**
   - ✓ Deletes all custom changes
   - ✓ Re-seeds from YML
   - ✓ is_custom reset to 0
   - ✓ Cache cleared

5. **Error Handling**
   - ✓ Invalid YML syntax
   - ✓ Missing required fields
   - ✓ Invalid state references
   - ✓ Database errors rollback transaction

---

## FILES SUMMARY

### Files to Create
```
/src/Seeders/
├── WorkflowYmlParser.php          (NEW)
└── WorkflowSeeder.php              (NEW)
```

### Files to Update
```
/src/Database/Tables/
└── StateMachinesDB.php             (UPDATE - add is_default column)

/src/Models/StateMachine/
└── StateMachineModel.php           (UPDATE - add is_default to format map)

/src/Controllers/
└── SettingsController.php          (UPDATE - add AJAX handlers)

/src/Views/admin/settings/
└── settings-view.php               (UPDATE - add Default Workflows tab)

/assets/css/
└── settings.css                    (UPDATE - workflow cards styling)

/assets/js/
└── settings.js                     (UPDATE - import/reset handlers)
```

### YML Files (Reference Only)
```
/examples/
├── blog-post-workflow.yml          (EXISTS - 4 states, 5 transitions)
├── order-state-machine.yml         (EXISTS - 8 states, 7 transitions)
└── support-ticket-workflow.yml     (EXISTS - 8 states, 11 transitions)
```

---

## DEPENDENCIES

### PHP Extensions
```bash
# Check if YAML extension available
php -m | grep yaml

# If not, use Symfony YAML component
composer require symfony/yaml
```

### Required Classes
- WorkflowGroupModel (exists)
- StateMachineModel (exists)
- StateModel (exists)
- TransitionModel (exists)

### Database Schema
- wp_app_sm_workflow_groups (exists)
- wp_app_sm_machines (needs is_default column)
- wp_app_sm_states (exists)
- wp_app_sm_transitions (exists)

---

## SECURITY CONSIDERATIONS

1. **File Access**: Only read YML from examples/ directory
2. **Permissions**: Only admin can import/reset workflows
3. **SQL Injection**: Use wpdb prepare for all queries
4. **Nonce Verification**: Required for all AJAX requests
5. **Input Validation**: Sanitize all user inputs
6. **Transaction Safety**: Rollback on any error

---

## USER EXPERIENCE FLOW

### Import Workflow
```
1. User goes to Settings > Default Workflows
2. Sees card "Blog Post Workflow - Not Seeded"
3. Clicks "Import Workflow"
4. Confirmation: "This will create workflow group, machine, states, transitions"
5. Loading indicator
6. Success toast: "Blog Post Workflow imported successfully"
7. Card status updates to "Seeded (Not Modified)"
```

### Reset to Default
```
1. User sees "Blog Post Workflow - Modified"
2. Clicks "Reset to Default"
3. Warning dialog: "This will delete all your changes. Are you sure?"
4. User confirms
5. Loading indicator
6. Success toast: "Workflow reset to default"
7. Card status updates to "Seeded (Not Modified)"
```

### View YML
```
1. User clicks "View YML"
2. Modal opens with syntax-highlighted YML content
3. User can review the workflow structure
4. Close modal
```

---

## ROLLOUT PLAN

### Phase 1: Core Implementation (Day 1)
- Database schema update
- YML Parser
- Workflow Seeder
- Manual testing via PHP script

### Phase 2: UI Integration (Day 2)
- Settings tab
- AJAX handlers
- JavaScript interactions
- Manual testing via UI

### Phase 3: Polish & Testing (Day 3)
- Error handling
- Confirmation dialogs
- Loading states
- Full end-to-end testing

---

## SUCCESS CRITERIA

- [x] All 3 YML files can be imported successfully
- [x] Workflows appear correctly in UI after import
- [x] States and transitions work as expected
- [x] Reset to default works without errors
- [x] Modified workflows show correct status
- [x] Permissions enforced properly
- [x] No data loss on reset (logged/backed up)
- [x] Cache invalidated after seed/reset
- [x] Error messages are clear and helpful
- [x] UI is intuitive and responsive

---

## FUTURE ENHANCEMENTS

### Phase 2 (Future)
- [ ] Export custom workflow to YML
- [ ] Share workflows between sites
- [ ] Workflow marketplace/repository
- [ ] Version control for workflows
- [ ] Diff view (show changes from default)
- [ ] Selective reset (states only, transitions only)
- [ ] Backup before reset
- [ ] Workflow templates gallery
- [ ] Import from URL
- [ ] Batch import multiple workflows

---

## NOTES

- YML files are read-only references
- Database is source of truth after seeding
- Reset recreates from YML (loses custom changes)
- Workflow IDs must match YML file ids
- State names used as references for transitions
- Weight determines display order
- Colors follow Bootstrap color scheme
- Icons use dashicons or custom

---

## REFERENCES

### YML Files
- `/examples/blog-post-workflow.yml` - Simple example
- `/examples/order-state-machine.yml` - E-commerce example
- `/examples/support-ticket-workflow.yml` - Comprehensive example

### Models
- WorkflowGroupModel: `/src/Models/WorkflowGroup/WorkflowGroupModel.php`
- StateMachineModel: `/src/Models/StateMachine/StateMachineModel.php`
- StateModel: `/src/Models/State/StateModel.php`
- TransitionModel: `/src/Models/Transition/TransitionModel.php`

### Related TODOs
- TODO-6102: Main implementation roadmap
- TODO-6103: Abstraction & refactoring
- TODO-6104: Per-plugin log tables

---

**Status:** 🔲 NOT STARTED
**Next Action:** Database schema update (add is_default column)
**Estimated Completion:** 3 days (with testing)
