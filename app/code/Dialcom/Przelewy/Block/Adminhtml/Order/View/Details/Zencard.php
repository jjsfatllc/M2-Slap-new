<?php

namespace Dialcom\Przelewy\Block\Adminhtml\Order\View\Details;

use Dialcom\Przelewy\Helper\Data;
use Dialcom\Przelewy\Model\Payment\Przelewy;

class Zencard extends \Magento\Backend\Block\Template
{
    /**
     * @var \Magento\Framework\App\Config\ScopeConfigInterface
     */
    protected $scopeConfig;

    /**
     * @var \Magento\Framework\Registry
     */
    protected $registryObject;

    /**
     * @var \Magento\Framework\App\ObjectManager
     */
    protected $objectManager;

    /**
     * @var \Magento\Store\Model\StoreManagerInterface
     */
    protected $storeManager;

    private $order;

    /**
     * Refunds constructor.
     *
     * @param \Magento\Framework\Registry $registryObject
     * @param \Magento\Backend\Block\Template\Context $context
     * @param array $data
     */
    public function __construct(
        \Magento\Framework\Registry $registryObject,
        \Magento\Backend\Block\Template\Context $context,
        array $data = []
    )
    {
        parent::__construct( $context, $data );
        $this->scopeConfig = $context->getScopeConfig();
        $this->registryObject = $registryObject;
        $this->objectManager = \Magento\Framework\App\ObjectManager::getInstance();
        $this->storeManager = $context->getStoreManager();
        $this->order = $this->getOrder();
    }

    public function getZencardInfo()
    {
        $przelewy = $this->objectManager->create( 'Dialcom\Przelewy\Model\Payment\Przelewy' );
        $sku = 'zenCardCoupon';
        $order = $this->objectManager->create( 'Magento\Sales\Model\Order' )->loadByIncrementId(
            $this->order['entity_id']
        );
        $zencardProduct = $przelewy->getVirtualProduct( $sku, $order );

        foreach( $this->order->getAllItems() as $key => $product )
        {
            if( (int)$zencardProduct['entity_id'] == (int)$product['product_id'] )
            {
                return __( 'Client used ZenCard coupon: ' ) .
                '<strong>' .
                number_format( $product['price'], 2, ',', '.' ) .
                '</strong>' .
                ' ' .
                $this->_storeManager->getStore()->getCurrentCurrency()->getCode();
            }
        }

        return null;
    }

    public function getOrder()
    {
        $orderFromRegistry = $this->registryObject->registry( 'current_order' );
        if( !is_null( $orderFromRegistry ) )
        {
            $this->order = $orderFromRegistry;

            return $this->order;
        }

        return $this->order;
    }
}