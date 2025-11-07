/**
 * Transition Logs Admin JavaScript
 *
 * @package     WP_State_Machine
 * @version     1.0.0
 * @author      arisciwek
 *
 * Path: /assets/js/transition-logs.js
 *
 * Description: JavaScript for transition logs admin interface.
 *              Handles DataTable, filtering, and CSV export.
 *              Separated from view file for clean architecture.
 *
 * Dependencies:
 * - jQuery
 * - DataTables
 *
 * Changelog:
 * 1.0.0 - 2025-11-07 (TODO-6104)
 * - Initial creation
 * - Extracted from inline script
 * - DataTable initialization
 * - Filter handling
 * - Export functionality
 */

(function($) {
    'use strict';

    // Wait for DOM ready
    $(document).ready(function() {
        const LogsAdmin = {
            nonce: wpStateMachineLogsData.nonce,
            logsTable: null,

            /**
             * Initialize the logs admin interface
             */
            init: function() {
                this.loadPlugins();
                this.initDataTable();
                this.bindEvents();
            },

            /**
             * Initialize DataTable
             */
            initDataTable: function() {
                const self = this;

                if ($.fn.DataTable.isDataTable('#logs-table')) {
                    $('#logs-table').DataTable().destroy();
                }

                this.logsTable = $('#logs-table').DataTable({
                    processing: true,
                    serverSide: true,
                    ajax: {
                        url: ajaxurl,
                        type: 'POST',
                        data: function(d) {
                            d.action = 'sm_logs_datatable';
                            d.nonce = self.nonce;
                            d.plugin_slug = $('#filter-plugin').val();
                            d.machine_id = $('#filter-machine').val();
                            d.date_from = $('#filter-date-from').val();
                            d.date_to = $('#filter-date-to').val();
                        },
                        error: function(xhr, error, thrown) {
                            console.error('DataTable error:', error, thrown);
                            alert(wpStateMachineLogsData.i18n.loadError);
                        }
                    },
                    columns: [
                        {
                            data: 'id',
                            width: '60px'
                        },
                        {
                            data: 'created_at',
                            width: '150px',
                            render: function(data) {
                                const date = new Date(data);
                                return date.toLocaleString();
                            }
                        },
                        {
                            data: 'machine_name',
                            render: function(data, type, row) {
                                return '<div class="sm-machine-info">' +
                                       '<strong>' + self.escapeHtml(data) + '</strong><br>' +
                                       '<small style="color: #666;">' + self.escapeHtml(row.machine_slug) + '</small>' +
                                       '</div>';
                            }
                        },
                        {
                            data: 'entity_type',
                            render: function(data, type, row) {
                                return '<div class="sm-entity-info">' +
                                       '<span class="sm-entity-type">' + self.escapeHtml(data) + '</span>' +
                                       '<span class="sm-entity-id">#' + row.entity_id + '</span>' +
                                       '</div>';
                            }
                        },
                        {
                            data: 'from_state_name',
                            render: function(data, type, row) {
                                if (!data) {
                                    return '<span class="sm-state-badge" style="background: #999;">Initial</span>';
                                }
                                const color = row.from_state_color || '#999';
                                return '<span class="sm-state-badge" style="background: ' + color + ';">' + self.escapeHtml(data) + '</span>';
                            }
                        },
                        {
                            data: null,
                            width: '30px',
                            orderable: false,
                            searchable: false,
                            className: 'text-center',
                            render: function() {
                                return '→';
                            }
                        },
                        {
                            data: 'to_state_name',
                            render: function(data, type, row) {
                                const color = row.to_state_color || '#2271b1';
                                return '<span class="sm-state-badge" style="background: ' + color + ';">' + self.escapeHtml(data) + '</span>';
                            }
                        },
                        {
                            data: 'user_name',
                            render: function(data) {
                                return '<div class="sm-user-info">' + self.escapeHtml(data) + '</div>';
                            }
                        },
                        {
                            data: 'comment',
                            render: function(data) {
                                if (!data) return '-';
                                return '<div class="sm-comment" title="' + self.escapeHtml(data) + '">' + self.escapeHtml(data) + '</div>';
                            }
                        }
                    ],
                    order: [[1, 'desc']], // Sort by date descending
                    pageLength: 25,
                    lengthMenu: [[10, 25, 50, 100], [10, 25, 50, 100]],
                    language: wpStateMachineLogsData.i18n.dataTable
                });

                this.updateFilterStatus();
            },

            /**
             * Load plugins for dropdown
             */
            loadPlugins: function() {
                const self = this;

                $.ajax({
                    url: ajaxurl,
                    type: 'POST',
                    data: {
                        action: 'sm_logs_get_plugins',
                        nonce: self.nonce
                    },
                    success: function(response) {
                        if (response.success && response.data) {
                            const $select = $('#filter-plugin');
                            $select.find('option:not(:first)').remove();

                            response.data.forEach(function(plugin) {
                                $select.append(
                                    $('<option>')
                                        .val(plugin.plugin_slug)
                                        .text(plugin.plugin_slug + ' (' + plugin.machine_count + ' machines)')
                                );
                            });
                        }
                    },
                    error: function(xhr, status, error) {
                        console.error('Failed to load plugins:', error);
                    }
                });
            },

            /**
             * Bind event handlers
             */
            bindEvents: function() {
                const self = this;

                // Apply filters
                $('#btn-apply-filters').on('click', function() {
                    self.logsTable.ajax.reload();
                    self.updateFilterStatus();
                });

                // Reset filters
                $('#btn-reset-filters').on('click', function() {
                    $('#filter-plugin').val('all');
                    $('#filter-machine').val('');
                    $('#filter-date-from').val('');
                    $('#filter-date-to').val('');
                    self.logsTable.ajax.reload();
                    self.updateFilterStatus();
                });

                // Export CSV
                $('#btn-export-csv').on('click', function() {
                    self.exportCSV();
                });

                // Enter key on date fields
                $('#filter-date-from, #filter-date-to').on('keypress', function(e) {
                    if (e.which === 13) {
                        $('#btn-apply-filters').click();
                    }
                });
            },

            /**
             * Export logs to CSV
             */
            exportCSV: function() {
                const params = new URLSearchParams({
                    action: 'sm_logs_export',
                    nonce: this.nonce,
                    plugin_slug: $('#filter-plugin').val(),
                    machine_id: $('#filter-machine').val(),
                    date_from: $('#filter-date-from').val(),
                    date_to: $('#filter-date-to').val()
                });

                window.location.href = ajaxurl + '?' + params.toString();
            },

            /**
             * Update filter status text
             */
            updateFilterStatus: function() {
                const plugin = $('#filter-plugin').val();
                const machine = $('#filter-machine').val();
                const dateFrom = $('#filter-date-from').val();
                const dateTo = $('#filter-date-to').val();

                const filters = [];
                if (plugin && plugin !== 'all') filters.push('Plugin: ' + plugin);
                if (machine) filters.push('Machine: ' + $('#filter-machine option:selected').text());
                if (dateFrom) filters.push('From: ' + dateFrom);
                if (dateTo) filters.push('To: ' + dateTo);

                if (filters.length > 0) {
                    $('#filter-status').text('Active filters: ' + filters.join(' | '));
                } else {
                    $('#filter-status').text('No filters applied');
                }
            },

            /**
             * Escape HTML to prevent XSS
             * @param {string} text Text to escape
             * @return {string} Escaped text
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
        LogsAdmin.init();
    });

})(jQuery);
