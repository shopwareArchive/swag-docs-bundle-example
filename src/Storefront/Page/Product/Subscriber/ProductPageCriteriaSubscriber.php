<?php declare(strict_types=1);

namespace Swag\BundleExample\Storefront\Page\Product\Subscriber;

use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Storefront\Page\Product\ProductPageCriteriaEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class ProductPageCriteriaSubscriber implements EventSubscriberInterface
{
    public static function getSubscribedEvents(): array
    {
        return [
            ProductPageCriteriaEvent::NAME => 'onProductCriteriaLoaded'
        ];
    }

    public function onProductCriteriaLoaded(ProductPageCriteriaEvent $event): void
    {
        $event->getCriteria()->addAssociationPath('bundles.products.cover');
    }
}
