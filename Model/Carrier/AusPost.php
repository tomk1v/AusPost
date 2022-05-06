<?php

namespace Elogic\AusPost\Model\Carrier;

use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Quote\Model\Quote\Address\RateRequest;
use Magento\Quote\Model\Quote\Address\RateResult\ErrorFactory;
use Magento\Shipping\Model\Carrier\AbstractCarrier;
use Magento\Shipping\Model\Carrier\CarrierInterface;
use Magento\Shipping\Model\Rate\ResultFactory;
use Psr\Log\LoggerInterface;
use Elogic\AusPost\Helper\ShippingRatesHelper;

/**
 * Custom shipping model
 */
class AusPost extends AbstractCarrier implements CarrierInterface
{
    /**
     * @var string
     */
    protected $_code = 'auspost';

    /**
     * @var bool
     */
    protected $_isFixed = true;

    /**
     * @var ResultFactory
     */
    private $rateResultFactory;

    /**
     * @var ShippingRatesHelper
     */
    private $shippingRatesHelper;


    /**
     * @param ScopeConfigInterface $scopeConfig
     * @param ErrorFactory $rateErrorFactory
     * @param LoggerInterface $logger
     * @param ResultFactory $rateResultFactory
     * @param ShippingRatesHelper $shippingRatesHelper
     * @param array $data
     */
    public function __construct(
        ScopeConfigInterface        $scopeConfig,
        ErrorFactory                $rateErrorFactory,
        LoggerInterface             $logger,
        ResultFactory               $rateResultFactory,
        ShippingRatesHelper         $shippingRatesHelper,
        array                       $data = []
    )
    {
        parent::__construct($scopeConfig, $rateErrorFactory, $logger, $data);
        $this->rateResultFactory = $rateResultFactory;
        $this->shippingRatesHelper = $shippingRatesHelper;
    }

    /**
     * Custom Shipping Rates Collector
     *
     * @param RateRequest $request
     * @return \Magento\Shipping\Model\Rate\Result|bool
     */
    public function collectRates(RateRequest $request)
    {
        if (!$this->getConfigFlag('active')) {
            return false;
        }

        /** @var \Magento\Shipping\Model\Rate\Result $result */
        $result = $this->rateResultFactory->create();

        try {
            $carrierTitle = $this->getConfigData('title') != null ? $this->getConfigData('title') : ' ';
            $methodTitle = $this->getConfigData('name') != null ? $this->getConfigData('name') : ' ';

            $rates = $this->shippingRatesHelper->getRates($carrierTitle, $methodTitle, $this->_code);

            foreach ($rates as $rate) {
                $result->append($rate);
            }
        } catch (\Exception $exception) {
            return false;
        }

        return $result;
    }

    /**
     * @return array
     */
    public function getAllowedMethods()
    {
        return [$this->_code => $this->getConfigData('name')];
    }
}
