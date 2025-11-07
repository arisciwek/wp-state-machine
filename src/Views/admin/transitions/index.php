<?php
/**
 * Transitions Admin View
 *
 * @package     WP_State_Machine
 * @subpackage  Views/Admin/Transitions
 * @version     1.0.0
 * @author      arisciwek
 *
 * Path: /wp-state-machine/src/Views/admin/transitions/index.php
 *
 * Description: Admin interface for managing state machine transitions.
 *              Includes DataTable listing, CRUD modals, and machine filtering.
 *              Follows wp-agency admin view pattern.
 *
 * Changelog:
 * 1.0.0 - 2025-11-07
 * - Initial creation
 * - DataTable integration with state name JOINs
 * - CRUD modal forms
 * - Machine filter dropdown
 * - Cascading state dropdowns
 */

defined('ABSPATH') || exit;

// Get all machines for the filter dropdown
global $wpdb;
$machine_table = $wpdb->prefix . 'app_sm_machines';
$machines = $wpdb->get_results("SELECT id, name FROM {$machine_table} WHERE is_active = 1 ORDER BY name ASC");
?>

<div class="wrap wp-state-machine-admin">
    <h1 class="wp-heading-inline"><?php _e('Transitions', 'wp-state-machine'); ?></h1>
    <button type="button" class="page-title-action" id="btn-add-transition">
        <?php _e('Add New Transition', 'wp-state-machine'); ?>
    </button>
    <hr class="wp-header-end">

    <!-- Machine Filter -->
    <div class="tablenav top">
        <div class="alignleft actions">
            <label for="filter-machine" class="screen-reader-text">
                <?php _e('Filter by machine', 'wp-state-machine'); ?>
            </label>
            <select name="machine_id" id="filter-machine">
                <option value=""><?php _e('All Machines', 'wp-state-machine'); ?></option>
                <?php foreach ($machines as $machine): ?>
                    <option value="<?php echo esc_attr($machine->id); ?>">
                        <?php echo esc_html($machine->name); ?>
                    </option>
                <?php endforeach; ?>
            </select>
            <button type="button" class="button" id="btn-filter">
                <?php _e('Filter', 'wp-state-machine'); ?>
            </button>
        </div>
    </div>

    <!-- DataTable -->
    <table id="transitions-table" class="wp-list-table widefat fixed striped">
        <thead>
            <tr>
                <th><?php _e('ID', 'wp-state-machine'); ?></th>
                <th><?php _e('Label', 'wp-state-machine'); ?></th>
                <th><?php _e('From State', 'wp-state-machine'); ?></th>
                <th><?php _e('To State', 'wp-state-machine'); ?></th>
                <th><?php _e('Guard Class', 'wp-state-machine'); ?></th>
                <th><?php _e('Sort Order', 'wp-state-machine'); ?></th>
                <th><?php _e('Machine', 'wp-state-machine'); ?></th>
                <th><?php _e('Created', 'wp-state-machine'); ?></th>
                <th><?php _e('Actions', 'wp-state-machine'); ?></th>
            </tr>
        </thead>
        <tbody>
        </tbody>
    </table>
</div>

