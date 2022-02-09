<?php

class Paycorp_Payments_Block_Form_CC extends Mage_Payment_Block_Form
{

    protected function _construct()
    {
        parent::_construct();
        $this->setTemplate('paycorp/cc/form.phtml');
    }
}
