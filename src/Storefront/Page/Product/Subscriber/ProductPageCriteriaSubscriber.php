<?php declare(strict_types=1);

namespace ShopwareLabs\Plugin\SwagBundleExample\Storefront\Page\Product\Subscriber;

use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Storefront\Page\Product\ProductPageCriteriaEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class ProductPageCriteriaSubscriber implements EventSubscriberInterface
{
    public static function getSubscribedEvents()
    {
        return [
            ProductPageCriteriaEvent::NAME => 'onProductLoaded'
        ];
    }

    public function onProductLoaded(ProductPageCriteriaEvent $event)
    {
        $event->getCriteria()->addAssociation('bundles', (new Criteria())->addAssociation('products'));
    }
}