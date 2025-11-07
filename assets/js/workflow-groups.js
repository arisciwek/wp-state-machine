/**
 * Workflow Groups Admin JavaScript
 *
 * @package     WP_State_Machine
 * @version     1.0.0
 * @author      arisciwek
 *
 * Path: /assets/js/workflow-groups.js
 *
 * Description: JavaScript for workflow groups admin interface.
 *              Handles DataTable, CRUD operations, and modals.
 *
 * Dependencies:
 * - jQuery
 * - DataTables
 *
 * Changelog:
 * 1.0.0 - 2025-11-07 (TODO-6102 PRIORITAS #7)
 * - Initial creation
 * - DataTable initialization
 * - CRUD handlers
 * - Modal management
 */

(function($) {
    'use strict';

    // Wait for DOM ready
    $(document).ready(function() {
        const GroupsAdmin = {
            nonce: wpStateMachineGroupsData.nonce,
            ajaxUrl: wpStateMachineGroupsData.ajaxUrl,
            groupsTable: null,

            /**
             * Initialize the groups admin interface
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

                if ($.fn.DataTable.isDataTable('#workflow-groups-table')) {
                    $('#workflow-groups-table').DataTable().destroy();
                }

                this.groupsTable = $('#workflow-groups-table').DataTable({
                    processing: true,
                    serverSide: true,
                    ajax: {
                        url: this.ajaxUrl,
                        type: 'POST',
                        data: function(d) {
                            d.action = 'handle_workflow_group_datatable';
                            d.nonce = self.nonce;
                        },
                        error: function(xhr, error, thrown) {
                            console.error('DataTable error:', error, thrown);
                            alert('Failed to load workflow groups.');
                        }
                    },
                    columns: [
                        { data: 'id', width: '60px' },
                        {
                            data: 'icon',
                            width: '50px',
                            orderable: false,
                            render: function(data) {
                                return '<span class="dashicons ' + self.escapeHtml(data) + '" style="font-size: 20px;"></span>';
                            }
                        },
                        { data: 'name' },
                        { data: 'slug', render: function(data) {
                            return '<code>' + self.escapeHtml(data) + '</code>';
                        }},
                        {
                            data: 'machine_count',
                            width: '120px',
                            render: function(data) {
                                const badgeClass = data > 0 ? 'sm-machine-count' : 'sm-machine-count zero';
                                return '<span class="' + badgeClass + '">' + data + ' machines</span>';
                            }
                        },
                        { data: 'sort_order', width: '100px' },
                        {
                            data: 'is_active',
                            width: '100px',
                            render: function(data) {
                                if (data == 1) {
                                    return '<span class="sm-status-badge active">' + wpStateMachineGroupsData.i18n.active + '</span>';
                                } else {
                                    return '<span class="sm-status-badge inactive">' + wpStateMachineGroupsData.i18n.inactive + '</span>';
                                }
                            }
                        },
                        {
                            data: null,
                            width: '150px',
                            orderable: false,
                            render: function(data, type, row) {
                                return '<div class="sm-action-buttons">' +
                                    '<button class="button button-small btn-view" data-id="' + row.id + '" title="View Details">' +
                                    '<span class="dashicons dashicons-visibility"></span></button>' +
                                    '<button class="button button-small btn-edit" data-id="' + row.id + '" title="Edit">' +
                                    '<span class="dashicons dashicons-edit"></span></button>' +
                                    '<button class="button button-small btn-delete" data-id="' + row.id + '" title="Delete">' +
                                    '<span class="dashicons dashicons-trash"></span></button>' +
                                    '</div>';
                            }
                        }
                    ],
                    order: [[5, 'asc'], [2, 'asc']], // Sort by sort_order, then name
                    pageLength: 25,
                    lengthMenu: [[10, 25, 50, 100], [10, 25, 50, 100]],
                    language: wpStateMachineGroupsData.i18n.dataTable
                });
            },

            /**
             * Bind event handlers
             */
            bindEvents: function() {
                const self = this;

                // Add new group
                $('#btn-add-group').on('click', function() {
                    self.showAddModal();
                });

                // Edit group (delegated)
                $('#workflow-groups-table').on('click', '.btn-edit', function() {
                    const id = $(this).data('id');
                    self.showEditModal(id);
                });

                // View group (delegated)
                $('#workflow-groups-table').on('click', '.btn-view', function() {
                    const id = $(this).data('id');
                    self.showViewModal(id);
                });

                // Delete group (delegated)
                $('#workflow-groups-table').on('click', '.btn-delete', function() {
                    const id = $(this).data('id');
                    self.deleteGroup(id);
                });

                // Form submit
                $('#group-form').on('submit', function(e) {
                    e.preventDefault();
                    self.saveGroup();
                });

                // Cancel buttons
                $('#btn-cancel, .sm-modal-close').on('click', function() {
                    self.closeModals();
                });

                // Close view modal
                $('#btn-close-view').on('click', function() {
                    $('#view-group-modal').hide();
                });

                // Auto-generate slug from name
                $('#group-name').on('input', function() {
                    if (!$('#group-id').val()) { // Only for new groups
                        const slug = self.generateSlug($(this).val());
                        $('#group-slug').val(slug);
                    }
                });
            },

            /**
             * Show add modal
             */
            showAddModal: function() {
                $('#modal-title').text('Add Workflow Group');
                $('#group-form')[0].reset();
                $('#group-id').val('');
                $('#group-icon').val('dashicons-networking');
                $('#group-is-active').prop('checked', true);
                $('.error-message').hide();
                $('#group-modal').show();
            },

            /**
             * Show edit modal
             */
            showEditModal: function(id) {
                const self = this;

                $.ajax({
                    url: this.ajaxUrl,
                    type: 'POST',
                    data: {
                        action: 'show_workflow_group',
                        nonce: this.nonce,
                        id: id
                    },
                    success: function(response) {
                        if (response.success) {
                            const group = response.data.group;
                            $('#modal-title').text('Edit Workflow Group');
                            $('#group-id').val(group.id);
                            $('#group-name').val(group.name);
                            $('#group-slug').val(group.slug);
                            $('#group-description').val(group.description);
                            $('#group-icon').val(group.icon);
                            $('#group-sort-order').val(group.sort_order);
                            $('#group-is-active').prop('checked', group.is_active == 1);
                            $('.error-message').hide();
                            $('#group-modal').show();
                        } else {
                            alert(response.data.message);
                        }
                    },
                    error: function() {
                        alert('Failed to load group data.');
                    }
                });
            },

            /**
             * Show view modal
             */
            showViewModal: function(id) {
                const self = this;

                $.ajax({
                    url: this.ajaxUrl,
                    type: 'POST',
                    data: {
                        action: 'show_workflow_group',
                        nonce: this.nonce,
                        id: id
                    },
                    success: function(response) {
                        if (response.success) {
                            const group = response.data.group;
                            const machines = response.data.machines;

                            $('#view-id').text(group.id);
                            $('#view-icon-preview').attr('class', 'dashicons ' + group.icon);
                            $('#view-icon').text(group.icon);
                            $('#view-name').text(group.name);
                            $('#view-slug').text(group.slug);
                            $('#view-description').text(group.description || '-');
                            $('#view-sort-order').text(group.sort_order);
                            $('#view-status').html(group.is_active == 1 ?
                                '<span class="sm-status-badge active">' + wpStateMachineGroupsData.i18n.active + '</span>' :
                                '<span class="sm-status-badge inactive">' + wpStateMachineGroupsData.i18n.inactive + '</span>'
                            );
                            $('#view-created').text(group.created_at);
                            $('#view-updated').text(group.updated_at);

                            // Machines list
                            let machinesHtml = '';
                            if (machines && machines.length > 0) {
                                machinesHtml = '<ul>';
                                machines.forEach(function(machine) {
                                    machinesHtml += '<li><strong>' + self.escapeHtml(machine.name) + '</strong> (' + self.escapeHtml(machine.slug) + ')</li>';
                                });
                                machinesHtml += '</ul>';
                            } else {
                                machinesHtml = '<em>' + wpStateMachineGroupsData.i18n.noMachines + '</em>';
                            }
                            $('#view-machines').html(machinesHtml);

                            $('#view-group-modal').show();
                        } else {
                            alert(response.data.message);
                        }
                    },
                    error: function() {
                        alert('Failed to load group data.');
                    }
                });
            },

            /**
             * Save group (create or update)
             */
            saveGroup: function() {
                const self = this;
                const id = $('#group-id').val();
                const action = id ? 'update_workflow_group' : 'create_workflow_group';

                // Clear previous errors
                $('.error-message').hide();

                $.ajax({
                    url: this.ajaxUrl,
                    type: 'POST',
                    data: {
                        action: action,
                        nonce: this.nonce,
                        id: id,
                        name: $('#group-name').val(),
                        slug: $('#group-slug').val(),
                        description: $('#group-description').val(),
                        icon: $('#group-icon').val(),
                        sort_order: $('#group-sort-order').val(),
                        is_active: $('#group-is-active').is(':checked') ? 1 : 0
                    },
                    success: function(response) {
                        if (response.success) {
                            self.closeModals();
                            self.groupsTable.ajax.reload();
                            alert(response.data.message);
                        } else {
                            // Show validation errors
                            if (response.data.errors) {
                                $.each(response.data.errors, function(field, message) {
                                    $('#error-' + field).text(message).show();
                                });
                            } else {
                                alert(response.data.message);
                            }
                        }
                    },
                    error: function() {
                        alert('An error occurred while saving the group.');
                    }
                });
            },

            /**
             * Delete group
             */
            deleteGroup: function(id) {
                const self = this;

                if (!confirm(wpStateMachineGroupsData.i18n.confirmDelete)) {
                    return;
                }

                $.ajax({
                    url: this.ajaxUrl,
                    type: 'POST',
                    data: {
                        action: 'delete_workflow_group',
                        nonce: this.nonce,
                        id: id
                    },
                    success: function(response) {
                        if (response.success) {
                            self.groupsTable.ajax.reload();
                            alert(response.data.message);
                        } else {
                            alert(response.data.message || wpStateMachineGroupsData.i18n.deleteError);
                        }
                    },
                    error: function() {
                        alert('An error occurred while deleting the group.');
                    }
                });
            },

            /**
             * Close all modals
             */
            closeModals: function() {
                $('#group-modal').hide();
                $('#view-group-modal').hide();
                $('#group-form')[0].reset();
                $('.error-message').hide();
            },

            /**
             * Generate slug from text
             */
            generateSlug: function(text) {
                return text
                    .toLowerCase()
                    .replace(/[^a-z0-9]+/g, '-')
                    .replace(/(^-|-$)/g, '');
            },

            /**
             * Escape HTML to prevent XSS
             */
            escapeHtml: function(text) {
                if (!text) return '';
                const map = {
                    '&': '&amp;',
                    '<': '&lt;',
                    '>': '&gt;',
                    '"': '&quot;',
                    "'": '&#039;'
                };
                return text.toString().replace(/[&<>"']/g, function(m) { return map[m]; });
            }
        };

        // Initialize
        GroupsAdmin.init();
    });

})(jQuery);
