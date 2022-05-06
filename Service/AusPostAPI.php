<?php
namespace Elogic\AusPost\Service;

use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Setup\Exception;
use GuzzleHttp\Client;

class AusPostAPI
{
    /**
     * @var string
     */
    const URL_PREFIX = "digitalapi.auspost.com.au";

    /**
     * @var string
     */
    const PRICE_CALCULATE_SERVICE_CODE = "AUS_PARCEL_REGULAR";

    /**
     * @var string
     */
    const CALCULATE_LOCAL_PRICE_ROUTE = "/postage/parcel/domestic/calculate.json";

    /**
     * @var string
     */
    const FETCH_INTERNATIONAL_SERVICES_ROUTE = "/postage/parcel/international/service.json";

    /**
     * @var string
     */
    const CALCULATE_INTERNATIONAL_PRICE_ROUTE = "/postage/parcel/international/calculate.json";

    /**
     * @var ScopeConfigInterface
     */
    private $scopeConfig;

    /**
     * @var Client
     */
    private $httpClient;

    public function __construct(
        ScopeConfigInterface $scopeConfig,
        Client $httpClient
    )
    {
        $this->httpClient = $httpClient;
        $this->scopeConfig = $scopeConfig;
    }

    /**
     * Make an GET api call
     *
     * @param string $route
     * @return mixed
     * @throws Exception
     */
    public function sendCalculationGETRequest(string $route, $params = [])
    {
        $fullLink = "https://" . self::URL_PREFIX . $route;
        $querySettings = [
            'headers' => [
                'AUTH-KEY' => $this->getAPIKey()
            ],

            'query' => $params
        ];

        $response = $this->httpClient->request(
            'GET',
            $fullLink,
            $querySettings
        );

        $responseContent = $response->getBody()->getContents();

        if(!$responseContent) {
            throw new \Exception("AusPost is not available");
        }

        return \Safe\json_decode($responseContent, true);
    }

    /**
     * Get API key
     *
     * @return string|null
     */
    private function getAPIKey()
    {
        return $this->scopeConfig->getValue('carriers/auspost/calculation_api_key');
    }
}
