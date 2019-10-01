<?php declare(strict_types=1);

namespace Swag\BundleExample\Core\Content\Bundle\Aggregate\BundleTranslation;

use Shopware\Core\Framework\DataAbstractionLayer\TranslationEntity;
use Swag\BundleExample\Core\Content\Bundle\BundleEntity;

class BundleTranslationEntity extends TranslationEntity
{
    /**
     * @var string
     */
    protected $bundleId;

    /**
     * @var string|null
     */
    protected $name;

    /**
     * @var BundleEntity
     */
    protected $bundle;

    public function getBundleId(): string
    {
        return $this->bundleId;
    }

    public function setBundleId(string $bundleId): void
    {
        $this->bundleId = $bundleId;
    }

    public function getName(): ?string
    {
        return $this->name;
    }

    public function setName(string $name): void
    {
        $this->name = $name;
    }

    public function getBundle(): BundleEntity
    {
        return $this->bundle;
    }

    public function setBundle(BundleEntity $bundle): void
    {
        $this->bundle = $bundle;
    }
}
