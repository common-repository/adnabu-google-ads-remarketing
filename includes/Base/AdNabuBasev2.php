<?php
/**
 * Created by PhpStorm.
 * User: Mahaveer Chouhan
 * Date: 21/9/18
 * Time: 11:24 AM
 */

class AdNabuBasev2{
    public $app_dir;
    public $app_dir_url;

    function activate(){
        flush_rewrite_rules();
        self::define_base_globals();
        self::activation_beacon();
    }


    function get_app_db_prefix(){
        global $wpdb;
        $db_prefix = $wpdb->prefix . $this::$app_prefix;
        return $db_prefix;
    }


    function add_menu_page() {
        if ( empty ( $GLOBALS['admin_page_hooks']['adnabu_plugin'] ) )
        {
            add_menu_page( 'AdNabu',
                'AdNabu',
                'manage_options',
                'adnabu_plugin',
                '',
                'dashicons-store', 60 );
            add_submenu_page('adnabu_plugin', 'Apps',
                'More Apps From Adnabu', 'manage_options',
                'adnabu_plugin', array($this, 'menu_page_render'));
        }
    }


    function menu_page_render(){
        $d = new DOMDocument;
        $d->loadHTMLFile('https://www.adnabu.com/woocommerce/apps');
        $body = $d->getElementsByTagName('body')->item(0);
        foreach ($body->childNodes as $childNode) {
            echo $d->saveHTML($childNode);
        }
    }


    function activation_greeting(){
        $message = "<p>Thank You for choosing us! For tutorials on the plugins please visit
                        <a href=\"https://adnabu.com/\" target=\"_blank\">{$this::$app_name}</a>
                    </p>";

        if(get_transient($this::$app_name)){
            $this->show_message($message, "SUCCESS" );
        }
    }


    function missing_woocommerce() {
        $message =  "{$this::$app_name} requires
                    <a href='https://woocommerce.com/' target='_blank'>WooCommerce</a>
                    to be installed and active";
            $this->show_message($message, "FAILURE" );
    }


    function custom_wp_remote($url, $data = array(), $more_options = array(), $method = "GET"){
        /*$more_options is a associative array containing extra options that may be created in args array
        also it can have new values for the existing options
        array_merge floods the old values if the keys matches in associative arrays
        */
        global $wp_version;
        $args = array(
            'timeout'     => 5,
            'redirection' => 5,
            'httpversion' => '1.0',
            'user-agent'  => 'WordPress/' . $wp_version . '; ' . home_url(),
            'blocking'    => true,
            'headers'     => array(),
            'cookies'     => array(),
            'body'        => array(),
            'compress'    => false,
            'decompress'  => true,
            'sslverify'   => true,
            'stream'      => false,
            'filename'    => null
        );

        $args = array_merge($args, $more_options);
        $body = array(
                'app_id' => $this::$app_id,
                'store' => get_option('adnabu_store_id')) + $data;
        $args['body'] = $body;

        if ($method == "POST"){
            $response = wp_remote_post($url, $args);
        }
        else{
            $response = wp_remote_get($url, $args);
        }
        return $response;
    }


    function enqueue_base_assets(){
        wp_enqueue_script('jquery');
        $css = array(
            'boostrap4' => $this->app_dir_url . '/includes/Base/assets/css/bootstrap.min.css',
            'font-awesome3' => 'https://use.fontawesome.com/releases/v5.3.1/css/all.css'
        );
        $this->enqueue_styles($css);
    }

    function enqueue_app_assets($app_prefix = "adnabu"){
        $styles_assoc_array = array(
            $app_prefix . 'admin_css' => $this->app_dir_url . '/assets/css/style.css'
        );

        $scripts_assoc_array = array(
            $app_prefix . 'admin_js' => $this->app_dir_url . '/assets/js/script.js'
        );

        $this->enqueue_styles($styles_assoc_array);
        $this->enqueue_scripts($scripts_assoc_array);
    }


    function deactivate(){
        flush_rewrite_rules();
    }

//    accepts an associative array of option name and value
    function add_my_options($options){
        foreach ($options as $key => $value){
            update_option($key, $value, true);
        }
    }


    function enqueue_scripts($scripts_handles_assoc_array){
        foreach ($scripts_handles_assoc_array as $handle => $source) {
            wp_register_script($handle, $source);
            wp_enqueue_script( $handle);
        }
    }


