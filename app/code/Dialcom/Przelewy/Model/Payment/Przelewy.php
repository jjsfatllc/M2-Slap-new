<?php

namespace Dialcom\Przelewy\Model\Payment;

use Dialcom\Przelewy\Helper\Data;
use Dialcom\Przelewy\Przelewy24Class;
use Dialcom\Przelewy\Model\Recurring;
use Dialcom\Przelewy\ZenCard\ZenCardApi;

class  Przelewy extends \Magento\Payment\Model\Method\AbstractMethod
{
    const PAYMENT_METHOD_PRZELEWY_CODE = 'dialcom_przelewy';
    protected $_code = self::PAYMENT_METHOD_PRZELEWY_CODE;
    protected $_formBlockType = 'Dialcom\Przelewy\Block\Form\Przelewy';
    protected $_infoBlockType = 'Dialcom\Przelewy\Block\Info\Przelewy';

    protected $_isGateway = false;
    protected $_canAuthorize = false;
    protected $_canCapture = true;
    protected $_canCapturePartial = false;
    protected $_canRefund = false;
    protected $_canVoid = false;
    protected $_canUseInternal = true;
    protected $_canUseCheckout = true;
    protected $_canUseForMultishipping = false;
    protected $_canSaveCc = false;

    private $P24 = null;
    private $storeId = 0;

    /**
     * @var \Magento\Framework\App\Config\ScopeConfigInterface
     */
    protected $scopeConfig;

    /**
     * @var \Magento\Framework\App\RequestInterface
     */
    protected $request;

    /**
     * @var \Psr\Log\LoggerInterface
     */
    protected $logger;
    /**
     * @var \Magento\Framework\UrlInterface
     */
    protected $urlBuilder;

    /**
     * @var \Magento\Framework\App\ObjectManager
     */
    private $objectManager;

    /**
     * @var \Magento\Customer\Model\Session
     */
    private $customerSession;

    /**
     * @var \Magento\Store\Model\StoreManagerInterface
     */
    public $_storeManager;

    /**
     * Przelewy constructor.
     * @param \Magento\Framework\Model\Context $context
     * @param \Magento\Framework\Registry $registry
     * @param \Magento\Framework\Api\ExtensionAttributesFactory $extensionFactory
     * @param \Magento\Framework\Api\AttributeValueFactory $customAttributeFactory
     * @param \Magento\Payment\Helper\Data $paymentData
     * @param \Magento\Framework\App\Config\ScopeConfigInterface $scopeConfig
     * @param \Magento\Payment\Model\Method\Logger $logger
     * @param \Magento\Framework\App\RequestInterface $request
     * @param \Magento\Framework\UrlInterface $urlBuilder
     * @param \Magento\Store\Model\StoreManagerInterface $storeManager
     * @param \Magento\Framework\Model\ResourceModel\AbstractResource|null $resource
     * @param \Magento\Framework\Data\Collection\AbstractDb|null $resourceCollection
     * @param array $data
     */
    public function __construct(
        \Magento\Framework\Model\Context $context,
        \Magento\Framework\Registry $registry,
        \Magento\Framework\Api\ExtensionAttributesFactory $extensionFactory,
        \Magento\Framework\Api\AttributeValueFactory $customAttributeFactory,
        \Magento\Payment\Helper\Data $paymentData,
        \Magento\Framework\App\Config\ScopeConfigInterface $scopeConfig,
        \Magento\Payment\Model\Method\Logger $logger,
        \Magento\Framework\App\RequestInterface $request,
        \Magento\Framework\UrlInterface $urlBuilder,
        \Magento\Store\Model\StoreManagerInterface $storeManager,
        \Magento\Framework\Model\ResourceModel\AbstractResource $resource = null,
        \Magento\Framework\Data\Collection\AbstractDb $resourceCollection = null,
        array $data = []
    )
    {
        $this->objectManager = \Magento\Framework\App\ObjectManager::getInstance();
        /*        $this->request = $this->objectManager->get('Magento\Framework\App\RequestInterface'); // Magento\Framework\App\Request\Http
                $this->urlBuilder = $this->objectManager->get('\Magento\Framework\UrlInterface');*/
        $this->request = $request;
        $this->urlBuilder = $urlBuilder;
        $this->scopeConfig = $scopeConfig;
        $this->logger = $logger;
        $this->customerSession = $this->objectManager->get('Magento\Customer\Model\Session');
        $this->_storeManager = $storeManager;
        $this->storeId = $this->_storeManager->getStore()->getStoreId();

        $this->P24 = new Przelewy24Class($this->getMerchantId(),
            $this->getShopId(),
            $this->getSalt(),
            ($this->getTestMode() == '1'));

        parent::__construct(
            $context,
            $registry,
            $extensionFactory,
            $customAttributeFactory,
            $paymentData,
            $scopeConfig,
            $logger,
            $resource,
            $resourceCollection,
            $data
        );
    }

