<?php declare(strict_types=1);

namespace Swag\BundleExample\Core\Checkout\Bundle\Cart\Rule;

use Shopware\Core\Checkout\Cart\Rule\CartRuleScope;
use Shopware\Core\Framework\Rule\Rule;
use Shopware\Core\Framework\Rule\RuleScope;
use Swag\BundleExample\Core\Checkout\Bundle\Cart\BundleCartProcessor;

class CartContainsBundleRule extends Rule
{
    public function getName(): string
    {
        return 'swagBundleContainsBundle';
    }

    public function match(RuleScope $scope): bool
    {
        if (!$scope instanceof CartRuleScope) {
            return false;
        }

        $bundles = $scope->getCart()->getLineItems()->filterFlatByType(BundleCartProcessor::TYPE);

        if (\count($bundles) < 1) {
            return false;
        }

        return true;
    }

    public function getConstraints(): array
    {
        return [];
    }
}
