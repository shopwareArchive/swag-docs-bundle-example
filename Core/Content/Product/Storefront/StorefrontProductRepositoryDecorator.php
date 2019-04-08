<?php


namespace SwagBundleExample\Core\Content\Product\Storefront;


use Shopware\Core\Content\Product\ProductCollection;
use Shopware\Core\Content\Product\Storefront\StorefrontProductRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\System\SalesChannel\SalesChannelContext;

class StorefrontProductRepositoryDecorator extends StorefrontProductRepository
{
    public function read(Criteria $criteria, SalesChannelContext $context): ProductCollection
    {
        $criteria->addAssociation('bundles', (new Criteria())->addAssociation('products'));
        return parent::read($criteria, $context);
    }
}