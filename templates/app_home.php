<br xmlns="http://www.w3.org/1999/html" xmlns="http://www.w3.org/1999/html">
<div class="container">
    <div class="row">
        <div class="col-6 offset-3">
            <a href="https://ads.google.com/">
                <img src="<?php $instance = new AdNabuAdwordsRemarketing();
                echo $instance->app_dir_url . '/assets/images/Google_Ads_logo.svg';
                ?>"
                     alt="Google_Ads_logo"
                     style="width:20%">
            </a>
            <strong style ="font-size: 200%; text-align: center">Adwords Remarketing</strong>
        </div>
    </div>
    <br>

    <?php
    $message = "";
    $instance = new AdNabuAdwordsRemarketing();
    $current_item_id_expression = get_option($instance::$app_prefix . 'item_id_expression');
//    update_option($instance::$app_prefix . 'item_id_expression',"");//use it to reset

    if(count($_POST) != 0) {
        if (isset($_POST["enable_tracker"])) {
            $pixel_id = sanitize_text_field($_POST["enable_tracker"]);
            if (wp_verify_nonce($_POST["wp_nonce"], 'create_pixel_' . $pixel_id) == 1) {
                $instance->enable_new_pixel($pixel_id);
            }
        }
        if (isset($_POST["toggle"])) {
            $pixel_id = sanitize_text_field($_POST["toggle"]);
            if (wp_verify_nonce($_POST["wp_nonce"], 'toggle_pixel_' . $pixel_id) == 1) {
                if($current_item_id_expression){
                    $instance->flip_pixel_status($pixel_id);
                }
                else{
                    $message = "Pixel cant be enabled as Item ID expression is empty, please configure";
                }
            }
        }
        if (isset($_POST['delete'])) {
            $pixel_id = sanitize_text_field($_POST['delete']);
            if (wp_verify_nonce($_POST["wp_nonce"], 'delete_pixel_' . $pixel_id) == 1) {
                $instance->delete_pixel($pixel_id);
            }
        }

        //nonce is function generated here so name is _wpnonce..beware
        if (isset($_POST["ItemIdExpression"])) {
            if (wp_verify_nonce($_POST["_wpnonce"], 'ItemIdExpressionNonce')) {
                $expression = sanitize_text_field($_POST["ItemIdExpression"]);
                update_option($instance::$app_prefix . 'item_id_expression', $expression);
                $current_item_id_expression = $expression;

                $message = "Item ID Settings Saved";

                if (isset($_POST["gtin_field"])) {
                    update_option($instance::$app_prefix . 'gtin_field',
                        sanitize_text_field($_POST["gtin_field"]));
                }
            }
        }
    }


    function render_item_and_predicted_id($style_display =''){
        ?>
        <div id="predicted_item_id_div"
             style="display:<?php echo $style_display?>">
        <div class="row">
            <div class="col-12 text-center">
                <b> This Table Will Show How Item IDs Are Predicted Using The Expression.</b>
            </div>
        </div>
        <div class="row"
        >
            <table class="table col-12 text-center border" id="products">
                <thead>
                <tr>
                    <th>Product ID</th>
                    <th>Product Name</th>
                    <th>Predicted Item ID</th>
                </tr>
                </thead>
                <tbody>

                <?php
                $args     = array( 'post_type' => 'product',  'posts_per_page' => 3 );
                $simple_products = get_posts( $args );
                $args2     = array( 'post_type' => array('product', 'product_variation'),  'posts_per_page' => 3 );
                $variable_products = get_posts( $args2 );
                $products = array_merge($simple_products, $variable_products);

                foreach ($products as $product){
                    echo "<tr id='{$product->ID}'>
                        <td>{$product->ID}</td>
                        <td>{$product->post_title}</td>
                        <td><div class='predicted_item_id'></div></td>
                        </tr>";
                }
                ?>

                </tbody>
            </table>
        </div>
        </div>

        <?php
    }

    function render_item_id_related_div($current_item_id_expression){
        $instance = new AdNabuAdwordsRemarketing();
        $input_status ='disabled';
        $display_save = '';
        $display_edit = 'none';
        if(!$current_item_id_expression){
            $input_status='';;
        }
        else{
            $display_save = 'none';
            $display_edit = '';
        }

        ?>
        <script>
            var AdNabuItemIDVar = '<?php echo $current_item_id_expression;?>'
        </script>
        <div id="expression_input_box">
            <br>
            <form action="#"  class="" method="post">
                <div class="row" >
                    <span class="mt-1">Your Current Item ID Expression is</span>
                    <div class="col-8 text-center" >
                        <?php wp_nonce_field( 'ItemIdExpressionNonce'); ?>
                        <input type="hidden" id="expression_value" value='<?php
                        echo $current_item_id_expression;
                        ?>'
                        >

                        <input type="text"  id="input-tags" placeholder="Enter Item ID expression" required
                               <?php echo  $input_status?>
                               name ="ItemIdExpression" class="selectized">
                        </div>
                    <span>
                        <button class="btn-primary" id="editButton" style="display:<?php echo  $display_edit?>"  type="button" onclick="startEditMode();" > Edit
                        </button>
                        <button class="btn-primary" style="display: <?php echo  $display_save?>" id="saveItemIDExpression" type="submit"> Save
                        </button>
                    </span>
                </div>
                <div class="row" >
                    <div class="col-4 offset-4 align-content-center">
                        <div class="form-group" style="display: none" id="gtin_form_div">
                            <label for="gtin_field">Select GTIN coloumn:</label>
                            <select class="form-control" id="gtin_field" name="gtin_field">
                                <?php
                                $fields = $instance->get_product_meta_gtin_related_fields();
                                foreach ($fields as $field) {
                                    ?>
                                    <option>
                                        <?php echo $field->meta_key?>
                                    </option>
                                    <?php
                                }
                                ?>
                            </select>
                        </div>
                    </div>
                </div>
            </form>
        </div>
        <br>
        <div class="row">
            <div class="col-12 text-center">
                <br>
                <button class="btn btn-secondary"  onclick="toggle_display_div('whyItemID')">Why I Need To Do This?</button>
                <button class="btn btn-secondary" onclick="window.open('https://merchants.google.com/mc/items?a=<merchant_id','_blank')">Go to Merchant Center
                </button>
                <br><br>
                <div id="whyItemID" style="display: none" >
                    Your Merchant Centre Item ID (ecomm_prodid) is required for the Remarketing pixel to work.
                    Google needs the product ID to verify against Merchant Center.
                    For more information Kindly check this <a href="https://developers.google.com/adwords-remarketing-tag/parameters#retail" target="_blank">Article</a>

                    <img src="<?php $instance = new AdNabuAdwordsRemarketing();
                    echo $instance->app_dir_url . '/assets/images/MerchantCenterItemID.png';
                    ?>" style="width:100%">
                    <div class="caption">
                        <p> Above image is a merchant center screenshot showing Item ID coloumn </p>
                    </div>
                </div>
            </div>
        </div>
        <?php
        render_item_and_predicted_id('none');
    }

    function render_add_pixel_button(){
        $instance = new AdNabuAdwordsRemarketing();
        ?>
        <br>
        <div class="col-12 text-center">
            <span class="icon-input-btn">
                <button name="enable_tracker" class="btn btn-primary mx-auto"
                        type="submit" id=
                        <?php
                        $pixel_id = wp_generate_password(20,false,false);
                        $add_pixel_nonce = wp_create_nonce( 'create_pixel_'. $pixel_id );
                        echo $pixel_id;
                        $do_on_add_pixel = 'enable_tracker(this.id,\'' . $instance->add_pixel_url() .
                                '&pixel_id=' . $pixel_id . '\',\'' . $add_pixel_nonce .'\')';
                        ?>
                        value="new" onclick="<?php echo $do_on_add_pixel ?>">
                    <i class="fas fa-rocket"> Enable Remarketing</i>
                </button>
            </span>
        </div>
        <?php
    }


    function render_pixel_div($current_item_id_expression){
        $instance = new AdNabuAdwordsRemarketing();
        $url = $instance->get_action_url("fetch-all");
        $pixel_json = $instance->fetch_pixel_json($url);
        $instance->sync_db_with_remote($pixel_json);
        ?>
        <div class="row">
            <?php
            if(count($pixel_json) > 0){
                $instance->show_table($pixel_json);
            }
            if(count($pixel_json) == 0 and  $current_item_id_expression !=''){
                render_add_pixel_button($instance, $current_item_id_expression);
            }
            ?>
        </div>
        <br>
    <?php
    }

  if($message != ''){
      $instance->show_message($message);
  }
  render_pixel_div($current_item_id_expression);
  render_item_id_related_div($current_item_id_expression);
    ?>