    function enqueue_styles($styles_handles_assoc_array){
        foreach ($styles_handles_assoc_array as $handle => $source) {
            wp_register_style( $handle, $source);
            wp_enqueue_style( $handle );
        }
    }


    function define_base_globals(){
        global $wpdb;
        $adnabu_url = "https://adnabu.com/";
        $global_options = array('adnabu_url' => $adnabu_url
        );
        if(!get_option('adnabu_store_id')){
            $global_options = $global_options + array('adnabu_store_id' => wp_generate_password(20, false, false));
        }
        $this->add_my_options($global_options);
    }


    function create_table($query){
        global $wpdb;
        require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );
        $charset_collate = $wpdb->get_charset_collate();
        $query = $query . $charset_collate;//
        dbDelta( $query );//dbDelta handles sanitization
    }


    function localise_enqueue_scripts($handle, $data, $expose_object_name){
        wp_register_script( $handle,"");
        wp_localize_script( $handle, $expose_object_name, $data);
        wp_enqueue_script( $handle);
    }


    function current_admin_data(){
        if(WP_ADMIN){
            $admin_data = get_userdata(get_current_user_id());
            $data = self::user_details($admin_data);
            return $data;
        }
        return array();
    }


    function registered_admin_data(){
        get_user_by( 'email', get_option('admin_email') );
        $data = self::user_details(get_user_by( 'email', get_option('admin_email') ));
        return $data;
    }


    function user_details($user){
        $user_data_json = json_decode(json_encode($user));
        $user_details = array(
            'email' => $user_data_json->data->user_email,
            'name' => $user_data_json->data->user_nicename,
            'display_name' => $user_data_json->data->display_name
        );
        return $user_details;
    }


    function activation_beacon(){
        $current_user =self::current_admin_data();
        $registered_user = self::registered_admin_data();
        $site_info = array(
                    'site' => site_url(),
                    'currency' => get_woocommerce_currency(),
                    'user' => array('current' => $current_user,
                    'registered' => $registered_user));
        self::custom_wp_remote(self::add_base_url("woocommerce/app/activated"),
           $site_info, array(),"POST");
    }


    function add_base_url($url){
        $url = get_option('adnabu_url') . $url;
        return $url;
    }


    function wc_page_type(){
        if (is_search() and is_shop()){
            return 'searchresults';
        }
        if(is_shop()){
            return 'home';
        }
        if (is_order_received_page()){
            return 'purchase';
        }
        if (is_checkout()){
            return 'checkout';
        }
        if (is_cart()){
            return 'cart';
        }
        if (is_product()){
            return 'product';
        }
        if (is_product_category()){
            return 'category';
        }
        return 'other';
    }


    function show_message($message = "Something is Not right", $type = "WARNING"){
        if($type == "FAILURE"){
            $color = 'red';
            $html_class =  "notice-error";
        }
        elseif ($type == "WARNING"){
            $color = 'darkmagenta';
            $html_class =  "notice-warning";
        }
        elseif ($type == "SUCCESS"){
            $color = 'green';
            $html_class =  "notice-success";
        }
        else{
            $color = 'blue';
            $html_class =  "notice-info";
        }

        ?>
        <div style="display: flex; justify-content: center;" class="container  notice <?php echo $html_class ?> is-dismissible">
            <strong><p style="color:  <?php echo $color ?>"> <?php echo $message ?></p></strong>
        </div>
        <?php
    }

    public static function uninstall($app_prefix){
        global $wpdb;
        $prefix = $wpdb->prefix . $app_prefix;
        $uninstall_query = "SET @Drop_Stm = CONCAT('DROP TABLE ',(SELECT GROUP_CONCAT(TABLE_NAME) AS All_Tables FROM information_schema.tables WHERE TABLE_NAME LIKE '{$prefix}%'));";
        $wpdb->query($uninstall_query);
        $uninstall_query = "PREPARE Stm FROM @Drop_Stm;";
        $wpdb->query($uninstall_query);
        $uninstall_query = "EXECUTE Stm;";
        $wpdb->query($uninstall_query);
        $uninstall_query = "DEALLOCATE PREPARE Stm;";
        $wpdb->query($uninstall_query);
    }
}