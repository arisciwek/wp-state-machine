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
