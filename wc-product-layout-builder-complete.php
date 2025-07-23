<?php
/**
 * Plugin Name: WooCommerce Product Layout Builder - Complete
 * Description: Erstelle visuelle Produkt-Layouts mit perfekter Ausrichtung, Grids und Filteroptionen - Alles in einer Datei
 * Version: 0.8.0 - Single File Complete
 * Author: Jörg Middelkamp
 * Requires at least: 5.6
 * Requires PHP: 7.4
 * WC requires at least: 5.0
 * WC tested up to: 7.0
 * License: GPLv2 or later
 * Text Domain: wc-product-layout
 */

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

// HPOS-Kompatibilität deklarieren
add_action('before_woocommerce_init', function() {
    if (class_exists('\Automattic\WooCommerce\Utilities\FeaturesUtil')) {
        \Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility(
            'custom_order_tables', 
            __FILE__, 
            true
        );
    }
});

/**
 * Hauptklasse für den WooCommerce Product Layout Builder
 */
class WC_Product_Layout_Builder_Complete {

    /**
     * Plugin-Version
     */
    const VERSION = '0.8.0';

    /**
     * Instanz der Klasse
     */
    private static $instance = null;

    /**
     * Singleton-Instanz
     */
    public static function get_instance() {
        if (is_null(self::$instance)) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Konstruktor
     */
    private function __construct() {
        // Plugin initialisieren
        $this->define_constants();
        $this->init_hooks();
    }

    /**
     * Definiere Plugin-Konstanten
     */
    private function define_constants() {
        define('WC_PLB_VERSION', self::VERSION);
        define('WC_PLB_PLUGIN_DIR', plugin_dir_path(__FILE__));
        define('WC_PLB_PLUGIN_URL', plugin_dir_url(__FILE__));
        define('WC_PLB_POST_TYPE', 'wc_product_layout');
    }

    /**
     * Initialisiere Hooks und Aktionen
     */
    private function init_hooks() {
        // Plugin aktivieren/deaktivieren
        register_activation_hook(__FILE__, array($this, 'activate'));
        register_deactivation_hook(__FILE__, array($this, 'deactivate'));

        // Textdomain laden
        add_action('plugins_loaded', array($this, 'load_textdomain'));

        // Post Type registrieren
        add_action('init', array($this, 'register_post_type'));

        // Admin-Styles und Skripte
        add_action('admin_enqueue_scripts', array($this, 'admin_scripts'));

        // Frontend-Styles und Skripte
        add_action('wp_enqueue_scripts', array($this, 'frontend_scripts'));

        // Meta Boxen hinzufügen
        add_action('add_meta_boxes', array($this, 'add_meta_boxes'));

        // Meta speichern
        add_action('save_post_' . WC_PLB_POST_TYPE, array($this, 'save_layout_settings'));

        // Shortcode registrieren
        add_shortcode('wc_product_layout', array($this, 'shortcode_render'));

        // Admin-Spalten anpassen
        add_filter('manage_' . WC_PLB_POST_TYPE . '_posts_columns', array($this, 'custom_columns'));
        add_action('manage_' . WC_PLB_POST_TYPE . '_posts_custom_column', array($this, 'custom_column_content'), 10, 2);

        // AJAX Handler für Filter
        add_action('wp_ajax_wc_plb_filter_products', array($this, 'ajax_filter_products'));
        add_action('wp_ajax_nopriv_wc_plb_filter_products', array($this, 'ajax_filter_products'));
        
        // Hook hinzufügen, sobald WooCommerce geladen ist
        add_action('woocommerce_loaded', function() {
            // Avaya-Telefonnummernerkennung für Preise deaktivieren
            add_filter('avaya_dce_skip_content', function($skip, $content) {
                if (strpos($content, 'woocommerce-Price-amount') !== false) {
                    return true; // Überspringt Avaya für WooCommerce-Preise
                }
                return $skip;
            }, 10, 2);
        });          
                
    }

    /**
     * Plugin aktivieren
     */
    public function activate() {
        $this->register_post_type();
        
        // Rewrite Rules für schöne Pagination-URLs
        add_rewrite_rule(
            '^(.*)/page/([0-9]+)/?$',
            'index.php?pagename=$matches[1]&plb_page=$matches[2]',
            'top'
        );
        
        // Füge den plb_page Parameter zu den erlaubten Query Vars hinzu
        add_filter('query_vars', function($vars) {
            $vars[] = 'plb_page';
            return $vars;
        });
        
        flush_rewrite_rules();
    }

    /**
     * Plugin deaktivieren
     */
    public function deactivate() {
        flush_rewrite_rules();
    }

    /**
     * Textdomain laden
     */
    public function load_textdomain() {
        load_plugin_textdomain('wc-product-layout', false, dirname(plugin_basename(__FILE__)) . '/languages');
    }

    /**
     * Custom Post Type registrieren
     */
    public function register_post_type() {
        $labels = array(
            'name'               => __('Produkt Layouts', 'wc-product-layout'),
            'singular_name'      => __('Produkt Layout', 'wc-product-layout'),
            'menu_name'          => __('Produkt Layouts', 'wc-product-layout'),
            'name_admin_bar'     => __('Produkt Layout', 'wc-product-layout'),
            'add_new'            => __('Neues Layout', 'wc-product-layout'),
            'add_new_item'       => __('Neues Produkt Layout', 'wc-product-layout'),
            'new_item'           => __('Neues Layout', 'wc-product-layout'),
            'edit_item'          => __('Layout bearbeiten', 'wc-product-layout'),
            'view_item'          => __('Layout anzeigen', 'wc-product-layout'),
            'all_items'          => __('Alle Layouts', 'wc-product-layout'),
            'search_items'       => __('Layouts suchen', 'wc-product-layout'),
            'parent_item_colon'  => __('Übergeordnetes Layout:', 'wc-product-layout'),
            'not_found'          => __('Keine Layouts gefunden.', 'wc-product-layout'),
            'not_found_in_trash' => __('Keine Layouts im Papierkorb gefunden.', 'wc-product-layout')
        );

        $args = array(
            'labels'             => $labels,
            'public'             => false,
            'publicly_queryable' => true,
            'show_ui'            => true,
            'show_in_menu'       => true,
            'query_var'          => true,
            'rewrite'            => false,
            'capability_type'    => 'post',
            'has_archive'        => false,
            'hierarchical'       => false,
            'menu_position'      => 56,
            'menu_icon'          => 'dashicons-products',
            'supports'           => array('title')
        );

        register_post_type(WC_PLB_POST_TYPE, $args);
    }

    /**
     * Admin-Skripte und Styles
     */
    public function admin_scripts($hook) {
        global $post_type;

        // Nur auf unserer Post-Type-Seite laden
        if ($hook == 'post-new.php' || $hook == 'post.php') {
            if ($post_type == WC_PLB_POST_TYPE) {
                // Farbauswahl
                wp_enqueue_style('wp-color-picker');
                
                // jQuery UI für Sortierfunktion
                wp_enqueue_script('jquery-ui-sortable');
                
                // Inline Admin CSS
                add_action('admin_head', array($this, 'output_admin_css'));
                
                // Inline Admin JavaScript
                add_action('admin_footer', array($this, 'output_admin_js'));
                
                // Lokalisierte Skripte
                wp_localize_script('jquery', 'wc_plb_admin', array(
                    'ajax_url' => admin_url('admin-ajax.php'),
                    'nonce'    => wp_create_nonce('wc_plb_nonce')
                ));
            }
        }
    }

    /**
     * Frontend-Skripte und Styles
     */
    public function frontend_scripts() {
        // Inline Frontend CSS
        add_action('wp_head', array($this, 'output_frontend_css'));
        
        // Inline Frontend JavaScript
        add_action('wp_footer', array($this, 'output_frontend_js'));
        
        // AJAX Variablen
        wp_localize_script('jquery', 'wc_plb_frontend', array(
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce'    => wp_create_nonce('wc_plb_nonce')
        ));
    }

    /**
     * Output Admin CSS
     */
    public function output_admin_css() {
        ?>
        <style>
        /* WooCommerce Product Layout Builder - Admin Styles */
        
        .wc-plb-settings-container {
            margin-top: 20px;
            background: #fff;
            border: 1px solid #ccd0d4;
            border-radius: 4px;
            box-shadow: 0 1px 1px rgba(0, 0, 0, 0.04);
        }
        
        .wc-plb-settings-container .form-table {
            margin: 0;
        }
        
        .wc-plb-settings-container .form-table th {
            padding: 20px 20px 20px 10px;
            vertical-align: top;
            font-weight: 600;
            color: #23282d;
        }
        
        .wc-plb-settings-container .form-table td {
            padding: 20px 10px 20px 0;
            vertical-align: top;
        }
        
        /* Tab Navigation */
        .wc-plb-tabs-nav {
            display: flex;
            border-bottom: 1px solid #ccd0d4;
            margin: 0;
            background: #f9f9f9;
            border-radius: 4px 4px 0 0;
        }
        
        .wc-plb-tabs-nav a {
            padding: 15px 20px;
            margin: 0;
            background: transparent;
            text-decoration: none;
            border: none;
            border-right: 1px solid #ccd0d4;
            color: #555;
            font-weight: 500;
            transition: all 0.3s ease;
            position: relative;
        }
        
        .wc-plb-tabs-nav a:last-child {
            border-right: none;
        }
        
        .wc-plb-tabs-nav a:hover {
            background: #fff;
            color: #0073aa;
        }
        
        .wc-plb-tabs-nav a.active {
            background: #fff;
            color: #0073aa;
            font-weight: 600;
            border-bottom: 2px solid #0073aa;
            margin-bottom: -1px;
        }
        
        .wc-plb-tab-content {
            display: none;
            padding: 20px;
        }
        
        .wc-plb-tab-content.active {
            display: block;
        }
        
        /* Sortable Elements */
        .wc-plb-sortable {
            list-style-type: none;
            padding: 0;
            margin: 15px 0;
            width: 100%;
            max-width: 600px;
        }
        
        .wc-plb-sortable li {
            padding: 15px;
            background: #f8f9fa;
            border: 1px solid #e1e5e9;
            margin-bottom: 8px;
            cursor: move;
            border-radius: 6px;
            transition: all 0.3s ease;
            position: relative;
        }
        
        .wc-plb-sortable li:hover {
            background: #e8f4fd;
            border-color: #0073aa;
            transform: translateY(-1px);
            box-shadow: 0 2px 8px rgba(0, 115, 170, 0.1);
        }
        
        .wc-plb-sortable li.ui-sortable-helper {
            background: #fff;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
            transform: rotate(2deg);
            z-index: 1000;
        }
        
        .wc-plb-sortable li::before {
            content: "⋮⋮";
            position: absolute;
            left: 8px;
            top: 50%;
            transform: translateY(-50%);
            color: #999;
            font-weight: bold;
            line-height: 0.8;
        }
        
        .wc-plb-element-header {
            font-weight: 600;
            margin-bottom: 10px;
            font-size: 14px;
            color: #23282d;
            margin-left: 20px;
        }
        
        .wc-plb-spacing-control {
            background: rgba(255, 255, 255, 0.8);
            padding: 10px;
            border-radius: 4px;
            margin-top: 10px;
            margin-left: 20px;
            border: 1px solid #e1e5e9;
        }
        
        .wc-plb-spacing-control label {
            display: flex;
            justify-content: space-between;
            align-items: center;
            font-size: 13px;
            margin: 0;
            color: #555;
        }
        
        .wc-plb-spacing-control input {
            width: 70px;
            padding: 4px 8px;
            text-align: right;
            border: 1px solid #ddd;
            border-radius: 3px;
        }
        
        .wc-plb-spacing-option.top-bottom {
            background: #f0f6fc;
            padding: 12px 15px;
            margin: 10px 0;
            border-radius: 4px;
            border-left: 4px solid #0073aa;
        }
        
        .wc-plb-spacing-option.top-bottom label {
            display: flex;
            justify-content: space-between;
            align-items: center;
            font-size: 13px;
            font-weight: 600;
            color: #0073aa;
            margin: 0;
        }
        
        .wc-plb-spacing-option.top-bottom input {
            width: 70px;
            padding: 4px 8px;
            text-align: right;
            border: 1px solid #0073aa;
            border-radius: 3px;
        }
        
        /* Section Dividers */
        .wc-plb-section-divider {
            border: 0 !important;
            height: 1px;
            margin: 25px 0 !important;
        }
        
        .wc-plb-divider {
            border-top: 1px solid #e1e5e9;
            margin: 0 -20px;
            height: 1px;
        }
        
        /* Color Options */
        .wc-plb-color-option {
            display: flex;
            align-items: center;
            margin-bottom: 12px;
            padding: 8px;
            background: #fff;
            border-radius: 4px;
            border: 1px solid #e1e5e9;
        }
        
        .wc-plb-color-option:last-child {
            margin-bottom: 0;
        }
        
        .wc-plb-color-option label {
            flex: 1;
            min-width: 140px;
            font-weight: 500;
            color: #23282d;
            margin: 0;
        }
        
        .wc-plb-color-picker {
            width: 100px !important;
        }
        
        /* Form Elements */
        .wc-plb-settings-container input[type="number"],
        .wc-plb-settings-container input[type="text"],
        .wc-plb-settings-container select {
            border: 1px solid #ddd;
            border-radius: 4px;
            padding: 8px 12px;
            font-size: 14px;
            transition: border-color 0.3s ease;
        }
        
        .wc-plb-settings-container input[type="number"]:focus,
        .wc-plb-settings-container input[type="text"]:focus,
        .wc-plb-settings-container select:focus {
            border-color: #0073aa;
            box-shadow: 0 0 0 1px #0073aa;
            outline: none;
        }
        
        .wc-plb-settings-container input[type="checkbox"] {
            margin-right: 8px;
            transform: scale(1.1);
        }
        
        .wc-plb-settings-container .description {
            color: #666;
            font-style: italic;
            margin-top: 5px;
            font-size: 13px;
            line-height: 1.4;
        }
        
        /* Shortcode Info Box */
        .wc-plb-shortcode-info {
            padding: 15px;
            background: #f8f9fa;
            border-radius: 6px;
            border: 1px solid #e1e5e9;
        }
        
        .wc-plb-shortcode-info p {
            margin-bottom: 10px;
            color: #555;
            font-size: 13px;
        }
        
        .wc-plb-shortcode-info input[type="text"] {
            width: 100%;
            margin: 8px 0;
            padding: 10px;
            font-family: monospace;
            background: #fff;
            border: 1px solid #ddd;
            border-radius: 4px;
            font-size: 12px;
        }
        
        .wc-plb-copy-btn {
            width: 100%;
            margin-top: 8px;
            padding: 8px 12px;
            background: #0073aa;
            color: white;
            border: 1px solid #0073aa;
            border-radius: 4px;
            cursor: pointer;
            transition: all 0.3s ease;
            font-size: 13px;
        }
        
        .wc-plb-copy-btn:hover {
            background: #005a87;
            border-color: #005a87;
            transform: translateY(-1px);
        }
        
        .wc-plb-copy-btn.success {
            background-color: #46b450 !important;
            border-color: #46b450 !important;
            color: white !important;
        }
        
        .wc-plb-copy-btn .dashicons {
            font-size: 14px;
            width: 14px;
            height: 14px;
            vertical-align: text-top;
            margin-right: 4px;
        }
        
        /* Responsive */
        @media (max-width: 768px) {
            .wc-plb-settings-container .form-table th,
            .wc-plb-settings-container .form-table td {
                display: block;
                width: 100%;
                padding: 10px;
            }
            
            .wc-plb-settings-container .form-table th {
                background: #f8f9fa;
                border-bottom: 1px solid #e1e5e9;
                margin-bottom: 0;
            }
        }
        </style>
        <?php
    }

    /**
     * Output Admin JavaScript
     */
    public function output_admin_js() {
        ?>
        <script>
        jQuery(document).ready(function($) {
            'use strict';

            // Initialize color pickers
            function initColorPickers() {
                $('.wc-plb-color-picker').wpColorPicker();
            }

            // Initialize sortable elements
            function initSortableElements() {
                $('#wc-plb-element-order').sortable({
                    placeholder: 'ui-state-highlight',
                    cursor: 'move',
                    opacity: 0.8,
                    tolerance: 'pointer',
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
                    }
                });
            }

            // Tab navigation
            function initTabNavigation() {
                $('.wc-plb-tabs-nav a').on('click', function(e) {
                    e.preventDefault();
                    
                    $('.wc-plb-tabs-nav a').removeClass('active');
                    $('.wc-plb-tab-content').removeClass('active');
                    
                    $(this).addClass('active');
                    
                    var tab = $(this).attr('href');
                    $(tab).addClass('active');
                    
                    localStorage.setItem('wc-plb-active-tab', tab);
                });
                
                // Restore active tab
                var activeTab = localStorage.getItem('wc-plb-active-tab');
                if (activeTab && $(activeTab).length) {
                    $('.wc-plb-tabs-nav a[href="' + activeTab + '"]').trigger('click');
                }
            }

            // Clipboard functionality
            function initClipboard() {
                $(document).on('click', '.wc-plb-copy-btn', function(e) {
                    e.preventDefault();
                    
                    var $btn = $(this);
                    var copyText = $btn.data('clipboard-text');
                    
                    var $temp = $('<textarea>');
                    $('body').append($temp);
                    $temp.val(copyText).select();
                    
                    try {
                        var successful = document.execCommand('copy');
                        
                        if (successful) {
                            var originalHtml = $btn.html();
                            $btn.html('<span class="dashicons dashicons-yes"></span> Kopiert!');
                            $btn.addClass('success');
                            
                            setTimeout(function() {
                                $btn.html(originalHtml);
                                $btn.removeClass('success');
                            }, 2000);
                        }
                    } catch (err) {
                        var $input = $btn.siblings('input[type="text"]');
                        if ($input.length) {
                            $input.select();
                            alert('Bitte manuell kopieren (Ctrl+C / Cmd+C)');
                        }
                    }
                    
                    $temp.remove();
                });
            }

            // Initialize all functionality
            initColorPickers();
            initSortableElements();
            initTabNavigation();
            initClipboard();
        });
        </script>
        <?php
    }

