<?php declare(strict_types=1);

namespace Swag\BundleExample\Core\Checkout\Bundle\Cart;

use Shopware\Core\Checkout\Cart\Cart;
use Shopware\Core\Checkout\Cart\CartBehavior;
use Shopware\Core\Checkout\Cart\CartDataCollectorInterface;
use Shopware\Core\Checkout\Cart\CartProcessorInterface;
use Shopware\Core\Checkout\Cart\Delivery\Struct\DeliveryInformation;
use Shopware\Core\Checkout\Cart\Delivery\Struct\DeliveryTime;
use Shopware\Core\Checkout\Cart\LineItem\CartDataCollection;
use Shopware\Core\Checkout\Cart\LineItem\LineItem;
use Shopware\Core\Checkout\Cart\LineItem\LineItemCollection;
use Shopware\Core\Checkout\Cart\LineItem\QuantityInformation;
use Shopware\Core\Checkout\Cart\Price\AbsolutePriceCalculator;
use Shopware\Core\Checkout\Cart\Price\PercentagePriceCalculator;
use Shopware\Core\Checkout\Cart\Price\QuantityPriceCalculator;
use Shopware\Core\Checkout\Cart\Price\Struct\AbsolutePriceDefinition;
use Shopware\Core\Checkout\Cart\Price\Struct\PercentagePriceDefinition;
use Shopware\Core\Checkout\Cart\Price\Struct\QuantityPriceDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Swag\BundleExample\Core\Content\Bundle\BundleCollection;
use Swag\BundleExample\Core\Content\Bundle\BundleEntity;

class BundleCartProcessor implements CartProcessorInterface, CartDataCollectorInterface
{
    public const TYPE = 'swagbundle';
    public const DISCOUNT_TYPE = 'swagbundle-discount';
    public const DATA_KEY = 'swag_bundle-';
    public const DISCOUNT_TYPE_ABSOLUTE = 'absolute';
    public const DISCOUNT_TYPE_PERCENTAGE = 'percentage';

    /**
     * @var EntityRepositoryInterface
     */
    private $bundleRepository;

    /**
     * @var PercentagePriceCalculator
     */
    private $percentagePriceCalculator;

    /**
     * @var AbsolutePriceCalculator
     */
    private $absolutePriceCalculator;

    /**
     * @var QuantityPriceCalculator
     */
    private $quantityPriceCalculator;

    public function __construct(
        EntityRepositoryInterface $bundleRepository,
        PercentagePriceCalculator $percentagePriceCalculator,
        AbsolutePriceCalculator $absolutePriceCalculator,
        QuantityPriceCalculator $quantityPriceCalculator
    ) {
        $this->bundleRepository = $bundleRepository;
        $this->percentagePriceCalculator = $percentagePriceCalculator;
        $this->absolutePriceCalculator = $absolutePriceCalculator;
        $this->quantityPriceCalculator = $quantityPriceCalculator;
    }

    public function collect(CartDataCollection $data, Cart $original, SalesChannelContext $context, CartBehavior $behavior): void
    {
        /** @var LineItemCollection $bundleLineItems */
        $bundleLineItems = $original->getLineItems()->filterType(self::TYPE);

        // no bundles in cart? exit
        if (\count($bundleLineItems) === 0) {
            return;
        }

        // fetch missing bundle information from database
        $bundles = $this->fetchBundles($bundleLineItems, $data, $context);

        foreach ($bundles as $bundle) {
            // ensure all line items have a data entry
            $data->set(self::DATA_KEY . $bundle->getId(), $bundle);
        }

        foreach ($bundleLineItems as $bundleLineItem) {
            $bundle = $data->get(self::DATA_KEY . $bundleLineItem->getReferencedId());

            // enrich bundle information with quantity and delivery information
            $this->enrichBundle($bundleLineItem, $bundle);

            // add bundle products which are not already assigned
            $this->addMissingProducts($bundleLineItem, $bundle);

            // add bundle discount if not already assigned
            $this->addDiscount($bundleLineItem, $bundle, $context);
        }
    }

    public function process(CartDataCollection $data, Cart $original, Cart $toCalculate, SalesChannelContext $context, CartBehavior $behavior): void
    {
        // collect all bundle in cart
        /** @var LineItemCollection $bundleLineItems */
        $bundleLineItems = $original->getLineItems()
            ->filterType(self::TYPE);

        if (\count($bundleLineItems) === 0) {
            return;
        }

        foreach ($bundleLineItems as $bundleLineItem) {
            // first calculate all bundle product prices
            $this->calculateChildProductPrices($bundleLineItem, $context);

            // after the product prices calculated, we can calculate the discount
            $this->calculateDiscountPrice($bundleLineItem, $context);

            // at last we have to set the total price for the root line item (the bundle)
            $bundleLineItem->setPrice(
                $bundleLineItem->getChildren()->getPrices()->sum()
            );

            // afterwards we can move the bundle to the new cart
            $toCalculate->add($bundleLineItem);
        }
    }

    /**
     * Fetches all Bundles that are not already stored in data
     */
    private function fetchBundles(LineItemCollection $bundleLineItems, CartDataCollection $data, SalesChannelContext $context): BundleCollection
    {
        $bundleIds = $bundleLineItems->getReferenceIds();

        $filtered = [];
        foreach ($bundleIds as $bundleId) {
            // If data already contains the bundle we don't need to fetch it again
            if ($data->has(self::DATA_KEY . $bundleId)) {
                continue;
            }

            $filtered[] = $bundleId;
        }

        $criteria = new Criteria($filtered);
        $criteria->addAssociation('products');
        /** @var BundleCollection $bundles */
        $bundles = $this->bundleRepository->search($criteria, $context->getContext())->getEntities();

        return $bundles;
    }

