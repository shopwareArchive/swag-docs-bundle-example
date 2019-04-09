<?php declare(strict_types=1);

namespace ShopwareLabs\Plugin\SwagBundleExample;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Plugin;
use Shopware\Core\Framework\Plugin\Context\UninstallContext;

class SwagBundleExample extends Plugin
{
    public function getAdministrationEntryPath(): string
    {
        return 'Administration';
    }

    public function uninstall(UninstallContext $context): void
    {
        parent::uninstall($context);

        if ($context->keepUserData()) {
            return;
        }

        $connection = $this->container->get(Connection::class);

        $connection->executeQuery('DROP TABLE IF EXISTS `swag_bundle_product`');
        $connection->executeQuery('DROP TABLE IF EXISTS `swag_bundle_translation`');
        $connection->executeQuery('DROP TABLE IF EXISTS `swag_bundle`');
    }
}