    public static function requestGet($url)
    {
        $isCurl = function_exists('curl_init') && function_exists('curl_setopt') && function_exists('curl_exec') && function_exists('curl_close');

        if ($isCurl) {
            $userAgent = "Mozilla/4.0 (compatible; MSIE 5.01; Windows NT 5.0)";
            $curlConnection = curl_init();
            curl_setopt($curlConnection, CURLOPT_URL, $url);
            curl_setopt($curlConnection, CURLOPT_SSL_VERIFYHOST, 2);
            curl_setopt($curlConnection, CURLOPT_USERAGENT, $userAgent);
            curl_setopt($curlConnection, CURLOPT_RETURNTRANSFER, 1);
            curl_setopt($curlConnection, CURLOPT_SSL_VERIFYPEER, false);
            $result = curl_exec($curlConnection);
            curl_close($curlConnection);
            return $result;
        }
        return "";
    }

    public static function getMinRatyAmount()
    {
        return 300;
    }

    public function assignData(\Magento\Framework\DataObject $data)
    {
        if(!empty($data->_data['additional_data'])){
            $additionalData = $data->_data['additional_data'];

            $info = $this->getInfoInstance();
            if (isset($additionalData['method_id'])) {
                $info->setAdditionalInformation('method_id', $additionalData['method_id']);
            }
            if (isset($additionalData['method_name'])) {
                $info->setAdditionalInformation('method_name', $additionalData['method_name']);
            }
            if (isset($additionalData['accept_regulations'])) {
                $info->setAdditionalInformation('accept_regulations', $additionalData['accept_regulations']);
            }
            if (isset($additionalData['cc_id'])) {
                $info->setAdditionalInformation('cc_id', $additionalData['cc_id']);
            }
            if (isset($additionalData['cc_name'])) {
                $info->setAdditionalInformation('cc_name', $additionalData['cc_name']);
            }
            if (isset($additionalData['p24_forget'])) {
                $info->setAdditionalInformation('p24_forget', $additionalData['p24_forget']);
                Recurring::setP24Forget((int)$additionalData['p24_forget'] === 1);
            }
        }
        return $this;
    }

    public function getText()
    {
        return $this->getConfigData("text");
    }

    public function getOrderPlaceRedirectUrl()
    {
        return $this->urlBuilder->getUrl('przelewy/przelewy/redirect', array('noCache' => time() . uniqid(true)));
    }

    public function getCheckout()
    {
        return $this->objectManager->get('Magento\Checkout\Model\Session');
    }

