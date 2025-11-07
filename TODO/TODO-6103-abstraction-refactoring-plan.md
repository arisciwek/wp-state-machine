# TODO-6103: Abstraction & Refactoring Plan
**Created:** 2025-11-07
**Version:** 1.0.0
**Context:** Code reusability optimization for wp-state-machine as foundation library
**Dependencies:** Requires completion of Prioritas #3 (TransitionController)

---

## 🎯 OBJECTIVE

Create reusable abstractions to eliminate code duplication across 18+ plugins that will use wp-state-machine. Focus on high-ROI patterns with proven consistency.

**Impact Calculation:**
- 18 plugins × 3 controllers (minimum) = 54 validators
- ~70 lines of permission logic per validator
- **Potential savings: 3,780+ lines of duplicate code**

---

## 📋 PREREQUISITES

### ✅ Already Completed:
- StateMachineController + StateMachineValidator (Prioritas #1)
- StateController + StateValidator (Prioritas #2)

### 🔄 Must Complete First:
- **Prioritas #3: TransitionController + TransitionValidator**
  - Need 3 complete samples to identify solid patterns
  - Prevents premature abstraction (wait for pattern clarity)

---

## FASE 1: COMPLETE SAMPLE SET

### ⭐ STEP 1: TransitionController Implementation
**Status:** PENDING
**Effort:** 2-3 jam
**Dependency:** Prioritas #2 completed ✅

**Tasks:**
- [ ] Create TransitionController.php (follow StateController pattern)
- [ ] Create TransitionValidator.php (follow StateValidator pattern)
- [ ] Create view file: transitions/index.php
- [ ] Test CRUD operations

**Why:** Need 3 concrete examples to extract reliable patterns

**Files to Create:**
- `/src/Controllers/TransitionController.php`
- `/src/Validators/TransitionValidator.php`
- `/src/Views/admin/transitions/index.php`

---

## FASE 2: VALIDATOR ABSTRACTION (HIGH PRIORITY)

### ⭐ STEP 2: Create AbstractStateMachineValidator
**Status:** ✅ COMPLETED
**Effort:** 3-4 jam (actual: 2 jam)
**Dependency:** Step 1 completed
**ROI:** VERY HIGH ✅✅✅

**Why This First:**
1. ✅ **Proven pattern** - 100% identical across all validators
2. ✅ **High impact** - 70 lines × 54 validators = 3,780 lines saved
3. ✅ **Low risk** - no business logic variation
4. ✅ **Foundation for others** - other plugins will copy this pattern

**Pattern Analysis:**
```php
// IDENTICAL in StateMachineValidator, StateValidator, TransitionValidator:
✅ validatePermission(int $id, string $operation): array  ~25 lines
✅ getUserRelation(int $id): array                        ~30 lines
✅ canView(int $id): bool                                 ~3 lines
✅ canUpdate(int $id): bool                               ~3 lines
✅ canDelete(int $id): bool                               ~3 lines
✅ validateBulkOperation(array $ids, string $op): array   ~8 lines
---
Total: ~70 lines of PURE DUPLICATION
```

**Implementation Plan:**

#### A. Create AbstractStateMachineValidator.php
**Location:** `/src/Validators/AbstractStateMachineValidator.php`

**Structure:**
```php
<?php
namespace WPStateMachine\Validators;

abstract class AbstractStateMachineValidator {
    protected $model;

    public function __construct() {
        $this->model = $this->getModel();
    }

    // ===================================
    // ABSTRACT METHODS (Must implement)
    // ===================================

    /**
     * Get model instance for this validator
     * @return object Model instance
     */
    abstract protected function getModel();

    /**
     * Get capability prefix for permission checks
     * @return string Capability prefix (e.g., 'state_machines', 'transitions')
     */
    abstract protected function getCapabilityPrefix(): string;

    /**
     * Validate form data (business-specific)
     * @param array $data Form data to validate
     * @param int|null $id Entity ID (for updates)
     * @return array Validation errors
     */
    abstract public function validateForm(array $data, ?int $id = null): array;

    // ===================================
    // CONCRETE METHODS (Inherited FREE)
    // ===================================

    /**
     * Validate user permission for operation
     * @param int $id Entity ID
     * @param string $operation Operation type (view, update, delete)
     * @return array ['allowed' => bool, 'message' => string, 'relation' => array]
     */
    public function validatePermission(int $id, string $operation = 'view'): array {
        $relation = $this->getUserRelation($id);

        switch ($operation) {
            case 'view':
                $allowed = $relation['can_view'];
                $message = $allowed ? '' : __('You do not have permission to view this item', 'wp-state-machine');
                break;

            case 'update':
                $allowed = $relation['can_update'];
                $message = $allowed ? '' : __('You do not have permission to update this item', 'wp-state-machine');
                break;

            case 'delete':
                $allowed = $relation['can_delete'];
                $message = $allowed ? '' : __('You do not have permission to delete this item', 'wp-state-machine');
                break;

            default:
                $allowed = false;
                $message = __('Invalid operation', 'wp-state-machine');
                break;
        }

        return [
            'allowed' => $allowed,
            'message' => $message,
            'relation' => $relation
        ];
    }

    /**
     * Get user's relation to entity
     * @param int $id Entity ID
     * @return array User relation data
     */
    public function getUserRelation(int $id): array {
        $entity = $this->model->find($id);
        if (!$entity) {
            return [
                'exists' => false,
                'is_admin' => false,
                'can_view' => false,
                'can_update' => false,
                'can_delete' => false,
                'access_type' => 'none'
            ];
        }

        $capability_prefix = $this->getCapabilityPrefix();

        $is_admin = current_user_can('manage_' . $capability_prefix);
        $can_view = current_user_can('view_' . $capability_prefix);
        $can_edit = current_user_can('edit_' . $capability_prefix);
        $can_delete = current_user_can('delete_' . $capability_prefix);

        $access_type = 'none';
        if ($is_admin) {
            $access_type = 'admin';
        } elseif ($can_edit) {
            $access_type = 'editor';
        } elseif ($can_view) {
            $access_type = 'viewer';
        }

        return [
            'exists' => true,
            'is_admin' => $is_admin,
            'can_view' => $can_view || $is_admin,
            'can_update' => $can_edit || $is_admin,
            'can_delete' => $can_delete || $is_admin,
            'access_type' => $access_type,
            'entity' => $entity
        ];
    }

    /**
     * Check if user can view entity
     */
    public function canView(int $id): bool {
        $relation = $this->getUserRelation($id);
        return $relation['can_view'];
    }

    /**
     * Check if user can update entity
     */
    public function canUpdate(int $id): bool {
        $relation = $this->getUserRelation($id);
        return $relation['can_update'];
    }

    /**
     * Check if user can delete entity
     */
    public function canDelete(int $id): bool {
        $relation = $this->getUserRelation($id);
        return $relation['can_delete'];
    }

    /**
     * Validate bulk operation
     */
    public function validateBulkOperation(array $ids, string $operation): array {
        $results = [];

        foreach ($ids as $id) {
            $validation = $this->validatePermission($id, $operation);
            $results[$id] = $validation;
        }

        return $results;
    }
}
```

#### B. Refactor Existing Validators
**Effort:** 1 jam (straightforward search & replace)

**StateMachineValidator.php:**
```php
class StateMachineValidator extends AbstractStateMachineValidator {
    private $machine_model; // Keep for specific methods

    protected function getModel() {
        return new StateMachineModel();
    }

    protected function getCapabilityPrefix(): string {
        return 'state_machines';
    }

    public function validateForm(array $data, ?int $id = null): array {
        // Keep existing validateForm logic
        // DELETE: validatePermission, getUserRelation, canView, canUpdate, canDelete
        // (inherited from parent)
    }

    // Keep domain-specific methods:
    // - validateMachineStructure()
    // - etc.
}
```

**StateValidator.php:**
```php
class StateValidator extends AbstractStateMachineValidator {
    private $machine_model; // Keep for machine verification

    protected function getModel() {
        return new StateModel();
    }

    protected function getCapabilityPrefix(): string {
        return 'state_machines'; // States use same capability as machines
    }

    public function validateForm(array $data, ?int $id = null): array {
        // Keep existing validateForm logic
    }

    // Keep domain-specific methods:
    // - canDeleteState() - checks transition usage
}
```

**TransitionValidator.php:**
```php
class TransitionValidator extends AbstractStateMachineValidator {
    protected function getModel() {
        return new TransitionModel();
    }

    protected function getCapabilityPrefix(): string {
        return 'state_machines';
    }

    public function validateForm(array $data, ?int $id = null): array {
        // Transition-specific validation
    }
}
```

#### C. Testing Checklist
- [x] Test permission validation in all 3 validators
- [x] Verify capability checks work correctly
- [x] Test bulk operations
- [x] Ensure backward compatibility
- [x] Update unit tests if any

**Result:**
- ✅ Deleted ~210 lines of duplicate code (70 × 3)
- ✅ Future validators: Just extend + implement 3 methods
- ✅ Consistent permission behavior across all entities
- ✅ All syntax checks passed
- ✅ No regression - controllers load successfully

---

## FASE 3: CONTROLLER HELPERS (OPTIONAL)

### ⭐ STEP 3: Create Controller Traits (Lower Priority)
**Status:** PENDING
**Effort:** 2-3 jam
**Dependency:** Step 2 completed
**ROI:** MEDIUM ⚠️

**Why Traits > Abstract:**
1. ⚠️ Controller business logic varies significantly
2. ⚠️ Abstract would be too rigid
3. ✅ Traits = flexible, opt-in, composable
4. ✅ Controllers can pick what they need

**Proposed Traits:**

#### A. AjaxSecurityTrait.php
**Location:** `/src/Traits/AjaxSecurityTrait.php`

```php
trait AjaxSecurityTrait {
    /**
     * Verify AJAX request with nonce
     */
    protected function verifyAjaxRequest(string $nonce_action = 'wp_state_machine_nonce'): void {
        check_ajax_referer($nonce_action, 'nonce');
    }

    /**
     * Check user capability
     */
    protected function requireCapability(string $capability): void {
        if (!current_user_can($capability)) {
            wp_send_json_error([
                'message' => __('Permission denied', 'wp-state-machine')
            ]);
        }
    }
}
```

#### B. JsonResponseTrait.php
**Location:** `/src/Traits/JsonResponseTrait.php`

```php
trait JsonResponseTrait {
    /**
     * Send success response
     */
    protected function sendSuccess($data, string $message = ''): void {
        $response = ['data' => $data];
        if ($message) {
            $response['message'] = $message;
        }
        wp_send_json_success($response);
    }

    /**
     * Send error response
     */
    protected function sendError(string $message, array $errors = []): void {
        $response = ['message' => $message];
        if (!empty($errors)) {
            $response['errors'] = $errors;
        }
        wp_send_json_error($response);
    }

    /**
     * Send validation error
     */
    protected function sendValidationError(array $errors): void {
        $this->sendError(
            __('Validation failed', 'wp-state-machine'),
            $errors
        );
    }
}
```

#### C. CacheInvalidationTrait.php
**Location:** `/src/Traits/CacheInvalidationTrait.php`

```php
trait CacheInvalidationTrait {
    /**
     * Invalidate multiple cache keys
     */
    protected function invalidateCaches(array $cache_keys): void {
        foreach ($cache_keys as $key => $value) {
            if (is_numeric($key)) {
                // Simple key: 'states_list'
                $this->cache->delete($value);
            } else {
                // Key with ID: 'state' => 123
                $this->cache->delete($key, $value);
            }
        }
    }
}
```

#### D. Usage Example
```php
class StateController {
    use AjaxSecurityTrait;
    use JsonResponseTrait;
    use CacheInvalidationTrait;

    public function store() {
        $this->verifyAjaxRequest();
        $this->requireCapability('manage_state_machines');

        $errors = $this->validator->validateForm($data);
        if (!empty($errors)) {
            $this->sendValidationError($errors);
        }

        $id = $this->model->create($data);

        if ($id) {
            $this->invalidateCaches([
                'states_list',
                'state' => $id,
                'states_by_machine' => $data['machine_id']
            ]);

            $this->sendSuccess(
                ['id' => $id],
                __('State created successfully', 'wp-state-machine')
            );
        }
    }
}
```

**Benefits:**
- Reduce boilerplate ~15-20 lines per method
- Consistent error handling
- Easy to adopt (opt-in)
- No breaking changes

---

## FASE 4: DOCUMENTATION

### ⭐ STEP 4: Update Documentation
**Status:** PENDING
**Effort:** 1-2 jam

**Tasks:**
- [ ] Document AbstractStateMachineValidator usage
- [ ] Create example validator for plugin developers
- [ ] Document available traits
- [ ] Update README with best practices
- [ ] Add code examples to integration guide

**Files to Update:**
- `/README.md`
- `/docs/creating-validators.md` (new)
- `/docs/using-traits.md` (new)
- `/examples/custom-validator-example.php` (new)

---

## 📊 IMPACT ANALYSIS

### Before Refactoring:
```
StateMachineValidator.php    320 lines (70 duplicate)
StateValidator.php           365 lines (70 duplicate)
TransitionValidator.php      ~350 lines (70 duplicate)
---
Total: 1,035 lines (210 duplicate = 20.3%)
```

### After Refactoring:
```
AbstractStateMachineValidator.php   150 lines (reusable)
StateMachineValidator.php           250 lines (unique logic)
StateValidator.php                  295 lines (unique logic)
TransitionValidator.php             280 lines (unique logic)
---
Total: 975 lines (60 lines saved now)
Future: Every new validator saves 70 lines
```

### Long-term Impact (18 plugins):
```
Conservative estimate: 3 validators per plugin average
18 plugins × 3 validators = 54 validators

Without abstraction: 54 × 70 = 3,780 lines duplicate
With abstraction: 54 × 0 = 0 lines duplicate

Saved: 3,780 lines + easier maintenance
```

---

## 🎯 EXECUTION ORDER

### Phase 1: Complete Sample (Week 1)
1. ✅ StateMachineController + Validator (Done)
2. ✅ StateController + Validator (Done)
3. 🔄 TransitionController + Validator (Next)

### Phase 2: Abstraction (Week 2)
4. Create AbstractStateMachineValidator
5. Refactor existing validators
6. Test & validate

### Phase 3: Optional Enhancement (Week 3)
7. Create controller traits
8. Optional adoption in existing controllers

### Phase 4: Documentation (Week 3)
9. Write developer guides
10. Update examples

---

## ✅ SUCCESS CRITERIA

- [ ] AbstractStateMachineValidator created and tested
- [ ] All 3 validators successfully extend abstract class
- [ ] No regression in functionality
- [ ] Permission checks work identically
- [ ] Code reduction achieved (min 200 lines)
- [ ] Documentation complete
- [ ] Example validator created for other plugins

---

## 🚨 RISKS & MITIGATION

### Risk 1: Breaking Existing Functionality
**Mitigation:**
- Thorough testing after refactoring
- Keep git branches for easy rollback
- Test all CRUD operations

### Risk 2: Over-abstraction
**Mitigation:**
- Only abstract proven patterns (3+ examples)
- Keep business logic in child classes
- Use traits for optional features

### Risk 3: Future Plugin Needs Different Pattern
**Mitigation:**
- Abstract class allows method overrides
- Capability prefix is configurable
- Validators can add custom methods

---

## 📝 NOTES

### Why Not Abstract Controller?
- Business logic varies too much
- DataTable columns differ per entity
- Filtering requirements differ
- Traits provide better flexibility

### Why Validator First?
- Highest ROI (most duplication)
- Lowest risk (pure logic, no UI)
- Foundation pattern for other plugins
- Clear, proven pattern

### Future Considerations
- Consider AbstractDataTableController if pattern emerges
- May create AbstractFormValidator if form patterns stabilize
- Monitor plugin development for new abstraction opportunities

---

## REFERENCES

- Current Implementation: `/src/Validators/StateMachineValidator.php`
- Current Implementation: `/src/Validators/StateValidator.php`
- Pattern Analysis: This document section "Pattern Analysis"
- AbstractStateMachineModel: `/src/Models/AbstractStateMachineModel.php` (proven success)

---

## ✅ EXECUTION SUMMARY

**Date Completed:** 2025-11-07
**Status:** FASE 2 COMPLETED ✅

### What Was Implemented:

**Created:**
1. **AbstractStateMachineValidator.php** (270 lines)
   - Base class for all validators
   - Shared permission logic
   - 6 concrete methods (free inheritance)
   - 3 abstract methods (must implement)

**Refactored:**
2. **StateMachineValidator.php** - Reduced from 321 to 215 lines (-106 lines)
3. **StateValidator.php** - Reduced from 365 to 247 lines (-118 lines)
4. **TransitionValidator.php** - Reduced from 357 to 237 lines (-120 lines)

### Impact Analysis:

**Immediate Savings:**
- Lines deleted: 344 lines (106 + 118 + 120)
- Lines added (abstract): 270 lines
- **Net reduction: 74 lines**
- Duplicate code eliminated: ~210 lines

**Long-term Impact (18 plugins × 3 validators = 54):**
- Without abstraction: 54 × 70 = 3,780 lines duplicate
- With abstraction: 54 × 0 = 0 lines duplicate
- **Total saved: 3,780 lines**

### Verification:

✅ All syntax checks passed
✅ No PHP errors
✅ Controllers load successfully
✅ Backward compatible
✅ Permission logic identical

### Next Steps:

**FASE 3 (Optional):** Controller Traits
- Can be deferred to future
- Lower priority than Guard System
- ROI: Medium

**OR Continue to FASE 2 of TODO-6102:**
- Prioritas #4: Guard System
- Prioritas #5: StateMachineEngine

---

**Back to:** [TODO-6102 Implementation Roadmap](TODO-6102-implementation-roadmap.md)
