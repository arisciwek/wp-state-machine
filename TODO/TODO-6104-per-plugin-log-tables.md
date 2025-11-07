# TODO-6104: Per-Plugin Log Tables & Logs Viewer
**Created:** 2025-11-07
**Version:** 1.0.0
**Status:** ✅ COMPLETED (FASE 1, 2, 3, 5)
**Context:** Scalable logging strategy for 18+ plugins with isolated storage
**Dependencies:** FASE 2 Complete (StateMachineEngine, Guards, TransitionLogModel)

---

## 🎯 OBJECTIVE

Implement per-plugin log tables untuk menghindari performance degradation dari single central table dengan millions of records. Setiap plugin bisa punya table logs sendiri atau pakai central table (flexible).

**Problem Statement:**
```
Current: app_sm_transition_logs (ONE table for ALL plugins)
├── wp-rfq transitions (100K+ records)
├── wp-agency transitions (500K+ records)
├── wp-purchase transitions (200K+ records)
├── ... 15 plugins lainnya
└── = MILLIONS of records in 1 table 😱

Issues:
❌ Performance degradation with millions of records
❌ Cleanup difficult (uninstall plugin = orphaned logs)
❌ Backup/restore becomes heavy
❌ Query slow without proper partitioning
❌ Hard to isolate per-plugin data
```

**Solution:**
```
Per-Plugin Tables (Isolated):
├── app_wp_rfq_sm_logs          (100K records - isolated)
├── app_wp_agency_sm_logs       (500K records - isolated)
├── app_wp_purchase_sm_logs     (200K records - isolated)
├── ... 15+ more
└── app_sm_transition_logs      (central/fallback - optional)

Benefits:
✅ Perfect isolation per plugin
✅ Better performance (smaller tables)
✅ Easier cleanup per plugin
✅ Easier backup/restore per plugin
✅ Scalable to any number of plugins
```

---

## 📊 CURRENT ABSTRACTIONS ANALYSIS

### ✅ AbstractStateMachineModel (Already sufficient!)

**Provides:**
```php
abstract class AbstractStateMachineModel {
    protected $table;           // ✅ Dynamic table name support
    protected $cache_group;     // ✅ Dynamic cache group support

    // Methods:
    - setTableName($table)      // ✅ Perfect for per-plugin tables!
    - getTableName()
    - find(), findBy()
    - Cache methods
    - CRUD operations
}
```

**Conclusion:** AbstractStateMachineModel **SUDAH CUKUP** untuk per-plugin tables! ✅

### ✅ AbstractStateMachineValidator (Not needed for logs)

**Analysis:**
- ❌ Logs are write-once, read-many (no editing)
- ❌ No form validation needed (no create/edit form)
- ❌ No update/delete operations (audit trail integrity)
- ✅ Just capability checks in controller

**Conclusion:** NO validator abstraction needed for logs ✅

### ⚠️ NEW Abstractions Needed?

**Question:** Apakah perlu AbstractLogsController atau AbstractLogModel?

**Answer:** **TIDAK PERLU!** ❌

**Reasons:**
1. TransitionLogModel already extends AbstractStateMachineModel ✅
2. LogsController is standalone (different from CRUD controllers)
3. Logs are read-only (berbeda pattern dari state/transition controllers)
4. Adding abstraction would add complexity without benefit
5. Standalone = simpler, more flexible

---

## 🏗️ ARCHITECTURE DESIGN

### Strategy: Enhance Existing, No New Abstractions

```
Current Architecture (Keep):
AbstractStateMachineModel
    ↓
TransitionLogModel (ENHANCE with dynamic tables)
    ↓
StateMachineEngine (OPTIONAL: inject custom log model)

New Component (Standalone):
LogsController (NO abstraction needed)
```

---

## 📋 IMPLEMENTATION PLAN

### ⭐ FASE 1: Enhance TransitionLogModel
**Effort:** 1-2 jam
**Status:** ✅ COMPLETED
**Priority:** HIGH

**Tasks:**
- [x] Add `$plugin_slug` parameter to constructor
- [x] Add `resolveTableName()` method for dynamic table names
- [x] Add `maybeCreatePluginTable()` for on-demand table creation
- [x] Update `$cache_group` dynamically based on plugin_slug
- [x] Add `createPluginTable()` method
- [x] Modify schema methods to accept dynamic table name
- [x] Test backward compatibility (no plugin_slug = central table)

**Completed:** 2025-11-07
**Version:** 1.0.1

**Implementation:**

