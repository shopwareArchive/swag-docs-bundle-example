import { Component } from 'src/core/shopware';

Component.override('sw-product-detail', {
    computed: {
        productCriteria() {
            const criteria = this.$super('productCriteria');
            criteria.addAssociation('bundles');

            return criteria;
        },
    }

});
