<?php

/**
 * @category   Webinterpret
 * @package    Webinterpret_Connector
 * @author     Webinterpret Team <info@webinterpret.com>
 * @license    http://opensource.org/licenses/osl-3.0.php
 */
class Webinterpret_Connector_CartController extends Mage_Core_Controller_Front_Action
{
    public function indexAction()
    {
        $quote = Mage::getSingleton('checkout/cart')->getQuote();
        $cart = array();
        foreach ($quote->getAllVisibleItems() as $item) {
            $item_option = $item->getOptionByCode('simple_product');
            $cart[] = array(
                'external_id' => $item->getProduct()->getId(),
                'variant_id' => $item_option ? $item_option->getProduct()->getId() : null,
                'quantity' => $item->getQty(),
                'custom_options' => $item->getBuyRequest()->getOptions(),
            );
        }
        header('Content-Type: application/json');
        echo json_encode($cart);
    }
}