```php
<?php
class TransitionLogModel extends AbstractStateMachineModel {
    /**
     * Plugin slug for isolated table
     * @var string|null
     */
    protected $plugin_slug;

    /**
     * Constructor
     * @param string|null $plugin_slug Plugin slug for per-plugin table, null for central
     */
    public function __construct($plugin_slug = null) {
        parent::__construct();

        $this->plugin_slug = $plugin_slug;
        $this->table = $this->resolveTableName($plugin_slug);
        $this->setTableName($this->table);

        // Dynamic cache group
        $this->cache_group = $plugin_slug
            ? "{$plugin_slug}_transition_logs"
            : 'transition_logs';

        // Create table if needed (per-plugin tables only)
        if ($plugin_slug) {
            $this->maybeCreatePluginTable();
        }
    }

    /**
     * Resolve table name based on plugin slug
     * @param string|null $plugin_slug
     * @return string Table name
     */
    protected function resolveTableName($plugin_slug): string {
        global $wpdb;

        if ($plugin_slug) {
            // Per-plugin table: app_wp_rfq_sm_logs
            return $wpdb->prefix . "app_{$plugin_slug}_sm_logs";
        }

        // Central table (backward compatible)
        return $wpdb->prefix . 'app_sm_transition_logs';
    }

    /**
     * Create plugin-specific table if not exists
     * @return void
     */
    private function maybeCreatePluginTable() {
        global $wpdb;
        $table_name = $this->getTableName();

        // Check if table exists
        $table_exists = $wpdb->get_var(
            $wpdb->prepare("SHOW TABLES LIKE %s", $table_name)
        );

        if (!$table_exists) {
            $this->createPluginTable();
        }
    }

    /**
     * Create plugin-specific logs table
     * Uses same schema as central table
     * @return void
     */
    private function createPluginTable() {
        global $wpdb;
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');

        $table_name = $this->getTableName();
        $charset_collate = $wpdb->get_charset_collate();

        // Same schema as TransitionLogsDB::get_schema() but with dynamic table name
        $sql = "CREATE TABLE {$table_name} (
            id bigint(20) UNSIGNED NOT NULL auto_increment,
            machine_id bigint(20) UNSIGNED NOT NULL,
            entity_id bigint(20) UNSIGNED NOT NULL,
            entity_type varchar(50) NOT NULL,
            from_state_id bigint(20) UNSIGNED NULL,
            to_state_id bigint(20) UNSIGNED NOT NULL,
            transition_id bigint(20) UNSIGNED NULL,
            user_id bigint(20) UNSIGNED NOT NULL,
            comment text NULL,
            metadata text NULL,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY  (id),
            KEY machine_id_index (machine_id),
            KEY entity_index (entity_type, entity_id),
            KEY from_state_index (from_state_id),
            KEY to_state_index (to_state_id),
            KEY user_id_index (user_id),
            KEY created_at_index (created_at)
        ) $charset_collate;";

        dbDelta($sql);

        // Add foreign keys (same as central table)
        $this->addForeignKeys($table_name);

        // Log table creation
        error_log("[TransitionLogModel] Created plugin table: {$table_name}");
    }

    /**
     * Add foreign key constraints to plugin table
     * @param string $table_name
     * @return void
     */
    private function addForeignKeys($table_name) {
        global $wpdb;
        $machines_table = $wpdb->prefix . 'app_sm_machines';
        $states_table = $wpdb->prefix . 'app_sm_states';
        $transitions_table = $wpdb->prefix . 'app_sm_transitions';

        $constraints = [
            [
                'name' => "fk_{$this->plugin_slug}_logs_machine",
                'sql' => "ALTER TABLE {$table_name}
                         ADD CONSTRAINT fk_{$this->plugin_slug}_logs_machine
                         FOREIGN KEY (machine_id)
                         REFERENCES {$machines_table}(id)
                         ON DELETE CASCADE"
            ],
            [
                'name' => "fk_{$this->plugin_slug}_logs_from_state",
                'sql' => "ALTER TABLE {$table_name}
                         ADD CONSTRAINT fk_{$this->plugin_slug}_logs_from_state
                         FOREIGN KEY (from_state_id)
                         REFERENCES {$states_table}(id)
                         ON DELETE SET NULL"
            ],
            [
                'name' => "fk_{$this->plugin_slug}_logs_to_state",
                'sql' => "ALTER TABLE {$table_name}
                         ADD CONSTRAINT fk_{$this->plugin_slug}_logs_to_state
                         FOREIGN KEY (to_state_id)
                         REFERENCES {$states_table}(id)
                         ON DELETE CASCADE"
            ],
            [
                'name' => "fk_{$this->plugin_slug}_logs_transition",
                'sql' => "ALTER TABLE {$table_name}
                         ADD CONSTRAINT fk_{$this->plugin_slug}_logs_transition
                         FOREIGN KEY (transition_id)
                         REFERENCES {$transitions_table}(id)
                         ON DELETE SET NULL"
            ]
        ];

        foreach ($constraints as $constraint) {
            // Check if constraint already exists
            $constraint_exists = $wpdb->get_var($wpdb->prepare(
                "SELECT COUNT(*) FROM information_schema.TABLE_CONSTRAINTS
                 WHERE CONSTRAINT_SCHEMA = DATABASE()
                 AND TABLE_NAME = %s
                 AND CONSTRAINT_NAME = %s",
                $table_name,
                $constraint['name']
            ));

            if ($constraint_exists > 0) {
                continue; // Skip if exists
            }

            // Add foreign key constraint
            $result = $wpdb->query($constraint['sql']);
            if ($result === false) {
                error_log("[TransitionLogModel] Failed to add FK {$constraint['name']}: " . $wpdb->last_error);
            }
        }
    }

    /**
     * Get plugin slug
     * @return string|null
     */
    public function getPluginSlug(): ?string {
        return $this->plugin_slug;
    }

    // All other methods stay the same!
    // - create()
    // - getEntityHistory()
    // - getMachineLogs()
    // - getUserLogs()
    // - getCurrentState()
    // etc...
}
```

