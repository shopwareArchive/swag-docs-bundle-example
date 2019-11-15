import '../core/component/swag-cart-contains-bundle';

const { Application } = Shopware;

Application.addServiceProviderDecorator('ruleConditionDataProviderService', (ruleConditionService) => {
    ruleConditionService.addCondition('swagBundleContainsBundle', {
        component: 'swag-cart-contains-bundle',
        label: 'sw-condition.condition.cartContainsBundle.label',
        scopes: ['cart']
    });

    return ruleConditionService;
});
