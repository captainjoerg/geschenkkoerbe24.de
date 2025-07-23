/**
 * WooCommerce Product Layout Builder - Admin JavaScript
 * Handles admin interface interactions and layout configuration
 */

jQuery(document).ready(function($) {
    'use strict';

    // ===========================
    // COLOR PICKER INITIALIZATION
    // ===========================

    /**
     * Initialize WordPress color pickers
     */
    function initColorPickers() {
        $('.wc-plb-color-picker').wpColorPicker({
            change: function(event, ui) {
                // Trigger preview update if needed
                $(document).trigger('wc-plb-color-changed', [event, ui]);
            }
        });
    }

    // ===========================
    // SORTABLE ELEMENT ORDER
    // ===========================

    /**
     * Initialize sortable functionality for element ordering
     */
    function initSortableElements() {
        $('#wc-plb-element-order').sortable({
            placeholder: 'ui-state-highlight',
            cursor: 'move',
            opacity: 0.8,
            tolerance: 'pointer',
            start: function(event, ui) {
                ui.placeholder.height(ui.item.height());
                ui.placeholder.css('background', '#f0f0f0');
                ui.placeholder.css('border', '2px dashed #ddd');
                ui.placeholder.css('border-radius', '4px');
            },
            update: function(event, ui) {
                // Remove old hidden inputs
                $('#wc-plb-element-order input[type="hidden"]').remove();
                
                // Add new hidden inputs in the correct order
                $('#wc-plb-element-order li').each(function(index) {
                    var element = $(this).data('element');
                    $(this).append(
                        '<input type="hidden" name="wc_plb_config[element_order][]" value="' + 
                        element + '">'
                    );
                });
                
                // Trigger update event
                $(document).trigger('wc-plb-order-changed');
            }
        });
        
        // Make sortable items more visually interactive
        $('#wc-plb-element-order li').hover(
            function() {
                $(this).css('background-color', '#e8f4fd');
            },
            function() {
                $(this).css('background-color', '#f5f5f5');
            }
        );
    }

    // ===========================
    // TAB NAVIGATION
    // ===========================

    /**
     * Handle tab navigation in admin interface
     */
    function initTabNavigation() {
        $('.wc-plb-tabs-nav a').on('click', function(e) {
            e.preventDefault();
            
            // Remove active class from all tabs and content
            $('.wc-plb-tabs-nav a').removeClass('active');
            $('.wc-plb-tab-content').removeClass('active');
            
            // Add active class to clicked tab
            $(this).addClass('active');
            
            // Show corresponding content
            var tab = $(this).attr('href');
            $(tab).addClass('active');
            
            // Save active tab to localStorage
            localStorage.setItem('wc-plb-active-tab', tab);
            
            // Trigger tab change event
            $(document).trigger('wc-plb-tab-changed', [tab]);
        });
        
        // Restore active tab from localStorage
        var activeTab = localStorage.getItem('wc-plb-active-tab');
        if (activeTab && $(activeTab).length) {
            $('.wc-plb-tabs-nav a[href="' + activeTab + '"]').trigger('click');
        }
    }

    // ===========================
    // FORM VALIDATION
    // ===========================

    /**
     * Validate form inputs
     */
    function validateForm() {
        var isValid = true;
        var errors = [];
        
        // Validate columns
        var desktopCols = $('input[name="wc_plb_config[columns_desktop]"]').val();
        var tabletCols = $('input[name="wc_plb_config[columns_tablet]"]').val();
        var mobileCols = $('input[name="wc_plb_config[columns_mobile]"]').val();
        
        if (desktopCols < 1 || desktopCols > 6) {
            errors.push('Desktop-Spalten müssen zwischen 1 und 6 liegen.');
            isValid = false;
        }
        
        if (tabletCols < 1 || tabletCols > 4) {
            errors.push('Tablet-Spalten müssen zwischen 1 und 4 liegen.');
            isValid = false;
        }
        
        if (mobileCols < 1 || mobileCols > 2) {
            errors.push('Mobile-Spalten müssen zwischen 1 und 2 liegen.');
            isValid = false;
        }
        
        // Validate per page
        var perPage = $('input[name="wc_plb_config[per_page]"]').val();
        if (perPage < 1 || perPage > 100) {
            errors.push('Produkte pro Seite müssen zwischen 1 und 100 liegen.');
            isValid = false;
        }
        
        // Validate font sizes
        $('input[type="number"][name*="font_size"]').each(function() {
            var value = $(this).val();
            if (value && (value < 10 || value > 50)) {
                errors.push('Schriftgrößen müssen zwischen 10 und 50 px liegen.');
                isValid = false;
                return false; // Break loop
            }
        });
        
        // Show errors if any
        if (!isValid) {
            alert('Validierungsfehler:\n' + errors.join('\n'));
        }
        
        return isValid;
    }

    // ===========================
    // CLIPBOARD FUNCTIONALITY
    // ===========================

    /**
     * Handle shortcode copying to clipboard
     */
    function initClipboard() {
        $(document).on('click', '.wc-plb-copy-btn', function(e) {
            e.preventDefault();
            
            var $btn = $(this);
            var copyText = $btn.data('clipboard-text');
            
            // Create temporary textarea
            var $temp = $('<textarea>');
            $('body').append($temp);
            $temp.val(copyText).select();
            
            try {
                // Copy to clipboard
                var successful = document.execCommand('copy');
                
                if (successful) {
                    // Show success feedback
                    var originalHtml = $btn.html();
                    $btn.html('<span class="dashicons dashicons-yes"></span> Kopiert!');
                    $btn.addClass('success');
                    
                    setTimeout(function() {
                        $btn.html(originalHtml);
                        $btn.removeClass('success');
                    }, 2000);
                } else {
                    throw new Error('Copy command failed');
                }
            } catch (err) {
                // Fallback: Select the text for manual copying
                var $input = $btn.siblings('input[type="text"]');
                if ($input.length) {
                    $input.select();
                    alert('Bitte manuell kopieren (Ctrl+C / Cmd+C)');
                }
            }
            
            // Remove temporary textarea
            $temp.remove();
        });
    }

    // ===========================
    // LIVE PREVIEW HELPERS
    // ===========================

    /**
     * Update live preview (if implemented)
     */
    function updatePreview() {
        // This could be expanded to show a live preview
        $(document).trigger('wc-plb-preview-update');
    }

    /**
     * Handle input changes for live preview
     */
    function initLivePreview() {
        // Color changes
        $(document).on('wc-plb-color-changed', function(event, colorEvent, ui) {
            updatePreview();
        });
        
        // Font size changes
        $('input[type="number"][name*="font_size"]').on('input change', function() {
            updatePreview();
        });
        
        // Column changes
        $('input[name*="columns_"]').on('input change', function() {
            updatePreview();
        });
        
        // Element order changes
        $(document).on('wc-plb-order-changed', function() {
            updatePreview();
        });
    }

    // ===========================
    // CONDITIONAL FIELD DISPLAY
    // ===========================

    /**
     * Show/hide fields based on other field values
     */
    function initConditionalFields() {
        // Show/hide rating count options based on show_rating_count
        $('input[name="wc_plb_config[show_rating_count]"]').on('change', function() {
            var $ratingCountOptions = $(this).closest('tr').nextUntil('tr:not([class*="rating-count"])');
            if ($(this).is(':checked')) {
                $ratingCountOptions.show();
            } else {
                $ratingCountOptions.hide();
            }
        }).trigger('change');
        
        // Show/hide description options based on show_description
        $('input[name="wc_plb_config[show_description]"]').on('change', function() {
            var $descriptionOptions = $(this).closest('tr').nextUntil('tr:not([class*="description"])');
            if ($(this).is(':checked')) {
                $descriptionOptions.show();
            } else {
                $descriptionOptions.hide();
            }
        }).trigger('change');
        
        // Show/hide more info options based on show_more_info
        $('input[name="wc_plb_config[show_more_info]"]').on('change', function() {
            var $moreInfoOptions = $(this).closest('tr').nextUntil('tr:not([class*="more-info"])');
            if ($(this).is(':checked')) {
                $moreInfoOptions.show();
            } else {
                $moreInfoOptions.hide();
            }
        }).trigger('change');
    }

    // ===========================
    // FORM SUBMISSION
    // ===========================

    /**
     * Handle form submission with validation
     */
    function initFormSubmission() {
        $('form#post').on('submit', function(e) {
            if (!validateForm()) {
                e.preventDefault();
                return false;
            }
            
            // Show loading state
            var $submitBtn = $('#publish, #save-post');
            $submitBtn.prop('disabled', true);
            $submitBtn.val('Speichern...');
            
            // The form will submit normally after validation
        });
    }

    // ===========================
    // TOOLTIPS AND HELP
    // ===========================

    /**
     * Initialize tooltips for help text
     */
    function initTooltips() {
        // Add tooltips to description texts
        $('.description').each(function() {
            var $this = $(this);
            if ($this.text().length > 50) {
                $this.attr('title', $this.text());
            }
        });
        
        // Initialize jQuery UI tooltips if available
        if (typeof $.fn.tooltip === 'function') {
            $('[title]').tooltip({
                position: { my: "left+15 center", at: "right center" },
                tooltipClass: "wc-plb-tooltip"
            });
        }
    }

    // ===========================
    // RESET TO DEFAULTS
    // ===========================

    /**
     * Add reset to defaults functionality
     */
    function initResetDefaults() {
        // Add reset button if it doesn't exist
        if ($('.wc-plb-reset-defaults').length === 0) {
            var $resetBtn = $('<button type="button" class="button wc-plb-reset-defaults">Auf Standardwerte zurücksetzen</button>');
            $('.wc-plb-settings-container').prepend($resetBtn);
        }
        
        $('.wc-plb-reset-defaults').on('click', function(e) {
            e.preventDefault();
            
            if (confirm('Möchten Sie wirklich alle Einstellungen auf die Standardwerte zurücksetzen?')) {
                // Reset form fields to defaults
                resetToDefaults();
            }
        });
    }

    /**
     * Reset all form fields to default values
     */
    function resetToDefaults() {
        var defaults = {
            'columns_desktop': 4,
            'columns_tablet': 3,
            'columns_mobile': 1,
            'per_page': 12,
            'title_font_size': 16,
            'price_font_size': 16,
            'more_info_font_size': 14,
            'description_length': 300,
            'title_color': '#333333',
            'price_color': '#333333',
            'text_color': '#333333',
            'button_bg_color': '#96588a',
            'button_text_color': '#ffffff',
            'sale_badge_color': '#77a464',
            'sale_badge_text_color': '#ffffff',
            'rating_star_filled_color': '#ffb100',
            'rating_star_empty_color': '#cccccc'
        };
        
        // Reset number inputs
        $.each(defaults, function(key, value) {
            var $input = $('input[name="wc_plb_config[' + key + ']"]');
            if ($input.length) {
                $input.val(value);
                if ($input.hasClass('wc-plb-color-picker')) {
                    $input.wpColorPicker('color', value);
                }
            }
        });
        
        // Reset checkboxes to checked state
        var checkedByDefault = ['show_title', 'show_image', 'show_price', 'show_rating', 'show_rating_count', 'show_add_to_cart', 'show_sale_badge', 'allow_html'];
        checkedByDefault.forEach(function(key) {
            $('input[name="wc_plb_config[' + key + ']"]').prop('checked', true);
        });
        
        // Reset unchecked checkboxes
        var uncheckedByDefault = ['show_stock_status', 'show_attributes', 'show_description', 'show_more_info', 'filter_by_category', 'filter_by_price', 'filter_by_rating'];
        uncheckedByDefault.forEach(function(key) {
            $('input[name="wc_plb_config[' + key + ']"]').prop('checked', false);
        });
        
        // Reset select fields
        $('select[name="wc_plb_config[excerpt_type]"]').val('short');
        $('select[name="wc_plb_config[fallback_option]"]').val('use_long');
        $('select[name="wc_plb_config[attribute_display]"]').val('text');
        $('select[name="wc_plb_config[more_info_display]"]').val('text');
        $('select[name="wc_plb_config[rating_count_position]"]').val('after');
        
        // Trigger change events
        $('input, select').trigger('change');
        
        alert('Einstellungen wurden auf Standardwerte zurückgesetzt.');
    }

    // ===========================
    // INITIALIZATION
    // ===========================

    /**
     * Initialize all admin functionality
     */
    function init() {
        console.log('WC Product Layout Builder Admin: Initializing...');
        
        // Initialize all components
        initColorPickers();
        initSortableElements();
        initTabNavigation();
        initClipboard();
        initLivePreview();
        initConditionalFields();
        initFormSubmission();
        initTooltips();
        initResetDefaults();
        
        // Add custom styles for better UX
        addCustomStyles();
        
        console.log('WC Product Layout Builder Admin: Initialized');
    }

    /**
     * Add custom styles for admin interface
     */
    function addCustomStyles() {
        if ($('#wc-plb-admin-styles').length === 0) {
            $('head').append(`
                <style id="wc-plb-admin-styles">
                    .wc-plb-copy-btn.success {
                        background-color: #46b450 !important;
                        border-color: #46b450 !important;
                        color: white !important;
                    }
                    
                    .wc-plb-tooltip {
                        max-width: 300px;
                        background: #333;
                        color: white;
                        border-radius: 4px;
                        padding: 8px 12px;
                        font-size: 12px;
                        line-height: 1.4;
                    }
                    
                    .wc-plb-reset-defaults {
                        margin-bottom: 20px;
                        background: #dc3232;
                        color: white;
                        border-color: #dc3232;
                    }
                    
                    .wc-plb-reset-defaults:hover {
                        background: #c62d2d;
                        border-color: #c62d2d;
                    }
                    
                    .ui-state-highlight {
                        height: 60px;
                        line-height: 60px;
                        text-align: center;
                        color: #999;
                        border: 2px dashed #ddd !important;
                        background: #f9f9f9 !important;
                        border-radius: 4px;
                    }
                    
                    .ui-state-highlight:before {
                        content: "Element hier ablegen";
                        font-style: italic;
                    }
                </style>
            `);
        }
    }

    // ===========================
    // DOCUMENT READY
    // ===========================

    // Initialize when document is ready
    init();

    // Make functions globally available for debugging
    window.wcPlbAdmin = {
        initColorPickers: initColorPickers,
        initSortableElements: initSortableElements,
        validateForm: validateForm,
        resetToDefaults: resetToDefaults,
        updatePreview: updatePreview
    };

});