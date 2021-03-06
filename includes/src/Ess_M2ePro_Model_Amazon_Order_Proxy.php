<?php

/*
 * @copyright  Copyright (c) 2013 by  ESS-UA.
 */

class Ess_M2ePro_Model_Amazon_Order_Proxy extends Ess_M2ePro_Model_Order_Proxy
{
    /** @var $order Ess_M2ePro_Model_Amazon_Order */
    protected $order = NULL;

    // ########################################

    /**
     * @param Ess_M2ePro_Model_Order_Item_Proxy[] $items
     * @return Ess_M2ePro_Model_Order_Item_Proxy[]
     * @throws Exception
     */
    protected function mergeItems(array $items)
    {
        foreach ($items as $key => $item) {
            if ($item->getPrice() <= 0) {
                unset($items[$key]);
            }
        }

        if (count($items) == 0) {
            throw new Exception('Every item in order has zero price.');
        }

        return parent::mergeItems($items);
    }

    // ########################################

    public function getBillingAddressData()
    {
        if ($this->order->getAmazonAccount()->isMagentoOrdersBillingAddressSameAsShipping()) {
            return parent::getBillingAddressData();
        }

        if ($this->order->getShippingAddress()->hasSameBuyerAndRecipient()) {
            return parent::getBillingAddressData();
        }

        $customerNameParts = $this->getNameParts($this->order->getBuyerName());

        return array(
            'firstname'  => $customerNameParts['firstname'],
            'lastname'   => $customerNameParts['lastname'],
            'country_id' => '',
            'region'     => '',
            'region_id'  => '',
            'city'       => 'The Amazon does not supply the complete billing buyer information.',
            'postcode'   => '',
            'street'     => array(),
            'company'    => ''
        );
    }

    public function shouldIgnoreBillingAddressValidation()
    {
        if ($this->order->getAmazonAccount()->isMagentoOrdersBillingAddressSameAsShipping()) {
            return false;
        }

        if ($this->order->getShippingAddress()->hasSameBuyerAndRecipient()) {
            return false;
        }

        return true;
    }

    // ########################################

    public function getCheckoutMethod()
    {
        if ($this->order->getAmazonAccount()->isMagentoOrdersCustomerPredefined() ||
            $this->order->getAmazonAccount()->isMagentoOrdersCustomerNew()) {
            return self::CHECKOUT_REGISTER;
        }

        return self::CHECKOUT_GUEST;
    }

    public function getBuyerEmail()
    {
        return $this->order->getBuyerEmail();
    }

    public function getCustomer()
    {
        $customer = Mage::getModel('customer/customer');

        if ($this->order->getAmazonAccount()->isMagentoOrdersCustomerPredefined()) {
            $customer->load($this->order->getAmazonAccount()->getMagentoOrdersCustomerId());

            if (is_null($customer->getId())) {
                throw new Exception('Customer with ID specified in Amazon account settings does not exist.');
            }
        }

        if ($this->order->getAmazonAccount()->isMagentoOrdersCustomerNew()) {
            $customerInfo = $this->getAddressData();

            $customer->setWebsiteId($this->order->getAmazonAccount()->getMagentoOrdersCustomerNewWebsiteId());
            $customer->loadByEmail($customerInfo['email']);

            if (!is_null($customer->getId())) {
                return $customer;
            }

            $customerInfo['website_id'] = $this->order->getAmazonAccount()->getMagentoOrdersCustomerNewWebsiteId();
            $customerInfo['group_id'] = $this->order->getAmazonAccount()->getMagentoOrdersCustomerNewGroupId();
//            $customerInfo['is_subscribed'] = $this->order->getAmazonAccount()->isMagentoOrdersCustomerNewSubscribed();

            /** @var $customerBuilder Ess_M2ePro_Model_Magento_Customer */
            $customerBuilder = Mage::getModel('M2ePro/Magento_Customer')->setData($customerInfo);
            $customerBuilder->buildCustomer();

            $customer = $customerBuilder->getCustomer();

//            if ($this->order->getAmazonAccount()->isMagentoOrdersCustomerNewNotifyWhenCreated()) {
//                $customer->sendNewAccountEmail();
//            }
        }

        return $customer;
    }

