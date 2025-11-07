<?php
/**
 * States Admin View
 *
 * @package     WP_State_Machine
 * @subpackage  Views/Admin/States
 * @version     1.0.0
 * @author      arisciwek
 *
 * Path: /wp-state-machine/src/Views/admin/states/index.php
 *
 * Description: Admin interface for managing state machine states.
 *              Includes DataTable listing, CRUD modals, and machine filtering.
 *              Follows wp-agency admin view pattern.
 *
 * Changelog:
 * 1.0.0 - 2025-11-07
 * - Initial creation
 * - DataTable integration
 * - CRUD modal forms
 * - Machine filter dropdown
 */

defined('ABSPATH') || exit;

// Get all machines for the filter dropdown
global $wpdb;
$machine_table = $wpdb->prefix . 'app_sm_machines';
$machines = $wpdb->get_results("SELECT id, name FROM {$machine_table} WHERE is_active = 1 ORDER BY name ASC");
?>

<div class="wrap wp-state-machine-admin">
    <h1 class="wp-heading-inline"><?php _e('States', 'wp-state-machine'); ?></h1>
    <button type="button" class="page-title-action" id="btn-add-state">
        <?php _e('Add New State', 'wp-state-machine'); ?>
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
    <table id="states-table" class="wp-list-table widefat fixed striped">
        <thead>
            <tr>
                <th><?php _e('ID', 'wp-state-machine'); ?></th>
                <th><?php _e('Name', 'wp-state-machine'); ?></th>
                <th><?php _e('Slug', 'wp-state-machine'); ?></th>
                <th><?php _e('Type', 'wp-state-machine'); ?></th>
                <th><?php _e('Color', 'wp-state-machine'); ?></th>
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
<div id="state-modal" class="wp-state-machine-modal" style="display:none;">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h2 id="modal-title"><?php _e('Add New State', 'wp-state-machine'); ?></h2>
                <button type="button" class="modal-close" aria-label="<?php esc_attr_e('Close', 'wp-state-machine'); ?>">
                    <span class="dashicons dashicons-no"></span>
                </button>
            </div>
            <div class="modal-body">
                <form id="state-form">
                    <input type="hidden" id="state-id" name="id" value="">

                    <table class="form-table">
                        <tr>
                            <th scope="row">
                                <label for="state-machine-id"><?php _e('Machine', 'wp-state-machine'); ?> <span class="required">*</span></label>
                            </th>
                            <td>
                                <select id="state-machine-id" name="machine_id" class="regular-text" required>
                                    <option value=""><?php _e('Select Machine', 'wp-state-machine'); ?></option>
                                    <?php foreach ($machines as $machine): ?>
                                        <option value="<?php echo esc_attr($machine->id); ?>">
                                            <?php echo esc_html($machine->name); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                                <p class="description"><?php _e('The state machine this state belongs to', 'wp-state-machine'); ?></p>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row">
                                <label for="state-name"><?php _e('Name', 'wp-state-machine'); ?> <span class="required">*</span></label>
                            </th>
                            <td>
                                <input type="text" id="state-name" name="name" class="regular-text" required>
                                <p class="description"><?php _e('Display name for this state (e.g., "Pending Approval")', 'wp-state-machine'); ?></p>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row">
                                <label for="state-slug"><?php _e('Slug', 'wp-state-machine'); ?> <span class="required">*</span></label>
                            </th>
                            <td>
                                <input type="text" id="state-slug" name="slug" class="regular-text" pattern="[a-z0-9-_]+" required>
                                <p class="description"><?php _e('Unique identifier within this machine (lowercase, no spaces)', 'wp-state-machine'); ?></p>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row">
                                <label for="state-type"><?php _e('Type', 'wp-state-machine'); ?> <span class="required">*</span></label>
                            </th>
                            <td>
                                <select id="state-type" name="type" class="regular-text" required>
                                    <option value="normal"><?php _e('Normal', 'wp-state-machine'); ?></option>
                                    <option value="initial"><?php _e('Initial', 'wp-state-machine'); ?></option>
                                    <option value="intermediate"><?php _e('Intermediate', 'wp-state-machine'); ?></option>
                                    <option value="final"><?php _e('Final', 'wp-state-machine'); ?></option>
                                </select>
                                <p class="description">
                                    <?php _e('Initial: Starting state | Normal/Intermediate: Regular state | Final: End state', 'wp-state-machine'); ?>
                                </p>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row">
                                <label for="state-color"><?php _e('Color', 'wp-state-machine'); ?></label>
                            </th>
                            <td>
                                <input type="color" id="state-color" name="color" value="#3498db">
                                <p class="description"><?php _e('Color for visual representation (e.g., in diagrams)', 'wp-state-machine'); ?></p>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row">
                                <label for="state-sort-order"><?php _e('Sort Order', 'wp-state-machine'); ?></label>
                            </th>
                            <td>
                                <input type="number" id="state-sort-order" name="sort_order" class="small-text" value="0" min="0">
                                <p class="description"><?php _e('Display order (lower numbers appear first)', 'wp-state-machine'); ?></p>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row">
                                <label for="state-metadata"><?php _e('Metadata', 'wp-state-machine'); ?></label>
                            </th>
                            <td>
                                <textarea id="state-metadata" name="metadata" class="large-text code" rows="4"></textarea>
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
                <button type="button" class="button button-primary" id="btn-save-state">
                    <?php _e('Save State', 'wp-state-machine'); ?>
                </button>
            </div>
        </div>
    </div>
