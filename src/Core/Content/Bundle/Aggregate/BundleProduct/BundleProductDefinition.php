<?php declare(strict_types=1);

namespace ShopwareLabs\Plugin\SwagBundleExample\Core\Content\Bundle\Aggregate\BundleProduct;

use Shopware\Core\Content\Product\ProductDefinition;
use Shopware\Core\Content\Product\SalesChannel\SalesChannelProductDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\Field\CreatedAtField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\FkField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\PrimaryKey;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\Required;
use Shopware\Core\Framework\DataAbstractionLayer\Field\ManyToOneAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\ReferenceVersionField;
use Shopware\Core\Framework\DataAbstractionLayer\FieldCollection;
use Shopware\Core\Framework\DataAbstractionLayer\MappingEntityDefinition;
use ShopwareLabs\Plugin\SwagBundleExample\Core\Content\Bundle\BundleDefinition;

class BundleProductDefinition extends MappingEntityDefinition
{
    public static function getEntityName(): string
    {
        return 'swag_bundle_product';
    }

    protected static function defineFields(): FieldCollection
    {
        return new FieldCollection([
            (new FkField('bundle_id', 'bundleId', BundleDefinition::class))->addFlags(new PrimaryKey(), new Required()),
            (new FkField('product_id', 'productId', SalesChannelProductDefinition::class))->addFlags(new PrimaryKey(), new Required()),
            (new ReferenceVersionField(SalesChannelProductDefinition::class))->addFlags(new PrimaryKey(), new Required()),
            new CreatedAtField(),
            new ManyToOneAssociationField('bundle', 'bundle_id', BundleDefinition::class),
            new ManyToOneAssociationField('product', 'product_id', SalesChannelProductDefinition::class),
        ]);
    }
}