    public function getRedirectionFormData($orderId = null)
    {

        if (is_null($orderId)) {
            $orderId = (int) $this->getCheckout()->getLastRealOrderId();
        }else{
            $orderId = (int) $orderId;
        }
        $order = $this->objectManager->create('Magento\Sales\Model\Order')->loadByIncrementId($orderId);
        $this->storeId = $order->getStoreId();
        $sessionId = Data::getSessionId($orderId);
        $order->setData('p24_session_id', $sessionId);
        $order->save();
        $amount = number_format($order->getGrandTotal() * 100, 0, "", "");
        $currency = $order->getOrderCurrencyCode();

        $data = array(
            'p24_session_id' => $sessionId,
            'p24_merchant_id' => (int) $this->getMerchantId(),
            'p24_pos_id' => (int) $this->getShopId(),
            'p24_email' => filter_var($order->getCustomerEmail(), FILTER_SANITIZE_EMAIL),
            'p24_amount' => $amount,
            'p24_currency' => $currency,
            'p24_description' => filter_var(__('Order').' '. $orderId, FILTER_SANITIZE_STRING),
            'p24_language' => strtolower(substr($this->objectManager->get('Magento\Framework\Locale\Resolver')->getLocale(), 0, 2)),
            'p24_client' => filter_var($order->getBillingAddress()->getData('firstname') . ' ' . $order->getBillingAddress()->getData('lastname'), FILTER_SANITIZE_STRING),
            'p24_address' => filter_var($order->getBillingAddress()->getData('street'), FILTER_SANITIZE_STRING),
            'p24_city' => filter_var($order->getBillingAddress()->getData('city'), FILTER_SANITIZE_STRING),
            'p24_zip' => $order->getBillingAddress()->getData('postcode'),
            'p24_country' => 'PL',
            'p24_encoding' => 'utf-8',
            'p24_url_status' => filter_var($this->urlBuilder->getUrl('przelewy/przelewy/status'),FILTER_SANITIZE_URL),
            'p24_url_return' => filter_var($this->urlBuilder->getUrl('przelewy/przelewy/returnUrl', array('ga_order_id' => $orderId)),FILTER_SANITIZE_URL),
            'p24_api_version' => filter_var(P24_VERSION, FILTER_SANITIZE_URL),
            'p24_ecommerce' => 'magento2_' . $this->objectManager->get('Magento\Framework\App\ProductMetadata')->getVersion(),
            'p24_ecommerce2' => $this->objectManager->get('Magento\Framework\Module\ModuleList')->getOne('Dialcom_Przelewy')['setup_version'],
            'p24_wait_for_result' => $this->getWaitForResult() ? '1' : '0',
            'p24_shipping' => number_format($order->getShippingAmount() * 100, 0, "", ""),
        );

        $productsInfo = array();
        foreach ($order->getAllVisibleItems() as $item) {
            $productId = $item->getProductId();
            $product = $this->objectManager->create('Magento\Catalog\Model\Product')->load($productId);

            $productsInfo[] = array(
                'name' => filter_var($product->getName(), FILTER_SANITIZE_STRING),
                'description' => $product->getDescription(),
                'quantity' => (int)$item->getQtyOrdered(),
                'price' => (int)number_format($item->getPrice() * 100, 0, "", ""),
                'number' => $productId,
            );
        }

        $translations = array(
            'virtual_product_name' => __('Extra charge [VAT and discounts]')->__toString(),
            'cart_as_product' => __('Your order')->__toString(),
        );

        $p24Product = new \Przelewy24Product($translations);
        $p24ProductItems = $p24Product->prepareCartItems($amount, $productsInfo, $data['p24_shipping']);

        $data = array_merge($data, $p24ProductItems);

        $data['p24_sign'] = $this->P24->trnDirectSign($data);

        $info = $order->getPayment()->getMethodInstance()->getInfoInstance();
        if ((int)$info->getAdditionalInformation('method_id') > 0) {
            $data['p24_method'] = (int)$info->getAdditionalInformation('method_id');
        }

        $p24_time_limit = $this->getTimeLimit();
        if (!empty($p24_time_limit) && (int)$p24_time_limit >= 0 && (int)$p24_time_limit <= 99) {
            $data['p24_time_limit'] = (int)$p24_time_limit;
        }

        if ($this->getPaySlow()) {
            $data['p24_channel'] = 16;
        }

        if ((int)$info->getAdditionalInformation('accept_regulations') > 0) {
            $data['p24_regulation_accept'] = 1;
        }

        $this->P24->checkMandatoryFieldsForAction($data, 'trnDirect');
        return (array)@$data;
    }

    public function getMerchantId()
    {
        return $this->scopeConfig->getValue(Data::XML_PATH_MERCHANT_ID, \Magento\Store\Model\ScopeInterface::SCOPE_STORE, $this->storeId);
    }

    public function getShopId()
    {
        return $this->scopeConfig->getValue(Data::XML_PATH_SHOP_ID, \Magento\Store\Model\ScopeInterface::SCOPE_STORE, $this->storeId);
    }

    public function getSalt()
    {
        return $this->scopeConfig->getValue(Data::XML_PATH_SALT, \Magento\Store\Model\ScopeInterface::SCOPE_STORE, $this->storeId);
    }

    public function getTestMode()
    {
        return $this->scopeConfig->getValue(Data::XML_PATH_MODE, \Magento\Store\Model\ScopeInterface::SCOPE_STORE, $this->storeId);
    }

