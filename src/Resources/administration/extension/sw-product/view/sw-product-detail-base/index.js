import { Component } from 'src/core/shopware';
import template from './sw-product-detail-base.html.twig';

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
                this.productRepository.save(this.product, this.context);
            }
        }
    }

});