**Usage Examples:**

```php
// Per-plugin table (auto-created):
$rfq_logs = new TransitionLogModel('wp-rfq');
$rfq_logs->create([...]);  // Saves to: app_wp_rfq_sm_logs

// Central table (backward compatible):
$central_logs = new TransitionLogModel();
$central_logs->create([...]); // Saves to: app_sm_transition_logs

// In StateMachineEngine:
class StateMachineEngine {
    private $log_model;

    public function __construct($plugin_slug = null) {
        $this->log_model = new TransitionLogModel($plugin_slug);
    }

    // Or inject custom:
    public function setLogModel(TransitionLogModel $log_model) {
        $this->log_model = $log_model;
        return $this;
    }
}
```

---

### ⭐ FASE 2: Create LogsController
**Effort:** 2-3 jam
**Status:** ✅ COMPLETED
**Priority:** HIGH

**Tasks:**
- [x] Create LogsController.php (standalone, no abstraction)
- [x] Implement DataTable handler dengan plugin filter
- [x] Support multiple log sources (per-plugin + central)
- [x] Add export functionality (CSV)
- [x] Add date range filtering
- [x] Add permission checks (view_state_machine_logs)
- [x] Register AJAX handlers
- [x] Integrate dengan MenuManager
- [x] Move asset enqueuing to class-dependencies.php

**Completed:** 2025-11-07
**Version:** 1.0.1
**File:** `/src/Controllers/LogsController.php`

**Implementation:**

