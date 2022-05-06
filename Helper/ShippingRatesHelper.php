<?php
namespace Elogic\AusPost\Helper;

use Magento\Framework\App\Helper\AbstractHelper;
use Magento\Framework\App\Helper\Context;
use Magento\Quote\Model\Quote\Address\RateResult\MethodFactory;
use Elogic\AusPost\Helper\ShippingCostHelper;
use Elogic\AusPost\Helper\OrderDataHelper;

class ShippingRatesHelper extends AbstractHelper
{
    /**
     * @var ShippingCostHelper
     */
    private $shippingCostHelper;

    /**
     * @var OrderDataHelper
     */
    private $orderDataHelper;

    /**
     * @var MethodFactory
     */
    private $rateMethodFactory;

    /**
     * @var string
     */
    private $carrierTitle = "Australian Post";

    /**
     * @var string
     */
    private $methodTitle = "Australian Post";

    /**
     * @var string
     */
    private $methodCode = "auspost";

    /**
     * @param Context $context
     * @param ShippingCostHelper $shippingCostHelper
     * @param OrderDataHelper $orderDataHelper
     * @param MethodFactory $rateMethodFactory
     */
    public function __construct(
        Context $context,
        ShippingCostHelper $shippingCostHelper,
        OrderDataHelper $orderDataHelper,
        MethodFactory $rateMethodFactory
    )
    {
        $this->shippingCostHelper = $shippingCostHelper;
        $this->orderDataHelper = $orderDataHelper;
        $this->rateMethodFactory = $rateMethodFactory;
        parent::__construct($context);
    }

    /**
     * Get list of available rates.
     *
     * @param string $carrierTitle
     * @param string $methodTitle
     * @param string $methodCode
     * @return array|\Magento\Quote\Model\Quote\Address\RateResult\Method[]
     * @throws \Magento\Framework\Exception\LocalizedException
     * @throws \Magento\Framework\Exception\NoSuchEntityException
     * @throws \Magento\Setup\Exception
     */
    public function getRates(string $carrierTitle, string $methodTitle, string $methodCode)
    {
        $this->carrierTitle = $carrierTitle;
        $this->methodTitle = $methodTitle;
        $this->methodCode = $methodCode;

        $orderParams = $this->orderDataHelper->getOrderParams();
        $totalOrderWeight = $this->orderDataHelper->getTotalWeight();
        $destinationPostcode = $this->orderDataHelper->toPostCode();
        $destinationCountryCode = $this->orderDataHelper->getCountryCode();

        if($this->orderDataHelper->isShippingCountryAU()) {
            return $this->getLocalRates($orderParams, $destinationPostcode);
        } else {
            return $this->getInternationalRates($orderParams, $totalOrderWeight, $destinationCountryCode);
        }
    }

    public function getRatesFromShippingDetails($detail)
    {
        $weight = $detail['items_weight'];
        $originPostcode = $detail['origin_postcode'];
        $destinationCountry = $this->orderDataHelper->getCountryCode();

        $orderParams = [
            $originPostcode => $weight
        ];

        if($this->orderDataHelper->isShippingCountryAU($destinationCountry)) {
            $destinationPostcode = $this->orderDataHelper->toPostCode();
            return $this->getLocalRates($orderParams, $destinationPostcode);
        } else {
            return $this->getInternationalRates($orderParams, $weight, $destinationCountry);
        }
    }

    /**
     * Get array of shipping methods for local delivery.
     *
     * @param array $orderParams
     * @param string $destinationPostcode
     * @return \Magento\Quote\Model\Quote\Address\RateResult\Method[]
     */
    private function getLocalRates(array $orderParams, string $destinationPostcode)
    {
        $shippingCost = $this->shippingCostHelper->calculateTotalLocalShippingCost($orderParams, $destinationPostcode);

        /** @var \Magento\Quote\Model\Quote\Address\RateResult\Method $method */
        $method = $this->createShippingMethod($this->methodCode, $this->methodCode, $this->methodTitle, $this->carrierTitle, $shippingCost);

        return [$method];
    }

    /**
     * Get array of shipping methods for international delivery.
     *
     * @param array $orderParams
     * @param float $totalOrderWeight
     * @param string $destinationCountryCode
     * @return array
     * @throws \Magento\Setup\Exception
     */
    private function getInternationalRates(array $orderParams, float $totalOrderWeight, string $destinationCountryCode)
    {
        $internationalShippingMethods = $this->shippingCostHelper->getInternationalShippingServices($totalOrderWeight, $destinationCountryCode);
        $methods = [];

        foreach ($internationalShippingMethods['services']['service'] as $service) {
            $serviceOptions = $this->getInternationalShippingOptions($service);

            $methodCode = $this->methodCode . "_" . $service['code'];
            $carrierTitle = $this->methodTitle . " | " . $service['name'] . " (" . $serviceOptions . ")";
            $shippingCost = $this->shippingCostHelper->calculateTotalInternationalShippingCost($orderParams, $destinationCountryCode, $service['code']);

            /** @var \Magento\Quote\Model\Quote\Address\RateResult\Method $method */
            $method = $this->createShippingMethod($this->methodCode, $methodCode, $carrierTitle, $carrierTitle, $shippingCost);

            $methods[] = $method;
        }

        return $methods;
    }

    /**
     * Get string with all included option in shipment service
     *
     * @param array $service
     * @return false|string
     */
    private function getInternationalShippingOptions(array $service)
    {
        $resultString = '';

        foreach ($service['options']['option'] as $option) {
            $resultString .= " " . $option['name'] .",";
        }

        // remove whitespaces and last ',' character
        return substr(trim($resultString), 0, -1);
    }

    /**
     * Create rateMethod with given parameters.
     *
     * @param string $carrierCode
     * @param string $methodCode
     * @param string $methodTitle
     * @param string $carrierTitle
     * @param float $cost
     * @return \Magento\Quote\Model\Quote\Address\RateResult\Method
     */
    private function createShippingMethod(string $carrierCode, string $methodCode, string $methodTitle, string $carrierTitle, float $cost)
    {
        /** @var \Magento\Quote\Model\Quote\Address\RateResult\Method $method */
        $method = $this->rateMethodFactory->create();

        $method->setCarrier($carrierCode);
        $method->setMethod($methodCode);
        $method->setMethodTitle($methodTitle);
        $method->setCarrierTitle($carrierTitle);
        $method->setCost($cost);
        $method->setPrice($cost);

        return $method;
    }
}
