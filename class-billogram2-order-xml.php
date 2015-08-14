<?php
include_once("class-billogram2-xml.php");
class WCB_Order_XML_Document extends WCB_XML_Document{
    /**
     *
     */
    function __construct() {
        parent::__construct();
    }
    /**
     * Creates a n XML representation of an Order
     *
     * @access public
     * @param mixed $arr
     * @param $customerNumber
     * @return mixed
     */
    public function create($arr, $customerNumber){

        $order_options = get_option('woocommerce_billogram_order_settings');
        $options = get_option('woocommerce_billogram_general_settings');
		
		//Add for woosubscription support
		if (class_exists("WC_Subscriptions_Order") && WC_Subscriptions_Order::order_contains_subscription( $arr ))
			$subscription = WC_Subscriptions_Order::order_contains_subscription( $arr );
		
        //$root = 'Order';
        $signKey = uniqid();
        $siteurl = admin_url('admin-ajax.php').'?action=billogram_callback';
		//$siteurl = plugins_url( '/woocommerce-billogram-integration/billogram-callback.php' );
		//logthis("siteurl: ". $siteurl);
       
        $order['invoice_date'] = substr($arr->order_date, 0, 10);
        //$order['due_date'] = date("Y-m-d", strtotime($arr->order_date ." +15 day") );
		if($order_options['due-days'] != ''){
			$order['due_days'] = $order_options['due-days'];
		}
        $order['currency'] = 'SEK';
        $order['customer']['name'] = $arr->billing_first_name . " " . $arr->billing_last_name;
        $order['customer']['customer_no'] = $customerNumber;
        $order['customer']['phone'] = $arr->billing_phone;
        $order['customer']['address']['street_address'] = $arr->billing_address_1;
        $order['customer']['address']['city'] = $arr->billing_city;
        /*$order['customer']['address']['country'] = 'SE';*/
        /*$order['customer']['address']['country'] = $this->countries[$arr->billing_country];*/
        $order['customer']['address']['zipcode'] = $arr->billing_postcode;
        $order['customer']['email'] = $arr->billing_email;
        //$order['currency'] = $arr->get_order_currency();
		if($order_options['admin-fee'] != ''){
			$order['invoice_fee'] = $order_options['admin-fee'];
		}
        
        $invoicerows = array();
        //loop all items
        $index = 0;
        foreach($arr->get_items() as $item){
            /*$key = "items" . $index;*/

            //fetch product
            $pf = new WC_Product_Factory();
            
            
            //if variable product there might be a different SKU
            if(empty($item['variation_id'])){
                 $productId = $item['product_id'];
                 //$description = $item['name'];
            }
            else{
                 $productId = $item['variation_id'];
                 $_product  = apply_filters( 'woocommerce_order_item_product', $arr->get_product_from_item( $item ), $item );
                 $item_meta = new WC_Order_Item_Meta( $item['item_meta'], $_product );
                 //$description = $item['name'].' - '.$item_meta->display($flat = true, $return = true);
            }
			
			$productDesc = strip_tags(get_post($productId)->post_content);
			$description = (strlen($productDesc) > 200) ? substr($productDesc,0,196).'...' : $productDesc;
			
			$invoicerow = array();
			$invoicerow['title'] = (strlen($item['name']) > 40) ? substr($item['name'],0,36).'...' : $item['name'];
			$invoicerow['description'] = $description;
			$product = $pf->get_product($productId);
			if($product->is_on_sale()){
				$discount = $product->get_regular_price() - $product->get_sale_price();
				$invoicerow['price'] = $product->get_price_excluding_tax() ? round(($product->get_price_excluding_tax()+$discount), 2) : $product->get_regular_price();
			}else{
				$invoicerow['price'] = $product->get_price_excluding_tax() ? round($product->get_price_excluding_tax(), 2) : $product->get_regular_price();
			}
			//discount
			if($product->is_on_sale()){
				$invoicerow['discount'] = $item['qty']*($product->get_regular_price() - $product->get_sale_price());
				/*$invoicerow['DiscountType'] = 'AMOUNT';*/
			}
			$tax = $product->get_price_including_tax() - $product->get_price_excluding_tax();
			$taxper = round($tax*100/$product->get_price_excluding_tax());
			$invoicerow['vat'] = $taxper;
			$invoicerow['count'] = $item['qty'];
			if($subscription){
				if(WC_Subscriptions_Product::get_length( $productId ) != 0 ){
					//$total_initial_payment_with_tax = WC_Subscriptions_Order::get_total_initial_payment( $arr );
					//$total_initial_payment = $total_initial_payment_with_tax/(1+($taxper	/100));
					//$invoicerow['price'] = round($total_initial_payment, 2);
					if(WC_Subscriptions_Order::get_subscription_trial_length( $arr, $productId ) == 0)
						$price_per_period = WC_Subscriptions_Product::get_price( $productId );
					else
						$price_per_period = 0;
					$line_item_count = $arr->get_item_count('line_item');
					$sign_up_fee_without_tax = get_post_meta($productId, '_subscription_sign_up_fee', true);
					$invoicerow['price'] = round($price_per_period + $sign_up_fee_without_tax, 2);
				}
			}			
			
			/*$index += 1;*/
            $invoicerows[] = $invoicerow;
        }
		if ($arr->get_total_shipping() > 0 ) {
			$invoicerowShipping = array();
			$invoicerowShipping['title'] = 'Shipping and Handling: '.$arr->get_shipping_method() ;
			$invoicerowShipping['price'] = $arr->get_total_shipping();
			$tax = $arr->get_shipping_tax();
            $taxper = round($tax*100/$arr->get_total_shipping());
			//echo $taxper; die();
			$invoicerowShipping['vat'] = $taxper;
			$invoicerowShipping['count'] = 1;
			//$invoicerowShipping['unit'] = 'unit';
			$invoicerows[] = $invoicerowShipping;
		}
		if (count( WC()->cart->applied_coupons ) > 0 ) {
			$invoicerowDiscount = array();
			foreach (WC()->cart->applied_coupons as $code ) {
				$invoicerowDiscount['title'] = 'Coupon: '.$code;
				$coupounAmount = WC()->cart->coupon_discount_amounts[ $code ];
				$coupounTaxAmount = WC()->cart->coupon_discount_tax_amounts[ $code ];
				$invoicerowDiscount['price'] = -$coupounAmount;
				$tax = $coupounTaxAmount;
            	$taxper = round($tax*100/$coupounAmount);
            	$invoicerowDiscount['vat'] = $taxper;
				$invoicerowDiscount['count'] = 1;
				//$invoicerow['unit'] = 'unit';
				$invoicerows[] = $invoicerowDiscount;
			}
		}
		
        $order['items'] = $invoicerows;
        $order['callbacks']['sign_key'] = $signKey;
        $order['callbacks']['url'] = $siteurl;
        $order['info']['order_no'] = $arr->id;
        $order['info']['order_date'] = substr($arr->order_date, 0, 10);
        $order['info']['delivery_date'] = NULL;
        
		//logthis($order);
		
        return $order;
    }
	
	
	