    private function enrichBundle(LineItem $bundleLineItem, BundleEntity $bundle): void
    {
        if (!$bundleLineItem->getLabel()) {
            $bundleLineItem->setLabel($bundle->getName());
        }

        $bundleProducts = $bundle->getProducts();
        if ($bundleProducts === null) {
            throw new \RuntimeException(sprintf('Bundle "%s" has no products', $bundle->getName()));
        }

        $firstBundleProduct = $bundleProducts->first();
        if ($firstBundleProduct === null) {
            throw new \RuntimeException(sprintf('Bundle "%s" has no products', $bundle->getName()));
        }

        $firstBundleProductDeliveryTime = $firstBundleProduct->getDeliveryTime();
        if ($firstBundleProductDeliveryTime !== null) {
            $firstBundleProductDeliveryTime = DeliveryTime::createFromEntity($firstBundleProductDeliveryTime);
        }

        $bundleLineItem->setRemovable(true)
            ->setStackable(true)
            ->setDeliveryInformation(
                new DeliveryInformation(
                    $firstBundleProduct->getStock(),
                    (float) $firstBundleProduct->getWeight(),
                    (bool) $firstBundleProduct->getShippingFree(),
                    $firstBundleProduct->getRestockTime(),
                    $firstBundleProductDeliveryTime
                )
            )
            ->setQuantityInformation(new QuantityInformation());
    }

    private function addMissingProducts(LineItem $bundleLineItem, BundleEntity $bundle): void
    {
        $bundleProducts = $bundle->getProducts();
        if ($bundleProducts === null) {
            throw new \RuntimeException(sprintf('Bundle %s has no products', $bundle->getName()));
        }

        foreach ($bundleProducts->getIds() as $productId) {
            // if the bundleLineItem already contains the product we can skip it
            if ($bundleLineItem->getChildren()->has($productId)) {
                continue;
            }

            // the ProductCartProcessor will enrich the product further
            $productLineItem = new LineItem($productId, LineItem::PRODUCT_LINE_ITEM_TYPE, $productId);

            $bundleLineItem->addChild($productLineItem);
        }
    }

    private function addDiscount(LineItem $bundleLineItem, BundleEntity $bundle, SalesChannelContext $context): void
    {
        if ($this->getDiscount($bundleLineItem)) {
            return;
        }

        $discount = $this->createDiscount($bundle, $context);

        if ($discount) {
            $bundleLineItem->addChild($discount);
        }
    }

    private function getDiscount(LineItem $bundle): ?LineItem
    {
        return $bundle->getChildren()->get($bundle->getReferencedId() . '-discount');
    }

    private function createDiscount(BundleEntity $bundleData, SalesChannelContext $context): ?LineItem
    {
        if ($bundleData->getDiscount() === 0.0) {
            return null;
        }

        switch ($bundleData->getDiscountType()) {
            case self::DISCOUNT_TYPE_ABSOLUTE:
                $priceDefinition = new AbsolutePriceDefinition($bundleData->getDiscount() * -1, $context->getContext()->getCurrencyPrecision());
                $label = 'Absolute bundle voucher';
                break;

            case self::DISCOUNT_TYPE_PERCENTAGE:
                $priceDefinition = new PercentagePriceDefinition($bundleData->getDiscount() * -1, $context->getContext()->getCurrencyPrecision());
                $label = sprintf('Percental bundle voucher (%s%%)', $bundleData->getDiscount());
                break;

            default:
                throw new \RuntimeException('Invalid discount type.');
        }

        $discount = new LineItem(
            $bundleData->getId() . '-discount',
            self::DISCOUNT_TYPE,
            $bundleData->getId()
        );

        $discount->setPriceDefinition($priceDefinition)
            ->setLabel($label)
            ->setGood(false);

        return $discount;
    }

    private function calculateChildProductPrices(LineItem $bundleLineItem, SalesChannelContext $context): void
    {
        /** @var LineItemCollection $products */
        $products = $bundleLineItem->getChildren()->filterType(LineItem::PRODUCT_LINE_ITEM_TYPE);

        foreach ($products as $product) {
            $priceDefinition = $product->getPriceDefinition();
            if ($priceDefinition === null || !$priceDefinition instanceof QuantityPriceDefinition) {
                throw new \RuntimeException(sprintf('Product "%s" has invalid price definition', $product->getLabel()));
            }

            $product->setPrice(
                $this->quantityPriceCalculator->calculate($priceDefinition, $context)
            );
        }
    }

    private function calculateDiscountPrice(LineItem $bundleLineItem, SalesChannelContext $context): void
    {
        $discount = $this->getDiscount($bundleLineItem);

        if (!$discount) {
            return;
        }

        $childPrices = $bundleLineItem->getChildren()
            ->filterType(LineItem::PRODUCT_LINE_ITEM_TYPE)
            ->getPrices();

        $priceDefinition = $discount->getPriceDefinition();

        if (!$priceDefinition) {
            return;
        }

        switch (\get_class($priceDefinition)) {
            case AbsolutePriceDefinition::class:
                $price = $this->absolutePriceCalculator->calculate(
                    $priceDefinition->getPrice(),
                    $childPrices,
                    $context,
                    $bundleLineItem->getQuantity()
                );
                break;

            case PercentagePriceDefinition::class:
                $price = $this->percentagePriceCalculator->calculate(
                    $priceDefinition->getPercentage(),
                    $childPrices,
                    $context
                );
                break;

            default:
                throw new \RuntimeException('Invalid discount type.');
        }

        $discount->setPrice($price);
    }
}
