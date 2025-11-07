# TODO-6102: Implementation Roadmap
**Created:** 2025-11-07
**Version:** 1.1.0
**Context:** Systematic development plan for wp-state-machine plugin

---

## FASE 1: MANAGEMENT UI (Bikin Data Dulu)

### ⭐ PRIORITAS #1: Integrasi StateMachineController ke MenuManager
**Effort:** 5-10 menit
**Status:** PENDING
**Dependency:** NONE
**Impact:** Menu Machines langsung berfungsi

**Tasks:**
- [ ] Update MenuManager->renderMainPage() untuk delegasi ke StateMachineController
- [ ] Inject StateMachineController ke MenuManager constructor
- [ ] Update plugin main file untuk pass controller
- [ ] Test CRUD operations via UI
- [ ] Verify DataTable working

**Files:**
- `/src/Controllers/MenuManager.php` (line 153-163)
- `/wp-state-machine.php`

**Result:** Bisa CRUD state machines via UI

---

### ⭐ PRIORITAS #2: StateController
**Effort:** 2-3 jam
**Status:** PENDING
**Dependency:** Butuh machine exists dulu (#1)
**Impact:** Bisa manage states untuk setiap machine

**Tasks:**
- [ ] Create StateController.php (clone dari StateMachineController pattern)
- [ ] Implement DataTable AJAX handler
- [ ] Implement CRUD operations (create, update, delete, show)
- [ ] Create StateValidator.php
- [ ] Create view file: /src/Views/admin/states/index.php
- [ ] Update MenuManager untuk render states page
- [ ] Add filtering by machine_id
- [ ] Test with sample machine data

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
**Status:** PENDING
**Dependency:** Butuh states exists dulu (#2)
**Impact:** Bisa manage transitions, setup workflow lengkap

**Tasks:**
- [ ] Create TransitionController.php (clone pattern)
- [ ] Implement DataTable with from_state & to_state JOIN
- [ ] Implement CRUD operations
- [ ] Create TransitionValidator.php
- [ ] Validate no duplicate transitions (from→to)
- [ ] Create view file: /src/Views/admin/transitions/index.php
- [ ] Add sort order drag-drop functionality (optional)
- [ ] Test transition creation between states

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
**Status:** PENDING
**Dependency:** NONE (standalone)
**Impact:** Permission & business rule checking untuk transitions

**Tasks:**
- [ ] Create GuardInterface.php
- [ ] Create AbstractGuard.php base class
- [ ] Implement default guards:
  - [ ] RoleGuard (check user role)
  - [ ] CapabilityGuard (check user capability)
  - [ ] OwnerGuard (check if user owns entity)
  - [ ] CallbackGuard (custom callback function)
- [ ] Create GuardFactory.php untuk instantiate guards
- [ ] Add guard validation before transition execution
- [ ] Document guard creation for plugin developers

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
**Status:** PENDING
**Dependency:** Butuh Guard System (#4)
**Impact:** CORE - Execute transitions dengan validation

**Tasks:**
- [ ] Create StateMachineEngine.php
- [ ] Implement applyTransition() method
- [ ] Validate current state before transition
- [ ] Check guard permissions
- [ ] Execute transition
- [ ] Log to app_sm_transition_logs table
- [ ] Fire WordPress hooks before/after transition
- [ ] Handle transition errors gracefully
- [ ] Create TransitionLogModel (if not exists)
- [ ] Add rollback mechanism (optional)

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

### PRIORITAS #6: Logs Viewer
**Effort:** 2-3 jam
**Status:** PENDING

**Tasks:**
- [ ] Create LogsController.php
- [ ] Display transition logs dengan filtering
- [ ] Show: machine, from_state, to_state, user, timestamp
- [ ] Add search & date range filter
- [ ] Update MenuManager->renderLogsPage()

---

### PRIORITAS #7: Workflow Groups Controller
**Effort:** 2-3 jam
**Status:** PENDING

**Tasks:**
- [ ] Create WorkflowGroupController.php
- [ ] CRUD operations untuk groups
- [ ] Assign machines to groups
- [ ] Update MenuManager->renderGroupsPage()

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

### After Each Phase:
- [ ] Create sample data
- [ ] Test CRUD operations
- [ ] Verify cache invalidation
- [ ] Check permission enforcement
- [ ] Test with wp-rfq example integration
- [ ] Update documentation

---

## CURRENT STATUS

**Completed:**
- ✅ AbstractStateMachineModel
- ✅ StateMachineModel
- ✅ StateModel
- ✅ TransitionModel
- ✅ StateMachineController (AJAX only)
- ✅ Database tables & foreign keys
- ✅ Seeder & DefaultStateMachines
- ✅ Cache system
- ✅ MenuManager structure

**In Progress:**
- None

**Next Step:**
- Start with PRIORITAS #1: Integrasi StateMachineController ke MenuManager

---

## NOTES

- Pattern sudah established (AbstractStateMachineModel)
- Available transitions method sudah ada di TransitionModel
- Guard class field sudah ada di database
- Semua model sudah support caching
- Follow wp-agency pattern untuk consistency

---

## REFERENCES

- Main README: `/README.md`
- Example Integration: `/examples/wp-rfq-integration-example.php`
- TransitionModel: `/src/Models/Transition/TransitionModel.php:270` (getAvailableTransitions)
- StateMachineController: `/src/Controllers/StateMachineController.php`