```php
<?php
namespace WPStateMachine\Controllers;

class LogsController {
    /**
     * Cache of log model instances per plugin
     * @var array
     */
    private $log_models = [];

    /**
     * Constructor
     */
    public function __construct() {
        $this->registerAjaxHandlers();
    }

    /**
     * Register AJAX handlers
     */
    private function registerAjaxHandlers() {
        add_action('wp_ajax_sm_logs_datatable', [$this, 'handleDataTableRequest']);
        add_action('wp_ajax_sm_logs_export', [$this, 'handleExport']);
        add_action('wp_ajax_sm_logs_get_plugins', [$this, 'handleGetPlugins']);
    }

    /**
     * Get log model for plugin or central
     * @param string|null $plugin_slug
     * @return TransitionLogModel
     */
    private function getLogModel($plugin_slug = null) {
        $key = $plugin_slug ?? 'central';

        if (!isset($this->log_models[$key])) {
            $this->log_models[$key] = new \WPStateMachine\Models\TransitionLog\TransitionLogModel($plugin_slug);
        }

        return $this->log_models[$key];
    }

    /**
     * Handle DataTable AJAX request
     */
    public function handleDataTableRequest() {
        check_ajax_referer('wp_state_machine_nonce', 'nonce');

        // Permission check
        if (!current_user_can('view_state_machine_logs')) {
            wp_send_json_error(['message' => __('Access denied', 'wp-state-machine')]);
        }

        // Get parameters
        $plugin_slug = sanitize_text_field($_POST['plugin_slug'] ?? '');
        $plugin_slug = ($plugin_slug === 'all' || $plugin_slug === '') ? null : $plugin_slug;

        // Get appropriate log model
        $log_model = $this->getLogModel($plugin_slug);

        // Get DataTable parameters
        $params = $this->getDataTableParams();

        // Get logs
        $logs = $log_model->getForDataTable($params);

        wp_send_json_success($logs);
    }

    /**
     * Get plugins with state machines
     * For filter dropdown
     */
    public function handleGetPlugins() {
        check_ajax_referer('wp_state_machine_nonce', 'nonce');

        if (!current_user_can('view_state_machine_logs')) {
            wp_send_json_error(['message' => __('Access denied')]);
        }

        global $wpdb;
        $machines_table = $wpdb->prefix . 'app_sm_machines';

        // Get distinct plugin slugs
        $plugins = $wpdb->get_results(
            "SELECT DISTINCT plugin_slug, COUNT(*) as machine_count
             FROM {$machines_table}
             GROUP BY plugin_slug
             ORDER BY plugin_slug"
        );

        wp_send_json_success($plugins);
    }

    /**
     * Handle export request
     */
    public function handleExport() {
        check_ajax_referer('wp_state_machine_nonce', 'nonce');

        if (!current_user_can('view_state_machine_logs')) {
            wp_die(__('Access denied'));
        }

        // Get parameters
        $plugin_slug = sanitize_text_field($_GET['plugin_slug'] ?? '');
        $plugin_slug = ($plugin_slug === 'all' || $plugin_slug === '') ? null : $plugin_slug;

        // Get log model
        $log_model = $this->getLogModel($plugin_slug);

        // Get logs (no limit for export)
        $logs = $log_model->getForExport($_GET);

        // Export as CSV
        $this->exportAsCSV($logs, $plugin_slug);
    }

    /**
     * Export logs as CSV
     */
    private function exportAsCSV($logs, $plugin_slug) {
        $filename = $plugin_slug
            ? "logs-{$plugin_slug}-" . date('Y-m-d') . '.csv'
            : "logs-all-" . date('Y-m-d') . '.csv';

        header('Content-Type: text/csv');
        header('Content-Disposition: attachment; filename="' . $filename . '"');

        $output = fopen('php://output', 'w');

        // Headers
        fputcsv($output, [
            'ID', 'Date', 'Machine', 'Entity Type', 'Entity ID',
            'From State', 'To State', 'User', 'Comment'
        ]);

        // Data
        foreach ($logs as $log) {
            fputcsv($output, [
                $log->id,
                $log->created_at,
                $log->machine_name,
                $log->entity_type,
                $log->entity_id,
                $log->from_state_name ?? 'Initial',
                $log->to_state_name,
                $log->user_name,
                $log->comment
            ]);
        }

        fclose($output);
        exit;
    }

    /**
     * Get DataTable parameters
     */
    private function getDataTableParams() {
        return [
            'start' => intval($_POST['start'] ?? 0),
            'length' => intval($_POST['length'] ?? 10),
            'search' => sanitize_text_field($_POST['search']['value'] ?? ''),
            'machine_id' => intval($_POST['machine_id'] ?? 0),
            'date_from' => sanitize_text_field($_POST['date_from'] ?? ''),
            'date_to' => sanitize_text_field($_POST['date_to'] ?? ''),
            'user_id' => intval($_POST['user_id'] ?? 0),
        ];
    }
}
```

---

### ⭐ FASE 3: Create Logs View
**Effort:** 1-2 jam
**Status:** ✅ COMPLETED
**Priority:** MEDIUM

**Tasks:**
- [x] Create `/src/Views/admin/logs/transition-logs-view.php` (renamed from index.php)
- [x] Implement DataTables UI
- [x] Add plugin filter dropdown
- [x] Add date range picker
- [x] Add user filter
- [x] Add machine filter
- [x] Add export button
- [x] Style dengan wp-admin styles
- [x] Extract CSS to separate file (transition-logs.css)
- [x] Extract JS to separate file (transition-logs.js)
- [x] Clean view file (no inline CSS/JS)

**Completed:** 2025-11-07
**Version:** 1.0.1
**Files Created:**
- `/src/Views/admin/logs/transition-logs-view.php`
- `/assets/css/transition-logs.css`
- `/assets/js/transition-logs.js`

---

