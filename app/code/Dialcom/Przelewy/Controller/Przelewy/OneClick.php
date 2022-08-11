<?php

namespace Dialcom\Przelewy\Controller\Przelewy;

use Dialcom\Przelewy\Helper\Data;

class OneClick extends \Magento\Framework\App\Action\Action
{
    /**
     * @var \Magento\Framework\App\Config\ScopeConfigInterface
     */
    protected $scopeConfig;

    /**
     * @var \Dialcom\Przelewy\Helper\Data
     */
    protected $helper;

    /**
     * OneClick constructor.
     * @param \Magento\Framework\App\Action\Context $context
     * @param \Magento\Framework\App\Config\ScopeConfigInterface $scopeConfig
     * @param \Dialcom\Przelewy\Helper\Data $helper
     */
    public function __construct(
        \Magento\Framework\App\Action\Context $context,
        \Magento\Framework\App\Config\ScopeConfigInterface $scopeConfig,
        \Dialcom\Przelewy\Helper\Data $helper
    )
    {
        $this->scopeConfig = $scopeConfig;
        $this->helper = $helper;
        parent::__construct($context);
    }

    public function execute()
    {
        $zenCardConfirmed = true;
        $order_id = (int) $this->_objectManager->get('Magento\Checkout\Model\Session')->getLastRealOrderId();
        $przelewy = $this->_objectManager->create('Dialcom\Przelewy\Model\Payment\Przelewy');
        $przelewy->addExtracharge($order_id);

        $order = $this->_objectManager->create('Magento\Sales\Model\Order')->load($order_id);
        $storeId = $order->getStoreId();

        if ((int)$this->scopeConfig->getValue(Data::XML_PATH_ZENCARD, \Magento\Store\Model\ScopeInterface::SCOPE_STORE, $storeId) === 1) {
            $przelewy->addZenCardDiscount($order_id);
            $order = $this->_objectManager->create('Magento\Sales\Model\Order')->loadByIncrementId($order_id);
            $zenCardConfirmed = $przelewy->confirmZenCardDiscount($order);
        }

        if ($zenCardConfirmed) {
            $paymentData = $this->_objectManager->create('Magento\Sales\Model\Order')->loadByIncrementId($order_id)->getPayment()->getData();
            $additionalInformation = $paymentData['additional_information'];
            $recurring = $this->_objectManager->create('Dialcom\Przelewy\Model\Recurring');
            $result = $recurring->chargeCard($order_id, (int)$additionalInformation['cc_id']);
            if (!!$result) {
                $order = $this->_objectManager->create('Magento\Sales\Model\Order')->loadByIncrementId($order_id);

                $chgState = (int)$this->scopeConfig->getValue(Data::XML_PATH_CHG_STATE, \Magento\Store\Model\ScopeInterface::SCOPE_STORE, $storeId);
                $mkInvoice = (int)$this->scopeConfig->getValue(Data::XML_PATH_MK_INVOICE, \Magento\Store\Model\ScopeInterface::SCOPE_STORE, $storeId);

                if ($chgState == 1) {
                    $order->addStatusToHistory(\Magento\Sales\Model\Order::STATE_PROCESSING, __('The payment has been accepted.'), true);
                    $order->setState(\Magento\Sales\Model\Order::STATE_PROCESSING, true);
                    $order->save();
                    if ($mkInvoice == 1) {
                        $this->helper->makeInvoiceFromOrder($order);
                        $order->setSendEmail(true);
                    } else {
                        $order->setTotalPaid($order->getGrandTotal());
                    }
                }

                $order->save();
                $this->_redirect('przelewy/przelewy/success', array('ga_order_id' => $order_id));
            } else {
                $this->_redirect('przelewy/przelewy/failure', array('ga_order_id' => $order_id));
            }
        } else {
            $this->_redirect('przelewy/przelewy/failure', array('ga_order_id' => $order_id));
        }
    }
}
