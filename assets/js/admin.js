/**
 * Admin JavaScript for WP REST API Key Authentication
 */

(function($) {
    'use strict';

    var WPRestApiKeyAuth = {
        
        /**
         * Initialize the admin interface
         */
        init: function() {
            this.bindEvents();
            this.initModals();
            this.initTables();
        },

        /**
         * Bind event handlers
         */
        bindEvents: function() {
            // Add new API key button
            $(document).on('click', '#add-new-api-key', this.showCreateModal);
            
            // Modal close buttons
            $(document).on('click', '.api-key-modal-close, #cancel-api-key', this.hideModals);
            
            // Generate API key
            $(document).on('click', '#generate-api-key', this.generateApiKey);
            
            // Copy API key
            $(document).on('click', '#copy-api-key', this.copyApiKey);
            
            // Close API key display
            $(document).on('click', '#close-api-key-display', this.hideModals);
            
            // Delete API key
            $(document).on('click', '.delete-api-key', this.deleteApiKey);
            
            // Toggle API key status
            $(document).on('click', '.toggle-api-key', this.toggleApiKey);
            
            // Edit API key
            $(document).on('click', '.edit-api-key', this.editApiKey);
            
            // Export logs
            $(document).on('click', '.export-logs', this.exportLogs);
            
            // Close modal when clicking outside
            $(document).on('click', '.api-key-modal', function(e) {
                if (e.target === this) {
                    WPRestApiKeyAuth.hideModals();
                }
            });
            
            // Prevent modal close when clicking inside modal content
            $(document).on('click', '.api-key-modal-content', function(e) {
                e.stopPropagation();
            });
        },

        /**
         * Initialize modals
         */
        initModals: function() {
            // Reset form when modal is shown
            $(document).on('show.modal', '#api-key-modal', function() {
                $('#api-key-form')[0].reset();
                $('#api-key-name').focus();
            });
        },

        /**
         * Initialize tables
         */
        initTables: function() {
            // Select all checkbox
            $(document).on('change', '#cb-select-all-1', function() {
                var checked = $(this).prop('checked');
                $('input[name="api_keys[]"]').prop('checked', checked);
            });
            
            // Individual checkboxes
            $(document).on('change', 'input[name="api_keys[]"]', function() {
                var total = $('input[name="api_keys[]"]').length;
                var checked = $('input[name="api_keys[]"]:checked').length;
                $('#cb-select-all-1').prop('checked', total === checked);
            });
        },

        /**
         * Show create modal
         */
        showCreateModal: function(e) {
            e.preventDefault();
            $('#api-key-modal').show().trigger('show.modal');
        },

        /**
         * Hide all modals
         */
        hideModals: function() {
            $('.api-key-modal').hide();
        },

        /**
         * Generate API key
         */
        generateApiKey: function(e) {
            e.preventDefault();
            
            var $button = $(this);
            var $form = $('#api-key-form');
            
            // Validate form
            if (!$form[0].checkValidity()) {
                $form[0].reportValidity();
                return;
            }
            
            // Get form data
            var formData = {
                action: 'wp_rest_api_key_auth_generate_key',
                nonce: wpRestApiKeyAuth.nonce,
                name: $('#api-key-name').val(),
                permissions: [],
                rate_limit: $('#api-key-rate-limit').val(),
                allowed_ips: $('#api-key-allowed-ips').val(),
                allowed_domains: $('#api-key-allowed-domains').val(),
                allowed_endpoints: $('#api-key-allowed-endpoints').val(),
                blocked_endpoints: $('#api-key-blocked-endpoints').val()
            };
            
            // Get selected permissions
            $('input[name="permissions[]"]:checked').each(function() {
                formData.permissions.push($(this).val());
            });
            
            // Show loading state
            $button.addClass('loading').prop('disabled', true);
            
            // Send AJAX request
            $.post(wpRestApiKeyAuth.ajaxUrl, formData)
                .done(function(response) {
                    if (response.success) {
                        // Hide create modal
                        $('#api-key-modal').hide();
                        
                        // Show API key
                        $('#generated-api-key').val(response.data.api_key);
                        $('#api-key-display-modal').show();
                        
                        // Show success message
                        WPRestApiKeyAuth.showNotice(response.data.message, 'success');
                        
                        // Reload page after a delay
                        setTimeout(function() {
                            location.reload();
                        }, 3000);
                    } else {
                        WPRestApiKeyAuth.showNotice(response.data || wpRestApiKeyAuth.strings.error, 'error');
                    }
                })
                .fail(function() {
                    WPRestApiKeyAuth.showNotice(wpRestApiKeyAuth.strings.error, 'error');
                })
                .always(function() {
                    $button.removeClass('loading').prop('disabled', false);
                });
        },

        /**
         * Copy API key to clipboard
         */
        copyApiKey: function(e) {
            e.preventDefault();
            
            var $input = $('#generated-api-key');
            $input.select();
            
            try {
                var successful = document.execCommand('copy');
                if (successful) {
                    WPRestApiKeyAuth.showNotice(wpRestApiKeyAuth.strings.copySuccess, 'success');
                    $(this).text('Copied!').addClass('button-secondary').removeClass('button-primary');
                } else {
                    throw new Error('Copy command failed');
                }
            } catch (err) {
                WPRestApiKeyAuth.showNotice(wpRestApiKeyAuth.strings.copyError, 'error');
            }
        },

        /**
         * Delete API key
         */
        deleteApiKey: function(e) {
            e.preventDefault();
            
            var $link = $(this);
            var id = $link.data('id');
            var name = $link.data('name');
            
            if (!confirm(wpRestApiKeyAuth.strings.confirmDelete.replace('%s', name))) {
                return;
            }
            
            var $row = $link.closest('tr');
            
            // Show loading state
            $row.addClass('loading');
            
            // Send AJAX request
            $.post(wpRestApiKeyAuth.ajaxUrl, {
                action: 'wp_rest_api_key_auth_delete_key',
                nonce: wpRestApiKeyAuth.nonce,
                id: id
            })
            .done(function(response) {
                if (response.success) {
                    $row.fadeOut(function() {
                        $(this).remove();
                        WPRestApiKeyAuth.updateStats();
                    });
                    WPRestApiKeyAuth.showNotice(response.data, 'success');
                } else {
                    WPRestApiKeyAuth.showNotice(response.data || wpRestApiKeyAuth.strings.error, 'error');
                }
            })
            .fail(function() {
                WPRestApiKeyAuth.showNotice(wpRestApiKeyAuth.strings.error, 'error');
            })
            .always(function() {
                $row.removeClass('loading');
            });
        },

        /**
         * Toggle API key status
         */
        toggleApiKey: function(e) {
            e.preventDefault();
            
            var $link = $(this);
            var id = $link.data('id');
            var action = $link.data('action');
            var $row = $link.closest('tr');
            
            // Show loading state
            $row.addClass('loading');
            
            // Send AJAX request
            $.post(wpRestApiKeyAuth.ajaxUrl, {
                action: 'wp_rest_api_key_auth_update_key',
                nonce: wpRestApiKeyAuth.nonce,
                id: id,
                status: action === 'activate' ? 'active' : 'inactive'
            })
            .done(function(response) {
                if (response.success) {
                    // Reload page to update status
                    location.reload();
                } else {
                    WPRestApiKeyAuth.showNotice(response.data || wpRestApiKeyAuth.strings.error, 'error');
                }
            })
            .fail(function() {
                WPRestApiKeyAuth.showNotice(wpRestApiKeyAuth.strings.error, 'error');
            })
            .always(function() {
                $row.removeClass('loading');
            });
        },

        /**
         * Edit API key (placeholder for future implementation)
         */
        editApiKey: function(e) {
            e.preventDefault();
            
            var id = $(this).data('id');
            
            // For now, just show an alert
            alert('Edit functionality will be implemented in a future version.');
        },

        /**
         * Export logs
         */
        exportLogs: function(e) {
            e.preventDefault();
            
            var type = $(this).data('type') || 'api';
            var days = $(this).data('days') || 30;
            
            // Create form and submit
            var $form = $('<form>', {
                method: 'POST',
                action: wpRestApiKeyAuth.ajaxUrl
            });
            
            $form.append($('<input>', {
                type: 'hidden',
                name: 'action',
                value: 'wp_rest_api_key_auth_export_logs'
            }));
            
            $form.append($('<input>', {
                type: 'hidden',
                name: 'nonce',
                value: wpRestApiKeyAuth.nonce
            }));
            
            $form.append($('<input>', {
                type: 'hidden',
                name: 'type',
                value: type
            }));
            
            $form.append($('<input>', {
                type: 'hidden',
                name: 'days',
                value: days
            }));
            
            $form.appendTo('body').submit().remove();
        },

        /**
         * Show admin notice
         */
        showNotice: function(message, type) {
            type = type || 'info';
            
            var $notice = $('<div>', {
                class: 'notice notice-' + type + ' is-dismissible wp-rest-api-key-auth-notice',
                html: '<p>' + message + '</p><button type="button" class="notice-dismiss"><span class="screen-reader-text">Dismiss this notice.</span></button>'
            });
            
            $('.wrap h1').after($notice);
            
            // Auto-dismiss success notices
            if (type === 'success') {
                setTimeout(function() {
                    $notice.fadeOut();
                }, 5000);
            }
            
            // Handle dismiss button
            $notice.on('click', '.notice-dismiss', function() {
                $notice.fadeOut();
            });
        },

        /**
         * Update statistics
         */
        updateStats: function() {
            var $rows = $('.api-keys-table tbody tr');
            var total = $rows.length;
            var active = $rows.filter(':contains("Active")').length;
            
            $('.stat-item').eq(0).find('.stat-number').text(total);
            $('.stat-item').eq(1).find('.stat-number').text(active);
        },

        /**
         * Format number with commas
         */
        formatNumber: function(num) {
            return num.toString().replace(/\B(?=(\d{3})+(?!\d))/g, ',');
        }
    };

    // Initialize when document is ready
    $(document).ready(function() {
        WPRestApiKeyAuth.init();
    });

    // Make it globally available
    window.WPRestApiKeyAuth = WPRestApiKeyAuth;

})(jQuery); 