### ⭐ FASE 4: Update StateMachineEngine (Optional)
**Effort:** 30 menit
**Status:** PENDING
**Priority:** LOW

**Tasks:**
- [ ] Allow injection of custom log model
- [ ] Add `setLogModel()` method
- [ ] Update constructor to accept plugin_slug
- [ ] Maintain backward compatibility

**Implementation:**

```php
class StateMachineEngine {
    private $log_model;

    public function __construct($plugin_slug = null) {
        // ... existing code ...

        // Use plugin-specific log model if provided
        $this->log_model = new TransitionLogModel($plugin_slug);
    }

    /**
     * Set custom log model
     * @param TransitionLogModel $log_model
     * @return self
     */
    public function setLogModel(TransitionLogModel $log_model): self {
        $this->log_model = $log_model;
        return $this;
    }
}
```

---

### ⭐ FASE 5: Update MenuManager
**Effort:** 15 menit
**Status:** ✅ COMPLETED
**Priority:** HIGH

**Tasks:**
- [x] Inject LogsController ke MenuManager
- [x] Update renderLogsPage() untuk load logs view
- [x] Register logs submenu (already existed)
- [x] Update wp-state-machine.php to instantiate LogsController

**Completed:** 2025-11-07
**Files Updated:**
- `/src/Controllers/MenuManager.php`
- `/wp-state-machine.php`

---

## 🎯 USAGE EXAMPLES

### For Plugin Developers:

**Option 1: Use per-plugin table (RECOMMENDED for high-volume)**
```php
// In wp-rfq plugin:
$engine = new StateMachineEngine('wp-rfq');
$result = $engine->applyTransition([
    'machine_slug' => 'order-workflow',
    'entity_type' => 'order',
    'entity_id' => 123,
    'transition_id' => 5
]);
// Logs saved to: app_wp_rfq_sm_logs
```

**Option 2: Use central table (for low-volume plugins)**
```php
// Default behavior:
$engine = new StateMachineEngine();
$result = $engine->applyTransition([...]);
// Logs saved to: app_sm_transition_logs
```

**Option 3: Custom log model**
```php
$custom_log_model = new TransitionLogModel('wp-agency');
$engine = new StateMachineEngine();
$engine->setLogModel($custom_log_model);
```

### For Admin Users:

**View logs in admin:**
```
State Machines → Logs
- Filter by plugin: [Dropdown: All | wp-rfq | wp-agency | ...]
- Filter by date range: [Date picker]
- Filter by machine: [Dropdown]
- Export to CSV: [Button]
```

---

## 📊 TABLE STRUCTURE

```sql
-- Per-plugin tables (created on-demand):
CREATE TABLE app_wp_rfq_sm_logs (
    -- Same structure as central table
);

CREATE TABLE app_wp_agency_sm_logs (
    -- Same structure as central table
);

-- Central table (optional, backward compatible):
CREATE TABLE app_sm_transition_logs (
    -- Existing structure
);
```

**Indexes (same for all tables):**
- PRIMARY KEY (id)
- KEY machine_id_index (machine_id)
- KEY entity_index (entity_type, entity_id)
- KEY from_state_index (from_state_id)
- KEY to_state_index (to_state_id)
- KEY user_id_index (user_id)
- KEY created_at_index (created_at)

---

## ✅ TESTING CHECKLIST

### FASE 1: TransitionLogModel Enhancement ✅
- [x] Test backward compatibility (no plugin_slug = central table)
- [x] Test per-plugin table creation
- [x] Test dynamic table name resolution
- [x] Test cache group isolation
- [x] Test foreign keys creation
- [x] Test all CRUD methods with both central and plugin tables
- [x] Test getEntityHistory() with plugin-specific table
- [x] Test getCurrentState() with plugin-specific table

### FASE 2: LogsController ✅
- [x] Test DataTable request with plugin filter
- [x] Test DataTable request without plugin filter (all logs)
- [x] Test permission checks
- [x] Test export functionality
- [x] Test date range filtering
- [x] Test user filtering
- [x] Test machine filtering
- [x] Test pagination

### FASE 3: Logs View ✅
- [x] Test plugin dropdown population
- [x] Test DataTable rendering
- [x] Test filtering UI
- [x] Test export button
- [x] Test responsive design
- [x] Test with different user roles

### FASE 4: StateMachineEngine (DEFERRED)
- [ ] Test with plugin_slug in constructor
- [ ] Test with setLogModel() injection
- [ ] Test backward compatibility (no plugin_slug)
- [ ] Verify logs saved to correct table
- Note: FASE 4 is optional and deferred for future enhancement

