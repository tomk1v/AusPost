<?php
namespace Elogic\AusPost\Helper;

use Magento\Framework\App\Helper\AbstractHelper;
use Magento\Framework\App\Helper\Context;
use Elogic\AusPost\Service\AusPostAPI;

class ShippingCostHelper extends AbstractHelper
{
    /**
     * @var \Elogic\AusPost\Service\AusPostAPI
     */
    private $api;

    /**
     * @param Context $context
     * @param AusPostAPI $api
     */
    public function __construct(
        Context $context,
        AusPostAPI $api
    )
    {
        $this->api = $api;
        parent::__construct($context);
    }

    /**
     * Calculate local shipping cost
     *
     * @param array $orderInformation
     * @param string $customerPostcode
     * @return float
     */
    public function calculateTotalLocalShippingCost(
        array $orderInformation,
        string $customerPostcode
    )
    {
        $sellerPostcodes = array_keys($orderInformation);
        $shippingCost = 0;

        foreach ($sellerPostcodes as $sellerPostcode) {
            $weight = $orderInformation[$sellerPostcode];
            $shippingCost += $this->getLocalPrice($weight, $sellerPostcode, $customerPostcode);
        }

        return $shippingCost;
    }

    /**
     * Calculate international shipping cost
     *
     * @param array $orderInformation
     * @param string $countryCode
     * @param string $serviceCode
     * @return float|int
     */
    public function calculateTotalInternationalShippingCost(
        array $orderInformation,
        string $countryCode,
        string $serviceCode
    )
    {
        $shippingCost = 0;
        $summrayWeightsFromSellers = array_values($orderInformation);

        foreach ($summrayWeightsFromSellers as $weight) {
            $shippingCost += $this->getInternationalPrice($weight, $countryCode, $serviceCode);
        }

        return $shippingCost;
    }

    /**
     * Get available shipping services for international shipping
     *
     * @param $weight
     * @param $countryCode
     * @return mixed
     * @throws \Magento\Setup\Exception
     */
    public function getInternationalShippingServices($weight, $countryCode)
    {
        $queryParams = [
            "country_code" => $countryCode,
            "weight" => $weight
        ];

        return $this->api->sendCalculationGETRequest(AusPostAPI::FETCH_INTERNATIONAL_SERVICES_ROUTE, $queryParams);
    }

    /**
     * Get price for shipping in Australia from API
     *
     * @param float $weight
     * @param string $postcodeFrom
     * @param string $postcodeTo
     * @return float
     * @throws \Magento\Setup\Exception
     */
    private function getLocalPrice(float $weight, string $postcodeFrom, string $postcodeTo) : float
    {
        $queryParams = [
            "from_postcode" => $postcodeFrom,
            "to_postcode" => $postcodeTo,
            "length" => 0,
            "height" => 0,
            "width" => 0,
            "weight" => $weight,
            "service_code" => AusPostAPI::PRICE_CALCULATE_SERVICE_CODE
        ];

        $result = $this->api->sendCalculationGETRequest(AusPostAPI::CALCULATE_LOCAL_PRICE_ROUTE, $queryParams);
        return $result['postage_result']['costs']['cost']['cost'];
    }

    /**
     * Calculate price for international delivery from API
     *
     * @param float $weight
     * @param string $countryCode
     * @param string $shippingServiceCode
     * @return float
     * @throws \Magento\Setup\Exception
     */
    private function getInternationalPrice(float $weight, string $countryCode, string $shippingServiceCode)
    {
        $queryParams = array(
            "country_code" => $countryCode,
            "weight" => $weight,
            "service_code" => $shippingServiceCode
        );

        $queryResult = $this->api->sendCalculationGETRequest(AusPostAPI::CALCULATE_INTERNATIONAL_PRICE_ROUTE, $queryParams);

        return (float) $queryResult['postage_result']['total_cost'];
    }
}
