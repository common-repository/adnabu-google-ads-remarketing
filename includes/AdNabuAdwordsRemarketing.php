<?php
/**
 * Created by PhpStorm.
 * User: Mahaveer Chouhan
 * Date: 12/10/18
 * Time: 6:20 PM
 */

/**
 * @package  AdnabuRemarketing
 */


class AdNabuAdwordsRemarketing extends AdNabuPixelBase {
    public static $app_prefix = "adnabu_woocommerce_adwords_remarketing_";
    public static $app_id = 'GOOGLE_ADS_DYNAMIC_REMARKETING'; //'GOOGLE_ADS_CONVERSION_TRACKING'; //'GOOGLE_ADS_REMARKETING';
    public static $app_version = "18.5.01";
    public static $app_name = "AdNabu Adwords Remarketing";
    public $pixel_table;

    function __construct() {
        $this->pixel_table =  $this->get_app_db_prefix() . "pixels";
        $this->app_dir = plugin_dir_path( dirname( __FILE__, 1 ) );
        $this->app_dir_url = plugins_url(basename(dirname(__FILE__,2)));
    }


    function activate_app(){
        self::create_pixel_table();
        set_transient($this::$app_name, 1, 5);
        self::activate();
    }


    function create_pixel_table(){
        $create_pixel_table_query = "
            CREATE TABLE IF NOT EXISTS `{$this->pixel_table}` (
              ID int NOT NULL AUTO_INCREMENT PRIMARY KEY,
              `pixel` text NOT NULL,
              `status` TinyInt(1)
            )";
        self::create_table($create_pixel_table_query);
    }


    function enqueue_pixel_scripts(){
        $pixels = $this->read_pixel_list_from_db();
        foreach ($pixels as $pixel) {
            if (wp_script_is($pixel)) {
                return;
            }
            else {
                $script_url = "https://storage.googleapis.com/adnabu-woocommerce/remarketing-pixels/$pixel.js";
                $script_array = array("gtag_$pixel" =>"https://storage.googleapis.com/adnabu-woocommerce/global-site-tags/$pixel.js");
                $script_array = $script_array + array($pixel => $script_url);
                $this->enqueue_scripts($script_array);
            }
        }
    }


    function settings_link($links){
        $settings_link = '<a href="admin.php?page=adnabu-adwords-remarketing">Home</a>';
        array_push($links, $settings_link);
        return $links;
    }


    function admin_index(){
        require_once  $this->app_dir . '/templates/app_home.php' ;
    }


    function add_app_page(){
        add_submenu_page('adnabu_plugin', 'Adwords Remarketing',
            'Adwords Remarketing', 'manage_options',
            'adnabu-adwords-remarketing', array($this, 'admin_index'));
    }


    function enqueue_admin_assets($hook){
        if($hook == 'adnabu_page_adnabu-adwords-remarketing'){
            $this->enqueue_base_assets();
            $this->enqueue_app_assets($this::$app_prefix);

            $js = array($this::$app_prefix . 'selectize_js' => $this->app_dir_url . '/assets/js/selectize.js');
            $css =array($this::$app_prefix . 'selectize_css' => $this->app_dir_url . '/assets/css/selectize.default.css');
            $this->enqueue_scripts($js);
            $this->enqueue_styles($css);
            wp_enqueue_script( 'jquery' );
        }
    }


    function enqueue(){
        $page_type = self::wc_page_type();
        $exposer_array = $this->gtag_data($page_type);
        $this->localise_enqueue_scripts("checkout_handle", $exposer_array, 'adnabu_remarketing_data');
        $this->enqueue_pixel_scripts();
    }


    function get_product_category_by_id( $category_id ) {
        $term = get_term_by( 'id', $category_id, 'product_cat', 'ARRAY_A' );
        return $term['name'];
    }

    function get_product_meta_gtin_related_fields(){
        global $wpdb;
        $query = "
            SELECT DISTINCT($wpdb->postmeta.meta_key)
            FROM $wpdb->posts 
            LEFT JOIN $wpdb->postmeta 
            ON $wpdb->posts.ID = $wpdb->postmeta.post_id 
            WHERE ($wpdb->posts.post_type = 'product' 
            OR $wpdb->posts.post_type = 'product_variation') 
            AND $wpdb->postmeta.meta_key != ''
            AND ($wpdb->postmeta.meta_key LIKE '%gtin%'
            OR $wpdb->postmeta.meta_key LIKE '%ean%'
            OR $wpdb->postmeta.meta_key LIKE '%barcode%'
            OR $wpdb->postmeta.meta_key LIKE '%isbn%'
            OR $wpdb->postmeta.meta_key LIKE '%upc%')
            ORDER BY $wpdb->postmeta.meta_key
          ";
        $meta_keys = $wpdb->get_results($query);
        return $meta_keys;
    }


