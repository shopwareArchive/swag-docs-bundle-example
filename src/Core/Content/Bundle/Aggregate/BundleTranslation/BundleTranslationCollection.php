<?php declare(strict_types=1);

namespace Swag\BundleExample\Core\Content\Bundle\Aggregate\BundleTranslation;

use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;

/**
 * @method void                         add(BundleTranslationEntity $entity)
 * @method void                         set(string $key, BundleTranslationEntity $entity)
 * @method BundleTranslationEntity[]    getIterator()
 * @method BundleTranslationEntity[]    getElements()
 * @method BundleTranslationEntity|null get(string $key)
 * @method BundleTranslationEntity|null first()
 * @method BundleTranslationEntity|null last()
 */
class BundleTranslationCollection extends EntityCollection
{
    protected function getExpectedClass(): string
    {
        return BundleTranslationEntity::class;
    }
}
