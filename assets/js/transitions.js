/**
 * Transitions Admin JavaScript
 *
 * @package     WP_State_Machine
 * @subpackage  Assets/JS
 * @version     1.0.0
 * @author      arisciwek
 *
 * Description: JavaScript for Transitions admin page
 *              Extracted from transitions view for clean architecture
 *              Uses localized data from wpStateMachineTransitionsData
 *
 * Dependencies:
 * - jQuery
 * - DataTables
 *
 * Changelog:
 * 1.0.0 - 2025-11-08
 * - Extracted from transitions-view.php
 * - Implemented object-based pattern
 * - Uses localized data for i18n and config
 * - Removed success alerts, added auto-reload
 */

(function($) {
    'use strict';

    const TransitionsAdmin = {
        table: null,
        currentMachineId: '',
        isEditMode: false,
        data: wpStateMachineTransitionsData,

        /**
         * Initialize the admin interface
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
            if ($.fn.DataTable && $.fn.DataTable.isDataTable('#transitions-table')) {
                $('#transitions-table').DataTable().destroy();
            }

            this.table = $('#transitions-table').DataTable({
                processing: true,
                serverSide: true,
                ajax: {
                    url: self.data.ajaxUrl,
                    type: 'POST',
                    data: function(d) {
                        d.action = 'handle_transition_datatable';
                        d.nonce = self.data.nonce;
                        d.machine_id = self.currentMachineId;
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
                    emptyTable: self.data.i18n.emptyTable,
                    processing: self.data.i18n.processing
                }
            });
        },

        /**
         * Bind all event handlers
         */
        bindEvents: function() {
            const self = this;

            // Filter button
            $('#btn-filter').on('click', function() {
                self.currentMachineId = $('#filter-machine').val();
                self.table.ajax.reload();
            });

            // Load states when machine is selected
            $('#transition-machine-id').on('change', function() {
                const machineId = $(this).val();
                self.loadStatesForMachine(machineId);
            });

            // Add new transition button
            $('#btn-add-transition').on('click', function() {
                self.showAddModal();
            });

            // Close modal
            $('.modal-close').on('click', function() {
                $(this).closest('.wp-state-machine-modal').fadeOut(200);
            });

            // Close modal on outside click
            $('.wp-state-machine-modal').on('click', function(e) {
                if ($(e.target).hasClass('wp-state-machine-modal')) {
                    $(this).fadeOut(200);
                }
            });

            // Save transition
            $('#btn-save-transition').on('click', function() {
                self.saveTransition();
            });

            // View transition
            $(document).on('click', '.btn-view-transition', function() {
                const transitionId = $(this).data('id');
                self.viewTransition(transitionId);
            });

            // Edit transition
            $(document).on('click', '.btn-edit-transition', function() {
                const transitionId = $(this).data('id');
                self.editTransition(transitionId);
            });

            // Delete transition
            $(document).on('click', '.btn-delete-transition', function() {
                const transitionId = $(this).data('id');
                self.deleteTransition(transitionId);
            });
        },

        /**
         * Load states for a specific machine
         */
        loadStatesForMachine: function(machineId) {
            const self = this;

            if (!machineId) {
                $('#transition-from-state, #transition-to-state').html(
                    '<option value="">' + self.data.i18n.selectMachine + '</option>'
                );
                return;
            }

            // Load states via AJAX
            $.post(self.data.ajaxUrl, {
                action: 'get_states_by_machine',
                nonce: self.data.nonce,
                machine_id: machineId
            }, function(response) {
                if (response.success) {
                    const states = response.data;
                    let options = '<option value="">' + self.data.i18n.selectState + '</option>';
                    states.forEach(function(state) {
                        options += '<option value="' + state.id + '">' + state.name + '</option>';
                    });
                    $('#transition-from-state, #transition-to-state').html(options);
                }
            });
        },

        /**
         * Show add new transition modal
         */
        showAddModal: function() {
            const self = this;

            $('#transition-form')[0].reset();
            $('#transition-id').val('');
            $('#modal-title').text(self.data.i18n.addTitle);
            self.isEditMode = false;

            // Enable state selects
            $('#transition-machine-id, #transition-from-state, #transition-to-state').prop('disabled', false);
            $('.edit-only').hide();

            // Pre-select machine if filtered
            if (self.currentMachineId) {
                $('#transition-machine-id').val(self.currentMachineId).trigger('change');
            } else {
                $('#transition-from-state, #transition-to-state').html(
                    '<option value="">' + self.data.i18n.selectMachine + '</option>'
                );
            }

            $('#transition-modal').fadeIn(200);
        },

        /**
         * Save transition (create or update)
         */
        saveTransition: function() {
            const self = this;
            const transitionId = $('#transition-id').val();
            const action = transitionId ? 'update_transition' : 'create_transition';

            const formData = {
                action: action,
                nonce: self.data.nonce,
                id: transitionId,
                machine_id: $('#transition-machine-id').val(),
                from_state_id: $('#transition-from-state').val(),
                to_state_id: $('#transition-to-state').val(),
                label: $('#transition-label').val(),
                guard_class: $('#transition-guard-class').val(),
                sort_order: $('#transition-sort-order').val(),
                metadata: $('#transition-metadata').val()
            };

            $.post(self.data.ajaxUrl, formData)
                .done(function(response) {
                    console.log('Save response:', response);
                    if (response.success) {
                        // Close modal
                        $('#transition-modal').fadeOut(200);
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
                    console.error('AJAX Error:', status, error, xhr.responseText);
                    alert('An error occurred while saving. Please check console for details.');
                });
        },

        /**
         * View transition details
         */
        viewTransition: function(transitionId) {
            const self = this;

            $.post(self.data.ajaxUrl, {
                action: 'show_transition',
                nonce: self.data.nonce,
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
                    $('#view-transition-modal').fadeIn(200);
                }
            });
        },

        /**
         * Edit transition
         */
        editTransition: function(transitionId) {
            const self = this;

            $.post(self.data.ajaxUrl, {
                action: 'show_transition',
                nonce: self.data.nonce,
                id: transitionId
            }, function(response) {
                if (response.success) {
                    const transition = response.data.data;
                    self.isEditMode = true;

                    $('#transition-id').val(transition.id);
                    $('#transition-machine-id').val(transition.machine_id);
                    $('#transition-label').val(transition.label);
                    $('#transition-guard-class').val(transition.guard_class || '');
                    $('#transition-sort-order').val(transition.sort_order);
                    $('#transition-metadata').val(transition.metadata || '');

                    // Load states and set values
                    self.loadStatesForMachine(transition.machine_id);
                    setTimeout(function() {
                        $('#transition-from-state').val(transition.from_state_id);
                        $('#transition-to-state').val(transition.to_state_id);

                        // Disable state changes in edit mode
                        $('#transition-machine-id, #transition-from-state, #transition-to-state').prop('disabled', true);
                        $('.edit-only').show();
                    }, 500);

                    $('#modal-title').text(self.data.i18n.editTitle);
                    $('#transition-modal').fadeIn(200);
                }
            });
        },

        /**
         * Delete transition
         */
        deleteTransition: function(transitionId) {
            const self = this;

            if (!confirm(self.data.i18n.confirmDelete)) {
                return;
            }

            $.post(self.data.ajaxUrl, {
                action: 'delete_transition',
                nonce: self.data.nonce,
                id: transitionId
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

    // Initialize when document is ready
    $(document).ready(function() {
        if (typeof $.fn.DataTable === 'undefined') {
            console.error('DataTables library not loaded');
            return;
        }

        if (typeof wpStateMachineTransitionsData === 'undefined') {
            console.error('Transitions data not localized');
            return;
        }

        TransitionsAdmin.init();
    });

})(jQuery);
