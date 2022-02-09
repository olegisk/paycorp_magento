<?php

class Paycorp_Payments_Block_Info_CC extends Mage_Payment_Block_Info
{
    protected function _construct()
    {
        parent::_construct();

        $this->setTemplate('paycorp/cc/info.phtml');

        // Template for Checkout page
        if ($this->getRequest()->getRequestedActionName() === 'progress') {
            $this->setTemplate('paycorp/cc/title.phtml');
        }
    }

    /**
     * Returns code of payment method
     *
     * @return string
     */
    public function getMethodCode()
    {
        return $this->getInfo()->getMethodInstance()->getCode();
    }
}
