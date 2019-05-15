<?php declare(strict_types=1);

namespace Swag\BundleExample\Core\Content\Product;

use Shopware\Core\Content\Product\ProductDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\EntityExtensionInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Field\ManyToManyAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\FieldCollection;
use Swag\BundleExample\Core\Content\Bundle\Aggregate\BundleProduct\BundleProductDefinition;
use Swag\BundleExample\Core\Content\Bundle\BundleDefinition;

class ProductExtension implements EntityExtensionInterface
{
    /**
     * Allows to add fields to an entity.
     *
     * To load fields by your own, add the \Swag\BundleExample\Core\Framework\DataAbstractionLayer\Field\Flag\Deferred flag to the field.
     * Added fields should have the \Swag\BundleExample\Core\Framework\DataAbstractionLayer\Field\Flag\Extension which tells the ORM that this data
     * is not include in the struct and collection classes
     */
    public function extendFields(FieldCollection $collection): void
    {
        $collection->add(
            new ManyToManyAssociationField('bundles', BundleDefinition::class, BundleProductDefinition::class, 'product_id', 'bundle_id')
        );
    }

    /**
     * Defines which entity definition should be extended by this class
     */
    public function getDefinitionClass(): string
    {
        return ProductDefinition::class;
    }
}
