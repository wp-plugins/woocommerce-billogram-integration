<?php
include_once("class-billogram2-xml.php");
class WCB_Product_XML_Document extends WCB_XML_Document{
    /**
     *
     */
    function __construct() {
        parent::__construct();
    }
    /**
     * Creates a XML representation of a Product
     *
     * @access public
     * @param mixed $product
     * @return mixed
     */
    public function create($product){

        //$root = 'Article';
        $productNode = array();
        $sku = $product->get_sku();
        $tax = $product->get_price_including_tax() - $product->get_price_excluding_tax();
        $taxper = round($tax*100/$product->get_price_excluding_tax());
        if($sku){
            $productNode['item_no'] = $sku;
        }
        $productNode['title'] = $product->get_title();
        $productNode['description'] = $product->get_title();
        $productNode['price'] = $product->get_price_excluding_tax() ? round($product->get_price_excluding_tax(), 2) : $product->get_regular_price();
        $productNode['vat'] = $taxper;
        //$productNode['QuantityInStock'] = $product->get_stock_quantity();
        $productNode['unit'] = 'unit';
        //$productNode['ArticleNumber'] = $product->get_sku();
        return $productNode;
        //return $this->generate($root, $productNode);
    }
    /**
     * Updates a XML representation of a Product
     *
     * @access public
     * @param mixed $product
     * @return mixed
     */
    public function update($product){

        //$root = 'Article';
        $productNode = array();
        
        $tax = $product->get_price_including_tax() - $product->get_price_excluding_tax();
        $taxper = round($tax*100/$product->get_price_excluding_tax());
        $productNode['title'] = $product->get_title();
        $productNode['description'] = $product->get_title();
        $productNode['price'] = $product->get_price_excluding_tax() ? round($product->get_price_excluding_tax(), 2) : $product->get_regular_price();
        $productNode['vat'] = $taxper;
        //$productNode['ArticleNumber'] = $product->get_sku();
        //$productNode['QuantityInStock'] = $product->get_stock_quantity();
        $productNode['unit'] = 'unit';
        return $productNode;
        //return $this->generate($root, $productNode);
    }

    /**
     * Creates a XML representation of a Product
     *
     * @access public
     * @param mixed $product
     * @return mixed
     */
    public function create_price($product){

        $root = 'Price';
        $options = get_option('woocommerce_billogram_general_settings');
        $price = array();

        if(!isset($meta['pricelist_id'])){
            $price['PriceList'] = 'A';
        }
        else{
            $price['PriceList'] = $meta['pricelist_id'];
        }
        if($options['activate-vat'] == 'on'){
            $price['Price'] = $product->get_price_excluding_tax() ? $product->get_price_excluding_tax() : $product->get_regular_price();
            logthis('YES');
        }
        else{
            $price['Price'] = $product->get_price_excluding_tax() ? $product->get_price_excluding_tax() : $product->get_regular_price();
            logthis('NO');
        }
        $price['ArticleNumber'] = $product->get_sku();
        $price['FromQuantity'] = 1;

        return $this->generate($root, $price);
    }

    /**
     * Creates a XML representation of a Product
     *
     * @access public
     * @param mixed $product
     * @return mixed
     */
    public function update_price($product){

        $root = 'Price';
        $price = array();

        $options = get_option('woocommerce_billogram_general_settings');
        if($options['activate-vat'] == 'on'){
            $price['Price'] = $product->get_price_excluding_tax() ? $product->get_price_excluding_tax() : $product->get_regular_price();
            logthis('YES');
        }
        else{
            $price['Price'] = $product->get_price_excluding_tax() ? $product->get_price_excluding_tax() : $product->get_regular_price();
            logthis('NO');
        }

        return $this->generate($root, $price);
    }
}