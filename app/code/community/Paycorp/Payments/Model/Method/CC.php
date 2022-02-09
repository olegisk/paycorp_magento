<?php

class Paycorp_Payments_Model_Method_CC extends Mage_Payment_Model_Method_Abstract
{
    /**
     * Payment Method Code
     */
    const METHOD_CODE = 'paycorp_cc';

    /**
     * Payment method code
     */
    public $_code = self::METHOD_CODE;

    /**
     * Availability options
     */
    protected $_isGateway = true;
    protected $_canOrder = true;
    protected $_canAuthorize = false;
    protected $_canCapture = false;
    protected $_canCapturePartial = false;
    protected $_canRefund = true;
    protected $_canRefundInvoicePartial = true;
    protected $_canVoid = true;
    protected $_canUseInternal = true;
    protected $_canUseCheckout = true;
    protected $_canUseForMultishipping = false;
    protected $_isInitializeNeeded = false;
    protected $_canFetchTransactionInfo = false;
    protected $_canSaveCc = false;

    /**
     * Payment method blocks
     */
    protected $_infoBlockType = 'paycorp/info_CC';
    protected $_formBlockType = 'paycorp/form_CC';

    /**
     * Get initialized flag status
     * @return true
     */
    public function isInitializeNeeded()
    {
        return false;
    }

    /**
     * Get the redirect url
     * @return string
     */
    public function getOrderPlaceRedirectUrl()
    {
        return Mage::getUrl('paycorp/payment/redirect', array('_secure' => true));
    }

    /**
     * Get config action to process initialization
     * @return string
     */
    public function getConfigPaymentAction()
    {
        $paymentAction = $this->getConfigData('payment_action');
        return empty($paymentAction) ? true : $paymentAction;
    }

    public function getCheckout()
    {
        return Mage::getSingleton('checkout/session');
    }

    public function getQuote()
    {
        return $this->getCheckout()->getQuote();
    }
}
