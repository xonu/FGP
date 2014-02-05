<?php
/**
 * @copyright (c) 2013, Pawel Kazakow <support@xonu.de>
 * @license http://xonu.de/license/ xonu.de EULA
 */
class Xonu_FGP_Model_Calculation extends Mage_Tax_Model_Calculation
{
    protected $adminSession = false;

    // set origin to destination
	public function getRateOriginRequest($store = null)
	{
        if (Mage::app()->getStore()->isAdmin() || Mage::getDesign()->getArea() == 'adminhtml') {
            $this->adminSession = true; // creating order in the backend
        }

        $session = $this->getSession();
		if($session->hasQuote() || $this->adminSession) // getQuote() would lead to infinite loop here when switching currency
		{
			$quote = $session->getQuote();
			if($quote->getIsActive() || $this->adminSession)
			{
                // use destination of the existing quote as origin if quote exists
				$request = $this->getRateRequest(
						$quote->getShippingAddress(),
						$quote->getBillingAddress(),
						$quote->getCustomerTaxClassId(),
						$store
				);

				return $request;
			}
            if (Mage::getSingleton('customer/session')->isLoggedIn()) {

                $customer = Mage::getSingleton('customer/session')->getCustomer();
                if (($billingAddress = $customer->getDefaultBillingAddress())
                    && ($shippingAddress = $customer->getDefaultShippingAddress())) {

                    // use destination of the existing quote as origin if quote exists
                    $request = $this->getRateRequest(
                        $shippingAddress,
                        $billingAddress,
                        $customer->getTaxClassId(),
                        $store
                    );

                    return $request;
                }
            }

            return $this->getDefaultDestination();
		}
		else // quote is not available when switching the currency
		{
			return $this->getDefaultDestination();
		}
	}

	public function getSession()
    {
        if ($this->adminSession) {
            return Mage::getSingleton('adminhtml/session_quote'); // order creation in the backend
        } else {
			return Mage::getSingleton('checkout/session'); // default order creation in the frontend
		}
    }

    // sometimes it is required to use shipping address from the quote instead of the default address
	public function getRateRequest(
			$shippingAddress = null,
			$billingAddress = null,
			$customerTaxClass = null,
			$store = null)
	{
		if ($shippingAddress === false && $billingAddress === false && $customerTaxClass === false) {
			return $this->getRateOriginRequest($store);
		}
		$address    = new Varien_Object();
		$customer   = $this->getCustomer();
		$basedOn    = Mage::getStoreConfig(Mage_Tax_Model_Config::CONFIG_XML_PATH_BASED_ON, $store);

		if (($shippingAddress === false && $basedOn == 'shipping')
				|| ($billingAddress === false && $basedOn == 'billing')) {
			$basedOn = 'default';
		} else {
			if ((($billingAddress === false || is_null($billingAddress) || !$billingAddress->getCountryId())
					&& $basedOn == 'billing')
					|| (($shippingAddress === false || is_null($shippingAddress) || !$shippingAddress->getCountryId())
							&& $basedOn == 'shipping')
			){
				$session = Mage::getSingleton('checkout/session');
				if ($customer) {
					$defBilling = $customer->getDefaultBillingAddress();
					$defShipping = $customer->getDefaultShippingAddress();

					if ($basedOn == 'billing' && $defBilling && $defBilling->getCountryId()) {
						$billingAddress = $defBilling;
					} else if ($basedOn == 'shipping' && $defShipping && $defShipping->getCountryId()) {
						$shippingAddress = $defShipping;
					} else {
						if($session->hasQuote() || $this->adminSession)
						{
							$quote = $session->getQuote();
							$isActive = $quote->getIsActive();
							if($isActive)
							{
								$shippingAddress = $quote->getShippingAddress();
								$billingAddress = $quote->getBillingAddress();
							}
							else{
								$basedOn = 'default';
							}
						}
						else{
							$basedOn = 'default';
						}
					}
				} else {

					if($session->hasQuote() || $this->adminSession)
					{
						$quote = $session->getQuote();
						$isActive = $quote->getIsActive();
						if($isActive)
						{
							$shippingAddress = $quote->getShippingAddress();
							$billingAddress = $quote->getBillingAddress();
						}
						else {
							$basedOn = 'default';
						}
					}
					else {
						$basedOn = 'default';
					}
				}
			}
		}
		switch ($basedOn) {
			case 'billing':
				$address = $billingAddress;
				break;
			case 'shipping':
				$address = $shippingAddress;
				break;
			case 'origin':
				$address = $this->getRateOriginRequest($store);
				break;
			case 'default':
				$address
					->setCountryId(Mage::getStoreConfig(Mage_Tax_Model_Config::CONFIG_XML_PATH_DEFAULT_COUNTRY, $store))
					->setRegionId(Mage::getStoreConfig(Mage_Tax_Model_Config::CONFIG_XML_PATH_DEFAULT_REGION, $store))
					->setPostcode(Mage::getStoreConfig(Mage_Tax_Model_Config::CONFIG_XML_PATH_DEFAULT_POSTCODE, $store));
				break;
		}

		if (is_null($customerTaxClass) && $customer) {
			$customerTaxClass = $customer->getTaxClassId();
		} elseif (($customerTaxClass === false) || !$customer) {
			$customerTaxClass = $this->getDefaultCustomerTaxClass($store);
		}

		$request = new Varien_Object();
		$request
			->setCountryId($address->getCountryId())
			->setRegionId($address->getRegionId())
			->setPostcode($address->getPostcode())
			->setStore($store)
			->setCustomerClassId($customerTaxClass);
		return $request;
	}


	private function getDefaultDestination($store = null)
	{
        $address = new Varien_Object();
		$request = new Varien_Object();

		$address
			->setCountryId(Mage::getStoreConfig(Mage_Tax_Model_Config::CONFIG_XML_PATH_DEFAULT_COUNTRY, $store))
			->setRegionId(Mage::getStoreConfig(Mage_Tax_Model_Config::CONFIG_XML_PATH_DEFAULT_REGION, $store))
			->setPostcode(Mage::getStoreConfig(Mage_Tax_Model_Config::CONFIG_XML_PATH_DEFAULT_POSTCODE, $store));

		$customerTaxClass = null;
		$customer = $this->getCustomer();

		if (is_null($customerTaxClass) && $customer) {
			$customerTaxClass = $customer->getTaxClassId();
		} elseif (($customerTaxClass === false) || !$customer) {
			$customerTaxClass = $this->getDefaultCustomerTaxClass($store);
		}

		$request
			->setCountryId($address->getCountryId())
			->setRegionId($address->getRegionId())
			->setPostcode($address->getPostcode())
			->setStore($store)
			->setCustomerClassId($customerTaxClass);


		return $request;
	}
}
