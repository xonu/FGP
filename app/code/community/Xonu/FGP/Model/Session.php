<?php
/**
 * @copyright (c) 2013, Pawel Kazakow <support@xonu.de>
 * @license http://xonu.de/license/ xonu.de EULA
 */

class Xonu_FGP_Model_Session extends Mage_Checkout_Model_Session {
    // implements hasQuote() method for Magento versions older than 1.6
    public function hasQuote()
    {
        return isset($this->_quote);
    }
}