</div>

<!-- View Modal -->
<div id="view-state-modal" class="wp-state-machine-modal" style="display:none;">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h2><?php _e('State Details', 'wp-state-machine'); ?></h2>
                <button type="button" class="modal-close" aria-label="<?php esc_attr_e('Close', 'wp-state-machine'); ?>">
                    <span class="dashicons dashicons-no"></span>
                </button>
            </div>
            <div class="modal-body">
                <table class="form-table">
                    <tr>
                        <th><?php _e('ID', 'wp-state-machine'); ?>:</th>
                        <td id="view-state-id"></td>
                    </tr>
                    <tr>
                        <th><?php _e('Machine', 'wp-state-machine'); ?>:</th>
                        <td id="view-state-machine"></td>
                    </tr>
                    <tr>
                        <th><?php _e('Name', 'wp-state-machine'); ?>:</th>
                        <td id="view-state-name"></td>
                    </tr>
                    <tr>
                        <th><?php _e('Slug', 'wp-state-machine'); ?>:</th>
                        <td id="view-state-slug"></td>
                    </tr>
                    <tr>
                        <th><?php _e('Type', 'wp-state-machine'); ?>:</th>
                        <td id="view-state-type"></td>
                    </tr>
                    <tr>
                        <th><?php _e('Color', 'wp-state-machine'); ?>:</th>
                        <td id="view-state-color"></td>
                    </tr>
                    <tr>
                        <th><?php _e('Sort Order', 'wp-state-machine'); ?>:</th>
                        <td id="view-state-sort-order"></td>
                    </tr>
                    <tr>
                        <th><?php _e('Metadata', 'wp-state-machine'); ?>:</th>
                        <td><pre id="view-state-metadata"></pre></td>
                    </tr>
                    <tr>
                        <th><?php _e('Created', 'wp-state-machine'); ?>:</th>
                        <td id="view-state-created"></td>
                    </tr>
                    <tr>
                        <th><?php _e('Updated', 'wp-state-machine'); ?>:</th>
                        <td id="view-state-updated"></td>
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
    max-width: 600px;
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

#view-state-metadata {
    background: #f5f5f5;
    padding: 10px;
    border-radius: 3px;
    max-height: 200px;
    overflow: auto;
}
</style>