    function get_gtin_id($product_id){
        $gtin_field = get_option($this::$app_prefix . 'gtin_field');
        $post_type = get_post_type( $product_id );
        $gtin_value = '';
        if($post_type == 'product') {
            $gtin_value = get_post_meta($product_id, $gtin_field, $single = true);
        }
        elseif($post_type == 'product_variation') {
            $gtin_value = get_post_meta($product_id, $gtin_field.'_variation', $single = true);
            if(empty($gtin_value)) {
                $gtin_value = get_post_meta($product_id, $gtin_field, $single = true);
            }
        }
        return $gtin_value;
    }


    function get_merchant_centre_product_id($product_id, $item_id_regex = ''){
        if($item_id_regex == ''){
            $item_id_regex = get_option(self::$app_prefix . 'item_id_expression');
        }
        $id = $item_id_regex;

        $product = wc_get_product($product_id);
        $sku_id = $product->get_sku();
        $variation_id = '';
        $countries = new WC_Countries();
        $delivery_country = $countries->get_base_country();
        if($product->is_type('variation')){
            $variation_id = $product->get_id();
        }

        $gtin_id = $this->get_gtin_id($product_id);

        $id = str_replace("{Woocommerce Product ID}", $product_id, $id);
        $id = str_replace("{Site Country}", $delivery_country, $id);
        $id = str_replace("{SKU}", $sku_id, $id);
        $id = str_replace("{Variation ID}", $variation_id, $id);
        $id = str_replace("{GTIN}", $gtin_id, $id);
        $id = str_replace(",", '', $id);
        return $id;
    }


    function gtag_data($pagetype){
        $data = array('ecomm_pagetype' => $pagetype,
            'store' => get_option('adnabu_store_id'));
        $merchant_centre_product_ids = array();
        if ($pagetype == 'category'){
            return $data + array(
                'ecomm_category' => get_the_category_by_ID(get_queried_object()->term_id));
        }
        if ($pagetype == 'product'){
            $product = wc_get_product();
            $categories_id = $product->get_category_ids();
            $ecomm_category_array = array();
            foreach ($categories_id as $id){
                $ecomm_category_array[] = get_the_category_by_ID($id);
            }
            $ecomm_category = implode(" > ", $ecomm_category_array);

            $merchant_center_product_id = $this->get_merchant_centre_product_id($product->get_id());

            return $data + array("ecomm_prodid" =>$merchant_center_product_id,
                    'ecomm_totalvalue' => $product->get_price(),
                    'ecomm_category' => $ecomm_category
                );
        }
        if ($pagetype == 'cart'){
            $product_id_array = array();
            foreach( WC()->cart->get_cart() as $cart_item ){
                // compatibility with WC +3

                if( version_compare( WC_VERSION, '3.0', '<' ) ){
                    $product_id_array[] = $cart_item['data']->id; // Before version 3.0

                } else {
                    $product_id_array[] = $cart_item['data']->get_id(); // For version 3 or more
                }
                $merchant_centre_product_ids[] = $this->get_merchant_centre_product_id(
                    end($product_id_array));
            }
            $ecomm_totalvalue =  WC()->cart->get_cart_contents_total();
            return $data + array(
                    'ecomm_totalvalue' => $ecomm_totalvalue,
                    'ecomm_prodid' => implode(',', $merchant_centre_product_ids)
                );
        }
        if ($pagetype == 'purchase'){
            $order_id = wc_get_order_id_by_order_key($_GET['key']) ;
            $order    = wc_get_order($order_id );
            $order_items = $order->get_items();


            foreach ($order_items as $item){
                $product_id = $item->get_product_id();
                $merchant_centre_product_ids[] = $this->get_merchant_centre_product_id($product_id);
            }
            $ecomm_prodid = implode(',', $merchant_centre_product_ids);
            return $data + array(
                    'ecomm_totalvalue' => $order->get_subtotal(),
                    'ecomm_prodid' => $ecomm_prodid
                );
        }
        return $data;
    }

    public  static function uninstall_app(){
        parent::uninstall(self::$app_prefix);
    }
}