    public function getCurrency()
    {
        return $this->order->getCurrency();
    }

    public function getPaymentData()
    {
        $paymentData = array(
            'method'            => Mage::getSingleton('M2ePro/Magento_Payment')->getCode(),
            'component_mode'    => Ess_M2ePro_Helper_Component_Amazon::NICK,
            'payment_method'    => '',
            'channel_order_id'  => $this->order->getAmazonOrderId(),
            'channel_final_fee' => 0,
            'transactions'      => array()
        );

        return $paymentData;
    }

    public function getShippingData()
    {
        return array(
            'shipping_method' => $this->order->getShippingService(),
            'shipping_price'  => $this->getBaseShippingPrice(),
            'carrier_title'   => Mage::helper('M2ePro')->__('Amazon Shipping')
        );
    }

    protected function getShippingPrice()
    {
        $price = $this->order->getShippingPrice();

        if ($this->isTaxModeNone() && !$this->isShippingPriceIncludesTax()) {
            $taxAmount = Mage::getSingleton('tax/calculation')
                ->calcTaxAmount($price, $this->getTaxRate(), false, false);

            $price += $taxAmount;
        }

        return $price;
    }

    // ########################################

    public function getTaxRate()
    {
        $paidAmount = $this->order->getPaidAmount();
        $taxAmount  = $this->order->getTaxAmount();

        if ($paidAmount == 0 || $taxAmount == 0) {
            return 0;
        }

        if ($paidAmount == $taxAmount) {
            // impossible in real world, but this check is required to prevent possible division by zero
            return 0;
        }

        return round(100 / (($paidAmount - $taxAmount) / $taxAmount), 4);
    }

    public function hasVat()
    {
        return $this->order->getTaxAmount() == 0;
    }

    public function hasTax()
    {
        return $this->order->getTaxAmount() != 0;
    }

    public function isShippingPriceIncludesTax()
    {
        return $this->order->getTaxAmount() == 0;
    }

    public function isTaxModeNone()
    {
        return $this->order->getAmazonAccount()->isMagentoOrdersTaxModeNone();
    }

    public function isTaxModeMagento()
    {
        return $this->order->getAmazonAccount()->isMagentoOrdersTaxModeMagento();
    }

    public function isTaxModeChannel()
    {
        return $this->order->getAmazonAccount()->isMagentoOrdersTaxModeChannel();
    }

    public function getChannelComments()
    {
        $comments = array();

        if ($this->order->getDiscountAmount() != 0) {
            $discount = Mage::getSingleton('M2ePro/Currency')
                ->formatPrice($this->getCurrency(), $this->order->getDiscountAmount());

            $comment = Mage::helper('M2ePro')->__(
                '%s promotion discount amount were subtracted from the Total Amount.', $discount
            );
            $comment .= '<br />';

            $comments[] = $comment;
        }

        // Gift Wrapped Items
        // ---------------------------------------------------
        $itemsGiftPrices = array();

        /** @var Ess_M2ePro_Model_Order_Item[] $items */
        $items = $this->order->getParentObject()->getItemsCollection();
        foreach ($items as $item) {
            $giftPrice = $item->getChildObject()->getGiftPrice();
            if (empty($giftPrice)) {
                continue;
            }

            $itemsGiftPrices[] = array(
                'name'  => $item->getMagentoProduct()->getName(),
                'type'  => $item->getChildObject()->getGiftType(),
                'price' => $giftPrice,
            );
        }

        if (!empty($itemsGiftPrices)) {
            $comment = '<u>'.Mage::helper('M2ePro')->__('The following items are purchased with gift wraps') . ':</u><br />';

            foreach ($itemsGiftPrices as $productInfo) {
                $formattedCurrency = Mage::getSingleton('M2ePro/Currency')->formatPrice(
                    $this->getCurrency(), $productInfo['price']
                );

                $comment .= '<b>'.$productInfo['name'].'</b> -> '
                    .$productInfo['type'].' ('.$formattedCurrency.')';
            }

            $comments[] = $comment;
        }
        // ---------------------------------------------------

        return $comments;
    }

    // ########################################
}