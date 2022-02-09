<?php

class Paycorp_Payments_Model_Source_TransactionType
{
    public function toOptionArray()
    {
        return array(
            array(
                'value' => 'AUTHORISATION',
                'label' => Mage::helper('paycorp')->__('Authorization')
            ),
            array(
                'value' => 'PURCHASE',
                'label' => Mage::helper('paycorp')->__('Purchase')
            ),
        );
    }
}
