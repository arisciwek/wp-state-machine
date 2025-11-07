# TODO-6102: Implementation Roadmap
**Created:** 2025-11-07
**Version:** 1.1.0
**Context:** Systematic development plan for wp-state-machine plugin

---

## FASE 1: MANAGEMENT UI (Bikin Data Dulu)

### ⭐ PRIORITAS #1: Integrasi StateMachineController ke MenuManager
**Effort:** 5-10 menit
**Status:** ✅ COMPLETED
**Dependency:** NONE
**Impact:** Menu Machines langsung berfungsi

**Tasks:**
- [x] Update MenuManager->renderMainPage() untuk delegasi ke StateMachineController
- [x] Inject StateMachineController ke MenuManager constructor
- [x] Update plugin main file untuk pass controller
- [x] Test CRUD operations via UI
- [x] Verify DataTable working

**Files:**
- `/src/Controllers/MenuManager.php` (line 153-163)
- `/wp-state-machine.php`

**Result:** Bisa CRUD state machines via UI

---

### ⭐ PRIORITAS #2: StateController
**Effort:** 2-3 jam
**Status:** ✅ COMPLETED
**Dependency:** Butuh machine exists dulu (#1)
**Impact:** Bisa manage states untuk setiap machine

**Tasks:**
- [x] Create StateController.php (clone dari StateMachineController pattern)
- [x] Implement DataTable AJAX handler
- [x] Implement CRUD operations (create, update, delete, show)
- [x] Create StateValidator.php
- [x] Create view file: /src/Views/admin/states/index.php
- [x] Update MenuManager untuk render states page
- [x] Add filtering by machine_id
- [x] Test with sample machine data

**Pattern Reference:**
- Follow StateMachineController pattern exactly
- Use StateModel (already exists)
- Integrate with StateMachineCacheManager
- Permission checking with custom capabilities

**Files to Create:**
- `/src/Controllers/StateController.php`
- `/src/Validators/StateValidator.php`
- `/src/Views/admin/states/index.php`

**Result:** Bisa CRUD states untuk setiap machine

---

### ⭐ PRIORITAS #3: TransitionController
**Effort:** 2-3 jam
**Status:** ✅ COMPLETED
**Dependency:** Butuh states exists dulu (#2)
**Impact:** Bisa manage transitions, setup workflow lengkap

**Tasks:**
- [x] Create TransitionController.php (clone pattern)
- [x] Implement DataTable with from_state & to_state JOIN
- [x] Implement CRUD operations
- [x] Create TransitionValidator.php
- [x] Validate no duplicate transitions (from→to)
- [x] Create view file: /src/Views/admin/transitions/index.php
- [ ] Add sort order drag-drop functionality (optional - future enhancement)
- [x] Test transition creation between states

**Special Features:**
- [ ] Visual transition diagram (future enhancement)
- [ ] Bulk import transitions
- [ ] Guard class selector dropdown

**Pattern Reference:**
- Use TransitionModel->getAvailableTransitions() (already exists)
- Display from_state_name and to_state_name in DataTable
- Support guard_class field
- Support metadata JSON field

**Files to Create:**
- `/src/Controllers/TransitionController.php`
- `/src/Validators/TransitionValidator.php`
- `/src/Views/admin/transitions/index.php`

**Result:** Setup workflow lengkap (machines → states → transitions)

---

## FASE 2: EXECUTION (Run State Machines)

### ⭐ PRIORITAS #4: Guard System
**Effort:** 3-4 jam
**Status:** ✅ COMPLETED
**Dependency:** NONE (standalone)
**Impact:** Permission & business rule checking untuk transitions

**Tasks:**
- [x] Create GuardInterface.php
- [x] Create AbstractGuard.php base class
- [x] Implement default guards:
  - [x] RoleGuard (check user role)
  - [x] CapabilityGuard (check user capability)
  - [x] OwnerGuard (check if user owns entity)
  - [x] CallbackGuard (custom callback function)
- [x] Create GuardFactory.php untuk instantiate guards
- [x] Add guard validation before transition execution
- [x] Document guard creation for plugin developers

**Guard Interface:**
```php
interface GuardInterface {
    public function isAllowed(object $entity, WP_User $user): bool;
    public function getErrorMessage(): string;
}
```

**Files to Create:**
- `/src/Guards/GuardInterface.php`
- `/src/Guards/AbstractGuard.php`
- `/src/Guards/RoleGuard.php`
- `/src/Guards/CapabilityGuard.php`
- `/src/Guards/OwnerGuard.php`
- `/src/Guards/CallbackGuard.php`
- `/src/Guards/GuardFactory.php`

**Example Usage:**
```php
// In transition definition
'guard_class' => 'WPStateMachine\\Guards\\RoleGuard',
'metadata' => ['required_roles' => ['editor', 'administrator']]
```

**Result:** Foundation untuk secure transition execution

---

### ⭐ PRIORITAS #5: StateMachineEngine
**Effort:** 4-5 jam
**Status:** ✅ COMPLETED
**Dependency:** Butuh Guard System (#4)
**Impact:** CORE - Execute transitions dengan validation

**Tasks:**
- [x] Create StateMachineEngine.php
- [x] Implement applyTransition() method
- [x] Validate current state before transition
- [x] Check guard permissions
- [x] Execute transition
- [x] Log to app_sm_transition_logs table
- [x] Fire WordPress hooks before/after transition
- [x] Handle transition errors gracefully
- [x] Create TransitionLogModel
- [x] Add rollback mechanism (via logging)

**Core Methods:**
```php
class StateMachineEngine {
    public function canTransition($entity, $transition_id): bool;
    public function applyTransition($entity, $transition_id): bool;
    public function getAvailableTransitions($entity): array;
    public function getCurrentState($entity): object;
}
```

**Hooks to Fire:**
```php
// Before transition
do_action('wp_state_machine_before_transition', $entity, $from_state, $to_state, $transition);

// After successful transition
do_action('wp_state_machine_after_transition', $entity, $from_state, $to_state, $transition);

// On transition failure
do_action('wp_state_machine_transition_failed', $entity, $transition, $error);
```

**Files to Create:**
- `/src/Engine/StateMachineEngine.php`
- `/src/Models/TransitionLog/TransitionLogModel.php` (if needed)

**Result:** State machine bisa dijalankan dan log semua transitions!

---

## FASE 3: EXTRAS (Nice to Have)

### ⭐ PRIORITAS #6: Logs Viewer
**Effort:** 2-3 jam
**Status:** ✅ COMPLETED
**Dependency:** Butuh TransitionLogModel exists (#5)
**Impact:** View dan audit transition history

**Tasks:**
- [x] Create LogsController.php (standalone controller)
- [x] Display transition logs dengan filtering (plugin, machine, date range)
- [x] Show: machine, from_state, to_state, user, timestamp, comment
- [x] Add search & date range filter
- [x] Update MenuManager->renderLogsPage()
- [x] Create transition-logs-view.php (clean view, no inline CSS/JS)
- [x] Extract CSS to /assets/css/transition-logs.css
- [x] Extract JS to /assets/js/transition-logs.js
- [x] Implement CSV export functionality
- [x] Add plugin filtering support (per-plugin tables)
- [x] Move asset enqueuing to class-dependencies.php

**Special Features:**
- DataTables server-side processing
- Multi-plugin support (queries per-plugin tables + central table)
- Export to CSV with filters applied
- Permission checks (view_state_machine_logs)
- Real-time filter status display

**Files Created:**
- `/src/Controllers/LogsController.php` (v1.0.1)
- `/src/Views/admin/logs/transition-logs-view.php` (v1.0.1)
- `/assets/css/transition-logs.css`
- `/assets/js/transition-logs.js`

**Files Updated:**
- `/includes/class-dependencies.php` (v1.0.1) - Added logs assets enqueuing
- `/src/Controllers/MenuManager.php` - Added LogsController injection
- `/wp-state-machine.php` - Instantiate LogsController

**Result:** Complete logs viewer dengan filtering, search, dan export

---

### ⭐ PRIORITAS #7: Workflow Groups Controller
**Effort:** 2-3 jam
**Status:** ✅ COMPLETED
**Dependency:** FASE 1 & 2 Complete
**Impact:** Organize state machines into logical groups

**Tasks:**
- [x] Create WorkflowGroupModel.php extending AbstractStateMachineModel
- [x] Create WorkflowGroupValidator.php extending AbstractStateMachineValidator
- [x] Create WorkflowGroupController.php (CRUD operations)
- [x] Create workflow-groups-view.php (clean view, no inline CSS/JS)
- [x] Extract CSS to /assets/css/workflow-groups.css
- [x] Extract JS to /assets/js/workflow-groups.js
- [x] Update MenuManager->renderGroupsPage()
- [x] Update wp-state-machine.php to instantiate controller
- [x] Move asset enqueuing to class-dependencies.php
- [x] Add sort order management
- [x] Add machine count tracking

**Special Features:**
- DataTables server-side processing
- Add/Edit/Delete groups via modals
- View group details with assigned machines
- Active/Inactive status toggle
- Dashicon selector for group icons
- Sort order management (ready for drag-drop)
- Machine count badges
- Comprehensive validation
- Permission checks (workflow_groups capabilities)
- Auto-slug generation from name

**Files Created:**
- `/src/Models/WorkflowGroup/WorkflowGroupModel.php` (v1.0.0)
- `/src/Validators/WorkflowGroupValidator.php` (v1.0.0)
- `/src/Controllers/WorkflowGroupController.php` (v1.0.0)
- `/src/Views/admin/workflow-groups/workflow-groups-view.php` (v1.0.0)
- `/assets/css/workflow-groups.css` (v1.0.0)
- `/assets/js/workflow-groups.js` (v1.0.0)
- `/examples/blog-post-workflow.yml` (sampler - educational)
- `/examples/support-ticket-workflow.yml` (sampler - comprehensive)
- `/examples/order-state-machine.yml` (sampler - e-commerce)

**Files Updated:**
- `/includes/class-dependencies.php` (v1.0.2) - Added workflow groups assets
- `/src/Controllers/MenuManager.php` - Added WorkflowGroupController injection
- `/wp-state-machine.php` - Instantiate WorkflowGroupController

**Architecture Notes:**
- NO new abstractions needed (existing abstractions sufficient)
- Model extends AbstractStateMachineModel
- Validator extends AbstractStateMachineValidator
- Controller follows StateMachineController pattern
- Centralized feature (one controller for all plugins)

**Result:** Complete workflow groups management dengan CRUD, filtering, dan machine tracking

---

### PRIORITAS #8: Settings Controller
**Effort:** 2-3 jam
**Status:** PENDING

**Tasks:**
- [ ] Create SettingsController.php
- [ ] General settings tab
- [ ] Permission management tab
- [ ] Cache settings tab
- [ ] Database cleanup options
- [ ] Update MenuManager->renderSettingsPage()

---

## DEPENDENCY TREE

```
FASE 1 (Sequential):
#1 MenuManager Integration (START HERE)
  ↓
#2 StateController (needs machines)
  ↓
#3 TransitionController (needs states)

FASE 2 (Sequential):
#4 Guard System (standalone)
  ↓
#5 StateMachineEngine (needs guards)

FASE 3 (Parallel):
#6 Logs Viewer
#7 Workflow Groups
#8 Settings
```

---

## TESTING CHECKLIST

### FASE 1 Testing (Management UI):
- [x] Create sample data via UI
- [x] Test CRUD operations for Machines
- [x] Test CRUD operations for States
- [x] Test CRUD operations for Transitions
- [x] Verify cache invalidation works
- [x] Check permission enforcement
- [x] Verify DataTable server-side processing
- [x] Test machine filtering in States view
- [x] Test machine filtering in Transitions view
- [x] Test cascading state dropdowns in Transitions
- [x] Verify duplicate transition prevention
- [ ] Test with wp-rfq example integration (deferred to FASE 2)
- [ ] Update documentation (deferred until abstraction complete)

### Next Phase Testing (After Abstraction):
- [ ] Verify refactored validators work correctly
- [ ] Ensure no regression after abstraction
- [ ] Test permission checks still function
- [ ] Validate backward compatibility

---

## CURRENT STATUS

**Completed:**
- ✅ AbstractStateMachineModel
- ✅ StateMachineModel
- ✅ StateModel
- ✅ TransitionModel
- ✅ StateMachineController (AJAX + UI)
- ✅ StateMachineValidator
- ✅ StateController (AJAX + UI)
- ✅ StateValidator
- ✅ TransitionController (AJAX + UI)
- ✅ TransitionValidator
- ✅ Database tables & foreign keys
- ✅ Seeder & DefaultStateMachines
- ✅ Cache system
- ✅ MenuManager structure
- ✅ PRIORITAS #1: MenuManager Integration
- ✅ PRIORITAS #2: StateController Implementation
- ✅ PRIORITAS #3: TransitionController Implementation
- ✅ **FASE 1 COMPLETE: Management UI (Bikin Data Dulu)**
- ✅ AbstractStateMachineValidator (TODO-6103)
- ✅ PRIORITAS #4: Guard System (GuardInterface, AbstractGuard, 4 default guards, GuardFactory)
- ✅ PRIORITAS #5: StateMachineEngine (Core execution engine with guards, logging, hooks)
- ✅ TransitionLogModel (Audit trail and history tracking)
- ✅ **FASE 2 COMPLETE: Execution (Run State Machines)**
- ✅ PRIORITAS #6: Logs Viewer (LogsController with DataTables, filtering, export)
- ✅ **TODO-6104:** Per-Plugin Log Tables Implementation
- ✅ PRIORITAS #7: Workflow Groups Controller (Complete groups management)
- ✅ **FASE 3 EXTRAS:** Partially Complete (2 of 3 done)

**Bonus Deliverables:**
- ✅ 3x YML Workflow Samplers (blog-post, support-ticket, order)
- ✅ Clean MVC architecture dengan separated assets
- ✅ Centralized asset management (class-dependencies.php)

**In Progress:**
- None

**Next Step:**
- **RECOMMENDED:** Continue to PRIORITAS #8: Settings Controller
  - Create SettingsController for plugin configuration
  - Build admin UI for settings management
  - General settings, permissions, cache options
- **ALTERNATIVE:** Start integration testing with existing features
- **ALTERNATIVE:** Create plugin integration examples
- **ALTERNATIVE:** Begin comprehensive documentation

---

## NOTES

- Pattern sudah established (AbstractStateMachineModel)
- Available transitions method sudah ada di TransitionModel
- Guard class field sudah ada di database
- Semua model sudah support caching
- Follow wp-agency pattern untuk consistency

---

## REFERENCES

### Documentation:
- Main README: `/README.md`
- Example Integration: `/examples/wp-rfq-integration-example.php`

### Implemented Controllers:
- StateMachineController: `/src/Controllers/StateMachineController.php`
- StateController: `/src/Controllers/StateController.php`
- TransitionController: `/src/Controllers/TransitionController.php`
- LogsController: `/src/Controllers/LogsController.php` (v1.0.1)

### Implemented Validators:
- StateMachineValidator: `/src/Validators/StateMachineValidator.php`
- StateValidator: `/src/Validators/StateValidator.php`
- TransitionValidator: `/src/Validators/TransitionValidator.php`

### Models:
- TransitionModel: `/src/Models/Transition/TransitionModel.php:270` (getAvailableTransitions)
- StateModel: `/src/Models/State/StateModel.php`
- StateMachineModel: `/src/Models/StateMachine/StateMachineModel.php`
- TransitionLogModel: `/src/Models/TransitionLog/TransitionLogModel.php`

### Views:
- Machines: `/src/Views/admin/state-machines/index.php`
- States: `/src/Views/admin/states/index.php`
- Transitions: `/src/Views/admin/transitions/index.php`
- Logs: `/src/Views/admin/logs/transition-logs-view.php` (v1.0.1)

### Engine:
- StateMachineEngine: `/src/Engine/StateMachineEngine.php`

### Guards:
- GuardInterface: `/src/Guards/GuardInterface.php`
- AbstractGuard: `/src/Guards/AbstractGuard.php`
- RoleGuard: `/src/Guards/RoleGuard.php`
- CapabilityGuard: `/src/Guards/CapabilityGuard.php`
- OwnerGuard: `/src/Guards/OwnerGuard.php`
- CallbackGuard: `/src/Guards/CallbackGuard.php`
- GuardFactory: `/src/Guards/GuardFactory.php`

### Completed Phases:
- **✅ FASE 1:** Management UI (Machines, States, Transitions CRUD)
- **✅ FASE 2:** Execution Engine (Guards, Engine, Logging)
- **✅ TODO-6103:** Abstraction & Refactoring (AbstractStateMachineValidator)
