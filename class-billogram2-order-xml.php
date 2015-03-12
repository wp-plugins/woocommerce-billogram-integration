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

        $order_options = $options = get_option('woocommerce_billogram_order_settings');
        $options = get_option('woocommerce_billogram_general_settings');

        //$root = 'Order';
        $signKey = uniqid();
        $siteurl = get_site_url();
       
        $order['invoice_date'] = substr($arr->order_date, 0, 10);
        $order['due_date'] = date("Y-m-d", strtotime($arr->order_date ." +15 day") );
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
                 $description = $item['name'];
            }
            else{
                 $productId = $item['variation_id'];
                 $_product  = apply_filters( 'woocommerce_order_item_product', $arr->get_product_from_item( $item ), $item );
                 $item_meta = new WC_Order_Item_Meta( $item['item_meta'], $_product );
                 $description = $item['name'].' - '.$item_meta->display($flat = true, $return = true);
            }

            $product = $pf->get_product($productId);
            /*print_r($product);
            print_r($item);
            exit;*/
            $invoicerow = array();
            $invoicerow['title'] = $item['name'];
            
            $invoicerow['price'] = $product->get_regular_price();
            
            //$invoicerow['unit'] = 'st';
            //$invoicerow['item_no'] = $product->get_sku();
            $invoicerow['description'] = $description;
            $tax = $product->get_price_including_tax() - $product->get_price_excluding_tax();
            $taxper = $tax*100/$product->get_price_excluding_tax();
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
        $order['items'] = $invoicerows;
        $order['callbacks']['sign_key'] = $signKey;
        $order['callbacks']['url'] = $siteurl;
        $order['info']['order_no'] = $arr->id;
        $order['info']['order_date'] = substr($arr->order_date, 0, 10);
        $order['info']['delivery_date'] = NULL;
        
        return $order;
    }
}