<!-- Create/Edit Modal -->
<div id="transition-modal" class="wp-state-machine-modal" style="display:none;">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h2 id="modal-title"><?php _e('Add New Transition', 'wp-state-machine'); ?></h2>
                <button type="button" class="modal-close" aria-label="<?php esc_attr_e('Close', 'wp-state-machine'); ?>">
                    <span class="dashicons dashicons-no"></span>
                </button>
            </div>
            <div class="modal-body">
                <form id="transition-form">
                    <input type="hidden" id="transition-id" name="id" value="">

                    <table class="form-table">
                        <tr>
                            <th scope="row">
                                <label for="transition-machine-id"><?php _e('Machine', 'wp-state-machine'); ?> <span class="required">*</span></label>
                            </th>
                            <td>
                                <select id="transition-machine-id" name="machine_id" class="regular-text" required>
                                    <option value=""><?php _e('Select Machine', 'wp-state-machine'); ?></option>
                                    <?php foreach ($machines as $machine): ?>
                                        <option value="<?php echo esc_attr($machine->id); ?>">
                                            <?php echo esc_html($machine->name); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                                <p class="description"><?php _e('The state machine this transition belongs to', 'wp-state-machine'); ?></p>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row">
                                <label for="transition-from-state"><?php _e('From State', 'wp-state-machine'); ?> <span class="required">*</span></label>
                            </th>
                            <td>
                                <select id="transition-from-state" name="from_state_id" class="regular-text" required>
                                    <option value=""><?php _e('Select machine first', 'wp-state-machine'); ?></option>
                                </select>
                                <p class="description"><?php _e('Starting state for this transition', 'wp-state-machine'); ?></p>
                                <p class="description edit-only" style="display:none;color:#d63638;">
                                    <?php _e('Note: From state cannot be changed after creation', 'wp-state-machine'); ?>
                                </p>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row">
                                <label for="transition-to-state"><?php _e('To State', 'wp-state-machine'); ?> <span class="required">*</span></label>
                            </th>
                            <td>
                                <select id="transition-to-state" name="to_state_id" class="regular-text" required>
                                    <option value=""><?php _e('Select machine first', 'wp-state-machine'); ?></option>
                                </select>
                                <p class="description"><?php _e('Target state for this transition', 'wp-state-machine'); ?></p>
                                <p class="description edit-only" style="display:none;color:#d63638;">
                                    <?php _e('Note: To state cannot be changed after creation', 'wp-state-machine'); ?>
                                </p>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row">
                                <label for="transition-label"><?php _e('Label', 'wp-state-machine'); ?> <span class="required">*</span></label>
                            </th>
                            <td>
                                <input type="text" id="transition-label" name="label" class="regular-text" required>
                                <p class="description"><?php _e('Display name for this transition (e.g., "Submit for Approval", "Approve", "Reject")', 'wp-state-machine'); ?></p>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row">
                                <label for="transition-guard-class"><?php _e('Guard Class', 'wp-state-machine'); ?></label>
                            </th>
                            <td>
                                <input type="text" id="transition-guard-class" name="guard_class" class="regular-text">
                                <p class="description"><?php _e('Optional guard class for permission checking (e.g., "RoleGuard", "CapabilityGuard")', 'wp-state-machine'); ?></p>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row">
                                <label for="transition-sort-order"><?php _e('Sort Order', 'wp-state-machine'); ?></label>
                            </th>
                            <td>
                                <input type="number" id="transition-sort-order" name="sort_order" class="small-text" value="0" min="0">
                                <p class="description"><?php _e('Display order (lower numbers appear first)', 'wp-state-machine'); ?></p>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row">
                                <label for="transition-metadata"><?php _e('Metadata', 'wp-state-machine'); ?></label>
                            </th>
                            <td>
                                <textarea id="transition-metadata" name="metadata" class="large-text code" rows="4"></textarea>
                                <p class="description"><?php _e('Optional JSON metadata for custom data', 'wp-state-machine'); ?></p>
                            </td>
                        </tr>
                    </table>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="button button-secondary modal-close">
                    <?php _e('Cancel', 'wp-state-machine'); ?>
                </button>
                <button type="button" class="button button-primary" id="btn-save-transition">
                    <?php _e('Save Transition', 'wp-state-machine'); ?>
                </button>
            </div>
        </div>
    </div>
</div>

<!-- View Modal -->
<div id="view-transition-modal" class="wp-state-machine-modal" style="display:none;">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h2><?php _e('Transition Details', 'wp-state-machine'); ?></h2>
                <button type="button" class="modal-close" aria-label="<?php esc_attr_e('Close', 'wp-state-machine'); ?>">
                    <span class="dashicons dashicons-no"></span>
                </button>
            </div>
            <div class="modal-body">
                <table class="form-table">
                    <tr>
                        <th><?php _e('ID', 'wp-state-machine'); ?>:</th>
                        <td id="view-transition-id"></td>
                    </tr>
                    <tr>
                        <th><?php _e('Machine', 'wp-state-machine'); ?>:</th>
                        <td id="view-transition-machine"></td>
                    </tr>
                    <tr>
                        <th><?php _e('From State', 'wp-state-machine'); ?>:</th>
                        <td id="view-transition-from-state"></td>
                    </tr>
                    <tr>
                        <th><?php _e('To State', 'wp-state-machine'); ?>:</th>
                        <td id="view-transition-to-state"></td>
                    </tr>
                    <tr>
                        <th><?php _e('Label', 'wp-state-machine'); ?>:</th>
                        <td id="view-transition-label"></td>
                    </tr>
                    <tr>
                        <th><?php _e('Guard Class', 'wp-state-machine'); ?>:</th>
                        <td id="view-transition-guard-class"></td>
                    </tr>
                    <tr>
                        <th><?php _e('Sort Order', 'wp-state-machine'); ?>:</th>
                        <td id="view-transition-sort-order"></td>
                    </tr>
                    <tr>
                        <th><?php _e('Metadata', 'wp-state-machine'); ?>:</th>
                        <td><pre id="view-transition-metadata"></pre></td>
                    </tr>
                    <tr>
                        <th><?php _e('Created', 'wp-state-machine'); ?>:</th>
                        <td id="view-transition-created"></td>
                    </tr>
                    <tr>
                        <th><?php _e('Updated', 'wp-state-machine'); ?>:</th>
                        <td id="view-transition-updated"></td>
                    </tr>
                </table>
            </div>
            <div class="modal-footer">
                <button type="button" class="button button-secondary modal-close">
                    <?php _e('Close', 'wp-state-machine'); ?>
                </button>
            </div>
        </div>
    </div>
