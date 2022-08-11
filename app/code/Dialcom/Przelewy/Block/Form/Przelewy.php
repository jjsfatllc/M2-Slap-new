<?php

namespace Dialcom\Przelewy\Block\Form;

use Dialcom\Przelewy\Helper\Data;
use Dialcom\Przelewy\Model\Recurring;
use Dialcom\Przelewy\Model\Config\Waluty;
use Dialcom\Przelewy\ZenCard\ZenCardApi;

class Przelewy extends \Magento\Payment\Block\Form
{

    /**
     * @var \Magento\Framework\App\Config\ScopeConfigInterface
     */
    protected $scopeConfig;

    /**
     * @var \Magento\Store\Model\StoreManagerInterface
     */
    protected $storeManager;

    /**
     * @var \Magento\Framework\App\ObjectManager
     */
    protected $objectManager;

    /**
     * @var \Magento\Framework\UrlInterface
     */
    protected $urlBuilder;

    /**
     * @var \Magento\Customer\Model\Session
     */
    private $customerSession;

    /**
     * Przelewy constructor.
     * @param \Magento\Backend\Block\Template\Context $context
     * @param array $data
     */
    public function __construct(
        \Magento\Backend\Block\Template\Context $context,
        array $data = []
    )
    {
        $this->scopeConfig = $context->getScopeConfig();
        $this->storeManager = $context->getStoreManager();
        $this->objectManager = \Magento\Framework\App\ObjectManager::getInstance();
        $this->customerSession = $this->objectManager->get('Magento\Customer\Model\Session');
        $this->urlBuilder = $context->getUrlBuilder();
        parent::__construct($context, $data);
    }

//    protected function _construct()
//    {
//        parent::_construct();
//        $this->setTemplate('dialcom/przelewy/form.phtml');
//    }

    public function getCards()
    {
        if (!is_null($this->customerSession) && $this->customerSession->isLoggedIn()) {
            $customerData = $this->customerSession->getCustomer();
            return Recurring::getCards($customerData->getId());
        }
        return array();
    }

    public function getDescription()
    {
        return __($this->scopeConfig->getValue(Data::XML_PATH_TEXT, \Magento\Store\Model\ScopeInterface::SCOPE_STORE));
    }

    public function getLastPaymentMethod()
    {
        try {
            if ($this->customerSession->isLoggedIn()) {
                $customerId = $this->customerSession->getCustomer()->getId();
                if (!is_null($customerId)) {
                    $collection = $this->objectManager->create('Magento\Sales\Model\ResourceModel\Order\CollectionFactory')
                        ->create()
                        ->AddFieldToFilter(
                            'customer_id',
                            array(
                                'eq' => $customerId
                            )
                        );
                    $collection->setOrder('created_at', \Magento\Framework\Data\Collection\AbstractDb::SORT_ORDER_DESC);
                    $order = $collection->getFirstItem();
                    if ($order && $order->getPayment()) {
                        $paymentData = $order->getPayment()->getData();
                        if (isset($paymentData['additional_information']['method_id'])) {
                            $lastMethod = $paymentData['additional_information']['method_id'];
                            if (!in_array($lastMethod, Recurring::getChannelsCards())) {
                                return $lastMethod;
                            }
                        }
                    }
                }
            }
        } catch (\Exception $e) {
            error_log(__METHOD__ . ' ' . $e->getMessage());
        }
        return false;
    }

    public function getPaymentChannels()
    {
        $channels = $this->objectManager->create('Dialcom\Przelewy\Model\Config\Channels');
        $currency = strtoupper($this->storeManager->getStore()->getCurrentCurrencyCode());
        $nonPln = $channels::getChannelsNonPln();
        $payment_list = array();
        foreach ($channels->toOptionArray() as $item) {
            if ($currency == 'PLN' || in_array($item['value'], $nonPln)) {
                $payment_list[$item['value']] = $item['label'];
            }
        }
        return $payment_list;
    }

