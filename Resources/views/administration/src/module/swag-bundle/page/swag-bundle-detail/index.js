import { Component } from 'src/core/shopware';
import template from './swag-bundle-detail.html.twig';

Component.register('swag-bundle-detail', {

    template,

    inject: [
        'repositoryFactory',
        'context'
    ],

    data() {
        return {
            repository: null,
            bundle: null,
            isLoading: false
        };
    },

    created() {
        this.repository = this.repositoryFactory.create('product_manufacturer');

        this.getBundle();
    },

    methods: {
        getBundle() {
            const id = this.$route.params.id;

            this.repository
                .get(id, this.context)
                .then((entity) => {
                    this.bundle = entity;
                });
        },

        onClickSave() {
            // sends the request immediately
            this.isLoading = true;

            this.repository
                .save(this.bundle, this.context)
                .then(() => {
                    // the entity is stateless, the new data has be fetched from the server, if required
                    this.getBundle();
                    this.isLoading = false;
                });
        }
    }
});