<script>
jQuery(document).ready(function($) {
    let statesTable;
    let currentMachineId = '';

    // Initialize DataTable
    function initDataTable() {
        if ($.fn.DataTable.isDataTable('#states-table')) {
            statesTable.destroy();
        }

        statesTable = $('#states-table').DataTable({
            processing: true,
            serverSide: true,
            ajax: {
                url: ajaxurl,
                type: 'POST',
                data: function(d) {
                    d.action = 'handle_state_datatable';
                    d.nonce = '<?php echo wp_create_nonce('wp_state_machine_nonce'); ?>';
                    d.machine_id = currentMachineId;
                }
            },
            columns: [
                { data: 'id' },
                { data: 'name' },
                { data: 'slug' },
                { data: 'type' },
                {
                    data: 'color',
                    render: function(data, type, row) {
                        if (data && data !== '-') {
                            return '<span style="display:inline-block;width:20px;height:20px;background-color:' +
                                   data + ';border:1px solid #ccc;border-radius:3px;"></span> ' + data;
                        }
                        return data;
                    }
                },
                { data: 'sort_order' },
                { data: 'machine_name' },
                { data: 'created_at' },
                { data: 'actions', orderable: false, searchable: false }
            ],
            order: [[5, 'asc']], // Sort by sort_order by default
            pageLength: 25,
            language: {
                emptyTable: '<?php _e('No states found', 'wp-state-machine'); ?>',
                processing: '<?php _e('Loading...', 'wp-state-machine'); ?>'
            }
        });
    }

    initDataTable();

    // Filter button
    $('#btn-filter').on('click', function() {
        currentMachineId = $('#filter-machine').val();
        statesTable.ajax.reload();
    });

    // Add new state button
    $('#btn-add-state').on('click', function() {
        $('#state-form')[0].reset();
        $('#state-id').val('');
        $('#modal-title').text('<?php _e('Add New State', 'wp-state-machine'); ?>');

        // Pre-select machine if filtered
        if (currentMachineId) {
            $('#state-machine-id').val(currentMachineId);
        }

        $('#state-modal').fadeIn();
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

    // Auto-generate slug from name
    $('#state-name').on('blur', function() {
        if ($('#state-slug').val() === '') {
            let slug = $(this).val()
                .toLowerCase()
                .replace(/[^a-z0-9-_]/g, '-')
                .replace(/-+/g, '-')
                .replace(/^-|-$/g, '');
            $('#state-slug').val(slug);
        }
    });

    // Save state
    $('#btn-save-state').on('click', function() {
        const stateId = $('#state-id').val();
        const action = stateId ? 'update_state' : 'create_state';

        const formData = {
            action: action,
            nonce: '<?php echo wp_create_nonce('wp_state_machine_nonce'); ?>',
            id: stateId,
            machine_id: $('#state-machine-id').val(),
            name: $('#state-name').val(),
            slug: $('#state-slug').val(),
            type: $('#state-type').val(),
            color: $('#state-color').val(),
            sort_order: $('#state-sort-order').val(),
            metadata: $('#state-metadata').val()
        };

        $.post(ajaxurl, formData, function(response) {
            if (response.success) {
                $('#state-modal').fadeOut();
                statesTable.ajax.reload();
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

    // View state
    $(document).on('click', '.btn-view-state', function() {
        const stateId = $(this).data('id');

        $.post(ajaxurl, {
            action: 'show_state',
            nonce: '<?php echo wp_create_nonce('wp_state_machine_nonce'); ?>',
            id: stateId
        }, function(response) {
            if (response.success) {
                const state = response.data.data;
                $('#view-state-id').text(state.id);
                $('#view-state-machine').text(state.machine_id);
                $('#view-state-name').text(state.name);
                $('#view-state-slug').text(state.slug);
                $('#view-state-type').text(state.type);
                $('#view-state-color').html('<span style="display:inline-block;width:20px;height:20px;background-color:' +
                    (state.color || '#cccccc') + ';border:1px solid #ccc;border-radius:3px;"></span> ' +
                    (state.color || '-'));
                $('#view-state-sort-order').text(state.sort_order);
                $('#view-state-metadata').text(state.metadata || '-');
                $('#view-state-created').text(state.created_at);
                $('#view-state-updated').text(state.updated_at);
                $('#view-state-modal').fadeIn();
            }
        });
    });

    // Edit state
    $(document).on('click', '.btn-edit-state', function() {
        const stateId = $(this).data('id');

        $.post(ajaxurl, {
            action: 'show_state',
            nonce: '<?php echo wp_create_nonce('wp_state_machine_nonce'); ?>',
            id: stateId
        }, function(response) {
            if (response.success) {
                const state = response.data.data;
                $('#state-id').val(state.id);
                $('#state-machine-id').val(state.machine_id);
                $('#state-name').val(state.name);
                $('#state-slug').val(state.slug);
                $('#state-type').val(state.type);
                $('#state-color').val(state.color || '#3498db');
                $('#state-sort-order').val(state.sort_order);
                $('#state-metadata').val(state.metadata || '');
                $('#modal-title').text('<?php _e('Edit State', 'wp-state-machine'); ?>');
                $('#state-modal').fadeIn();
            }
        });
    });

    // Delete state
    $(document).on('click', '.btn-delete-state', function() {
        if (!confirm('<?php _e('Are you sure you want to delete this state?', 'wp-state-machine'); ?>')) {
            return;
        }

        const stateId = $(this).data('id');

        $.post(ajaxurl, {
            action: 'delete_state',
            nonce: '<?php echo wp_create_nonce('wp_state_machine_nonce'); ?>',
            id: stateId
        }, function(response) {
            if (response.success) {
                statesTable.ajax.reload();
                alert(response.data.message);
            } else {
                alert(response.data.message);
            }
        });
    });
});
</script>
