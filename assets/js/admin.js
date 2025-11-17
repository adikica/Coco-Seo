/**
 * Coco SEO Plugin Admin JavaScript
 */
(function($) {
    'use strict';

    const CocoSEO = {
        /**
         * Initialize the admin functionality
         */
        init: function() {
            this.bindEvents();
            this.initSettingsPage();
        },

        /**
         * Bind event handlers
         */
        bindEvents: function() {
            // Handle settings form submission
            $(document).on('submit', '#coco-seo-settings-form', this.handleSettingsSubmit);
            
            // Handle character counters in meta boxes
            if ($('.coco-seo-meta-box').length > 0) {
                this.initMetaBoxCounters();
            }
        },

        /**
         * Initialize settings page functionality
         */
        initSettingsPage: function() {
            if (!$('#coco-seo-settings-form').length) {
                return;
            }
            
            // Toggle dependent settings visibility
            $('select[name="coco_seo_settings[global_index]"]').on('change', function() {
                const value = $(this).val();
                const followField = $('select[name="coco_seo_settings[global_follow]"]').closest('tr');
                
                if (value === 'noindex') {
                    followField.fadeIn();
                } else {
                    followField.fadeIn();
                }
            }).trigger('change');
        },

        /**
         * Handle settings form submission
         * 
         * @param {Event} e The form submit event
         */
        handleSettingsSubmit: function(e) {
            e.preventDefault();
            
            const $form = $(this);
            const $submitButton = $form.find('#coco-seo-save-settings');
            const $spinner = $form.find('.spinner');
            
            // Show loading state
            $submitButton.prop('disabled', true);
            $spinner.addClass('is-active');
            
            // Collect form data
            const formData = $form.serializeArray();
            const settings = {};
            
            // Process form data into settings object
            formData.forEach(function(item) {
                const name = item.name;
                const value = item.value;
                
                // Handle checkboxes for post types
                if (name.includes('post_types]')) {
                    if (!settings.post_types) {
                        settings.post_types = [];
                    }
                    settings.post_types.push(value);
                } else if (name.includes('coco_seo_settings[')) {
                    // Extract setting name from form field name
                    const settingName = name.replace('coco_seo_settings[', '').replace(']', '');
                    settings[settingName] = value;
                }
            });
            
            // Send AJAX request
            $.ajax({
                url: cocoSEO.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'coco_seo_save_settings',
                    nonce: cocoSEO.nonce,
                    settings: settings
                },
                success: function(response) {
                    if (response.success) {
                        // Show success message
                        $('<div class="notice notice-success is-dismissible"><p>' + 
                            response.data.message + '</p></div>')
                            .insertBefore($form).fadeIn();
                            
                        // Update settings object
                        cocoSEO.settings = response.data.settings;
                        
                        // Remove message after 3 seconds
                        setTimeout(function() {
                            $('.notice').fadeOut(function() {
                                $(this).remove();
                            });
                        }, 3000);
                    } else {
                        // Show error message
                        $('<div class="notice notice-error is-dismissible"><p>' + 
                            response.data.message + '</p></div>')
                            .insertBefore($form).fadeIn();
                    }
                },
                error: function() {
                    // Show generic error message
                    $('<div class="notice notice-error is-dismissible"><p>' + 
                        'An error occurred while saving settings.' + '</p></div>')
                        .insertBefore($form).fadeIn();
                },
                complete: function() {
                    // Reset form state
                    $submitButton.prop('disabled', false);
                    $spinner.removeClass('is-active');
                }
            });
        },

        /**
         * Initialize meta box character counters
         */
        initMetaBoxCounters: function() {
            const titleInput = $('#coco_meta_title');
            const titleCount = $('#coco_meta_title_count');
            const descInput = $('#coco_meta_description');
            const descCount = $('#coco_meta_description_count');
            
            // Update count on input
            titleInput.on('input', function() {
                const length = $(this).val().length;
                titleCount.text(length);
                
                // Add warning class if too long or too short
                if (length > 60 || length < 30) {
                    titleCount.addClass('coco-seo-warning');
                } else {
                    titleCount.removeClass('coco-seo-warning');
                }
            }).trigger('input');
            
            descInput.on('input', function() {
                const length = $(this).val().length;
                descCount.text(length);
                
                // Add warning class if too long or too short
                if (length > 160 || length < 130) {
                    descCount.addClass('coco-seo-warning');
                } else {
                    descCount.removeClass('coco-seo-warning');
                }
            }).trigger('input');
        }
    };

    // Initialize when DOM is ready
    $(document).ready(function() {
        CocoSEO.init();
    });

})(jQuery);