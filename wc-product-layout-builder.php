<?php
/**
 * Plugin Name: WooCommerce Product Layout Builder
 * Description: Erstelle visuelle Produkt-Layouts mit Grids, Filteroptionen und WooCommerce-spezifischen Funktionen.
 * Version: 0.8.0 - Improved Alignment
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
class WC_Product_Layout_Builder {

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
                
                // Haupt-Skript
                wp_enqueue_script(
                    'wc-plb-admin', 
                    WC_PLB_PLUGIN_URL . 'assets/js/admin.js', 
                    array('jquery', 'wp-color-picker', 'jquery-ui-sortable'), 
                    WC_PLB_VERSION, 
                    true
                );
                
                // Haupt-Style
                wp_enqueue_style(
                    'wc-plb-admin', 
                    WC_PLB_PLUGIN_URL . 'assets/css/admin.css', 
                    array(), 
                    WC_PLB_VERSION
                );
                
                // Lokalisierte Skripte
                wp_localize_script('wc-plb-admin', 'wc_plb_admin', array(
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
        // Haupt-Style - Use external CSS file
        wp_enqueue_style(
            'wc-plb-frontend', 
            WC_PLB_PLUGIN_URL . 'assets/css/frontend.css', 
            array(), 
            WC_PLB_VERSION
        );
        
        // Haupt-Skript - Use external JS file
        wp_enqueue_script(
            'wc-plb-frontend', 
            WC_PLB_PLUGIN_URL . 'assets/js/frontend.js', 
            array('jquery'), 
            WC_PLB_VERSION, 
            true
        );
        
        // AJAX Variablen
        wp_localize_script('wc-plb-frontend', 'wc_plb_frontend', array(
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce'    => wp_create_nonce('wc_plb_nonce')
        ));
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
     * Shortcode rendern
     */
    public function shortcode_render($atts) {
        $atts = shortcode_atts(array(
            'id' => 0,
            'name' => '' // Für Abwärtskompatibilität behalten
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
        
        if ($config['filter_by_price'] && $config['default_price_range']) {
            $price_range = explode('-', $config['default_price_range']);
            if (count($price_range) == 2) {
                $args['meta_query'][] = array(
                    'key' => '_price',
                    'value' => array_map('floatval', $price_range),
                    'compare' => 'BETWEEN',
                    'type' => 'DECIMAL'
                );
            }
        }
        
        if ($config['filter_by_rating'] && $config['default_rating']) {
            $args['meta_query'][] = array(
                'key' => '_wc_average_rating',
                'value' => floatval($config['default_rating']),
                'compare' => '>=',
                'type' => 'DECIMAL'
            );
        }
        
        $products = new WP_Query($args);
        
        ob_start();
        
        // Filter anzeigen
        if ($config['filter_by_category'] || $config['filter_by_price'] || $config['filter_by_rating'] || !empty($config['filter_by_attribute'])) {
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
            
            // Preis-Filter
            if ($config['filter_by_price']) {
                echo '<div class="wc-plb-filter wc-plb-price-filter">';
                echo '<label>' . __('Preisbereich:', 'wc-product-layout') . '</label>';
                echo '<select class="wc-plb-filter-select">';
                echo '<option value="">' . __('Alle Preise', 'wc-product-layout') . '</option>';
                echo '<option value="0-50" ' . selected($config['default_price_range'], '0-50', false) . '>€0 - €50</option>';
                echo '<option value="50-100" ' . selected($config['default_price_range'], '50-100', false) . '>€50 - €100</option>';
                echo '<option value="100-200" ' . selected($config['default_price_range'], '100-200', false) . '>€100 - €200</option>';
                echo '<option value="200-" ' . selected($config['default_price_range'], '200-', false) . '>€200+</option>';
                echo '</select>';
                echo '</div>';
            }
            
            // Bewertungs-Filter
            if ($config['filter_by_rating']) {
                echo '<div class="wc-plb-filter wc-plb-rating-filter">';
                echo '<label>' . __('Mindestbewertung:', 'wc-product-layout') . '</label>';
                echo '<select class="wc-plb-filter-select">';
                echo '<option value="">' . __('Alle Bewertungen', 'wc-product-layout') . '</option>';
                echo '<option value="3" ' . selected($config['default_rating'], '3', false) . '>3+ ' . __('Sterne', 'wc-product-layout') . '</option>';
                echo '<option value="4" ' . selected($config['default_rating'], '4', false) . '>4+ ' . __('Sterne', 'wc-product-layout') . '</option>';
                echo '<option value="4.5" ' . selected($config['default_rating'], '4.5', false) . '>4.5+ ' . __('Sterne', 'wc-product-layout') . '</option>';
                echo '</select>';
                echo '</div>';
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
                    $price = $product->get_price();
                    $price_html = ($price >= 2000000) 
                        ? '<span class="wc-plb-product-price" style="font-weight: bold;">' . number_format($price, 2, ',', '.') . ' €</span>'
                        : $product->get_price_html();
                    
                    echo '<div class="wc-plb-product-price">' . $price_html . '</div>';
                }
                break;
                
            case 'rating':
                if ($config['show_rating'] || $config['show_rating_count']) {
                    $rating_count = $product->get_rating_count();
                    $average = $product->get_average_rating();
            
                    if ($rating_count > 0) {
                        echo '<div class="wc-plb-product-rating-container">';
                        echo '<div class="wc-plb-product-rating">';
            
                        // Bestimme, ob vor oder nach den Sternen etwas kommt
                        if ($config['rating_count_position'] === 'before' && $config['show_rating_count']) {
                            $prefix = esc_html($config['rating_count_prefix']);
                            $suffix = esc_html($config['rating_count_suffix']);
                            echo '<span class="wc-plb-product-rating-count">';
                            echo $prefix . $rating_count . $suffix;
                            echo '</span>';
                            if ($config['show_rating']) echo '&nbsp;';
                        }
            
                        if ($config['show_rating']) {
                            echo wc_get_rating_html($average, $rating_count);
                        }
            
                        if ($config['rating_count_position'] === 'after' && $config['show_rating_count']) {
                            if ($config['show_rating']) echo '&nbsp;';
                            $prefix = esc_html($config['rating_count_prefix']);
                            $suffix = esc_html($config['rating_count_suffix']);
                            echo '<span class="wc-plb-product-rating-count">';
                            echo $prefix . $rating_count . $suffix;
                            echo '</span>';
                        }
            
                        echo '</div>'; // .wc-plb-product-rating
                        echo '</div>'; // .wc-plb-product-rating-container
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
                
            case 'stock_status':
                if ($config['show_stock_status']) {
                    $availability = $product->get_availability();
                    $stock_status = '';
            
                    if ($product->is_in_stock()) {
                        if ($product->is_on_backorder()) {
                            $stock_status = __('Verfügbar bei Nachbestellung', 'wc-product-layout');
                        } else {
                            $stock_status = __('Verfügbar', 'wc-product-layout');
                        }
                    } else {
                        $stock_status = __('Nicht vorrätig', 'wc-product-layout');
                    }
            
                    if (!empty($availability['availability'])) {
                        $stock_status = $availability['availability'];
                    }
            
                    echo '<div class="wc-plb-product-stock-status">';
                    echo esc_html($stock_status);
                    echo '</div>';
                }
                break;
                
            case 'attributes':
                if ($config['show_attributes']) {
                    $attributes = $product->get_attributes();
                    if (!empty($attributes)) {
                        echo '<div class="wc-plb-product-attributes">';
                        foreach ($attributes as $attribute) {
                            $values = wc_get_product_terms($product->get_id(), $attribute['name'], array('fields' => 'names'));
                            if (!empty($values)) {
                                $label = wc_attribute_label($attribute['name']);
                                $value = implode(', ', $values);
            
                                if ($config['attribute_display'] == 'label') {
                                    echo '<span class="wc-plb-attribute-label">';
                                    echo esc_html($label) . ': ' . esc_html($value);
                                    echo '</span> ';
                                } else {
                                    echo '<div class="wc-plb-attribute-text">';
                                    echo '<strong>' . esc_html($label) . ':</strong> ' . esc_html($value);
                                    echo '</div>';
                                }
                            }
                        }
                        echo '</div>';
                    }
                }
                break;
                
            case 'description':
                if ($config['show_description']) {
                    if ($config['excerpt_type'] === 'short') {
                        $raw = $product->get_short_description();
                        if (empty($raw) && $config['fallback_option'] === 'use_long') {
                            $raw = $product->get_description();
                        }
                    } else {
                        $raw = $product->get_description();
                    }
            
                    if (!empty($raw)) {
                        echo '<div class="wc-plb-product-description">';
            
                        if ($config['allow_html']) {
                            // Nur sichtbare Zeichen zählen, HTML behalten
                            $stripped = wp_strip_all_tags($raw, true);
                            if (mb_strlen($raw) > $config['description_length']) {
                                // Finde sichere Kürzungsposition im Original-HTML
                                $trim_pos = mb_strlen(mb_substr($stripped, 0, $config['description_length']));
                                $processed = mb_substr($raw, 0, $this->find_safe_cut_position($raw, $trim_pos));
                                $processed .= '...';
                                echo wp_kses_post($processed);
                            } else {
                                echo wp_kses_post($raw);
                            }
                        } else {
                            // Kein HTML erlaubt → nur Text
                            $text = wp_strip_all_tags($raw);
                            if (mb_strlen($text) > $config['description_length']) {
                                $out = mb_substr($text, 0, $config['description_length']) . '...';
                                echo esc_html($out);
                            } else {
                                echo esc_html($text);
                            }
                        }
            
                        echo '</div>';
                    }
                }
                break;

            case 'more_info':
                if ($config['show_more_info']) {
                    if ($config['more_info_display'] == 'button') {
                        echo '<div class="wc-plb-more-info-button">';
                        echo '<a href="' . esc_url(get_permalink()) . '" class="wc-plb-more-info-btn">';
                        echo esc_html($config['more_info_text']);
                        echo '</a>';
                        echo '</div>';
                    } else {
                        echo '<div class="wc-plb-more-info-text">';
                        echo '<a href="' . esc_url(get_permalink()) . '" class="wc-plb-more-info-link">';
                        echo esc_html($config['more_info_text']);
                        echo '</a>';
                        echo '</div>';
                    }
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
        
            $current_url = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http") . "://$_SERVER[HTTP_HOST]$_SERVER[REQUEST_URI]";
            $base_url = preg_replace('/\/page\/\d+\/$/', '/', $current_url);
            $base_url = rtrim($base_url, '/') . '/';
            $base_url = preg_replace('/(\/+)/', '/', $base_url);
            
            echo '<div class="wc-plb-pagination" data-layout-id="' . esc_attr($layout_id) . '">';
            echo '<ul class="page-numbers">';
            
            // << Erste Seite
            echo '<li>';
            if ($current_page > 1) {
                echo '<a class="page-numbers first" href="' . esc_url($base_url . 'page/1/') . '" data-page="1">&laquo;</a>';
            } else {
                echo '<span class="page-numbers first disabled">&laquo;</span>';
            }
            echo '</li>';
            
            // < Vorherige Seite
            echo '<li>';
            if ($current_page > 1) {
                $prev_page = $current_page - 1;
                echo '<a class="page-numbers prev" href="' . esc_url($base_url . 'page/' . $prev_page . '/') . '" data-page="' . $prev_page . '">&lsaquo;</a>';
            } else {
                echo '<span class="page-numbers prev disabled">&lsaquo;</span>';
            }
            echo '</li>';
            
            // Seitenzahlen
            for ($i = 1; $i <= $total_pages; $i++) {
                echo '<li>';
                if ($i == $current_page) {
                    echo '<span class="page-numbers current" data-page="' . $i . '">' . $i . '</span>';
                } else {
                    echo '<a class="page-numbers" href="' . esc_url($base_url . 'page/' . $i . '/') . '" data-page="' . $i . '">' . $i . '</a>';
                }
                echo '</li>';
            }
            
            // > Nächste Seite
            echo '<li>';
            if ($current_page < $total_pages) {
                $next_page = $current_page + 1;
                echo '<a class="page-numbers next" href="' . esc_url($base_url . 'page/' . $next_page . '/') . '" data-page="' . $next_page . '">&rsaquo;</a>';
            } else {
                echo '<span class="page-numbers next disabled">&rsaquo;</span>';
            }
            echo '</li>';
            
            // >> Letzte Seite
            echo '<li>';
            if ($current_page < $total_pages) {
                echo '<a class="page-numbers last" href="' . esc_url($base_url . 'page/' . $total_pages . '/') . '" data-page="' . $total_pages . '">&raquo;</a>';
            } else {
                echo '<span class="page-numbers last disabled">&raquo;</span>';
            }
            echo '</li>';
            
            echo '</ul>';
            echo '</div>';
        }
    }

    /**
     * Generate dynamic CSS based on configuration
     */
    private function generate_dynamic_css($config) {
        echo '<style>
            /* Dynamic CSS for layout customization */
            .wc-plb-product-title {
                color: ' . esc_attr($config['title_color']) . ';
                font-size: ' . esc_attr($config['title_font_size']) . 'px;
            }
            
            .wc-plb-product-price {
                color: ' . esc_attr($config['price_color']) . ';
                font-size: ' . esc_attr($config['price_font_size']) . 'px;
            }
            
            .wc-plb-product-description {
                color: ' . esc_attr($config['text_color']) . ';
                ' . (!empty($config['description_max_height']) ? 
                    'max-height: ' . esc_attr($config['description_max_height']) . 'px;' .
                    ($config['description_overflow'] === 'scroll' ? 
                        'overflow-y: auto;' : 
                        'overflow: hidden;'
                    ) : ''
                ) . '
            }
            
            .wc-plb-product-add-to-cart .button {
                background-color: ' . esc_attr($config['button_bg_color']) . ';
                color: ' . esc_attr($config['button_text_color']) . ';
                border-color: ' . esc_attr($config['button_border_color']) . ';
            }
            
            .wc-plb-product-sale-badge {
                background-color: ' . esc_attr($config['sale_badge_color']) . ';
                color: ' . esc_attr($config['sale_badge_text_color']) . ';
                border-color: ' . esc_attr($config['sale_badge_border_color']) . ';
            }
            
            .wc-plb-product-stock-status {
                color: ' . esc_attr($config['stock_status_color']) . ';
            }
            
            .wc-plb-product-rating-count {
                color: ' . esc_attr($config['rating_count_color']) . ';
            }
            
            .wc-plb-attribute-label {
                background-color: ' . esc_attr($config['attribute_bg_color']) . ';
                color: ' . esc_attr($config['attribute_text_color']) . ';
                border-color: ' . esc_attr($config['attribute_border_color']) . ';
            }
            
            .wc-plb-more-info-btn {
                background-color: ' . esc_attr($config['more_info_bg_color']) . ';
                color: ' . esc_attr($config['more_info_color']) . ';
                border-color: ' . esc_attr($config['more_info_border_color']) . ';
                font-size: ' . esc_attr($config['more_info_font_size']) . 'px;
            }
            
            .wc-plb-more-info-link {
                color: ' . esc_attr($config['more_info_color']) . ';
                font-size: ' . esc_attr($config['more_info_font_size']) . 'px;
            }
            
            .wc-plb-product-rating .star-rating {
                color: ' . esc_attr($config['rating_star_filled_color']) . ' !important;
            }
            
            .wc-plb-product-rating .star-rating::before {
                color: ' . esc_attr($config['rating_star_empty_color']) . ' !important;
            }
            
            .wc-plb-pagination ul.page-numbers li a,
            .wc-plb-pagination ul.page-numbers li span {
                background-color: ' . esc_attr($config['pagination_bg_color']) . ';
                color: ' . esc_attr($config['pagination_text_color']) . ';
                border-color: ' . esc_attr($config['pagination_border_color'] ?? '#ddd') . ';
            }
            
            .wc-plb-pagination ul.page-numbers li a:hover,
            .wc-plb-pagination ul.page-numbers li span.current {
                background-color: ' . esc_attr($config['pagination_active_bg']) . ';
                color: ' . esc_attr($config['pagination_active_text']) . ';
                border-color: ' . esc_attr($config['pagination_active_bg']) . ';
            }
        </style>';
    }

    /**
     * AJAX Produktfilter
     */
    public function ajax_filter_products() {
        check_ajax_referer('wc_plb_nonce', 'nonce');
        
        $layout_id = isset($_POST['layout_id']) ? absint($_POST['layout_id']) : 0;
        $page = isset($_POST['page']) ? absint($_POST['page']) : 1;
        $category = isset($_POST['category']) ? sanitize_text_field($_POST['category']) : '';
        $price_range = isset($_POST['price_range']) ? sanitize_text_field($_POST['price_range']) : '';
        $rating = isset($_POST['rating']) ? floatval($_POST['rating']) : 0;
        
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
        
        // Preisbereich filtern
        if (!empty($price_range)) {
            $price_range = explode('-', $price_range);
            if (count($price_range) == 2) {
                $min = floatval($price_range[0]);
                $max = !empty($price_range[1]) ? floatval($price_range[1]) : 999999;
                
                $args['meta_query'][] = array(
                    'key' => '_price',
                    'value' => array($min, $max),
                    'compare' => 'BETWEEN',
                    'type' => 'DECIMAL'
                );
            }
        }
        
        // Bewertung filtern
        if ($rating > 0) {
            $args['meta_query'][] = array(
                'key' => '_wc_average_rating',
                'value' => $rating,
                'compare' => '>=',
                'type' => 'DECIMAL'
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
                
                    // Content-Bereich
                    echo '<div class="wc-plb-product-content">';
                    
                    // Elemente in der definierten Reihenfolge anzeigen
                    foreach ($config['element_order'] as $element) {
                        $this->render_product_element($element, $config, $product);
                    }

                    echo '</div>'; // .wc-plb-product-content

                echo '</div>'; // .wc-plb-product
            }
            
            echo '</div>'; // .wc-plb-product-grid
            
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
            <script>
            jQuery(document).ready(function($) {
                $('.wc-plb-copy-btn').on('click', function() {
                    var copyText = $(this).data('clipboard-text');
                    var $temp = $('<textarea>');
                    $('body').append($temp);
                    $temp.val(copyText).select();
                    document.execCommand('copy');
                    $temp.remove();
                    
                    // Feedback anzeigen
                    $(this).html('<span class="dashicons dashicons-yes"></span> <?php _e('Kopiert!', 'wc-product-layout'); ?>');
                    setTimeout(function() {
                        $(this).html('<span class="dashicons dashicons-clipboard"></span> <?php _e('Kopieren', 'wc-product-layout'); ?>');
                    }.bind(this), 2000);
                });
            });
            </script>
            <?php
        }
    }
    
    /**
     * Findet eine sichere Kürzungsposition im HTML-Text
     */
    private function find_safe_cut_position($html, $target_pos) {
        $len = 0;
        $tag_stack = [];
        $in_tag = false;
        
        for ($i = 0; $i < mb_strlen($html); $i++) {
            $char = mb_substr($html, $i, 1);
            
            if ($char === '<') {
                $in_tag = true;
                continue;
            }
            
            if ($char === '>') {
                $in_tag = false;
                continue;
            }
            
            if (!$in_tag) {
                $len++;
                if ($len >= $target_pos) {
                    // Stelle gefunden, aber prüfen ob wir in einem Tag sind
                    $next_tag = mb_strpos($html, '<', $i);
                    return ($next_tag !== false) ? $next_tag : $i;
                }
            }
        }
        
        return mb_strlen($html);
    }

    // Note: Meta boxes and admin functionality would be added here
    // For brevity, I'm focusing on the main rendering improvements
    
    public function add_meta_boxes() {
        // Add meta boxes for admin interface
    }
    
    public function save_layout_settings($post_id) {
        // Save layout settings
    }
    
    public function render_layout_config($post) {
        // Render admin interface
    }
    
    public function render_shortcode_info($post) {
        // Render shortcode info
    }
}

// Plugin initialisieren
WC_Product_Layout_Builder::get_instance();