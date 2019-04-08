<?php declare(strict_types=1);


namespace SwagBundleExample\Core\Checkout\Bundle;


use Shopware\Core\Framework\Struct\Struct;

class BundleFetchDefinition extends Struct
{
    /**
     * @var string[]
     */
    protected $ids;

    /**
     * @param string[] $ids
     */
    public function __construct(array $ids)
    {
        $this->ids = $ids;
    }

    /**
     * @return string[]
     */
    public function getIds(): array
    {
        return $this->ids;
    }
}
