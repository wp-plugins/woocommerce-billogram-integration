<?php
/**
 * Plugin Name: WooCommerce Billogram Integration
 * Plugin URI: http://plugins.svn.wordpress.org/woocommerce-billogram-integration/
 * Description: A Billogram 2 API Interface. Synchronizes products, orders and more to billogram.
 * Also fetches inventory from billogram and updates WooCommerce
 * Version: 1.3
 * Author: WooBill
 * Author URI: http://woobill.com
 * License: GPL2
 */
 if ( ! defined( 'ABSPATH' ) ) exit;
if(!defined('TESTING')){
    define('TESTING',true);
}

if(!defined('AUTOMATED_TESTING')){
    define('AUTOMATED_TESTING', false);
}

if(!defined('WORDPRESS_FOLDER')){
    define('WORDPRESS_FOLDER',$_SERVER['DOCUMENT_ROOT']);
}

if ( ! function_exists( 'logthis' ) ) {
    function logthis($msg) {
        if(TESTING){
            if(!file_exists(dirname(__FILE__).'/logfile.log')){
                $fileobject = fopen(dirname(__FILE__).'/logfile.log', 'a');
                chmod(dirname(__FILE__).'/logfile.log', 0666);
            }
            else{
                $fileobject = fopen(dirname(__FILE__).'/logfile.log', 'a');
            }

            if(is_array($msg) || is_object($msg)){
                fwrite($fileobject,print_r($msg, true));
            }
            else{
                fwrite($fileobject,date("Y-m-d H:i:s"). "\n" . $msg . "\n");
            }
        }
        else{
            error_log($msg);
        }
    }
}