    public function getTimeLimit()
    {
        return $this->scopeConfig->getValue(Data::XML_PATH_TIMELIMIT, \Magento\Store\Model\ScopeInterface::SCOPE_STORE, $this->storeId);
    }

    public function getWaitForResult()
    {
        return $this->scopeConfig->getValue(Data::XML_PATH_WAIT_FOR_RESULT, \Magento\Store\Model\ScopeInterface::SCOPE_STORE, $this->storeId);
    }

    public function getPaySlow()
    {
        return $this->scopeConfig->getValue(Data::XML_PATH_PAYSLOW, \Magento\Store\Model\ScopeInterface::SCOPE_STORE, $this->storeId);
    }

    public function getPayMethodFirst()
    {

        return $this->scopeConfig->getValue(Data::XML_PATH_PAYMETHOD_FIRST, \Magento\Store\Model\ScopeInterface::SCOPE_STORE, $this->storeId);
    }

    public function getPayMethodSecond()
    {
        return $this->scopeConfig->getValue(Data::XML_PATH_PAYMETHOD_SECOND, \Magento\Store\Model\ScopeInterface::SCOPE_STORE, $this->storeId);
    }

    public function getRegulationAccept()
    {
        return $this->scopeConfig->getValue(Data::XML_PATH_P24REGULATIONS, \Magento\Store\Model\ScopeInterface::SCOPE_STORE, $this->storeId);
    }

    public function getOneClick()
    {
        return $this->scopeConfig->getValue(Data::XML_PATH_ONECLICK, \Magento\Store\Model\ScopeInterface::SCOPE_STORE, $this->storeId);
    }

    public function getShowPayMethods()
    {
        return $this->scopeConfig->getValue(Data::XML_PATH_SHOWPAYMENTMETHODS, \Magento\Store\Model\ScopeInterface::SCOPE_STORE, $this->storeId);
    }

    public function getUseGraphical()
    {
        return $this->scopeConfig->getValue(Data::XML_PATH_USEGRAPHICAL, \Magento\Store\Model\ScopeInterface::SCOPE_STORE, $this->storeId);
    }

    public function getInstallment()
    {
        return $this->scopeConfig->getValue(Data::XML_PATH_INSTALLMENT, \Magento\Store\Model\ScopeInterface::SCOPE_STORE, $this->storeId);
    }

    public function getShowPromoted()
    {
        return $this->scopeConfig->getValue(Data::XML_PATH_SHOW_PROMOTED, \Magento\Store\Model\ScopeInterface::SCOPE_STORE, $this->storeId);
    }

    public function getPayMethodPromoted()
    {
        return $this->scopeConfig->getValue(Data::XML_PATH_PAYMETHOD_PROMOTED, \Magento\Store\Model\ScopeInterface::SCOPE_STORE, $this->storeId);
    }

    public function getZencard()
    {
        return $this->scopeConfig->getValue(Data::XML_PATH_ZENCARD, \Magento\Store\Model\ScopeInterface::SCOPE_STORE, $this->storeId);
    }

    public function getTotalPrice()
    {
        return number_format($this->getCheckout()->getQuote()->getBaseGrandTotal(), 2, '.', '');
    }

    public function getPaymentURI()
    {
        return $this->P24->trnDirectUrl();
    }

    public function getCountriesToOptionArray()
    {
        $new = array();
        foreach ($this->_sa_countries as $key => $option) {
            $new[] = array(
                'value' => $key,
                'label' => $option
            );
        }

        return $new;
    }

