/**
 * State Machines Admin JavaScript
 *
 * @package     WP_State_Machine
 * @subpackage  Assets/JS
 * @version     1.0.0
 * @author      arisciwek
 *
 * Path: /wp-state-machine/assets/js/machines.css
 *
 * Description: Handles DataTable initialization, CRUD operations,
 *              modal interactions, and AJAX requests for state machines.
 *
 * Dependencies:
 * - jQuery
 * - DataTables
 * - wpStateMachineMachinesData (localized script)
 *
 * Changelog:
 * 1.0.0 - 2025-11-08
 * - Initial creation
 * - DataTable integration
 * - CRUD operations
 * - Modal management
 * - Slug auto-generation
 */

(function($) {
    'use strict';

    const MachinesAdmin = {
        /**
         * DataTable instance
         */
        table: null,

        /**
         * Current workflow group filter
         */
        currentWorkflowGroupId: '',

        /**
         * Prevent double-click on filter
         */
        isFiltering: false,

        /**
         * Localized data from PHP
         */
        data: wpStateMachineMachinesData,

        /**
         * Initialize admin functionality
         */
        init: function() {
            this.initDataTable();
            this.bindEvents();
        },

        /**
         * Initialize DataTable
         */
        initDataTable: function() {
            const self = this;

            // Check if DataTables is loaded and table exists
            if ($.fn.DataTable && $.fn.DataTable.isDataTable('#machines-table')) {
                $('#machines-table').DataTable().destroy();
            }

            this.table = $('#machines-table').DataTable({
                processing: true,
                serverSide: true,
                ajax: {
                    url: self.data.ajaxUrl,
                    type: 'POST',
                    data: function(d) {
                        d.action = 'handle_state_machine_datatable';
                        d.nonce = self.data.nonce;
                        d.workflow_group_id = self.currentWorkflowGroupId;
                        d._cache_bust = Date.now(); // Prevent caching issues
                    },
                    error: function(xhr, error, code) {
                        // Force hide processing indicator
                        $('.dataTables_processing').hide();
                        // Reset filtering state
                        self.isFiltering = false;
                        $('#btn-filter').prop('disabled', false).removeClass('loading');
                    },
                    dataSrc: function(json) {
                        // Ensure data is valid
                        if (!json || !json.data) {
                            return [];
                        }
                        return json.data;
                    }
                },
                columns: [
                    { data: 'id' },
                    { data: 'name' },
                    { data: 'slug' },
                    { data: 'description' },
                    { data: 'workflow_group_name' },
                    {
                        data: 'is_active',
                        render: function(data) {
                            return data == 1 ?
                                '<span class="dashicons dashicons-yes" style="color:green;"></span>' :
                                '<span class="dashicons dashicons-no" style="color:red;"></span>';
                        }
                    },
                    { data: 'created_at' },
                    { data: 'actions', orderable: false, searchable: false }
                ],
                order: [[1, 'asc']], // Sort by name
                pageLength: 25,
                language: {
                    emptyTable: self.data.i18n.emptyTable,
                    processing: self.data.i18n.processing
                }
            });
        },

        /**
         * Bind event handlers
         */
        bindEvents: function() {
            const self = this;

            // Filter button
            $('#btn-filter').on('click', function(e) {
                e.preventDefault();

                // Prevent double-click
                if (self.isFiltering) {
                    return;
                }

                const selectedGroup = $('#filter-workflow-group').val();

                // Update current workflow group ID (empty string for "Show All")
                self.currentWorkflowGroupId = selectedGroup || '';
                self.isFiltering = true;

                // Disable button during filtering
                $(this).prop('disabled', true).addClass('loading');

                // Timeout fallback (in case request hangs)
                const timeoutId = setTimeout(function() {
                    self.isFiltering = false;
                    $('#btn-filter').prop('disabled', false).removeClass('loading');
                    $('.dataTables_processing').hide();
                }, 10000); // 10 second timeout

                // Force reload with callbacks to ensure spinner stops
                if (self.table && self.table.ajax) {
                    self.table.ajax.reload(function(json) {
                        // Success callback
                        clearTimeout(timeoutId);
                        self.isFiltering = false;
                        $('#btn-filter').prop('disabled', false).removeClass('loading');
                    }, false); // false = don't reset paging
                } else {
                    clearTimeout(timeoutId);
                    self.isFiltering = false;
                    $('#btn-filter').prop('disabled', false).removeClass('loading');
                }
            });

            // Add new machine
            $('#btn-add-machine').on('click', function() {
                self.openCreateModal();
            });

            // Close modals
            $('.modal-close').on('click', function() {
                $(this).closest('.wp-state-machine-modal').fadeOut();
            });

            // Close modal on outside click
            $('.wp-state-machine-modal').on('click', function(e) {
                if ($(e.target).hasClass('wp-state-machine-modal')) {
                    $(this).fadeOut();
                }
            });

            // Auto-generate slug from name (only in create mode)
            $('#machine-name').on('input blur', function() {
                // Only auto-generate if in create mode (no ID)
                if (!$('#machine-id').val()) {
                    const slug = self.generateSlug($(this).val());
                    $('#machine-slug').val(slug).addClass('has-value');
                }
            });

            // Save machine
            $('#btn-save-machine').on('click', function() {
                self.saveMachine();
            });

            // View machine
            $(document).on('click', '.btn-view-machine', function() {
                self.viewMachine($(this).data('id'));
            });

            // Edit machine
            $(document).on('click', '.btn-edit-machine', function() {
                self.editMachine($(this).data('id'));
            });

            // Delete machine
            $(document).on('click', '.btn-delete-machine', function() {
                self.deleteMachine($(this).data('id'));
            });
        },

        /**
         * Open create modal
         */
        openCreateModal: function() {
            $('#machine-form')[0].reset();
            $('#machine-id').val('');
            $('#machine-plugin-slug').val('wp-state-machine');
            $('#machine-entity-type').val('generic');
            $('#machine-is-active').prop('checked', true);
            $('#machine-slug').val('').removeClass('has-value');
            $('#modal-title').text(this.data.i18n.addTitle);
            $('#machine-modal').fadeIn();
        },

        /**
         * Generate slug from name
         */
        generateSlug: function(name) {
            return name
                .toLowerCase()
                .replace(/[^a-z0-9-_]/g, '-')
                .replace(/-+/g, '-')
                .replace(/^-|-$/g, '');
        },

        /**
         * Save machine (create or update)
         */
        saveMachine: function() {
            const self = this;
            const machineId = $('#machine-id').val();
            const action = machineId ? 'update_state_machine' : 'create_state_machine';

            const formData = {
                action: action,
                nonce: self.data.nonce,
                id: machineId,
                name: $('#machine-name').val(),
                slug: $('#machine-slug').val(),
                plugin_slug: $('#machine-plugin-slug').val(),
                entity_type: $('#machine-entity-type').val(),
                description: $('#machine-description').val(),
                workflow_group_id: $('#machine-workflow-group').val() || null,
                is_active: $('#machine-is-active').is(':checked') ? 1 : 0
            };

            $.post(self.data.ajaxUrl, formData)
                .done(function(response) {
                    if (response.success) {
                        // Close modal
                        $('#machine-modal').fadeOut(200);
                        // Reload table immediately
                        setTimeout(function() {
                            if (self.table && self.table.ajax) {
                                self.table.ajax.reload(null, false);
                            }
                        }, 250);
                    } else {
                        let errorMsg = response.data.message;
                        if (response.data.errors) {
                            errorMsg += '\n\n' + Object.values(response.data.errors).join('\n');
                        }
                        alert(errorMsg);
                    }
                })
                .fail(function(xhr, status, error) {
                    alert('An error occurred while saving. Please try again.');
                });
        },

        /**
         * View machine details
         */
        viewMachine: function(id) {
            const self = this;

            $.post(self.data.ajaxUrl, {
                action: 'show_state_machine',
                nonce: self.data.nonce,
                id: id
            }, function(response) {
                if (response.success) {
                    const machine = response.data.data;
                    $('#view-machine-id').text(machine.id);
                    $('#view-machine-name').text(machine.name);
                    $('#view-machine-slug').text(machine.slug);
                    $('#view-machine-description').text(machine.description || '-');
                    $('#view-machine-workflow-group').text(machine.workflow_group_name || '-');
                    $('#view-machine-is-active').html(
                        machine.is_active == 1 ?
                            '<span class="dashicons dashicons-yes" style="color:green;"></span> ' + self.data.i18n.active :
                            '<span class="dashicons dashicons-no" style="color:red;"></span> ' + self.data.i18n.inactive
                    );
                    $('#view-machine-created').text(machine.created_at);
                    $('#view-machine-updated').text(machine.updated_at);
                    $('#view-machine-modal').fadeIn();
                }
            });
        },

        /**
         * Edit machine
         */
        editMachine: function(id) {
            const self = this;

            $.post(self.data.ajaxUrl, {
                action: 'show_state_machine',
                nonce: self.data.nonce,
                id: id
            }, function(response) {
                if (response.success) {
                    const machine = response.data.data;
                    $('#machine-id').val(machine.id);
                    $('#machine-name').val(machine.name);
                    $('#machine-slug').val(machine.slug).addClass('has-value');
                    $('#machine-plugin-slug').val(machine.plugin_slug || 'wp-state-machine');
                    $('#machine-entity-type').val(machine.entity_type || 'generic');
                    $('#machine-description').val(machine.description || '');
                    $('#machine-workflow-group').val(machine.workflow_group_id || '');
                    $('#machine-is-active').prop('checked', machine.is_active == 1);
                    $('#modal-title').text(self.data.i18n.editTitle);
                    $('#machine-modal').fadeIn();
                }
            });
        },

        /**
         * Delete machine
         */
        deleteMachine: function(id) {
            const self = this;

            if (!confirm(self.data.i18n.confirmDelete)) {
                return;
            }

            $.post(self.data.ajaxUrl, {
                action: 'delete_state_machine',
                nonce: self.data.nonce,
                id: id
            }, function(response) {
                if (response.success) {
                    self.table.ajax.reload();
                    alert(response.data.message);
                } else {
                    alert(response.data.message);
                }
            });
        }
    };

    // Initialize on document ready
    $(document).ready(function() {
        // Ensure DataTables is loaded before initializing
        if (typeof $.fn.DataTable === 'undefined') {
            return;
        }
        MachinesAdmin.init();
    });

})(jQuery);
