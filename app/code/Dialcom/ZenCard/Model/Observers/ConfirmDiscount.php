<?php

namespace Dialcom\ZenCard\Model\Observers;

use \Magento\Framework\Event\Observer;
use \Magento\Framework\Event\ObserverInterface;
use \Psr\Log\LoggerInterface;
use Dialcom\Przelewy\Helper\Data;
use Dialcom\Przelewy\Model\Payment\Przelewy;

class ConfirmDiscount implements ObserverInterface
{
    protected $_logger;

    /**
     * @var \Magento\Framework\App\Config\ScopeConfigInterface
     */
    private $scopeConfig;

    /**
     * @var \Magento\Framework\ObjectManagerInterface 
     */
    private $objectManager;

    public function __construct(
        LoggerInterface $logger,
        \Magento\Framework\App\Config\ScopeConfigInterface $scopeConfig,
	\Magento\Framework\ObjectManagerInterface $objectManager
    ) {
        $this->_logger = $logger;
        $this->scopeConfig = $scopeConfig;
	$this->objectManager = $objectManager;
    }

    public function execute(Observer $observer)
    {
        if(!$this->scopeConfig->getValue(Data::XML_PATH_ZENCARD, \Magento\Store\Model\ScopeInterface::SCOPE_STORE)) {
		return;
	}
            $order = $observer->getEvent()->getOrder();
            $quote = $observer->getEvent()->getQuote();
            $paymentMethod = $quote->getPayment()->getMethodInstance()->getCode();
            file_put_contents(__DIR__ . '/observer_exec.log',
                date('Y-m-d H:i:s')
                . ' name: ' . $observer->getEvent()->getName() . ' $paymentMethod: ' . print_r([ $paymentMethod ],true),
                FILE_APPEND
            );
            $shippingAddress = $quote->getShippingAddress();
            $paymentMethod = $quote->getPayment()->getMethodInstance()->getCode();

            if ($shippingAddress && $shippingAddress->getData('discount_total')) {
                $address = $shippingAddress;
            } else {
                $address = $quote->getBillingAddress();
            }

            $discount = $address->getData('discount_total');


        $payment = $this->objectManager->create('Dialcom\Przelewy\Model\Payment\Przelewy');
        $zenCardConfirmed = $payment->confirmZenCardDiscount($order);

        if ($paymentMethod === 'dialcom_przelewy') {
            $order->setData('discount_total', $discount);
        } else if ($zenCardConfirmed) {
            $order->setData('discount_total', $discount);
        } else {
            $grandTotal = $order->getGrandTotal();
            $baseGrandTotal = $order->getBaseGrandTotal();

            $order->setGrandTotal($grandTotal + abs($discount));
            $order->setBaseGrandTotal($baseGrandTotal + abs($discount));
            $address->setData('discount_total', 0.0);

            $address->save();
            $quote->save();
        }

           $order->save();
    }
}
