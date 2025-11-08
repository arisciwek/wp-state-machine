/**
 * Settings Page JavaScript
 *
 * @package     WP_State_Machine
 * @subpackage  Assets/JS
 * @version     1.0.0
 * @author      arisciwek
 *
 * Path: /wp-state-machine/assets/js/settings.js
 *
 * Description: Handles tab switching, form submission, AJAX requests,
 *              dan UI interactions untuk settings page.
 *              Clean separation dengan XSS protection.
 *
 * Dependencies:
 * - jQuery
 * - wpStateMachineSettingsData (localized script)
 *
 * Changelog:
 * 1.0.0 - 2025-11-07
 * - Initial creation
 * - Tab switching functionality
 * - AJAX form submission
 * - Cache clearing
 * - Log cleanup
 * - Database statistics
 * - Toast notifications
 */

(function($) {
    'use strict';

    const SettingsAdmin = {
        /**
         * Localized data from PHP
         */
        nonce: wpStateMachineSettingsData.nonce,
        ajaxUrl: wpStateMachineSettingsData.ajaxUrl,
        i18n: wpStateMachineSettingsData.i18n,

        /**
         * Initialize settings page
         */
        init: function() {
            this.bindEvents();
            this.initTabSwitching();
            this.initResetButtonState();
            this.loadWorkflows(); // Load workflows on init
        },

        /**
         * Bind event handlers
         */
        bindEvents: function() {
            const self = this;

            // Form submission
            $('#settings-form').on('submit', function(e) {
                e.preventDefault();
                self.saveSettings($(this));
            });

            // Toggle development mode warning (both checkboxes must be checked)
            $('#enable_development, #clear_data_on_deactivate').on('change', function() {
                const devMode = $('#enable_development').is(':checked');
                const clearData = $('#clear_data_on_deactivate').is(':checked');
                const warning = $('#dev-mode-warning');
                const resetNotice = $('#reset-dev-mode-notice');
                const resetBtn = $('#btn-reset-all-workflows');

                // Toggle deactivation warning
                if (devMode && clearData) {
                    warning.slideDown(300);
                } else {
                    warning.slideUp(300);
                }

                // Toggle reset notice and button state for bulk actions
                // Note: Individual reset buttons in cards are controlled by DATABASE value, not checkbox
                if (devMode) {
                    resetNotice.slideUp(300);
                    resetBtn.prop('disabled', false).css('opacity', '1');
                } else {
                    resetNotice.slideDown(300);
                    resetBtn.prop('disabled', true).css('opacity', '0.5');
                }

                // Don't reload cards here - they will reload after settings are SAVED
                // This prevents confusion where checkbox is ON but buttons don't appear (because DB is still OFF)
            });

            // Clear cache button
            $('#btn-clear-cache').on('click', function(e) {
                e.preventDefault();
                self.clearCache($(this));
            });

            // Cleanup logs button
            $('#btn-cleanup-logs').on('click', function(e) {
                e.preventDefault();
                self.cleanupLogs($(this));
            });

            // Load statistics button
            $('#btn-load-stats').on('click', function(e) {
                e.preventDefault();
                self.loadStats($(this));
            });

            // Seed all workflows button (bulk action)
            $('#btn-seed-all-workflows').on('click', function(e) {
                e.preventDefault();
                self.seedAllWorkflows($(this));
            });

            // Reset all workflows button (bulk action)
            $('#btn-reset-all-workflows').on('click', function(e) {
                e.preventDefault();
                self.resetAllWorkflows($(this));
            });

            // Individual workflow seed button (event delegation)
            $(document).on('click', '.seed-workflow', function(e) {
                e.preventDefault();
                const slug = $(this).data('slug');
                const filename = $(this).data('filename');
                self.seedIndividualWorkflow(slug, filename, $(this));
            });

            // Individual workflow reset button (event delegation)
            $(document).on('click', '.reset-workflow', function(e) {
                e.preventDefault();
                const slug = $(this).data('slug');
                self.resetIndividualWorkflow(slug, $(this));
            });
        },

        /**
         * Initialize tab switching
         */
        initTabSwitching: function() {
            const self = this;

            $('.sm-settings-tabs .nav-tab').on('click', function(e) {
                e.preventDefault();

                const tabId = $(this).data('tab');

                // Update tab navigation
                $('.sm-settings-tabs .nav-tab').removeClass('nav-tab-active');
                $(this).addClass('nav-tab-active');

                // Update tab content
                $('.sm-settings-tab').removeClass('active');
                $('#' + self.escapeHtml(tabId)).addClass('active');

                // Update hidden field
                $('#active-tab').val(tabId);
            });
        },

        /**
         * Initialize reset button state based on dev mode
         */
        initResetButtonState: function() {
            const devMode = $('#enable_development').is(':checked');
            const resetNotice = $('#reset-dev-mode-notice');
            const resetBtn = $('#btn-reset-all-workflows');

            if (devMode) {
                resetNotice.hide();
                resetBtn.prop('disabled', false).css('opacity', '1');
            } else {
                resetNotice.show();
                resetBtn.prop('disabled', true).css('opacity', '0.5');
            }
        },

        /**
         * Save settings via AJAX
         */
        saveSettings: function($form) {
            const self = this;
            const $submitBtn = $form.find('.sm-save-settings');
            const activeTab = $('#active-tab').val();

            // Get form data
            const formData = new FormData($form[0]);
            formData.append('action', 'save_state_machine_settings');
            formData.append('tab', activeTab);

            // Show loading
            $submitBtn.addClass('loading').prop('disabled', true);

            $.ajax({
                url: self.ajaxUrl,
                type: 'POST',
                data: formData,
                processData: false,
                contentType: false,
                success: function(response) {
                    if (response.success) {
                        self.showToast(response.data.message, 'success');

                        // Reload workflow cards if on Database tab (to update reset buttons based on new dev mode setting)
                        if (activeTab === 'database' && $('#workflows-grid').length > 0) {
                            self.loadWorkflows();
                        }
                    } else {
                        self.showToast(response.data.message || self.i18n.error, 'error');
                    }
                },
                error: function(xhr, status, error) {
                    self.showToast(self.i18n.error, 'error');
                },
                complete: function() {
                    $submitBtn.removeClass('loading').prop('disabled', false);
                }
            });
        },

        /**
         * Clear all cache
         */
        clearCache: function($button) {
            const self = this;

            if (!confirm(self.i18n.confirmClearCache)) {
                return;
            }

            // Show loading
            $button.addClass('loading').prop('disabled', true);

            $.ajax({
                url: self.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'clear_all_cache',
                    nonce: self.nonce
                },
                success: function(response) {
                    if (response.success) {
                        self.showToast(response.data.message, 'success');
                    } else {
                        self.showToast(response.data.message || self.i18n.error, 'error');
                    }
                },
                error: function(xhr, status, error) {
                    console.error('Cache clear error:', error);
                    self.showToast(self.i18n.error, 'error');
                },
                complete: function() {
                    $button.removeClass('loading').prop('disabled', false);
                }
            });
        },

        /**
         * Cleanup old logs
         */
        cleanupLogs: function($button) {
            const self = this;

            if (!confirm(self.i18n.confirmCleanupLogs)) {
                return;
            }

            // Show loading
            $button.addClass('loading').prop('disabled', true);

            $.ajax({
                url: self.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'cleanup_old_logs',
                    nonce: self.nonce
                },
                success: function(response) {
                    if (response.success) {
                        const message = response.data.message + ' (' + response.data.deleted_count + ' ' + self.i18n.entries + ')';
                        self.showToast(message, 'success');
                    } else {
                        self.showToast(response.data.message || self.i18n.error, 'error');
                    }
                },
                error: function(xhr, status, error) {
                    console.error('Cleanup error:', error);
                    self.showToast(self.i18n.error, 'error');
                },
                complete: function() {
                    $button.removeClass('loading').prop('disabled', false);
                }
            });
        },

        /**
         * Load database statistics
         */
        loadStats: function($button) {
            const self = this;
            const $statsContainer = $('#database-stats');

            // Show loading
            $button.addClass('loading').prop('disabled', true);

            $.ajax({
                url: self.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'get_database_stats',
                    nonce: self.nonce
                },
                success: function(response) {
                    if (response.success) {
                        self.renderStats(response.data.stats, $statsContainer);
                        $statsContainer.slideDown();
                    } else {
                        self.showToast(response.data.message || self.i18n.error, 'error');
                    }
                },
                error: function(xhr, status, error) {
                    console.error('Stats load error:', error);
                    self.showToast(self.i18n.error, 'error');
                },
                complete: function() {
                    $button.removeClass('loading').prop('disabled', false);
                }
            });
        },

        /**
         * Render database statistics
         */
        renderStats: function(stats, $container) {
            const self = this;

            const html = `
                <div class="sm-stats-grid">
                    <div class="sm-stat-card">
                        <span class="stat-label">${self.escapeHtml(self.i18n.machines)}</span>
                        <span class="stat-value">${self.escapeHtml(stats.machines.toString())}</span>
                    </div>
                    <div class="sm-stat-card">
                        <span class="stat-label">${self.escapeHtml(self.i18n.states)}</span>
                        <span class="stat-value">${self.escapeHtml(stats.states.toString())}</span>
                    </div>
                    <div class="sm-stat-card">
                        <span class="stat-label">${self.escapeHtml(self.i18n.transitions)}</span>
                        <span class="stat-value">${self.escapeHtml(stats.transitions.toString())}</span>
                    </div>
                    <div class="sm-stat-card">
                        <span class="stat-label">${self.escapeHtml(self.i18n.logs)}</span>
                        <span class="stat-value">${self.escapeHtml(stats.logs.toString())}</span>
                    </div>
                    <div class="sm-stat-card">
                        <span class="stat-label">${self.escapeHtml(self.i18n.workflowGroups)}</span>
                        <span class="stat-value">${self.escapeHtml(stats.workflow_groups.toString())}</span>
                    </div>
                    <div class="sm-stat-card">
                        <span class="stat-label">${self.escapeHtml(self.i18n.databaseSize)}</span>
                        <span class="stat-value">${self.escapeHtml(stats.database_size_mb.toString())}</span>
                        <span class="stat-unit">MB</span>
                    </div>
                </div>
            `;

            $container.html(html);
        },

        /**
         * Load workflows data and render cards
         */
        loadWorkflows: function() {
            const self = this;
            const $grid = $('#workflows-grid');

            // Show loading
            $grid.html('<div style="text-align: center; padding: 40px;"><span class="spinner is-active" style="float: none;"></span><p>Loading workflows...</p></div>');

            $.ajax({
                url: self.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'get_workflows_data',
                    nonce: self.nonce
                },
                success: function(response) {
                    if (response.success) {
                        // Pass dev_mode from server (authoritative source)
                        const devMode = response.data.development_mode || false;
                        self.renderWorkflowCards(response.data.workflows, $grid, devMode);
                    } else {
                        $grid.html('<div class="workflows-empty"><span class="dashicons dashicons-warning"></span><p>Failed to load workflows: ' + self.escapeHtml(response.data.message || 'Unknown error') + '</p></div>');
                    }
                },
                error: function(xhr, status, error) {
                    console.error('Load workflows error:', error);
                    $grid.html('<div class="workflows-empty"><span class="dashicons dashicons-warning"></span><p>Error loading workflows. Please refresh the page.</p></div>');
                }
            });
        },

        /**
         * Render workflow cards
         */
        renderWorkflowCards: function(workflows, $container, devModeFromServer) {
            const self = this;

            if (!workflows || workflows.length === 0) {
                $container.html('<div class="workflows-empty"><span class="dashicons dashicons-admin-generic"></span><p>No workflow templates found.</p></div>');
                return;
            }

            // Use server value if provided, otherwise fallback to checkbox (but server value is authoritative)
            const devMode = typeof devModeFromServer !== 'undefined' ? devModeFromServer : false;
            let html = '';

            workflows.forEach(function(workflow) {
                const isSeeded = workflow.is_seeded;
                const statusBadge = isSeeded ?
                    '<span class="workflow-status-badge seeded">Seeded ✓</span>' :
                    '<span class="workflow-status-badge not-seeded">Not Seeded</span>';

                const seedButton = isSeeded ?
                    '<button type="button" class="button seed-workflow" data-slug="' + self.escapeHtml(workflow.slug) + '" data-filename="' + self.escapeHtml(workflow.filename) + '"><span class="dashicons dashicons-update"></span> Re-seed Workflow</button>' :
                    '<button type="button" class="button seed-workflow" data-slug="' + self.escapeHtml(workflow.slug) + '" data-filename="' + self.escapeHtml(workflow.filename) + '"><span class="dashicons dashicons-download"></span> Seed Workflow</button>';

                const resetButton = isSeeded && devMode ?
                    '<button type="button" class="button reset-workflow" data-slug="' + self.escapeHtml(workflow.slug) + '"><span class="dashicons dashicons-trash"></span> Reset Workflow</button>' : '';

                html += `
                    <div class="workflow-card" data-slug="${self.escapeHtml(workflow.slug)}">
                        <div class="workflow-card-header">
                            ${statusBadge}
                            <h4>${self.escapeHtml(workflow.name)}</h4>
                            <div class="workflow-slug">${self.escapeHtml(workflow.slug)}</div>
                        </div>
                        <div class="workflow-card-body">
                            <p class="description">${self.escapeHtml(workflow.description || 'Workflow template definition.')}</p>
                            <div class="workflow-stats">
                                <div class="workflow-stat">
                                    <span class="stat-label">States</span>
                                    <span class="stat-value">${self.escapeHtml((workflow.states_count || 0).toString())}</span>
                                </div>
                                <div class="workflow-stat">
                                    <span class="stat-label">Transitions</span>
                                    <span class="stat-value">${self.escapeHtml((workflow.transitions_count || 0).toString())}</span>
                                </div>
                            </div>
                        </div>
                        <div class="workflow-card-footer">
                            ${seedButton}
                            ${resetButton}
                        </div>
                    </div>
                `;
            });

            $container.html(html);
        },

        /**
         * Seed individual workflow
         */
        seedIndividualWorkflow: function(slug, filename, $button) {
            const self = this;
            const $card = $button.closest('.workflow-card');

            if (!confirm('Are you sure you want to seed this workflow? If it already exists, it will be updated with the latest YML data.')) {
                return;
            }

            // Show loading
            $button.addClass('loading').prop('disabled', true);
            $card.addClass('loading');

            $.ajax({
                url: self.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'seed_individual_workflow',
                    nonce: self.nonce,
                    filename: filename
                },
                success: function(response) {
                    if (response.success) {
                        self.showToast(response.data.message || 'Workflow seeded successfully', 'success');
                        // Reload workflows to update UI
                        self.loadWorkflows();
                    } else {
                        self.showToast(response.data.message || self.i18n.error, 'error');
                        $button.removeClass('loading').prop('disabled', false);
                        $card.removeClass('loading');
                    }
                },
                error: function(xhr, status, error) {
                    console.error('Seed individual workflow error:', error);
                    self.showToast(self.i18n.error, 'error');
                    $button.removeClass('loading').prop('disabled', false);
                    $card.removeClass('loading');
                }
            });
        },

        /**
         * Reset individual workflow
         */
        resetIndividualWorkflow: function(slug, $button) {
            const self = this;
            const $card = $button.closest('.workflow-card');

            // Double check - this should not happen if cards rendered correctly
            const devMode = $('#enable_development').is(':checked');
            if (!devMode) {
                self.showToast('Development Mode must be enabled. Please enable it and save settings first.', 'error');
                return;
            }

            const confirmMessage = 'WARNING: This will DELETE this workflow and re-seed it from the YML file.\n\n' +
                                 'Make sure you have saved Development Mode settings first.\n\n' +
                                 'Are you sure you want to continue?';

            if (!confirm(confirmMessage)) {
                return;
            }

            // Show loading
            $button.addClass('loading').prop('disabled', true);
            $card.addClass('loading');

            $.ajax({
                url: self.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'reset_individual_workflow',
                    nonce: self.nonce,
                    slug: slug
                },
                success: function(response) {
                    if (response.success) {
                        self.showToast(response.data.message || 'Workflow reset successfully', 'success');
                        // Reload workflows to update UI
                        self.loadWorkflows();
                    } else {
                        self.showToast(response.data.message || self.i18n.error, 'error');
                        $button.removeClass('loading').prop('disabled', false);
                        $card.removeClass('loading');
                    }
                },
                error: function(xhr, status, error) {
                    console.error('Reset individual workflow error:', error);
                    self.showToast(self.i18n.error, 'error');
                    $button.removeClass('loading').prop('disabled', false);
                    $card.removeClass('loading');
                }
            });
        },

        /**
         * Seed all workflows from YML files (bulk action)
         */
        seedAllWorkflows: function($button) {
            const self = this;

            if (!confirm('Are you sure you want to seed all workflows? This will import all workflow templates from YML files.')) {
                return;
            }

            // Show loading
            $button.addClass('loading').prop('disabled', true);

            $.ajax({
                url: self.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'seed_default_workflows',
                    nonce: self.nonce
                },
                success: function(response) {
                    if (response.success) {
                        let message = response.data.message;
                        if (response.data.details && response.data.details.summary) {
                            const summary = response.data.details.summary;
                            message += ` (${summary.success} succeeded, ${summary.errors} failed)`;
                        }
                        self.showToast(message, 'success');
                        // Reload workflows to update UI
                        self.loadWorkflows();
                    } else {
                        self.showToast(response.data.message || self.i18n.error, 'error');
                    }
                },
                error: function(xhr, status, error) {
                    console.error('Seed workflows error:', error);
                    self.showToast(self.i18n.error, 'error');
                },
                complete: function() {
                    $button.removeClass('loading').prop('disabled', false);
                }
            });
        },

        /**
         * Reset all default workflows (bulk action)
         */
        resetAllWorkflows: function($button) {
            const self = this;

            // Check if development mode is enabled
            const devMode = $('#enable_development').is(':checked');

            if (!devMode) {
                self.showToast('Development Mode must be enabled to reset workflows', 'error');
                return;
            }

            const confirmMessage = 'WARNING: This will DELETE all default workflows (is_default=1) and re-seed from YML files.\n\n' +
                                 'Custom workflows will be preserved.\n\n' +
                                 'Are you sure you want to continue?';

            if (!confirm(confirmMessage)) {
                return;
            }

            // Show loading
            $button.addClass('loading').prop('disabled', true);

            $.ajax({
                url: self.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'reset_to_default_workflows',
                    nonce: self.nonce,
                    create_backup: true  // Always create backup before reset
                },
                success: function(response) {
                    if (response.success) {
                        let message = 'Workflows reset to defaults successfully';
                        if (response.data.details && response.data.details.seed_result) {
                            const summary = response.data.details.seed_result.summary;
                            if (summary) {
                                message += ` (${summary.success} workflows seeded)`;
                            }
                        }
                        self.showToast(message, 'success');
                        // Reload workflows to update UI
                        self.loadWorkflows();
                    } else {
                        self.showToast(response.data.message || self.i18n.error, 'error');
                    }
                },
                error: function(xhr, status, error) {
                    console.error('Reset workflows error:', error);
                    self.showToast(self.i18n.error, 'error');
                },
                complete: function() {
                    $button.removeClass('loading').prop('disabled', false);
                }
            });
        },

        /**
         * Show toast notification
         */
        showToast: function(message, type) {
            const $toast = $('#sm-toast');

            $toast
                .removeClass('success error warning')
                .addClass(type)
                .text(message)
                .addClass('show');

            setTimeout(function() {
                $toast.removeClass('show');
            }, 3000);
        },

        /**
         * Escape HTML to prevent XSS
         */
        escapeHtml: function(text) {
            if (typeof text !== 'string') {
                return text;
            }

            const map = {
                '&': '&amp;',
                '<': '&lt;',
                '>': '&gt;',
                '"': '&quot;',
                "'": '&#039;'
            };

            return text.replace(/[&<>"']/g, function(m) {
                return map[m];
            });
        }
    };

    // Initialize on document ready
    $(document).ready(function() {
        SettingsAdmin.init();
    });

})(jQuery);
