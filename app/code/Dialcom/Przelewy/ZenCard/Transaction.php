<?php

namespace Dialcom\Przelewy\ZenCard;

class Transaction
{
    /**
     * @var string
     */
    private $userEmail;

    /**
     * @var bool
     */
    private $verified;

    /**
     * @var bool
     */
    private $confirmed;

    /**
     * @var bool
     */
    private $discount;

    /**
     * @var string
     */
    private $info;

    /**
     * @var int
     */
    private $amount;

    /**
     * @var int
     */
    private $amountWithDiscount;

    /**
     * Transaction constructor.
     * @param \stdClass $data
     */
    public function __construct(\stdClass $data)
    {
        $this->setData($data);
    }

    /**
     * @return string
     */
    public function getUserEmail()
    {
        return $this->userEmail;
    }

    /**
     * @return boolean
     */
    public function isVerified()
    {
        return $this->verified;
    }

    /**
     * @return boolean
     */
    public function isConfirmed()
    {
        return $this->confirmed;
    }

    /**
     * @return boolean
     */
    public function hasDiscount()
    {
        return $this->discount;
    }

    /**
     * @return string
     */
    public function getInfo()
    {
        if (empty($this->info) && $this->isConfirmed()) {
            $this->info = __('Used a ZenCard coupon of value:').' '. $this->getDiscountAmountFloat();
        }

        return $this->info;
    }

    /**
     * @return int
     */
    public function getAmount()
    {
        return $this->amount;
    }

    /**
     * @return int
     */
    public function getAmountWithDiscount()
    {
        return $this->amountWithDiscount;
    }

    /**
     * @return float
     */
    public function getDiscountAmountFloat()
    {
        return ($this->getAmount() - $this->getAmountWithDiscount()) / 100;
    }

    /**
     * @return float
     */
    public function getDiscountAmountNegative()
    {
        return -($this->getDiscountAmountFloat());
    }

    /**
     * @param \stdClass $data
     */
    private function setData(\stdClass $data)
    {
        $this->userEmail = (isset($data->_identity) && isset($data->_identity->_user)) ? (string)$data->_identity->_user : '';
        $this->verified = isset($data->_verified) ? (bool)$data->_verified : false;
        $this->confirmed = isset($data->_confirmed) ? (bool)$data->_confirmed : false;
        $this->discount = (isset($data->_discount) && $data->_discount instanceof \stdClass) ? (bool)$data->_discount : false;
        $this->amount = isset($data->_amount) ? (int)$data->_amount : 0;
        $this->amountWithDiscount = isset($data->_amountWithDiscount) ? (int)$data->_amountWithDiscount : 0;
        $this->info = isset($data->_info) && isset($data->_info->data) && isset($data->_info->data->info) ? (string)$data->_info->data->info : '';
    }
}