    private $_sa_countries = array(
        'AL' => 'Albania',
        'AUS' => 'Australia',
        'A' => 'Austria',
        'BY' => 'Belarus',
        'B' => 'Belgium',
        'BIH' => 'Bosnia and Herzegowina',
        'BR' => 'Brazil',
        'BG' => 'Bulgaria',
        'CDN' => 'Canada',
        'HR' => 'Croatia',
        'CY' => 'Cyprus',
        'CZ' => 'Czech Republic',
        'DK' => 'Denmark',
        'ET' => 'Egypt',
        'EST' => 'Estonia',
        'FIN' => 'Finland',
        'F' => 'France',
        'DE' => 'Germany',
        'GR' => 'Greece',
        'H' => 'Hungary',
        'IS' => 'Iceland',
        'IND' => 'India',
        'IRL' => 'Ireland',
        'I' => 'Italy',
        'J' => 'Japan',
        'LV' => 'Latvia',
        'FL' => 'Liechtenstein',
        'LT' => 'Lithuania',
        'L' => 'Luxembourg',
        'NL' => 'Netherlands',
        'N' => 'Norway',
        'PL' => 'Polska',
        'P' => 'Portugal',
        'RO' => 'Romania',
        'RUS' => 'Russian Federation',
        'SK' => 'Slovakia (Slovak Republic)',
        'SLO' => 'Slovenia',
        'E' => 'Spain',
        'S' => 'Sweden',
        'CH' => 'Switzerland',
        'TR' => 'Turkey',
        'UA' => 'Ukraine',
        'UK' => 'United Kingdom',
        'USA' => 'United States',
    );

    /*
     * Zwraca kwotę dodatkowej opłaty przy wyborze przelewy24 na podstawie order_id
     * @param int
     * @return float
     *
     * */
    public function getExtrachargeAmount($order_id)
    {
        $order_id = (int) $order_id;
        $order = $this->objectManager->create('Magento\Sales\Model\Order')->loadByIncrementId($order_id);
        $amount = number_format($order->getGrandTotal() * 100, 0, "", "");
        return self::getExtrachargeAmountByAmount($amount);
    }

    /*
     * Zwraca kwotę dodatkowej opłaty przy wyborze przelewy24 na podstawie kwoty
     * @param int
     * @return float
     *
     * */
    public function getExtrachargeAmountByAmount($amount)
    {
        $amount = round($amount);
        $extracharge_amount = 0;

        if (
            $this->scopeConfig->getValue(Data::XML_PATH_EXTRACHARGE, \Magento\Store\Model\ScopeInterface::SCOPE_STORE,  $this->storeId) == 1 &&
            $amount > 0 &&
            ((float)$this->scopeConfig->getValue(Data::XML_PATH_EXTRACHARGE_PRODUCT, \Magento\Store\Model\ScopeInterface::SCOPE_STORE,  $this->storeId) > 0 ||
                (float)$this->scopeConfig->getValue(Data::XML_PATH_EXTRACHARGE_PERCENT, \Magento\Store\Model\ScopeInterface::SCOPE_STORE,  $this->storeId) > 0 )
        ) {

            $inc_amount_settings = (float)$this->scopeConfig->getValue(Data::XML_PATH_EXTRACHARGE_AMOUNT, \Magento\Store\Model\ScopeInterface::SCOPE_STORE,  $this->storeId);
            $inc_percent_settings = (float)$this->scopeConfig->getValue(Data::XML_PATH_EXTRACHARGE_PERCENT, \Magento\Store\Model\ScopeInterface::SCOPE_STORE,  $this->storeId);

            $inc_amount = round($inc_amount_settings > 0 ? $inc_amount_settings * 100 : 0);
            $inc_percent = round($inc_percent_settings > 0 ? $inc_percent_settings / 100 * $amount : 0);

            $extracharge_amount = max($inc_amount, $inc_percent);
        }
        return $extracharge_amount;
    }

