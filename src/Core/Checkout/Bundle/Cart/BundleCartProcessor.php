<?php declare(strict_types=1);

namespace Swag\BundleExample\Core\Checkout\Bundle\Cart;

use Shopware\Core\Checkout\Cart\Cart;
use Shopware\Core\Checkout\Cart\CartBehavior;
use Shopware\Core\Checkout\Cart\CartDataCollectorInterface;
use Shopware\Core\Checkout\Cart\CartProcessorInterface;
use Shopware\Core\Checkout\Cart\Delivery\Struct\DeliveryInformation;
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
use Shopware\Core\Checkout\Cart\Tax\PercentageTaxRuleBuilder;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Swag\BundleExample\Core\Content\Bundle\BundleCollection;
use Swag\BundleExample\Core\Content\Bundle\BundleEntity;

class BundleCartProcessor implements CartProcessorInterface, CartDataCollectorInterface
{
    public const TYPE = 'swagbundle';
    public const DISCOUNT_TYPE = 'swagbundle-discount';
    private const DATA_KEY = 'swag_bundle-';
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

    /**
     * @var PercentageTaxRuleBuilder
     */
    private $percentageTaxRuleBuilder;

    public function __construct(
        EntityRepositoryInterface $bundleRepository,
        PercentagePriceCalculator $percentagePriceCalculator,
        AbsolutePriceCalculator $absolutePriceCalculator,
        QuantityPriceCalculator $quantityPriceCalculator,
        PercentageTaxRuleBuilder $percentageTaxRuleBuilder
    )
    {
        $this->bundleRepository = $bundleRepository;
        $this->percentagePriceCalculator = $percentagePriceCalculator;
        $this->absolutePriceCalculator = $absolutePriceCalculator;
        $this->quantityPriceCalculator = $quantityPriceCalculator;
        $this->percentageTaxRuleBuilder = $percentageTaxRuleBuilder;
    }

    public function collect(CartDataCollection $data, Cart $original, SalesChannelContext $context, CartBehavior $behavior): void
    {
        $bundleLineItems = $original->getLineItems()
            ->filterType(self::TYPE);

        if (\count($bundleLineItems) === 0) {
            return;
        }

        $bundles = $this->fetchBundles($bundleLineItems, $data, $context);

        /** @var BundleEntity $bundle */
        foreach ($bundles as $bundle) {
            $data->set(self::DATA_KEY . $bundle->getId(), $bundle);

            $bundleLineItem = $original->get($bundle->getId());

            if (!$bundleLineItem) {
                continue;
            }

            $this->enrichBundle($bundleLineItem, $bundle);
            $this->addMissingProducts($bundleLineItem, $bundle);
            $this->addDiscount($bundleLineItem, $bundle, $context);
        }
    }

    public function process(CartDataCollection $data, Cart $original, Cart $toCalculate, SalesChannelContext $context, CartBehavior $behavior): void
    {
        $bundleLineItems = $original->getLineItems()
            ->filterType(self::TYPE);

        if (\count($bundleLineItems) === 0) {
            return;
        }

        foreach ($bundleLineItems as $bundleLineItem) {
            $this->calculateChildProductPrices($bundleLineItem, $context);
            $this->calculateDiscountPrice($bundleLineItem, $context);
            $this->calculateBundlePrice($bundleLineItem, $context);

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

        $bundleLineItem->setRemovable(true)
            ->setStackable(true)
            ->setDeliveryInformation(
                new DeliveryInformation(
                    (int)$bundle->getProducts()->first()->getStock(),
                    (float)$bundle->getProducts()->first()->getWeight(),
                    $bundle->getProducts()->first()->getDeliveryDate(),
                    $bundle->getProducts()->first()->getRestockDeliveryDate(),
                    $bundle->getProducts()->first()->getShippingFree()
                )
            )
            ->setQuantityInformation(new QuantityInformation());
    }

    private function addMissingProducts(LineItem $bundleLineItem, BundleEntity $bundle): void
    {
        foreach ($bundle->getProducts()->getIds() as $productId) {
            // if the bundleLineItem already contains the product we can skip it
            if ($bundleLineItem->getChildren()->has($productId)) {
                continue;
            }

            // the ProductCartProcessor will enrich the product further
            $productLineItem = new LineItem($productId, LineItem::PRODUCT_LINE_ITEM_TYPE, $productId);
            $productLineItem->setPayload(['id' => $productId]);

            $bundleLineItem->addChild($productLineItem);
        }
    }

    private function addDiscount(LineItem $bundleLineItem, BundleEntity $bundle, SalesChannelContext $context): void
    {
        if (!$this->getDiscount($bundleLineItem)) {
            $discount = $this->createDiscount($bundleLineItem, $bundle, $context);

            if ($discount) {
                $bundleLineItem->addChild($discount);
            }
        }
    }

    private function getDiscount(LineItem $bundle): ?LineItem
    {
        return $bundle->getChildren()->get($bundle->getReferencedId() . '-discount');
    }

    private function createDiscount(LineItem $bundleLineItem, BundleEntity $bundleData, SalesChannelContext $context): ?LineItem
    {
        if ($bundleData->getDiscount() === 0) {
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
            $bundleData->getId(),
            $bundleLineItem->getQuantity()
        );

        $discount->setPriceDefinition($priceDefinition)
            ->setLabel($label)
            ->setGood(false);

        return $discount;
    }

    private function calculateChildProductPrices(LineItem $bundleLineItem, SalesChannelContext $context): void
    {
        foreach ($bundleLineItem->getChildren()->filterType(LineItem::PRODUCT_LINE_ITEM_TYPE) as $lineItem) {
            /** @var QuantityPriceDefinition $priceDefinition */
            $priceDefinition = $lineItem->getPriceDefinition();

            $lineItem->setPrice($this->quantityPriceCalculator->calculate($priceDefinition, $context));
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
                    $context
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

    private function calculateBundlePrice(LineItem $bundleLineItem, SalesChannelContext $context): void
    {
        $unitPrice = $bundleLineItem->getChildren()->getPrices()->sum();
        $priceDefinition = new QuantityPriceDefinition(
            $unitPrice->getTotalPrice(),
            $this->percentageTaxRuleBuilder->buildRules($unitPrice),
            $context->getContext()->getCurrencyPrecision(),
            $bundleLineItem->getQuantity()
        );

        $bundleLineItem->setPriceDefinition(
            $priceDefinition
        );

        $bundleLineItem->setPrice(
            $this->quantityPriceCalculator->calculate($priceDefinition, $context)
        );
    }
}