</div>

<style>
.wp-state-machine-modal {
    position: fixed;
    z-index: 100000;
    left: 0;
    top: 0;
    width: 100%;
    height: 100%;
    overflow: auto;
    background-color: rgba(0,0,0,0.5);
}

.modal-dialog {
    background-color: #fefefe;
    margin: 5% auto;
    width: 90%;
    max-width: 700px;
    border-radius: 4px;
    box-shadow: 0 5px 15px rgba(0,0,0,0.3);
}

.modal-header {
    padding: 20px;
    border-bottom: 1px solid #ddd;
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.modal-header h2 {
    margin: 0;
    font-size: 1.3em;
}

.modal-close {
    background: none;
    border: none;
    cursor: pointer;
    padding: 0;
}

.modal-body {
    padding: 20px;
    max-height: 60vh;
    overflow-y: auto;
}

.modal-footer {
    padding: 15px 20px;
    border-top: 1px solid #ddd;
    text-align: right;
}

.modal-footer .button {
    margin-left: 10px;
}

.required {
    color: #dc3232;
}

#view-transition-metadata {
    background: #f5f5f5;
    padding: 10px;
    border-radius: 3px;
    max-height: 200px;
    overflow: auto;
}
</style>

<script>
jQuery(document).ready(function($) {
    let transitionsTable;
    let currentMachineId = '';
    let isEditMode = false;

    // Initialize DataTable
    function initDataTable() {
        if ($.fn.DataTable.isDataTable('#transitions-table')) {
            transitionsTable.destroy();
        }

        transitionsTable = $('#transitions-table').DataTable({
            processing: true,
            serverSide: true,
            ajax: {
                url: ajaxurl,
                type: 'POST',
                data: function(d) {
                    d.action = 'handle_transition_datatable';
                    d.nonce = '<?php echo wp_create_nonce('wp_state_machine_nonce'); ?>';
                    d.machine_id = currentMachineId;
                }
            },
            columns: [
                { data: 'id' },
                { data: 'label' },
                { data: 'from_state_name' },
                { data: 'to_state_name' },
                { data: 'guard_class' },
                { data: 'sort_order' },
                { data: 'machine_name' },
                { data: 'created_at' },
                { data: 'actions', orderable: false, searchable: false }
            ],
            order: [[5, 'asc']], // Sort by sort_order by default
            pageLength: 25,
            language: {
                emptyTable: '<?php _e('No transitions found', 'wp-state-machine'); ?>',
                processing: '<?php _e('Loading...', 'wp-state-machine'); ?>'
            }
        });
    }

    initDataTable();

    // Filter button
    $('#btn-filter').on('click', function() {
        currentMachineId = $('#filter-machine').val();
        transitionsTable.ajax.reload();
    });

    // Load states when machine is selected
    $('#transition-machine-id').on('change', function() {
        const machineId = $(this).val();
        loadStatesForMachine(machineId);
    });

    // Function to load states for a machine
    function loadStatesForMachine(machineId) {
        if (!machineId) {
            $('#transition-from-state, #transition-to-state').html('<option value="">Select machine first</option>');
            return;
        }

        // Load states via AJAX
        $.post(ajaxurl, {
            action: 'get_states_by_machine',
            nonce: '<?php echo wp_create_nonce('wp_state_machine_nonce'); ?>',
            machine_id: machineId
        }, function(response) {
            if (response.success) {
                const states = response.data;
                let options = '<option value="">Select state</option>';
                states.forEach(function(state) {
                    options += '<option value="' + state.id + '">' + state.name + '</option>';
                });
                $('#transition-from-state, #transition-to-state').html(options);
            }
        });
    }

    // Add new transition button
    $('#btn-add-transition').on('click', function() {
        $('#transition-form')[0].reset();
        $('#transition-id').val('');
        $('#modal-title').text('<?php _e('Add New Transition', 'wp-state-machine'); ?>');
        isEditMode = false;

        // Enable state selects
        $('#transition-machine-id, #transition-from-state, #transition-to-state').prop('disabled', false);
        $('.edit-only').hide();

        // Pre-select machine if filtered
        if (currentMachineId) {
            $('#transition-machine-id').val(currentMachineId).trigger('change');
        } else {
            $('#transition-from-state, #transition-to-state').html('<option value="">Select machine first</option>');
        }

        $('#transition-modal').fadeIn();
    });

    // Close modal
    $('.modal-close').on('click', function() {
        $(this).closest('.wp-state-machine-modal').fadeOut();
    });

    // Close modal on outside click
    $('.wp-state-machine-modal').on('click', function(e) {
        if ($(e.target).hasClass('wp-state-machine-modal')) {
            $(this).fadeOut();
        }
    });

    // Save transition
    $('#btn-save-transition').on('click', function() {
        const transitionId = $('#transition-id').val();
        const action = transitionId ? 'update_transition' : 'create_transition';

        const formData = {
            action: action,
            nonce: '<?php echo wp_create_nonce('wp_state_machine_nonce'); ?>',
            id: transitionId,
            machine_id: $('#transition-machine-id').val(),
            from_state_id: $('#transition-from-state').val(),
            to_state_id: $('#transition-to-state').val(),
            label: $('#transition-label').val(),
            guard_class: $('#transition-guard-class').val(),
            sort_order: $('#transition-sort-order').val(),
            metadata: $('#transition-metadata').val()
        };

        $.post(ajaxurl, formData, function(response) {
            if (response.success) {
                $('#transition-modal').fadeOut();
                transitionsTable.ajax.reload();
                alert(response.data.message);
            } else {
                let errorMsg = response.data.message;
                if (response.data.errors) {
                    errorMsg += '\n\n' + Object.values(response.data.errors).join('\n');
                }
                alert(errorMsg);
            }
        });
    });

    // View transition
    $(document).on('click', '.btn-view-transition', function() {
        const transitionId = $(this).data('id');

        $.post(ajaxurl, {
            action: 'show_transition',
            nonce: '<?php echo wp_create_nonce('wp_state_machine_nonce'); ?>',
            id: transitionId
        }, function(response) {
            if (response.success) {
                const transition = response.data.data;
                $('#view-transition-id').text(transition.id);
                $('#view-transition-machine').text(transition.machine_id);
                $('#view-transition-from-state').text(transition.from_state_name || '-');
                $('#view-transition-to-state').text(transition.to_state_name || '-');
                $('#view-transition-label').text(transition.label);
                $('#view-transition-guard-class').text(transition.guard_class || '-');
                $('#view-transition-sort-order').text(transition.sort_order);
                $('#view-transition-metadata').text(transition.metadata || '-');
                $('#view-transition-created').text(transition.created_at);
                $('#view-transition-updated').text(transition.updated_at);
                $('#view-transition-modal').fadeIn();
            }
        });
    });

    // Edit transition
    $(document).on('click', '.btn-edit-transition', function() {
        const transitionId = $(this).data('id');

        $.post(ajaxurl, {
            action: 'show_transition',
            nonce: '<?php echo wp_create_nonce('wp_state_machine_nonce'); ?>',
            id: transitionId
        }, function(response) {
            if (response.success) {
                const transition = response.data.data;
                isEditMode = true;

                $('#transition-id').val(transition.id);
                $('#transition-machine-id').val(transition.machine_id);
                $('#transition-label').val(transition.label);
                $('#transition-guard-class').val(transition.guard_class || '');
                $('#transition-sort-order').val(transition.sort_order);
                $('#transition-metadata').val(transition.metadata || '');

                // Load states and set values
                loadStatesForMachine(transition.machine_id);
                setTimeout(function() {
                    $('#transition-from-state').val(transition.from_state_id);
                    $('#transition-to-state').val(transition.to_state_id);

                    // Disable state changes in edit mode
                    $('#transition-machine-id, #transition-from-state, #transition-to-state').prop('disabled', true);
                    $('.edit-only').show();
                }, 500);

                $('#modal-title').text('<?php _e('Edit Transition', 'wp-state-machine'); ?>');
                $('#transition-modal').fadeIn();
            }
        });
    });

    // Delete transition
    $(document).on('click', '.btn-delete-transition', function() {
        if (!confirm('<?php _e('Are you sure you want to delete this transition?', 'wp-state-machine'); ?>')) {
            return;
        }

        const transitionId = $(this).data('id');

        $.post(ajaxurl, {
            action: 'delete_transition',
            nonce: '<?php echo wp_create_nonce('wp_state_machine_nonce'); ?>',
            id: transitionId
        }, function(response) {
            if (response.success) {
                transitionsTable.ajax.reload();
                alert(response.data.message);
            } else {
                alert(response.data.message);
            }
        });
    });
});
</script>
