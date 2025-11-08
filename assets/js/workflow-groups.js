/**
 * Workflow Groups Admin JavaScript
 *
 * @package     WP_State_Machine
 * @subpackage  Assets/JS
 * @version     1.0.1
 * @author      arisciwek
 *
 * Path: /wp-state-machine/assets/js/workflow-groups.js
 *
 * Description: Handles DataTable initialization, CRUD operations,
 *              modal interactions, and AJAX requests for workflow groups.
 *              Follows machines/states/transitions pattern.
 *
 * Dependencies:
 * - jQuery
 * - DataTables
 * - wpStateMachineWorkflowGroupsData (localized script)
 *
 * Changelog:
 * 1.0.1 - 2025-11-08
 * - Updated to follow machines/states/transitions pattern
 * - Fixed initDataTable method
 * - Added auto-slug generation with input blur
 * - Added has-value class for readonly slug
 * - Added icon preview real-time update
 * - Silent delete success
 *
 * 1.0.0 - 2025-11-07
 * - Initial creation
 */

(function($) {
    'use strict';

    const WorkflowGroupsAdmin = {
        /**
         * DataTable instance
         */
        table: null,

        /**
         * Localized data from PHP
         */
        data: wpStateMachineWorkflowGroupsData,

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
            if ($.fn.DataTable && $.fn.DataTable.isDataTable('#workflow-groups-table')) {
                $('#workflow-groups-table').DataTable().destroy();
            }

            this.table = $('#workflow-groups-table').DataTable({
                processing: true,
                serverSide: true,
                ajax: {
                    url: self.data.ajaxUrl,
                    type: 'POST',
                    data: function(d) {
                        d.action = 'handle_workflow_group_datatable';
                        d.nonce = self.data.nonce;
                    }
                },
                columns: [
                    { data: 'id' },
                    {
                        data: 'icon',
                        orderable: false,
                        render: function(data, type, row) {
                            if (data) {
                                return '<span class="dashicons ' + data + '"></span>';
                            }
                            return '<span class="dashicons dashicons-networking"></span>';
                        }
                    },
                    { data: 'name' },
                    {
                        data: 'slug',
                        render: function(data, type, row) {
                            return '<code>' + data + '</code>';
                        }
                    },
                    {
                        data: 'machine_count',
                        render: function(data, type, row) {
                            const badgeClass = data > 0 ? 'machine-count-badge' : 'machine-count-badge zero';
                            return '<span class="' + badgeClass + '">' + data + '</span>';
                        }
                    },
                    { data: 'sort_order' },
                    {
                        data: 'is_active',
                        render: function(data, type, row) {
                            if (data == 1) {
                                return '<span class="status-badge active">' + self.data.i18n.active + '</span>';
                            }
                            return '<span class="status-badge inactive">' + self.data.i18n.inactive + '</span>';
                        }
                    },
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

            // Add new group
            $('#btn-add-group').on('click', function() {
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
            $('#group-name').on('input blur', function() {
                // Only auto-generate if in create mode (no ID)
                if (!$('#group-id').val()) {
                    const slug = self.generateSlug($(this).val());
                    $('#group-slug').val(slug);
                }
            });

            // Icon preview real-time update
            $('#group-icon').on('input blur', function() {
                const iconClass = $(this).val() || 'dashicons-networking';
                $('#icon-preview-display').attr('class', 'dashicons ' + iconClass);
            });

            // Save group
            $('#btn-save-group').on('click', function() {
                self.saveGroup();
            });

            // View group
            $(document).on('click', '.btn-view-group', function() {
                self.viewGroup($(this).data('id'));
            });

            // Edit group
            $(document).on('click', '.btn-edit-group', function() {
                self.editGroup($(this).data('id'));
            });

            // Delete group
            $(document).on('click', '.btn-delete-group', function() {
                self.deleteGroup($(this).data('id'));
            });
        },

        /**
         * Open create modal
         */
        openCreateModal: function() {
            $('#group-form')[0].reset();
            $('#group-id').val('');
            $('#group-slug').removeClass('has-value');
            $('#icon-preview-display').attr('class', 'dashicons dashicons-networking');
            $('#modal-title').text(this.data.i18n.addTitle);
            $('#group-modal').fadeIn();
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
         * Save group (create or update)
         */
        saveGroup: function() {
            const self = this;
            const groupId = $('#group-id').val();
            const action = groupId ? 'update_workflow_group' : 'create_workflow_group';

            const formData = {
                action: action,
                nonce: self.data.nonce,
                id: groupId,
                name: $('#group-name').val(),
                slug: $('#group-slug').val(),
                description: $('#group-description').val(),
                icon: $('#group-icon').val(),
                sort_order: $('#group-sort-order').val(),
                is_active: $('#group-is-active').prop('checked') ? 1 : 0
            };

            $.post(self.data.ajaxUrl, formData)
                .done(function(response) {
                    if (response.success) {
                        // Close modal and reload table
                        $('#group-modal').fadeOut(200);
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
         * View group details
         */
        viewGroup: function(id) {
            const self = this;

            console.log('View group clicked, ID:', id);

            $.post(self.data.ajaxUrl, {
                action: 'show_workflow_group',
                nonce: self.data.nonce,
                id: id
            }, function(response) {
                console.log('View response:', response);

                if (response.success) {
                    const group = response.data.data;
                    console.log('Group data for view:', group);

                    $('#view-group-id').text(group.id);
                    $('#view-group-name').text(group.name);
                    $('#view-group-slug').text(group.slug);
                    $('#view-group-description').text(group.description || '-');
                    $('#view-group-icon').text(group.icon || 'dashicons-networking');
                    $('#view-group-icon-preview').attr('class', 'dashicons ' + (group.icon || 'dashicons-networking'));
                    $('#view-group-sort-order').text(group.sort_order);
                    $('#view-group-status').html(
                        group.is_active == 1 ?
                            '<span class="dashicons dashicons-yes" style="color:green;"></span> ' + self.data.i18n.active :
                            '<span class="dashicons dashicons-no" style="color:red;"></span> ' + self.data.i18n.inactive
                    );
                    $('#view-group-machines').text(group.machine_count || '0');
                    $('#view-group-created').text(group.created_at);
                    $('#view-group-updated').text(group.updated_at);
                    $('#view-group-modal').fadeIn();
                } else {
                    console.error('View failed:', response.data);
                    alert('Error: ' + (response.data.message || 'Failed to load group'));
                }
            }).fail(function(xhr, status, error) {
                console.error('AJAX Error:', status, error, xhr);
                alert('AJAX error: ' + error);
            });
        },

        /**
         * Edit group
         */
        editGroup: function(id) {
            const self = this;

            console.log('Edit group clicked, ID:', id);

            $.post(self.data.ajaxUrl, {
                action: 'show_workflow_group',
                nonce: self.data.nonce,
                id: id
            }, function(response) {
                console.log('Edit response:', response);

                if (response.success) {
                    const group = response.data.data;
                    console.log('Group data:', group);

                    $('#group-id').val(group.id);
                    $('#group-name').val(group.name);
                    $('#group-slug').val(group.slug).addClass('has-value');
                    $('#group-description').val(group.description || '');
                    $('#group-icon').val(group.icon || 'dashicons-networking');
                    $('#icon-preview-display').attr('class', 'dashicons ' + (group.icon || 'dashicons-networking'));
                    $('#group-sort-order').val(group.sort_order);
                    $('#group-is-active').prop('checked', group.is_active == 1);
                    $('#modal-title').text(self.data.i18n.editTitle);
                    $('#group-modal').fadeIn();
                } else {
                    console.error('Edit failed:', response.data);
                    alert('Error: ' + (response.data.message || 'Failed to load group'));
                }
            }).fail(function(xhr, status, error) {
                console.error('AJAX Error:', status, error, xhr);
                alert('AJAX error: ' + error);
            });
        },

        /**
         * Delete group
         */
        deleteGroup: function(id) {
            const self = this;

            if (!confirm(self.data.i18n.confirmDelete)) {
                return;
            }

            $.post(self.data.ajaxUrl, {
                action: 'delete_workflow_group',
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

        if (typeof wpStateMachineWorkflowGroupsData === 'undefined') {
            console.error('Workflow groups data not localized');
            return;
        }

        WorkflowGroupsAdmin.init();
    });

})(jQuery);