    /*
     * Dodaje do zamówienia produkt wirtualny, extracharge
     * @param int
     * @return void
     *
     * */
    public function addExtracharge($order_id)
    {
        $order_id = (int) $order_id;
        $extracharge_amount = self::getExtrachargeAmount($order_id);
        $extracharge_product = (int)$this->scopeConfig->getValue(Data::XML_PATH_EXTRACHARGE_PRODUCT, \Magento\Store\Model\ScopeInterface::SCOPE_STORE);

        if ($this->scopeConfig->getValue(Data::XML_PATH_EXTRACHARGE, \Magento\Store\Model\ScopeInterface::SCOPE_STORE) == 1 && $extracharge_amount > 0 && $extracharge_product > 0) {

            $order = $this->objectManager->create('Magento\Sales\Model\Order')->loadByIncrementId($order_id);
            $product = $this->objectManager->create('Magento\Catalog\Model\Product')->load($extracharge_product);

            $foundExtracharge = false;
            foreach ($order->getAllItems() as $item) {
                if ($item->getSku() == $product->getSku()) $foundExtracharge = true;
            }

            if (!$foundExtracharge) {
                try {
                    $rowTotal = $extracharge_amount / 100;

                    $qty = 1;
                    $orderItem = $this->objectManager->create('Magento\Sales\Model\Order\Item')
                        ->setStoreId($order->getStore()->getStoreId())
                        ->setQuoteItemId(NULL)
                        ->setQuoteParentItemId(NULL)
                        ->setProductId($product->getId())
                        ->setProductType($product->getTypeId())
                        ->setQtyBackordered(NULL)
                        ->setTotalQtyOrdered($qty)
                        ->setQtyOrdered($qty)
                        ->setName($product->getName())
                        ->setSku($product->getSku())
                        ->setPrice($rowTotal)
                        ->setBasePrice($rowTotal)
                        ->setOriginalPrice($rowTotal)
                        ->setRowTotal($rowTotal)
                        ->setBaseRowTotal($rowTotal)
                        ->setOrder($order);
                    $orderItem->save();

                    //  $quote = $this->objectManager->create('Magento\Quote\Model\QuoteRepository')->addFieldToFilter("entity_id", $order->getQuoteId())->getFirstItem();

                    $order->setSubtotal($rowTotal + $order->getSubtotal())
                        ->setBaseSubtotal($rowTotal + $order->getBaseSubtotal())
                        ->setGrandTotal($rowTotal + $order->getGrandTotal())
                        ->setBaseGrandTotal($rowTotal + $order->getBaseGrandTotal());


                    // $quote->save();
                    $order->save();
                } catch (\Exception $e) {
                    $this->logger->debug(array(__METHOD__ . ' ' . $e->getMessage()));
                }
            }
        }
    }

    public function getBlock()
    {
        return $this->objectManager->create('Dialcom\Przelewy\Block\Form\Przelewy');
    }


    /**
     * @param $order_id
     */
    public function addZenCardDiscount($order_id)
    {
        $order_id = (int) $order_id;
        $sku = 'zenCardCoupon';
        $order = $this->objectManager->create('Magento\Sales\Model\Order')->loadByIncrementId($order_id);
        $product = $this->getVirtualProduct($sku, $order);

        if ($product) {
            $foundZenCardCoupon = false;
            foreach ($order->getAllItems() as $item) {
                if ($item->getSku() == $product->getSku()) $foundZenCardCoupon = true;
            }

            if (!$foundZenCardCoupon) {
                try {
                    $discount = $this->getZenCardDiscount($order);
                    $this->updateOrder($order, $product, $discount);
                } catch (\Exception $e) {
                    $this->logger->debug(array(__METHOD__ . ' ' . $e->getMessage()));
                }
            }
        }
    }

    /**
     * @param $sku
     * @param $order
     * @return mixed
     */
    public function getVirtualProduct($sku, $order)
    {
        $product = $this->objectManager->create('Magento\Catalog\Model\Product');
        $id = $product->getIdBySku($sku);

        if (!$id) {
            try {
                $product
                    ->setStoreId($order->getStore()->getStoreId())
                    ->setWebsiteIds(array(1))
                    ->setAttributeSetId(4)
                    ->setTypeId('virtual')
                    ->setCreatedAt(strtotime('now'))
                    ->setUpdatedAt(strtotime('now'))
                    ->setSku($sku)
                    ->setName('Rabat ZenCard')
                    ->setStatus(1)
                    ->setTaxClassId(0)
                    ->setVisibility(\Magento\Catalog\Model\Product\Visibility::VISIBILITY_BOTH)
                    ->setPrice(1)
                    ->setDescription('Rabat ZenCard')
                    ->setShortDescription('Rabat ZenCard');

                $product->save();
            } catch (\Exception $ex) {
                $this->logger->debug(array(__METHOD__ . ' ' . $ex->getMessage()));
            }
        } else {
            $product = $this->objectManager->create('Magento\Catalog\Model\Product')->load($id);
        }

        return $product;
    }


