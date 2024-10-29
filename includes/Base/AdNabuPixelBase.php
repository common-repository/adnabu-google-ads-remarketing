<?php
/**
 * Created by PhpStorm.
 * User: Mahaveer Chouhan
 * Date: 26/10/18
 * Time: 5:09 PM
 */

class AdNabuPixelBase extends AdNabuBasev2
{
    function sync_db_with_remote($remote_pixels_json){
        $local_pixel_array = $this->read_pixel_list_from_db();
        if(count($remote_pixels_json) != count($local_pixel_array)){
            foreach ($remote_pixels_json as $remote_pixel){
                $pixel = $remote_pixel['id'];
                if(!array_search( $pixel, $local_pixel_array)){
                    $this->save_pixel_to_db( $pixel,1);
                }
            }
        }
    }


    function read_pixel_list_from_db($status = 2){
        global $wpdb;
        $get_active_pixel_query = "SELECT pixel FROM  {$this->pixel_table}";
        if($status == 0 or $status == 1){
            $get_active_pixel_query = $wpdb->prepare("SELECT pixel FROM  {$this->pixel_table}  WHERE status = %d", $status);
        }
        $pixels = $wpdb->get_col($get_active_pixel_query);
        return $pixels;
    }


    function fetch_pixel_json($relative_url){
        $response = $this->custom_wp_remote(
            $this->add_base_url($relative_url),
            array()
        );
        $pixel_data = wp_remote_retrieve_body($response);
        $pixel_json = json_decode($pixel_data, true);
        return $pixel_json;
    }


    function show_table($pixels_json){
        $count = 1;
        ?>
        <br>
        <div class="container">
            <table class="table table-bordered text-center" id = "pixel_table">
                <thead class="thead-dark">
                <tr>
                    <th scope="col">#</th>
                    <th scope="col">Pixel ID</th>
                    <th scope="col">Pixel Name</th>
                    <th scope="col">Account Name</th>
                    <th scope="col">Account ID</th>
                    <th scope="col">Status</th>
                    <th scope="col">Delete</th>
                </tr>
                </thead>
                <tbody id = 'pixels'>
                <?php
                foreach ( $pixels_json as $pixel_data_dict ){
                    $this->show_pixel_row(
                        $count,
                        $pixel_data_dict
                    );
                    $count = $count + 1;
                }
                ?>
                </tbody>
            </table>
        </div>
        <?php
        return;
    }


    function show_pixel_row($count, $pixel_data_dict){
        $status = $this->get_pixel_status($pixel_data_dict['id']);
        if ($status == 1){
            $pixel_status = "checked";
        }
        else{
            $pixel_status = "";
        }

        $toggle_nonce = wp_create_nonce( 'toggle_pixel_'. $pixel_data_dict['id']);
        $delete_nonce = wp_create_nonce( 'delete_pixel_'. $pixel_data_dict['id']);

        $row =
            "<tr>
        	<th scope='row'>$count</th>
        	<td>{$pixel_data_dict['id']}</td>
        	<td>{$pixel_data_dict['name']}</td>
        	<td>{$pixel_data_dict['adwords_account_name']}</td>
        	<td>{$pixel_data_dict['adwords_account_id']}</td>
			<td>
				<label class='switch'>
					<input  {$pixel_status} type='checkbox' onclick='toggle(this.id,\"{$toggle_nonce}\")' id = {$pixel_data_dict['id']}>
 					<span class='slider round'></span>
 				</label>
 			</td>
 			<td>
 				<form method='post' action='#'>
 				    <input type='hidden' id='wpnonce' name='wp_nonce' value={$delete_nonce} />
 					<button name='delete' class='btn btn-sm' type='submit' value= {$pixel_data_dict['id']}>
  						<i class='fa fa-trash'></i>
  					</button>
  				</form>
  				<style>
                </style>
			</td>
		</tr>";
        echo $row;
    }


    function save_pixel_to_db($pixel_id, $status = 1){
        global $wpdb;
        require(ABSPATH . 'wp-admin/includes/upgrade.php');

        $wpdb->insert(
            $this->pixel_table,
            array(
                'pixel' => $pixel_id,
                'status' => $status),
            array('%s', '%d'));
    }


    function get_action_url($action = "fetch-all"){
        $action_value = "";
        if ($action == "pause"){
            $action_value = "disable";
        }
        elseif ($action == "enable" or $action == "unpause"){
            $action_value = "add";
        }
        elseif ($action == "delete"){
            $action_value = "remove";
        }
        elseif ($action == "fetch-all"){
            return  "woocommerce/fetch/pixels";
        }
        $url = "woocommerce/" . $action_value ."/pixel";
        return $url;
    }

    function add_pixel_url(){
        $query = http_build_query(array(
            'app_id' => $this::$app_id,
            'store' => get_option('adnabu_store_id')
        ));
        $url = "https://www.adnabu.com/woocommerce/add/pixel?" ;
        return $url . $query;
    }


    function enable_new_pixel($pixel_id){
        $data = array('pixel_id' => $pixel_id);

        $url = $this->get_action_url("fetch-all");
        $response = $this->custom_wp_remote(
            $this->add_base_url($url),
            $data);

        if( is_wp_error( $response ) ) {
            return;
        }
        if($pixel_id == json_decode(wp_remote_retrieve_body($response))['0']->id){
            $this->save_pixel_to_db($pixel_id);
        }
    }


    function delete_pixel($pixel_id){

        $url = $this->get_action_url("delete");
        $data = array('pixel_id' => $pixel_id);
        $response = $this->custom_wp_remote(
            $this->add_base_url($url),
            $data);

        if( is_wp_error( $response ) ) {
            return;
        }
        $message = json_decode(wp_remote_retrieve_body($response))->message;
        $type = json_decode(wp_remote_retrieve_body($response))->type;
        $this->show_message($message, $type);
        if ($type == "SUCCESS"){
            global $wpdb;
            require(ABSPATH . 'wp-admin/includes/upgrade.php');
            $wpdb->delete(
                $this->pixel_table,
                array('pixel' =>  $pixel_id));
        }
    }


    function get_pixel_status($pixel_id){
        global $wpdb;

        $get_pixel_status_query = $wpdb->prepare(
            "select status from  {$this->pixel_table}  where pixel = %s",
            $pixel_id);
        $status = $wpdb->get_row(
            $get_pixel_status_query,
            ARRAY_A ,
            0)['status'];
        return (int)$status;
    }


    function set_pixel_status($pixel_id, $status){
        global $wpdb;
        $row_affected = $wpdb->update(
            $this->pixel_table,
            array('status' => $status),
            array('pixel' => $pixel_id,),
            array('%d'),
            array('%s')
        );
        return $row_affected;
    }


    function flip_pixel_status($pixel_id){

        $current_status = $this->get_pixel_status($pixel_id);
        if ($current_status == 1 ){
            $status = 0;
            $message = "Tracker disabled successfully.";
        }
        else{
            $status = 1;
            $message = "Tracker enabled successfully.";
        }

        $row_affected = $this->set_pixel_status($pixel_id, $status);

        if($row_affected == 1){
            $this->show_message($message, "SUCCESS");
        }
        else{
            $this->show_message('Failed!', 'FAILURE');
        }
    }
}