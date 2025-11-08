/**
 * States Admin JavaScript
 *
 * @package     WP_State_Machine
 * @subpackage  Assets/JS
 * @version     1.0.0
 * @author      arisciwek
 *
 * Path: /wp-state-machine/assets/js/states.js
 *
 * Description: Handles DataTable initialization, CRUD operations,
 *              modal interactions, and AJAX requests for states.
 *
 * Dependencies:
 * - jQuery
 * - DataTables
 * - wpStateMachineStatesData (localized script)
 *
 * Changelog:
 * 1.0.0 - 2025-11-08
 * - Initial creation (extracted from states-view.php)
 * - DataTable integration
 * - CRUD operations
 * - Modal management
 * - Slug auto-generation
 */

(function($) {
    'use strict';

    const StatesAdmin = {
        /**
         * DataTable instance
         */
        table: null,

        /**
         * Current machine filter
         */
        currentMachineId: '',

        /**
         * Localized data from PHP
         */
        data: wpStateMachineStatesData,

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
            if ($.fn.DataTable && $.fn.DataTable.isDataTable('#states-table')) {
                $('#states-table').DataTable().destroy();
            }

            this.table = $('#states-table').DataTable({
                processing: true,
                serverSide: true,
                ajax: {
                    url: self.data.ajaxUrl,
                    type: 'POST',
                    data: function(d) {
                        d.action = 'handle_state_datatable';
                        d.nonce = self.data.nonce;
                        d.machine_id = self.currentMachineId;
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
                order: [[5, 'asc']], // Sort by sort_order
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
            $('#btn-filter').on('click', function() {
                self.currentMachineId = $('#filter-machine').val();
                self.table.ajax.reload();
            });

            // Add new state
            $('#btn-add-state').on('click', function() {
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
            $('#state-name').on('input blur', function() {
                // Only auto-generate if in create mode (no ID)
                if (!$('#state-id').val()) {
                    const slug = self.generateSlug($(this).val());
                    $('#state-slug').val(slug);
                }
            });

            // Save state
            $('#btn-save-state').on('click', function() {
                self.saveState();
            });

            // View state
            $(document).on('click', '.btn-view-state', function() {
                self.viewState($(this).data('id'));
            });

            // Edit state
            $(document).on('click', '.btn-edit-state', function() {
                self.editState($(this).data('id'));
            });

            // Delete state
            $(document).on('click', '.btn-delete-state', function() {
                self.deleteState($(this).data('id'));
            });
        },

        /**
         * Open create modal
         */
        openCreateModal: function() {
            $('#state-form')[0].reset();
            $('#state-id').val('');
            $('#state-slug').removeClass('has-value');
            $('#modal-title').text(this.data.i18n.addTitle);

            // Pre-select machine if filtered
            if (this.currentMachineId) {
                $('#state-machine-id').val(this.currentMachineId);
            }

            $('#state-modal').fadeIn();
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
         * Save state (create or update)
         */
        saveState: function() {
            const self = this;
            const stateId = $('#state-id').val();
            const action = stateId ? 'update_state' : 'create_state';

            const formData = {
                action: action,
                nonce: self.data.nonce,
                id: stateId,
                machine_id: $('#state-machine-id').val(),
                name: $('#state-name').val(),
                slug: $('#state-slug').val(),
                type: $('#state-type').val(),
                color: $('#state-color').val(),
                sort_order: $('#state-sort-order').val(),
                metadata: $('#state-metadata').val()
            };

            $.post(self.data.ajaxUrl, formData)
                .done(function(response) {
                    if (response.success) {
                        // Close modal and reload table
                        $('#state-modal').fadeOut(200);
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
                    console.error('AJAX Error:', status, error, xhr.responseText);
                    alert('An error occurred while saving. Please check console for details.');
                });
        },

        /**
         * View state details
         */
        viewState: function(id) {
            const self = this;

            $.post(self.data.ajaxUrl, {
                action: 'show_state',
                nonce: self.data.nonce,
                id: id
            }, function(response) {
                if (response.success) {
                    const state = response.data.data;
                    $('#view-state-id').text(state.id);
                    $('#view-state-machine').text(state.machine_id);
                    $('#view-state-name').text(state.name);
                    $('#view-state-slug').text(state.slug);
                    $('#view-state-type').text(state.type);
                    $('#view-state-color').html(
                        '<span style="display:inline-block;width:20px;height:20px;background-color:' +
                        (state.color || '#cccccc') + ';border:1px solid #ccc;border-radius:3px;"></span> ' +
                        (state.color || '-')
                    );
                    $('#view-state-sort-order').text(state.sort_order);
                    $('#view-state-metadata').text(state.metadata || '-');
                    $('#view-state-created').text(state.created_at);
                    $('#view-state-updated').text(state.updated_at);
                    $('#view-state-modal').fadeIn();
                }
            });
        },

        /**
         * Edit state
         */
        editState: function(id) {
            const self = this;

            $.post(self.data.ajaxUrl, {
                action: 'show_state',
                nonce: self.data.nonce,
                id: id
            }, function(response) {
                if (response.success) {
                    const state = response.data.data;
                    $('#state-id').val(state.id);
                    $('#state-machine-id').val(state.machine_id);
                    $('#state-name').val(state.name);
                    $('#state-slug').val(state.slug).addClass('has-value');
                    $('#state-type').val(state.type);
                    $('#state-color').val(state.color || '#3498db');
                    $('#state-sort-order').val(state.sort_order);
                    $('#state-metadata').val(state.metadata || '');
                    $('#modal-title').text(self.data.i18n.editTitle);
                    $('#state-modal').fadeIn();
                }
            });
        },

        /**
         * Delete state
         */
        deleteState: function(id) {
            const self = this;

            if (!confirm(self.data.i18n.confirmDelete)) {
                return;
            }

            $.post(self.data.ajaxUrl, {
                action: 'delete_state',
                nonce: self.data.nonce,
                id: id
            }, function(response) {
                if (response.success) {
                    self.table.ajax.reload();
                    // Silent success - no alert
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
            console.error('DataTables library not loaded');
            return;
        }
        StatesAdmin.init();
    });

})(jQuery);
