<?php declare(strict_types=1);

namespace ShopwareLabs\Plugin\SwagBundleExample\Core\Checkout\Bundle;

use Shopware\Core\Checkout\Cart\Cart;
use Shopware\Core\Checkout\Cart\CartBehavior;
use Shopware\Core\Checkout\Cart\CollectorInterface;
use Shopware\Core\Checkout\Cart\LineItem\LineItem;
use Shopware\Core\Checkout\Cart\Price\Struct\AbsolutePriceDefinition;
use Shopware\Core\Checkout\Cart\Price\Struct\PercentagePriceDefinition;
use Shopware\Core\Content\Product\Cart\ProductCollector;
use Shopware\Core\Content\Product\Cart\Struct\ProductFetchDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\Struct\StructCollection;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use ShopwareLabs\Plugin\SwagBundleExample\Core\Content\Bundle\BundleCollection;
use ShopwareLabs\Plugin\SwagBundleExample\Core\Content\Bundle\BundleEntity;

class BundleCollector implements CollectorInterface
{
    public const TYPE = 'swagbundle';
    public const DATA_KEY = 'swag_bundles';
    public const DISCOUNT_TYPE_ABSOLUTE = 'absolute';
    public const DISCOUNT_TYPE_PERCENTAGE = 'percentage';
    /**
     * @var EntityRepositoryInterface
     */
    private $bundleRepository;

    public function __construct(EntityRepositoryInterface $bundleRepository)
    {
        $this->bundleRepository = $bundleRepository;
    }

    public function prepare(StructCollection $definitions, Cart $cart, SalesChannelContext $context, CartBehavior $behavior): void
    {
        $bundleLineItems = $cart->getLineItems()->filterType(self::TYPE);

        if ($bundleLineItems->count() === 0) {
            return;
        }

        $definitions->add(new BundleFetchDefinition($bundleLineItems->getKeys()));
    }

    public function collect(StructCollection $fetchDefinitions, StructCollection $data, Cart $cart, SalesChannelContext $context, CartBehavior $behavior): void
    {
        $bundleDefinitions = $fetchDefinitions->filterInstance(BundleFetchDefinition::class);

        if ($bundleDefinitions->count() === 0) {
            return;
        }

        $ids = [[]];
        /** @var BundleFetchDefinition $fetchDefinition */
        foreach ($bundleDefinitions as $fetchDefinition) {
            $ids[] = $fetchDefinition->getIds();
        }

        $ids = array_unique(array_merge(...$ids));

        // TODO the product relation is currently loaded in basic but only the product ids are really needed!
        $criteria = new Criteria($ids);
        $criteria->addAssociation('products');
        $bundles = $this->bundleRepository->search($criteria, $context->getContext())->getEntities();

        $productIds = [[]];
        /** @var BundleEntity $bundle */
        foreach ($bundles as $bundle) {
            $productIds[] = $bundle->getProducts()->getIds();

            $bundleLineItem = $cart->get($bundle->getId());

            if (!$bundleLineItem) {
                continue;
            }

            foreach ($bundle->getProducts()->getIds() as $productId) {
                if ($bundleLineItem->getChildren()->has($productId)) {
                    continue;
                }
                $productLineItem = new LineItem($productId, ProductCollector::LINE_ITEM_TYPE);
                $productLineItem->setPayload(['id' => $productId]);
                $bundleLineItem->addChild($productLineItem);
            }

            $bundleLineItem->setRemovable(true)->setStackable(true);
        }

        $productIds = array_merge(...$productIds);

        $fetchDefinitions->add(new ProductFetchDefinition($productIds));
        $data->set(self::DATA_KEY, $bundles);
    }

    public function enrich(StructCollection $data, Cart $cart, SalesChannelContext $context, CartBehavior $behavior): void
    {
        if (!$data->has(self::DATA_KEY)) {
            return;
        }

        /** @var BundleCollection $bundles */
        $bundles = $data->get(self::DATA_KEY);

        $bundleLineItems = $cart->getLineItems()->filterType(self::TYPE);
        if (count($bundleLineItems) === 0) {
            return;
        }

        /** @var LineItem $bundleLineItem */
        foreach ($bundleLineItems as $bundleLineItem) {
            if ($this->isComplete($bundleLineItem)) {
                continue;
            }

            $id = $bundleLineItem->getKey();

            $bundle = $bundles->get($id);

            if(!$bundle) {
                continue;
            }

            if (!$bundleLineItem->getLabel()) {
                $bundleLineItem->setLabel($bundle->getName());
            }

            $bundleLineItem->getChildren()->add($this->calculateBundleDiscount($bundleLineItem, $bundle, $context));
        }
    }

    private function calculateBundleDiscount(LineItem $bundleLineItem, BundleEntity $bundleData, SalesChannelContext $context): ?LineItem
    {
        if ($bundleData->getDiscount() === 0) {
            return null;
        }

        switch ($bundleData->getDiscountType()) {
            case self::DISCOUNT_TYPE_ABSOLUTE:
                $price = new AbsolutePriceDefinition($bundleData->getDiscount() * -1, $context->getContext()->getCurrencyPrecision());
                $label = 'Absolute bundle voucher';
                break;

            case self::DISCOUNT_TYPE_PERCENTAGE:
                $price = new PercentagePriceDefinition($bundleData->getDiscount() * -1, $context->getContext()->getCurrencyPrecision());
                $label = sprintf('Percental bundle voucher (%s%%)', $bundleData->getDiscount());
                break;
        }

        $discount = new LineItem(
            $bundleData->getId() . '-discount',
            self::TYPE,
            $bundleLineItem->getQuantity()
        );

        $discount->setPriceDefinition($price)->setLabel($label);

        return $discount;
    }

    private function isComplete(LineItem $lineItem)
    {
        return $lineItem->getLabel()
            && $lineItem->getDescription()
            && $lineItem->getChildren() !== null
            && $lineItem->getChildren()->get($lineItem->getKey() . '-discount')
            && $lineItem->getChildren()->get($lineItem->getKey() . '-discount')->getPriceDefinition();
    }

}