    public function getBankHtml($bank_id, $bank_name, $text = '', $cc_id = '', $class = '')
    {
        $bank_id = $this->escapeHtml($bank_id);
        $bank_name = $this->escapeHtml($bank_name);
        $text = $this->escapeHtml($text);
        $cc_id = $this->escapeHtml($cc_id);
        $class = $this->escapeHtml($class);
        return '<a class="bank-box ' . $class . '" data-id="' . $bank_id . '" data-cc="' . $cc_id . '">' .
        '<div class="bank-logo bank-logo-' . $bank_id . '">' .
        (empty($text) ? "" : "<span>{$text}</span>") .
        '</div><div class="bank-name">' . $bank_name . '</div></a>';
    }

    public function getBankTxt($bank_id, $bank_name, $checked = false, $cc_id = '', $text = '')
    {
        $bank_id = $this->escapeHtml($bank_id);
        $bank_name = $this->escapeHtml($bank_name);
        $text = $this->escapeHtml($text);
        $cc_id = $this->escapeHtml($cc_id);
        return
            '<li><div class="input-box  bank-item">' .
            '<input id="przelewy_method_id_' . $bank_id . '-' . $cc_id . '" name="payment_method_id" data-id="' . $bank_id . '" data-cc="' . $cc_id . '" data-text="' . $text . '" ' .
            ' class="radio" type="radio" ' . ($checked ? 'checked="checked"' : '') . ' />' .
            '<label for="przelewy_method_id_' . $bank_id . '-' . $cc_id . '">' . $bank_name . '</label>' .
            '</div></li>';

    }

    public function p24getCssUrl()
    {
        return $this->getAssetUrl('Dialcom_Przelewy::css/css_paymethods.css');
    }

    public function p24getJsUrl()
    {
        return $this->getAssetUrl('Dialcom_Przelewy::js/payment.js');
    }

    public function getCardImgUrl()
    {
        return $this->getAssetUrl('Dialcom_Przelewy::images/cc_empty.png');
    }

    private function getAssetUrl($asset)
    {
        $assetRepository = $this->objectManager->get('Magento\Framework\View\Asset\Repository');
        return $assetRepository->createAsset($asset)->getUrl();
    }

    public function getMyCardsUrl($cardId)
    {
        return $this->urlBuilder->getUrl('przelewy/przelewy/mycards', array('cardrm' => $cardId));
    }

    public function getLogoUrl()
    {
        return $this->getAssetUrl('Dialcom_Przelewy::images/logo_small.png');
    }

    /**
     * @return bool
     */
    public function isZenCardEnabled()
    {
        $currency = strtoupper($this->storeManager->getStore()->getCurrentCurrencyCode());
        $fullConfig = Waluty::getFullConfig($currency, $this->scopeConfig);

        $zenCardApi = new ZenCardApi($fullConfig['merchant_id'], $fullConfig['api']);

        return (boolean)$zenCardApi->isEnabled();
    }

    /**
     * @return string
     */
    public function getZendCardScript()
    {
        $currency = strtoupper($this->storeManager->getStore()->getCurrentCurrencyCode());
        $fullConfig = Waluty::getFullConfig($currency, $this->scopeConfig);

        $zenCardApi = new ZenCardApi($fullConfig['merchant_id'], $fullConfig['api']);
        return $zenCardApi->getScript();
    }

    /**
     * @return mixed
     */
    public function getZendCardScriptUrl()
    {
        $script = $this->getZendCardScript();
        $search = array('<script src="', '" data-zencard-mtoken="' . $this->getZendCardScriptToken() . '"></script>');
        $replace = array('', '');
        return str_replace($search, $replace, $script);
    }

    /**
     * @return mixed
     */
    public function getZendCardScriptToken()
    {
        $script = $this->getZendCardScript();
        if (strpos($script, 'demo.zencard.pl') !== false) {
            $search = array('<script src="https://public.demo.zencard.pl/js/dist/zencard-sdk.min.js" data-zencard-mtoken="', '"></script>');
        } else {
            $search = array('<script src="https://public.zencard.pl/js/dist/zencard-sdk.min.js" data-zencard-mtoken="', '"></script>');
        }

        $replace = array('', '');
        return str_replace($search, $replace, $script);
    }
}