    /**
     * Output Frontend CSS
     */
    public function output_frontend_css() {
        ?>
        <style>
        /* WooCommerce Product Layout Builder - Frontend Styles */
        
        /* Main Grid Layout with perfect alignment */
        .wc-plb-product-grid {
            display: grid;
            grid-template-columns: repeat(var(--desktop-cols), 1fr);
            gap: 20px;
            margin: 20px 0;
            align-items: stretch; /* Ensures all items in a row have the same height */
            justify-items: stretch;
        }

        /* Responsive Grid */
        @media (max-width: 1024px) {
            .wc-plb-product-grid {
                grid-template-columns: repeat(var(--tablet-cols), 1fr);
            }
        }
        @media (max-width: 768px) {
            .wc-plb-product-grid {
                grid-template-columns: repeat(var(--mobile-cols), 1fr);
                gap: 15px;
            }
        }

        /* Enhanced product container structure */
        .wc-plb-product {
            display: flex;
            flex-direction: column;
            height: 100%;
            background: #fff;
            border: 1px solid #ddd;
            border-radius: 8px;
            overflow: hidden;
            transition: all 0.3s ease;
            position: relative;
        }
        
        .wc-plb-product:hover {
            box-shadow: 0 8px 25px rgba(0, 0, 0, 0.12);
            transform: translateY(-2px);
        }
        
        .wc-plb-product-content {
            flex: 1;
            display: flex;
            flex-direction: column;
            padding: 15px;
            position: relative;
        }
        
        /* Fixed height image container for perfect alignment */
        .wc-plb-product-image {
            position: relative;
            width: 100%;
            height: 200px;
            margin-bottom: 15px;
            overflow: hidden;
            border-radius: 6px;
            background: #f8f8f8;
        }
        
        .wc-plb-product-image img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            object-position: center;
            transition: transform 0.3s ease;
        }
        