	/**
     * Creates a n XML representation of an Order
     *
     * @access public
     * @param mixed $arr
     * @param $customerNumber
     * @return mixed
     */
    public function create_scheduled_subscription($amount_to_charge, $arr, $product_id, $customerNumber){

        $order_options = get_option('woocommerce_billogram_order_settings');
        $options = get_option('woocommerce_billogram_general_settings');
		
		//Add for woosubscription support
		if (class_exists("WC_Subscriptions_Order") && WC_Subscriptions_Order::order_contains_subscription( $arr ))
			$subscription = WC_Subscriptions_Order::order_contains_subscription( $arr );
		else
			return false;
		
        //$root = 'Order';
        $signKey = uniqid();
        $siteurl = admin_url('admin-ajax.php').'?action=billogram_callback_subscription';
		//$siteurl = plugins_url( '/woocommerce-billogram-integration/billogram-callback.php' );
		//logthis("siteurl: ". $siteurl);
       
	   
	   	$order['invoice_date'] = date('Y-m-d', strtotime(WC_Subscriptions_Order::get_next_payment_date( $arr, $product_id, $from_date = '' )));
	   
        //$order['invoice_date'] = substr($arr->order_date, 0, 10);
        //$order['due_date'] = date("Y-m-d", strtotime($arr->order_date ." +15 day") );
		if($order_options['due-days'] != ''){
			$order['due_days'] = $order_options['due-days'];
		}
        $order['currency'] = 'SEK';
        $order['customer']['name'] = $arr->billing_first_name . " " . $arr->billing_last_name;
        $order['customer']['customer_no'] = $customerNumber;
        $order['customer']['phone'] = $arr->billing_phone;
        $order['customer']['address']['street_address'] = $arr->billing_address_1;
        $order['customer']['address']['city'] = $arr->billing_city;
        /*$order['customer']['address']['country'] = 'SE';*/
        /*$order['customer']['address']['country'] = $this->countries[$arr->billing_country];*/
        $order['customer']['address']['zipcode'] = $arr->billing_postcode;
        $order['customer']['email'] = $arr->billing_email;
        //$order['currency'] = $arr->get_order_currency();
		if($order_options['admin-fee'] != ''){
			$order['invoice_fee'] = $order_options['admin-fee'];
		}
        
        $invoicerows = array();
        //loop all items
        $index = 0;
        foreach($arr->get_items() as $item){
            /*$key = "items" . $index;*/

            //fetch product
            $pf = new WC_Product_Factory();
            
            
            //if variable product there might be a different SKU
            if(empty($item['variation_id'])){
                 $productId = $item['product_id'];
                 //$description = $item['name'];
            }
            else{
                 $productId = $item['variation_id'];
                 $_product  = apply_filters( 'woocommerce_order_item_product', $arr->get_product_from_item( $item ), $item );
                 $item_meta = new WC_Order_Item_Meta( $item['item_meta'], $_product );
                 //$description = $item['name'].' - '.$item_meta->display($flat = true, $return = true);
            }
			
			if(WC_Subscriptions_Product::get_length( $productId ) != 0 ){
				$invoicerow = array();
				$product = $pf->get_product($productId);

				$productDesc = strip_tags(get_post($productId)->post_content);
				$description = (strlen($productDesc) > 200) ? substr($productDesc,0,196).'...' : $productDesc;
				
				$invoicerow['title'] = (strlen($item['name']) > 40) ? substr($item['name'],0,36).'...' : $item['name'];
				$invoicerow['description'] = $description;
				
				if($product->is_on_sale()){
					$discount = $product->get_regular_price() - $product->get_sale_price();
					$invoicerow['price'] = $product->get_price_excluding_tax() ? round(($product->get_price_excluding_tax()+$discount), 2) : $product->get_regular_price();
				}else{
					$invoicerow['price'] = $product->get_price_excluding_tax() ? round($product->get_price_excluding_tax(), 2) : $product->get_regular_price();
				}
				
				//$invoicerow['price'] = $amount_to_charge;
				
				//$tax_rates  = WC_Tax::get_base_tax_rates( $product->tax_class );
				//$tax      	= WC_Tax::calc_tax( $product->get_price_excluding_tax() * $item['qty'], $tax_rates, true );
				$tax = $amount_to_charge - $product->get_price_excluding_tax();
				$taxper = round($tax*100/$product->get_price_excluding_tax());
				$invoicerow['vat'] = $taxper;
				$invoicerow['count'] = $item['qty'];
				
				
				//discount
				if($product->is_on_sale()){
					$invoicerow['discount'] = $item['qty']*($product->get_regular_price() - $product->get_sale_price());
					/*$invoicerow['DiscountType'] = 'AMOUNT';*/
				}
						
				
				/*$index += 1;*/
				$invoicerows[] = $invoicerow;
			}
        }
		if ($arr->get_total_shipping() > 0 ) {
			$invoicerowShipping = array();
			$invoicerowShipping['title'] = 'Shipping and Handling: '.$arr->get_shipping_method() ;
			$invoicerowShipping['price'] = $arr->get_total_shipping();
			$tax = $arr->get_shipping_tax();
            $taxper = round($tax*100/$arr->get_total_shipping());
			//echo $taxper; die();
			$invoicerowShipping['vat'] = $taxper;
			$invoicerowShipping['count'] = 1;
			//$invoicerowShipping['unit'] = 'unit';
			$invoicerows[] = $invoicerowShipping;
		}
		/*if (count( WC()->cart->applied_coupons ) > 0 ) {
			$invoicerowDiscount = array();
			foreach (WC()->cart->applied_coupons as $code ) {
				$invoicerowDiscount['title'] = 'Coupon: '.$code;
				$coupounAmount = WC()->cart->coupon_discount_amounts[ $code ];
				$coupounTaxAmount = WC()->cart->coupon_discount_tax_amounts[ $code ];
				$invoicerowDiscount['price'] = -$coupounAmount;
				$tax = $coupounTaxAmount;
            	$taxper = round($tax*100/$coupounAmount);
            	$invoicerowDiscount['vat'] = $taxper;
				$invoicerowDiscount['count'] = 1;
				//$invoicerow['unit'] = 'unit';
				$invoicerows[] = $invoicerowDiscount;
			}
		}*/
		
        $order['items'] = $invoicerows;
        $order['callbacks']['sign_key'] = $signKey;
        $order['callbacks']['url'] = $siteurl;
        $order['info']['order_no'] = $arr->id;
        $order['info']['order_date'] = substr($arr->order_date, 0, 10);
        $order['info']['delivery_date'] = NULL;
        
		//logthis($order);
		
        return $order;
    }
}