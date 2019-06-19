<?php declare(strict_types=1);

namespace Swag\BundleExample\Core\Checkout\Bundle\Cart;

use Shopware\Core\Checkout\Cart\Cart;
use Shopware\Core\Checkout\Cart\CartBehavior;
use Shopware\Core\Checkout\Cart\CartDataCollectorInterface;
use Shopware\Core\Checkout\Cart\CartProcessorInterface;
use Shopware\Core\Checkout\Cart\Delivery\Struct\DeliveryInformation;
use Shopware\Core\Checkout\Cart\LineItem\LineItem;
use Shopware\Core\Checkout\Cart\Price\AbsolutePriceCalculator;
use Shopware\Core\Checkout\Cart\Price\AmountCalculator;
use Shopware\Core\Checkout\Cart\Price\PercentagePriceCalculator;
use Shopware\Core\Checkout\Cart\Price\QuantityPriceCalculator;
use Shopware\Core\Checkout\Cart\Price\Struct\AbsolutePriceDefinition;
use Shopware\Core\Checkout\Cart\Price\Struct\CalculatedPrice;
use Shopware\Core\Checkout\Cart\Price\Struct\PercentagePriceDefinition;
use Shopware\Core\Checkout\Cart\Price\Struct\PriceCollection;
use Shopware\Core\Checkout\Cart\Price\Struct\QuantityPriceDefinition;
use Shopware\Core\Content\Product\ProductCollection;
use Shopware\Core\Content\Product\ProductEntity;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\Struct\StructCollection;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Svg\Tag\Line;
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

    public function __construct(
        EntityRepositoryInterface $bundleRepository,
        PercentagePriceCalculator $percentagePriceCalculator,
        AbsolutePriceCalculator $absolutePriceCalculator,
        QuantityPriceCalculator $quantityPriceCalculator
    )
    {
        $this->bundleRepository = $bundleRepository;
        $this->percentagePriceCalculator = $percentagePriceCalculator;
        $this->absolutePriceCalculator = $absolutePriceCalculator;
        $this->quantityPriceCalculator = $quantityPriceCalculator;
    }

    public function collect(StructCollection $data, Cart $original, SalesChannelContext $context, CartBehavior $behavior): void
    {
        $bundleLineItems = $original->getLineItems()
            ->filterFlatByType(self::TYPE);

        if (\count($bundleLineItems) === 0) {
            return;
        }

        $ids = array_map(function(LineItem $lineItem) {
            return $lineItem->getReferencedId();
        }, $bundleLineItems);

        $criteria = new Criteria($ids);
        $criteria->addAssociation('products');
        $bundles = $this->bundleRepository->search($criteria, $context->getContext())->getEntities();

        /** @var BundleEntity $bundle */
        foreach ($bundles as $bundle) {
            $data->set(self::DATA_KEY . $bundle->getId(), $bundle);

            $bundleLineItem = $original->get($bundle->getId());

            if (!$bundleLineItem) {
                continue;
            }

            if (!$bundleLineItem->getLabel()) {
                $bundleLineItem->setLabel($bundle->getName());
            }

            $bundleLineItem->setRemovable(true)->setStackable(true);

            $bundleLineItem->setDeliveryInformation(
                new DeliveryInformation(
                    (int) $bundle->getProducts()->first()->getStock(),
                    (float) $bundle->getProducts()->first()->getWeight(),
                    $bundle->getProducts()->first()->getDeliveryDate(),
                    $bundle->getProducts()->first()->getRestockDeliveryDate(),
                    $bundle->getProducts()->first()->getShippingFree()
                )
            );

            foreach ($bundle->getProducts()->getIds() as $productId) {
                if ($bundleLineItem->getChildren()->has($productId)) {
                    continue;
                }
                $productLineItem = new LineItem($productId, LineItem::PRODUCT_LINE_ITEM_TYPE, $productId);
                $productLineItem->setPayload(['id' => $productId]);

                $bundleLineItem->addChild($productLineItem);
            }

            if (!$this->getDiscount($bundleLineItem)) {
                $discount = $this->getDiscountLineItem($bundleLineItem, $bundle, $context);

                if ($discount) {
                    $bundleLineItem->addChild($discount);
                }
            }
        }
    }

    public function process(StructCollection $data, Cart $original, Cart $toCalculate, SalesChannelContext $context, CartBehavior $behavior): void
    {
        $bundleLineItems = $original->getLineItems()
            ->filterFlatByType(self::TYPE);

        if (\count($bundleLineItems) === 0) {
            return;
        }

        foreach ($bundleLineItems as $bundleLineItem) {
            foreach ($bundleLineItem->getChildren()->filterType(LineItem::PRODUCT_LINE_ITEM_TYPE) as $lineItem) {
                /** @var QuantityPriceDefinition $priceDefinition */
                $priceDefinition = $lineItem->getPriceDefinition();

                $lineItem->setPrice($this->quantityPriceCalculator->calculate($priceDefinition, $context));
            }

            /** @var BundleEntity $bundle */
            $bundle = $data->get(self::DATA_KEY . $bundleLineItem->getReferencedId());
            $discount = $this->getDiscount($bundleLineItem);

            $this->calculateDiscountPrice($discount, $bundleLineItem, $bundle, $context);

            $bundleLineItem->setPrice(
                $this->percentagePriceCalculator->calculate(100, $bundleLineItem->getChildren()->getPrices(), $context)
            );

            $toCalculate->add($bundleLineItem);
        }
    }

    private function getDiscountLineItem(LineItem $bundleLineItem, BundleEntity $bundleData, SalesChannelContext $context): ?LineItem
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
            ->setLabel($label);

        return $discount;
    }

    private function calculateDiscountPrice(?LineItem $discount, LineItem $bundleLineItem, BundleEntity $bundle, SalesChannelContext $context): void
    {
        if (!$discount) {
            return;
        }

        $childPrices = $bundleLineItem->getChildren()
            ->filterType(LineItem::PRODUCT_LINE_ITEM_TYPE)
            ->getPrices();

        switch ($bundle->getDiscountType()) {
            case self::DISCOUNT_TYPE_ABSOLUTE:
                $price = $this->absolutePriceCalculator->calculate(
                    $discount->getPriceDefinition()->getPrice(),
                    new PriceCollection($childPrices),
                    $context
                );
                break;

            case self::DISCOUNT_TYPE_PERCENTAGE:
                $price = $this->percentagePriceCalculator->calculate(
                    $discount->getPriceDefinition()->getPercentage(),
                    new PriceCollection($childPrices),
                    $context
                );
                break;

            default:
                throw new \RuntimeException('Invalid discount type.');
        }

        $discount->setPrice($price);
    }

    private function getDiscount(LineItem $bundle): ?LineItem
    {
        return $bundle->getChildren()->get($bundle->getReferencedId() . '-discount');
    }
}