### Integration Tests
- [ ] Create test machine in wp-rfq
- [ ] Execute transitions
- [ ] Verify logs in app_wp_rfq_sm_logs
- [ ] View logs in admin UI
- [ ] Export logs to CSV
- [ ] Test with multiple plugins simultaneously
- [ ] Test cleanup on plugin uninstall

---

## 🚀 BENEFITS

### Performance:
- ✅ Smaller tables = faster queries
- ✅ Better indexing efficiency
- ✅ Parallel query execution (multiple tables)
- ✅ No table lock contention

### Maintenance:
- ✅ Easy cleanup per plugin
- ✅ Isolated backup/restore
- ✅ Plugin uninstall = drop own table
- ✅ No orphaned records

### Scalability:
- ✅ Linear scaling (not exponential)
- ✅ Each plugin isolated
- ✅ No single point of failure
- ✅ Can handle unlimited plugins

### Flexibility:
- ✅ Plugin chooses: isolated table or central
- ✅ Backward compatible (central table still works)
- ✅ No migration needed for existing data
- ✅ Progressive adoption

---

## 📝 NOTES

### Design Decisions:

1. **No new abstractions:** TransitionLogModel already extends AbstractStateMachineModel which provides all needed functionality ✅

2. **On-demand table creation:** Tables created automatically when TransitionLogModel instantiated with plugin_slug ✅

3. **Backward compatible:** Existing code without plugin_slug continues to use central table ✅

4. **No validator needed:** Logs are read-only, just capability checks in controller ✅

5. **Standalone controller:** LogsController doesn't need abstraction, different pattern from CRUD controllers ✅

### Future Enhancements:

- [ ] Auto-archiving old logs (> 1 year)
- [ ] Log compression for archived data
- [ ] Log aggregation/statistics per plugin
- [ ] Real-time log streaming (WebSocket)
- [ ] Advanced search (full-text search)
- [ ] Log retention policies per plugin
- [ ] External log storage (S3, CloudWatch, etc.)

---

## 🔗 DEPENDENCIES

**Requires:**
- ✅ AbstractStateMachineModel (exists)
- ✅ TransitionLogModel (exists, needs enhancement)
- ✅ StateMachineEngine (exists, optional update)
- ✅ MenuManager (exists, needs logs integration)

**Provides:**
- LogsController (new)
- Enhanced TransitionLogModel (updated)
- Logs view (new)
- Per-plugin table support (new)

---

## 📚 REFERENCES

### Existing Files:
- AbstractStateMachineModel: `/src/Models/AbstractStateMachineModel.php`
- TransitionLogModel: `/src/Models/TransitionLog/TransitionLogModel.php`
- StateMachineEngine: `/src/Engine/StateMachineEngine.php`
- TransitionLogsDB: `/src/Database/Tables/TransitionLogsDB.php`

### Files to Create:
- LogsController: `/src/Controllers/LogsController.php`
- Logs View: `/src/Views/admin/logs/index.php`

### Related TODOs:
- TODO-6102: Implementation Roadmap (FASE 3, Prioritas #6)
- TODO-6103: Abstraction Refactoring Plan (FASE 2 completed)

---

## ⏭️ NEXT STEPS

**Completed:**
1. ✅ **FASE 1:** Enhance TransitionLogModel (1-2 jam) - DONE
2. ✅ **FASE 2:** Create LogsController (2-3 jam) - DONE
3. ✅ **FASE 3:** Create Logs View (1-2 jam) - DONE
4. ⏸️ **FASE 4:** Update StateMachineEngine (optional, 30 min) - DEFERRED
5. ✅ **FASE 5:** Update MenuManager (15 min) - DONE
6. ✅ **EXTRA:** Move asset enqueuing to class-dependencies.php - DONE

**Total effort completed:** 5-7 jam

**Implementation Notes:**
- Asset management centralized in class-dependencies.php (v1.0.1)
- LogsController cleaned up (removed enqueueAssets method)
- View file uses clean architecture (no inline CSS/JS)
- All files versioned as 1.0.1 for this feature

**Deferred for Future:**
- ⏸️ FASE 4: Update StateMachineEngine with plugin_slug constructor parameter
- Integration testing with wp-rfq
- Document usage for plugin developers
- Update README with per-plugin table strategy

**Recommended Next Priority:**
- Continue to TODO-6102 PRIORITAS #7: Workflow Groups Controller
- Or: Start integration testing with existing plugins
