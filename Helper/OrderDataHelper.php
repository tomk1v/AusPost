<?php
namespace Elogic\AusPost\Helper;

use Magento\Framework\App\Helper\AbstractHelper;
use Magento\Framework\App\Helper\Context;
use Magento\Checkout\Model\Session;
use Magento\Quote\Api\CartItemRepositoryInterface;
use Magento\Customer\Api\AddressRepositoryInterface;
use Magento\Framework\Api\SearchCriteriaBuilder;
use Magento\Setup\Exception;
use Webkul\Marketplace\Model\ResourceModel\Product\CollectionFactory as ProductCollectionFactory;

/**
 * This class provides all data about order.
 */
class OrderDataHelper extends AbstractHelper
{
    /**
     * @var Session
     */
    private $checkoutSession;

    /**
     * @var CartItemRepositoryInterface
     */
    private $cartItemRepository;

    /**
     * @var ProductCollectionFactory
     */
    private $productCollectionFactory;

    /**
     * @var SearchCriteriaBuilder
     */
    private $searchCriteriaBuilder;

    /**
     * @var AddressRepositoryInterface
     */
    private $addressRepository;

    /**
     * @param Context $context
     * @param Session $session
     * @param CartItemRepositoryInterface $cartItemRepository
     * @param ProductCollectionFactory $productCollectionFactory
     * @param SearchCriteriaBuilder $searchCriteriaBuilder
     * @param AddressRepositoryInterface $addressRepository
     */
    public function __construct(
        Context $context,
        Session $session,
        CartItemRepositoryInterface $cartItemRepository,
        ProductCollectionFactory $productCollectionFactory,
        SearchCriteriaBuilder $searchCriteriaBuilder,
        AddressRepositoryInterface $addressRepository
    )
    {
        $this->checkoutSession = $session;
        $this->cartItemRepository = $cartItemRepository;
        $this->productCollectionFactory = $productCollectionFactory;
        $this->searchCriteriaBuilder = $searchCriteriaBuilder;
        $this->addressRepository = $addressRepository;
        parent::__construct($context);
    }

    /**
     * @return bool
     * @throws \Magento\Framework\Exception\LocalizedException
     * @throws \Magento\Framework\Exception\NoSuchEntityException
     */
    public function isShippingCountryAU($countryCode = null)
    {
        return $countryCode == null ? $this->getCountryCode() === "AU" : $countryCode === "AU";
    }

    /**
     * @return mixed|string|null
     * @throws \Magento\Framework\Exception\LocalizedException
     * @throws \Magento\Framework\Exception\NoSuchEntityException
     */
    public function getCountryCode()
    {
        return $this->checkoutSession->getQuote()->getShippingAddress()->getCountryId();
    }

    /**
     *  Weight of all items in quote
     *
     * @return array
     */
    public function getOrderWeight()
    {
        $cartId = $this->checkoutSession->getQuoteId();

        /** @var \Magento\Quote\Api\Data\CartItemInterface[] */
        $cartItems = $this->cartItemRepository->getList($cartId);

        $fromPostcode = $this->fromPostCode();

        $weight = [];
        foreach ($cartItems as $item) {
            foreach ($fromPostcode as $sellerId) {
                foreach ($sellerId as $productId => $sellerPostcode) {
                    if ((!empty($itemId = $item->getProduct()->getId())
                            && !array_key_exists($itemId, $weight))
                        && $productId == $itemId) {
                        $weight[$itemId][$sellerPostcode] = $item->getProduct()->getWeight() * $item->getQty();
                    }
                }
            }
        }
        return $weight;
    }

   /**
     * Calculate total weight of all items in order
     *
     * @return int|mixed
     */
    public function getTotalWeight()
    {
        $orderWeights = array_values($this->getOrderParams());
        $totalWeight = 0;

        foreach ($orderWeights as $weight) {
            $totalWeight += $weight;
        }

        return $totalWeight;
    }

    /**
     * Get customer post code
     *
     * @return int
     */
    public function toPostCode()
    {
        return $this->checkoutSession->getQuote()->getShippingAddress()->getPostcode();
    }

    /**
     * Get sellers post code
     *
     * @return array
     * @throws Exception
     */
    public function fromPostCode()
    {
        $productInfo = $this->checkoutSession->getQuote()->getItems();

        $productId = [];
        foreach ($productInfo as $product) {
            $productId[] = $product->getProductId();
        }

        $sellersInfo = $this->productCollectionFactory->create()
            ->addFieldToSelect('seller_id')
            ->addFieldToSelect('mageproduct_id')
            ->addFieldToFilter('mageproduct_id', $productId);

        $sellersData = [];
        foreach ($sellersInfo->getData() as $data) {
            $sellersData[$data['seller_id']][$data['mageproduct_id']] = $data['mageproduct_id'];
        }

        $searchCriteria = $this->searchCriteriaBuilder->addFilter("parent_id", array_keys($sellersData), 'in')->create();
        $addressesInfo = $this->addressRepository->getList($searchCriteria)->getItems();

        $postcode = [];
        foreach ($addressesInfo as $address) {
            if (!empty($customerId = $address->getCustomerId())
                && !array_key_exists($customerId, $postcode)
                && $address->getCountryId() == "AU"
                && is_numeric($address->getPostcode())
            ) {
                foreach($sellersData[$customerId] as $productId => $productPostcode) {
                    $postcode[$customerId][$productId] = $address->getPostcode();
                }
            } elseif ($address->getCountryId() != "AU") {
                throw new \Exception("Seller is not from AU");
            }
        }
        return $postcode;
    }

    /**
     * Preparing order parameters for sending API request
     *
     * @return array
     */
    public function getOrderParams()
    {
        $orderWeight = $this->getOrderWeight();

        $orderParams = [];
        foreach ($orderWeight as $order) {
            foreach ($order as $postcode => $weight) {
                if (!array_key_exists($postcode, $orderParams)) {
                    $orderParams[$postcode] = $weight;
                } else {
                    $orderParams[$postcode] += $weight;
                }
            }
        }
        return $orderParams;
    }
}
