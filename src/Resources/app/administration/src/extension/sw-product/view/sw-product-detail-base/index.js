import template from './sw-product-detail-base.html.twig';

const { Component } = Shopware;

Component.override('sw-product-detail-base', {
    template,

    computed: {
        productRepository() {
            return this.repositoryFactory.create('product');
        },
    },

    methods: {
        saveProduct() {
            if (this.product) {
                this.productRepository.save(this.product, Shopware.Context.api);
            }
        }
    }

});
