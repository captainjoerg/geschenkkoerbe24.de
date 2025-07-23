/**
 * WooCommerce Product Layout Builder - Frontend JavaScript
 * Handles alignment, filtering, and interactive features
 */

jQuery(function($) {
    'use strict';

    // ===========================
    // HEIGHT EQUALIZATION SYSTEM
    // ===========================

    /**
     * Main function to equalize product heights within each row
     */
    function equalizeProductHeights() {
        $('.wc-plb-product-grid').each(function() {
            const $grid = $(this);
            const $products = $grid.find('.wc-plb-product');
            
            if ($products.length === 0) return;

            // Reset heights to get natural measurements
            $products.css('min-height', '');
            
            // Get number of columns based on current viewport
            const gridComputedStyle = window.getComputedStyle($grid[0]);
            const gridTemplateColumns = gridComputedStyle.getPropertyValue('grid-template-columns');
            const columns = gridTemplateColumns.split(' ').length;
            
            console.log('Detected columns:', columns);
            
            // Group products by rows
            const rows = [];
            for (let i = 0; i < $products.length; i += columns) {
                const rowProducts = $products.slice(i, i + columns);
                if (rowProducts.length > 0) {
                    rows.push(rowProducts);
                }
            }
            
            // Equalize height within each row
            rows.forEach(function(rowProducts) {
                // Wait for images to load before measuring heights
                const $rowProducts = $(rowProducts);
                const images = $rowProducts.find('img');
                
                if (images.length === 0) {
                    equalizeRowHeights($rowProducts);
                } else {
                    // Track loaded images
                    let loadedImages = 0;
                    const totalImages = images.length;
                    
                    images.each(function() {
                        const img = this;
                        if (img.complete) {
                            loadedImages++;
                            if (loadedImages === totalImages) {
                                setTimeout(() => equalizeRowHeights($rowProducts), 50);
                            }
                        } else {
                            $(img).on('load error', function() {
                                loadedImages++;
                                if (loadedImages === totalImages) {
                                    setTimeout(() => equalizeRowHeights($rowProducts), 50);
                                }
                            });
                        }
                    });
                }
            });
        });
    }

    /**
     * Equalize heights for products in a single row
     */
    function equalizeRowHeights($products) {
        if ($products.length <= 1) return;
        
        // Reset heights
        $products.css('min-height', '');
        
        // Measure natural heights
        let maxHeight = 0;
        $products.each(function() {
            const height = $(this).outerHeight(true);
            if (height > maxHeight) {
                maxHeight = height;
            }
        });
        
        // Apply the maximum height to all products in this row
        if (maxHeight > 0) {
            $products.css('min-height', maxHeight + 'px');
        }
        
        console.log('Row equalized to height:', maxHeight);
    }

    /**
     * Advanced element alignment within each product card
     */
    function alignProductElements() {
        $('.wc-plb-product-grid').each(function() {
            const $grid = $(this);
            const $products = $grid.find('.wc-plb-product');
            
            // Get columns for current viewport
            const gridComputedStyle = window.getComputedStyle($grid[0]);
            const gridTemplateColumns = gridComputedStyle.getPropertyValue('grid-template-columns');
            const columns = gridTemplateColumns.split(' ').length;
            
            // Group products by rows for element alignment
            for (let i = 0; i < $products.length; i += columns) {
                const $rowProducts = $products.slice(i, i + columns);
                if ($rowProducts.length > 1) {
                    alignElementsInRow($rowProducts);
                }
            }
        });
    }

    /**
     * Align specific elements across products in a row
     */
    function alignElementsInRow($products) {
        const elements = [
            '.wc-plb-product-image',
            '.wc-plb-product-title', 
            '.wc-plb-product-price',
            '.wc-plb-product-rating-container',
            '.wc-plb-product-description'
        ];
        
        elements.forEach(function(selector) {
            const $elements = $products.find(selector);
            if ($elements.length <= 1) return;
            
            // Reset heights
            $elements.css('min-height', '');
            
            // Find max height
            let maxHeight = 0;
            $elements.each(function() {
                const height = $(this).outerHeight(true);
                if (height > maxHeight) {
                    maxHeight = height;
                }
            });
            
            // Apply max height
            if (maxHeight > 0) {
                $elements.css('min-height', maxHeight + 'px');
            }
        });
    }

    // ===========================
    // AJAX FILTERING SYSTEM
    // ===========================

    /**
     * Load products via AJAX with filters
     */
    function loadProducts(data) {
        const $container = $(`.wc-plb-product-grid[data-layout-id="${data.layout_id}"]`);
        const $pagination = $(`.wc-plb-pagination[data-layout-id="${data.layout_id}"]`);
        
        $.ajax({
            url: wc_plb_frontend.ajax_url,
            type: 'POST',
            data: {
                action: 'wc_plb_filter_products',
                nonce: wc_plb_frontend.nonce,
                layout_id: data.layout_id,
                page: data.page || 1,
                category: data.category || '',
                price_range: data.price_range || '',
                rating: data.rating || ''
            },
            beforeSend: function() {
                $container.addClass('loading');
                $pagination.addClass('loading');
                
                // Show loading overlay
                showLoadingOverlay($container);
            },
            success: function(response) {
                if (response.success) {
                    // Replace content
                    $container.replaceWith(response.data.html);
                    $pagination.replaceWith(response.data.pagination);
                    
                    // Update URL
                    updateURL(data.page);
                    
                    // Trigger content loaded event
                    $(document).trigger('wc_plb_products_loaded');
                } else {
                    console.error('AJAX Error:', response.data);
                }
            },
            error: function(xhr, status, error) {
                console.error('AJAX Request failed:', error);
            },
            complete: function() {
                hideLoadingOverlay();
            }
        });
    }

    /**
     * Show loading overlay
     */
    function showLoadingOverlay($container) {
        if ($container.find('.wc-plb-loading-overlay').length === 0) {
            $container.append('<div class="wc-plb-loading-overlay"><div class="wc-plb-spinner"></div></div>');
        }
    }

    /**
     * Hide loading overlay
     */
    function hideLoadingOverlay() {
        $('.wc-plb-loading-overlay').remove();
        $('.loading').removeClass('loading');
    }

    /**
     * Update browser URL for pagination
     */
    function updateURL(page) {
        if (!history.pushState) return;
        
        let basePath = window.location.pathname.replace(/\/page\/\d+\/$/, '');
        basePath = basePath.replace(/\/$/, '');
        
        let newUrl = basePath;
        if (page > 1) {
            newUrl += `/page/${page}/`;
        } else {
            newUrl += '/';
        }
        
        // Remove double slashes
        newUrl = newUrl.replace(/\/+/g, '/');
        
        window.history.pushState({path: newUrl}, '', newUrl);
    }

    // ===========================
    // EVENT HANDLERS
    // ===========================

    /**
     * Pagination click handler
     */
    $(document).on('click', '.wc-plb-pagination a.page-numbers', function(e) {
        e.preventDefault();
        
        const $link = $(this);
        const page = $link.data('page');
        const layoutId = $link.closest('.wc-plb-pagination').data('layout-id');
        const $filters = $(`.wc-plb-filters[data-layout-id="${layoutId}"]`);
        
        // Scroll to top of grid
        const $grid = $(`.wc-plb-product-grid[data-layout-id="${layoutId}"]`);
        if ($grid.length) {
            $('html, body').animate({
                scrollTop: $grid.offset().top - 50
            }, 500);
        }
        
        loadProducts({
            page: page,
            category: $filters.find('.wc-plb-category-filter select').val(),
            price_range: $filters.find('.wc-plb-price-filter select').val(),
            rating: $filters.find('.wc-plb-rating-filter select').val(),
            layout_id: layoutId
        });
    });

    /**
     * Filter change handler
     */
    $(document).on('change', '.wc-plb-filter-select', function() {
        const $filters = $(this).closest('.wc-plb-filters');
        const layoutId = $filters.data('layout-id');
        
        loadProducts({
            page: 1, // Reset to first page when filtering
            category: $filters.find('.wc-plb-category-filter select').val(),
            price_range: $filters.find('.wc-plb-price-filter select').val(),
            rating: $filters.find('.wc-plb-rating-filter select').val(),
            layout_id: layoutId
        });
    });

    /**
     * Window resize handler with debouncing
     */
    let resizeTimer;
    $(window).on('resize', function() {
        clearTimeout(resizeTimer);
        resizeTimer = setTimeout(function() {
            equalizeProductHeights();
            alignProductElements();
        }, 250);
    });

    /**
     * Products loaded event handler
     */
    $(document).on('wc_plb_products_loaded', function() {
        // Wait for DOM updates and then equalize heights
        setTimeout(function() {
            equalizeProductHeights();
            alignProductElements();
        }, 100);
        
        // Additional check after a longer delay for any async loading
        setTimeout(function() {
            equalizeProductHeights();
            alignProductElements();
        }, 500);
    });

    // ===========================
    // INITIALIZATION
    // ===========================

    /**
     * Initialize the plugin when DOM is ready
     */
    $(document).ready(function() {
        console.log('WC Product Layout Builder: Initializing...');
        
        // Initial height equalization
        setTimeout(function() {
            equalizeProductHeights();
            alignProductElements();
        }, 100);
        
        // Re-equalize after images load
        setTimeout(function() {
            equalizeProductHeights();
            alignProductElements();
        }, 1000);
        
        console.log('WC Product Layout Builder: Initialized');
    });

    /**
     * Font loading detection (if Web Font Loader is available)
     */
    if (typeof WebFont !== 'undefined') {
        WebFont.load({
            active: function() {
                setTimeout(function() {
                    equalizeProductHeights();
                    alignProductElements();
                }, 200);
            }
        });
    }

    // ===========================
    // UTILITY FUNCTIONS
    // ===========================

    /**
     * Debounce function to limit function calls
     */
    function debounce(func, wait, immediate) {
        let timeout;
        return function() {
            const context = this;
            const args = arguments;
            const later = function() {
                timeout = null;
                if (!immediate) func.apply(context, args);
            };
            const callNow = immediate && !timeout;
            clearTimeout(timeout);
            timeout = setTimeout(later, wait);
            if (callNow) func.apply(context, args);
        };
    }

    /**
     * Get current viewport breakpoint
     */
    function getCurrentBreakpoint() {
        const width = $(window).width();
        if (width >= 1024) return 'desktop';
        if (width >= 768) return 'tablet';
        return 'mobile';
    }

    // Make functions globally available for debugging
    window.wcPlbFrontend = {
        equalizeProductHeights: equalizeProductHeights,
        alignProductElements: alignProductElements,
        loadProducts: loadProducts,
        getCurrentBreakpoint: getCurrentBreakpoint
    };

});

// ===========================
// CSS FOR LOADING OVERLAY
// ===========================

// Add loading styles dynamically
jQuery(document).ready(function($) {
    if ($('#wc-plb-loading-styles').length === 0) {
        $('head').append(`
            <style id="wc-plb-loading-styles">
                .wc-plb-loading-overlay {
                    position: absolute;
                    top: 0;
                    left: 0;
                    right: 0;
                    bottom: 0;
                    background: rgba(255, 255, 255, 0.8);
                    display: flex;
                    align-items: center;
                    justify-content: center;
                    z-index: 10;
                }
                
                .wc-plb-spinner {
                    width: 40px;
                    height: 40px;
                    border: 3px solid #f3f3f3;
                    border-top: 3px solid #0073aa;
                    border-radius: 50%;
                    animation: wc-plb-spin 1s linear infinite;
                }
                
                @keyframes wc-plb-spin {
                    0% { transform: rotate(0deg); }
                    100% { transform: rotate(360deg); }
                }
                
                .wc-plb-product-grid {
                    position: relative;
                }
            </style>
        `);
    }
});