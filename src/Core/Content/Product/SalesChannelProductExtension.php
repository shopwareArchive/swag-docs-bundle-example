<?php declare(strict_types=1);

namespace Swag\BundleExample\Core\Content\Product;

use Shopware\Core\Content\Product\SalesChannel\SalesChannelProductDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\EntityExtensionInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Field\ManyToManyAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\FieldCollection;
use Swag\BundleExample\Core\Content\Bundle\Aggregate\BundleProduct\BundleProductDefinition;
use Swag\BundleExample\Core\Content\Bundle\BundleDefinition;

class SalesChannelProductExtension implements EntityExtensionInterface
{
    public function extendFields(FieldCollection $collection): void
    {
        $collection->add(
            new ManyToManyAssociationField(
                'bundles',
                BundleDefinition::class,
                BundleProductDefinition::class,
                'product_id',
                'bundle_id'
            )
        );
    }

    public function getDefinitionClass(): string
    {
        return SalesChannelProductDefinition::class;
    }
}
