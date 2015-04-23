<?php
include_once("class-billogram2-xml.php");

class WCB_Contact_XML_Document extends WCB_XML_Document{

    /**
     *
     */
    function __construct() {
        parent::__construct();
    }

    /**
     * Creates an XML representation of an order
     *
     * @access public
     * @param mixed $arr
     * @return mixed
     */
    public function create($arr){
        $contact = array();
        $contact['name'] = $arr->billing_first_name . " " . $arr->billing_last_name;
        $contact['contact']['name'] = $arr->billing_first_name . " " . $arr->billing_last_name;
        $contact['contact']['email'] = $arr->billing_email;
        $contact['contact']['phone'] = $arr->billing_phone;
        $contact['address']['street_address'] = $arr->billing_address_1;
        $contact['address']['zipcode'] = $arr->billing_postcode;
        $contact['address']['city'] = $arr->billing_city;
        $contact['delivery_address']['street_address'] = $arr->shipping_address_1;
        $contact['delivery_address']['zipcode'] = $arr->shipping_postcode;
        $contact['delivery_address']['city'] = $arr->shipping_city;
        $contact['company_type'] = 'individual';
        //$contact['PriceList'] = 'A';
        //$root = 'Customer';
        //return $this->generate($root, $contact);
        return $contact;
    }
    
    public function update($arr,$custome_no){
        $contact = array();
        $contact['customer_no'] = $custome_no;
        $contact['name'] = $arr->billing_first_name . " " . $arr->billing_last_name;
        $contact['contact']['name'] = $arr->billing_first_name . " " . $arr->billing_last_name;
        $contact['contact']['email'] = $arr->billing_email;
        $contact['contact']['phone'] = $arr->billing_phone;
        $contact['address']['street_address'] = $arr->billing_address_1;
        $contact['address']['zipcode'] = $arr->billing_postcode;
        $contact['address']['city'] = $arr->billing_city;
        $contact['delivery_address']['street_address'] = $arr->shipping_address_1;
        $contact['delivery_address']['zipcode'] = $arr->shipping_postcode;
        $contact['delivery_address']['city'] = $arr->shipping_city;
        $contact['company_type'] = 'individual';
        //$contact['PriceList'] = 'A';
        //$root = 'Customer';
        //return $this->generate($root, $contact);
        return $contact;
    }
}