    /**
     * @param $order
     * @param $product
     * @param $discount
     */
    private function updateOrder($order, $product, $discount)
    {
        $qty = 1;
        $orderItem = $this->objectManager->create('Magento\Sales\Model\Order\Item')
            ->setStoreId($order->getStore()->getStoreId())
            ->setQuoteItemId(NULL)
            ->setQuoteParentItemId(NULL)
            ->setProductId($product->getId())
            ->setProductType($product->getTypeId())
            ->setQtyBackordered(NULL)
            ->setTotalQtyOrdered($qty)
            ->setQtyOrdered($qty)
            ->setName($product->getName())
            ->setSku($product->getSku())
            ->setPrice(-$discount)
            ->setBasePrice(-$discount)
            ->setOriginalPrice(-$discount)
            ->setRowTotal(-$discount)
            ->setBaseRowTotal(-$discount)
            ->setOrder($order);
        $orderItem->save();

        //$order->setSubtotal($order->getSubtotal() - $discount)
        //    ->setBaseSubtotal($order->getBaseSubtotal() - $discount)
        //    ->setGrandTotal($order->getGrandTotal() - $discount)
        //    ->setBaseGrandTotal($order->getBaseGrandTotal() - $discount);
        //$order->save();
    }


    /**
     * @param $order
     * @return float
     */
    private function getZenCardDiscount($order)
    {
        $result = 0.00;
        $merchantId = (int) $this->scopeConfig->getValue(Data::XML_PATH_MERCHANT_ID, \Magento\Store\Model\ScopeInterface::SCOPE_STORE);
        $apiKey = $this->scopeConfig->getValue(Data::XML_PATH_API_KEY, \Magento\Store\Model\ScopeInterface::SCOPE_STORE);
        $zenCardApi = new ZenCardApi($merchantId, $apiKey);

        try {
            if ($this->customerSession->isLoggedIn()) {
                $customer = $this->customerSession->getCustomer();
                $email = $customer->getEmail();
            } else {
                $milliseconds = round(microtime(true) * 1000);
                $email = 'przelewy_' . $milliseconds . '@zencard.pl';
            }

            $amount = $order->getSubtotal() * 100;

            $storeUrl = $this->_storeManager->getStore()->getBaseUrl();
            $orderId = (int) $order->getIncrementId();
            $zenCardOrderId = $zenCardApi->buildZenCardOrderId($orderId, $storeUrl);
            $transaction = $zenCardApi->verify($email, $amount, $zenCardOrderId);

            if ($transaction && $transaction->isVerified() && $transaction->hasDiscount()) {
                $result = $transaction->getDiscountAmountFloat();
            }
        } catch (\Exception $ex) {
            $this->logger->debug(array(__METHOD__ . ' ' . $ex->getMessage()));
        }

        return $result;
    }

    /**
     * @param $order
     * @return bool
     */
    public function confirmZenCardDiscount($order)
    {
        $result = false;
        $productDiscount = 0;
        $merchantId = (int) $this->scopeConfig->getValue(Data::XML_PATH_MERCHANT_ID, \Magento\Store\Model\ScopeInterface::SCOPE_STORE);
        $apiKey = $this->scopeConfig->getValue(Data::XML_PATH_API_KEY, \Magento\Store\Model\ScopeInterface::SCOPE_STORE);
        $zenCardApi = new ZenCardApi($merchantId, $apiKey);

        try {
           $amount = ($order->getSubtotal() * 100) + $productDiscount;
            $storeUrl = $this->_storeManager->getStore()->getBaseUrl();
            $orderId = (int) $order->getIncrementId();
            $zenCardOrderId = $zenCardApi->buildZenCardOrderId($orderId, $storeUrl);
            $confirm = $zenCardApi->confirm($zenCardOrderId, $amount);

            if ($confirm->isVerified() && $confirm->hasDiscount() && $confirm->isConfirmed()) {
                $order->addStatusHistoryComment($confirm->getInfo());
                $order->save();
                $result = $confirm->isConfirmed();
            }
        } catch (\Exception $e) {
            file_put_contents(__DIR__ . '/confirmZCD.log',
	        date('Y-m-d H:i:s') . ' end: $classMethods: ' . print_r([ $e->getMessage() ],true),
                FILE_APPEND
            );
        }

file_put_contents(__DIR__ . '/confirmZCD.log',
	        date('Y-m-d H:i:s') . ' end: $classMethods: ' . print_r([ get_class_methods($order) ],true),
                FILE_APPEND
            );

        return $result;
    }
}
