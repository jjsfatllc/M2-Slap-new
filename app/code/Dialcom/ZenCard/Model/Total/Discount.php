<?php
/**
 * Copyright ? 2015 Magento. All rights reserved.
 * See COPYING.txt for license details.
 */
namespace Dialcom\ZenCard\Model\Total;

use Dialcom\Przelewy\Helper\Data;
use Dialcom\Przelewy\ZenCard\ZenCardApi;

class Discount extends \Magento\Quote\Model\Quote\Address\Total\AbstractTotal
{
    /**
     * @var \Magento\Quote\Model\QuoteValidator
     */
    private $quoteValidator;

    /**
     * @var \Magento\Framework\App\Config\ScopeConfigInterface
     */
    private $scopeConfig;

    /**
     * @var \Magento\Framework\Pricing\PriceCurrencyInterface
     */
    private $priceCurrency;

    /**
     * @param \Magento\Quote\Model\QuoteValidator $quoteValidator
     * @param \Magento\Framework\App\Config\ScopeConfigInterface $scopeConfig
     * @param \Magento\Framework\Pricing\PriceCurrencyInterface $priceCurrency
     */
    public function __construct(
        \Magento\Quote\Model\QuoteValidator $quoteValidator,
        \Magento\Framework\App\Config\ScopeConfigInterface $scopeConfig,
        \Magento\Framework\Pricing\PriceCurrencyInterface $priceCurrency
    )
    {
        $this->quoteValidator = $quoteValidator;
        $this->scopeConfig = $scopeConfig;
        $this->priceCurrency = $priceCurrency;
    }


    public function collect(
        \Magento\Quote\Model\Quote $quote,
        \Magento\Quote\Api\Data\ShippingAssignmentInterface $shippingAssignment,
        \Magento\Quote\Model\Quote\Address\Total $total
    )
    {
        parent::collect($quote, $shippingAssignment, $total);

        if (!$this->scopeConfig->getValue(Data::XML_PATH_ZENCARD, \Magento\Store\Model\ScopeInterface::SCOPE_STORE)) {
            return $this;
        }

        $discountAmount = $this->getZenCardDiscount($total, $quote);
        //$discount =  $this->priceCurrency->convert($baseDiscount);
//           $total->addTotalAmount('customdiscount', $discountAmount);
//           $total->addBaseTotalAmount('customdiscount', $discountAmount);
        $total->setFee($discountAmount);
        $total->setBaseFee($discountAmount);

        $total->setGrandTotal($total->getTotalAmount('subtotal') + $discountAmount);
        $total->setBaseGrandTotal($total->getTotalAmount('subtotal') + $discountAmount);
        try {
            $this->_setAmount($discountAmount)->_setBaseAmount($discountAmount);
        } catch (\Exception $ex) {
            file_put_contents(__DIR__ . '/collect.log',
                date('Y-m-d H:i:s')
                . ' $$ex: ' . print_r([$ex->getMessage()], true) . ' |' . "\n",
                FILE_APPEND
            );

        }
        $quote->setCustomDiscount($discountAmount);

        file_put_contents(__DIR__ . '/collect.log',
            date('Y-m-d H:i:s')
            . ' $discountAmount: ' . print_r([$discountAmount, $total->getTotalAmount('subtotal') + $discountAmount], true) . ' |' . "\n",
            FILE_APPEND
        );

        return $this;
    }

//    protected function clearValues(Address\Total $total)
//    {
//        $total->setTotalAmount('subtotal', 0);
//        $total->setBaseTotalAmount('subtotal', 0);
//        $total->setTotalAmount('tax', 0);
//        $total->setBaseTotalAmount('tax', 0);
//        $total->setTotalAmount('discount_tax_compensation', 0);
//        $total->setBaseTotalAmount('discount_tax_compensation', 0);
//        $total->setTotalAmount('shipping_discount_tax_compensation', 0);
//        $total->setBaseTotalAmount('shipping_discount_tax_compensation', 0);
//        $total->setSubtotalInclTax(0);
//        $total->setBaseSubtotalInclTax(0);
//    }

    /**
     * Assign subtotal amount and label to address object
     *
     * @param \Magento\Quote\Model\Quote $quote
     * @param Address\Total $total
     * @return array
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    public function fetch(\Magento\Quote\Model\Quote $quote, \Magento\Quote\Model\Quote\Address\Total $total)
    {
        return [
            'code' => 'discount',
            'title' => $this->getLabel(),
            'value' => 0
        ];
    }

    /**
     * Get Subtotal label
     *
     * @return \Magento\Framework\Phrase
     */
    public function getLabel()
    {
        return __('discount');
    }

    /**
     * @return float
     */
    private function getZenCardDiscount($total, $quote)
    {
        $result = 0.0;
        $merchantId = $this->scopeConfig->getValue(Data::XML_PATH_MERCHANT_ID, \Magento\Store\Model\ScopeInterface::SCOPE_STORE);
        $apiKey = $this->scopeConfig->getValue(Data::XML_PATH_API_KEY, \Magento\Store\Model\ScopeInterface::SCOPE_STORE);
        $zenCardApi = new ZenCardApi($merchantId, $apiKey);

        try {
            $objectManager = \Magento\Framework\App\ObjectManager::getInstance();
            $customerSession = $objectManager->get('Magento\Customer\Model\Session');
            if ($customerSession->isLoggedIn()) {
                $customer = $customerSession->getCustomer();
                $customerData = $customer->getData();
                $email = $customerData['email'];
            } else {
                $milliseconds = round(microtime(true) * 1000);
                $email = 'przelewy_' . $milliseconds . '@zencard.pl';
            }

            $amount = $total->getTotalAmount('subtotal') * 100;
            $storeUrl = $objectManager->get('Magento\Store\Model\StoreManagerInterface')
                ->getStore()
                ->getBaseUrl();
            $orderId = (int) $quote->getId();
            $zenCardOrderId = $zenCardApi->buildZenCardOrderId($orderId, $storeUrl);
            $transaction = $zenCardApi->verify($email, $amount, $zenCardOrderId);

            file_put_contents(
                __DIR__ . '/getZenCardDiscount.log',
                date('Y-m-d H:i:s')
                . ' $total(): ' . print_r([$amount, $transaction], true) . ' |' . "\n",
                FILE_APPEND
            );
            if ($transaction && $transaction->isVerified() && $transaction->hasDiscount()) {
                $result = $transaction->getDiscountAmountNegative();
            }
        } catch (\Exception $ex) {
            file_put_contents(__DIR__ . '/getZenCardDiscount.log',
                date('Y-m-d H:i:s')
                . ' $ex: ' . $ex->getMessage() . ' |' . "\n",
                FILE_APPEND
            );
        }

        return $result;
    }
}