        .wc-plb-product:hover .wc-plb-product-image img {
            transform: scale(1.05);
        }

        /* Sale Badge positioned absolutely on image */
        .wc-plb-product-sale-badge {
            position: absolute;
            top: 12px;
            left: 12px;
            z-index: 3;
            padding: 6px 10px;
            border-radius: 4px;
            font-size: 11px;
            font-weight: bold;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.15);
        }

        /* Consistent title heights across rows */
        .wc-plb-product-title {
            font-weight: 600;
            line-height: 1.4;
            margin: 0 0 12px 0;
            min-height: 2.8em; /* Space for 2 lines */
            display: -webkit-box;
            -webkit-line-clamp: 2;
            -webkit-box-orient: vertical;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        /* Aligned price display */
        .wc-plb-product-price {
            font-weight: bold;
            margin: 0 0 12px 0;
            min-height: 1.8em;
            display: flex;
            align-items: center;
        }

        /* Consistent rating container heights */
        .wc-plb-product-rating-container {
            margin: 0 0 12px 0;
            min-height: 24px;
            display: flex;
            align-items: center;
        }
        
        .wc-plb-product-rating {
            display: flex;
            align-items: center;
            gap: 8px;
        }
        
        .wc-plb-product-rating .star-rating {
            float: none !important;
            display: inline-block;
            vertical-align: middle;
        }

        /* Stock status alignment */
        .wc-plb-product-stock-status {
            margin: 0 0 12px 0;
            min-height: 1.5em;
            display: flex;
            align-items: center;
            font-size: 14px;
        }

        /* Attributes with consistent baseline */
        .wc-plb-product-attributes {
            margin: 0 0 15px 0;
            min-height: 32px;
            display: flex;
            flex-wrap: wrap;
            gap: 6px;
            align-items: flex-start;
        }
        
        .wc-plb-attribute-label {
            display: inline-block;
            padding: 4px 8px;
            border-radius: 3px;
            font-size: 12px;
            border: 1px solid;
        }

        /* Description with controlled overflow */
        .wc-plb-product-description {
            flex: 1;
            margin: 0 0 15px 0;
            line-height: 1.6;
            font-size: 14px;
        }

        /* More info section */
        .wc-plb-more-info-button,
        .wc-plb-more-info-text {
            margin: auto 0 10px 0;
            text-align: center;
        }

        /* Add to cart button - pushed to bottom */
        .wc-plb-product-add-to-cart {
            margin-top: auto;
            padding: 10px 0 15px 0;
        }
        
        .wc-plb-product-add-to-cart .button {
            width: 100%;
            padding: 12px 8px;
            text-align: center;
            border-radius: 6px;
            font-weight: 600;
            text-decoration: none;
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
            justify-content: center;
            min-height: 44px;
            line-height: 1.2;
            word-wrap: break-word;
        }
        
        .wc-plb-product-add-to-cart .button:hover {
            transform: translateY(-1px);
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
        }

        /* Responsive adjustments for different column counts */
        @media (min-width: 1200px) {
            .wc-plb-product-grid[style*="--desktop-cols: 6"] .wc-plb-product-image {
                height: 160px;
            }
            
            .wc-plb-product-grid[style*="--desktop-cols: 6"] .wc-plb-product-title {
                font-size: 14px;
                min-height: 3.2em;
            }
            
            .wc-plb-product-grid[style*="--desktop-cols: 6"] .wc-plb-product-add-to-cart .button {
                font-size: 12px;
                padding: 8px 4px;
            }
        }
        
        /* Mobile optimizations */
        @media (max-width: 768px) {
            .wc-plb-product-content {
                padding: 12px;
            }
            
            .wc-plb-product-image {
                height: 180px;
                margin-bottom: 12px;
            }
            
            .wc-plb-product-title {
                font-size: 16px;
                min-height: 2.4em;
            }
            
            .wc-plb-product-add-to-cart .button {
                padding: 10px 6px;
                min-height: 40px;
            }
        }

        /* Filters */
        .wc-plb-filters {
            display: flex;
            flex-wrap: wrap;
            gap: 15px;
            margin: 20px 0;
            padding: 15px;
            background: #f9f9f9;
            border-radius: 4px;
            border: 1px solid #ddd;
        }

        .wc-plb-filter {
            display: flex;
            flex-direction: column;
            gap: 5px;
        }

        .wc-plb-filter label {
            font-weight: 600;
            font-size: 14px;
            color: #333;
        }

        .wc-plb-filter-select {
            padding: 8px 12px;
            border: 1px solid #ddd;
            border-radius: 3px;
            background: #fff;
            min-width: 150px;
        }

        /* Pagination */
        .wc-plb-pagination {
            margin: 30px 0;
            text-align: center;
        }

        .wc-plb-pagination ul.page-numbers {
            display: inline-flex;
            list-style: none;
            padding: 0;
            margin: 0;
            gap: 5px;
            flex-wrap: wrap;
            justify-content: center;
        }

        .wc-plb-pagination ul.page-numbers li {
            margin: 0;
        }

        .wc-plb-pagination ul.page-numbers li a,
        .wc-plb-pagination ul.page-numbers li span {
            padding: 10px 15px;
            border-radius: 6px;
            text-decoration: none;
            border: 1px solid #ddd;
            background-color: #f9f9f9;
            color: #333;
            transition: all 0.3s ease;
            display: inline-block;
            min-width: 40px;
            text-align: center;
            font-weight: 500;
        }

        .wc-plb-pagination ul.page-numbers li a:hover,
        .wc-plb-pagination ul.page-numbers li span.current {
            background-color: #0073aa;
            color: #fff;
            border-color: #0073aa;
            transform: translateY(-1px);
        }

        .wc-plb-pagination ul.page-numbers li span.disabled {
            opacity: 0.5;
            cursor: not-allowed;
            transform: none !important;
        }

        /* No products message */
        .wc-plb-no-products {
            text-align: center;
            padding: 40px 20px;
            color: #666;
            font-style: italic;
            border: 2px dashed #ddd;
            border-radius: 8px;
            background: #f9f9f9;
        }

        /* Loading states */
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

        .wc-plb-product-grid.loading {
            opacity: 0.6;
            pointer-events: none;
        }
        </style>
        <?php
    }

    /**
     * Output Frontend JavaScript
     */
    public function output_frontend_js() {
        ?>
        <script>
        jQuery(function($) {
            'use strict';

            // Main function to equalize product heights within each row
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
                        const $rowProducts = $(rowProducts);
                        const images = $rowProducts.find('img');
                        
                        if (images.length === 0) {
                            equalizeRowHeights($rowProducts);
                        } else {
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

            // Equalize heights for products in a single row
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

            // Load products via AJAX with filters
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
                        
                        if ($container.find('.wc-plb-loading-overlay').length === 0) {
                            $container.append('<div class="wc-plb-loading-overlay"><div class="wc-plb-spinner"></div></div>');
                        }
                    },
                    success: function(response) {
                        if (response.success) {
                            $container.replaceWith(response.data.html);
                            $pagination.replaceWith(response.data.pagination);
                            
                            // Update URL
                            if (history.pushState) {
                                let basePath = window.location.pathname.replace(/\/page\/\d+\/$/, '');
                                basePath = basePath.replace(/\/$/, '');
                                
                                let newUrl = basePath;
                                if (data.page > 1) {
                                    newUrl += `/page/${data.page}/`;
                                } else {
                                    newUrl += '/';
                                }
                                
                                newUrl = newUrl.replace(/\/+/g, '/');
                                window.history.pushState({path: newUrl}, '', newUrl);
                            }
                            
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
                        $('.wc-plb-loading-overlay').remove();
                        $('.loading').removeClass('loading');
                    }
                });
            }

            // Pagination click handler
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

            // Filter change handler
            $(document).on('change', '.wc-plb-filter-select', function() {
                const $filters = $(this).closest('.wc-plb-filters');
                const layoutId = $filters.data('layout-id');
                
                loadProducts({
                    page: 1,
                    category: $filters.find('.wc-plb-category-filter select').val(),
                    price_range: $filters.find('.wc-plb-price-filter select').val(),
                    rating: $filters.find('.wc-plb-rating-filter select').val(),
                    layout_id: layoutId
                });
            });

            // Window resize handler with debouncing
            let resizeTimer;
            $(window).on('resize', function() {
                clearTimeout(resizeTimer);
                resizeTimer = setTimeout(function() {
                    equalizeProductHeights();
                }, 250);
            });

            // Products loaded event handler
            $(document).on('wc_plb_products_loaded', function() {
                setTimeout(function() {
                    equalizeProductHeights();
                }, 100);
                
                setTimeout(function() {
                    equalizeProductHeights();
                }, 500);
            });

            // Initialize when DOM is ready
            $(document).ready(function() {
                console.log('WC Product Layout Builder: Initializing...');
                
                setTimeout(function() {
                    equalizeProductHeights();
                }, 100);
                
                setTimeout(function() {
                    equalizeProductHeights();
                }, 1000);
                
                console.log('WC Product Layout Builder: Initialized');
            });

            // Make functions globally available for debugging
            window.wcPlbFrontend = {
                equalizeProductHeights: equalizeProductHeights,
                loadProducts: loadProducts
            };
        });
        </script>
        <?php
    }

    /**
     * Liefert das Default-Config-Array.
     */
    private function get_defaults() {
        return array(
            'columns_desktop'             => 4,
            'columns_tablet'              => 3,
            'columns_mobile'              => 1,
            'per_page'                    => 12,
            'show_title'                  => 1,
            'show_image'                  => 1,
            'show_price'                  => 1,
            'show_rating'                 => 1,
            'show_rating_count'           => 1,
            'rating_count_prefix'         => '(',
            'rating_count_suffix'         => ')',
            'rating_count_position'       => 'after',
            'rating_count_color'          => '#666666',
            'show_add_to_cart'            => 1,
            'show_sale_badge'             => 1,
            'show_stock_status'           => 0,
            'show_attributes'             => 0,
            'show_description'            => 0,
            'show_more_info'              => 0,
            'description_length'          => 300,
            'description_max_height'      => '',
            'description_overflow'        => 'truncate',
            'excerpt_type'                => 'short',
            'fallback_option'             => 'use_long',
            'allow_html'                  => 1,
            'element_order'               => array(
                'image','sale_badge','title','price',
                'rating','add_to_cart','stock_status',
                'attributes','description','more_info'
            ),
            'title_color'                 => '#333333',
            'title_font_size'             => 16,
            'attribute_display'           => 'text',
            'text_color'                  => '#333333',
            'button_bg_color'             => '#96588a',
            'button_text_color'           => '#ffffff',
            'button_border_color'         => '#96588a',
            'sale_badge_text' => __('Sale!', 'wc-product-layout'), 
            'sale_badge_color'            => '#77a464',
            'sale_badge_text_color'       => '#ffffff',
            'sale_badge_border_color'     => '#77a464',
            'stock_status_color'          => '#333333',
            'price_color'                 => '#333333',
            'price_font_size'             => 16,
            'attribute_text_color'        => '#333333',
            'attribute_bg_color'          => '#f5f5f5',
            'attribute_border_color'      => '#dddddd',
            'more_info_text'              => __('Mehr Infos', 'wc-product-layout'),
            'more_info_display'           => 'text',
            'more_info_color'             => '#333333',
            'more_info_bg_color'          => '#f5f5f5',
            'more_info_border_color'      => '#dddddd',
            'more_info_font_size'         => 14,
            'rating_star_filled_color'    => '#ffb100',
            'rating_star_empty_color'     => '#cccccc',
            'pagination_bg_color'         => '#f9f9f9',
            'pagination_text_color'       => '#333',
            'pagination_active_bg'        => '#0073aa',
            'pagination_active_text'      => '#fff',            
            'element_spacing' => array(
                'top' => 0,
                'image' => array('after' => 15),
                'title' => array('after' => 10),
                'price' => array('after' => 10),
                'rating' => array('after' => 10),
                'add_to_cart' => array('after' => 15),
                'sale_badge' => array('after' => 0),
                'stock_status' => array('after' => 10),
                'attributes' => array('after' => 15),
                'description' => array('after' => 15),
                'more_info' => array('after' => 15),
                'bottom' => 0
            ),            
            'filter_by_category'          => 0,
            'filter_by_price'             => 0,
            'filter_by_rating'            => 0,
            'filter_by_attribute'         => array(),
            'default_category'            => '',
            'default_price_range'         => '',
            'default_rating'              => '',
            'default_attributes'          => array(),
        );
    }

    /**
     * Meta Boxen hinzufügen
     */
    public function add_meta_boxes() {
        add_meta_box(
            'wc_plb_layout_config', 
            __('Layout-Einstellungen', 'wc-product-layout'), 
            array($this, 'render_layout_config'), 
            WC_PLB_POST_TYPE, 
            'normal', 
            'high'
        );
        
        add_meta_box(
            'wc_plb_shortcode_info', 
            __('Shortcode', 'wc-product-layout'), 
            array($this, 'render_shortcode_info'), 
            WC_PLB_POST_TYPE, 
            'side', 
            'default'
        );
    }

    /**
     * Shortcode Info Box rendern
     */
    public function render_shortcode_info($post) {
        $shortcode = '[wc_product_layout id="' . esc_attr($post->ID) . '"]';
        ?>
        <div class="wc-plb-shortcode-info">
            <p><?php _e('Verwenden Sie diesen Shortcode, um dieses Layout in Ihren Seiten oder Beiträgen anzuzeigen:', 'wc-product-layout'); ?></p>
            <input type="text" value="<?php echo esc_attr($shortcode); ?>" readonly style="width:100%;">
            <button class="button button-small wc-plb-copy-btn" data-clipboard-text="<?php echo esc_attr($shortcode); ?>">
                <span class="dashicons dashicons-clipboard"></span> <?php _e('Kopieren', 'wc-product-layout'); ?>
            </button>
        </div>
        <?php
    }

    /**
     * Layout-Konfiguration rendern (vereinfachte Version)
     */
    public function render_layout_config($post) {
        wp_nonce_field('wc_plb_save_layout', 'wc_plb_nonce');
        
        $config = get_post_meta($post->ID, '_wc_plb_config', true);
        $defaults = $this->get_defaults();
        $config = wp_parse_args($config, $defaults);
        
        // Produktkategorien für Filter
        $categories = get_terms(array(
            'taxonomy' => 'product_cat',
            'hide_empty' => true
        ));
        ?>
        
        <div class="wc-plb-settings-container">
            <!-- Tabs Navigation -->
            <div class="wc-plb-tabs-nav">
                <a href="#wc-plb-layout-settings" class="active"><?php _e('Layout', 'wc-product-layout'); ?></a>
                <a href="#wc-plb-display-settings"><?php _e('Anzeige', 'wc-product-layout'); ?></a>
                <a href="#wc-plb-filter-settings"><?php _e('Filter', 'wc-product-layout'); ?></a>
            </div>
            
            <!-- Layout Einstellungen -->
            <div id="wc-plb-layout-settings" class="wc-plb-tab-content active">
                <table class="form-table">
                    <tr>
                        <th><?php _e('Spalten', 'wc-product-layout'); ?></th>
                        <td>
                            <label><?php _e('Desktop:', 'wc-product-layout'); ?>
                                <input type="number" name="wc_plb_config[columns_desktop]" value="<?php echo esc_attr($config['columns_desktop']); ?>" min="1" max="6">
                            </label>
                            <label><?php _e('Tablet:', 'wc-product-layout'); ?>
                                <input type="number" name="wc_plb_config[columns_tablet]" value="<?php echo esc_attr($config['columns_tablet']); ?>" min="1" max="4">
                            </label>
                            <label><?php _e('Mobil:', 'wc-product-layout'); ?>
                                <input type="number" name="wc_plb_config[columns_mobile]" value="<?php echo esc_attr($config['columns_mobile']); ?>" min="1" max="2">
                            </label>
                        </td>
                    </tr>
                    <tr>
                        <th><?php _e('Produkte pro Seite', 'wc-product-layout'); ?></th>
                        <td>
                            <input type="number" name="wc_plb_config[per_page]" value="<?php echo esc_attr($config['per_page']); ?>" min="1" max="100">
                        </td>
                    </tr>
                    <tr>
                        <th><?php _e('Elementreihenfolge', 'wc-product-layout'); ?></th>
                        <td>
                            <ul id="wc-plb-element-order" class="wc-plb-sortable">
                                <?php foreach ($config['element_order'] as $element) : ?>
                                    <li class="ui-state-default" data-element="<?php echo esc_attr($element); ?>">
                                        <input type="hidden" name="wc_plb_config[element_order][]" value="<?php echo esc_attr($element); ?>">
                                        
                                        <div class="wc-plb-element-header">
                                            <?php
                                            $element_labels = array(
                                                'image' => __('Bild', 'wc-product-layout'),
                                                'title' => __('Titel', 'wc-product-layout'),
                                                'price' => __('Preis', 'wc-product-layout'),
                                                'rating' => __('Bewertung', 'wc-product-layout'),
                                                'add_to_cart' => __('In den Warenkorb', 'wc-product-layout'),
                                                'sale_badge' => __('Verkaufs-Badge', 'wc-product-layout'),
                                                'stock_status' => __('Lagerstatus', 'wc-product-layout'),
                                                'attributes' => __('Attribute', 'wc-product-layout'),
                                                'description' => __('Beschreibung', 'wc-product-layout'),
                                                'more_info' => __('Mehr Infos', 'wc-product-layout')
                                            );
                                            echo esc_html($element_labels[$element] ?? $element);
                                            ?>
                                        </div>
                                    </li>
                                <?php endforeach; ?>
                            </ul>
                            <p class="description"><?php _e('Ziehen Sie die Elemente per Drag & Drop in die gewünschte Reihenfolge', 'wc-product-layout'); ?></p>
                        </td>
                    </tr>
                </table>
            </div>
            
            <!-- Anzeige Einstellungen -->
            <div id="wc-plb-display-settings" class="wc-plb-tab-content">
                <table class="form-table">
                    <tr>
                        <th><?php _e('Titel anzeigen', 'wc-product-layout'); ?></th>
                        <td>
                            <label>
                                <input type="checkbox" name="wc_plb_config[show_title]" value="1" <?php checked($config['show_title'], 1); ?>>
                                <?php _e('Ja', 'wc-product-layout'); ?>
                            </label>
                        </td>
                    </tr>
                    
                    <tr>
                        <th><?php _e('Titel Farbe', 'wc-product-layout'); ?></th>
                        <td>
                            <input type="text" name="wc_plb_config[title_color]" value="<?php echo esc_attr($config['title_color']); ?>" class="wc-plb-color-picker">
                        </td>
                    </tr>

                    <tr>
                        <th><?php _e('Bild anzeigen', 'wc-product-layout'); ?></th>
                        <td>
                            <label>
                                <input type="checkbox" name="wc_plb_config[show_image]" value="1" <?php checked($config['show_image'], 1); ?>>
                                <?php _e('Ja', 'wc-product-layout'); ?>
                            </label>
                        </td>
                    </tr>
            
                    <tr>
                        <th><?php _e('Preis anzeigen', 'wc-product-layout'); ?></th>
                        <td>
                            <label>
                                <input type="checkbox" name="wc_plb_config[show_price]" value="1" <?php checked($config['show_price'], 1); ?>>
                                <?php _e('Ja', 'wc-product-layout'); ?>
                            </label>
                        </td>
                    </tr>

                    <tr>
                        <th><?php _e('Preis Farbe', 'wc-product-layout'); ?></th>
                        <td>
                            <input type="text" name="wc_plb_config[price_color]" value="<?php echo esc_attr($config['price_color']); ?>" class="wc-plb-color-picker">
                        </td>
                    </tr>

                    <tr>
                        <th><?php _e('Bewertung anzeigen', 'wc-product-layout'); ?></th>
                        <td>
                            <label>
                                <input type="checkbox" name="wc_plb_config[show_rating]" value="1" <?php checked($config['show_rating'], 1); ?>>
                                <?php _e('Ja', 'wc-product-layout'); ?>
                            </label>
                        </td>
                    </tr>

                    <tr>
                        <th><?php _e('In den Warenkorb-Button anzeigen', 'wc-product-layout'); ?></th>
                        <td>
                            <label>
                                <input type="checkbox" name="wc_plb_config[show_add_to_cart]" value="1" <?php checked($config['show_add_to_cart'], 1); ?>>
                                <?php _e('Ja', 'wc-product-layout'); ?>
                            </label>
                        </td>
                    </tr>

                    <tr>
                        <th><?php _e('Button Hintergrundfarbe', 'wc-product-layout'); ?></th>
                        <td>
                            <input type="text" name="wc_plb_config[button_bg_color]" value="<?php echo esc_attr($config['button_bg_color']); ?>" class="wc-plb-color-picker">
                        </td>
                    </tr>

                    <tr>
                        <th><?php _e('Button Textfarbe', 'wc-product-layout'); ?></th>
                        <td>
                            <input type="text" name="wc_plb_config[button_text_color]" value="<?php echo esc_attr($config['button_text_color']); ?>" class="wc-plb-color-picker">
                        </td>
                    </tr>

                    <tr>
                        <th><?php _e('Verkaufs-Badge anzeigen', 'wc-product-layout'); ?></th>
                        <td>
                            <label>
                                <input type="checkbox" name="wc_plb_config[show_sale_badge]" value="1" <?php checked($config['show_sale_badge'], 1); ?>>
                                <?php _e('Ja', 'wc-product-layout'); ?>
                            </label>
                        </td>
                    </tr>

                    <tr>
                        <th><?php _e('Sale Badge Farbe', 'wc-product-layout'); ?></th>
                        <td>
                            <input type="text" name="wc_plb_config[sale_badge_color]" value="<?php echo esc_attr($config['sale_badge_color']); ?>" class="wc-plb-color-picker">
                        </td>
                    </tr>

                    <tr>
                        <th><?php _e('Sale Badge Text', 'wc-product-layout'); ?></th>
                        <td>
                            <input type="text" name="wc_plb_config[sale_badge_text]" value="<?php echo esc_attr($config['sale_badge_text']); ?>">
                        </td>
                    </tr>
                </table>
            </div>

            <!-- Filter Einstellungen -->
            <div id="wc-plb-filter-settings" class="wc-plb-tab-content">
                <table class="form-table">
                    <tr>
                        <th><?php _e('Filter aktivieren', 'wc-product-layout'); ?></th>
                        <td>
                            <label><input type="checkbox" name="wc_plb_config[filter_by_category]" value="1" <?php checked($config['filter_by_category'], 1); ?>> <?php _e('Nach Kategorie filtern', 'wc-product-layout'); ?></label><br>
                            <label><input type="checkbox" name="wc_plb_config[filter_by_price]" value="1" <?php checked($config['filter_by_price'], 1); ?>> <?php _e('Nach Preis filtern', 'wc-product-layout'); ?></label><br>
                            <label><input type="checkbox" name="wc_plb_config[filter_by_rating]" value="1" <?php checked($config['filter_by_rating'], 1); ?>> <?php _e('Nach Bewertung filtern', 'wc-product-layout'); ?></label>
                        </td>
                    </tr>
                    
                    <tr>
                        <th><?php _e('Standardfilter', 'wc-product-layout'); ?></th>
                        <td>
                            <?php if (!empty($categories)) : ?>
                                <label><?php _e('Standard-Kategorie:', 'wc-product-layout'); ?>
                                    <select name="wc_plb_config[default_category]">
                                        <option value=""><?php _e('Keine', 'wc-product-layout'); ?></option>
                                        <?php foreach ($categories as $category) : ?>
                                            <option value="<?php echo esc_attr($category->slug); ?>" <?php selected($config['default_category'], $category->slug); ?>>
                                                <?php echo esc_html($category->name); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </label>
                            <?php endif; ?>
                        </td>
                    </tr>
                </table>
            </div>
        </div>
        <?php
    }

    /**
     * Bereinigt die Eingabedaten (vereinfachte Version)
     */
    private function sanitize_layout_settings($raw) {
        $clean = [];
    
        // Checkboxen
        $checkboxes = [
            'show_title','show_image','show_price','show_rating',
            'show_add_to_cart','show_sale_badge','show_stock_status',
            'show_attributes','show_description','show_more_info',
            'filter_by_category','filter_by_price','filter_by_rating',
            'show_rating_count'
        ];
        foreach ($checkboxes as $key) {
            $clean[$key] = isset($raw[$key]) ? 1 : 0;
        }
        
        // Zahlen
        $numbers = ['columns_desktop','columns_tablet','columns_mobile','per_page','description_length','price_font_size','more_info_font_size'];
        foreach ($numbers as $key) {
            $clean[$key] = isset($raw[$key]) ? absint($raw[$key]) : 0;
        }
    
        // Elementreihenfolge
        if (!empty($raw['element_order']) && is_array($raw['element_order'])) {
            $allowed = [
                'image','sale_badge','title','price',
                'rating','add_to_cart','stock_status',
                'attributes','description','more_info'
            ];
            $clean['element_order'] = array_values(array_intersect(
                array_map('sanitize_text_field', $raw['element_order']),
                $allowed
            ));
        }
        
        // Farben
        $colors = ['title_color', 'text_color', 'button_bg_color', 'button_text_color', 'sale_badge_color', 'price_color'];
        foreach ($colors as $key) {
            if (!empty($raw[$key])) {
                $clean[$key] = sanitize_hex_color($raw[$key]);
            }
        }
        
        // Texte
        $clean['sale_badge_text'] = isset($raw['sale_badge_text']) 
            ? sanitize_text_field($raw['sale_badge_text']) 
            : __('Sale!', 'wc-product-layout');
            
        $clean['default_category'] = isset($raw['default_category']) 
            ? sanitize_text_field($raw['default_category']) 
            : '';
    
        return $clean;
    }

    /**
     * Layout-Einstellungen speichern
     */
    public function save_layout_settings($post_id) {
        // Auto-Saves überspringen
        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
            return;
        }
        // Nonce prüfen
        if (!isset($_POST['wc_plb_nonce']) || !wp_verify_nonce($_POST['wc_plb_nonce'], 'wc_plb_save_layout')) {
            return;
        }
        // Berechtigung prüfen
        if (!current_user_can('edit_post', $post_id)) {
            return;
        }
        // Wenn Config-Daten übermittelt wurden, speichere sie
        if (isset($_POST['wc_plb_config'])) {
            update_post_meta(
                $post_id,
                '_wc_plb_config',
                $this->sanitize_layout_settings($_POST['wc_plb_config'])
            );
        }
    }

    /**
     * Shortcode rendern
     */
    public function shortcode_render($atts) {
        $atts = shortcode_atts(array(
            'id' => 0,
            'name' => ''
        ), $atts, 'wc_product_layout');
        
        // Zuerst nach ID suchen
        if ($atts['id']) {
            $layout = get_post($atts['id']);
        } 
        // Falls keine ID, nach Name suchen (für alte Shortcodes)
        elseif ($atts['name']) {
            $layout = get_page_by_path($atts['name'], OBJECT, WC_PLB_POST_TYPE);
        }
        
        if (!$layout || $layout->post_type != WC_PLB_POST_TYPE) {
            return '';
        }
        
        $config = get_post_meta($layout->ID, '_wc_plb_config', true);
        
        $defaults = $this->get_defaults();
        
        $config = wp_parse_args($config, $defaults);
        
        // Aktuelle Seite ermitteln
        $page = isset($_GET['plb_page']) ? absint($_GET['plb_page']) : 1;
        if ($page < 1) $page = 1;
        
        
        // Produkte abfragen
        $args = array(
            'post_type' => 'product',
            'posts_per_page' => $config['per_page'],
            'paged' => $page,
        );
        
        // Filter anwenden
        if ($config['filter_by_category'] && $config['default_category']) {
            $args['tax_query'][] = array(
                'taxonomy' => 'product_cat',
                'field' => 'slug',
                'terms' => $config['default_category']
            );
        }
        
        $products = new WP_Query($args);
        
        ob_start();
        
        // Filter anzeigen
        if ($config['filter_by_category'] || $config['filter_by_price'] || $config['filter_by_rating']) {
            echo '<div class="wc-plb-filters" data-layout-id="' . esc_attr($layout->ID) . '">';
            
            // Kategoriefilter
            if ($config['filter_by_category']) {
                $categories = get_terms(array(
                    'taxonomy' => 'product_cat',
                    'hide_empty' => true
                ));
                
                if (!empty($categories)) {
                    echo '<div class="wc-plb-filter wc-plb-category-filter">';
                    echo '<label>' . __('Kategorie:', 'wc-product-layout') . '</label>';
                    echo '<select class="wc-plb-filter-select">';
                    echo '<option value="">' . __('Alle Kategorien', 'wc-product-layout') . '</option>';
                    
                    foreach ($categories as $category) {
                        $selected = ($config['default_category'] == $category->slug) ? 'selected' : '';
                        echo '<option value="' . esc_attr($category->slug) . '" ' . $selected . '>' . esc_html($category->name) . '</option>';
                    }
                    
                    echo '</select>';
                    echo '</div>';
                }
            }
            
            echo '</div>'; // .wc-plb-filters schließen
        }
        
        // Produktgrid anzeigen
        if ($products->have_posts()) {
            echo '<div class="wc-plb-product-grid" data-layout-id="' . esc_attr($layout->ID) . '" 
                 style="--desktop-cols: ' . esc_attr($config['columns_desktop']) . ';
                        --tablet-cols: ' . esc_attr($config['columns_tablet']) . ';
                        --mobile-cols: ' . esc_attr($config['columns_mobile']) . ';">';
            
            while ($products->have_posts()) {
                $products->the_post();
                global $product;
                
                // Beginn Produkt-Box
                echo '<div class="wc-plb-product">';
                
                    // Content-Bereich
                    echo '<div class="wc-plb-product-content">';
                    
                    // Elemente in der definierten Reihenfolge anzeigen
                    foreach ($config['element_order'] as $element) {
                        $this->render_product_element($element, $config, $product);
                    }

                    echo '</div>'; // .wc-plb-product-content

                echo '</div>'; // .wc-plb-product
                // Ende Produkt-Box
            }
            
            echo '</div>'; // .wc-plb-product-grid
            
            // Pagination anzeigen
            $this->render_pagination($products, $layout->ID);
            
            wp_reset_postdata();
        } else {
            echo '<p class="wc-plb-no-products">' . __('Keine Produkte gefunden.', 'wc-product-layout') . '</p>';
        }

        // Generate inline CSS for customization
        $this->generate_dynamic_css($config);

        return ob_get_clean();
    }

    /**
     * Render individual product element
     */
    private function render_product_element($element, $config, $product) {
        switch ($element) {
            case 'image':
                if ($config['show_image']) {
                    echo '<div class="wc-plb-product-image">';
                    echo woocommerce_get_product_thumbnail();
                    
                    // Sale Badge direkt auf dem Bild
                    if ($config['show_sale_badge'] && $product->is_on_sale()) {
                        echo '<div class="wc-plb-product-sale-badge">';
                        echo esc_html($config['sale_badge_text']);  
                        echo '</div>';
                    }
                    
                    echo '</div>';
                }
                break;

            case 'title':
                if ($config['show_title']) {
                    echo '<h3 class="wc-plb-product-title">' . get_the_title() . '</h3>';
                }
                break;
                
            case 'price':
                if ($config['show_price']) {
                    echo '<div class="wc-plb-product-price">' . $product->get_price_html() . '</div>';
                }
                break;
                
            case 'rating':
                if ($config['show_rating']) {
                    $rating_count = $product->get_rating_count();
                    $average = $product->get_average_rating();
            
                    if ($rating_count > 0) {
                        echo '<div class="wc-plb-product-rating-container">';
                        echo '<div class="wc-plb-product-rating">';
                        echo wc_get_rating_html($average, $rating_count);
                        echo '</div>';
                        echo '</div>';
                    }
                }
                break;
                
            case 'add_to_cart':
                if ($config['show_add_to_cart']) {
                    echo '<div class="wc-plb-product-add-to-cart">';
                    echo '<a href="' . esc_url($product->add_to_cart_url()) . '" class="button">';
                    echo esc_html($product->add_to_cart_text());
                    echo '</a>';
                    echo '</div>';
                }
                break;
        }
    }

    /**
     * Render pagination
     */
    private function render_pagination($products, $layout_id) {
        $current_page = max(1, $products->query_vars['paged']);
        $total_pages = $products->max_num_pages;
        
        if ($total_pages > 1) {
            echo '<div class="wc-plb-pagination" data-layout-id="' . esc_attr($layout_id) . '">';
            echo '<ul class="page-numbers">';
            
            // Seitenzahlen
            for ($i = 1; $i <= $total_pages; $i++) {
                echo '<li>';
                if ($i == $current_page) {
                    echo '<span class="page-numbers current" data-page="' . $i . '">' . $i . '</span>';
                } else {
                    echo '<a class="page-numbers" href="#" data-page="' . $i . '">' . $i . '</a>';
                }
                echo '</li>';
            }
            
            echo '</ul>';
            echo '</div>';
        }
    }

    /**
     * Generate dynamic CSS based on configuration
     */
    private function generate_dynamic_css($config) {
        echo '<style>
            .wc-plb-product-title {
                color: ' . esc_attr($config['title_color']) . ';
            }
            
            .wc-plb-product-price {
                color: ' . esc_attr($config['price_color']) . ';
            }
            
            .wc-plb-product-add-to-cart .button {
                background-color: ' . esc_attr($config['button_bg_color']) . ';
                color: ' . esc_attr($config['button_text_color']) . ';
                border-color: ' . esc_attr($config['button_bg_color']) . ';
            }
            
            .wc-plb-product-sale-badge {
                background-color: ' . esc_attr($config['sale_badge_color']) . ';
                color: #ffffff;
                border-color: ' . esc_attr($config['sale_badge_color']) . ';
            }
        </style>';
    }

    /**
     * AJAX Produktfilter (vereinfachte Version)
     */
    public function ajax_filter_products() {
        check_ajax_referer('wc_plb_nonce', 'nonce');
        
        $layout_id = isset($_POST['layout_id']) ? absint($_POST['layout_id']) : 0;
        $page = isset($_POST['page']) ? absint($_POST['page']) : 1;
        $category = isset($_POST['category']) ? sanitize_text_field($_POST['category']) : '';
        
        $config = get_post_meta($layout_id, '_wc_plb_config', true);
        $defaults = $this->get_defaults();
        $config = wp_parse_args($config, $defaults);
        
        // Produktabfrage vorbereiten
        $args = array(
            'post_type' => 'product',
            'posts_per_page' => $config['per_page'],
            'paged' => $page
        );
        
        // Kategoriefilter
        if (!empty($category)) {
            $args['tax_query'][] = array(
                'taxonomy' => 'product_cat',
                'field' => 'slug',
                'terms' => $category
            );
        }
        
        // Produktabfrage durchführen
        $products = new WP_Query($args);
        
        // Ausgabe vorbereiten
        ob_start();
        
        if ($products->have_posts()) {
            echo '<div class="wc-plb-product-grid" data-layout-id="' . esc_attr($layout_id) . '" 
                 style="--desktop-cols: ' . esc_attr($config['columns_desktop']) . ';
                        --tablet-cols: ' . esc_attr($config['columns_tablet']) . ';
                        --mobile-cols: ' . esc_attr($config['columns_mobile']) . ';">';
            
            while ($products->have_posts()) {
                $products->the_post();
                global $product;
                
                // Beginn Produkt-Box
                echo '<div class="wc-plb-product">';
                    echo '<div class="wc-plb-product-content">';
                    
                    // Elemente in der definierten Reihenfolge anzeigen
                    foreach ($config['element_order'] as $element) {
                        $this->render_product_element($element, $config, $product);
                    }

                    echo '</div>';
                echo '</div>';
            }
            
            echo '</div>';
            
       } else {
            echo '<p class="wc-plb-no-products">' . __('Keine Produkte gefunden.', 'wc-product-layout') . '</p>';
        }
        
        $html = ob_get_clean();
        
        // Pagination generieren
        ob_start();
        $this->render_pagination($products, $layout_id);
        $pagination = ob_get_clean();
        
        wp_reset_postdata();
        
        wp_send_json_success(array(
            'html' => $html,
            'pagination' => $pagination
        ));
    } 
    
    /**
     * Benutzerdefinierte Spalten in der Übersicht
     */
    public function custom_columns($columns) {
        $new_columns = array();
        
        foreach ($columns as $key => $title) {
            $new_columns[$key] = $title;
            
            if ($key === 'title') {
                $new_columns['shortcode'] = __('Shortcode', 'wc-product-layout');
            }
        }
        
        return $new_columns;
    }

    /**
     * Inhalt der benutzerdefinierten Spalten
     */
    public function custom_column_content($column, $post_id) {
        if ($column === 'shortcode') {
            $shortcode = '[wc_product_layout id="' . esc_attr($post_id) . '"]';
            ?>
            <input type="text" value="<?php echo esc_attr($shortcode); ?>" readonly style="width:100%; max-width:300px;">
            <button class="button button-small wc-plb-copy-btn" data-clipboard-text="<?php echo esc_attr($shortcode); ?>">
                <span class="dashicons dashicons-clipboard"></span> <?php _e('Kopieren', 'wc-product-layout'); ?>
            </button>
            <?php
        }
    }
}

// Plugin initialisieren
WC_Product_Layout_Builder_Complete::get_instance();