if ( in_array( 'woocommerce/woocommerce.php', apply_filters( 'active_plugins', get_option( 'active_plugins' ) ) ) ) {

    load_plugin_textdomain( 'wc_billogram_extended', false, dirname( plugin_basename( __FILE__ ) ) . '/' );
    if ( ! class_exists( 'WCBillogramExtended' ) ) {


		//Add billogram payment class
		include_once("class-billogram2-payment.php");

        // in javascript, object properties are accessed as ajax_object.ajax_url, ajax_object.we_value
        function billogram_enqueue(){
            wp_enqueue_script('jquery');
            wp_register_script( 'billogram-script', plugins_url( '/woocommerce-billogram-integration/js/billogram.js' ) );
            wp_enqueue_script( 'billogram-script' );
        }

        add_action( 'admin_enqueue_scripts', 'billogram_enqueue' );
		
		
		//Add action to handle billogram invoice payment order sync
		add_action('woocommerce_checkout_order_processed', 'billogram_sent_invoice', 10, 1);
		function billogram_sent_invoice($order_id){
			$fnox = new WC_Billogram_Extended();
			$fnox->send_contact_to_billogram($order_id);
		}
		
		
        add_action( 'wp_ajax_initial_sync_products', 'billogram_initial_sync_products_callback' );

        function billogram_initial_sync_products_callback() {
            global $wpdb; // this is how you get access to the database

            $fnox = new WC_Billogram_Extended();
            if($fnox->initial_products_sync()){
                echo "Produkter är synkroniserade utan problem.";
            }
            else{
                echo "Något gick fel";
            }
            die(); // this is required to return a proper result
        }

        add_action( 'wp_ajax_sync_orders', 'billogram_sync_orders_callback' );

        function billogram_sync_orders_callback() {
            global $wpdb; // this is how you get access to the database
            $fnox = new WC_Billogram_Extended();
            if($fnox->sync_orders_to_billogram()){
                echo "Ordrar är synkroniserade utan problem.";
            }
            else{
                echo "Något gick fel";
            }
            die(); // this is required to return a proper result
        }

        add_action( 'wp_ajax_fetch_contacts', 'billogram_fetch_contacts_callback' );

        function billogram_fetch_contacts_callback() {
            global $wpdb; // this is how you get access to the database
            $fnox = new WC_Billogram_Extended();
            $fnox->fetch_billogram_contacts();
            echo "Kontakter är synkroniserade utan problem.";
            die(); // this is required to return a proper result
        }

        add_action( 'wp_ajax_send_support_mail', 'billogram_send_support_mail_callback' );
		

        function billogram_send_support_mail_callback() {

            //$message = 'Kontakta ' . $_POST['name'] . ' <br>på ' . $_POST['company'] . ' <br>antingen på ' .$_POST['telephone'] .' <br>eller ' . $_POST['email'] . ' <br>gällande: <br>' . $_POST['subject'];
			$message = '<html><body><table rules="all" style="border-color: #91B9F6; width:70%; font-family:Calibri, Arial, sans-serif;" cellpadding="10">';
			if(isset($_POST['supportForm']) && $_POST['supportForm'] ==  "support"){
				$message .= '<tr><td align="right">Type: </td><td align="left" colspan="1"><strong>Support</strong></td></tr>';
			}else{
				$message .= '<tr><td align="right">Type: </td><td align="left" colspan="1"><strong>Installationssupport</strong></td></tr>';
			}
			$message .= '<tr><td align="right">Företag: </td><td align="left">'.$_POST['company'].'</td></tr>';
			$message .= '<tr><td align="right">Namn: </td><td align="left">'.$_POST['name'].'</td></tr>';
			$message .= '<tr><td align="right">Telefon: </td><td align="left">'.$_POST['telephone'].'</td></tr>';
			$message .= '<tr><td align="right">Email: </td><td align="left">'.$_POST['email'].'</td></tr>';
			$message .= '<tr><td align="right">Ärende: </td><td align="left">'.$_POST['subject'].'</td></tr>';
			
			if(isset($_POST['supportForm']) && $_POST['supportForm'] ==  "support"){
				$options = get_option('woocommerce_billogram_general_settings');
				$order_options = get_option('woocommerce_billogram_order_settings');
				$message .= '<tr><td align="right" colspan="1"><strong>Allmänna inställningar</strong></td></tr>';
				$message .= '<tr><td align="right">License Nyckel: </td><td align="left">'.$options['license-key'].'</td></tr>';
				$message .= '<tr><td align="right">Billogram API-användar ID: </td><td align="left">'.$options['api-key'].'</td></tr>';
				$message .= '<tr><td align="right">Billogram Lösenord: </td><td align="left">'.$options['authorization_code'].'</td></tr>';
				$message .= '<tr><td align="right">Billogram läge: </td><td align="left">'.$options['billogram-mode'].'</td></tr>';
				$message .= '<tr><td align="right">Aktivera ORDER synkning: </td><td align="left">'.$options['activate-orders'].'</td></tr>';
				$message .= '<tr><td align="right">ORDER synkning method: </td><td align="left">'.$options['activate-invoices'].'</td></tr>';
				$message .= '<tr><td align="right">Aktivera alla beställningar synkning: </td><td align="left">'.$options['activate-allsync'].'</td></tr>';
				$message .= '<tr><td align="right">Aktivera PRODUKT synkning: </td><td align="left">'.$options['activate-prices'].'</td></tr>';
				$message .= '<tr><td align="right" colspan="1"><strong>Orderinställningar</strong></td></tr>';
				$message .= '<tr><td align="right">Administrationsavgift: </td><td align="left">'.$order_options['admin-fee'].'</td></tr>';
				$message .= '<tr><td align="right">Antal dagar till fakturans förfallodatum days: </td><td align="left">'.$order_options['due-days'].'</td></tr>';
			}
			
			$message .= '</table></html></body>';
	
			
			$headers = "MIME-Version: 1.0\r\n";
			$headers .= "Content-type: text/html; charset=utf-8 \r\n";
			//$headers .= "From:".get_option('admin_email')."\r\n";
			
            echo wp_mail( 'support@woobill.com', 'Billogram Support', $message , $headers) ? "success" : "error";
            //die(); // this is required to return a proper result
        }
		
		
		//Test the connection
		
		function billogram_test_connection_callback() {
			include_once("class-billogram2-api.php");
			$fnox = new WC_Billogram_Extended();
			$apiInterface = new WCB_API();
			if( $fnox->is_license_key_valid() == "Invalid" ){
				echo "License Key is Invalid!";
				die(); // this is required to return a proper result
			}else{
				$data = $apiInterface->fetch_settings();
				if( $data['status'] == "OK" ){
					echo "Hello ".$data['data']['name'].", your integration works fine!";
					die(); // this is required to return a proper result
				}else{
					echo "Your Billogram API-användar ID and Billogram Lösenord does not match!";
					die(); // this is required to return a proper result
				}
				//echo $fnox->is_api_key_valid()? $fnox->is_api_key_valid() : false;
			}
			echo "Something went wrong, please try again later!";
			die(); // this is required to return a proper result
        }
		
		//Connection testing ends

        add_action( 'wp_ajax_test_connection', 'billogram_test_connection_callback' );
		
		
		//License key invalid warning message.
		
		function license_key_invalid() {
			$options = get_option('woocommerce_billogram_general_settings');
			$fnox = new WC_Billogram_Extended();
			$key_status = $fnox->is_license_key_valid();
			if(!isset($options['license-key']) || $options['license-key'] == '' || $key_status!='Active'){
			?>
                <div class="error">
                    <p>WooCommerce Billogram Integration: License Key Invalid! <button type="button button-primary" class="button button-primary" title="" style="margin:5px" onclick="window.open('http://whmcs.onlineforce.net/cart.php?a=add&pid=54&billingcycle=annually','_blank');">Hämta license-Nyckel</button></p>
                </div>
			<?php
			}
		}
		
		add_action( 'admin_notices', 'license_key_invalid' );
		//License key invalid warning message ends.
		
		//Code for handlin the billogram callbacks
		function billogram_callback() {
			include_once("class-billogram2-api.php");
			$apiInterface = new WCB_API();
			global $wpdb;
			logthis("callback");
			$entityBody = file_get_contents('php://input');
			$billogram = json_decode($entityBody);
			$ocr_number = $billogram->billogram->ocr_number;
			//logthis("billogram");
			//logthis($billogram);
			if($billogram->event->type == 'BillogramSent'){
				logthis($billogram->billogram->id);
				$invoice = $apiInterface->get_invoice($billogram->billogram->id);
				//logthis("invoice:");
				//logthis($invoice);
				$orderID = $invoice->info->order_no;
				$wpdb->query("UPDATE wcb_orders SET invoice_no = ".$billogram->event->data->invoice_no.", ocr_number=".$ocr_number." WHERE order_id = ".$orderID);
				//logthis("orderID");
				//logthis($orderID);
				
				return http_response_code(200);
			}
			
			if($billogram->event->type == 'BillogramEnded'){
				$result = $wpdb->get_results("SELECT order_id FROM wcb_orders WHERE ocr_number = ".$ocr_number);
				$order = new WC_Order($result[0]->order_id);
				$order->update_status('Completed');
				return http_response_code(200);
			}
			die(); // this is required to return a proper result
		}
		add_action( 'wp_ajax_nopriv_billogram_callback', 'billogram_callback' );


		//Section for wordpress pointers
		
		function billogram_wp_pointer_hide_callback(){
			update_option('billogram-tour', false);
		}
		add_action( 'wp_ajax_wp_pointer_hide', 'billogram_wp_pointer_hide_callback' );
		
		$billogram_tour = get_option('billogram-tour');
		
		if(isset($billogram_tour) && $billogram_tour){
			// Register the pointer styles and scripts
			add_action( 'admin_enqueue_scripts', 'enqueue_scripts' );
			
			// Add pointer javascript
			add_action( 'admin_print_footer_scripts', 'add_pointer_scripts' );
		}
		
		// enqueue javascripts and styles
		function enqueue_scripts()
		{
			wp_enqueue_style( 'wp-pointer' );
			wp_enqueue_script( 'wp-pointer' );	
		}
		
		// Add the pointer javascript
		function add_pointer_scripts()
		{
			$content = '<h3>WooCommerce Billogram Integration</h3>';
			$content .= '<p>You’ve just installed WooCommerce Billogram Integration by WooBill. Please use the plugin options page to setup your integration.</p>';
		
			?>
			
            <script type="text/javascript">
				jQuery(document).ready( function($) {
					$("#toplevel_page_woocommerce_billogram_options").pointer({
						content: '<?php echo $content; ?>',
						position: {
							edge: 'left',
							align: 'center'
						},
						close: function() {
							// what to do after the object is closed
							var data = {
								action: 'wp_pointer_hide'
							};
	
							jQuery.post(ajaxurl, data);
						}
					}).pointer('open');
				});
			</script>
		   
		<?php
		}
		
		//Section for wordpress pointers ends.


		//Section for Plugin installation and activation
		/**
		 * Creates tables for WooCommerce Billogram
		 *
		 * @access public
		 * @param void
		 * @return bool
		 */
		function billogram_install(){
			global $wpdb;
                $table_name = "wcb_orders";
                $sql = "CREATE TABLE IF NOT EXISTS ".$table_name."( id mediumint(9) NOT NULL AUTO_INCREMENT,
                        order_id mediumint(9) NOT NULL,
						invoice_no mediumint(20) NOT NULL,
						ocr_number bigint(9) NOT NULL,
                        synced tinyint(1) DEFAULT FALSE NOT NULL,
                        UNIQUE KEY id (id)
                );";

                require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );
                dbDelta( $sql );

                $table_name = "wcb_customers";
                $sql = "CREATE TABLE IF NOT EXISTS ".$table_name."( id mediumint(9) NOT NULL AUTO_INCREMENT,
                        customer_number VARCHAR(50) NULL,
                        email VARCHAR(100) NOT NULL,
                        UNIQUE KEY id (id),
                        UNIQUE (email)
                );";
                dbDelta( $sql );

                $table_name = "wcb_products";
                $sql = "CREATE TABLE IF NOT EXISTS ".$table_name."( id mediumint(9) NOT NULL AUTO_INCREMENT,
                        product_id mediumint(9) NULL,
                        product_sku VARCHAR(250) NOT NULL,
                        UNIQUE KEY id (id),
                        UNIQUE (product_sku)
                );";
                dbDelta( $sql );
				
				update_option('billogram_version', '1.3');
				
				add_option('billogram-tour', true);
		}
		
		/**
		 * Drops tables for WooCommerce Billogram
		 *
		 * @access public
		 * @param void
		 * @return bool
		 */
		function billogram_uninstall(){
			global $wpdb;
			$wcb_orders = 'wcb_orders';
			$wcb_customers = 'wcb_customers';
			$wcb_products = 'wcb_products';
			$wpdb->query ("DROP TABLE ".$wcb_orders.";");
			$wpdb->query ("DROP TABLE ".$wcb_customers.";");	
			$wpdb->query ("DROP TABLE ".$wcb_products.";");	
			delete_option('billogram-tour');	
			delete_option('billogram_version');
			delete_option('woocommerce_billogram_general_settings');	
			delete_option('local_key_billogram_plugin');
			delete_option('woocommerce_billogram_order_settings');		
			return true;
		}
		
		/**
		 *
		 *Functon for plugin update
		*/
		function billogram_update(){
			global $wpdb;
			$table_name = "wcb_orders";
			$billogram_version = get_option('billogram_version');
			if($billogram_version != '1.3'){
				$wpdb->query ("ALTER TABLE ".$table_name." 
						   ADD invoice_no MEDIUMINT( 20 ) NOT NULL AFTER  order_id, 
						   ADD ocr_number BIGINT( 9 ) NOT NULL AFTER  invoice_no");
			}
			update_option('billogram_version', '1.3');
		}
		
		add_action( 'plugins_loaded', 'billogram_update' );
		
		// install necessary tables
		register_activation_hook( __FILE__, 'billogram_install');
		register_uninstall_hook( __FILE__, 'billogram_uninstall');
		//Section for plugin installation and activation ends

        /**
         * Localisation
         **/
        load_plugin_textdomain( 'wc_billogram', false, dirname( plugin_basename( __FILE__ ) ) . '/' );

        class WC_Billogram_Extended {

            private $general_settings_key = 'woocommerce_billogram_general_settings';
            private $accounting_settings_key = 'woocommerce_billogram_accounting_settings';
            private $order_settings_key = 'woocommerce_billogram_order_settings';
            private $support_key = 'woocommerce_billogram_support';
            private $manual_action_key = 'woocommerce_billogram_manual_action';
            private $start_action_key = 'woocommerce_billogram_start_action';
            private $general_settings;
            private $accounting_settings;
            private $plugin_options_key = 'woocommerce_billogram_options';
            private $plugin_settings_tabs = array();

            public $FORTNOX_ERROR_CODE_PRODUCT_NOT_EXIST = 2001302;
            public $FORTNOX_ERROR_CODE_ORDER_EXISTS = 2000861;

            public function __construct() {

                //call register settings function
                add_action( 'init', array( &$this, 'load_settings' ) );
                add_action( 'admin_init', array( &$this, 'register_woocommerce_billogram_start_action' ));
                add_action( 'admin_init', array( &$this, 'register_woocommerce_billogram_general_settings' ));
                add_action( 'admin_init', array( &$this, 'register_woocommerce_billogram_order_settings' ));
                add_action( 'admin_init', array( &$this, 'register_woocommerce_billogram_manual_action' ));
                add_action( 'admin_init', array( &$this, 'register_woocommerce_billogram_support' ));
                add_action( 'admin_menu', array( &$this, 'add_admin_menus' ) );

                // Register WooCommerce Hooks
                if(!AUTOMATED_TESTING)
                    add_action( 'woocommerce_order_status_completed', array(&$this, 'send_contact_to_billogram'), 10, 1 );

                if(!AUTOMATED_TESTING)
                    add_action( 'save_post', array(&$this, 'send_product_to_billogram'), 10, 1 );
            }

            /***********************************************************************************************************
             * ADMIN SETUP
             ***********************************************************************************************************/

            /**
             * Adds admin menu
             *
             * @access public
             * @param void
             * @return void
             */
            function add_admin_menus() {
				add_menu_page( 'WooCommerce Billogram Integration', 'Billogram', 'manage_options', $this->plugin_options_key, array( &$this, 'woocommerce_billogram_options_page' ) );
				/*$current_tab = isset( $_GET['tab'] ) ? $_GET['tab'] : $this->start_action_key;
				$this->plugin_settings_tabs[$this->start_action_key] = 'Välkommen!';
				$this->plugin_settings_tabs[$this->general_settings_key] = 'Allmänna inställningar';
				$this->plugin_settings_tabs[$this->order_settings_key] = 'Orderinställningar';
				$this->plugin_settings_tabs[$this->manual_action_key] = 'Manuella funktioner';
				$this->plugin_settings_tabs[$this->support_key] = 'Support';
				foreach ( $this->plugin_settings_tabs as $tab_key => $tab_caption ) {
                    $active = $current_tab == $tab_key ? 'nav-tab-active' : '';
					add_submenu_page( $this->plugin_options_key, $tab_caption, $tab_caption, 'manage_options', $this->plugin_options_key.'&tab=' . $tab_key, array( &$this, 'woocommerce_billogram_options_page' ) );
                }*/
            }

            /**
             * Generates html for textfield for given settings params
             *
             * @access public
             * @param void
             * @return void
             */
            function field_gateway($args) {
                $options = get_option($args['tab_key']);?>

                <input type="hidden" name="<?php echo $args['tab_key']; ?>[<?php echo $args['key']; ?>]" value="<?php echo $args['key']; ?>" />

                <select name="<?php echo $args['tab_key']; ?>[<?php echo $args['key'] . "_payment_method"; ?>]" >';
                    <option value=""<?php if(isset($options[$args['key'] . "_payment_method"]) && $options[$args['key'] . "_payment_method"] == ''){echo 'selected="selected"';}?>>Välj nedan</option>
                    <option value="CARD"<?php if(isset($options[$args['key'] . "_payment_method"]) && $options[$args['key'] . "_payment_method"] == 'CARD'){echo 'selected="selected"';}?>>Kortbetalning</option>
                    <option value="BANK"<?php if(isset($options[$args['key'] . "_payment_method"]) && $options[$args['key'] . "_payment_method"] == 'BANK'){echo 'selected="selected"';}?>>Bankgiro/Postgiro</option>
                </select>
                <?php
                $str = '';
                if(isset($options[$args['key'] . "_book_keep"])){
                    if($options[$args['key'] . "_book_keep"] == 'on'){
                        $str = 'checked = checked';
                    }
                }
                ?>
                <span>Bokför automatiskt:  </span>
                <input type="checkbox" name="<?php echo $args['tab_key']; ?>[<?php echo $args['key'] . "_book_keep"; ?>]" <?php echo $str; ?> />

            <?php
            }

            /**
             * Generates html for textfield for given settings params
             *
             * @access public
             * @param void
             * @return void
             */
            function field_option_text($args) {
                $options = get_option($args['tab_key']);
                $val = '';
                if(isset($options[$args['key']] )){
                    $val = esc_attr( $options[$args['key']] );
                }
                ?>
                <input <?php echo isset($args['id'])? 'id="'.$args['id'].'"': ''; ?> type="text" name="<?php echo $args['tab_key']; ?>[<?php echo $args['key']; ?>]" value="<?php echo $val; ?>" />
                <span><i><?php echo $args['desc']; ?></i></span>
            <?php
            }
			
			
			/**
             * Generates html for date field for given settings params
             *
             * @access public
             * @param void
             * @return void
             */
            function field_option_date($args) {
                $options = get_option($args['tab_key']);
                $val = '';
                if(isset($options[$args['key']] )){
                    $val = esc_attr( $options[$args['key']] );
                }
                ?>
                <input <?php echo isset($args['id'])? 'id="'.$args['id'].'"': ''; ?> type="date" name="<?php echo $args['tab_key']; ?>[<?php echo $args['key']; ?>]" value="<?php echo $val; ?>" />
                <span><i><?php echo $args['desc']; ?></i></span>
            <?php
            }
            
            /**
             * Generates html for dropdown for given settings of sandbox params
             *
             * @access public
             * @param void
             * @return void
             */
            function field_mode_dropdown($args) {
                $options = get_option($args['tab_key']);
                $str = '';
                $str2 = '';
                if(isset($options[$args['key']])){
                    if($options[$args['key']] == 'Live'){
                        $str = 'selected';
                    }
                    else
                    {
                        $str2 = 'selected';
                    }
                }

                ?>
                <select <?php echo isset($args['id'])? 'id="'.$args['id'].'"': ''; ?> name="<?php echo $args['tab_key']; ?>[<?php echo $args['key']; ?>]">
                    <option <?php echo $str; ?>>Live</option>
                    <option <?php echo $str2; ?>>Sandbox</option>
                </select>
                <span id="sandbox-mode"><i><?php echo $args['desc']; ?></i></span>
            <?php
            }
            
            /**
             * Generates html for dropdown for given settings params
             *
             * @access public
             * @param void
             * @return void
             */
            function field_option_dropdown($args) {
                $options = get_option($args['tab_key']);
                $str = '';
                $str2 = '';
                if(isset($options[$args['key']])){
                    if($options[$args['key']] == 'Skapa faktura och skicka som epost'){
                        $str = 'selected';
                    }
					elseif($options[$args['key']] == 'Skapa faktura och skicka som brev'){
						$str3 = 'selected';
					}
                    else
                    {
                        $str2 = 'selected';
                    }
                }

                ?>
                <select <?php echo isset($args['id'])? 'id="'.$args['id'].'"':''; ?> name="<?php echo $args['tab_key']; ?>[<?php echo $args['key']; ?>]">
                	<option <?php echo $str; ?>>Skapa faktura och skicka som epost</option>
                    <option <?php echo $str3; ?>>Skapa faktura och skicka som brev</option>
                    <option <?php echo $str2; ?>>Spara utkast</option>
                </select>
                <span><i><?php echo $args['desc']; ?></i></span>
            <?php
            }


            /**
             * Generates html for checkbox for given settings params
             *
             * @access public
             * @param void
             * @return void
             */
            function field_option_checkbox($args) {
                $options = get_option($args['tab_key']);
                $str = '';
                if(isset($options[$args['key']])){
                    if($options[$args['key']] == 'on'){
                        $str = 'checked = checked';
                    }
                }

                ?>
                <input <?php echo isset($args['id'])? 'id="'.$args['id'].'"': ''; ?> type="checkbox" name="<?php echo $args['tab_key']; ?>[<?php echo $args['key']; ?>]" <?php echo $str; ?> />
                <span><i><?php echo $args['desc']; ?></i></span>
            <?php
            }

            /**
             * WooCommerce Loads settigns
             *
             * @access public
             * @param void
             * @return void
             */
            function load_settings() {
                $this->general_settings = (array) get_option( $this->general_settings_key );
                $this->accounting_settings = (array) get_option( $this->accounting_settings_key );
                $this->order_settings = (array) get_option( $this->order_settings_key );
            }

            /**
             * Tabs and plugin page setup
             *
             * @access public
             * @param void
             * @return void
             */
            function plugin_options_tabs() {
                $current_tab = isset( $_GET['tab'] ) ? $_GET['tab'] : $this->start_action_key;
                $options = get_option('woocommerce_billogram_general_settings');
                echo '<div class="wrap"><h2>WooCommerce Billogram Integration</h2><div id="icon-edit" class="icon32"></div></div>';
                $key_status = $this->is_license_key_valid();
                if(!isset($options['license-key']) || $options['license-key'] == '' || $key_status!='Active'){
                    echo "<button type=\"button button-primary\" class=\"button button-primary\" title=\"\" style=\"margin:5px\" onclick=\"window.open('http://whmcs.onlineforce.net/cart.php?a=add&pid=54&billingcycle=annually','_blank');\">Hämta license-Nyckel</button> <div class='key_error'>License Key ".$key_status."</div>";

                }

                echo '<h2 class="nav-tab-wrapper">';

                foreach ( $this->plugin_settings_tabs as $tab_key => $tab_caption ) {
                    $active = $current_tab == $tab_key ? 'nav-tab-active' : '';
                    echo '<a class="nav-tab ' . $active . '" href="?page=' . $this->plugin_options_key . '&tab=' . $tab_key . '">' . $tab_caption . '</a>';
                }
                echo '</h2>';

            }

            /**
             * WooCommerce Billogram General Settings
             *
             * @access public
             * @param void
             * @return void
             */
            function register_woocommerce_billogram_general_settings() {

                $this->plugin_settings_tabs[$this->general_settings_key] = 'Allmänna inställningar';

                register_setting( $this->general_settings_key, $this->general_settings_key );
                add_settings_section( 'section_general', 'Allmänna inställningar', array( &$this, 'section_general_desc' ), $this->general_settings_key );
                add_settings_field( 'woocommerce-billogram-license-key', 'License Nyckel', array( &$this, 'field_option_text' ), $this->general_settings_key, 'section_general', array ( 'id' => 'license-key', 'tab_key' => $this->general_settings_key, 'key' => 'license-key', 'desc' => 'Här anges License-nyckeln du har erhållit från oss via mail.') );
                add_settings_field( 'woocommerce-billogram-api-key', 'Billogram API-användar ID', array( &$this, 'field_option_text' ), $this->general_settings_key, 'section_general', array ( 'tab_key' => $this->general_settings_key, 'key' => 'api-key', 'desc' => 'Här anges din API-användar ID från Billogram. <a target="_blank" href="http://vimeo.com/62060237#t=0m50s">Videoinstruktion</a>') );
                add_settings_field( 'woocommerce-billogram-authorization-code', 'Billogram Lösenord', array( &$this, 'field_option_text' ), $this->general_settings_key, 'section_general', array ( 'tab_key' => $this->general_settings_key, 'key' => 'authorization_code', 'desc' => 'Här anges din API kod från Billogram. <a target="_blank" href="http://vimeo.com/62060237#t=0m50s">Videoinstruktion</a>') );
                add_settings_field( 'woocommerce-billogram-billogram-mode', 'Billogram läge', array( &$this, 'field_mode_dropdown'), $this->general_settings_key, 'section_general', array ( 'id' => 'billogram-mode', 'tab_key' => $this->general_settings_key, 'key' => 'billogram-mode', 'desc' => 'Välj LIVE. SANDBOX läge används endast av utvecklare'));
				add_settings_field( 'woocommerce-billogram-activate-orders', 'Aktivera ORDER synkning', array( &$this, 'field_option_checkbox' ), $this->general_settings_key, 'section_general', array ( 'id' => 'activate-order-sync', 'tab_key' => $this->general_settings_key, 'key' => 'activate-orders', 'desc' => 'Skal vara vald för att ordrar skal synkas till Billogram') );
                add_settings_field( 'woocommerce-billogram-activate-invoices', 'ORDER synkning method', array( &$this, 'field_option_dropdown'), $this->general_settings_key, 'section_general', array ( 'id' => 'order-sync', 'tab_key' => $this->general_settings_key, 'key' => 'activate-invoices', 'desc' => 'Välj här vad som skal hända i Billogram när en order i woocommerce synkas ditt'));
				add_settings_field( 'woocommerce-billogram-activate-allsync', 'Aktivera alla beställningar synkning', array( &$this, 'field_option_checkbox' ), $this->general_settings_key, 'section_general', array ( 'tab_key' => $this->general_settings_key, 'key' => 'activate-allsync', 'desc' => 'Synka alla ordrar från WooCommerce till Billogram oavsett om kund väljer annat betalningsalternativ (t.ex; Paypa, Dibs, Stripe, Payson etc.) <br><i style="margin-left:25px; color: #F00;">Om du är osäker vad du ska välja här rekommenderar vi att du inte markerar detta alternativ.</i>') );
                add_settings_field( 'woocommerce-billogram-activate-prices', 'Aktivera PRODUKT synkning', array( &$this, 'field_option_checkbox' ), $this->general_settings_key, 'section_general', array ( 'tab_key' => $this->general_settings_key, 'key' => 'activate-prices', 'desc' => '') );              
            }

            /**
             * WooCommerce Billogram Accounting Settings
             *
             * @access public
             * @param void
             * @return void
             */
            function register_woocommerce_billogram_accounting_settings() {

                $this->plugin_settings_tabs[$this->accounting_settings_key] = 'Bokföringsinställningar';

                register_setting( $this->accounting_settings_key, $this->accounting_settings_key );
                add_settings_section( 'section_accounting', 'Bokföringsinställningar', array( &$this, 'section_accounting_desc' ), $this->accounting_settings_key );
                add_settings_field( 'woocommerce-billogram-account-25-vat', 'Konto försäljning 25% Moms', array( &$this, 'field_option_text'), $this->accounting_settings_key, 'section_accounting', array ( 'tab_key' => $this->accounting_settings_key, 'key' => 'account-25-vat', 'desc' => '') );
                add_settings_field( 'woocommerce-billogram-account-12-vat', 'Konto försäljning 12% Moms', array( &$this, 'field_option_text' ), $this->accounting_settings_key, 'section_accounting', array ( 'tab_key' => $this->accounting_settings_key, 'key' => 'account-12-vat', 'desc' => '') );
                add_settings_field( 'woocommerce-billogram-account-6-vat', 'Konto försäljning 6% Moms', array( &$this, 'field_option_text' ), $this->accounting_settings_key, 'section_accounting', array ( 'tab_key' => $this->accounting_settings_key, 'key' => 'account-6-vat', 'desc' => '') );
                add_settings_field( 'woocommerce-billogram-taxclass-account-25-vat', 'Skatteklass för 25% Moms', array( &$this, 'field_option_text' ), $this->accounting_settings_key, 'section_accounting', array ( 'tab_key' => $this->accounting_settings_key, 'key' => 'taxclass-account-25-vat', 'desc' => '') );
                add_settings_field( 'woocommerce-billogram-taxclass-account-12-vat', 'Skatteklass för 12% Moms', array( &$this, 'field_option_text' ), $this->accounting_settings_key, 'section_accounting', array ( 'tab_key' => $this->accounting_settings_key, 'key' => 'taxclass-account-12-vat', 'desc' => '') );
                add_settings_field( 'woocommerce-billogram-taxclass-account-6-vat', 'Skatteklass för 6% Moms', array( &$this, 'field_option_text' ), $this->accounting_settings_key, 'section_accounting', array ( 'tab_key' => $this->accounting_settings_key, 'key' => 'taxclass-account-6-vat', 'desc' => '') );

            }


            /**
             * WooCommerce Manual Actions Settings
             *
             * @access public
             * @param void
             * @return void
             */
            function register_woocommerce_billogram_manual_action() {

                $this->plugin_settings_tabs[$this->manual_action_key] = 'Manuella funktioner';
                register_setting( $this->manual_action_key, $this->manual_action_key );
            }


            /**
             * WooCommerce Start Actions
             *
             * @access public
             * @param void
             * @return void
             */
            function register_woocommerce_billogram_start_action() {
                $this->plugin_settings_tabs[$this->start_action_key] = 'Välkommen!';
                register_setting( $this->start_action_key, $this->start_action_key );
            }


            /**
             * WooCommerce Billogram Order Settings
             *
             * @access public
             * @param void
             * @return void
             */
            function register_woocommerce_billogram_order_settings() {

                $this->plugin_settings_tabs[$this->order_settings_key] = 'Orderinställningar';

                register_setting( $this->order_settings_key, $this->order_settings_key );
                add_settings_section( 'section_order', 'Orderinställningar', array( &$this, 'section_order_desc' ), $this->order_settings_key );
                add_settings_field( 'woocommerce-billogram-admin-fee', 'Administrationsavgift', array( &$this, 'field_option_text'), $this->order_settings_key, 'section_order', array ( 'id' => 'admin-fee', 'tab_key' => $this->order_settings_key, 'key' => 'admin-fee', 'desc' => '<br>Här anges fakturaavgiften/administrationsavgiften för Billogram <br>Lämna fältet tomt om avgift redan är konfigurerat i Billogram kontot under: Mitt konto  --> Inställningar --> Fakturainställningar --> Faktura avgift') );
				
				//add_settings_field( 'woocommerce-billogram-due-date', 'Invoice Due date', array( &$this, 'field_option_date'), $this->order_settings_key, 'section_order', array ( 'id' => 'due-date', 'tab_key' => $this->order_settings_key, 'key' => 'due-date', 'desc' => '<br>Om inte inställd, då standard kommer att vara 30 dagar efter fakturadatum (eller beroende på grund dagar)') );
				
				add_settings_field( 'woocommerce-billogram-due-days', 'Antal dagar till fakturans förfallodatum', array( &$this, 'field_option_text'), $this->order_settings_key, 'section_order', array ( 'id' => 'due-days', 'tab_key' => $this->order_settings_key, 'key' => 'due-days', 'desc' => '<br>Om detta ej ställs in så är standard 30 dagar') );
                /*add_settings_field( 'woocommerce-billogram-payment-options', 'Betalningsvillkor för order', array( &$this, 'field_option_text'), $this->order_settings_key, 'section_order', array ( 'tab_key' => $this->order_settings_key, 'key' => 'payment-options', 'desc' => 'Här anges Billogram-koden för betalningsalternativ för ordern. Koder finns under INSTÄLLNINGAR->BOKFÖRING->BETALNINGSALTERNATIV i Billogram.') );*/
            }

            /**
             * WooCommerce Billogram Accounting Settings
             *
             * @access public
             * @param void
             * @return void
             */
            function register_woocommerce_billogram_support() {

                $this->plugin_settings_tabs[$this->support_key] = 'Support';
                register_setting( $this->support_key, $this->support_key );
            }

            /**
             * The description for the general section
             *
             * @access public
             * @param void
             * @return void
             */
            function section_general_desc() { echo 'Här anges grundinställningar för Billogramkopplingen och här kan man styra vilka delar som ska synkas till Billogram'; }

            /**
             * The description for the accounting section
             *
             * @access public
             * @param void
             * @return void
             */
            function section_accounting_desc() { echo 'Beskrivning bokföringsinställningar.'; }

            /**
             * The description for the shipping section
             *
             * @access public
             * @param void
             * @return void
             */
            function section_order_desc() { echo ''; }

            /**
             * Options page
             *
             * @access public
             * @param void
             * @return void
             */
            function woocommerce_billogram_options_page() {
                $tab = isset( $_GET['tab'] ) ? $_GET['tab'] : $this->start_action_key;?>

                <!-- CSS -->
                <style>
                    li.logo,  {
                        float: left;
                        width: 100%;
                        padding: 20px;
                    }
                    li.full {
	                    padding: 10px 0;
                        height: 50px;
                    }
                    li.full img, img.test_load{
                        float: left;
                        margin: -5px 0 0 5px;
                        display: none;
                    }
					span.test_warning{
						float: left;
						margin:25px 0px 0px 10px;
					}
                    li.col-two {
                        float: left;
                        width: 380px;
                        margin-left: 1%;
                    }
                    li.col-onethird, li.col-twothird {
	                    float: left;
                    }
                    li.col-twothird {
	                    max-width: 772px;
	                    margin-right: 20px;
                    }
                    li.col-onethird {
	                    width: 300px;
                    }
                    .mailsupport {
	                	background: #dadada;
	                	border-radius: 4px;
	                	-moz-border-radius: 4px;
	                	-webkit-border-radius: 4px;
	                	max-width: 230px;
	                	padding: 0 0 20px 20px;
	                }
	                .mailsupport > h2 {
		                font-size: 20px;
		            }
	                form#support table.form-table tbody tr td, form#installationSupport table.form-table tbody tr td {
		                padding: 4px 0 !important;
		            }
		            form#support input, form#support textarea, form#installationSupport input, form#support textarea {
			                border: 1px solid #b7b7b7;
			                border-radius: 3px;
			                -moz-border-radius: 3px;
			                -webkit-border-radius: 3px;
			                box-shadow: none;
			                width: 210px;
			        }
			        form#support textarea, form#installationSupport textarea {
				        height: 60px;
			        }
			        form#support button, form#installationSupport button {
				        float: left;
				        margin: 0 !important;
				        min-width: 100px;
				    }
				    ul.manuella li.full button.button {
					       clear: left;
					       float: left;
					       min-width: 250px;
				    }
				    ul.manuella li.full > p {
					        clear: right;
					        float: left;
					        margin: 2px 0 20px 11px;
					        max-width: 440px;
					        padding: 5px 10px;
					}
					.key_error
					{
						 background-color: white;
					    color: red;
					    display: inline;
					    font-weight: bold;
					    margin-top: 5px;
					    padding: 5px;
					    position: absolute;
					    text-align: center;
					    width: 200px;
					}
					.testConnection{
						float:left;
					}
					
					p.submit{
						float: left;
						width: auto;
						padding: 0px;
					}
					/*li.wp-first-item{
						display:none;
					}*/
					span#sandbox-mode{
						color:#F00
					}
					span.error{
						color:#F00
					}
                </style>
                <script type="text/javascript">
					jQuery(document).ready(function() {
						var element = jQuery('#order-sync').parent().parent();
						if(jQuery('#activate-order-sync').is(':checked')){
							element.show();
						}else{
							element.hide();
						}
						jQuery('#activate-order-sync').change(function() {
							if(this.checked) {
								element.show(300);							
							}else{
								element.hide(300);
							}
						});
						
						//script for sandbox text
						if(jQuery('#billogram-mode').val() == "Live"){
							jQuery('#sandbox-mode').hide();
						}
						jQuery('#billogram-mode').change(function(){
							if(jQuery('#billogram-mode').val() == "Live"){
								jQuery('#sandbox-mode').hide(100);
							}else{
								jQuery('#sandbox-mode').show(100);
							}
						});
						
						jQuery("#license-key").keyup(function(){
							var str = jQuery("#license-key").val();
							var patt = /wbm-[a-zA-Z0-9][^\W]+/gi;
							var licenseMatch = patt.exec(str);
							if(licenseMatch){
								licenseMatch = licenseMatch.toString();
								if(licenseMatch.length == 24){
									jQuery("#license-key").next().removeClass("error");
									jQuery("#license-key").next().children("i").html("Här anges License-nyckeln du har erhållit från oss via mail.");
								}else{
									jQuery("#license-key").next().children("i").html("Ogiltigt format");
									jQuery("#license-key").next().addClass("error");
								}
							}else{
								jQuery("#license-key").next().children("i").html("Ogiltigt format");
								jQuery("#license-key").next().addClass("error");
							}
						});
						
						jQuery("#admin-fee").keyup(function(){
							if(!jQuery.isNumeric(jQuery(this).val()) && jQuery(this).val() != ''){
								jQuery(this).next().children("i").html("Ogiltigt format");
								jQuery(this).next().addClass("error");
							}else{
								jQuery(this).next().removeClass("error");
								jQuery(this).next().children("i").html("<br>Här anges fakturaavgiften/administrationsavgiften för Billogram <br>Lämna fältet tomt om avgift redan är konfigurerat i Billogram kontot under: Mitt konto  --&gt; Inställningar --&gt; Fakturainställningar --&gt; Faktura avgift");
							}
						});
						
						jQuery("#due-date").keyup(function(){
							if(!jQuery.isNumeric(jQuery(this).val()) && jQuery(this).val() != ''){
								jQuery(this).next().children("i").html("Ogiltigt format");
								jQuery(this).next().addClass("error");
							}else{
								jQuery(this).next().removeClass("error");
								jQuery(this).next().children("i").html("<br>Om inte inställd, då standard kommer att vara 30 dagar efter fakturadatum (eller beroende på grund dagar)");
							}
						});
						
						jQuery("#due-days").keyup(function(){
							if(!jQuery.isNumeric(jQuery(this).val()) && jQuery(this).val() != ''){
								jQuery(this).next().children("i").html("Ogiltigt format");
								jQuery(this).next().addClass("error");
							}else{
								jQuery(this).next().removeClass("error");
								jQuery(this).next().children("i").html("<br>Om inte inställd, kommer standard vara 30 dagar (eller beroende på förfallodagen)");
							}
						});
						
						jQuery("#billogramOrderinstallningar").submit(function(e){
							if(!jQuery.isNumeric(jQuery("#admin-fee").val()) && jQuery("#admin-fee").val() != ''){
								e.preventDefault();
								jQuery("#admin-fee").next().children("i").html("Ogiltigt format");
								jQuery("#admin-fee").next().addClass("error");
							}else{
								jQuery("#admin-fee").next().removeClass("error");
								jQuery("#admin-fee").next().children("i").html("<br>Här anges fakturaavgiften/administrationsavgiften för Billogram <br>Lämna fältet tomt om avgift redan är konfigurerat i Billogram kontot under: Mitt konto  --&gt; Inställningar --&gt; Fakturainställningar --&gt; Faktura avgift");
							}
						});
					});
				</script>
                <?php
                if($tab == $this->support_key){ ?>
                    <div class="wrap">
                        <?php $this->plugin_options_tabs(); ?>
                        <ul>
                            <li class="logo"><?php echo '<img src="' . plugins_url( 'img/logo_landscape.png', __FILE__ ) . '" > '; ?></li>
                            <li class="col-two"><a href="http://woobill.com/category/faq/"><?php echo '<img src="' . plugins_url( 'img/awp_faq.png', __FILE__ ) . '" > '; ?></a></li>
                            <li class="col-two"><a href="http://woobill.com/"><?php echo '<img src="' . plugins_url( 'img/awp_support.png', __FILE__ ) . '" > '; ?></a></li>
                    </div>
                <?php
                }
                else if($tab == $this->general_settings_key){ ?>
                    <div class="wrap">
                        <?php $this->plugin_options_tabs(); ?>
                        <form method="post" id="billogramGeneralSettings" action="options.php">
                            <?php wp_nonce_field( 'update-options' ); ?>
                            <?php settings_fields( $tab ); ?>
                            <?php do_settings_sections( $tab ); ?>
                            <?php submit_button('Spara ändringar'); ?>
                            <button style="margin: 20px 0px 0px 10px;" type="button" name="testConnection" class="button button-primary testConnection" onclick="test_connection()" />Testa anslutning</button>
                            <span class="test_warning">OBS! Spara ändringar innan du testar anslutning</span>
                            <img style="margin: 10px 0px 0px 10px;" src="<?php echo plugins_url( 'img/ajax-loader.gif', __FILE__ );?>" class="test_load" >
                        </form>
                    </div>
                <?php }
                else if($tab == $this->manual_action_key){ ?>
                    <div class="wrap">
                        <?php $this->plugin_options_tabs(); ?>
                        <ul class="manuella">
                            <li class="full">
                                <button type="button" class="button" title="Manuell Synkning" style="margin:5px" onclick="fetch_contacts()">Manuell synkning kontakter</button>
                                <img src="<?php echo plugins_url( 'img/ajax-loader.gif', __FILE__ );?>" class="customer_load" >
                                <p>Hämtar alla kunder från er Billogram. Detta görs för att undvika dubbletter.</p>
                            </li>
                            <li class="full">
                                <button type="button" class="button" title="Manuell Synkning Orders" style="margin:5px" onclick="sync_orders()">Manuell synkning ordrar</button>
                                <img src="<?php echo plugins_url( 'img/ajax-loader.gif', __FILE__ );?>" class="order_load" >
                                <p>Synkroniserar alla ordrar som misslyckats att synkronisera.&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;</p>
                            </li>
                            <li class="full">
                                <button type="button" class="button" title="Manuell Synkning Products" style="margin:5px" onclick="initial_sync_products()">Manuell synkning produkter</button>
                                <img src="<?php echo plugins_url( 'img/ajax-loader.gif', __FILE__ );?>" class="product_load" >
                                <p>Skicka alla produkter till er Billogram. Om ni har många produkter kan det ta ett tag.</p>
                            </li>
                        </ul>
                    </div>
                <?php }
                else if($tab == $this->start_action_key){
                    $options = get_option('woocommerce_billogram_general_settings');
                    ?>
                    <div class="wrap">
                        <?php $this->plugin_options_tabs(); ?>
                        <ul>
                        	<li>
                        		<?php echo '<img src="' . plugins_url( 'img/banner-772x250.png', __FILE__ ) . '" > '; ?>
                        	</li>
                            <li class="col-twothird">
                                <iframe src="//player.vimeo.com/video/62060237" width="500" height="281" frameborder="0" webkitallowfullscreen mozallowfullscreen allowfullscreen></iframe>
                            </li>
                            <?php if(!isset($options['license-key']) || $options['license-key'] == ''){ ?>
                            <li class="col-onethird">
                            	<div class="mailsupport">
                            		<h2>Installationssupport</h2>
                            	    <form method="post" id="installationSupport">
                            	        <input type="hidden" value="send_support_mail" name="action">
                            	        <table class="form-table">
								
                            	            <tbody>
                            	            <tr valign="top">
                            	                <td>
                            	                    <input type="text" value="" placeholder="Företag" name="company">
                            	                </td>
                            	            </tr>
                            	            <tr valign="top">
                            	                <td>
                            	                    <input type="text" value="" placeholder="Namn" name="name">
                            	                </td>
                            	            </tr>
                            	            <tr valign="top">
                            	                <td>
                            	                    <input type="text" value="" placeholder="Telefon" name="telephone">
                            	                </td>
                            	            </tr>
                            	            <tr valign="top">
                            	                <td>
                            	                    <input type="text" value="" placeholder="Email" name="email">
                            	                </td>
                            	            </tr>
                            	            <tr valign="top">
                            	                <td>
                            	                    <textarea placeholder="Ärende" name="subject"></textarea>
                            	                </td>
                            	            </tr>
                            	            <tr valign="top">
                            	                <td>
                            	                    <button type="button" class="button button-primary" title="send_support_mail" style="margin:5px" onclick="send_support_mail('installationSupport')">Skicka</button>
                            	                </td>
                            	            </tr>
                            	            </tbody>
                            	        </table>
                            	        <!-- p class="submit">
                            	           <button type="button" class="button button-primary" title="send_support_mail" style="margin:5px" onclick="send_support_mail()">Skicka</button> 
                            	        </p -->
                            	    </form>
                            	</div>
                            </li>
                        <?php } else{ ?>
                        	<li class="col-onethird">
                            	<div class="mailsupport">
                            		<h2>Support</h2>
                            	    <form method="post" id="support">
                            	        <input type="hidden" value="send_support_mail" name="action">
                            	        <table class="form-table">
								
                            	            <tbody>
                            	            <tr valign="top">
                            	                <td>
                            	                    <input type="text" value="" placeholder="Företag" name="company">
                            	                </td>
                            	            </tr>
                            	            <tr valign="top">
                            	                <td>
                            	                    <input type="text" value="" placeholder="Namn" name="name">
                            	                </td>
                            	            </tr>
                            	            <tr valign="top">
                            	                <td>
                            	                    <input type="text" value="" placeholder="Telefon" name="telephone">
                            	                </td>
                            	            </tr>
                            	            <tr valign="top">
                            	                <td>
                            	                    <input type="text" value="" placeholder="Email" name="email">
                            	                </td>
                            	            </tr>
                            	            <tr valign="top">
                            	                <td>
                            	                    <textarea placeholder="Ärende" name="subject"></textarea>
                            	                </td>
                            	            </tr>
                            	            <tr valign="top">
                            	                <td>
                                                	<input type="hidden" name="supportForm" value="support" />
                            	                    <button type="button" class="button button-primary" title="send_support_mail" style="margin:5px" onclick="send_support_mail('support')">Skicka</button>
                            	                </td>
                            	            </tr>
                            	            </tbody>
                            	        </table>
                            	        <!-- p class="submit">
                            	           <button type="button" class="button button-primary" title="send_support_mail" style="margin:5px" onclick="send_support_mail()">Skicka</button> 
                            	        </p -->
                            	    </form>
                            	</div>
                            </li>
                        <?php } ?>
                        </ul>
                    </div>
                <?php }
                else{ ?>
                    <div class="wrap">
                        <?php $this->plugin_options_tabs(); ?>
                        <form method="post" id="billogramOrderinstallningar" action="options.php">
                            <?php wp_nonce_field( 'update-options' ); ?>
                            <?php settings_fields( $tab ); ?>
                            <?php do_settings_sections( $tab ); ?>
                            <?php submit_button(); ?>
                        </form>
                    </div>
                <?php }
            }

            /***********************************************************************************************************
             * BILLOGRAM FUNCTIONS
             ***********************************************************************************************************/

			
			/**
             * Fetches contacts from Billogram and writes them local db
             *
             * @access public
             * @return void
             */
            public function fetch_billogram_contacts() {
                include_once("class-billogram2-order-xml.php");
                include_once("class-billogram2-database-interface.php");
                include_once("class-billogram2-api.php");

                $apiInterface = new WCB_API();
                $customers = $apiInterface->get_customers();
                $databaseInterface = new WCB_Database_Interface();

                foreach($customers as $customer){
						$exist = $databaseInterface->get_customer_by_email($customer->contact->email);
						if(empty($exist)){
                        	$databaseInterface->create_existing_customer($customer);
						}
                }
                return true;
            }

            /**
             * Sends contact to Billogram API
             *
             * @access public
             * @param int $orderId
             * @return void
             */
            public function send_contact_to_billogram($orderId) {
                global $wcdn, $woocommerce;
                $options = get_option('woocommerce_billogram_general_settings');
                if($this->is_license_key_valid()=='Active'){
                    include_once("class-billogram2-contact-xml.php");
                    include_once("class-billogram2-database-interface.php");
                    include_once("class-billogram2-api.php");
                    //fetch Order
					$database = new WCB_Database_Interface();
                    $order = new WC_Order($orderId);
					logthis("Payment method: ".$order->payment_method);
					if($order->payment_method != 'billogram-invoice'){
						if($options['activate-allsync'] != "on"){
							$unsyncedOrders = $database->read_unsynced_orders();
							foreach($unsyncedOrders as $unsyncedOrder){
								if($unsyncedOrder->order_id == $orderId){
									return false;
								}
								else{
									$database->create_unsynced_order($orderId);
									return false;
								}
							}
							$database->create_unsynced_order($orderId);
							return false;
						}
					}
                    logthis('send_contact_to_billogram');
                    $customerNumber = $this->get_or_create_customer($order);
                    logthis("CREATE UNSYNCED ORDER");
					if(!$database->is_synced_order($orderId)){
						//Save
						$database->create_unsynced_order($orderId);
						if(!isset($options['activate-orders'])){
							return true;
						}
	
						if($options['activate-orders'] == 'on'){
							$orderNumber = $this->send_order_to_billogram($orderId, $customerNumber);
							if($orderNumber == 0){
								$database->set_as_synced($orderId);
								return true;
							}
						}
					}
                    
                }
            }

            /**
             * Sends order to Billogram API
             *
             * @access public
             * @param int $orderId
             * @param $customerNumber
             * @return void
             */
            public function send_order_to_billogram($orderId, $customerNumber) {
                global $wcdn;
                $options = get_option('woocommerce_billogram_general_settings');
                if(!isset($options['activate-orders'])){
                    return;
                }
                if($options['activate-orders'] == 'on'){
                    include_once("class-billogram2-order-xml.php");
                    include_once("class-billogram2-database-interface.php");
                    include_once("class-billogram2-api.php");

                    //fetch Order
                    $order = new WC_Order($orderId);
                    logthis("ORDER");
                    logthis($order);
                    //Init API
                    $apiInterface = new WCB_API();

                    //create Order XML
                    $orderDoc = new WCB_Order_XML_Document();
                    $orderXml = $orderDoc->create($order, $customerNumber);
                    
                    //send Order XML
                    $orderResponse = $apiInterface->create_order_request($orderXml);

					logthis("OrderResponse: ".$orderResponse);
                    //Error handling
                    if(array_key_exists('Error', $orderResponse)){
                        logthis(print_r($orderResponse, true));
                        // if order exists
                        if($orderResponse['Code'] == $this->BILLOGRAM_ERROR_CODE_ORDER_EXISTS){
                            logthis("ORDER EXISTS");
                            $apiInterface->update_order_request($orderXml, $orderId);
                        }
                        // if products dont exist
                        elseif($orderResponse['Code'] == $this->BILLOGRAM_ERROR_CODE_PRODUCT_NOT_EXIST){
                            logthis("PRODUCT DOES NOT EXIST");

                            foreach($order->get_items() as $item){
                                //if variable product there might be a different SKU
                                if(empty($item['variation_id'])){
                                    $productId = $item['product_id'];
                                }
                                else{
                                    $productId = $item['variation_id'];
                                }
                                $this->send_product_to_billogram($productId);
                            }
                            $orderResponse = $apiInterface->create_order_request($orderXml);
                        }
                        else{
                            logthis("CREATE UNSYNCED ORDER");
                            //Init DB 2000861
                            $database = new WCB_Database_Interface();
                            //Save
                            $database->create_unsynced_order($orderId);
                            return 1;
                        }
                    }
                    /*if(!isset($options['activate-invoices'])){
                        return;
                    }*/
                    if(($options['activate-invoices'] == 'Skapa faktura och skicka som epost' || $options['activate-invoices'] == 'Skapa faktura och skicka som brev') && $order->payment_method == 'billogram-invoice'){
                        //Create invoice
                        $invoiceResponse = $apiInterface->create_order_invoice_request($orderResponse);
                    }
                }
                return 0;
            }

            /**
             * Sends ALL unsynced orders to Billogram API
             *
             * @access public
             * @return void
             */
            public function sync_orders_to_billogram() {
                include_once("class-billogram2-order-xml.php");
                include_once("class-billogram2-contact-xml.php");
                include_once("class-billogram2-database-interface.php");
                include_once("class-billogram2-api.php");
                
                $options = get_option('woocommerce_billogram_general_settings');
                
                $apiInterface = new WCB_API();
                $databaseInterface = new WCB_Database_Interface();
                $unsyncedOrders = $databaseInterface->read_unsynced_orders();

                foreach($unsyncedOrders as $order){

                    $orderId = $order->order_id;

                    $order = new WC_Order($orderId);
					if($order->payment_method != 'billogram-invoice'){
						if($options['activate-allsync'] != "on"){
							return true;
						}
					}
                    
                    $customerNumber = $this->get_or_create_customer($order);
                       
                    //create Order XML
                    $orderDoc = new WCB_Order_XML_Document();
                    $orderXml = $orderDoc->create($order, $customerNumber);

                    //send Order XML
                    $orderResponse = $apiInterface->create_order_request($orderXml);
                    
                    if($orderResponse->id){
                        $databaseInterface->set_as_synced($orderId);   
                    }
                    else{
						return false;
                    }
                    if(!isset($options['activate-invoices'])){
                        continue;
                    }
                    if(($options['activate-invoices'] == 'Skapa faktura och skicka som epost' || $options['activate-invoices'] == 'Skapa faktura och skicka som brev') && $order->payment_method == 'billogram-invoice'){
                        //Create invoice
                        $invoiceResponse = $apiInterface->create_order_invoice_request($orderResponse);
                        
                    }
                }
                return true;
            }


            /**
             * Syncs ALL products to Billogram API
             *
             * @access public
             * @return bool
             */
            public function initial_products_sync() {
                $args = array(
                    'post_type' => 'product',
                    'orderby' => 'id',
                    'posts_per_page' => -1,
                );
                $the_query = new WP_Query( $args );
                foreach($the_query->get_posts() as $product){
                    $this->send_product_to_billogram($product->ID);
                }
                wp_reset_postdata();
                return true;
            }

            /**
             * Sends product to Billogram API
             *
             * @access public
             * @param $productId
             * @internal param int $orderId
             * @return void
             */
            public function send_product_to_billogram($productId) {
                global $wcdn;
                $options = get_option('woocommerce_billogram_general_settings');
                if(!isset($options['activate-prices'])){
                    return;
                }
                
                if($options['activate-prices'] == 'on' && $this->is_license_key_valid()=='Active'){

                    $post = get_post($productId);

                    if($post->post_type == 'product' && $post->post_status == 'publish'){
                        include_once("class-billogram2-product-xml.php");
                        include_once("class-billogram2-database-interface.php");
                        include_once("class-billogram2-api.php");
                        //fetch Product

                        $pf = new WC_Product_Factory();
                        $product = $pf->get_product($productId);

                        //fetch meta
                        //Init API
                        $apiInterface = new WCB_API();
                        //create Product XML
                        $productDoc = new WCB_Product_XML_Document();
                        $databaseInterface = new WCB_Database_Interface();
                        $cur_sku = $product->get_sku();
                        //$cur_sku_ajax = get_post_meta( $productId, '_sku', true );
                        $sku_array = $databaseInterface->get_product_sku($productId);
                        $sku = $sku_array[0]->product_sku;
                        
                        $isSynced = get_post_meta( $productId, '_is_synced_to_billogram' );

                        if (!empty($isSynced) && !empty($sku_array)) {

                        	

                            logthis("UPDATE PRODUCT");
                            //echo $cur_sku."|".$sku."|".$cur_sku_ajax;
                            //exit;
                            if($cur_sku!=$sku)
                            {
                                $productXml = $productDoc->create($product);
                            }
                            else
                            {
                                $productXml = $productDoc->update($product);
                            }

                            $updateResponse = $apiInterface->update_product_request($productXml, $sku);
                           /* $productPriceXml = $productDoc->update_price($product);
                            $apiInterface->update_product_price_request($productPriceXml, $sku);*/

                            if($updateResponse->item_no){
                                $billogramId = $updateResponse->item_no;

                                //set sku;
                                $databaseInterface->update_product_sku($productId,$billogramId);
                                update_post_meta($productId, '_is_synced_to_billogram', 1);
                               
                            }
                            return $updateResponse;
                        }
                        else{


                            logthis("CREATE PRODUCT");
                            $productXml = $productDoc->create($product);

                            $productResponseCode = $apiInterface->create_product_request($productXml);
                            $billogramId = $productResponseCode->item_no;
                            //set sku;
                            if($productResponseCode->item_no){
                            	//echo $billogramId = $productResponseCode->item_no;
                                $databaseInterface->set_product_sku($productId,$billogramId);
                                update_post_meta($productId, '_sku', $billogramId);
                                update_post_meta($productId, '_is_synced_to_billogram', 1);
                            
                            }
                           	return $productResponseCode;
                        }

                        
                    }
                }
            }

            /***********************************************************************************************************
             * WP-PLUGS API FUNCTIONS
             ***********************************************************************************************************/

            /**
             * Checks if API-key is valid
             *
             * @access public
             * @return void
             */
            public function is_api_key_valid() {
                include_once("class-billogram2-api.php");
                $apiInterface = new WCB_API();
                return $apiInterface->create_api_validation_request();
            }

            /**
             * Checks if license-key is valid
             *
             * @access public
             * @return void
             */
            public function is_license_key_valid() {
                include_once("class-billogram2-api.php");
                $apiInterface = new WCB_API();
                $result = $apiInterface->create_license_validation_request();
                switch ($result['status']) {
		            case "Active":
		                // get new local key and save it somewhere
		                $localkeydata = $result['localkey'];
		                update_option( 'local_key_billogram_plugin', $localkeydata );
		                return $result['status'];
		                break;
		            case "Invalid":
		                logthis("License key is Invalid");
		            	return $result['status'];
		                break;
		            case "Expired":
		                logthis("License key is Expired");
                        return $result['status'];
		                break;
		            case "Suspended":
		                logthis("License key is Suspended");
		                return $result['status'];
		                break;
		            default:
                        logthis("Invalid Response");
		                break;
	        	}
            }

            /**
             * Get user data of current order completed
             *
             * @access public
             * @return void
            */
            public function get_orderd_user_data($orderId , $key) {
                
                return $value = get_post_meta( $orderId , '_'.$key, true );
            }
            /**
             * Fetches customer from DB or creates it at Billogram
             *
             * @access public
             * @param $order
             * @return void
             */
            private function get_or_create_customer($order){
                $databaseInterface = new WCB_Database_Interface();
                //$customeremail = $this->get_orderd_user_data($order->id,'billing_email');
                $customer = $databaseInterface->get_customer_by_email($order->billing_email);
                
                //Init API
                $apiInterface = new WCB_API();
                 //create Contact XML
                $contactDoc = new WCB_Contact_XML_Document();
                $contactXml = $contactDoc->create($order);
				//logthis("billing emal:".$order->billing_email);
				//logthis("customer:".print_r($customer, true));
                if(empty($customer)){
                    
                    $customerId = $databaseInterface->create_customer($order->billing_email);
                    //send Contact XML
                    $contactResponseCode = $apiInterface->create_customer_request($contactXml);
					logthis("contactResponseCode:".$contactResponseCode);
                    $customerNumber = $contactResponseCode->customer_no;
                    $databaseInterface->update_customer($customerId, $customerNumber);

                }
                else{
                    
                    $customerNumber = $customer[0]->customer_number;
                    $apiInterface->update_customer_request($contactXml, $customerNumber);
                    
                }
                return $customerNumber;
            }
        }
        $GLOBALS['wc_consuasor'] = new WC_Billogram_Extended();
    }
}